<script setup>
import { computed, ref, watch } from 'vue'
import { Head, Link, router, usePage } from '@inertiajs/vue3'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import ModalTicketDetails from '@/Pages/TicketsV2/ModalComponents/ModalTicketDetails.vue'
import axios from 'axios'

const page = usePage()

const props = defineProps({
  events: {
    type: Array,
    default: () => [],
  },
  kpis: {
    type: Object,
    default: () => ({
      total_events: 0,
      this_month_count: 0,
      ongoing_today_count: 0,
      upcoming_count: 0,
      multi_day_count: 0,
      completed_count: 0,
    }),
  },
  statusCounts: {
    type: Object,
    default: () => ({
      All: 0,
      New: 0,
      Pending: 0,
      'In Progress': 0,
      Completed: 0,
      Cancelled: 0,
      Closed: 0,
    }),
  },
  filters: {
    type: Object,
    default: () => ({
      month: '',
      status: 'All',
      division: 'All',
      search: '',
      staff: 'All',
      view: 'month',
    }),
  },
  activeStaff: {
    type: Array,
    default: () => [],
  },
})

// ── State ───────────────────────────────────────────────────────────────────
const localEvents = ref([...props.events])
const localKpis = ref({ ...props.kpis })
const localStatusCounts = ref({ ...props.statusCounts })
const isLoading = ref(false)

const currentMonthStr = ref(props.filters.month || new Date().toISOString().slice(0, 7))
const currentView = ref(props.filters.view || 'month') // 'month' | 'week' | 'agenda'
const statusFilter = ref(props.filters.status || 'All')
const divisionFilter = ref(props.filters.division || 'All')
const staffFilter = ref(props.filters.staff || 'All')
const searchQuery = ref(props.filters.search || '')

// Active date focus (for Week view or selecting a specific day)
const selectedDate = ref(new Date().toISOString().slice(0, 10))

// Modal states
const showDetailsModal = ref(false)
const selectedTicketForModal = ref(null)

const showEventPreviewModal = ref(false)
const activePreviewEvent = ref(null)

// Watch props sync
watch(
  () => props.events,
  (newVal) => {
    localEvents.value = [...newVal]
  }
)
watch(
  () => props.kpis,
  (newVal) => {
    localKpis.value = { ...newVal }
  }
)
watch(
  () => props.statusCounts,
  (newVal) => {
    localStatusCounts.value = { ...newVal }
  }
)

// ── Date Computations ───────────────────────────────────────────────────────
const todayStr = computed(() => new Date().toISOString().slice(0, 10))

const currentMonthDate = computed(() => {
  const [year, month] = currentMonthStr.value.split('-').map(Number)
  return new Date(year, month - 1, 1)
})

const monthYearHeading = computed(() => {
  return currentMonthDate.value.toLocaleDateString('en-US', {
    month: 'long',
    year: 'numeric',
  })
})

const weekHeading = computed(() => {
  const curr = new Date(selectedDate.value)
  const first = new Date(curr.setDate(curr.getDate() - curr.getDay()))
  const last = new Date(first)
  last.setDate(first.getDate() + 6)

  const opt = { month: 'short', day: 'numeric' }
  const firstStr = first.toLocaleDateString('en-US', opt)
  const lastStr = last.toLocaleDateString('en-US', { ...opt, year: 'numeric' })
  return `${firstStr} – ${lastStr}`
})

// ── Google Calendar Month Grid Generator ────────────────────────────────────
const monthGridWeeks = computed(() => {
  const [year, month] = currentMonthStr.value.split('-').map(Number)
  const firstDayOfMonth = new Date(year, month - 1, 1)
  const lastDayOfMonth = new Date(year, month, 0)

  // Start on Sunday before or on the 1st
  const startDate = new Date(firstDayOfMonth)
  startDate.setDate(startDate.getDate() - startDate.getDay())

  // End on Saturday after or on the last day
  const endDate = new Date(lastDayOfMonth)
  if (endDate.getDay() !== 6) {
    endDate.setDate(endDate.getDate() + (6 - endDate.getDay()))
  }

  const weeks = []
  let currentDay = new Date(startDate)

  while (currentDay <= endDate) {
    const weekDays = []
    for (let i = 0; i < 7; i++) {
      const dateStr = currentDay.toISOString().slice(0, 10)
      weekDays.push({
        dateStr,
        dayNumber: currentDay.getDate(),
        isCurrentMonth: currentDay.getMonth() === month - 1,
        isToday: dateStr === todayStr.value,
        isSelected: dateStr === selectedDate.value,
      })
      currentDay.setDate(currentDay.getDate() + 1)
    }

    const weekStart = weekDays[0].dateStr
    const weekEnd = weekDays[6].dateStr

    // Filter events overlapping this week
    const weekEvents = filteredEvents.value.filter((ev) => {
      const evStart = ev.event_start_date
      const evEnd = ev.event_end_date
      return evStart <= weekEnd && evEnd >= weekStart
    })

    // Sort: multi-day first (descending span), then by start date
    weekEvents.sort((a, b) => {
      const spanA = Math.min(a.event_end_date, weekEnd) >= Math.max(a.event_start_date, weekStart)
      const spanB = Math.min(b.event_end_date, weekEnd) >= Math.max(b.event_start_date, weekStart)
      return b.duration_days - a.duration_days || a.event_start_date.localeCompare(b.event_start_date)
    })

    // Track/Lane Allocation (prevent overlap)
    const trackOccupancy = [] // trackOccupancy[laneIndex] = boolean[7]
    const allocatedSegments = []

    weekEvents.forEach((ev) => {
      const segStart = ev.event_start_date < weekStart ? weekStart : ev.event_start_date
      const segEnd = ev.event_end_date > weekEnd ? weekEnd : ev.event_end_date

      const startCol = weekDays.findIndex((d) => d.dateStr === segStart)
      const endCol = weekDays.findIndex((d) => d.dateStr === segEnd)

      if (startCol === -1 || endCol === -1) return

      const span = endCol - startCol + 1
      const isStart = ev.event_start_date >= weekStart
      const isEnd = ev.event_end_date <= weekEnd

      // Find lowest track that has all columns free
      let chosenTrack = 0
      while (true) {
        if (!trackOccupancy[chosenTrack]) {
          trackOccupancy[chosenTrack] = new Array(7).fill(false)
        }

        let isFree = true
        for (let col = startCol; col <= endCol; col++) {
          if (trackOccupancy[chosenTrack][col]) {
            isFree = false
            break
          }
        }

        if (isFree) {
          for (let col = startCol; col <= endCol; col++) {
            trackOccupancy[chosenTrack][col] = true
          }
          break
        }
        chosenTrack++
      }

      allocatedSegments.push({
        event: ev,
        startCol,
        endCol,
        span,
        isStart,
        isEnd,
        track: chosenTrack,
      })
    })

    const maxTrack = trackOccupancy.length > 0 ? trackOccupancy.length : 1

    weeks.push({
      weekStart,
      weekEnd,
      days: weekDays,
      segments: allocatedSegments,
      maxTrack,
    })
  }

  return weeks
})

