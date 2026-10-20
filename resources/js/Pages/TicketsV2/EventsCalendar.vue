<script setup>
import { computed, onMounted, ref, watch } from 'vue'
import { Head, Link, usePage } from '@inertiajs/vue3'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import TicketProgressStepper from '@/Components/TicketProgressStepper.vue'
import ModalTicketDetails from '@/Pages/TicketsV2/ModalComponents/ModalTicketDetails.vue'
import ModalAction_CreateTicket from '@/Pages/TicketsV2/ModalComponents/ModalAction_CreateTicket.vue'
import axios from 'axios'
import { useToast } from 'vue-toastification'

const page = usePage()
const toast = useToast()

// Custom click-outside directive for custom dropdown popovers
const vClickOutside = {
  mounted(el, binding) {
    el._clickOutside = (e) => {
      if (!el.contains(e.target)) {
        binding.value(e)
      }
    }
    document.addEventListener('mousedown', el._clickOutside, { passive: true })
  },
  unmounted(el) {
    document.removeEventListener('mousedown', el._clickOutside)
  },
}

const isAdminUser = computed(() => {
  const role = page.props?.auth?.user?.role || ''
  return ['Admin', 'Super Admin'].includes(role)
})

const currentUserName = computed(() => page.props?.auth?.user?.name || '')

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
      unassigned_active_count: 0,
      urgent_unassigned_count: 0,
      conflict_count: 0,
      venue_conflict_count: 0,
      staff_conflict_count: 0,
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
      venue: 'All',
      custom_venue: '',
      search: '',
      staff: 'All',
      view: 'month',
    }),
  },
  activeStaff: {
    type: Array,
    default: () => [],
  },
  standardVenues: {
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
const currentView = ref(props.filters.view || 'month') // 'month' | 'week' | 'agenda' | 'venues'
const statusFilter = ref(props.filters.status || 'All')
const divisionFilter = ref(props.filters.division || 'All')
const venueFilter = ref(props.filters.venue || 'All') // 'All' | preset venue | 'Others'
const customVenueFilter = ref(props.filters.custom_venue || '')
const staffFilter = ref(props.filters.staff || 'All')
const searchQuery = ref(props.filters.search || '')
const onlyUnassignedFilter = ref(false)
const onlyMultiDayFilter = ref(false)
const onlyConflictsFilter = ref(false)

// Custom Dropdown & Popover States
const showCreateMenu = ref(false)
const showMonthPicker = ref(false)
const pickerYear = ref(Number((props.filters.month || new Date().toISOString().slice(0, 7)).split('-')[0]))
const showDivisionMenu = ref(false)
const showVenueMenu = ref(false)
const showStaffMenu = ref(false)
const staffSearchText = ref('')
const showQuickAssignMenu = ref(false)
const quickAssignSearchText = ref('')

const DEFAULT_STAFF_FALLBACK = [
  'Aerone Dominhick Sebastian',
  'Etienne Wayne Amparado',
  'Mc Gyver Basaya',
  'Michael Binondo',
  'John Michael Cagadas',
  'Chester Francisco',
  'Joram Kate Leonardo',
  'Lloyd Mandapat',
  'Jomar Rabanera',
  'Mark Jayson Sison',
]

// Normalize activeStaff whether backend passes strings or User objects
const normalizedStaffList = computed(() => {
  const raw = Array.isArray(props.activeStaff) ? props.activeStaff : []
  const names = raw
    .map((item) => {
      if (typeof item === 'string') return item.trim()
      if (item && typeof item === 'object') {
        return String(item.name || item.username || '').trim()
      }
      return ''
    })
    .filter(Boolean)
  return names.length > 0 ? [...new Set(names)] : DEFAULT_STAFF_FALLBACK
})

const DIVISION_OPTIONS = [
  { value: 'All', label: 'All Divisions', full: 'All Divisions & External Agencies', icon: 'fa-building-columns' },
  { value: '1', label: 'FAD', full: 'Finance and Administrative Division', icon: 'fa-coins' },
  { value: '2', label: 'IRAD', full: 'Information Resources & Analytics Division', icon: 'fa-database' },
  { value: '3', label: 'CRPD', full: 'Communication Resources & Production Division', icon: 'fa-bullhorn' },
  { value: '4', label: 'OD-MISPS', full: "Office of the Director - MISPS", icon: 'fa-network-wired' },
  { value: 'OTHERS', label: 'Others / External', full: 'External Clients & Partner Agencies', icon: 'fa-globe' },
]

const MONTH_NAMES_SHORT = [
  { num: '01', label: 'Jan', full: 'January' },
  { num: '02', label: 'Feb', full: 'February' },
  { num: '03', label: 'Mar', full: 'March' },
  { num: '04', label: 'Apr', full: 'April' },
  { num: '05', label: 'May', full: 'May' },
  { num: '06', label: 'Jun', full: 'June' },
  { num: '07', label: 'Jul', full: 'July' },
  { num: '08', label: 'Aug', full: 'August' },
  { num: '09', label: 'Sep', full: 'September' },
  { num: '10', label: 'Oct', full: 'October' },
  { num: '11', label: 'Nov', full: 'November' },
  { num: '12', label: 'Dec', full: 'December' },
]

const QUICK_VENUES = [
  'DOST-STII Atrium',
  'Cast Room',
  'Training Room',
  'Conference Room',
  'Mini Theater',
  'Zoom / Online',
]

const VENUE_META = {
  'DOST-STII Atrium': { icon: 'fa-building-flag', desc: 'Main lobby & atrium assembly space' },
  'Cast Room': { icon: 'fa-microphone-lines', desc: 'Broadcast & studio room' },
  'Training Room': { icon: 'fa-chalkboard-user', desc: 'Hands-on training & workshop room' },
  'Conference Room': { icon: 'fa-people-roof', desc: 'Executive & division meetings' },
  'Mini Theater': { icon: 'fa-film', desc: 'Audio-visual screening & auditorium' },
  'Zoom / Online': { icon: 'fa-video', desc: 'Virtual / hybrid conference platform' },
  Others: { icon: 'fa-map-location-dot', desc: 'Custom or external event venues' },
}

function isStandardVenueName(venue) {
  const clean = (venue || '').trim().toLowerCase()
  return QUICK_VENUES.some((v) => v.toLowerCase() === clean)
}

function isVirtualOrUnspecifiedVenue(venue) {
  const clean = (venue || '').trim().toLowerCase()
  return [
    '',
    'venue not specified',
    'n/a',
    'none',
    'tba',
    'tbd',
    'zoom',
    'zoom / online',
    'online',
    'virtual',
    'ms teams',
    'google meet',
  ].includes(clean)
}

// Active date focus (for Week view or selecting a specific day)
const selectedDate = ref(new Date().toISOString().slice(0, 10))

// Modal states
const showDetailsModal = ref(false)
const selectedTicketForModal = ref(null)

const showEventPreviewModal = ref(false)
const activePreviewEvent = ref(null)
const previewViewMode = ref('modal') // 'modal' | 'drawer'

onMounted(() => {
  try {
    const saved = localStorage.getItem('events_preview_mode')
    if (saved === 'drawer' || saved === 'modal') {
      previewViewMode.value = saved
    }
  } catch {}
})

function setPreviewViewMode(mode) {
  previewViewMode.value = mode
  try {
    localStorage.setItem('events_preview_mode', mode)
  } catch {}
}

// Quick Create Event Modal state (Click-to-Schedule / Book Venue)
const showCreateEventModal = ref(false)
const createEventDate = ref('')
const createEventVenue = ref('')
const createModalMode = ref('standard') // 'standard' | 'late'

// Pause background infinite animations when any modal is open to prevent scroll repaint lag
const isAnyModalOpen = computed(
  () => showEventPreviewModal.value || showCreateEventModal.value || showDetailsModal.value
)

// Inline Quick-Actions state in Event Preview Modal
const isQuickSaving = ref(false)
const quickAssignStaff = ref('')
const isEditingSchedule = ref(false)
const quickScheduleVenueOption = ref('')
const quickScheduleCustomVenue = ref('')
const quickScheduleForm = ref({
  event_start_date: '',
  event_end_date: '',
  event_venue: '',
  event_remarks: '',
  other_request: '',
})
const showQuickCompleteForm = ref(false)
const quickCompleteForm = ref({
  ticket_support_type: 'Audio & Visual Setup',
  ticket_actions_taken: '',
  ticket_remarks: '',
})

function syncQuickScheduleVenueFields(venueValue) {
  const val = (venueValue || '').trim()
  if (!val) {
    quickScheduleVenueOption.value = ''
    quickScheduleCustomVenue.value = ''
  } else {
    const matchedStandard = QUICK_VENUES.find((v) => v.toLowerCase() === val.toLowerCase())
    if (matchedStandard) {
      quickScheduleVenueOption.value = matchedStandard
      quickScheduleCustomVenue.value = ''
    } else if (val === 'Others') {
      quickScheduleVenueOption.value = 'Others'
      quickScheduleCustomVenue.value = ''
    } else {
      quickScheduleVenueOption.value = 'Others'
      quickScheduleCustomVenue.value = val
    }
  }
}

function selectQuickScheduleVenueOption(option) {
  quickScheduleVenueOption.value = option
  if (option === 'Others') {
    quickScheduleForm.value.event_venue = quickScheduleCustomVenue.value.trim()
  } else {
    quickScheduleForm.value.event_venue = option
  }
}

watch(quickScheduleCustomVenue, (val) => {
  if (quickScheduleVenueOption.value === 'Others') {
    quickScheduleForm.value.event_venue = val
  }
})

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
watch(currentMonthStr, (val) => {
  if (val) {
    pickerYear.value = Number(val.split('-')[0])
  }
})

// ── Date Computations ───────────────────────────────────────────────────────
const todayStr = computed(() => new Date().toISOString().slice(0, 10))

const threeDaysFromNowStr = computed(() => {
  const d = new Date()
  d.setDate(d.getDate() + 3)
  return d.toISOString().slice(0, 10)
})

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

// ── Unassigned & Urgent Event Computations ──────────────────────────────────
function isEventUnassigned(ev) {
  if (typeof ev.is_unassigned === 'boolean') return ev.is_unassigned
  const st = (ev.ticket_status || '').toLowerCase()
  if (['completed', 'cancelled', 'closed'].includes(st)) return false
  return !ev.ticket_assigned_staff && !ev.ticket_attended_by
}

function isEventUrgentUnassigned(ev) {
  if (typeof ev.is_urgent_unassigned === 'boolean') return ev.is_urgent_unassigned
  if (!isEventUnassigned(ev)) return false
  const today = todayStr.value
  const limit = threeDaysFromNowStr.value
  const isOngoing = ev.event_start_date <= today && ev.event_end_date >= today
  const isStartingSoon = ev.event_start_date >= today && ev.event_start_date <= limit
  return isOngoing || isStartingSoon
}

function getUnassignedUrgencyLabel(ev) {
  if (!isEventUnassigned(ev)) return ''
  const today = todayStr.value
  if (ev.event_start_date <= today && ev.event_end_date >= today) {
    return 'Ongoing today — Unassigned!'
  }
  if (ev.days_until_start === 1) {
    return 'Starts tomorrow — Unassigned!'
  }
  if (ev.days_until_start > 1 && ev.days_until_start <= 3) {
    return `Starts in ${ev.days_until_start} days — Unassigned!`
  }
  if (ev.event_start_date < today) {
    return 'Past due — Unassigned'
  }
  return 'Needs Technician'
}

const unassignedActiveEvents = computed(() => {
  return localEvents.value.filter((ev) => isEventUnassigned(ev))
})

const urgentUnassignedEvents = computed(() => {
  return localEvents.value
    .filter((ev) => isEventUrgentUnassigned(ev))
    .sort((a, b) => a.event_start_date.localeCompare(b.event_start_date))
})

// ── Venue & Staff Conflict Detection ("Double-Booking" Alerts) ──────────────
function isEventActiveForConflict(ev) {
  const st = (ev?.ticket_status || '').toLowerCase()
  return !['cancelled', 'closed'].includes(st)
}

function getEventVenueConflicts(ev) {
  if (!ev) return []
  if (Array.isArray(ev.venue_conflicts)) return ev.venue_conflicts
  if (!isEventActiveForConflict(ev)) return []
  const venueClean = (ev.event_venue || '').trim().toLowerCase()
  if (isVirtualOrUnspecifiedVenue(venueClean)) return []

  return localEvents.value
    .filter((other) => {
      if (other.client_referenceNo === ev.client_referenceNo) return false
      if (!isEventActiveForConflict(other)) return false
      const otherVenue = (other.event_venue || '').trim().toLowerCase()
      if (otherVenue !== venueClean) return false
      return ev.event_start_date <= other.event_end_date && ev.event_end_date >= other.event_start_date
    })
    .map((other) => ({
      reference_no: other.client_referenceNo,
      client_name: other.client_name,
      venue: other.event_venue,
      start_date: other.event_start_date,
      end_date: other.event_end_date,
      status: other.ticket_status,
    }))
}

function getEventStaffConflicts(ev) {
  if (!ev) return []
  if (Array.isArray(ev.staff_conflicts)) return ev.staff_conflicts
  if (!isEventActiveForConflict(ev)) return []
  const staffA = [...new Set([ev.ticket_assigned_staff, ev.ticket_attended_by].map((s) => (s || '').trim()).filter(Boolean))]
  if (staffA.length === 0) return []

  return localEvents.value
    .filter((other) => {
      if (other.client_referenceNo === ev.client_referenceNo) return false
      if (!isEventActiveForConflict(other)) return false
      if (!(ev.event_start_date <= other.event_end_date && ev.event_end_date >= other.event_start_date)) return false
      const staffB = [other.ticket_assigned_staff, other.ticket_attended_by].map((s) => (s || '').trim()).filter(Boolean)
      return staffA.some((s) => staffB.includes(s))
    })
    .map((other) => {
      const staffB = [other.ticket_assigned_staff, other.ticket_attended_by].map((s) => (s || '').trim()).filter(Boolean)
      const shared = staffA.filter((s) => staffB.includes(s))
      return {
        reference_no: other.client_referenceNo,
        client_name: other.client_name,
        staff: shared.join(', '),
        venue: other.event_venue,
        start_date: other.event_start_date,
        end_date: other.event_end_date,
        status: other.ticket_status,
      }
    })
}

function hasVenueConflict(ev) {
  if (!ev) return false
  if (typeof ev.has_venue_conflict === 'boolean') return ev.has_venue_conflict
  return getEventVenueConflicts(ev).length > 0
}

function hasStaffConflict(ev) {
  if (!ev) return false
  if (typeof ev.has_staff_conflict === 'boolean') return ev.has_staff_conflict
  return getEventStaffConflicts(ev).length > 0
}

function hasEventConflict(ev) {
  if (!ev) return false
  if (typeof ev.has_conflict === 'boolean') return ev.has_conflict
  return hasVenueConflict(ev) || hasStaffConflict(ev)
}

function getConflictSummaryLabel(ev) {
  const vConf = hasVenueConflict(ev)
  const sConf = hasStaffConflict(ev)
  if (vConf && sConf) return 'Venue & Staff Conflict'
  if (vConf) return 'Venue Double-Booked'
  if (sConf) return 'Staff Double-Booked'
  return ''
}

const conflictingEvents = computed(() => {
  return localEvents.value
    .filter((ev) => hasEventConflict(ev))
    .sort((a, b) => a.event_start_date.localeCompare(b.event_start_date))
})

function openEventByReference(refNo) {
  const found = localEvents.value.find((e) => e.client_referenceNo === refNo)
  if (found) {
    openEventPreview(found)
  }
}

// Proactive Staff Conflict Check when assigning staff inside Event Preview Modal
function getStaffConflictsForPreviewEvent(staffName) {
  if (!staffName || !activePreviewEvent.value) return []
  const ev = activePreviewEvent.value
  const start = ev.event_start_date
  const end = ev.event_end_date || start
  const cleanStaff = staffName.trim().toLowerCase()

  return localEvents.value.filter((other) => {
    if (other.client_referenceNo === ev.client_referenceNo) return false
    if (!isEventActiveForConflict(other)) return false
    const assigned = (other.ticket_assigned_staff || '').trim().toLowerCase()
    const attended = (other.ticket_attended_by || '').trim().toLowerCase()
    if (assigned !== cleanStaff && attended !== cleanStaff) return false
    return start <= other.event_end_date && end >= other.event_start_date
  })
}

// Proactive Venue Conflict Check while editing schedule in Event Preview Modal
const editingScheduleVenueConflicts = computed(() => {
  if (!isEditingSchedule.value || !activePreviewEvent.value) return []
  const venue = (quickScheduleForm.value.event_venue || '').trim().toLowerCase()
  if (isVirtualOrUnspecifiedVenue(venue)) return []
  const start = quickScheduleForm.value.event_start_date
  const end = quickScheduleForm.value.event_end_date || start
  if (!start) return []

  return localEvents.value.filter((other) => {
    if (other.client_referenceNo === activePreviewEvent.value.client_referenceNo) return false
    if (!isEventActiveForConflict(other)) return false
    const otherVenue = (other.event_venue || '').trim().toLowerCase()
    if (otherVenue !== venue) return false
    return start <= other.event_end_date && end >= other.event_start_date
  })
})

function toggleNeedsStaffFilter() {
  onlyUnassignedFilter.value = !onlyUnassignedFilter.value
  if (onlyUnassignedFilter.value) {
    staffFilter.value = 'All'
  }
}

function toggleMultiDayFilter() {
  onlyMultiDayFilter.value = !onlyMultiDayFilter.value
}

function toggleConflictsFilter() {
  onlyConflictsFilter.value = !onlyConflictsFilter.value
}

function clearAllFilters() {
  statusFilter.value = 'All'
  divisionFilter.value = 'All'
  venueFilter.value = 'All'
  customVenueFilter.value = ''
  staffFilter.value = 'All'
  searchQuery.value = ''
  onlyUnassignedFilter.value = false
  onlyMultiDayFilter.value = false
  onlyConflictsFilter.value = false
}

const hasActiveFilters = computed(() => {
  return (
    statusFilter.value !== 'All' ||
    divisionFilter.value !== 'All' ||
    venueFilter.value !== 'All' ||
    customVenueFilter.value.trim() !== '' ||
    staffFilter.value !== 'All' ||
    searchQuery.value.trim() !== '' ||
    onlyUnassignedFilter.value ||
    onlyMultiDayFilter.value ||
    onlyConflictsFilter.value
  )
})

// ── Custom Dropdown Helpers ─────────────────────────────────────────────────
const selectedDivisionOption = computed(() => {
  return DIVISION_OPTIONS.find((d) => d.value === divisionFilter.value) || DIVISION_OPTIONS[0]
})

const divisionCountsMap = computed(() => {
  const counts = { All: localEvents.value.length }
  for (const opt of DIVISION_OPTIONS) {
    if (opt.value === 'All') continue
    counts[opt.value] = 0
  }
  for (const ev of localEvents.value) {
    for (const opt of DIVISION_OPTIONS) {
      if (opt.value === 'All') continue
      if (ev.client_division_agency === opt.value || ev.client_division_label === opt.value) {
        counts[opt.value] = (counts[opt.value] || 0) + 1
      }
    }
  }
  return counts
})

function getDivisionCount(divValue) {
  return divisionCountsMap.value[divValue] ?? 0
}

function selectDivision(val) {
  divisionFilter.value = val
  showDivisionMenu.value = false
}

// Venue Filter Dropdown Helpers
const venueCountsMap = computed(() => {
  const counts = { All: localEvents.value.length, Others: 0 }
  for (const v of QUICK_VENUES) {
    counts[v] = 0
  }
  for (const ev of localEvents.value) {
    const rawVenue = (ev.event_venue || '').trim()
    const matchedStandard = QUICK_VENUES.find((v) => v.toLowerCase() === rawVenue.toLowerCase())
    if (matchedStandard) {
      counts[matchedStandard] = (counts[matchedStandard] || 0) + 1
    } else {
      counts.Others = (counts.Others || 0) + 1
    }
  }
  return counts
})

function getVenueCount(venueKey) {
  return venueCountsMap.value[venueKey] ?? 0
}

function selectVenueFilter(val) {
  venueFilter.value = val
  if (val !== 'Others') {
    customVenueFilter.value = ''
    showVenueMenu.value = false
  }
}

const selectedVenueFilterLabel = computed(() => {
  if (venueFilter.value === 'All') return 'All Venues'
  if (venueFilter.value === 'Others') {
    const custom = customVenueFilter.value.trim()
    return custom ? `Others: ${custom}` : 'Others (Custom)'
  }
  return venueFilter.value
})

const filteredStaffOptions = computed(() => {
  const q = staffSearchText.value.toLowerCase().trim()
  if (!q) return normalizedStaffList.value
  return normalizedStaffList.value.filter((s) => s.toLowerCase().includes(q))
})

const filteredQuickAssignStaff = computed(() => {
  const q = quickAssignSearchText.value.toLowerCase().trim()
  if (!q) return normalizedStaffList.value
  return normalizedStaffList.value.filter((s) => s.toLowerCase().includes(q))
})

const staffEventCountsMap = computed(() => {
  const map = {}
  for (const ev of localEvents.value) {
    const a = ev.ticket_assigned_staff
    const b = ev.ticket_attended_by
    if (a) {
      map[a] = (map[a] || 0) + 1
    }
    if (b && b !== a) {
      map[b] = (map[b] || 0) + 1
    }
  }
  return map
})

function getStaffEventCount(staffName) {
  return staffEventCountsMap.value[staffName] || 0
}

function selectStaffFilter(val) {
  staffFilter.value = val
  if (val !== 'All') {
    onlyUnassignedFilter.value = false
  }
  showStaffMenu.value = false
}

function getInitials(name) {
  const str = typeof name === 'string' ? name : String(name?.name || name?.username || '')
  if (!str) return '?'
  return str
    .split(' ')
    .filter(Boolean)
    .slice(0, 2)
    .map((part) => part[0]?.toUpperCase())
    .join('')
}

function jumpToMonth(monthNum) {
  const target = `${pickerYear.value}-${monthNum}`
  currentMonthStr.value = target
  selectedDate.value = `${target}-01`
  showMonthPicker.value = false
  fetchDataForMonth(target)
}

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
      return b.duration_days - a.duration_days || a.event_start_date.localeCompare(b.event_start_date)
    })

    // Track/Lane Allocation (prevent overlap)
    const trackOccupancy = []
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

  // Needs Staff / Unassigned Toggle Filter
  if (onlyUnassignedFilter.value) {
    list = list.filter((ev) => isEventUnassigned(ev))
  }

  // Multi-day Toggle Filter
  if (onlyMultiDayFilter.value) {
    list = list.filter((ev) => ev.duration_days > 1 || ev.is_multi_day)
  }

  // Schedule Conflicts Toggle Filter
  if (onlyConflictsFilter.value) {
    list = list.filter((ev) => hasEventConflict(ev))
  }

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

  // Venue Filter (including 'Others' + custom venue input)
  if (venueFilter.value && venueFilter.value !== 'All') {
    if (venueFilter.value === 'Others') {
      const customQuery = customVenueFilter.value.toLowerCase().trim()
      list = list.filter((ev) => {
        const rawVenue = (ev.event_venue || '').trim()
        const isStd = isStandardVenueName(rawVenue)
        if (isStd) return false
        if (!customQuery) return true
        return rawVenue.toLowerCase().includes(customQuery)
      })
    } else {
      const targetVenue = venueFilter.value.toLowerCase().trim()
      list = list.filter((ev) => (ev.event_venue || '').trim().toLowerCase() === targetVenue)
    }
  }

  // Staff Filter
  if (staffFilter.value && staffFilter.value !== 'All') {
    if (staffFilter.value === 'Unassigned') {
      list = list.filter((ev) => isEventUnassigned(ev))
    } else {
      list = list.filter((ev) => {
        return (
          ev.ticket_assigned_staff === staffFilter.value ||
          ev.ticket_attended_by === staffFilter.value
        )
      })
    }
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

// ── Venue Occupancy View Computation ────────────────────────────────────────
const venueOccupancyCards = computed(() => {
  const today = todayStr.value
  const cards = [
    ...QUICK_VENUES.map((venueName) => ({
      key: venueName,
      name: venueName,
      isCustomGroup: false,
      icon: VENUE_META[venueName]?.icon || 'fa-location-dot',
      desc: VENUE_META[venueName]?.desc || 'Event Venue',
      events: [],
    })),
    {
      key: 'Others',
      name: customVenueFilter.value.trim()
        ? `Others (${customVenueFilter.value.trim()})`
        : 'Others / External Venues',
      isCustomGroup: true,
      icon: VENUE_META.Others.icon,
      desc: VENUE_META.Others.desc,
      events: [],
    },
  ]

  filteredEvents.value.forEach((ev) => {
    const rawVenue = (ev.event_venue || '').trim()
    const matchedCard = cards.find(
      (c) => !c.isCustomGroup && c.key.toLowerCase() === rawVenue.toLowerCase()
    )
    if (matchedCard) {
      matchedCard.events.push(ev)
    } else {
      const othersCard = cards.find((c) => c.isCustomGroup)
      if (othersCard) {
        othersCard.events.push(ev)
      }
    }
  })

  return cards
    .filter((card) => {
      if (venueFilter.value === 'All') return true
      return card.key.toLowerCase() === venueFilter.value.toLowerCase()
    })
    .map((card) => {
      card.events.sort((a, b) => a.event_start_date.localeCompare(b.event_start_date))

      const activeEvents = card.events.filter((ev) => isEventActiveForConflict(ev))
      const occupiedTodayEvents = activeEvents.filter(
        (ev) => ev.event_start_date <= today && ev.event_end_date >= today
      )
      const conflictedInVenue = activeEvents.filter((ev) => hasVenueConflict(ev))
      const totalBookedDays = activeEvents.reduce((acc, ev) => acc + (Number(ev.duration_days) || 1), 0)

      let availabilityStatus = 'Available Today'
      let availabilityTheme = 'bg-emerald-50 text-emerald-700 border-emerald-200'
      let availabilityDot = 'bg-emerald-500'

      if (conflictedInVenue.length > 0) {
        availabilityStatus = `Double-Booked (${conflictedInVenue.length})`
        availabilityTheme = 'bg-rose-600 text-white border-rose-600'
        availabilityDot = 'bg-white'
      } else if (occupiedTodayEvents.length > 0) {
        availabilityStatus = 'Occupied Today'
        availabilityTheme = 'bg-amber-100 text-amber-900 border-amber-300'
        availabilityDot = 'bg-amber-600'
      }

      return {
        ...card,
        totalBookings: card.events.length,
        activeBookingsCount: activeEvents.length,
        occupiedTodayCount: occupiedTodayEvents.length,
        conflictCount: conflictedInVenue.length,
        totalBookedDays,
        availabilityStatus,
        availabilityTheme,
        availabilityDot,
      }
    })
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

  ongoing.sort((a, b) => a.event_start_date.localeCompare(b.event_start_date))
  upcoming.sort((a, b) => a.event_start_date.localeCompare(b.event_start_date))
  future.sort((a, b) => a.event_start_date.localeCompare(b.event_start_date))
  past.sort((a, b) => b.event_end_date.localeCompare(a.event_end_date))

  return [
    { title: 'Happening Today & Ongoing', icon: 'fa-circle-play', key: 'ongoing', items: ongoing, badgeColor: 'bg-emerald-600 text-white' },
    { title: 'Upcoming (Next 7 Days)', icon: 'fa-calendar-day', key: 'upcoming', items: upcoming, badgeColor: 'bg-blue-900 text-white' },
    { title: 'Later This Month & Beyond', icon: 'fa-calendar-plus', key: 'future', items: future, badgeColor: 'bg-indigo-700 text-white' },
    { title: 'Concluded & Past Events', icon: 'fa-clock-rotate-left', key: 'past', items: past, badgeColor: 'bg-slate-600 text-white' },
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
  if (currentView.value === 'week') {
    const curr = new Date(selectedDate.value)
    curr.setDate(curr.getDate() - 7)
    selectedDate.value = curr.toISOString().slice(0, 10)
    currentMonthStr.value = selectedDate.value.slice(0, 7)
  } else {
    const [year, month] = currentMonthStr.value.split('-').map(Number)
    const prev = new Date(year, month - 2, 1)
    currentMonthStr.value = prev.toISOString().slice(0, 7)
    fetchDataForMonth(currentMonthStr.value)
  }
}

function nextPeriod() {
  if (currentView.value === 'week') {
    const curr = new Date(selectedDate.value)
    curr.setDate(curr.getDate() + 7)
    selectedDate.value = curr.toISOString().slice(0, 10)
    currentMonthStr.value = selectedDate.value.slice(0, 7)
  } else {
    const [year, month] = currentMonthStr.value.split('-').map(Number)
    const next = new Date(year, month, 1)
    currentMonthStr.value = next.toISOString().slice(0, 7)
    fetchDataForMonth(currentMonthStr.value)
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
        venue: venueFilter.value,
        custom_venue: customVenueFilter.value,
        staff: staffFilter.value,
        search: searchQuery.value,
      },
    })
    if (data?.events) {
      localEvents.value = data.events
      if (activePreviewEvent.value) {
        const refreshed = data.events.find(
          (e) => e.client_referenceNo === activePreviewEvent.value.client_referenceNo
        )
        if (refreshed) {
          activePreviewEvent.value = refreshed
          quickAssignStaff.value = refreshed.ticket_assigned_staff || ''
        }
      }
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

// ── Click-to-Schedule / Quick Event Creation Handlers ───────────────────────
function openCreateModalForDate(dateStr = '', mode = 'standard', prefillVenue = '') {
  const targetDate = dateStr || selectedDate.value || todayStr.value
  selectedDate.value = targetDate
  createEventDate.value = targetDate
  createEventVenue.value = prefillVenue || (venueFilter.value !== 'All' ? (venueFilter.value === 'Others' ? (customVenueFilter.value.trim() || 'Others') : venueFilter.value) : '')
  createModalMode.value = mode
  showCreateMenu.value = false
  showCreateEventModal.value = true
}

function closeCreateEventModal() {
  showCreateEventModal.value = false
}

function onEventTicketCreated() {
  showCreateEventModal.value = false
  fetchDataForMonth(currentMonthStr.value)
}

// ── Modal & Inline Quick-Action Handlers ────────────────────────────────────
function openEventPreview(event) {
  activePreviewEvent.value = event
  quickAssignStaff.value = event.ticket_assigned_staff || ''
  quickAssignSearchText.value = ''
  isEditingSchedule.value = false
  showQuickCompleteForm.value = false
  showQuickAssignMenu.value = false
  const initialVenue = event.event_venue && event.event_venue !== 'Venue not specified' ? event.event_venue : ''
  quickScheduleForm.value = {
    event_start_date: event.event_start_date || '',
    event_end_date: event.event_end_date || event.event_start_date || '',
    event_venue: initialVenue,
    event_remarks: event.event_remarks || '',
    other_request: event.other_request || '',
  }
  syncQuickScheduleVenueFields(initialVenue)
  quickCompleteForm.value = {
    ticket_support_type:
      event.ticket_support_type && event.ticket_support_type !== 'Events'
        ? event.ticket_support_type
        : 'Audio & Visual Setup',
    ticket_actions_taken: '',
    ticket_remarks: event.ticket_remarks || '',
  }
  showEventPreviewModal.value = true
}

function closeEventPreview() {
  showEventPreviewModal.value = false
  activePreviewEvent.value = null
  isEditingSchedule.value = false
  showQuickCompleteForm.value = false
  showQuickAssignMenu.value = false
  quickAssignSearchText.value = ''
}

// Prev / Next Event Navigation inside Preview Modal
const currentPreviewIndex = computed(() => {
  if (!activePreviewEvent.value) return -1
  return filteredEvents.value.findIndex(
    (e) => e.client_referenceNo === activePreviewEvent.value.client_referenceNo
  )
})

const hasPrevPreviewEvent = computed(() => currentPreviewIndex.value > 0)
const hasNextPreviewEvent = computed(
  () => currentPreviewIndex.value >= 0 && currentPreviewIndex.value < filteredEvents.value.length - 1
)

function prevPreviewEvent() {
  if (hasPrevPreviewEvent.value) {
    openEventPreview(filteredEvents.value[currentPreviewIndex.value - 1])
  }
}

function nextPreviewEvent() {
  if (hasNextPreviewEvent.value) {
    openEventPreview(filteredEvents.value[currentPreviewIndex.value + 1])
  }
}

async function copyReferenceNumber(refNo) {
  if (!refNo) return
  try {
    await navigator.clipboard.writeText(refNo)
    toast.info(`Reference #${refNo} copied to clipboard`)
  } catch {
    toast.info(`Reference #${refNo}`)
  }
}

async function quickAssignTechnician() {
  if (!activePreviewEvent.value?.ticket_id) return
  if (!quickAssignStaff.value) {
    toast.warning('Please select a staff member to assign.')
    return
  }

  isQuickSaving.value = true
  try {
    const currentStatus = activePreviewEvent.value.ticket_status || 'New'
    const nextStatus = ['New', 'Pending'].includes(currentStatus) ? 'In Progress' : currentStatus

    await axios.put(
      route('tickets.update', activePreviewEvent.value.ticket_id),
      {
        ticket_assigned_staff: quickAssignStaff.value,
        ticket_status: nextStatus,
      },
      { headers: { Accept: 'application/json' } }
    )

    toast.success(`Assigned #${activePreviewEvent.value.client_referenceNo} to ${quickAssignStaff.value}!`)
    await fetchDataForMonth(currentMonthStr.value)
  } catch (err) {
    const msg = err.response?.data?.message || 'Failed to assign staff.'
    toast.error(msg)
  } finally {
    isQuickSaving.value = false
  }
}

async function quickAttendEvent() {
  if (!activePreviewEvent.value?.ticket_id) return

  isQuickSaving.value = true
  try {
    await axios.put(
      route('tickets.attend', activePreviewEvent.value.ticket_id),
      {
        ticket_status: 'In Progress',
      },
      { headers: { Accept: 'application/json' } }
    )

    toast.success(`Event #${activePreviewEvent.value.client_referenceNo} is now In Progress!`)
    await fetchDataForMonth(currentMonthStr.value)
  } catch (err) {
    const msg = err.response?.data?.message || 'Failed to attend event.'
    toast.error(msg)
  } finally {
    isQuickSaving.value = false
  }
}

async function saveQuickSchedule() {
  if (!activePreviewEvent.value?.ticket_id) return
  const f = quickScheduleForm.value
  if (!f.event_start_date) {
    toast.error('Event start date is required.')
    return
  }
  if (!f.event_venue?.trim()) {
    toast.error('Event venue is required.')
    return
  }
  if (f.event_end_date && f.event_end_date < f.event_start_date) {
    toast.error('Event end date cannot be earlier than start date.')
    return
  }

  isQuickSaving.value = true
  try {
    await axios.put(
      route('tickets.update', activePreviewEvent.value.ticket_id),
      {
        ticket_type: 'Events',
        event_start_date: f.event_start_date,
        event_end_date: f.event_end_date || f.event_start_date,
        event_venue: f.event_venue.trim(),
        event_remarks: f.event_remarks?.trim() || '',
        other_request: f.other_request?.trim() || '',
      },
      { headers: { Accept: 'application/json' } }
    )

    toast.success('Event schedule and venue updated!')
    isEditingSchedule.value = false
    await fetchDataForMonth(currentMonthStr.value)
  } catch (err) {
    const msg = err.response?.data?.message || 'Failed to update event schedule.'
    toast.error(msg)
  } finally {
    isQuickSaving.value = false
  }
}

async function submitQuickComplete() {
  if (!activePreviewEvent.value?.ticket_id) return
  if (!quickCompleteForm.value.ticket_actions_taken?.trim()) {
    toast.warning('Please enter the actions taken to complete this event.')
    return
  }

  isQuickSaving.value = true
  try {
    await axios.put(
      route('tickets.complete', activePreviewEvent.value.ticket_id),
      {
        json: true,
        ticket_status: 'Completed',
        ticket_type: 'Events',
        ticket_nature_of_support: 'Events',
        ticket_support_type: quickCompleteForm.value.ticket_support_type || 'Audio & Visual Setup',
        ticket_actions_taken: quickCompleteForm.value.ticket_actions_taken.trim(),
        ticket_remarks: quickCompleteForm.value.ticket_remarks?.trim() || '',
      },
      { headers: { Accept: 'application/json' } }
    )

    toast.success(`Event #${activePreviewEvent.value.client_referenceNo} marked as Completed!`)
    showQuickCompleteForm.value = false
    await fetchDataForMonth(currentMonthStr.value)
  } catch (err) {
    const msg = err.response?.data?.message || 'Failed to complete event.'
    toast.error(msg)
  } finally {
    isQuickSaving.value = false
  }
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
    messages_count: event.messages_count || 0,
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

// ── Visual Styling Helpers (Aligned with System Theme: AllTicketsV2 / ModalTicketDetails) ──
function getStatusTheme(status) {
  switch (status?.toLowerCase()) {
    case 'in progress':
      return {
        bg: 'bg-emerald-600',
        bgSubtle: 'bg-emerald-50 text-emerald-700 border-emerald-200 ring-1 ring-emerald-600/20',
        text: 'text-emerald-700',
        border: 'border-emerald-500',
        dot: 'bg-emerald-500',
        bar: 'bg-gradient-to-r from-emerald-600 to-teal-600 text-white shadow-xs',
      }
    case 'completed':
      return {
        bg: 'bg-purple-600',
        bgSubtle: 'bg-purple-50 text-purple-700 border-purple-200 ring-1 ring-purple-600/20',
        text: 'text-purple-700',
        border: 'border-purple-500',
        dot: 'bg-purple-500',
        bar: 'bg-gradient-to-r from-purple-600 to-indigo-600 text-white shadow-xs',
      }
    case 'pending':
      return {
        bg: 'bg-amber-500',
        bgSubtle: 'bg-amber-50 text-amber-800 border-amber-200 ring-1 ring-amber-600/20',
        text: 'text-amber-700',
        border: 'border-amber-500',
        dot: 'bg-amber-500',
        bar: 'bg-gradient-to-r from-amber-500 to-amber-600 text-white shadow-xs',
      }
    case 'cancelled':
      return {
        bg: 'bg-rose-600',
        bgSubtle: 'bg-rose-50 text-rose-700 border-rose-200 ring-1 ring-rose-600/20',
        text: 'text-rose-700',
        border: 'border-rose-500',
        dot: 'bg-rose-500',
        bar: 'bg-gradient-to-r from-rose-600 to-rose-700 text-white shadow-xs',
      }
    case 'closed':
      return {
        bg: 'bg-slate-600',
        bgSubtle: 'bg-slate-100 text-slate-700 border-slate-300 ring-1 ring-slate-500/20',
        text: 'text-slate-700',
        border: 'border-slate-500',
        dot: 'bg-slate-500',
        bar: 'bg-gradient-to-r from-slate-600 to-slate-700 text-white shadow-xs',
      }
    case 'new':
    default:
      return {
        bg: 'bg-blue-600',
        bgSubtle: 'bg-blue-50 text-blue-700 border-blue-200 ring-1 ring-blue-600/20',
        text: 'text-blue-700',
        border: 'border-blue-500',
        dot: 'bg-blue-600',
        bar: 'bg-gradient-to-r from-blue-700 to-blue-600 text-white shadow-xs',
      }
  }
}

function getStatusPillActiveClass(st) {
  switch (st) {
    case 'New':
      return 'bg-blue-600 text-white shadow-xs'
    case 'Pending':
      return 'bg-amber-500 text-white shadow-xs'
    case 'In Progress':
      return 'bg-emerald-600 text-white shadow-xs'
    case 'Completed':
      return 'bg-purple-600 text-white shadow-xs'
    case 'Closed':
      return 'bg-slate-700 text-white shadow-xs'
    case 'Cancelled':
      return 'bg-rose-600 text-white shadow-xs'
    default:
      return 'bg-blue-900 text-white shadow-xs'
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
      <!-- ── 1. TOP COMMAND BAR & METRICS (System Navy/Slate Theme) ──────────────── -->
      <div class="bg-white rounded-2xl border border-slate-200/90 shadow-xs p-4 sm:p-5 flex flex-col lg:flex-row lg:items-center justify-between gap-4">
        <div class="flex items-start sm:items-center gap-3.5">
          <div class="w-11 h-11 rounded-2xl bg-gradient-to-br from-blue-950 via-blue-900 to-indigo-900 text-white flex items-center justify-center shadow-md shadow-blue-950/20 shrink-0">
            <i class="fa-solid fa-calendar-days text-lg text-blue-200"></i>
          </div>
          <div>
            <div class="flex items-center gap-2.5 flex-wrap">
              <h1 class="text-xl sm:text-2xl font-bold text-slate-900 tracking-tight">
                Events Calendar &amp; Monitoring
              </h1>
              <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-xs font-bold bg-blue-50 text-blue-800 border border-blue-200">
                <span class="w-1.5 h-1.5 rounded-full bg-blue-600"></span>
                {{ filteredEvents.length }} Event{{ filteredEvents.length === 1 ? '' : 's' }}
              </span>
            </div>
            <p class="text-xs sm:text-sm text-slate-500 mt-0.5">
              Track venue schedules, IT technician dispatches, and multi-day event durations across DOST-STII.
            </p>
          </div>
        </div>

        <!-- Header Actions: Link to All Tickets + Split-Button Schedule Event Ticket -->
        <div class="flex items-center gap-2.5 flex-wrap">
          <Link
            :href="route('tickets-v2.index')"
            class="inline-flex items-center gap-2 px-3.5 py-2 text-xs font-bold rounded-xl border border-slate-300 bg-white text-slate-700 hover:bg-slate-50 hover:border-slate-400 transition shadow-2xs"
          >
            <i class="fa-solid fa-table-list text-slate-500"></i>
            <span>All Tickets Queue</span>
          </Link>

          <!-- Split-Button Dropdown for Creating Standard vs Late Event Ticket -->
          <div v-click-outside="() => (showCreateMenu = false)" class="relative inline-flex rounded-xl shadow-sm">
            <button
              type="button"
              @click="openCreateModalForDate(selectedDate || todayStr, 'standard')"
              class="inline-flex items-center gap-2 pl-4 pr-3 py-2 text-xs font-bold rounded-l-xl bg-blue-900 text-white hover:bg-blue-800 transition cursor-pointer"
            >
              <i class="fa-solid fa-calendar-plus text-blue-200"></i>
              <span>Schedule Event Ticket</span>
            </button>
            <button
              type="button"
              @click="showCreateMenu = !showCreateMenu"
              class="px-2.5 py-2 text-xs font-bold rounded-r-xl bg-blue-950 text-blue-200 hover:bg-blue-800 hover:text-white border-l border-blue-800 transition cursor-pointer"
              title="More event creation options"
            >
              <i class="fa-solid fa-chevron-down text-[10px] transition-transform duration-150" :class="showCreateMenu ? 'rotate-180' : ''"></i>
            </button>

            <!-- Dropdown Menu -->
            <div
              v-if="showCreateMenu"
              class="absolute right-0 top-full mt-2 w-64 rounded-2xl bg-white border border-slate-200 shadow-xl py-1.5 z-40"
            >
              <div class="px-3 py-1.5 text-[10px] font-bold uppercase tracking-wider text-slate-400 border-b border-slate-100">
                Create Event Support Ticket
              </div>
              <button
                type="button"
                @click="openCreateModalForDate(selectedDate || todayStr, 'standard')"
                class="w-full px-3.5 py-2.5 text-left hover:bg-slate-50 flex items-start gap-3 transition cursor-pointer"
              >
                <span class="w-8 h-8 rounded-xl bg-blue-50 text-blue-700 border border-blue-200 flex items-center justify-center shrink-0 mt-0.5">
                  <i class="fa-solid fa-calendar-plus text-xs"></i>
                </span>
                <div>
                  <div class="text-xs font-bold text-slate-900">Standard Event Request</div>
                  <div class="text-[11px] text-slate-500">Schedule an upcoming or today's event</div>
                </div>
              </button>
              <button
                type="button"
                @click="openCreateModalForDate(selectedDate || todayStr, 'late')"
                class="w-full px-3.5 py-2.5 text-left hover:bg-amber-50/60 flex items-start gap-3 transition cursor-pointer"
              >
                <span class="w-8 h-8 rounded-xl bg-amber-50 text-amber-700 border border-amber-200 flex items-center justify-center shrink-0 mt-0.5">
                  <i class="fa-solid fa-clock-rotate-left text-xs"></i>
                </span>
                <div>
                  <div class="text-xs font-bold text-slate-900">Late / Backdated Event</div>
                  <div class="text-[11px] text-slate-500">Log a past event with actions taken</div>
                </div>
              </button>
            </div>
          </div>
        </div>
      </div>

      <!-- ── 2. KPI SUMMARY METRIC CARDS (Interactive & System Theme Aligned) ──── -->
      <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-3">
        <!-- Card 1: Total Events -->
        <button
          type="button"
          @click="clearAllFilters"
          class="text-left bg-white p-3.5 sm:p-4 rounded-2xl border border-slate-200/90 shadow-2xs hover:border-slate-300 hover:shadow-xs transition cursor-pointer group"
        >
          <div class="flex items-center justify-between text-xs text-slate-500 font-semibold">
            <span>Total Events</span>
            <span class="w-7 h-7 rounded-lg bg-slate-100 text-slate-600 flex items-center justify-center group-hover:bg-blue-900 group-hover:text-white transition">
              <i class="fa-solid fa-calendar text-xs"></i>
            </span>
          </div>
          <div class="mt-2 text-2xl font-black text-slate-900">
            {{ localKpis.total_events || 0 }}
          </div>
          <div class="mt-0.5 text-[11px] text-slate-500">
            Recorded in system
          </div>
        </button>

        <!-- Card 2: This Month -->
        <button
          type="button"
          @click="setView('month')"
          class="text-left bg-white p-3.5 sm:p-4 rounded-2xl border border-slate-200/90 shadow-2xs hover:border-blue-300 hover:shadow-xs transition cursor-pointer group"
        >
          <div class="flex items-center justify-between text-xs text-slate-500 font-semibold">
            <span>This Month</span>
            <span class="w-7 h-7 rounded-lg bg-blue-50 text-blue-700 flex items-center justify-center group-hover:bg-blue-600 group-hover:text-white transition">
              <i class="fa-solid fa-calendar-check text-xs"></i>
            </span>
          </div>
          <div class="mt-2 text-2xl font-black text-blue-700">
            {{ localKpis.this_month_count || 0 }}
          </div>
          <div class="mt-0.5 text-[11px] text-slate-500 truncate">
            {{ monthYearHeading }}
          </div>
        </button>

        <!-- Card 3: Ongoing Today -->
        <button
          type="button"
          @click="goToToday"
          class="text-left bg-white p-3.5 sm:p-4 rounded-2xl border border-emerald-200/90 bg-gradient-to-br from-emerald-50/60 to-white shadow-2xs hover:border-emerald-300 hover:shadow-xs transition cursor-pointer"
        >
          <div class="flex items-center justify-between text-xs text-emerald-800 font-bold">
            <span class="flex items-center gap-1.5">
              <span class="w-2 h-2 rounded-full bg-emerald-500" :class="{ 'animate-pulse': !isAnyModalOpen }"></span>
              Ongoing Today
            </span>
            <span class="w-7 h-7 rounded-lg bg-emerald-100 text-emerald-700 flex items-center justify-center">
              <i class="fa-solid fa-play text-[10px]"></i>
            </span>
          </div>
          <div class="mt-2 text-2xl font-black text-emerald-700">
            {{ localKpis.ongoing_today_count || 0 }}
          </div>
          <div class="mt-0.5 text-[11px] text-emerald-700/80 font-medium">
            Active today • Jump to today
          </div>
        </button>

        <!-- Card 4: Upcoming (7 Days) -->
        <button
          type="button"
          @click="setView('agenda')"
          class="text-left bg-white p-3.5 sm:p-4 rounded-2xl border border-slate-200/90 shadow-2xs hover:border-amber-300 hover:shadow-xs transition cursor-pointer"
        >
          <div class="flex items-center justify-between text-xs text-slate-500 font-semibold">
            <span>Next 7 Days</span>
            <span class="w-7 h-7 rounded-lg bg-amber-50 text-amber-600 flex items-center justify-center">
              <i class="fa-solid fa-forward text-xs"></i>
            </span>
          </div>
          <div class="mt-2 text-2xl font-black text-amber-600">
            {{ localKpis.upcoming_count || 0 }}
          </div>
          <div class="mt-0.5 text-[11px] text-slate-500">
            Upcoming schedules
          </div>
        </button>

        <!-- Card 5: Multi-Day Events (Interactive Filter) -->
        <button
          type="button"
          @click="toggleMultiDayFilter"
          class="text-left p-3.5 sm:p-4 rounded-2xl border transition cursor-pointer shadow-2xs"
          :class="onlyMultiDayFilter
            ? 'bg-indigo-50/80 border-indigo-400 ring-2 ring-indigo-500/20'
            : 'bg-white border-slate-200/90 hover:border-indigo-300'"
        >
          <div class="flex items-center justify-between text-xs font-semibold" :class="onlyMultiDayFilter ? 'text-indigo-800' : 'text-slate-500'">
            <span>Multi-Day Events</span>
            <span class="w-7 h-7 rounded-lg flex items-center justify-center" :class="onlyMultiDayFilter ? 'bg-indigo-600 text-white' : 'bg-indigo-50 text-indigo-600'">
              <i class="fa-solid fa-arrows-left-right text-xs"></i>
            </span>
          </div>
          <div class="mt-2 text-2xl font-black text-indigo-700">
            {{ localKpis.multi_day_count || 0 }}
          </div>
          <div class="mt-0.5 text-[11px]" :class="onlyMultiDayFilter ? 'text-indigo-700 font-bold' : 'text-slate-500'">
            {{ onlyMultiDayFilter ? 'Showing multi-day only' : 'Spanning > 1 day' }}
          </div>
        </button>

        <!-- Card 6: Unassigned / Completed -->
        <button
          v-if="unassignedActiveEvents.length > 0"
          type="button"
          @click="toggleNeedsStaffFilter"
          class="text-left p-3.5 sm:p-4 rounded-2xl border transition cursor-pointer shadow-2xs"
          :class="[
            onlyUnassignedFilter ? 'ring-2 ring-rose-500/30' : '',
            urgentUnassignedEvents.length > 0
              ? 'border-rose-300 bg-gradient-to-br from-rose-50 via-amber-50/40 to-white'
              : 'border-amber-300 bg-gradient-to-br from-amber-50/70 to-white',
          ]"
        >
          <div class="flex items-center justify-between text-xs font-bold" :class="urgentUnassignedEvents.length > 0 ? 'text-rose-700' : 'text-amber-800'">
            <span class="flex items-center gap-1.5">
              <span
                class="w-2 h-2 rounded-full"
                :class="[
                  urgentUnassignedEvents.length > 0 ? 'bg-rose-500' : 'bg-amber-500',
                  !isAnyModalOpen ? 'animate-ping' : '',
                ]"
              ></span>
              Needs Staff
            </span>
            <span class="w-7 h-7 rounded-lg flex items-center justify-center" :class="urgentUnassignedEvents.length > 0 ? 'bg-rose-100 text-rose-700' : 'bg-amber-100 text-amber-700'">
              <i class="fa-solid fa-triangle-exclamation text-xs"></i>
            </span>
          </div>
          <div class="mt-2 text-2xl font-black" :class="urgentUnassignedEvents.length > 0 ? 'text-rose-600' : 'text-amber-700'">
            {{ unassignedActiveEvents.length }}
          </div>
          <div class="mt-0.5 text-[11px] font-semibold" :class="onlyUnassignedFilter ? 'text-rose-700' : 'text-slate-600'">
            {{ onlyUnassignedFilter ? 'Showing unassigned only' : urgentUnassignedEvents.length > 0 ? `${urgentUnassignedEvents.length} urgent (≤ 72h)` : 'Click to filter unassigned' }}
          </div>
        </button>

        <button
          v-else
          type="button"
          @click="statusFilter = statusFilter === 'Completed' ? 'All' : 'Completed'"
          class="text-left bg-white p-3.5 sm:p-4 rounded-2xl border border-slate-200/90 shadow-2xs hover:border-purple-300 transition cursor-pointer"
        >
          <div class="flex items-center justify-between text-xs text-slate-500 font-semibold">
            <span>Completed</span>
            <span class="w-7 h-7 rounded-lg bg-purple-50 text-purple-600 flex items-center justify-center">
              <i class="fa-solid fa-circle-check text-xs"></i>
            </span>
          </div>
          <div class="mt-2 text-2xl font-black text-purple-700">
            {{ localKpis.completed_count || 0 }}
          </div>
          <div class="mt-0.5 text-[11px] text-slate-500">
            Finished events
          </div>
        </button>
      </div>

      <!-- ── 2.5 URGENT UNASSIGNED UPCOMING EVENT WARNING BANNER ───────────────── -->
      <div
        v-if="urgentUnassignedEvents.length > 0"
        class="rounded-2xl border border-rose-200 bg-gradient-to-r from-rose-50 via-amber-50/60 to-white p-3.5 sm:p-4 shadow-2xs flex flex-col lg:flex-row lg:items-center justify-between gap-3"
      >
        <div class="space-y-2">
          <div class="flex items-center gap-2.5 text-rose-900 font-bold text-xs sm:text-sm">
            <span class="inline-flex items-center justify-center w-7 h-7 rounded-xl bg-rose-600 text-white text-xs shadow-xs shrink-0">
              <i class="fa-solid fa-bell" :class="{ 'animate-bounce': !isAnyModalOpen }"></i>
            </span>
            <span>
              Urgent Dispatch Warning: {{ urgentUnassignedEvents.length }} upcoming/ongoing event{{ urgentUnassignedEvents.length > 1 ? 's have' : ' has' }} no assigned IT technician!
            </span>
          </div>

          <!-- Quick Jump Chips to Urgent Unassigned Events -->
          <div class="flex items-center gap-2 flex-wrap pl-9">
            <button
              v-for="urg in urgentUnassignedEvents.slice(0, 5)"
              :key="urg.client_referenceNo"
              type="button"
              @click="openEventPreview(urg)"
              class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-xl text-[11px] font-bold bg-white text-rose-700 border border-rose-200 hover:bg-blue-900 hover:text-white hover:border-blue-900 transition shadow-2xs cursor-pointer"
            >
              <span>#{{ urg.client_referenceNo }}</span>
              <span class="opacity-60">•</span>
              <span class="truncate max-w-[150px]">{{ urg.event_venue }}</span>
              <span class="px-1.5 py-0.5 rounded-md bg-rose-100 text-rose-800 text-[10px] font-extrabold">
                {{ urg.is_ongoing ? 'Today' : (urg.days_until_start === 1 ? 'Tomorrow' : `In ${urg.days_until_start}d`) }}
              </span>
              <i class="fa-solid fa-user-plus text-[10px] ml-0.5"></i>
            </button>
          </div>
        </div>

        <div class="flex items-center gap-2 shrink-0 pl-9 lg:pl-0">
          <button
            type="button"
            @click="toggleNeedsStaffFilter"
            class="px-3.5 py-2 rounded-xl text-xs font-bold border transition cursor-pointer"
            :class="onlyUnassignedFilter
              ? 'bg-rose-600 text-white border-rose-600 shadow-xs'
              : 'bg-white text-rose-700 border-rose-300 hover:bg-rose-50'"
          >
            <i class="fa-solid fa-filter mr-1.5"></i>
            {{ onlyUnassignedFilter ? 'Showing Unassigned Only' : `Filter All Unassigned (${unassignedActiveEvents.length})` }}
          </button>
        </div>
      </div>

      <!-- ── 2.6 VENUE & STAFF DOUBLE-BOOKING CONFLICT ALERT BANNER ────────────── -->
      <div
        v-if="conflictingEvents.length > 0"
        class="rounded-2xl border border-orange-300 bg-gradient-to-r from-orange-50 via-amber-50/70 to-white p-3.5 sm:p-4 shadow-2xs flex flex-col lg:flex-row lg:items-center justify-between gap-3"
      >
        <div class="space-y-2">
          <div class="flex items-center gap-2.5 text-orange-950 font-bold text-xs sm:text-sm">
            <span class="inline-flex items-center justify-center w-7 h-7 rounded-xl bg-orange-600 text-white text-xs shadow-xs shrink-0">
              <i class="fa-solid fa-triangle-exclamation"></i>
            </span>
            <span>
              Schedule Conflict Alert: {{ conflictingEvents.length }} active event{{ conflictingEvents.length > 1 ? 's have' : ' has' }} overlapping Venue or IT Staff bookings!
            </span>
          </div>

          <!-- Quick Jump Chips to Conflicted Events -->
          <div class="flex items-center gap-2 flex-wrap pl-9">
            <button
              v-for="confEv in conflictingEvents.slice(0, 6)"
              :key="`conf-${confEv.client_referenceNo}`"
              type="button"
              @click="openEventPreview(confEv)"
              class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-xl text-[11px] font-bold bg-white text-orange-900 border border-orange-300 hover:bg-blue-900 hover:text-white hover:border-blue-900 transition shadow-2xs cursor-pointer"
            >
              <span>#{{ confEv.client_referenceNo }}</span>
              <span class="opacity-60">•</span>
              <span class="truncate max-w-[140px]">{{ confEv.event_venue }}</span>
              <span class="px-1.5 py-0.5 rounded-md bg-orange-100 text-orange-900 text-[10px] font-extrabold">
                {{ getConflictSummaryLabel(confEv) }}
              </span>
            </button>
          </div>
        </div>

        <div class="flex items-center gap-2 shrink-0 pl-9 lg:pl-0">
          <button
            type="button"
            @click="setView('venues')"
            class="px-3 py-2 rounded-xl text-xs font-bold border border-orange-300 bg-white text-orange-800 hover:bg-orange-50 transition cursor-pointer"
          >
            <i class="fa-solid fa-building-circle-exclamation mr-1.5"></i>
            Inspect Venue Occupancy
          </button>
          <button
            type="button"
            @click="toggleConflictsFilter"
            class="px-3.5 py-2 rounded-xl text-xs font-bold border transition cursor-pointer"
            :class="onlyConflictsFilter
              ? 'bg-orange-600 text-white border-orange-600 shadow-xs'
              : 'bg-white text-orange-800 border-orange-300 hover:bg-orange-50'"
          >
            <i class="fa-solid fa-filter mr-1.5"></i>
            {{ onlyConflictsFilter ? 'Showing Conflicts Only' : `Filter Conflicts (${conflictingEvents.length})` }}
          </button>
        </div>
      </div>

      <!-- ── 3. GOOGLE CALENDAR CONTROLS & CUSTOM DROPDOWN FILTER TOOLBAR ──────── -->
      <div class="bg-white rounded-2xl border border-slate-200/90 shadow-xs p-4 space-y-3.5">
        <div class="flex flex-col lg:flex-row lg:items-center justify-between gap-4">
          <!-- Left: Google Calendar Style Navigation (Today, <, >, Interactive Month/Year Picker) -->
          <div class="flex items-center gap-2.5 flex-wrap">
            <button
              type="button"
              @click="goToToday"
              class="px-3.5 py-1.5 text-xs font-bold rounded-xl border border-slate-300 bg-white text-slate-700 hover:bg-slate-50 hover:border-slate-400 transition active:scale-95 cursor-pointer shadow-2xs"
            >
              <i class="fa-regular fa-calendar-check mr-1.5 text-blue-700"></i>
              Today
            </button>

            <div class="inline-flex items-center rounded-xl border border-slate-300 bg-white overflow-hidden shadow-2xs">
              <button
                type="button"
                @click="prevPeriod"
                class="px-2.5 py-1.5 text-slate-600 hover:bg-slate-100 hover:text-slate-900 transition cursor-pointer"
                title="Previous month / week"
              >
                <i class="fa-solid fa-chevron-left text-xs"></i>
              </button>
              <div class="w-px h-4 bg-slate-200"></div>
              <button
                type="button"
                @click="nextPeriod"
                class="px-2.5 py-1.5 text-slate-600 hover:bg-slate-100 hover:text-slate-900 transition cursor-pointer"
                title="Next month / week"
              >
                <i class="fa-solid fa-chevron-right text-xs"></i>
              </button>
            </div>

            <!-- Interactive Month / Year Quick-Jump Popover Trigger -->
            <div v-click-outside="() => (showMonthPicker = false)" class="relative">
              <button
                type="button"
                @click="showMonthPicker = !showMonthPicker"
                class="inline-flex items-center gap-2 px-3 py-1.5 rounded-xl text-base sm:text-lg font-extrabold text-slate-900 hover:bg-slate-100 transition cursor-pointer"
                title="Click to jump to any month or year"
              >
                <span>{{ currentView === 'week' ? weekHeading : monthYearHeading }}</span>
                <i class="fa-solid fa-caret-down text-xs text-slate-400 transition-transform" :class="showMonthPicker ? 'rotate-180 text-blue-700' : ''"></i>
              </button>

              <!-- Month/Year Popover Card -->
              <div
                v-if="showMonthPicker"
                class="absolute left-0 top-full mt-2 w-72 rounded-2xl bg-white border border-slate-200 shadow-xl p-3.5 z-40 space-y-3"
              >
                <div class="flex items-center justify-between pb-2 border-b border-slate-100">
                  <button
                    type="button"
                    @click="pickerYear--"
                    class="w-7 h-7 rounded-lg hover:bg-slate-100 text-slate-600 flex items-center justify-center cursor-pointer"
                  >
                    <i class="fa-solid fa-chevron-left text-xs"></i>
                  </button>
                  <span class="text-sm font-black text-slate-900">{{ pickerYear }}</span>
                  <button
                    type="button"
                    @click="pickerYear++"
                    class="w-7 h-7 rounded-lg hover:bg-slate-100 text-slate-600 flex items-center justify-center cursor-pointer"
                  >
                    <i class="fa-solid fa-chevron-right text-xs"></i>
                  </button>
                </div>

                <div class="grid grid-cols-3 gap-1.5">
                  <button
                    v-for="m in MONTH_NAMES_SHORT"
                    :key="m.num"
                    type="button"
                    @click="jumpToMonth(m.num)"
                    class="py-2 rounded-xl text-xs font-bold transition cursor-pointer"
                    :class="currentMonthStr === `${pickerYear}-${m.num}`
                      ? 'bg-blue-900 text-white shadow-xs'
                      : 'text-slate-700 hover:bg-slate-100'"
                  >
                    {{ m.label }}
                  </button>
                </div>

                <div class="pt-2 border-t border-slate-100 flex justify-between items-center">
                  <button
                    type="button"
                    @click="() => { showMonthPicker = false; goToToday() }"
                    class="text-[11px] font-bold text-blue-700 hover:underline cursor-pointer"
                  >
                    Jump to Current Month
                  </button>
                  <button
                    type="button"
                    @click="showMonthPicker = false"
                    class="text-[11px] font-semibold text-slate-400 hover:text-slate-600 cursor-pointer"
                  >
                    Close
                  </button>
                </div>
              </div>
            </div>

            <div v-if="isLoading" class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full bg-blue-50 text-blue-700 text-xs font-semibold">
              <i class="fa-solid fa-spinner animate-spin text-xs"></i>
              <span>Syncing...</span>
            </div>
          </div>

          <!-- Right: Segmented View Switcher (Matches AllTicketsV2) + Search -->
          <div class="flex items-center gap-2.5 flex-wrap">
            <!-- View Mode Switcher -->
            <div class="inline-flex rounded-xl bg-slate-200/70 p-1">
              <button
                type="button"
                @click="setView('month')"
                class="px-3 py-1.5 text-xs rounded-lg transition cursor-pointer flex items-center gap-1.5"
                :class="currentView === 'month'
                  ? 'bg-white text-slate-900 shadow-xs font-bold'
                  : 'text-slate-600 hover:text-slate-900 font-medium'"
              >
                <i class="fa-solid fa-calendar-days text-blue-700"></i>
                <span>Month</span>
              </button>
              <button
                type="button"
                @click="setView('week')"
                class="px-3 py-1.5 text-xs rounded-lg transition cursor-pointer flex items-center gap-1.5"
                :class="currentView === 'week'
                  ? 'bg-white text-slate-900 shadow-xs font-bold'
                  : 'text-slate-600 hover:text-slate-900 font-medium'"
              >
                <i class="fa-solid fa-calendar-week text-blue-700"></i>
                <span>Week</span>
              </button>
              <button
                type="button"
                @click="setView('agenda')"
                class="px-3 py-1.5 text-xs rounded-lg transition cursor-pointer flex items-center gap-1.5"
                :class="currentView === 'agenda'
                  ? 'bg-white text-slate-900 shadow-xs font-bold'
                  : 'text-slate-600 hover:text-slate-900 font-medium'"
              >
                <i class="fa-solid fa-list-check text-blue-700"></i>
                <span>Agenda</span>
              </button>
              <button
                type="button"
                @click="setView('venues')"
                class="px-3 py-1.5 text-xs rounded-lg transition cursor-pointer flex items-center gap-1.5"
                :class="currentView === 'venues'
                  ? 'bg-white text-slate-900 shadow-xs font-bold'
                  : 'text-slate-600 hover:text-slate-900 font-medium'"
              >
                <i class="fa-solid fa-building-circle-check text-blue-700"></i>
                <span>Venues</span>
              </button>
            </div>

            <!-- Search Input with Clear Button -->
            <div class="relative min-w-[230px] flex-1 sm:flex-initial">
              <i class="fa-solid fa-magnifying-glass absolute left-3 top-1/2 -translate-y-1/2 text-xs text-slate-400"></i>
              <input
                v-model="searchQuery"
                type="text"
                placeholder="Search venue, client, #ref..."
                class="w-full pl-8 pr-7 py-1.5 text-xs rounded-xl border border-slate-300 bg-slate-50/70 text-slate-900 placeholder-slate-400 focus:bg-white focus:outline-hidden focus:ring-2 focus:ring-blue-600/20 focus:border-blue-600 transition"
              />
              <button
                v-if="searchQuery"
                type="button"
                @click="searchQuery = ''"
                class="absolute right-2.5 top-1/2 -translate-y-1/2 text-slate-400 hover:text-slate-600 cursor-pointer"
                title="Clear search"
              >
                <i class="fa-solid fa-circle-xmark text-xs"></i>
              </button>
            </div>
          </div>
        </div>

        <!-- Bottom Filter Bar: System-Colored Status Pills + Custom Popover Dropdowns -->
        <div class="flex items-center justify-between gap-3 pt-3 border-t border-slate-100 flex-wrap">
          <!-- Status Pills + Needs Staff & Conflicts Toggles -->
          <div class="flex items-center gap-1.5 overflow-x-auto no-scrollbar py-0.5">
            <button
              v-for="(count, st) in localStatusCounts"
              :key="st"
              type="button"
              @click="statusFilter = st"
              class="px-2.5 py-1 text-xs font-bold rounded-xl transition shrink-0 flex items-center gap-1.5 cursor-pointer"
              :class="statusFilter === st
                ? getStatusPillActiveClass(st)
                : 'bg-slate-100 text-slate-600 hover:bg-slate-200/80'"
            >
              <span
                v-if="st !== 'All'"
                class="w-1.5 h-1.5 rounded-full"
                :class="statusFilter === st ? 'bg-white' : getStatusTheme(st).dot"
              ></span>
              <span>{{ st }}</span>
              <span class="opacity-80 text-[10px]">({{ count }})</span>
            </button>

            <!-- Dedicated Needs Staff Filter Pill -->
            <button
              type="button"
              @click="toggleNeedsStaffFilter"
              class="px-2.5 py-1 text-xs font-bold rounded-xl transition shrink-0 flex items-center gap-1.5 border cursor-pointer"
              :class="onlyUnassignedFilter
                ? 'bg-rose-600 text-white border-rose-600 shadow-xs'
                : unassignedActiveEvents.length > 0
                  ? 'bg-amber-50 text-amber-800 border-amber-300 hover:bg-amber-100'
                  : 'bg-slate-100 text-slate-500 border-transparent'"
            >
              <i class="fa-solid fa-triangle-exclamation text-[10px]" :class="onlyUnassignedFilter ? 'text-white' : 'text-amber-500'"></i>
              <span>Needs Staff</span>
              <span class="opacity-85 text-[10px]">({{ unassignedActiveEvents.length }})</span>
            </button>

            <!-- Dedicated Double-Booking Conflicts Filter Pill -->
            <button
              v-if="conflictingEvents.length > 0 || onlyConflictsFilter"
              type="button"
              @click="toggleConflictsFilter"
              class="px-2.5 py-1 text-xs font-bold rounded-xl transition shrink-0 flex items-center gap-1.5 border cursor-pointer"
              :class="onlyConflictsFilter
                ? 'bg-orange-600 text-white border-orange-600 shadow-xs'
                : 'bg-orange-50 text-orange-800 border-orange-300 hover:bg-orange-100'"
            >
              <i class="fa-solid fa-clone text-[10px]" :class="onlyConflictsFilter ? 'text-white' : 'text-orange-600'"></i>
              <span>Conflicts</span>
              <span class="opacity-85 text-[10px]">({{ conflictingEvents.length }})</span>
            </button>
          </div>

          <!-- Custom Popover Dropdown Filters: Venue + Division + Staff -->
          <div class="flex items-center gap-2 text-xs flex-wrap">
            <!-- Custom Venue Popover Dropdown (with 'Others' + Custom Venue Input) -->
            <div v-click-outside="() => (showVenueMenu = false)" class="relative flex items-center gap-1.5">
              <button
                type="button"
                @click="showVenueMenu = !showVenueMenu"
                class="inline-flex items-center gap-2 px-3 py-1.5 rounded-xl border text-xs font-semibold transition cursor-pointer"
                :class="venueFilter !== 'All'
                  ? 'border-blue-300 bg-blue-50/70 text-blue-900'
                  : 'border-slate-300 bg-white text-slate-700 hover:bg-slate-50'"
              >
                <i class="fa-solid fa-location-dot text-rose-500 text-[11px]"></i>
                <span class="text-slate-500 font-medium">Venue:</span>
                <span class="font-bold truncate max-w-[140px]">{{ selectedVenueFilterLabel }}</span>
                <i class="fa-solid fa-chevron-down text-[10px] text-slate-400 transition-transform" :class="showVenueMenu ? 'rotate-180' : ''"></i>
              </button>

              <!-- Inline Custom Venue Input when 'Others' is selected -->
              <div v-if="venueFilter === 'Others'" class="relative w-44 sm:w-52">
                <i class="fa-solid fa-pen-to-square absolute left-2.5 top-1/2 -translate-y-1/2 text-[10px] text-blue-600"></i>
                <input
                  v-model="customVenueFilter"
                  type="text"
                  placeholder="Enter custom venue..."
                  class="w-full pl-7 pr-6 py-1.5 text-xs rounded-xl border border-blue-300 bg-blue-50/40 text-slate-900 placeholder-slate-400 focus:bg-white focus:outline-hidden focus:ring-2 focus:ring-blue-600/20 focus:border-blue-600"
                />
                <button
                  v-if="customVenueFilter"
                  type="button"
                  @click="customVenueFilter = ''"
                  class="absolute right-2 top-1/2 -translate-y-1/2 text-slate-400 hover:text-slate-600 cursor-pointer"
                >
                  <i class="fa-solid fa-circle-xmark text-[11px]"></i>
                </button>
              </div>

              <!-- Venue Popover Panel -->
              <div
                v-if="showVenueMenu"
                class="absolute right-0 top-full mt-1.5 w-76 rounded-2xl bg-white border border-slate-200 shadow-xl py-1.5 z-40"
              >
                <div class="px-3 py-1.5 text-[10px] font-bold uppercase tracking-wider text-slate-400 border-b border-slate-100 flex items-center justify-between">
                  <span>Filter by Event Venue</span>
                  <button
                    type="button"
                    @click="() => { setView('venues'); showVenueMenu = false }"
                    class="text-[10px] font-bold text-blue-700 hover:underline cursor-pointer normal-case"
                  >
                    Occupancy View →
                  </button>
                </div>

                <!-- All Venues -->
                <button
                  type="button"
                  @click="selectVenueFilter('All')"
                  class="w-full px-3 py-2 text-left hover:bg-slate-50 flex items-center justify-between gap-2 transition cursor-pointer"
                  :class="venueFilter === 'All' ? 'bg-blue-50/60' : ''"
                >
                  <div class="flex items-center gap-2.5">
                    <span
                      class="w-7 h-7 rounded-lg flex items-center justify-center shrink-0 text-xs"
                      :class="venueFilter === 'All' ? 'bg-blue-900 text-white' : 'bg-slate-100 text-slate-600'"
                    >
                      <i class="fa-solid fa-building"></i>
                    </span>
                    <div>
                      <div class="text-xs font-bold text-slate-900">All Venues</div>
                      <div class="text-[10px] text-slate-500">All internal &amp; external locations</div>
                    </div>
                  </div>
                  <div class="flex items-center gap-1.5 shrink-0">
                    <span class="px-1.5 py-0.5 rounded-md bg-slate-100 text-slate-600 text-[10px] font-bold">
                      {{ getVenueCount('All') }}
                    </span>
                    <i v-if="venueFilter === 'All'" class="fa-solid fa-check text-blue-700 text-xs"></i>
                  </div>
                </button>

                <!-- Standard Preset Venues -->
                <button
                  v-for="vName in QUICK_VENUES"
                  :key="vName"
                  type="button"
                  @click="selectVenueFilter(vName)"
                  class="w-full px-3 py-2 text-left hover:bg-slate-50 flex items-center justify-between gap-2 transition cursor-pointer"
                  :class="venueFilter === vName ? 'bg-blue-50/60' : ''"
                >
                  <div class="flex items-center gap-2.5 min-w-0">
                    <span
                      class="w-7 h-7 rounded-lg flex items-center justify-center shrink-0 text-xs"
                      :class="venueFilter === vName ? 'bg-blue-900 text-white' : 'bg-slate-100 text-slate-600'"
                    >
                      <i class="fa-solid" :class="VENUE_META[vName]?.icon || 'fa-location-dot'"></i>
                    </span>
                    <div class="min-w-0">
                      <div class="text-xs font-bold text-slate-900 truncate">{{ vName }}</div>
                      <div class="text-[10px] text-slate-500 truncate">{{ VENUE_META[vName]?.desc }}</div>
                    </div>
                  </div>
                  <div class="flex items-center gap-1.5 shrink-0">
                    <span class="px-1.5 py-0.5 rounded-md bg-slate-100 text-slate-600 text-[10px] font-bold">
                      {{ getVenueCount(vName) }}
                    </span>
                    <i v-if="venueFilter === vName" class="fa-solid fa-check text-blue-700 text-xs"></i>
                  </div>
                </button>

                <!-- 'Others' Option + Custom Venue Input -->
                <div class="border-t border-slate-100 mt-1 pt-1">
                  <button
                    type="button"
                    @click="selectVenueFilter('Others')"
                    class="w-full px-3 py-2 text-left hover:bg-slate-50 flex items-center justify-between gap-2 transition cursor-pointer"
                    :class="venueFilter === 'Others' ? 'bg-blue-50/60' : ''"
                  >
                    <div class="flex items-center gap-2.5 min-w-0">
                      <span
                        class="w-7 h-7 rounded-lg flex items-center justify-center shrink-0 text-xs"
                        :class="venueFilter === 'Others' ? 'bg-blue-900 text-white' : 'bg-slate-100 text-slate-600'"
                      >
                        <i class="fa-solid fa-map-location-dot"></i>
                      </span>
                      <div class="min-w-0">
                        <div class="text-xs font-bold text-slate-900 truncate">Others</div>
                        <div class="text-[10px] text-slate-500 truncate">Custom or external venue</div>
                      </div>
                    </div>
                    <div class="flex items-center gap-1.5 shrink-0">
                      <span class="px-1.5 py-0.5 rounded-md bg-slate-100 text-slate-600 text-[10px] font-bold">
                        {{ getVenueCount('Others') }}
                      </span>
                      <i v-if="venueFilter === 'Others'" class="fa-solid fa-check text-blue-700 text-xs"></i>
                    </div>
                  </button>

                  <!-- Custom Venue Input Box inside Dropdown when 'Others' is selected -->
                  <div v-if="venueFilter === 'Others'" class="px-3 pb-2 pt-1 space-y-1.5">
                    <label class="block text-[10px] font-bold text-blue-900 uppercase tracking-wider">
                      Enter Custom Venue (Optional)
                    </label>
                    <div class="flex items-center gap-1.5">
                      <input
                        v-model="customVenueFilter"
                        type="text"
                        placeholder="e.g. PICC, Hotel, Hall..."
                        class="w-full px-2.5 py-1.5 text-xs rounded-xl border border-blue-300 bg-blue-50/40 text-slate-900 focus:bg-white focus:outline-hidden focus:border-blue-600"
                        @keydown.enter="showVenueMenu = false"
                      />
                      <button
                        type="button"
                        @click="showVenueMenu = false"
                        class="px-2.5 py-1.5 rounded-xl bg-blue-900 text-white text-[11px] font-bold hover:bg-blue-800 cursor-pointer shrink-0"
                      >
                        Apply
                      </button>
                    </div>
                  </div>
                </div>
              </div>
            </div>

            <!-- Custom Division Popover Dropdown -->
            <div v-click-outside="() => (showDivisionMenu = false)" class="relative">
              <button
                type="button"
                @click="showDivisionMenu = !showDivisionMenu"
                class="inline-flex items-center gap-2 px-3 py-1.5 rounded-xl border text-xs font-semibold transition cursor-pointer"
                :class="divisionFilter !== 'All'
                  ? 'border-blue-300 bg-blue-50/70 text-blue-900'
                  : 'border-slate-300 bg-white text-slate-700 hover:bg-slate-50'"
              >
                <i class="fa-solid text-slate-400 text-[11px]" :class="selectedDivisionOption.icon"></i>
                <span class="text-slate-500 font-medium">Division:</span>
                <span class="font-bold">{{ selectedDivisionOption.label }}</span>
                <i class="fa-solid fa-chevron-down text-[10px] text-slate-400 transition-transform" :class="showDivisionMenu ? 'rotate-180' : ''"></i>
              </button>

              <!-- Division Popover Panel -->
              <div
                v-if="showDivisionMenu"
                class="absolute right-0 top-full mt-1.5 w-72 rounded-2xl bg-white border border-slate-200 shadow-xl py-1.5 z-40"
              >
                <div class="px-3 py-1.5 text-[10px] font-bold uppercase tracking-wider text-slate-400 border-b border-slate-100">
                  Filter by Division / Agency
                </div>
                <button
                  v-for="opt in DIVISION_OPTIONS"
                  :key="opt.value"
                  type="button"
                  @click="selectDivision(opt.value)"
                  class="w-full px-3 py-2 text-left hover:bg-slate-50 flex items-center justify-between gap-2 transition cursor-pointer"
                  :class="divisionFilter === opt.value ? 'bg-blue-50/60' : ''"
                >
                  <div class="flex items-center gap-2.5 min-w-0">
                    <span
                      class="w-7 h-7 rounded-lg flex items-center justify-center shrink-0 text-xs"
                      :class="divisionFilter === opt.value ? 'bg-blue-900 text-white' : 'bg-slate-100 text-slate-600'"
                    >
                      <i class="fa-solid" :class="opt.icon"></i>
                    </span>
                    <div class="min-w-0">
                      <div class="text-xs font-bold text-slate-900 truncate">{{ opt.label }}</div>
                      <div class="text-[10px] text-slate-500 truncate">{{ opt.full }}</div>
                    </div>
                  </div>
                  <div class="flex items-center gap-1.5 shrink-0">
                    <span class="px-1.5 py-0.5 rounded-md bg-slate-100 text-slate-600 text-[10px] font-bold">
                      {{ getDivisionCount(opt.value) }}
                    </span>
                    <i v-if="divisionFilter === opt.value" class="fa-solid fa-check text-blue-700 text-xs"></i>
                  </div>
                </button>
              </div>
            </div>

            <!-- Custom Staff Popover Dropdown with Search & Avatars -->
            <div v-click-outside="() => (showStaffMenu = false)" class="relative">
              <button
                type="button"
                @click="showStaffMenu = !showStaffMenu"
                class="inline-flex items-center gap-2 px-3 py-1.5 rounded-xl border text-xs font-semibold transition cursor-pointer"
                :class="staffFilter !== 'All'
                  ? 'border-blue-300 bg-blue-50/70 text-blue-900'
                  : 'border-slate-300 bg-white text-slate-700 hover:bg-slate-50'"
              >
                <i class="fa-solid fa-user-gear text-slate-400 text-[11px]"></i>
                <span class="text-slate-500 font-medium">Staff:</span>
                <span class="font-bold truncate max-w-[130px]">
                  {{ staffFilter === 'All' ? 'All Staff' : staffFilter === 'Unassigned' ? 'Unassigned Only' : staffFilter }}
                </span>
                <i class="fa-solid fa-chevron-down text-[10px] text-slate-400 transition-transform" :class="showStaffMenu ? 'rotate-180' : ''"></i>
              </button>

              <!-- Staff Popover Panel -->
              <div
                v-if="showStaffMenu"
                class="absolute right-0 top-full mt-1.5 w-72 rounded-2xl bg-white border border-slate-200 shadow-xl py-2 z-40"
              >
                <div class="px-3 pb-2 border-b border-slate-100">
                  <div class="relative">
                    <i class="fa-solid fa-magnifying-glass absolute left-2.5 top-1/2 -translate-y-1/2 text-[11px] text-slate-400"></i>
                    <input
                      v-model="staffSearchText"
                      type="text"
                      placeholder="Filter IT staff..."
                      class="w-full pl-7 pr-2.5 py-1.5 text-xs rounded-xl border border-slate-200 bg-slate-50 text-slate-900 placeholder-slate-400 focus:bg-white focus:outline-hidden focus:border-blue-600"
                    />
                  </div>
                </div>

                <div class="max-h-60 overflow-y-auto py-1 divide-y divide-slate-50">
                  <!-- All Staff -->
                  <button
                    type="button"
                    @click="selectStaffFilter('All')"
                    class="w-full px-3 py-2 text-left hover:bg-slate-50 flex items-center justify-between gap-2 transition cursor-pointer"
                    :class="staffFilter === 'All' ? 'bg-blue-50/60' : ''"
                  >
                    <div class="flex items-center gap-2.5">
                      <span class="w-6 h-6 rounded-full bg-slate-200 text-slate-700 flex items-center justify-center text-[10px] font-bold">
                        <i class="fa-solid fa-users"></i>
                      </span>
                      <span class="text-xs font-bold text-slate-900">All IT Staff</span>
                    </div>
                    <i v-if="staffFilter === 'All'" class="fa-solid fa-check text-blue-700 text-xs"></i>
                  </button>

                  <!-- Unassigned Only -->
                  <button
                    type="button"
                    @click="selectStaffFilter('Unassigned')"
                    class="w-full px-3 py-2 text-left hover:bg-rose-50/60 flex items-center justify-between gap-2 transition cursor-pointer"
                    :class="staffFilter === 'Unassigned' ? 'bg-rose-50' : ''"
                  >
                    <div class="flex items-center gap-2.5">
                      <span class="w-6 h-6 rounded-full bg-amber-100 text-amber-800 flex items-center justify-center text-[10px] font-bold">
                        <i class="fa-solid fa-triangle-exclamation"></i>
                      </span>
                      <span class="text-xs font-bold text-rose-700">Unassigned Only</span>
                    </div>
                    <span class="px-1.5 py-0.5 rounded-md bg-rose-100 text-rose-800 text-[10px] font-bold">
                      {{ unassignedActiveEvents.length }}
                    </span>
                  </button>

                  <!-- Individual Staff Members -->
                  <button
                    v-for="s in filteredStaffOptions"
                    :key="s"
                    type="button"
                    @click="selectStaffFilter(s)"
                    class="w-full px-3 py-2 text-left hover:bg-slate-50 flex items-center justify-between gap-2 transition cursor-pointer"
                    :class="staffFilter === s ? 'bg-blue-50/60' : ''"
                  >
                    <div class="flex items-center gap-2.5 min-w-0">
                      <span class="w-6 h-6 rounded-full bg-blue-900 text-white flex items-center justify-center text-[10px] font-bold shrink-0">
                        {{ getInitials(s) }}
                      </span>
                      <span class="text-xs font-medium text-slate-800 truncate">{{ s }}</span>
                    </div>
                    <div class="flex items-center gap-1.5 shrink-0">
                      <span class="px-1.5 py-0.5 rounded-md bg-slate-100 text-slate-600 text-[10px] font-bold">
                        {{ getStaffEventCount(s) }}
                      </span>
                      <i v-if="staffFilter === s" class="fa-solid fa-check text-blue-700 text-xs"></i>
                    </div>
                  </button>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Active Filter Chips Strip (Shown when any filter is active) -->
        <div
          v-if="hasActiveFilters"
          class="flex items-center justify-between gap-2 pt-2.5 border-t border-slate-100 flex-wrap text-xs"
        >
          <div class="flex items-center gap-1.5 flex-wrap">
            <span class="text-[11px] font-semibold text-slate-400 mr-1">Active Filters:</span>

            <span
              v-if="statusFilter !== 'All'"
              class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-[11px] font-bold bg-blue-50 text-blue-800 border border-blue-200"
            >
              Status: {{ statusFilter }}
              <button type="button" @click="statusFilter = 'All'" class="hover:text-blue-950 cursor-pointer">
                <i class="fa-solid fa-xmark"></i>
              </button>
            </span>

            <span
              v-if="venueFilter !== 'All'"
              class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-[11px] font-bold bg-blue-50 text-blue-800 border border-blue-200"
            >
              Venue: {{ selectedVenueFilterLabel }}
              <button type="button" @click="() => { venueFilter = 'All'; customVenueFilter = '' }" class="hover:text-blue-950 cursor-pointer">
                <i class="fa-solid fa-xmark"></i>
              </button>
            </span>

            <span
              v-if="divisionFilter !== 'All'"
              class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-[11px] font-bold bg-blue-50 text-blue-800 border border-blue-200"
            >
              Division: {{ selectedDivisionOption.label }}
              <button type="button" @click="divisionFilter = 'All'" class="hover:text-blue-950 cursor-pointer">
                <i class="fa-solid fa-xmark"></i>
              </button>
            </span>

            <span
              v-if="staffFilter !== 'All'"
              class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-[11px] font-bold bg-blue-50 text-blue-800 border border-blue-200"
            >
              Staff: {{ staffFilter }}
              <button type="button" @click="staffFilter = 'All'" class="hover:text-blue-950 cursor-pointer">
                <i class="fa-solid fa-xmark"></i>
              </button>
            </span>

            <span
              v-if="onlyUnassignedFilter"
              class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-[11px] font-bold bg-rose-50 text-rose-800 border border-rose-200"
            >
              Needs Staff Only
              <button type="button" @click="onlyUnassignedFilter = false" class="hover:text-rose-950 cursor-pointer">
                <i class="fa-solid fa-xmark"></i>
              </button>
            </span>

            <span
              v-if="onlyConflictsFilter"
              class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-[11px] font-bold bg-orange-50 text-orange-800 border border-orange-200"
            >
              Schedule Conflicts Only
              <button type="button" @click="onlyConflictsFilter = false" class="hover:text-orange-950 cursor-pointer">
                <i class="fa-solid fa-xmark"></i>
              </button>
            </span>

            <span
              v-if="onlyMultiDayFilter"
              class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-[11px] font-bold bg-indigo-50 text-indigo-800 border border-indigo-200"
            >
              Multi-Day Events Only
              <button type="button" @click="onlyMultiDayFilter = false" class="hover:text-indigo-950 cursor-pointer">
                <i class="fa-solid fa-xmark"></i>
              </button>
            </span>

            <span
              v-if="searchQuery.trim()"
              class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-[11px] font-bold bg-slate-100 text-slate-700 border border-slate-300"
            >
              Search: "{{ searchQuery }}"
              <button type="button" @click="searchQuery = ''" class="hover:text-slate-950 cursor-pointer">
                <i class="fa-solid fa-xmark"></i>
              </button>
            </span>
          </div>

          <button
            type="button"
            @click="clearAllFilters"
            class="text-[11px] font-bold text-rose-600 hover:text-rose-700 hover:underline cursor-pointer"
          >
            Reset All Filters
          </button>
        </div>
      </div>

      <!-- ── 4. VIEW: MONTH VIEW (GOOGLE CALENDAR MULTI-DAY PLOTTING) ──────────── -->
      <div v-if="currentView === 'month'" class="bg-white rounded-2xl border border-slate-200/90 shadow-xs overflow-hidden">
        <!-- Calendar Days Header (SUN – SAT) -->
        <div class="grid grid-cols-7 border-b border-slate-200 bg-slate-50/90 text-center py-2.5 text-[11px] font-extrabold text-slate-600 tracking-wider uppercase">
          <div>Sun</div>
          <div>Mon</div>
          <div>Tue</div>
          <div>Wed</div>
          <div>Thu</div>
          <div>Fri</div>
          <div>Sat</div>
        </div>

        <!-- Weeks Rows -->
        <div class="divide-y divide-slate-200">
          <div
            v-for="(week, wIdx) in monthGridWeeks"
            :key="wIdx"
            class="relative min-h-[124px] lg:min-h-[144px]"
          >
            <!-- Background 7 Days Grid with Day Numbers & Quick-Add Button -->
            <div class="absolute inset-0 grid grid-cols-7 divide-x divide-slate-200 pointer-events-none">
              <div
                v-for="(day, dIdx) in week.days"
                :key="dIdx"
                class="group/day p-1.5 sm:p-2 flex flex-col justify-between transition-colors pointer-events-auto cursor-pointer"
                :class="[
                  day.isCurrentMonth
                    ? 'bg-transparent hover:bg-blue-50/35'
                    : 'bg-slate-50/60 opacity-55',
                  day.isSelected ? 'ring-1 ring-inset ring-blue-600/50 bg-blue-50/25' : '',
                ]"
                title="Click to select date • Double-click or click '+' to schedule an event"
                @click="selectDay(day.dateStr)"
                @dblclick="openCreateModalForDate(day.dateStr)"
              >
                <!-- Day Number & Quick-Add Trigger -->
                <div class="flex items-center justify-between">
                  <div class="flex items-center gap-1.5">
                    <span
                      class="inline-flex items-center justify-center w-6 h-6 text-xs font-bold rounded-full"
                      :class="day.isToday
                        ? 'bg-blue-900 text-white shadow-xs font-black'
                        : 'text-slate-700'"
                    >
                      {{ day.dayNumber }}
                    </span>

                    <span v-if="day.isToday" class="hidden sm:inline-block text-[9px] font-extrabold text-blue-800 bg-blue-100 px-1.5 py-0.2 rounded uppercase tracking-wider">
                      Today
                    </span>
                  </div>

                  <!-- Hover '+' Quick-Schedule Button -->
                  <button
                    type="button"
                    @click.stop="openCreateModalForDate(day.dateStr)"
                    class="opacity-0 group-hover/day:opacity-100 focus:opacity-100 w-5 h-5 rounded-lg bg-blue-900/10 hover:bg-blue-900 text-blue-900 hover:text-white flex items-center justify-center transition text-[10px] cursor-pointer"
                    :class="day.isSelected ? 'opacity-100' : ''"
                    :title="`Schedule event on ${formatDateDisplay(day.dateStr)}`"
                  >
                    <i class="fa-solid fa-plus"></i>
                  </button>
                </div>
              </div>
            </div>

            <!-- Overlaid Events Grid (Google Calendar Tracks) -->
            <div
              class="relative z-10 grid grid-cols-7 pt-8 pb-2 px-1 gap-y-1 pointer-events-none"
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
                    hasEventConflict(seg.event)
                      ? 'ring-2 ring-orange-400'
                      : isEventUrgentUnassigned(seg.event)
                        ? 'ring-2 ring-amber-300'
                        : '',
                    'hover:brightness-110 hover:shadow-md',
                  ]"
                  :title="`#${seg.event.client_referenceNo} - ${seg.event.client_name}\nVenue: ${seg.event.event_venue}\nDuration: ${formatDateDisplay(seg.event.event_start_date)} – ${formatDateDisplay(seg.event.event_end_date)} (${seg.event.duration_days} day${seg.event.duration_days > 1 ? 's' : ''})\nStatus: ${seg.event.ticket_status}\nStaff: ${seg.event.ticket_assigned_staff || '⚠️ Unassigned'}${hasEventConflict(seg.event) ? '\n⚠️ CONFLICT: ' + getConflictSummaryLabel(seg.event) : ''}`"
                >
                  <!-- Conflict Icon OR Urgent Unassigned Warning Icon OR Multi-day icon -->
                  <span
                    v-if="hasEventConflict(seg.event)"
                    class="inline-flex items-center justify-center w-3.5 h-3.5 rounded-full bg-orange-400 text-slate-950 text-[8px] font-black shrink-0"
                    :title="getConflictSummaryLabel(seg.event)"
                  >
                    <i class="fa-solid fa-triangle-exclamation"></i>
                  </span>
                  <span
                    v-else-if="isEventUrgentUnassigned(seg.event)"
                    class="inline-flex items-center justify-center w-3.5 h-3.5 rounded-full bg-amber-300 text-rose-950 text-[9px] font-black shrink-0"
                    :class="{ 'animate-pulse': !isAnyModalOpen }"
                    title="Urgent: No technician assigned!"
                  >
                    !
                  </span>
                  <i v-else-if="seg.event.is_multi_day && seg.isStart" class="fa-solid fa-arrows-left-right text-[9px] opacity-80 shrink-0"></i>
                  <span v-else-if="!seg.isStart" class="text-[9px] opacity-75 shrink-0">◀</span>
                  <span v-else class="w-1.5 h-1.5 rounded-full bg-white shrink-0"></span>

                  <!-- Event Title: Client name + Venue -->
                  <span class="truncate tracking-tight font-medium">
                    <span class="font-bold">#{{ seg.event.client_referenceNo }}</span>
                    {{ seg.event.client_name }}
                    <span v-if="seg.event.event_venue && seg.event.event_venue !== 'Venue not specified'" class="opacity-85">
                      • {{ seg.event.event_venue }}
                    </span>
                  </span>

                  <!-- Double-Booking Conflict Pill on Bar -->
                  <span
                    v-if="hasEventConflict(seg.event) && seg.isStart"
                    class="ml-auto shrink-0 px-1 py-0.2 rounded text-[9px] font-extrabold bg-orange-300 text-slate-950"
                  >
                    Conflict
                  </span>

                  <!-- Unassigned Pill on Bar -->
                  <span
                    v-else-if="isEventUnassigned(seg.event) && seg.isStart"
                    class="ml-auto shrink-0 px-1 py-0.2 rounded text-[9px] font-extrabold bg-amber-300 text-slate-950"
                  >
                    Unassigned
                  </span>

                  <!-- Duration Badge (shown on start segment or multi-day) -->
                  <span
                    v-if="seg.event.duration_days > 1"
                    :class="(hasEventConflict(seg.event) || isEventUnassigned(seg.event)) && seg.isStart ? 'shrink-0' : 'ml-auto shrink-0'"
                    class="px-1 py-0.2 rounded text-[9px] font-bold bg-black/25 text-white/95"
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
      <div v-else-if="currentView === 'week'" class="bg-white rounded-2xl border border-slate-200/90 shadow-xs overflow-hidden">
        <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-4 lg:grid-cols-7 divide-y sm:divide-y-0 sm:divide-x divide-slate-200 min-h-[500px]">
          <div
            v-for="(day, dIdx) in weekViewDays"
            :key="dIdx"
            class="p-3 flex flex-col space-y-2.5 transition-colors"
            :class="day.isToday ? 'bg-blue-50/35' : 'bg-transparent'"
          >
            <!-- Day Column Header -->
            <div class="pb-2 border-b border-slate-200 flex items-center justify-between">
              <div>
                <span class="text-xs font-bold text-slate-500 uppercase tracking-wider block">
                  {{ day.dayName }}
                </span>
                <span
                  class="inline-flex items-center justify-center w-7 h-7 text-sm font-black rounded-full mt-0.5"
                  :class="day.isToday ? 'bg-blue-900 text-white shadow-xs' : 'text-slate-800'"
                >
                  {{ day.dayNumber }}
                </span>
              </div>

              <div class="flex items-center gap-1">
                <button
                  type="button"
                  @click="openCreateModalForDate(day.dateStr)"
                  class="w-6 h-6 rounded-lg bg-blue-50 hover:bg-blue-900 text-blue-800 hover:text-white flex items-center justify-center text-[10px] transition cursor-pointer"
                  :title="`Schedule event on ${formatDateDisplay(day.dateStr)}`"
                >
                  <i class="fa-solid fa-plus"></i>
                </button>
                <span class="text-[10px] font-bold px-1.5 py-0.5 rounded-full bg-slate-100 text-slate-600">
                  {{ day.events.length }}
                </span>
              </div>
            </div>

            <!-- Day Events List -->
            <div class="space-y-2 flex-1">
              <div
                v-for="ev in day.events"
                :key="ev.client_id"
                @click="openEventPreview(ev)"
                class="p-2.5 rounded-xl border bg-white transition cursor-pointer hover:shadow-md"
                :class="[
                  hasEventConflict(ev)
                    ? 'border-orange-300 ring-1 ring-orange-300'
                    : isEventUrgentUnassigned(ev)
                      ? 'border-rose-300 ring-1 ring-rose-300'
                      : 'border-slate-200 hover:border-blue-300',
                ]"
              >
                <!-- Status & Duration -->
                <div class="flex items-center justify-between gap-1 text-[10px]">
                  <span
                    class="px-2 py-0.5 rounded-full font-bold flex items-center gap-1"
                    :class="getStatusTheme(ev.ticket_status).bgSubtle"
                  >
                    <span class="w-1.5 h-1.5 rounded-full" :class="getStatusTheme(ev.ticket_status).dot"></span>
                    {{ ev.ticket_status }}
                  </span>
                  <span v-if="ev.duration_days > 1" class="font-bold text-indigo-700 bg-indigo-50 px-1.5 py-0.5 rounded">
                    {{ ev.duration_days }}d
                  </span>
                </div>

                <!-- Double-Booking Conflict Warning Badge -->
                <div
                  v-if="hasEventConflict(ev)"
                  class="mt-1.5 px-2 py-0.5 rounded-md text-[10px] font-extrabold bg-orange-600 text-white flex items-center gap-1"
                >
                  <i class="fa-solid fa-clone text-[9px]"></i>
                  <span class="truncate">{{ getConflictSummaryLabel(ev) }}</span>
                </div>

                <!-- Urgent Unassigned Warning Badge -->
                <div
                  v-if="isEventUnassigned(ev)"
                  class="mt-1.5 px-2 py-0.5 rounded-md text-[10px] font-extrabold flex items-center gap-1"
                  :class="isEventUrgentUnassigned(ev)
                    ? 'bg-rose-600 text-white'
                    : 'bg-amber-100 text-amber-900'"
                >
                  <i class="fa-solid fa-triangle-exclamation text-[9px]"></i>
                  <span class="truncate">{{ getUnassignedUrgencyLabel(ev) }}</span>
                </div>

                <!-- Title & Client -->
                <div class="mt-1.5 text-xs font-bold text-blue-900 line-clamp-1">
                  #{{ ev.client_referenceNo }}
                </div>
                <div class="text-[11px] font-semibold text-slate-800 truncate">
                  {{ ev.client_name }}
                </div>

                <!-- Venue -->
                <div v-if="ev.event_venue" class="mt-1 flex items-center gap-1 text-[11px] text-slate-500 truncate">
                  <i class="fa-solid fa-location-dot text-[10px] text-rose-500"></i>
                  <span class="truncate">{{ ev.event_venue }}</span>
                </div>

                <!-- Assigned Staff -->
                <div v-if="ev.ticket_assigned_staff" class="mt-2 pt-1.5 border-t border-slate-100 text-[10px] text-slate-600 flex items-center gap-1">
                  <i class="fa-solid fa-user-gear text-[9px] text-blue-700"></i>
                  <span class="truncate font-medium">{{ ev.ticket_assigned_staff }}</span>
                </div>
              </div>

              <!-- Empty state for day with Click-to-Schedule -->
              <div v-if="day.events.length === 0" class="h-28 flex flex-col items-center justify-center gap-1.5 text-[11px] text-slate-400">
                <span class="italic">No events</span>
                <button
                  type="button"
                  @click="openCreateModalForDate(day.dateStr)"
                  class="px-2.5 py-1 rounded-lg text-[10px] font-bold text-blue-800 hover:bg-blue-50 transition cursor-pointer"
                >
                  + Schedule Event
                </button>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- ── 6. VIEW: AGENDA VIEW (CHRONOLOGICAL TIMELINE) ─────────────────────── -->
      <div v-else-if="currentView === 'agenda'" class="space-y-6">
        <div v-if="agendaGroups.length === 0" class="bg-white rounded-2xl border border-slate-200/90 p-12 text-center shadow-2xs">
          <div class="w-12 h-12 rounded-2xl bg-slate-100 text-slate-400 flex items-center justify-center mx-auto mb-3">
            <i class="fa-solid fa-calendar-xmark text-xl"></i>
          </div>
          <h3 class="text-base font-bold text-slate-800">No events match your current filters</h3>
          <p class="text-xs text-slate-500 mt-1">Try clearing your search query or resetting the active filters.</p>
          <button
            v-if="hasActiveFilters"
            type="button"
            @click="clearAllFilters"
            class="mt-3 px-3.5 py-1.5 rounded-xl text-xs font-bold bg-blue-900 text-white hover:bg-blue-800 transition cursor-pointer"
          >
            Reset Filters
          </button>
        </div>

        <div v-for="grp in agendaGroups" :key="grp.key" class="space-y-3">
          <div class="flex items-center gap-2">
            <span class="px-3 py-1 text-xs font-bold rounded-xl shadow-2xs flex items-center gap-1.5" :class="grp.badgeColor">
              <i class="fa-solid text-[11px]" :class="grp.icon"></i>
              <span>{{ grp.title }}</span>
            </span>
            <span class="text-xs font-semibold text-slate-500">
              ({{ grp.items.length }} event{{ grp.items.length > 1 ? 's' : '' }})
            </span>
          </div>

          <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-3.5">
            <div
              v-for="ev in grp.items"
              :key="ev.client_id"
              @click="openEventPreview(ev)"
              class="bg-white rounded-2xl border p-4 shadow-2xs hover:shadow-md transition cursor-pointer flex flex-col justify-between space-y-3 group"
              :class="hasEventConflict(ev)
                ? 'border-orange-300 ring-1 ring-orange-300/60'
                : isEventUrgentUnassigned(ev)
                  ? 'border-rose-300 ring-1 ring-rose-300/60'
                  : 'border-slate-200/90 hover:border-blue-300'"
            >
              <div>
                <!-- Top Header: Reference & Status -->
                <div class="flex items-center justify-between gap-2 flex-wrap">
                  <span class="text-xs font-extrabold text-blue-900 bg-blue-50 px-2.5 py-0.5 rounded-lg border border-blue-200/80">
                    #{{ ev.client_referenceNo }}
                  </span>
                  <div class="flex items-center gap-1.5 flex-wrap">
                    <span
                      v-if="hasEventConflict(ev)"
                      class="px-2 py-0.5 rounded-full text-[10px] font-extrabold bg-orange-600 text-white flex items-center gap-1"
                    >
                      <i class="fa-solid fa-clone text-[9px]"></i>
                      <span>{{ getConflictSummaryLabel(ev) }}</span>
                    </span>
                    <span
                      v-if="isEventUnassigned(ev)"
                      class="px-2 py-0.5 rounded-full text-[10px] font-extrabold flex items-center gap-1"
                      :class="isEventUrgentUnassigned(ev)
                        ? 'bg-rose-600 text-white'
                        : 'bg-amber-100 text-amber-800'"
                    >
                      <i class="fa-solid fa-triangle-exclamation text-[9px]"></i>
                      <span>{{ getUnassignedUrgencyLabel(ev) }}</span>
                    </span>
                    <span
                      class="px-2.5 py-0.5 rounded-full text-[10px] font-bold flex items-center gap-1"
                      :class="getStatusTheme(ev.ticket_status).bgSubtle"
                    >
                      <span class="w-1.5 h-1.5 rounded-full" :class="getStatusTheme(ev.ticket_status).dot"></span>
                      {{ ev.ticket_status }}
                    </span>
                  </div>
                </div>

                <!-- Client & Division -->
                <h4 class="mt-2 text-sm font-bold text-slate-900 group-hover:text-blue-800 transition">
                  {{ ev.client_name }}
                </h4>
                <div class="text-xs text-slate-500">
                  Division: <span class="font-semibold text-slate-700">{{ ev.client_division_label }}</span>
                </div>

                <!-- Event Schedule & Multi-day Duration -->
                <div class="mt-3 p-3 rounded-xl bg-slate-50 border border-slate-200/80 space-y-1.5 text-xs">
                  <div class="flex items-center justify-between font-bold text-slate-800">
                    <span class="flex items-center gap-1.5">
                      <i class="fa-solid fa-calendar-day text-blue-700"></i>
                      <span>{{ formatDateDisplay(ev.event_start_date) }}</span>
                      <span v-if="ev.is_multi_day">→ {{ formatDateDisplay(ev.event_end_date) }}</span>
                    </span>
                    <span
                      class="px-2 py-0.5 rounded-md text-[10px] font-black"
                      :class="ev.is_multi_day ? 'bg-indigo-100 text-indigo-800' : 'bg-slate-200 text-slate-700'"
                    >
                      {{ ev.duration_days }} Day{{ ev.duration_days > 1 ? 's' : '' }}
                    </span>
                  </div>

                  <!-- Venue -->
                  <div class="flex items-center gap-1.5 text-slate-600 text-[11px] truncate">
                    <i class="fa-solid fa-location-dot text-rose-500 shrink-0"></i>
                    <span class="truncate font-semibold">{{ ev.event_venue || 'Venue not specified' }}</span>
                  </div>

                  <!-- Other Request if any -->
                  <div v-if="ev.other_request" class="text-[11px] text-amber-800 bg-amber-50 px-2 py-1 rounded-lg border border-amber-200 line-clamp-1">
                    <i class="fa-solid fa-list-ul mr-1"></i>
                    <span>{{ ev.other_request }}</span>
                  </div>
                </div>
              </div>

              <!-- Footer: Assigned Staff & Actions -->
              <div class="pt-2.5 border-t border-slate-100 flex items-center justify-between text-xs">
                <div
                  class="text-[11px] flex items-center gap-1.5 truncate"
                  :class="isEventUnassigned(ev) ? 'text-rose-600 font-bold' : 'text-slate-600 font-medium'"
                >
                  <i class="fa-solid fa-user-gear" :class="isEventUnassigned(ev) ? 'text-rose-500' : 'text-blue-700'"></i>
                  <span class="truncate">{{ ev.ticket_assigned_staff || 'Unassigned — Click to assign' }}</span>
                </div>

                <span class="text-blue-800 text-xs font-bold group-hover:underline">
                  Quick Manage →
                </span>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- ── 6.5 VIEW: VENUE OCCUPANCY & AVAILABILITY BOARD ───────────────────── -->
      <div v-else-if="currentView === 'venues'" class="space-y-4">
        <!-- Venue Occupancy Top Summary Strip -->
        <div class="bg-white rounded-2xl border border-slate-200/90 p-4 shadow-2xs flex flex-col sm:flex-row sm:items-center justify-between gap-3">
          <div class="flex items-center gap-3">
            <div class="w-10 h-10 rounded-xl bg-blue-50 text-blue-800 border border-blue-200 flex items-center justify-center shrink-0">
              <i class="fa-solid fa-building-circle-check text-base"></i>
            </div>
            <div>
              <h3 class="text-sm sm:text-base font-extrabold text-slate-900">
                Venue Occupancy &amp; Booking Schedule
              </h3>
              <p class="text-xs text-slate-500">
                Inspect room availability, prevent venue double-bookings, or schedule an event directly in any venue.
              </p>
            </div>
          </div>

          <!-- Quick Venue Filter Pills inside Occupancy Board -->
          <div class="flex items-center gap-1.5 flex-wrap">
            <button
              type="button"
              @click="selectVenueFilter('All')"
              class="px-2.5 py-1 rounded-xl text-xs font-bold border transition cursor-pointer"
              :class="venueFilter === 'All'
                ? 'bg-blue-900 text-white border-blue-900 shadow-2xs'
                : 'bg-white text-slate-600 border-slate-200 hover:border-blue-300'"
            >
              All Venues
            </button>
            <button
              v-for="vName in QUICK_VENUES"
              :key="`occ-pill-${vName}`"
              type="button"
              @click="selectVenueFilter(vName)"
              class="px-2.5 py-1 rounded-xl text-xs font-bold border transition cursor-pointer"
              :class="venueFilter === vName
                ? 'bg-blue-900 text-white border-blue-900 shadow-2xs'
                : 'bg-white text-slate-600 border-slate-200 hover:border-blue-300'"
            >
              {{ vName }}
            </button>
            <button
              type="button"
              @click="selectVenueFilter('Others')"
              class="px-2.5 py-1 rounded-xl text-xs font-bold border transition cursor-pointer"
              :class="venueFilter === 'Others'
                ? 'bg-blue-900 text-white border-blue-900 shadow-2xs'
                : 'bg-white text-slate-600 border-slate-200 hover:border-blue-300'"
            >
              Others
            </button>
          </div>
        </div>

        <!-- Venue Cards Grid -->
        <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-4">
          <div
            v-for="vCard in venueOccupancyCards"
            :key="vCard.key"
            class="bg-white rounded-2xl border shadow-2xs flex flex-col justify-between overflow-hidden transition"
            :class="vCard.conflictCount > 0 ? 'border-orange-300 ring-1 ring-orange-300/60' : 'border-slate-200/90'"
          >
            <!-- Venue Card Header -->
            <div>
              <div class="p-4 border-b border-slate-100 bg-gradient-to-r from-slate-50 via-white to-slate-50/60 flex items-start justify-between gap-2">
                <div class="flex items-start gap-3 min-w-0">
                  <div
                    class="w-10 h-10 rounded-xl flex items-center justify-center shrink-0 text-sm shadow-2xs"
                    :class="vCard.conflictCount > 0
                      ? 'bg-orange-600 text-white'
                      : vCard.occupiedTodayCount > 0
                        ? 'bg-blue-900 text-white'
                        : 'bg-blue-50 text-blue-800 border border-blue-200'"
                  >
                    <i class="fa-solid" :class="vCard.icon"></i>
                  </div>
                  <div class="min-w-0">
                    <h4 class="text-sm font-extrabold text-slate-900 truncate">{{ vCard.name }}</h4>
                    <p class="text-[11px] text-slate-500 truncate">{{ vCard.desc }}</p>
                  </div>
                </div>

                <!-- Today's Availability Badge -->
                <span
                  class="px-2.5 py-1 rounded-full text-[10px] font-extrabold border flex items-center gap-1.5 shrink-0"
                  :class="vCard.availabilityTheme"
                >
                  <span class="w-1.5 h-1.5 rounded-full" :class="vCard.availabilityDot"></span>
                  <span>{{ vCard.availabilityStatus }}</span>
                </span>
              </div>

              <!-- Venue Monthly Metrics Bar -->
              <div class="grid grid-cols-3 divide-x divide-slate-100 border-b border-slate-100 bg-slate-50/50 text-center py-2 text-[11px]">
                <div>
                  <span class="text-slate-400 font-semibold block text-[10px] uppercase">Bookings</span>
                  <span class="font-extrabold text-slate-800">{{ vCard.totalBookings }}</span>
                </div>
                <div>
                  <span class="text-slate-400 font-semibold block text-[10px] uppercase">Booked Days</span>
                  <span class="font-extrabold text-blue-800">{{ vCard.totalBookedDays }}d</span>
                </div>
                <div>
                  <span class="text-slate-400 font-semibold block text-[10px] uppercase">Conflicts</span>
                  <span class="font-extrabold" :class="vCard.conflictCount > 0 ? 'text-orange-600' : 'text-emerald-600'">
                    {{ vCard.conflictCount > 0 ? `${vCard.conflictCount} Alert${vCard.conflictCount > 1 ? 's' : ''}` : 'None' }}
                  </span>
                </div>
              </div>

              <!-- Scheduled Events List inside Venue -->
              <div class="p-3.5 space-y-2 max-h-[340px] overflow-y-auto overscroll-contain">
                <div
                  v-for="ev in vCard.events"
                  :key="`v-ev-${ev.client_referenceNo}`"
                  @click="openEventPreview(ev)"
                  class="p-2.5 rounded-xl border transition cursor-pointer hover:shadow-sm"
                  :class="hasVenueConflict(ev)
                    ? 'bg-orange-50/60 border-orange-300 hover:border-orange-400'
                    : ev.is_ongoing
                      ? 'bg-emerald-50/40 border-emerald-200 hover:border-emerald-300'
                      : 'bg-white border-slate-200 hover:border-blue-300'"
                >
                  <div class="flex items-center justify-between gap-2 text-[10px]">
                    <div class="flex items-center gap-1.5 min-w-0">
                      <span class="font-extrabold text-blue-900">#{{ ev.client_referenceNo }}</span>
                      <span class="text-slate-300">•</span>
                      <span class="font-bold text-slate-800 truncate">{{ ev.client_name }}</span>
                    </div>
                    <span
                      class="px-2 py-0.5 rounded-full font-bold shrink-0"
                      :class="getStatusTheme(ev.ticket_status).bgSubtle"
                    >
                      {{ ev.ticket_status }}
                    </span>
                  </div>

                  <!-- Custom venue name if inside 'Others' card -->
                  <div v-if="vCard.isCustomGroup && ev.event_venue" class="mt-1 text-[11px] font-bold text-blue-800 flex items-center gap-1">
                    <i class="fa-solid fa-location-dot text-rose-500 text-[10px]"></i>
                    <span class="truncate">{{ ev.event_venue }}</span>
                  </div>

                  <!-- Dates & Duration -->
                  <div class="mt-1.5 flex items-center justify-between gap-2 text-[11px] text-slate-600">
                    <span class="font-semibold flex items-center gap-1">
                      <i class="fa-regular fa-calendar text-blue-700 text-[10px]"></i>
                      {{ formatDateDisplay(ev.event_start_date) }}
                      <template v-if="ev.is_multi_day"> → {{ formatDateDisplay(ev.event_end_date) }}</template>
                    </span>
                    <span class="px-1.5 py-0.2 rounded bg-slate-100 text-slate-700 text-[10px] font-bold shrink-0">
                      {{ ev.duration_days }}d
                    </span>
                  </div>

                  <!-- Staff & Conflict Row -->
                  <div class="mt-1.5 pt-1.5 border-t border-slate-100 flex items-center justify-between gap-2 text-[10px]">
                    <span :class="isEventUnassigned(ev) ? 'text-rose-600 font-bold' : 'text-slate-500'">
                      <i class="fa-solid fa-user-gear mr-1"></i>
                      {{ ev.ticket_assigned_staff || 'Unassigned' }}
                    </span>

                    <span
                      v-if="hasVenueConflict(ev)"
                      class="px-1.5 py-0.5 rounded bg-orange-600 text-white font-extrabold flex items-center gap-1"
                    >
                      <i class="fa-solid fa-triangle-exclamation text-[8px]"></i>
                      Venue Conflict
                    </span>
                    <span
                      v-else-if="hasStaffConflict(ev)"
                      class="px-1.5 py-0.5 rounded bg-amber-500 text-white font-extrabold flex items-center gap-1"
                    >
                      <i class="fa-solid fa-user-clock text-[8px]"></i>
                      Staff Conflict
                    </span>
                  </div>
                </div>

                <!-- Empty State for Venue -->
                <div v-if="vCard.events.length === 0" class="py-8 text-center space-y-1.5 text-slate-400">
                  <i class="fa-regular fa-calendar-check text-xl text-emerald-500/80 block"></i>
                  <div class="text-xs font-bold text-slate-600">No bookings for these filters</div>
                  <div class="text-[11px] text-slate-400">This venue is open for scheduling</div>
                </div>
              </div>
            </div>

            <!-- Venue Card Footer: Quick Book Venue Button -->
            <div class="px-4 py-3 border-t border-slate-100 bg-slate-50/70 flex items-center justify-between gap-2">
              <span class="text-[11px] text-slate-500 font-medium">
                {{ vCard.activeBookingsCount }} active schedule{{ vCard.activeBookingsCount === 1 ? '' : 's' }}
              </span>
              <button
                type="button"
                @click="openCreateModalForDate(selectedDate || todayStr, 'standard', vCard.isCustomGroup ? (customVenueFilter.trim() || 'Others') : vCard.key)"
                class="px-3 py-1.5 rounded-xl text-xs font-bold bg-blue-900 text-white hover:bg-blue-800 transition cursor-pointer flex items-center gap-1.5 shadow-2xs"
              >
                <i class="fa-solid fa-calendar-plus text-[10px] text-blue-200"></i>
                <span>Book {{ vCard.isCustomGroup ? 'Custom Venue' : vCard.name }}</span>
              </button>
            </div>
          </div>
        </div>
      </div>

      <!-- ── 7. EVENT QUICK PREVIEW & INLINE QUICK-ACTIONS MODAL / DRAWER ────── -->
      <div
        v-if="showEventPreviewModal && activePreviewEvent"
        class="fixed inset-0 z-50 bg-slate-950/60"
        :class="previewViewMode === 'drawer' ? 'flex justify-end' : 'flex items-center justify-center p-3 sm:p-4'"
        @click.self="closeEventPreview"
      >
        <div
          class="bg-white border border-slate-200 shadow-2xl flex flex-col overflow-hidden transform-gpu"
          :class="previewViewMode === 'drawer'
            ? 'w-full max-w-2xl h-full rounded-none sm:rounded-l-2xl'
            : 'w-full max-w-2xl max-h-[92vh] rounded-2xl'"
        >
          <!-- Modal Header (Matches ModalTicketDetails / ModalAction_CreateTicket Navy Gradient) -->
          <div class="px-5 py-4 bg-gradient-to-r from-blue-950 via-blue-900 to-indigo-900 text-white shrink-0">
            <div class="flex items-start justify-between gap-3">
              <div class="space-y-1.5 min-w-0">
                <!-- Top Badges Row -->
                <div class="flex items-center gap-2 flex-wrap">
                  <button
                    type="button"
                    @click="copyReferenceNumber(activePreviewEvent.client_referenceNo)"
                    class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-lg text-xs font-extrabold bg-white/15 hover:bg-white/25 text-white border border-white/15 transition cursor-pointer"
                    title="Click to copy reference number"
                  >
                    <span>#{{ activePreviewEvent.client_referenceNo }}</span>
                    <i class="fa-regular fa-copy text-[10px] text-blue-200"></i>
                  </button>

                  <span
                    class="px-2.5 py-0.5 rounded-full text-xs font-bold flex items-center gap-1.5 bg-white text-slate-900 shadow-2xs"
                  >
                    <span class="w-2 h-2 rounded-full" :class="getStatusTheme(activePreviewEvent.ticket_status).dot"></span>
                    {{ activePreviewEvent.ticket_status }}
                  </span>

                  <span
                    v-if="hasEventConflict(activePreviewEvent)"
                    class="px-2.5 py-0.5 rounded-full text-xs font-extrabold bg-orange-400 text-slate-950 flex items-center gap-1 shadow-2xs"
                  >
                    <i class="fa-solid fa-clone text-[10px]"></i>
                    {{ getConflictSummaryLabel(activePreviewEvent) }}
                  </span>

                  <span
                    v-if="isEventUnassigned(activePreviewEvent)"
                    class="px-2.5 py-0.5 rounded-full text-xs font-extrabold bg-amber-300 text-slate-950 flex items-center gap-1 shadow-2xs"
                  >
                    <i class="fa-solid fa-triangle-exclamation text-[10px]"></i>
                    {{ getUnassignedUrgencyLabel(activePreviewEvent) }}
                  </span>
                </div>

                <!-- Client Name & Meta -->
                <h3 class="text-base sm:text-lg font-bold text-white truncate">
                  {{ activePreviewEvent.client_name }}
                </h3>
                <div class="flex items-center gap-2 text-xs text-blue-200 flex-wrap">
                  <span class="inline-flex items-center gap-1">
                    <i class="fa-solid fa-building text-[10px]"></i>
                    {{ activePreviewEvent.client_division_label }}
                  </span>
                  <span>•</span>
                  <span class="inline-flex items-center gap-1">
                    <i class="fa-solid fa-calendar-check text-[10px]"></i>
                    {{ activePreviewEvent.ticket_type || 'Events' }}
                  </span>
                </div>
              </div>

              <!-- Right Header Controls: Prev/Next Event + Modal/Drawer Switcher + Close -->
              <div class="flex items-center gap-1.5 shrink-0">
                <!-- Prev / Next Event Navigation -->
                <div class="inline-flex items-center rounded-lg bg-white/10 border border-white/15 p-0.5 mr-1">
                  <button
                    type="button"
                    :disabled="!hasPrevPreviewEvent"
                    @click="prevPreviewEvent"
                    class="w-7 h-7 rounded-md flex items-center justify-center text-xs text-blue-100 hover:bg-white/15 hover:text-white disabled:opacity-35 disabled:pointer-events-none transition cursor-pointer"
                    title="Previous event in current view"
                  >
                    <i class="fa-solid fa-chevron-left"></i>
                  </button>
                  <button
                    type="button"
                    :disabled="!hasNextPreviewEvent"
                    @click="nextPreviewEvent"
                    class="w-7 h-7 rounded-md flex items-center justify-center text-xs text-blue-100 hover:bg-white/15 hover:text-white disabled:opacity-35 disabled:pointer-events-none transition cursor-pointer"
                    title="Next event in current view"
                  >
                    <i class="fa-solid fa-chevron-right"></i>
                  </button>
                </div>

                <!-- View Mode Toggle: Modal vs Drawer (Matches ModalTicketDetails) -->
                <div class="hidden sm:inline-flex items-center rounded-lg bg-white/10 border border-white/15 p-0.5 mr-1">
                  <button
                    type="button"
                    @click="setPreviewViewMode('modal')"
                    class="px-2 py-1 rounded-md text-[11px] font-semibold transition cursor-pointer"
                    :class="previewViewMode === 'modal' ? 'bg-white text-blue-950 shadow-2xs' : 'text-blue-100 hover:text-white'"
                    title="Centered Dialog View"
                  >
                    <i class="fa-regular fa-window-maximize"></i>
                  </button>
                  <button
                    type="button"
                    @click="setPreviewViewMode('drawer')"
                    class="px-2 py-1 rounded-md text-[11px] font-semibold transition cursor-pointer"
                    :class="previewViewMode === 'drawer' ? 'bg-white text-blue-950 shadow-2xs' : 'text-blue-100 hover:text-white'"
                    title="Right Side Drawer View"
                  >
                    <i class="fa-solid fa-table-columns"></i>
                  </button>
                </div>

                <!-- Close Button -->
                <button
                  type="button"
                  @click="closeEventPreview"
                  class="w-8 h-8 rounded-lg flex items-center justify-center text-blue-200 hover:text-white hover:bg-white/15 transition cursor-pointer"
                  aria-label="Close"
                >
                  <i class="fa-solid fa-xmark text-base"></i>
                </button>
              </div>
            </div>
          </div>

          <!-- Progress Stepper Sub-Bar (Matches ModalTicketDetails) -->
          <div class="px-5 py-2.5 bg-slate-50 border-b border-slate-200 shrink-0">
            <TicketProgressStepper :status="activePreviewEvent.ticket_status" compact />
          </div>

          <!-- Modal Body (GPU-composited & overscroll-contained for 60fps smooth scrolling) -->
          <div class="p-5 overflow-y-auto overscroll-contain transform-gpu space-y-4 text-xs flex-1">
            <!-- DOUBLE-BOOKING CONFLICT ALERT CARD (Shown when Venue or Staff overlaps) -->
            <div
              v-if="hasEventConflict(activePreviewEvent)"
              class="p-4 rounded-2xl border border-orange-300 bg-gradient-to-br from-orange-50 via-amber-50/50 to-white space-y-2.5"
            >
              <div class="flex items-center justify-between gap-2">
                <span class="text-xs font-extrabold text-orange-950 flex items-center gap-1.5">
                  <i class="fa-solid fa-triangle-exclamation text-orange-600"></i>
                  Schedule Conflict Detected ({{ getConflictSummaryLabel(activePreviewEvent) }})
                </span>
              </div>

              <!-- Venue Conflicts List -->
              <div v-if="getEventVenueConflicts(activePreviewEvent).length > 0" class="space-y-1.5">
                <div class="text-[11px] font-bold text-orange-900">
                  <i class="fa-solid fa-location-dot mr-1 text-rose-600"></i>
                  Same Venue ({{ activePreviewEvent.event_venue }}) on overlapping dates:
                </div>
                <div class="flex flex-wrap gap-1.5">
                  <button
                    v-for="vc in getEventVenueConflicts(activePreviewEvent)"
                    :key="`vc-${vc.reference_no}`"
                    type="button"
                    @click="openEventByReference(vc.reference_no)"
                    class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-xl bg-white border border-orange-300 text-[11px] font-bold text-orange-950 hover:bg-blue-900 hover:text-white hover:border-blue-900 transition cursor-pointer shadow-2xs"
                  >
                    <span>#{{ vc.reference_no }}</span>
                    <span class="opacity-60">•</span>
                    <span class="truncate max-w-[140px]">{{ vc.client_name }}</span>
                    <span class="text-[10px] opacity-80">({{ formatDateDisplay(vc.start_date) }})</span>
                  </button>
                </div>
              </div>

              <!-- Staff Conflicts List -->
              <div v-if="getEventStaffConflicts(activePreviewEvent).length > 0" class="space-y-1.5">
                <div class="text-[11px] font-bold text-orange-900">
                  <i class="fa-solid fa-user-clock mr-1 text-amber-700"></i>
                  Same Technician assigned on overlapping dates:
                </div>
                <div class="flex flex-wrap gap-1.5">
                  <button
                    v-for="sc in getEventStaffConflicts(activePreviewEvent)"
                    :key="`sc-${sc.reference_no}`"
                    type="button"
                    @click="openEventByReference(sc.reference_no)"
                    class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-xl bg-white border border-amber-300 text-[11px] font-bold text-amber-950 hover:bg-blue-900 hover:text-white hover:border-blue-900 transition cursor-pointer shadow-2xs"
                  >
                    <span>#{{ sc.reference_no }}</span>
                    <span class="opacity-60">•</span>
                    <span class="truncate max-w-[130px]">{{ sc.staff }}</span>
                    <span class="text-[10px] opacity-80">@ {{ sc.venue }}</span>
                  </button>
                </div>
              </div>
            </div>

            <!-- INLINE QUICK-ACTION 1: TECHNICIAN DISPATCH & STATUS -->
            <div
              class="relative z-30 p-4 rounded-2xl border space-y-3"
              :class="isEventUnassigned(activePreviewEvent)
                ? 'bg-rose-50/60 border-rose-200'
                : 'bg-slate-50/90 border-slate-200'"
            >
              <div class="flex items-center justify-between gap-2 flex-wrap">
                <span class="text-[11px] font-extrabold uppercase tracking-wider flex items-center gap-1.5" :class="isEventUnassigned(activePreviewEvent) ? 'text-rose-800' : 'text-slate-700'">
                  <i class="fa-solid fa-bolt text-amber-500"></i>
                  Quick Dispatch &amp; Status Controls
                </span>

                <span v-if="activePreviewEvent.ticket_attended_by" class="text-[11px] text-slate-500">
                  Attended by: <strong class="text-slate-800">{{ activePreviewEvent.ticket_attended_by }}</strong>
                </span>
              </div>

              <!-- Quick Assign / Reassign Row with Custom Staff Popover -->
              <div class="flex flex-col sm:flex-row items-stretch sm:items-center gap-2">
                <div v-click-outside="() => (showQuickAssignMenu = false)" class="relative flex-1">
                  <button
                    type="button"
                    :disabled="isQuickSaving"
                    @click.stop="() => { showQuickAssignMenu = !showQuickAssignMenu; if (showQuickAssignMenu) quickAssignSearchText = '' }"
                    class="w-full py-2 px-3 text-xs rounded-xl border bg-white text-slate-900 hover:border-blue-500 focus:ring-2 focus:ring-blue-600/20 focus:border-blue-600 flex items-center justify-between gap-2 transition cursor-pointer"
                    :class="showQuickAssignMenu ? 'border-blue-600 ring-2 ring-blue-600/15' : 'border-slate-300'"
                  >
                    <div class="flex items-center gap-2 min-w-0">
                      <span
                        v-if="quickAssignStaff"
                        class="w-5 h-5 rounded-full bg-blue-900 text-white flex items-center justify-center text-[9px] font-bold shrink-0"
                      >
                        {{ getInitials(quickAssignStaff) }}
                      </span>
                      <i v-else class="fa-solid fa-user-plus text-slate-400 text-xs"></i>
                      <span class="truncate" :class="quickAssignStaff ? 'font-bold text-slate-900' : 'text-slate-500'">
                        {{ quickAssignStaff || 'Select IT Specialist to Assign...' }}
                      </span>
                      <span
                        v-if="quickAssignStaff && getStaffConflictsForPreviewEvent(quickAssignStaff).length > 0"
                        class="px-1.5 py-0.2 rounded bg-orange-100 text-orange-800 text-[10px] font-extrabold shrink-0"
                      >
                        ⚠️ Double-Booked
                      </span>
                    </div>
                    <i
                      class="fa-solid fa-chevron-down text-[10px] transition-transform"
                      :class="showQuickAssignMenu ? 'rotate-180 text-blue-700' : 'text-slate-400'"
                    ></i>
                  </button>

                  <!-- Staff Dropdown List -->
                  <div
                    v-if="showQuickAssignMenu"
                    @click.stop
                    class="absolute left-0 right-0 top-full mt-1.5 rounded-2xl bg-white border border-slate-200 shadow-2xl z-50 overflow-hidden"
                  >
                    <!-- Quick Filter Inside Staff Menu -->
                    <div class="p-2 border-b border-slate-100 bg-slate-50/70">
                      <div class="relative">
                        <i class="fa-solid fa-magnifying-glass absolute left-2.5 top-1/2 -translate-y-1/2 text-[10px] text-slate-400"></i>
                        <input
                          v-model="quickAssignSearchText"
                          type="text"
                          placeholder="Search IT specialist..."
                          class="w-full pl-7 pr-2.5 py-1.5 text-xs rounded-lg border border-slate-200 bg-white focus:ring-2 focus:ring-blue-500/20 focus:border-blue-600"
                        />
                      </div>
                    </div>

                    <div class="max-h-52 overflow-y-auto overscroll-contain py-1 divide-y divide-slate-50">
                      <button
                        v-for="s in filteredQuickAssignStaff"
                        :key="s"
                        type="button"
                        @click.stop="() => { quickAssignStaff = s; showQuickAssignMenu = false }"
                        class="w-full px-3 py-2 text-left hover:bg-blue-50/80 flex items-center justify-between gap-2 transition cursor-pointer"
                        :class="quickAssignStaff === s ? 'bg-blue-50 font-bold text-blue-900' : 'text-slate-800'"
                      >
                        <div class="flex items-center gap-2.5 min-w-0">
                          <span class="w-6 h-6 rounded-full bg-blue-900 text-white flex items-center justify-center text-[10px] font-bold shrink-0">
                            {{ getInitials(s) }}
                          </span>
                          <span class="truncate text-xs">{{ s }}</span>
                        </div>
                        <div class="flex items-center gap-1.5 shrink-0">
                          <!-- Proactive Double-Booking Warning Badge -->
                          <span
                            v-if="getStaffConflictsForPreviewEvent(s).length > 0"
                            class="px-1.5 py-0.5 rounded-md bg-orange-100 text-orange-800 border border-orange-300 text-[10px] font-extrabold"
                            :title="`Already booked on overlapping dates (#${getStaffConflictsForPreviewEvent(s)[0]?.client_referenceNo})`"
                          >
                            ⚠️ Busy (#{{ getStaffConflictsForPreviewEvent(s)[0]?.client_referenceNo }})
                          </span>
                          <span class="px-1.5 py-0.5 rounded-md bg-slate-100 text-[10px] font-semibold text-slate-500">
                            {{ getStaffEventCount(s) }} event{{ getStaffEventCount(s) === 1 ? '' : 's' }}
                          </span>
                          <i v-if="quickAssignStaff === s" class="fa-solid fa-check text-blue-700 text-xs"></i>
                        </div>
                      </button>

                      <div
                        v-if="filteredQuickAssignStaff.length === 0"
                        class="px-3 py-3 text-center text-xs text-slate-400"
                      >
                        No matching IT specialist found.
                      </div>
                    </div>
                  </div>
                </div>

                <button
                  type="button"
                  :disabled="isQuickSaving || !quickAssignStaff"
                  @click="quickAssignTechnician"
                  class="px-4 py-2 rounded-xl text-xs font-bold bg-blue-900 text-white hover:bg-blue-800 disabled:opacity-50 transition shadow-xs cursor-pointer shrink-0 flex items-center justify-center gap-1.5"
                >
                  <i class="fa-solid fa-user-check text-[11px]"></i>
                  <span>{{ activePreviewEvent.ticket_assigned_staff ? 'Reassign Staff' : 'Assign Staff' }}</span>
                </button>
              </div>

              <!-- Quick Status Transition Buttons -->
              <div
                v-if="!['completed', 'cancelled', 'closed'].includes((activePreviewEvent.ticket_status || '').toLowerCase())"
                class="flex items-center gap-2 flex-wrap pt-2 border-t border-slate-200/80"
              >
                <button
                  v-if="['new', 'pending'].includes((activePreviewEvent.ticket_status || '').toLowerCase()) || !activePreviewEvent.ticket_attended_by"
                  type="button"
                  :disabled="isQuickSaving"
                  @click="quickAttendEvent"
                  class="px-3.5 py-1.5 rounded-xl text-xs font-bold bg-emerald-600 text-white hover:bg-emerald-700 disabled:opacity-50 transition cursor-pointer flex items-center gap-1.5 shadow-2xs"
                >
                  <i class="fa-solid fa-play text-[10px]"></i>
                  <span>Attend / Start Event (In Progress)</span>
                </button>

                <button
                  v-if="(activePreviewEvent.ticket_status || '').toLowerCase() === 'in progress'"
                  type="button"
                  :disabled="isQuickSaving"
                  @click="showQuickCompleteForm = !showQuickCompleteForm"
                  class="px-3.5 py-1.5 rounded-xl text-xs font-bold bg-purple-600 text-white hover:bg-purple-700 disabled:opacity-50 transition cursor-pointer flex items-center gap-1.5 shadow-2xs"
                >
                  <i class="fa-solid fa-circle-check text-[11px]"></i>
                  <span>{{ showQuickCompleteForm ? 'Hide Complete Form' : 'Quick Complete Event' }}</span>
                </button>
              </div>

              <!-- Collapsible Quick Complete Form -->
              <div
                v-if="showQuickCompleteForm"
                class="p-3.5 rounded-xl bg-purple-50/70 border border-purple-200 space-y-3"
              >
                <div class="font-bold text-purple-950 text-xs flex items-center justify-between">
                  <span class="flex items-center gap-1.5">
                    <i class="fa-solid fa-circle-check text-purple-600"></i>
                    Complete Event Ticket
                  </span>
                  <span class="text-[10px] font-medium text-purple-700">Sends completion notification</span>
                </div>

                <div>
                  <label class="block text-[11px] font-bold text-slate-700 mb-1">Event Support Type</label>
                  <div class="relative">
                    <select
                      v-model="quickCompleteForm.ticket_support_type"
                      class="w-full py-1.5 pl-3 pr-8 text-xs rounded-xl border border-slate-300 bg-white text-slate-900 focus:ring-2 focus:ring-purple-500/20 focus:border-purple-600"
                    >
                      <option value="Audio & Visual Setup">Audio &amp; Visual Setup</option>
                      <option value="Livestream Setup">Livestream Setup</option>
                      <option value="Technical Floor Support">Technical Floor Support</option>
                      <option value="Others">Others</option>
                    </select>
                  </div>
                </div>

                <div>
                  <label class="block text-[11px] font-bold text-slate-700 mb-1">Actions Taken *</label>
                  <textarea
                    v-model="quickCompleteForm.ticket_actions_taken"
                    rows="2"
                    placeholder="e.g. Set up sound system, LED wall, and assisted throughout the event..."
                    class="w-full p-2.5 text-xs rounded-xl border border-slate-300 bg-white text-slate-900 focus:ring-2 focus:ring-purple-500/20 focus:border-purple-600"
                  ></textarea>
                </div>

                <div class="flex items-center justify-end gap-2">
                  <button
                    type="button"
                    @click="showQuickCompleteForm = false"
                    class="px-3 py-1.5 rounded-xl text-xs font-semibold text-slate-600 hover:bg-slate-200/60 cursor-pointer"
                  >
                    Cancel
                  </button>
                  <button
                    type="button"
                    :disabled="isQuickSaving"
                    @click="submitQuickComplete"
                    class="px-3.5 py-1.5 rounded-xl text-xs font-bold bg-purple-600 text-white hover:bg-purple-700 disabled:opacity-50 cursor-pointer shadow-xs"
                  >
                    Confirm Completed
                  </button>
                </div>
              </div>
            </div>

            <!-- Event Duration Highlight Card + Inline Edit Toggle -->
            <div class="p-4 rounded-2xl bg-blue-50/60 border border-blue-200/90 space-y-3">
              <div class="flex items-center justify-between gap-2 flex-wrap">
                <span class="text-xs font-extrabold text-blue-950 uppercase tracking-wider flex items-center gap-1.5">
                  <i class="fa-solid fa-calendar-check text-blue-700"></i>
                  Event Duration &amp; Schedule
                </span>
                <div class="flex items-center gap-2">
                  <span class="px-2.5 py-0.5 rounded-full text-xs font-black bg-blue-900 text-white">
                    {{ activePreviewEvent.duration_days }} Day{{ activePreviewEvent.duration_days > 1 ? 's' : '' }} Total
                  </span>
                  <button
                    type="button"
                    @click="isEditingSchedule = !isEditingSchedule"
                    class="px-2.5 py-1 rounded-xl text-[11px] font-bold border border-blue-300 bg-white text-blue-900 hover:bg-blue-900 hover:text-white transition cursor-pointer"
                  >
                    <i class="fa-solid fa-pen-to-square mr-1"></i>
                    {{ isEditingSchedule ? 'Cancel Edit' : 'Edit Schedule' }}
                  </button>
                </div>
              </div>

              <!-- Inline Schedule & Venue Editor Form -->
              <div v-if="isEditingSchedule" class="pt-2.5 border-t border-blue-200/80 space-y-3">
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-2.5">
                  <div>
                    <label class="block text-[11px] font-bold text-slate-700 mb-1">Start Date *</label>
                    <input
                      v-model="quickScheduleForm.event_start_date"
                      type="date"
                      class="w-full py-1.5 px-2.5 text-xs rounded-xl border border-slate-300 bg-white text-slate-900 focus:ring-2 focus:ring-blue-600/20 focus:border-blue-600"
                    />
                  </div>
                  <div>
                    <label class="block text-[11px] font-bold text-slate-700 mb-1">End Date</label>
                    <input
                      v-model="quickScheduleForm.event_end_date"
                      type="date"
                      class="w-full py-1.5 px-2.5 text-xs rounded-xl border border-slate-300 bg-white text-slate-900 focus:ring-2 focus:ring-blue-600/20 focus:border-blue-600"
                    />
                  </div>
                </div>

                <div class="space-y-2">
                  <label class="block text-[11px] font-bold text-slate-700">Event Venue *</label>
                  <select
                    v-model="quickScheduleVenueOption"
                    @change="selectQuickScheduleVenueOption(quickScheduleVenueOption)"
                    class="w-full py-1.5 px-2.5 text-xs rounded-xl border border-slate-300 bg-white text-slate-900 font-medium focus:ring-2 focus:ring-blue-600/20 focus:border-blue-600"
                  >
                    <option value="" disabled>-- Select Event Venue --</option>
                    <option v-for="v in QUICK_VENUES" :key="v" :value="v">{{ v }}</option>
                    <option value="Others">Others (Specify custom venue...)</option>
                  </select>

                  <!-- Conditional Custom Venue Input when 'Others' is selected -->
                  <div v-if="quickScheduleVenueOption === 'Others'" class="p-2.5 rounded-xl bg-white border border-blue-300 space-y-1">
                    <label class="block text-[10px] font-bold text-blue-900 uppercase tracking-wider">
                      Specify Custom / External Venue *
                    </label>
                    <input
                      v-model="quickScheduleCustomVenue"
                      type="text"
                      placeholder="Enter custom venue name (e.g. PICC, Hotel, Hall)..."
                      class="w-full py-1.5 px-2.5 text-xs rounded-lg border border-slate-300 bg-white text-slate-900 focus:ring-2 focus:ring-blue-600/20 focus:border-blue-600"
                    />
                  </div>

                  <!-- Quick-Select Venue Chips (including 'Others') -->
                  <div class="flex items-center gap-1.5 flex-wrap pt-0.5">
                    <span class="text-[10px] font-semibold text-slate-500">Quick pick:</span>
                    <button
                      v-for="v in QUICK_VENUES"
                      :key="v"
                      type="button"
                      @click="selectQuickScheduleVenueOption(v)"
                      class="px-2 py-0.5 rounded-lg text-[10px] font-semibold border transition cursor-pointer"
                      :class="quickScheduleVenueOption === v
                        ? 'bg-blue-900 text-white border-blue-900'
                        : 'bg-white text-slate-600 border-slate-200 hover:border-blue-300'"
                    >
                      {{ v }}
                    </button>
                    <button
                      type="button"
                      @click="selectQuickScheduleVenueOption('Others')"
                      class="px-2 py-0.5 rounded-lg text-[10px] font-semibold border transition cursor-pointer"
                      :class="quickScheduleVenueOption === 'Others'
                        ? 'bg-blue-900 text-white border-blue-900'
                        : 'bg-white text-slate-600 border-slate-200 hover:border-blue-300'"
                    >
                      Others
                    </button>
                  </div>

                  <!-- Live Venue Double-Booking Warning inside Editor -->
                  <div
                    v-if="editingScheduleVenueConflicts.length > 0"
                    class="p-2.5 rounded-xl bg-orange-50 border border-orange-300 text-orange-950 text-[11px] font-semibold flex items-start gap-2"
                  >
                    <i class="fa-solid fa-triangle-exclamation text-orange-600 mt-0.5"></i>
                    <div>
                      <strong>Venue Conflict Warning:</strong>
                      {{ quickScheduleForm.event_venue }} is already booked on these dates by
                      <span
                        v-for="(cEv, cIdx) in editingScheduleVenueConflicts"
                        :key="cEv.client_referenceNo"
                        class="font-extrabold"
                      >
                        #{{ cEv.client_referenceNo }} ({{ cEv.client_name }}){{ cIdx < editingScheduleVenueConflicts.length - 1 ? ', ' : '' }}
                      </span>.
                    </div>
                  </div>
                </div>

                <div>
                  <label class="block text-[11px] font-bold text-slate-700 mb-1">Event Remarks</label>
                  <textarea
                    v-model="quickScheduleForm.event_remarks"
                    rows="2"
                    class="w-full p-2 text-xs rounded-xl border border-slate-300 bg-white text-slate-900 focus:ring-2 focus:ring-blue-600/20 focus:border-blue-600"
                  ></textarea>
                </div>

                <div>
                  <label class="block text-[11px] font-bold text-slate-700 mb-1">Other Support Requests</label>
                  <textarea
                    v-model="quickScheduleForm.other_request"
                    rows="2"
                    class="w-full p-2 text-xs rounded-xl border border-slate-300 bg-white text-slate-900 focus:ring-2 focus:ring-blue-600/20 focus:border-blue-600"
                  ></textarea>
                </div>

                <div class="flex items-center justify-end gap-2">
                  <button
                    type="button"
                    @click="isEditingSchedule = false"
                    class="px-3 py-1.5 rounded-xl text-xs font-semibold text-slate-600 hover:bg-slate-200/60 cursor-pointer"
                  >
                    Cancel
                  </button>
                  <button
                    type="button"
                    :disabled="isQuickSaving"
                    @click="saveQuickSchedule"
                    class="px-4 py-1.5 rounded-xl text-xs font-bold bg-blue-900 text-white hover:bg-blue-800 disabled:opacity-50 cursor-pointer shadow-xs"
                  >
                    <i class="fa-solid fa-floppy-disk mr-1"></i>
                    Save Schedule &amp; Venue
                  </button>
                </div>
              </div>

              <!-- Read-only Schedule Display -->
              <template v-else>
                <div class="grid grid-cols-2 gap-3 pt-1 text-slate-900">
                  <div class="bg-white/80 p-2.5 rounded-xl border border-blue-100">
                    <span class="text-[10px] font-bold uppercase tracking-wider text-slate-400 block">Start Date</span>
                    <span class="text-sm font-extrabold text-slate-900 mt-0.5 block">{{ formatDateDisplay(activePreviewEvent.event_start_date) }}</span>
                  </div>
                  <div class="bg-white/80 p-2.5 rounded-xl border border-blue-100">
                    <span class="text-[10px] font-bold uppercase tracking-wider text-slate-400 block">End Date</span>
                    <span class="text-sm font-extrabold text-slate-900 mt-0.5 block">{{ formatDateDisplay(activePreviewEvent.event_end_date) }}</span>
                  </div>
                </div>

                <!-- Temporal status badge -->
                <div v-if="activePreviewEvent.is_ongoing" class="mt-2 pt-2 border-t border-blue-200/60 flex items-center justify-between text-emerald-700 font-bold">
                  <span class="flex items-center gap-1.5">
                    <span class="w-2 h-2 rounded-full bg-emerald-500 animate-pulse"></span>
                    Currently in progress
                  </span>
                  <span>Day {{ activePreviewEvent.days_elapsed }} of {{ activePreviewEvent.duration_days }}</span>
                </div>
              </template>
            </div>

            <!-- 2-Column Structured Overview Grid (Venue & Staff Assignment) -->
            <div v-if="!isEditingSchedule" class="grid grid-cols-1 sm:grid-cols-2 gap-3">
              <!-- Venue Details -->
              <div class="p-3.5 rounded-2xl border border-slate-200 bg-white space-y-1">
                <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider block">
                  Event Venue / Location
                </span>
                <div class="text-sm font-bold text-slate-900 flex items-center gap-2 pt-0.5">
                  <span class="w-6 h-6 rounded-lg bg-rose-50 text-rose-600 flex items-center justify-center shrink-0 text-xs">
                    <i class="fa-solid fa-location-dot"></i>
                  </span>
                  <span class="truncate">{{ activePreviewEvent.event_venue || 'Venue not specified' }}</span>
                </div>
              </div>

              <!-- Staff Assignment Details Summary -->
              <div class="p-3.5 rounded-2xl border border-slate-200 bg-white grid grid-cols-2 gap-2">
                <div>
                  <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider block">
                    Assigned Staff
                  </span>
                  <span class="text-xs font-bold mt-1 block truncate" :class="activePreviewEvent.ticket_assigned_staff ? 'text-slate-900' : 'text-rose-600'">
                    {{ activePreviewEvent.ticket_assigned_staff || '⚠️ Unassigned' }}
                  </span>
                </div>
                <div>
                  <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider block">
                    Attended By
                  </span>
                  <span class="text-xs font-semibold text-slate-700 mt-1 block truncate">
                    {{ activePreviewEvent.ticket_attended_by || 'Not attended yet' }}
                  </span>
                </div>
              </div>
            </div>

            <!-- Remarks & Other Requests (shown when not inline-editing) -->
            <div v-if="!isEditingSchedule" class="p-4 rounded-2xl border border-slate-200 bg-white space-y-3">
              <div>
                <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider block">
                  Event Remarks / Setup Requirements
                </span>
                <p class="text-xs text-slate-700 mt-1 whitespace-pre-wrap leading-relaxed">
                  {{ activePreviewEvent.event_remarks || activePreviewEvent.ticket_remarks || 'No event remarks provided.' }}
                </p>
              </div>

              <div v-if="activePreviewEvent.other_request" class="pt-2.5 border-t border-slate-100">
                <span class="text-[10px] font-bold text-amber-700 uppercase tracking-wider block">
                  Other Support Requests
                </span>
                <p class="text-xs text-slate-800 mt-1 font-medium">
                  {{ activePreviewEvent.other_request }}
                </p>
              </div>
            </div>
          </div>

          <!-- Modal Footer: Actions -->
          <div class="px-5 py-3.5 border-t border-slate-200 bg-slate-50 flex items-center justify-between gap-3 shrink-0">
            <button
              type="button"
              @click="closeEventPreview"
              class="px-4 py-2 text-xs font-bold rounded-xl border border-slate-300 bg-white text-slate-700 hover:bg-slate-100 transition cursor-pointer"
            >
              Close
            </button>

            <button
              type="button"
              @click="openFullTicketDetails(activePreviewEvent)"
              class="px-4 py-2 text-xs font-bold rounded-xl bg-blue-900 text-white hover:bg-blue-800 transition shadow-sm flex items-center gap-2 cursor-pointer"
            >
              <i class="fa-solid fa-up-right-from-square text-xs text-blue-200"></i>
              <span>Open Full Ticket &amp; Messages</span>
            </button>
          </div>
        </div>
      </div>

      <!-- ── 8. CLICK-TO-SCHEDULE / QUICK CREATE EVENT TICKET MODAL ───────────── -->
      <div
        v-if="showCreateEventModal"
        class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-950/60"
        @click.self="closeCreateEventModal"
      >
        <ModalAction_CreateTicket
          :initial-mode="createModalMode"
          initial-ticket-type="Events"
          :initial-start-date="createEventDate"
          :initial-end-date="createEventDate"
          :initial-venue="createEventVenue"
          @created="onEventTicketCreated"
          @close="closeCreateEventModal"
        />
      </div>

      <!-- ── 9. FULL TICKET DETAILS MODAL / DRAWER (REUSED) ────────────────────── -->
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