// ── Week View Days ──────────────────────────────────────────────────────────
const weekViewDays = computed(() => {
  const curr = new Date(selectedDate.value)
  const first = new Date(curr.setDate(curr.getDate() - curr.getDay()))

  const days = []
  for (let i = 0; i < 7; i++) {
    const d = new Date(first)
    d.setDate(first.getDate() + i)
    const dateStr = d.toISOString().slice(0, 10)

    const dayEvents = filteredEvents.value.filter((ev) => {
      return ev.event_start_date <= dateStr && ev.event_end_date >= dateStr
    })

    days.push({
      dateStr,
      dayName: d.toLocaleDateString('en-US', { weekday: 'short' }),
      dayNumber: d.getDate(),
      isToday: dateStr === todayStr.value,
      isSelected: dateStr === selectedDate.value,
      events: dayEvents,
    })
  }
  return days
})

// ── Filtered Events ─────────────────────────────────────────────────────────
const filteredEvents = computed(() => {
  let list = localEvents.value

  // Status Filter
  if (statusFilter.value && statusFilter.value !== 'All') {
    list = list.filter((ev) => ev.ticket_status?.toLowerCase() === statusFilter.value.toLowerCase())
  }

  // Division Filter
  if (divisionFilter.value && divisionFilter.value !== 'All') {
    list = list.filter((ev) => {
      const div = ev.client_division_agency || ''
      const label = ev.client_division_label || ''
      return div === divisionFilter.value || label === divisionFilter.value
    })
  }

  // Staff Filter
  if (staffFilter.value && staffFilter.value !== 'All') {
    list = list.filter((ev) => {
      return (
        ev.ticket_assigned_staff === staffFilter.value ||
        ev.ticket_attended_by === staffFilter.value
      )
    })
  }

  // Search Filter
  if (searchQuery.value.trim()) {
    const q = searchQuery.value.toLowerCase().trim()
    list = list.filter((ev) => {
      return (
        ev.client_referenceNo?.toLowerCase().includes(q) ||
        ev.client_name?.toLowerCase().includes(q) ||
        ev.event_venue?.toLowerCase().includes(q) ||
        ev.event_remarks?.toLowerCase().includes(q) ||
        ev.other_request?.toLowerCase().includes(q) ||
        ev.ticket_assigned_staff?.toLowerCase().includes(q)
      )
    })
  }

  return list
})

// ── Agenda / Timeline Grouping ──────────────────────────────────────────────
const agendaGroups = computed(() => {
  const ongoing = []
  const upcoming = []
  const future = []
  const past = []

  const today = todayStr.value
  const sevenDaysFromNow = new Date()
  sevenDaysFromNow.setDate(sevenDaysFromNow.getDate() + 7)
  const sevenDaysStr = sevenDaysFromNow.toISOString().slice(0, 10)

  filteredEvents.value.forEach((ev) => {
    if (ev.event_start_date <= today && ev.event_end_date >= today) {
      ongoing.push(ev)
    } else if (ev.event_start_date > today && ev.event_start_date <= sevenDaysStr) {
      upcoming.push(ev)
    } else if (ev.event_start_date > sevenDaysStr) {
      future.push(ev)
    } else {
      past.push(ev)
    }
  })

  // Sort upcoming & future ascending, past descending
  ongoing.sort((a, b) => a.event_start_date.localeCompare(b.event_start_date))
  upcoming.sort((a, b) => a.event_start_date.localeCompare(b.event_start_date))
  future.sort((a, b) => a.event_start_date.localeCompare(b.event_start_date))
  past.sort((a, b) => b.event_end_date.localeCompare(a.event_end_date))

  return [
    { title: '🔴 Happening Today & Ongoing', key: 'ongoing', items: ongoing, badgeColor: 'bg-emerald-500 text-white' },
    { title: '📅 Upcoming (Next 7 Days)', key: 'upcoming', items: upcoming, badgeColor: 'bg-blue-600 text-white' },
    { title: '🗓️ Later This Month & Beyond', key: 'future', items: future, badgeColor: 'bg-indigo-600 text-white' },
    { title: '✅ Concluded & Past Events', key: 'past', items: past, badgeColor: 'bg-slate-500 text-white' },
  ].filter((g) => g.items.length > 0)
})

// ── Navigation & Controls ───────────────────────────────────────────────────
function goToToday() {
  const now = new Date()
  currentMonthStr.value = now.toISOString().slice(0, 7)
  selectedDate.value = now.toISOString().slice(0, 10)
  fetchDataForMonth(currentMonthStr.value)
}

function prevPeriod() {
  if (currentView.value === 'month') {
    const [year, month] = currentMonthStr.value.split('-').map(Number)
    const prev = new Date(year, month - 2, 1)
    currentMonthStr.value = prev.toISOString().slice(0, 7)
    fetchDataForMonth(currentMonthStr.value)
  } else if (currentView.value === 'week') {
    const curr = new Date(selectedDate.value)
    curr.setDate(curr.getDate() - 7)
    selectedDate.value = curr.toISOString().slice(0, 10)
    currentMonthStr.value = selectedDate.value.slice(0, 7)
  }
}

function nextPeriod() {
  if (currentView.value === 'month') {
    const [year, month] = currentMonthStr.value.split('-').map(Number)
    const next = new Date(year, month, 1)
    currentMonthStr.value = next.toISOString().slice(0, 7)
    fetchDataForMonth(currentMonthStr.value)
  } else if (currentView.value === 'week') {
    const curr = new Date(selectedDate.value)
    curr.setDate(curr.getDate() + 7)
    selectedDate.value = curr.toISOString().slice(0, 10)
    currentMonthStr.value = selectedDate.value.slice(0, 7)
  }
}

async function fetchDataForMonth(monthStr) {
  isLoading.value = true
  try {
    const { data } = await axios.get(route('admin.events.data'), {
      params: {
        month: monthStr,
        status: statusFilter.value,
        division: divisionFilter.value,
        staff: staffFilter.value,
        search: searchQuery.value,
      },
    })
    if (data?.events) {
      localEvents.value = data.events
    }
    if (data?.kpis) {
      localKpis.value = data.kpis
    }
    if (data?.statusCounts) {
      localStatusCounts.value = data.statusCounts
    }
  } catch (err) {
    console.error('Failed to load events data:', err)
  } finally {
    isLoading.value = false
  }
}

function setView(view) {
  currentView.value = view
}

function selectDay(dateStr) {
  selectedDate.value = dateStr
}

// ── Modal Handlers ──────────────────────────────────────────────────────────
function openEventPreview(event) {
  activePreviewEvent.value = event
  showEventPreviewModal.value = true
}

function closeEventPreview() {
  showEventPreviewModal.value = false
  activePreviewEvent.value = null
}

function openFullTicketDetails(event) {
  closeEventPreview()
  selectedTicketForModal.value = {
    client_id: event.client_id,
    client_referenceNo: event.client_referenceNo,
    client_name: event.client_name,
    client_email: event.client_email,
    client_empID: event.client_empID,
    client_division_agency: event.client_division_agency,
    client_sex: event.client_sex,
    client_type: event.client_type,
    created_at: event.created_at,
    detail: event.detail || {
      ticket_id: event.ticket_id,
      client_referenceNo: event.client_referenceNo,
      ticket_type: event.ticket_type,
      ticket_status: event.ticket_status,
      ticket_nature_of_support: event.ticket_nature_of_support,
      ticket_support_type: event.ticket_support_type,
      ticket_assigned_staff: event.ticket_assigned_staff,
      ticket_attended_by: event.ticket_attended_by,
      ticket_assisted_by: event.ticket_assisted_by,
      ticket_remarks: event.ticket_remarks,
      ticket_problem_that_needed_support: event.ticket_problem_that_needed_support,
      date_requested: event.date_requested,
      date_completed: event.date_completed,
    },
    event: event.event || {
      event_id: event.event_id,
      client_referenceNo: event.client_referenceNo,
      event_start_date: event.event_start_date,
      event_end_date: event.event_end_date,
      event_venue: event.event_venue,
      event_remarks: event.event_remarks,
      other_request: event.other_request,
    },
    rating: event.rating || null,
    messages: event.messages || [],
  }
  showDetailsModal.value = true
}

function closeDetailsModal() {
  showDetailsModal.value = false
  selectedTicketForModal.value = null
}

function onTicketUpdated() {
  fetchDataForMonth(currentMonthStr.value)
}

// ── Visual Styling Helpers ──────────────────────────────────────────────────
function getStatusTheme(status) {
  switch (status?.toLowerCase()) {
    case 'in progress':
      return {
        bg: 'bg-amber-600 dark:bg-amber-600',
        bgSubtle: 'bg-amber-50 dark:bg-amber-950/40 text-amber-700 dark:text-amber-300 border-amber-200 dark:border-amber-800',
        text: 'text-amber-700 dark:text-amber-300',
        border: 'border-amber-500',
        dot: 'bg-amber-500',
        bar: 'bg-gradient-to-r from-amber-600 to-amber-700 text-white shadow-xs',
      }
    case 'completed':
      return {
        bg: 'bg-emerald-600 dark:bg-emerald-600',
        bgSubtle: 'bg-emerald-50 dark:bg-emerald-950/40 text-emerald-700 dark:text-emerald-300 border-emerald-200 dark:border-emerald-800',
        text: 'text-emerald-700 dark:text-emerald-300',
        border: 'border-emerald-500',
        dot: 'bg-emerald-500',
        bar: 'bg-gradient-to-r from-emerald-600 to-emerald-700 text-white shadow-xs',
      }
    case 'pending':
      return {
        bg: 'bg-purple-600 dark:bg-purple-600',
        bgSubtle: 'bg-purple-50 dark:bg-purple-950/40 text-purple-700 dark:text-purple-300 border-purple-200 dark:border-purple-800',
        text: 'text-purple-700 dark:text-purple-300',
        border: 'border-purple-500',
        dot: 'bg-purple-500',
        bar: 'bg-gradient-to-r from-purple-600 to-purple-700 text-white shadow-xs',
      }
    case 'cancelled':
      return {
        bg: 'bg-rose-600 dark:bg-rose-600',
        bgSubtle: 'bg-rose-50 dark:bg-rose-950/40 text-rose-700 dark:text-rose-300 border-rose-200 dark:border-rose-800',
        text: 'text-rose-700 dark:text-rose-300',
        border: 'border-rose-500',
        dot: 'bg-rose-500',
        bar: 'bg-gradient-to-r from-rose-600 to-rose-700 text-white shadow-xs',
      }
    case 'closed':
      return {
        bg: 'bg-slate-600 dark:bg-slate-600',
        bgSubtle: 'bg-slate-100 dark:bg-neutral-800 text-slate-700 dark:text-neutral-300 border-slate-200 dark:border-neutral-700',
        text: 'text-slate-700 dark:text-neutral-300',
        border: 'border-slate-500',
        dot: 'bg-slate-400',
        bar: 'bg-gradient-to-r from-slate-600 to-slate-700 text-white shadow-xs',
      }
    case 'new':
    default:
      return {
        bg: 'bg-blue-600 dark:bg-blue-600',
        bgSubtle: 'bg-blue-50 dark:bg-blue-950/40 text-blue-700 dark:text-blue-300 border-blue-200 dark:border-blue-800',
        text: 'text-blue-700 dark:text-blue-300',
        border: 'border-blue-500',
        dot: 'bg-blue-500',
        bar: 'bg-gradient-to-r from-blue-600 to-indigo-600 text-white shadow-xs',
      }
  }
}

function formatDateDisplay(dateStr) {
  if (!dateStr) return 'N/A'
  try {
    const [y, m, d] = dateStr.split('-')
    const dt = new Date(y, m - 1, d)
    return dt.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' })
  } catch {
    return dateStr
  }
}
</script>

<template>
  <AuthenticatedLayout title="Events Calendar & Monitoring">
    <Head title="Events Calendar & Monitoring - IT Helpdesk" />

    <div class="w-full max-w-[1720px] mx-auto px-3 sm:px-6 py-4 sm:py-6 space-y-5">
      <!-- ── 1. TOP COMMAND BAR & METRICS ──────────────────────────────────────── -->
      <div class="flex flex-col lg:flex-row lg:items-center justify-between gap-4 pb-2 border-b border-slate-200 dark:border-neutral-800">
        <div>
          <div class="flex items-center gap-2.5">
            <span class="w-9 h-9 rounded-xl bg-blue-600 text-white flex items-center justify-center shadow-md shadow-blue-500/20">
              <i class="fa-solid fa-calendar-days text-base"></i>
            </span>
            <div>
              <h1 class="text-xl sm:text-2xl font-black text-slate-900 dark:text-white tracking-tight flex items-center gap-2">
                Events Calendar & Monitoring
                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-bold bg-blue-100 text-blue-700 dark:bg-blue-950 dark:text-blue-300">
                  {{ filteredEvents.length }} Events
                </span>
              </h1>
              <p class="text-xs text-slate-500 dark:text-neutral-400">
                Track venue schedules, technician dispatches, and multi-day event durations in a Google Calendar view.
              </p>
            </div>
          </div>
        </div>

        <!-- Header Actions: Link to All Tickets + Create Event Ticket -->
        <div class="flex items-center gap-2.5 flex-wrap">
          <Link
            :href="route('tickets-v2.index')"
            class="inline-flex items-center gap-2 px-3 py-2 text-xs font-semibold rounded-xl border border-slate-300 dark:border-neutral-700 bg-white dark:bg-neutral-900 text-slate-700 dark:text-neutral-200 hover:bg-slate-50 dark:hover:bg-neutral-800 transition shadow-2xs"
          >
            <i class="fa-solid fa-table-list text-slate-400"></i>
            <span>All Tickets Queue</span>
          </Link>

          <!-- <Link
            :href="route('tickets-v2.create', { type: 'Events' })"
            class="inline-flex items-center gap-2 px-3.5 py-2 text-xs font-bold rounded-xl bg-blue-600 text-white hover:bg-blue-700 transition shadow-md shadow-blue-500/20 active:scale-95"
          >
            <i class="fa-solid fa-plus text-xs"></i>
            <span>New Event Ticket</span>
          </Link> -->
        </div>
      </div>

      <!-- ── 2. KPI SUMMARY METRIC CARDS ──────────────────────────────────────── -->
      <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-3">
        <!-- Card 1: Total Events -->
        <div class="bg-white dark:bg-neutral-900 p-3 sm:p-4 rounded-xl border border-slate-200 dark:border-neutral-800 shadow-2xs">
          <div class="flex items-center justify-between text-xs text-slate-500 dark:text-neutral-400 font-medium">
            <span>Total Events</span>
            <i class="fa-solid fa-calendar text-slate-400"></i>
          </div>
          <div class="mt-2 text-2xl font-black text-slate-900 dark:text-white">
            {{ localKpis.total_events || 0 }}
          </div>
          <div class="mt-0.5 text-[11px] text-slate-500 dark:text-neutral-500">
            Recorded in system
          </div>
        </div>

        <!-- Card 2: This Month -->
        <div class="bg-white dark:bg-neutral-900 p-3 sm:p-4 rounded-xl border border-slate-200 dark:border-neutral-800 shadow-2xs">
          <div class="flex items-center justify-between text-xs text-slate-500 dark:text-neutral-400 font-medium">
            <span>This Month</span>
            <i class="fa-solid fa-calendar-check text-blue-500"></i>
          </div>
          <div class="mt-2 text-2xl font-black text-blue-600 dark:text-blue-400">
            {{ localKpis.this_month_count || 0 }}
          </div>
          <div class="mt-0.5 text-[11px] text-slate-500 dark:text-neutral-500">
            {{ monthYearHeading }}
          </div>
        </div>

        <!-- Card 3: Ongoing Today -->
        <div class="bg-white dark:bg-neutral-900 p-3 sm:p-4 rounded-xl border border-emerald-200 dark:border-emerald-900/40 bg-gradient-to-br from-emerald-500/5 to-transparent shadow-2xs">
          <div class="flex items-center justify-between text-xs text-emerald-700 dark:text-emerald-400 font-bold">
            <span class="flex items-center gap-1.5">
              <span class="w-2 h-2 rounded-full bg-emerald-500 animate-pulse"></span>
              Ongoing Today
            </span>
            <i class="fa-solid fa-play text-emerald-500"></i>
          </div>
          <div class="mt-2 text-2xl font-black text-emerald-600 dark:text-emerald-400">
            {{ localKpis.ongoing_today_count || 0 }}
          </div>
          <div class="mt-0.5 text-[11px] text-slate-500 dark:text-neutral-500">
            Active today
          </div>
        </div>

        <!-- Card 4: Upcoming (7 Days) -->
        <div class="bg-white dark:bg-neutral-900 p-3 sm:p-4 rounded-xl border border-slate-200 dark:border-neutral-800 shadow-2xs">
          <div class="flex items-center justify-between text-xs text-slate-500 dark:text-neutral-400 font-medium">
            <span>Next 7 Days</span>
            <i class="fa-solid fa-forward text-amber-500"></i>
          </div>
          <div class="mt-2 text-2xl font-black text-amber-600 dark:text-amber-400">
            {{ localKpis.upcoming_count || 0 }}
          </div>
          <div class="mt-0.5 text-[11px] text-slate-500 dark:text-neutral-500">
            Upcoming schedules
          </div>
        </div>

        <!-- Card 5: Multi-Day Events -->
        <div class="bg-white dark:bg-neutral-900 p-3 sm:p-4 rounded-xl border border-slate-200 dark:border-neutral-800 shadow-2xs">
          <div class="flex items-center justify-between text-xs text-slate-500 dark:text-neutral-400 font-medium">
            <span>Multi-Day Events</span>
            <i class="fa-solid fa-arrows-left-right text-indigo-500"></i>
          </div>
          <div class="mt-2 text-2xl font-black text-indigo-600 dark:text-indigo-400">
            {{ localKpis.multi_day_count || 0 }}
          </div>
          <div class="mt-0.5 text-[11px] text-slate-500 dark:text-neutral-500">
            Spanning &gt; 1 day
          </div>
        </div>

        <!-- Card 6: Completed -->
        <div class="bg-white dark:bg-neutral-900 p-3 sm:p-4 rounded-xl border border-slate-200 dark:border-neutral-800 shadow-2xs">
          <div class="flex items-center justify-between text-xs text-slate-500 dark:text-neutral-400 font-medium">
            <span>Completed</span>
            <i class="fa-solid fa-circle-check text-slate-400"></i>
          </div>
          <div class="mt-2 text-2xl font-black text-slate-700 dark:text-neutral-300">
            {{ localKpis.completed_count || 0 }}
          </div>
          <div class="mt-0.5 text-[11px] text-slate-500 dark:text-neutral-500">
            Finished events
          </div>
        </div>
      </div>

      <!-- ── 3. GOOGLE CALENDAR CONTROLS & FILTER TOOLBAR ──────────────────────── -->
      <div class="bg-white dark:bg-neutral-900 rounded-2xl border border-slate-200 dark:border-neutral-800 shadow-sm p-4 space-y-4">
        <div class="flex flex-col lg:flex-row lg:items-center justify-between gap-4">
          <!-- Left: Google Calendar Style Navigation (Today, <, >, Month/Year heading) -->
          <div class="flex items-center gap-3">
            <button
              type="button"
              @click="goToToday"
              class="px-3.5 py-1.5 text-xs font-bold rounded-lg border border-slate-300 dark:border-neutral-700 text-slate-700 dark:text-neutral-200 hover:bg-slate-100 dark:hover:bg-neutral-800 transition active:scale-95"
            >
              Today
            </button>

            <div class="flex items-center rounded-lg border border-slate-300 dark:border-neutral-700 overflow-hidden">
              <button
                type="button"
                @click="prevPeriod"
                class="p-2 text-slate-600 dark:text-neutral-300 hover:bg-slate-100 dark:hover:bg-neutral-800 transition"
                title="Previous month / week"
              >
                <i class="fa-solid fa-chevron-left text-xs"></i>
              </button>
              <div class="w-px h-5 bg-slate-300 dark:bg-neutral-700"></div>
              <button
                type="button"
                @click="nextPeriod"
                class="p-2 text-slate-600 dark:text-neutral-300 hover:bg-slate-100 dark:hover:bg-neutral-800 transition"
                title="Next month / week"
              >
                <i class="fa-solid fa-chevron-right text-xs"></i>
              </button>
            </div>

            <h2 class="text-lg sm:text-xl font-bold text-slate-900 dark:text-white tracking-tight">
              {{ currentView === 'week' ? weekHeading : monthYearHeading }}
            </h2>

            <div v-if="isLoading" class="text-blue-500 dark:text-blue-400">
              <i class="fa-solid fa-spinner animate-spin text-sm"></i>
            </div>
          </div>

          <!-- Right: View Modes (Month | Week | Agenda) + Search -->
          <div class="flex items-center gap-2.5 flex-wrap">
            <!-- View Mode Switcher -->
            <div class="inline-flex rounded-xl bg-slate-100 dark:bg-neutral-800 p-1 border border-slate-200 dark:border-neutral-700/60">
              <button
                type="button"
                @click="setView('month')"
                class="px-3 py-1.5 text-xs font-semibold rounded-lg transition"
                :class="currentView === 'month'
                  ? 'bg-white dark:bg-neutral-900 text-blue-600 dark:text-blue-400 shadow-xs'
                  : 'text-slate-600 dark:text-neutral-400 hover:text-slate-900 dark:hover:text-white'"
              >
                <i class="fa-solid fa-calendar-days mr-1.5"></i>
                Month
              </button>
              <button
                type="button"
                @click="setView('week')"
                class="px-3 py-1.5 text-xs font-semibold rounded-lg transition"
                :class="currentView === 'week'
                  ? 'bg-white dark:bg-neutral-900 text-blue-600 dark:text-blue-400 shadow-xs'
                  : 'text-slate-600 dark:text-neutral-400 hover:text-slate-900 dark:hover:text-white'"
              >
                <i class="fa-solid fa-calendar-week mr-1.5"></i>
                Week
              </button>
              <button
                type="button"
                @click="setView('agenda')"
                class="px-3 py-1.5 text-xs font-semibold rounded-lg transition"
                :class="currentView === 'agenda'
                  ? 'bg-white dark:bg-neutral-900 text-blue-600 dark:text-blue-400 shadow-xs'
                  : 'text-slate-600 dark:text-neutral-400 hover:text-slate-900 dark:hover:text-white'"
              >
                <i class="fa-solid fa-list-check mr-1.5"></i>
                Agenda
              </button>
            </div>

            <!-- Search Field -->
            <div class="relative min-w-[200px] flex-1 sm:flex-initial">
              <i class="fa-solid fa-magnifying-glass absolute left-3 top-2.5 text-xs text-slate-400"></i>
              <input
                v-model="searchQuery"
                type="text"
                placeholder="Search venue, client, ref..."
                class="w-full pl-8 pr-3 py-1.5 text-xs rounded-xl border border-slate-300 dark:border-neutral-700 bg-slate-50 dark:bg-neutral-950 text-slate-900 dark:text-white placeholder-slate-400 focus:outline-hidden focus:ring-1 focus:ring-blue-500"
              />
            </div>
          </div>
        </div>

        <!-- Bottom Filter Bar (Status Pills, Division, Assigned Staff) -->
        <div class="flex items-center justify-between gap-3 pt-3 border-t border-slate-100 dark:border-neutral-800 flex-wrap">
          <!-- Status Pills -->
          <div class="flex items-center gap-1.5 overflow-x-auto no-scrollbar py-0.5">
            <button
              v-for="(count, st) in localStatusCounts"
              :key="st"
              type="button"
              @click="statusFilter = st"
              class="px-2.5 py-1 text-xs font-semibold rounded-lg transition shrink-0"
              :class="statusFilter === st
                ? 'bg-blue-600 text-white shadow-xs'
                : 'bg-slate-100 dark:bg-neutral-800 text-slate-600 dark:text-neutral-300 hover:bg-slate-200 dark:hover:bg-neutral-700'"
            >
              {{ st }}
              <span class="ml-1 opacity-80 text-[10px]">({{ count }})</span>
            </button>
          </div>

          <!-- Dropdown Filters: Division + Staff -->
          <div class="flex items-center gap-2 text-xs">
            <!-- Division Filter -->
            <div class="flex items-center gap-1.5">
              <span class="text-slate-500 dark:text-neutral-400 font-medium">Division:</span>
              <select
                v-model="divisionFilter"
                class="py-1 px-2.5 text-xs rounded-lg border border-slate-300 dark:border-neutral-700 bg-white dark:bg-neutral-900 text-slate-800 dark:text-neutral-200 focus:ring-1 focus:ring-blue-500"
              >
                <option value="All">All Divisions</option>
                <option value="1">FAD</option>
                <option value="2">IRAD</option>
                <option value="3">CRPD</option>
                <option value="4">OD-MISPS</option>
                <option value="OTHERS">Others</option>
              </select>
            </div>

            <!-- Staff Filter -->
            <div class="flex items-center gap-1.5">
              <span class="text-slate-500 dark:text-neutral-400 font-medium">Staff:</span>
              <select
                v-model="staffFilter"
                class="py-1 px-2.5 text-xs rounded-lg border border-slate-300 dark:border-neutral-700 bg-white dark:bg-neutral-900 text-slate-800 dark:text-neutral-200 focus:ring-1 focus:ring-blue-500 max-w-[140px]"
              >
                <option value="All">All Staff</option>
                <option v-for="s in activeStaff" :key="s" :value="s">{{ s }}</option>
              </select>
            </div>
          </div>
        </div>
      </div>

      <!-- ── 4. VIEW: MONTH VIEW (GOOGLE CALENDAR MULTI-DAY PLOTTING) ──────────── -->
      <div v-if="currentView === 'month'" class="bg-white dark:bg-neutral-900 rounded-2xl border border-slate-200 dark:border-neutral-800 shadow-sm overflow-hidden">
        <!-- Calendar Days Header (SUN – SAT) -->
        <div class="grid grid-cols-7 border-b border-slate-200 dark:border-neutral-800 bg-slate-50/75 dark:bg-neutral-950/50 text-center py-2 text-xs font-bold text-slate-600 dark:text-neutral-400 tracking-wider uppercase">
          <div>Sun</div>
          <div>Mon</div>
          <div>Tue</div>
          <div>Wed</div>
          <div>Thu</div>
          <div>Fri</div>
          <div>Sat</div>
        </div>

        <!-- Weeks Rows -->
        <div class="divide-y divide-slate-200 dark:divide-neutral-800">
          <div
            v-for="(week, wIdx) in monthGridWeeks"
            :key="wIdx"
            class="relative min-h-[120px] lg:min-h-[140px]"
          >
            <!-- Background 7 Days Grid with Day Numbers -->
            <div class="absolute inset-0 grid grid-cols-7 divide-x divide-slate-200 dark:divide-neutral-800 pointer-events-none">
              <div
                v-for="(day, dIdx) in week.days"
                :key="dIdx"
                class="p-1.5 sm:p-2 flex flex-col justify-between transition-colors pointer-events-auto cursor-pointer"
                :class="[
                  day.isCurrentMonth
                    ? 'bg-transparent hover:bg-slate-50/60 dark:hover:bg-neutral-800/20'
                    : 'bg-slate-50/40 dark:bg-neutral-950/40 opacity-50',
                  day.isSelected ? 'ring-1 ring-inset ring-blue-500/50' : '',
                ]"
                @click="selectDay(day.dateStr)"
              >
                <!-- Day Number & Indicators -->
                <div class="flex items-center justify-between">
                  <span
                    class="inline-flex items-center justify-center w-6 h-6 text-xs font-bold rounded-full"
                    :class="day.isToday
                      ? 'bg-blue-600 text-white shadow-xs font-black'
                      : 'text-slate-700 dark:text-neutral-300'"
                  >
                    {{ day.dayNumber }}
                  </span>

                  <!-- Tiny Today pill on mobile -->
                  <span v-if="day.isToday" class="hidden sm:inline-block text-[9px] font-bold text-blue-600 dark:text-blue-400 uppercase tracking-wider">
                    Today
                  </span>
                </div>
              </div>
            </div>

            <!-- Overlaid Events Grid (Google Calendar Tracks) -->
            <div
              class="relative z-10 grid grid-cols-7 pt-7 pb-2 px-1 gap-y-1 pointer-events-none"
              :style="{ gridTemplateRows: `repeat(${Math.max(week.maxTrack, 1)}, minmax(24px, auto))` }"
            >
              <div
                v-for="(seg, sIdx) in week.segments"
                :key="sIdx"
                class="pointer-events-auto py-0.5 px-1 group cursor-pointer transition-transform active:scale-[0.99]"
                :style="{
                  gridColumn: `${seg.startCol + 1} / span ${seg.span}`,
                  gridRow: `${seg.track + 1}`,
                }"
                @click="openEventPreview(seg.event)"
              >
                <!-- Event Bar (Spans multiple columns seamlessly) -->
                <div
                  class="h-6 flex items-center gap-1.5 px-2 text-[11px] font-semibold transition shadow-xs overflow-hidden"
                  :class="[
                    getStatusTheme(seg.event.ticket_status).bar,
                    seg.isStart ? 'rounded-l-md pl-2' : 'rounded-l-none pl-1.5 border-l-2 border-white/60',
                    seg.isEnd ? 'rounded-r-md pr-2' : 'rounded-r-none pr-1.5 border-r-2 border-white/60',
                    'hover:brightness-110 hover:shadow-md',
                  ]"
                  :title="`#${seg.event.client_referenceNo} - ${seg.event.client_name}\nVenue: ${seg.event.event_venue}\nDuration: ${formatDateDisplay(seg.event.event_start_date)} – ${formatDateDisplay(seg.event.event_end_date)} (${seg.event.duration_days} day${seg.event.duration_days > 1 ? 's' : ''})\nStatus: ${seg.event.ticket_status}`"
                >
                  <!-- Multi-day indicator icon or status dot -->
                  <i v-if="seg.event.is_multi_day && seg.isStart" class="fa-solid fa-arrows-left-right text-[9px] opacity-80 shrink-0"></i>
                  <span v-else-if="!seg.isStart" class="text-[9px] opacity-75 shrink-0">◀</span>
                  <span v-else class="w-1.5 h-1.5 rounded-full bg-white shrink-0"></span>

                  <!-- Event Title: Client name + Venue -->
                  <span class="truncate tracking-tight font-medium">
                    <span class="font-bold">#{{ seg.event.client_referenceNo }}</span>
                    {{ seg.event.client_name }}
                    <span v-if="seg.event.event_venue && seg.event.event_venue !== 'Venue not specified'" class="opacity-80">
                      • {{ seg.event.event_venue }}
                    </span>
                  </span>

                  <!-- Duration Badge (shown on start segment or multi-day) -->
                  <span
                    v-if="seg.event.duration_days > 1"
                    class="ml-auto shrink-0 px-1 py-0.2 rounded text-[9px] font-bold bg-black/25 text-white/95"
                  >
                    {{ seg.event.duration_days }}d
                  </span>

                  <!-- Continuation arrow if continues past this week -->
                  <span v-if="!seg.isEnd" class="text-[9px] opacity-75 shrink-0 ml-0.5">▶</span>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- ── 5. VIEW: WEEK VIEW (7 COLUMNS WITH RICH CARDS) ────────────────────── -->
      <div v-else-if="currentView === 'week'" class="bg-white dark:bg-neutral-900 rounded-2xl border border-slate-200 dark:border-neutral-800 shadow-sm overflow-hidden">
        <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-4 lg:grid-cols-7 divide-y sm:divide-y-0 sm:divide-x divide-slate-200 dark:divide-neutral-800 min-h-[500px]">
          <div
            v-for="(day, dIdx) in weekViewDays"
            :key="dIdx"
            class="p-3 flex flex-col space-y-2.5 transition-colors"
            :class="day.isToday ? 'bg-blue-50/30 dark:bg-blue-950/20' : 'bg-transparent'"
          >
            <!-- Day Column Header -->
            <div class="pb-2 border-b border-slate-200 dark:border-neutral-800 flex items-center justify-between">
              <div>
                <span class="text-xs font-bold text-slate-500 dark:text-neutral-400 uppercase tracking-wider block">
                  {{ day.dayName }}
                </span>
                <span
                  class="inline-flex items-center justify-center w-7 h-7 text-sm font-black rounded-full mt-0.5"
                  :class="day.isToday ? 'bg-blue-600 text-white shadow-xs' : 'text-slate-800 dark:text-white'"
                >
                  {{ day.dayNumber }}
                </span>
              </div>

              <span class="text-[10px] font-semibold px-1.5 py-0.5 rounded-full bg-slate-100 dark:bg-neutral-800 text-slate-600 dark:text-neutral-400">
                {{ day.events.length }}
              </span>
            </div>

            <!-- Day Events List -->
            <div class="space-y-2 flex-1">
              <div
                v-for="ev in day.events"
                :key="ev.client_id"
                @click="openEventPreview(ev)"
                class="p-2.5 rounded-xl border transition cursor-pointer hover:shadow-md"
                :class="getStatusTheme(ev.ticket_status).bgSubtle"
              >
                <!-- Status & Duration -->
                <div class="flex items-center justify-between gap-1 text-[10px]">
                  <span class="font-bold flex items-center gap-1">
                    <span class="w-1.5 h-1.5 rounded-full" :class="getStatusTheme(ev.ticket_status).dot"></span>
                    {{ ev.ticket_status }}
                  </span>
                  <span v-if="ev.duration_days > 1" class="font-semibold text-slate-500 dark:text-neutral-400">
                    {{ ev.duration_days }} Days
                  </span>
                </div>

                <!-- Title & Client -->
                <div class="mt-1 text-xs font-bold text-slate-900 dark:text-white line-clamp-1">
                  #{{ ev.client_referenceNo }}
                </div>
                <div class="text-[11px] text-slate-600 dark:text-neutral-300 truncate">
                  {{ ev.client_name }}
                </div>

                <!-- Venue -->
                <div v-if="ev.event_venue" class="mt-1 flex items-center gap-1 text-[11px] text-slate-500 dark:text-neutral-400 truncate">
                  <i class="fa-solid fa-location-dot text-[10px] text-rose-500"></i>
                  <span class="truncate">{{ ev.event_venue }}</span>
                </div>

                <!-- Assigned Staff -->
                <div v-if="ev.ticket_assigned_staff" class="mt-2 pt-1 border-t border-slate-200/60 dark:border-neutral-700/60 text-[10px] text-slate-500 dark:text-neutral-400 flex items-center gap-1">
                  <i class="fa-solid fa-user-gear text-[9px]"></i>
                  <span class="truncate">{{ ev.ticket_assigned_staff }}</span>
                </div>
              </div>

              <!-- Empty state for day -->
              <div v-if="day.events.length === 0" class="h-24 flex items-center justify-center text-[11px] text-slate-400 dark:text-neutral-600 italic">
                No events
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- ── 6. VIEW: AGENDA VIEW (CHRONOLOGICAL TIMELINE) ─────────────────────── -->
      <div v-else-if="currentView === 'agenda'" class="space-y-6">
        <div v-if="agendaGroups.length === 0" class="bg-white dark:bg-neutral-900 rounded-2xl border border-slate-200 dark:border-neutral-800 p-12 text-center">
          <i class="fa-solid fa-calendar-xmark text-4xl text-slate-300 dark:text-neutral-700 mb-3 block"></i>
          <h3 class="text-base font-bold text-slate-800 dark:text-white">No events match your current filters</h3>
          <p class="text-xs text-slate-500 dark:text-neutral-400 mt-1">Try clearing search or changing the status filter.</p>
        </div>

        <div v-for="grp in agendaGroups" :key="grp.key" class="space-y-3">
          <div class="flex items-center gap-2">
            <span class="px-2.5 py-1 text-xs font-bold rounded-lg shadow-xs" :class="grp.badgeColor">
              {{ grp.title }}
            </span>
            <span class="text-xs text-slate-500 dark:text-neutral-400">
              ({{ grp.items.length }} event{{ grp.items.length > 1 ? 's' : '' }})
            </span>
          </div>

          <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-3">
            <div
              v-for="ev in grp.items"
              :key="ev.client_id"
              @click="openEventPreview(ev)"
              class="bg-white dark:bg-neutral-900 rounded-2xl border border-slate-200 dark:border-neutral-800 p-4 shadow-sm hover:shadow-md transition cursor-pointer flex flex-col justify-between space-y-3 group"
            >
              <div>
                <!-- Top Header: Reference & Status -->
                <div class="flex items-center justify-between gap-2">
                  <span class="text-xs font-bold text-blue-600 dark:text-blue-400">
                    #{{ ev.client_referenceNo }}
                  </span>
                  <span
                    class="px-2 py-0.5 rounded-full text-[10px] font-bold"
                    :class="getStatusTheme(ev.ticket_status).bgSubtle"
                  >
                    {{ ev.ticket_status }}
                  </span>
                </div>

                <!-- Client & Division -->
                <h4 class="mt-1 text-sm font-bold text-slate-900 dark:text-white group-hover:text-blue-600 dark:group-hover:text-blue-400 transition">
                  {{ ev.client_name }}
                </h4>
                <div class="text-xs text-slate-500 dark:text-neutral-400">
                  Division: <span class="font-medium text-slate-700 dark:text-neutral-300">{{ ev.client_division_label }}</span>
                </div>

                <!-- Event Schedule & Multi-day Duration -->
                <div class="mt-3 p-2.5 rounded-xl bg-slate-50 dark:bg-neutral-950 border border-slate-200/80 dark:border-neutral-800/80 space-y-1.5 text-xs">
                  <div class="flex items-center justify-between font-semibold text-slate-800 dark:text-neutral-200">
                    <span class="flex items-center gap-1.5">
                      <i class="fa-solid fa-calendar-day text-blue-500"></i>
                      <span>{{ formatDateDisplay(ev.event_start_date) }}</span>
                      <span v-if="ev.is_multi_day">→ {{ formatDateDisplay(ev.event_end_date) }}</span>
                    </span>
                    <span
                      class="px-2 py-0.5 rounded-md text-[10px] font-black"
                      :class="ev.is_multi_day ? 'bg-indigo-100 text-indigo-700 dark:bg-indigo-950 dark:text-indigo-300' : 'bg-slate-200 text-slate-700 dark:bg-neutral-800 dark:text-neutral-300'"
                    >
                      {{ ev.duration_days }} Day{{ ev.duration_days > 1 ? 's' : '' }}
                    </span>
                  </div>

                  <!-- Venue -->
                  <div class="flex items-center gap-1.5 text-slate-600 dark:text-neutral-400 text-[11px] truncate">
                    <i class="fa-solid fa-location-dot text-rose-500 shrink-0"></i>
                    <span class="truncate font-medium">{{ ev.event_venue || 'Venue not specified' }}</span>
                  </div>

                  <!-- Other Request if any -->
                  <div v-if="ev.other_request" class="text-[11px] text-amber-700 dark:text-amber-400 bg-amber-50 dark:bg-amber-950/40 px-2 py-1 rounded-md border border-amber-200 dark:border-amber-800/60 line-clamp-1">
                    <i class="fa-solid fa-list-ul mr-1"></i>
                    <span>{{ ev.other_request }}</span>
                  </div>
                </div>
              </div>

              <!-- Footer: Assigned Staff & Actions -->
              <div class="pt-2 border-t border-slate-100 dark:border-neutral-800 flex items-center justify-between text-xs">
                <div class="text-slate-500 dark:text-neutral-400 text-[11px] flex items-center gap-1.5 truncate">
                  <i class="fa-solid fa-user-gear text-slate-400"></i>
                  <span class="truncate">{{ ev.ticket_assigned_staff || 'Unassigned' }}</span>
                </div>

                <span class="text-blue-600 dark:text-blue-400 text-xs font-semibold group-hover:underline">
                  View Details →
                </span>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- ── 7. EVENT QUICK PREVIEW & ACTION MODAL ────────────────────────────── -->
      <div
        v-if="showEventPreviewModal && activePreviewEvent"
        class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/60 backdrop-blur-xs"
        @click.self="closeEventPreview"
      >
        <div class="bg-white dark:bg-neutral-900 rounded-3xl border border-slate-200 dark:border-neutral-800 max-w-xl w-full overflow-hidden shadow-2xl flex flex-col max-h-[90vh]">
          <!-- Modal Header -->
          <div class="p-5 border-b border-slate-200 dark:border-neutral-800 flex items-start justify-between gap-3 bg-gradient-to-r from-blue-600 to-indigo-600 text-white">
            <div>
              <div class="flex items-center gap-2">
                <span class="px-2.5 py-0.5 rounded-full text-xs font-black bg-white/20 text-white">
                  #{{ activePreviewEvent.client_referenceNo }}
                </span>
                <span class="px-2 py-0.5 rounded-full text-xs font-bold bg-white text-slate-900">
                  {{ activePreviewEvent.ticket_status }}
                </span>
              </div>
              <h3 class="text-lg font-black text-white mt-2 leading-tight">
                {{ activePreviewEvent.client_name }}
              </h3>
              <p class="text-xs text-blue-100 mt-0.5">
                Division: {{ activePreviewEvent.client_division_label }} • Ticket Type: {{ activePreviewEvent.ticket_type }}
              </p>
            </div>

            <button
              type="button"
              @click="closeEventPreview"
              class="p-1 rounded-lg text-white/80 hover:text-white hover:bg-white/10 transition"
              aria-label="Close"
            >
              <i class="fa-solid fa-xmark text-lg"></i>
            </button>
          </div>

          <!-- Modal Body -->
          <div class="p-5 overflow-y-auto space-y-4 text-xs">
            <!-- Event Duration Highlight Card -->
            <div class="p-4 rounded-2xl bg-blue-50 dark:bg-blue-950/40 border border-blue-200 dark:border-blue-800/60 space-y-2">
              <div class="flex items-center justify-between">
                <span class="text-xs font-bold text-blue-800 dark:text-blue-300 uppercase tracking-wider flex items-center gap-1.5">
                  <i class="fa-solid fa-calendar-check"></i>
                  Event Duration & Schedule
                </span>
                <span class="px-2.5 py-0.5 rounded-full text-xs font-black bg-blue-600 text-white">
                  {{ activePreviewEvent.duration_days }} Day{{ activePreviewEvent.duration_days > 1 ? 's' : '' }} Total
                </span>
              </div>

              <div class="grid grid-cols-2 gap-3 pt-1 text-slate-800 dark:text-neutral-200 font-semibold">
                <div>
                  <span class="text-[11px] text-slate-500 dark:text-neutral-400 block font-normal">Start Date</span>
                  <span class="text-sm font-bold">{{ formatDateDisplay(activePreviewEvent.event_start_date) }}</span>
                </div>
                <div>
                  <span class="text-[11px] text-slate-500 dark:text-neutral-400 block font-normal">End Date</span>
                  <span class="text-sm font-bold">{{ formatDateDisplay(activePreviewEvent.event_end_date) }}</span>
                </div>
              </div>

              <!-- Temporal status badge -->
              <div v-if="activePreviewEvent.is_ongoing" class="mt-2 pt-2 border-t border-blue-200/60 dark:border-blue-800/60 flex items-center justify-between text-emerald-700 dark:text-emerald-400 font-bold">
                <span class="flex items-center gap-1.5">
                  <span class="w-2 h-2 rounded-full bg-emerald-500 animate-pulse"></span>
                  Currently in progress
                </span>
                <span>Day {{ activePreviewEvent.days_elapsed }} of {{ activePreviewEvent.duration_days }}</span>
              </div>
            </div>

            <!-- Venue Details -->
            <div class="p-3.5 rounded-xl border border-slate-200 dark:border-neutral-800 space-y-1">
              <span class="text-[10px] font-bold text-slate-400 dark:text-neutral-500 uppercase tracking-wider block">
                Event Venue / Location
              </span>
              <div class="text-sm font-bold text-slate-900 dark:text-white flex items-center gap-2">
                <i class="fa-solid fa-location-dot text-rose-500"></i>
                <span>{{ activePreviewEvent.event_venue || 'Venue not specified' }}</span>
              </div>
            </div>

            <!-- Remarks & Other Requests -->
            <div class="p-3.5 rounded-xl border border-slate-200 dark:border-neutral-800 space-y-2">
              <div>
                <span class="text-[10px] font-bold text-slate-400 dark:text-neutral-500 uppercase tracking-wider block">
                  Event Remarks / Requirements
                </span>
                <p class="text-xs text-slate-700 dark:text-neutral-300 mt-1 whitespace-pre-wrap">
                  {{ activePreviewEvent.event_remarks || activePreviewEvent.ticket_remarks || 'No event remarks provided.' }}
                </p>
              </div>

              <div v-if="activePreviewEvent.other_request" class="pt-2 border-t border-slate-200 dark:border-neutral-800">
                <span class="text-[10px] font-bold text-amber-700 dark:text-amber-400 uppercase tracking-wider block">
                  Other Support Requests
                </span>
                <p class="text-xs text-slate-800 dark:text-neutral-200 mt-1">
                  {{ activePreviewEvent.other_request }}
                </p>
              </div>
            </div>

            <!-- Staff Assignment Details -->
            <div class="grid grid-cols-2 gap-3 p-3.5 rounded-xl border border-slate-200 dark:border-neutral-800">
              <div>
                <span class="text-[10px] font-bold text-slate-400 dark:text-neutral-500 uppercase tracking-wider block">
                  Assigned Staff
                </span>
                <span class="text-xs font-semibold text-slate-800 dark:text-neutral-200">
                  {{ activePreviewEvent.ticket_assigned_staff || 'Unassigned' }}
                </span>
              </div>
              <div>
                <span class="text-[10px] font-bold text-slate-400 dark:text-neutral-500 uppercase tracking-wider block">
                  Attended By
                </span>
                <span class="text-xs font-semibold text-slate-800 dark:text-neutral-200">
                  {{ activePreviewEvent.ticket_attended_by || 'Not attended yet' }}
                </span>
              </div>
            </div>
          </div>

          <!-- Modal Footer: Actions -->
          <div class="p-4 border-t border-slate-200 dark:border-neutral-800 bg-slate-50 dark:bg-neutral-950 flex items-center justify-between gap-3">
            <button
              type="button"
              @click="closeEventPreview"
              class="px-4 py-2 text-xs font-semibold rounded-xl border border-slate-300 dark:border-neutral-700 text-slate-700 dark:text-neutral-300 hover:bg-slate-100 dark:hover:bg-neutral-800 transition"
            >
              Close
            </button>

            <button
              type="button"
              @click="openFullTicketDetails(activePreviewEvent)"
              class="px-4 py-2 text-xs font-bold rounded-xl bg-blue-600 text-white hover:bg-blue-700 transition shadow-md shadow-blue-500/20 flex items-center gap-2"
            >
              <i class="fa-solid fa-up-right-from-square text-xs"></i>
              <span>Manage Ticket & Status</span>
            </button>
          </div>
        </div>
      </div>

      <!-- ── 8. FULL TICKET DETAILS MODAL / DRAWER (REUSED) ────────────────────── -->
      <ModalTicketDetails
        :show="showDetailsModal"
        :ticket="selectedTicketForModal"
        :allow-status-change="true"
        default-mode="drawer"
        @close="closeDetailsModal"
        @updated="onTicketUpdated"
      />
    </div>
  </AuthenticatedLayout>
</template>

<style scoped>
.no-scrollbar::-webkit-scrollbar {
  display: none;
}
.no-scrollbar {
  -ms-overflow-style: none;
  scrollbar-width: none;
}
</style>

