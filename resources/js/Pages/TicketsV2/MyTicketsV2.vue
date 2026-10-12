<script setup>
import { ref, watch, computed, onMounted, onUnmounted } from 'vue'
import { Head, router, usePage } from '@inertiajs/vue3'
import { useToast } from 'vue-toastification'
import axios from 'axios'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import CardNewTickets from '@/Pages/TicketsV2/MyTicketComponents/NewTickets.vue'
import CardRecentTickets from '@/Pages/TicketsV2/MyTicketComponents/InProgressTickets.vue'
import CardNoRatingTickets from '@/Pages/TicketsV2/MyTicketComponents/NoRatingTickets.vue'
import CardNoCSMTickets from '@/Pages/TicketsV2/MyTicketComponents/NoCSMTickets.vue'
import CardAllMyTickets from '@/Pages/TicketsV2/MyTicketComponents/AllMyTickets.vue'
import { getTicketSlaStatus } from '@/Utils/slaHelper'
import {
  playNewTicketChime,
  isSoundEnabled,
  setSoundEnabled,
} from '@/Utils/soundAlert'

const props = defineProps({
  ticketStats: {
    type: Object,
    default: () => ({}),
  },
  filters: {
    type: Object,
    default: () => ({}),
  },
  tickets: {
    type: Object,
    required: true,
  },
  pendingTickets: {
    type: Array,
    default: () => [],
  },
  myNewTickets: {
    type: Array,
    default: () => [],
  },
  myInProgTickets: {
    type: Array,
    default: () => [],
  },
  noCsmTickets: {
    type: Array,
    default: () => [],
  },
  noRatingTickets: {
    type: Array,
    default: () => [],
  },
})

const page = usePage()
const toast = useToast()

const localMyNew = ref(props.myNewTickets ? [...props.myNewTickets] : [])
const localMyInProg = ref(props.myInProgTickets ? [...props.myInProgTickets] : [])

// ── Real-Time Sound & Live Heartbeat Alert ──────────────────────────────────
const isSoundActive = ref(isSoundEnabled())

function toggleSound() {
  const next = !isSoundActive.value
  setSoundEnabled(next)
  isSoundActive.value = next
  if (next) {
    playNewTicketChime()
    toast.success('Audio notification chime enabled!')
  } else {
    toast.info('Audio notifications muted.')
  }
}

// Track known pending ticket reference numbers to detect brand-new assignments
const knownPendingRefNos = ref(new Set((props.myNewTickets || []).map(t => t.client_referenceNo).filter(Boolean)))

let heartbeatTimer = null
let titleFlashTimer = null
const originalTitle = typeof document !== 'undefined' ? document.title : 'My Tickets Workspace'

function flashDocumentTitle(count) {
  if (typeof document === 'undefined') return
  if (titleFlashTimer) clearInterval(titleFlashTimer)
  let isFlashing = false
  titleFlashTimer = setInterval(() => {
    document.title = isFlashing ? `🔔 (${count}) NEW ASSIGNED TICKET!` : originalTitle
    isFlashing = !isFlashing
  }, 1000)
}

function resetDocumentTitle() {
  if (titleFlashTimer) {
    clearInterval(titleFlashTimer)
    titleFlashTimer = null
  }
  if (typeof document !== 'undefined') {
    document.title = originalTitle
  }
}

async function checkAssignedUpdates() {
  try {
    const { data } = await axios.get('/admin/my-tickets/check-updates')
    if (data && Array.isArray(data.pending_tickets)) {
      const incoming = data.pending_tickets
      const newlyAssigned = incoming.filter(
        t => t.client_referenceNo && !knownPendingRefNos.value.has(t.client_referenceNo)
      )

      if (newlyAssigned.length > 0) {
        // 1) Play high-fidelity dual-tone chime
        playNewTicketChime()

        // 2) Toast alert & document title badge
        newlyAssigned.forEach(t => {
          const refCode = t.client_referenceNo || `#${t.ticket_id}`
          const clientName = t.client_name || 'Client'
          toast.info(`🔔 Newly Assigned Ticket: ${refCode} from ${clientName}!`, { timeout: 7000 })
          knownPendingRefNos.value.add(t.client_referenceNo)
        })
        flashDocumentTitle(newlyAssigned.length)

        // 3) Reload partial Inertia props to sync UI
        router.reload({
          preserveScroll: true,
          preserveState: true,
          only: ['myNewTickets', 'myInProgTickets', 'ticketStats', 'tickets'],
          onSuccess: () => {
            allRef.value?.refresh()
          },
        })
      }
    }
  } catch (err) {
    // Silent fail on minor network hiccups
  }
}

// Keep local copies updated after navigation/reloads
watch(
  () => props.myNewTickets,
  (val) => {
    localMyNew.value = val ? [...val] : []
    if (Array.isArray(val)) {
      val.forEach(t => {
        if (t.client_referenceNo) knownPendingRefNos.value.add(t.client_referenceNo)
      })
    }
  }
)
watch(
  () => props.myInProgTickets,
  (val) => {
    localMyInProg.value = val ? [...val] : []
  }
)

const currentUserName = computed(() => page.props?.auth?.user?.name || 'Technician')

const formattedToday = computed(() => {
  return new Intl.DateTimeFormat('en-US', {
    weekday: 'short',
    month: 'short',
    day: 'numeric',
    year: 'numeric',
  }).format(new Date())
})

// ── Metric Counters ──────────────────────────────────────────────────────────
const newTicketsCount = computed(() => props.myNewTickets?.length ?? props.ticketStats?.pending ?? 0)
const inProgTicketsCount = computed(() => localMyInProg.value?.length ?? props.ticketStats?.in_progress ?? 0)
const pendingRatingCount = computed(() => props.noRatingTickets?.length ?? 0)
const awaitingCsmCount = computed(() => props.noCsmTickets?.length ?? 0)
const resolvedTicketsCount = computed(() => (props.ticketStats?.completed ?? 0) + (props.ticketStats?.closed ?? 0))
const totalAssigned = computed(() => props.ticketStats?.total ?? 0)

const activeQueueTotal = computed(() => {
  return newTicketsCount.value + inProgTicketsCount.value + pendingRatingCount.value + awaitingCsmCount.value
})

const resolutionRate = computed(() => {
  const total = totalAssigned.value
  if (!total) return 0
  return Math.round((resolvedTicketsCount.value / total) * 100)
})

// ── SLA & Deadline Urgency Metrics ───────────────────────────────────────────
const slaBreachedCount = computed(() => {
  const activeTickets = [...(props.myNewTickets || []), ...(localMyInProg.value || [])]
  return activeTickets.filter((t) => getTicketSlaStatus(t).isBreached).length
})

const slaWarningCount = computed(() => {
  const activeTickets = [...(props.myNewTickets || []), ...(localMyInProg.value || [])]
  return activeTickets.filter((t) => getTicketSlaStatus(t).isWarning).length
})

// ── Workload Triage Tray Active Tab ──────────────────────────────────────────
const activeTrayTab = ref('in_progress')

onMounted(() => {
  if (newTicketsCount.value > 0) {
    activeTrayTab.value = 'new'
  } else if (inProgTicketsCount.value > 0) {
    activeTrayTab.value = 'in_progress'
  } else if (pendingRatingCount.value > 0) {
    activeTrayTab.value = 'no_rating'
  } else if (awaitingCsmCount.value > 0) {
    activeTrayTab.value = 'no_csm'
  } else {
    activeTrayTab.value = 'in_progress'
  }

  // Start background heartbeat check (every 8 seconds)
  heartbeatTimer = setInterval(checkAssignedUpdates, 8000)
  if (typeof window !== 'undefined') {
    window.addEventListener('focus', resetDocumentTitle)
  }
})

onUnmounted(() => {
  if (heartbeatTimer) {
    clearInterval(heartbeatTimer)
    heartbeatTimer = null
  }
  if (titleFlashTimer) {
    clearInterval(titleFlashTimer)
    titleFlashTimer = null
  }
  if (typeof window !== 'undefined') {
    window.removeEventListener('focus', resetDocumentTitle)
  }
})

function switchTrayTab(tabKey) {
  activeTrayTab.value = tabKey
  if (typeof window !== 'undefined' && window.innerWidth < 1280) {
    scrollToTray()
  }
}

function scrollToTray() {
  if (typeof document !== 'undefined') {
    const el = document.getElementById('workload-tray')
    if (el) {
      el.scrollIntoView({ behavior: 'smooth', block: 'start' })
    }
  }
}

// ── Two-Way KPI Linking (Syncs Tray & Master Table Filter) ───────────────────
const allRef = ref(null)

function handleKpiCardClick(cardKey) {
  const currentStatus = allRef.value?.statusFilter
  if (cardKey === 'new') {
    switchTrayTab('new')
    allRef.value?.setStatusFilter(currentStatus === 'Pending' ? '' : 'Pending')
  } else if (cardKey === 'in_progress') {
    switchTrayTab('in_progress')
    allRef.value?.setStatusFilter(currentStatus === 'In Progress' ? '' : 'In Progress')
  } else if (cardKey === 'no_rating') {
    switchTrayTab('no_rating')
    allRef.value?.setStatusFilter(currentStatus === 'Completed' ? '' : 'Completed')
  } else if (cardKey === 'no_csm') {
    switchTrayTab('no_csm')
    allRef.value?.setStatusFilter(currentStatus === 'Completed' ? '' : 'Completed')
  } else if (cardKey === 'resolved') {
    allRef.value?.setStatusFilter(currentStatus === 'Completed' ? '' : 'Completed')
  }
}

// ── Export Accomplishment Report URL ─────────────────────────────────────────
const exportReportUrl = computed(() => {
  const params = new URLSearchParams()
  if (props.filters?.status) params.set('status', props.filters.status)
  if (props.filters?.division) params.set('division', props.filters.division)
  if (props.filters?.preset) params.set('preset', props.filters.preset)
  if (props.filters?.search) params.set('search', props.filters.search)
  const qs = params.toString()
  return qs ? `/admin/my-tickets/export?${qs}` : '/admin/my-tickets/export'
})

// ── Refresh Workload ─────────────────────────────────────────────────────────
const isRefreshing = ref(false)

function refreshAll() {
  isRefreshing.value = true
  router.reload({
    preserveScroll: true,
    only: ['myNewTickets', 'myInProgTickets', 'ticketStats', 'noCsmTickets', 'noRatingTickets', 'tickets'],
    onFinish: () => {
      isRefreshing.value = false
      allRef.value?.refresh()
    },
  })
}

function handleChanged() {
  router.reload({
    preserveScroll: true,
    only: ['myNewTickets', 'myInProgTickets', 'ticketStats', 'noCsmTickets', 'noRatingTickets'],
    onSuccess: () => {
      allRef.value?.refresh()
    },
  })
}
</script>

<template>
  <Head title="My Tickets Workspace" />

  <AuthenticatedLayout :ticket-stats="ticketStats" :filters="filters">
    <!-- ── TECHNICIAN COMMAND HEADER & KPI STATS BAR ──────────────────────── -->
    <div class="mb-6 bg-white rounded-2xl border border-slate-200/90 shadow-sm p-5 md:p-6 transition-all">
      <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
        <!-- Title, Greeting Info & SLA Health Badges -->
        <div class="space-y-1.5">
          <div class="flex items-center gap-2 flex-wrap">

            <span class="text-xs text-slate-500 font-medium">
              <i class="fa-regular fa-calendar mr-1 text-slate-400"></i>
              {{ formattedToday }}
            </span>
            <span class="text-xs text-slate-300">•</span>

            <!-- Real-Time SLA Health Badge -->
            <span
              v-if="slaBreachedCount > 0"
              class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-xs font-bold bg-rose-50 text-rose-700 border border-rose-200 animate-pulse"
              title="You have tickets exceeding standard SLA turnaround"
            >
              <i class="fa-solid fa-triangle-exclamation text-rose-600"></i>
              {{ slaBreachedCount }} Overdue SLA
            </span>
            <span
              v-else-if="slaWarningCount > 0"
              class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-xs font-bold bg-amber-50 text-amber-800 border border-amber-200"
              title="You have active tickets approaching standard SLA limits"
            >
              <i class="fa-solid fa-clock text-amber-600"></i>
              {{ slaWarningCount }} Nearing SLA (24h)
            </span>
            <span
              v-else
              class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-xs font-bold bg-emerald-50 text-emerald-700 border border-emerald-200"
            >
              <i class="fa-solid fa-circle-check text-emerald-600"></i>
              SLA On Track
            </span>
          </div>

          <h1 class="text-xl md:text-2xl font-black text-slate-900 tracking-tight">
            Welcome back, <span class="text-blue-600">{{ currentUserName }}</span>
          </h1>

          <p class="text-xs md:text-sm text-slate-500 max-w-2xl">
            Triage newly assigned requests, monitor SLA turnaround times, and track feedback completions in real time.
          </p>
        </div>

        <!-- Quick Header Actions -->
        <div class="flex items-center gap-2 self-start md:self-center shrink-0 flex-wrap">

          <!-- Export Accomplishment Report CSV -->
          <a
            :href="exportReportUrl"
            class="inline-flex items-center gap-1.5 px-3.5 py-2 text-xs font-semibold rounded-xl border border-emerald-200 bg-emerald-50 hover:bg-emerald-100 text-emerald-800 shadow-2xs transition-all cursor-pointer"
            title="Download Accomplishment Report (CSV)"
          >
            <i class="fa-solid fa-file-arrow-down text-emerald-600"></i>
            <span class="hidden sm:inline">Export Report</span>
          </a>

          <!-- Refresh Queues -->
          <button
            type="button"
            @click="refreshAll"
            :disabled="isRefreshing"
            class="inline-flex items-center gap-2 px-3.5 py-2 text-xs font-semibold rounded-xl border border-slate-200 bg-white hover:bg-slate-50 text-slate-700 shadow-2xs hover:border-slate-300 transition-all disabled:opacity-60 cursor-pointer"
            title="Refresh active tickets & queues"
          >
            <i class="fa-solid fa-arrows-rotate text-blue-600" :class="{ 'animate-spin': isRefreshing }"></i>
            <span>{{ isRefreshing ? 'Refreshing...' : 'Refresh Queues' }}</span>
          </button>

          <!-- Jump to Tray button (on mobile/tablet) -->
          <button
            type="button"
            @click="scrollToTray"
            class="xl:hidden inline-flex items-center gap-1.5 px-3.5 py-2 text-xs font-semibold rounded-xl bg-blue-600 text-white hover:bg-blue-700 shadow-sm transition-all cursor-pointer"
          >
            <i class="fa-solid fa-inbox"></i>
            <span>Workload Tray</span>
            <span
              v-if="activeQueueTotal > 0"
              class="ml-1 px-1.5 py-0.2 text-[10px] font-bold bg-white text-blue-600 rounded-full"
            >
              {{ activeQueueTotal }}
            </span>
          </button>
        </div>
      </div>

      <!-- KPI Stats Bar (5 Interactive Cards Grid with 2-Way Filtering) -->
      <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-5 gap-3.5 mt-6 pt-6 border-t border-slate-100">
        <!-- 1. New Assigned Card -->
        <button
          type="button"
          @click="handleKpiCardClick('new')"
          class="flex flex-col justify-between p-3.5 rounded-xl border transition-all text-left group cursor-pointer"
          :class="activeTrayTab === 'new'
            ? 'bg-amber-50/70 border-amber-300 ring-2 ring-amber-500/40 shadow-xs'
            : 'bg-slate-50/50 border-slate-200/90 hover:bg-amber-50/30 hover:border-amber-200'"
          title="Filter table by Pending & switch tray to New"
        >
          <div class="flex items-center justify-between mb-2 w-full">
            <span class="text-[11px] font-bold uppercase tracking-wider text-slate-500 group-hover:text-amber-800 transition">
              New Assigned
            </span>
            <div
              class="w-8 h-8 rounded-lg flex items-center justify-center text-xs transition-colors"
              :class="activeTrayTab === 'new' ? 'bg-amber-500 text-white' : 'bg-amber-100 text-amber-700 group-hover:bg-amber-500 group-hover:text-white'"
            >
              <i class="fa-solid fa-bolt"></i>
            </div>
          </div>
          <div class="flex items-baseline gap-1.5">
            <span class="text-3xl font-black text-slate-900 leading-none">{{ newTicketsCount }}</span>
            <span v-if="newTicketsCount > 0" class="text-[10px] font-bold text-amber-600 bg-amber-100 px-1.5 py-0.2 rounded-full">
              Attention
            </span>
          </div>
          <p class="text-[11px] text-slate-500 mt-1">Awaiting attendance</p>
        </button>

        <!-- 2. In Progress Card -->
        <button
          type="button"
          @click="handleKpiCardClick('in_progress')"
          class="flex flex-col justify-between p-3.5 rounded-xl border transition-all text-left group cursor-pointer"
          :class="activeTrayTab === 'in_progress'
            ? 'bg-blue-50/70 border-blue-300 ring-2 ring-blue-600/40 shadow-xs'
            : 'bg-slate-50/50 border-slate-200/90 hover:bg-blue-50/30 hover:border-blue-200'"
          title="Filter table by In Progress & switch tray"
        >
          <div class="flex items-center justify-between mb-2 w-full">
            <span class="text-[11px] font-bold uppercase tracking-wider text-slate-500 group-hover:text-blue-800 transition">
              In Progress
            </span>
            <div
              class="w-8 h-8 rounded-lg flex items-center justify-center text-xs transition-colors"
              :class="activeTrayTab === 'in_progress' ? 'bg-blue-600 text-white' : 'bg-blue-100 text-blue-700 group-hover:bg-blue-600 group-hover:text-white'"
            >
              <i class="fa-solid fa-screwdriver-wrench"></i>
            </div>
          </div>
          <div class="flex items-baseline gap-1.5">
            <span class="text-3xl font-black text-slate-900 leading-none">{{ inProgTicketsCount }}</span>
            <span class="text-[10px] font-bold text-blue-600 bg-blue-100 px-1.5 py-0.2 rounded-full">
              Active
            </span>
          </div>
          <p class="text-[11px] text-slate-500 mt-1">Troubleshooting now</p>
        </button>

        <!-- 3. Pending Rating Card -->
        <button
          type="button"
          @click="handleKpiCardClick('no_rating')"
          class="flex flex-col justify-between p-3.5 rounded-xl border transition-all text-left group cursor-pointer"
          :class="activeTrayTab === 'no_rating'
            ? 'bg-purple-50/70 border-purple-300 ring-2 ring-purple-600/40 shadow-xs'
            : 'bg-slate-50/50 border-slate-200/90 hover:bg-purple-50/30 hover:border-purple-200'"
          title="Filter table & switch tray to No Rating"
        >
          <div class="flex items-center justify-between mb-2 w-full">
            <span class="text-[11px] font-bold uppercase tracking-wider text-slate-500 group-hover:text-purple-800 transition">
              Pending Rating
            </span>
            <div
              class="w-8 h-8 rounded-lg flex items-center justify-center text-xs transition-colors"
              :class="activeTrayTab === 'no_rating' ? 'bg-purple-600 text-white' : 'bg-purple-100 text-purple-700 group-hover:bg-purple-600 group-hover:text-white'"
            >
              <i class="fa-solid fa-star-half-stroke"></i>
            </div>
          </div>
          <div class="flex items-baseline gap-1.5">
            <span class="text-3xl font-black text-slate-900 leading-none">{{ pendingRatingCount }}</span>
            <span class="text-[10px] font-bold text-purple-600 bg-purple-100 px-1.5 py-0.2 rounded-full">
              Unrated
            </span>
          </div>
          <p class="text-[11px] text-slate-500 mt-1">Completed, no rating</p>
        </button>

        <!-- 4. Awaiting CSM Card -->
        <button
          type="button"
          @click="handleKpiCardClick('no_csm')"
          class="flex flex-col justify-between p-3.5 rounded-xl border transition-all text-left group cursor-pointer"
          :class="activeTrayTab === 'no_csm'
            ? 'bg-indigo-50/70 border-indigo-300 ring-2 ring-indigo-600/40 shadow-xs'
            : 'bg-slate-50/50 border-slate-200/90 hover:bg-indigo-50/30 hover:border-indigo-200'"
          title="Filter table & switch tray to No CSM"
        >
          <div class="flex items-center justify-between mb-2 w-full">
            <span class="text-[11px] font-bold uppercase tracking-wider text-slate-500 group-hover:text-indigo-800 transition">
              Awaiting CSM
            </span>
            <div
              class="w-8 h-8 rounded-lg flex items-center justify-center text-xs transition-colors"
              :class="activeTrayTab === 'no_csm' ? 'bg-indigo-600 text-white' : 'bg-indigo-100 text-indigo-700 group-hover:bg-indigo-600 group-hover:text-white'"
            >
              <i class="fa-solid fa-file-circle-exclamation"></i>
            </div>
          </div>
          <div class="flex items-baseline gap-1.5">
            <span class="text-3xl font-black text-slate-900 leading-none">{{ awaitingCsmCount }}</span>
            <span class="text-[10px] font-bold text-indigo-600 bg-indigo-100 px-1.5 py-0.2 rounded-full">
              Sync
            </span>
          </div>
          <p class="text-[11px] text-slate-500 mt-1">Pending CSM sheet</p>
        </button>

        <!-- 5. Resolved Total Card (Interactive Filter Button) -->
        <button
          type="button"
          @click="handleKpiCardClick('resolved')"
          class="flex flex-col justify-between p-3.5 rounded-xl border border-slate-200/90 bg-slate-50/50 text-left hover:bg-emerald-50/40 hover:border-emerald-300 transition-all cursor-pointer group"
          title="Filter master table to Completed tickets"
        >
          <div class="flex items-center justify-between mb-2 w-full">
            <span class="text-[11px] font-bold uppercase tracking-wider text-slate-500 group-hover:text-emerald-800 transition">
              Resolved Total
            </span>
            <div class="w-8 h-8 rounded-lg bg-emerald-100 text-emerald-700 group-hover:bg-emerald-600 group-hover:text-white flex items-center justify-center text-xs transition-colors">
              <i class="fa-solid fa-circle-check"></i>
            </div>
          </div>
          <div class="flex items-baseline gap-1.5">
            <span class="text-3xl font-black text-slate-900 leading-none">{{ resolvedTicketsCount }}</span>
            <span class="text-[10px] font-bold text-emerald-700 bg-emerald-100 px-1.5 py-0.2 rounded-full">
              {{ resolutionRate }}%
            </span>
          </div>
          <p class="text-[11px] text-slate-500 mt-1">{{ totalAssigned }} assigned total</p>
        </button>
      </div>
    </div>

    <!-- ── 2-COLUMN WORKSPACE: ALL TICKETS (LEFT) + WORKLOAD TRAY (RIGHT) ── -->
    <div class="grid grid-cols-1 xl:grid-cols-12 gap-6 items-start">
      <!-- Left Column: Searchable, Paginated All Assigned Tickets -->
      <div class="xl:col-span-7 2xl:col-span-8 min-w-0">
        <CardAllMyTickets
          ref="allRef"
          :tickets="tickets"
          :ticket-stats="ticketStats"
          :filters="filters"
          @attended="handleChanged"
          @completed="handleChanged"
        />
      </div>

      <!-- Right Column: Personal Workload Triage Tray (Tabbed Container) -->
      <div class="xl:col-span-5 2xl:col-span-4 min-w-0">
        <div
          id="workload-tray"
          class="bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden sticky top-4 transition-all"
        >
          <!-- Tray Top Bar with Header & Queue Counts -->
          <div class="p-4 bg-slate-50/80 border-b border-slate-200">
            <div class="flex items-center justify-between mb-3">
              <div class="flex items-center gap-2.5">
                <div class="w-8 h-8 rounded-xl bg-blue-600 text-white flex items-center justify-center text-xs shadow-xs">
                  <i class="fa-solid fa-inbox"></i>
                </div>
                <div>
                  <h2 class="text-sm font-bold text-slate-900 leading-tight">Workload Triage Tray</h2>
                  <p class="text-[11px] text-slate-500">Quick action on personal queues</p>
                </div>
              </div>

              <!-- Queue Items Badge -->
              <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-[11px] font-bold bg-white text-slate-700 border border-slate-200 shadow-2xs">
                <span
                  class="w-1.5 h-1.5 rounded-full"
                  :class="activeQueueTotal > 0 ? 'bg-amber-500 animate-pulse' : 'bg-emerald-500'"
                ></span>
                {{ activeQueueTotal }} queue item{{ activeQueueTotal === 1 ? '' : 's' }}
              </span>
            </div>

            <!-- Segmented Tab Switcher -->
            <div class="flex items-center p-1 bg-slate-200/70 rounded-xl gap-1 text-xs font-semibold overflow-x-auto">
              <!-- Tab 1: New -->
              <button
                type="button"
                @click="switchTrayTab('new')"
                class="flex-1 min-w-[70px] py-1.5 px-2 rounded-lg transition-all flex items-center justify-center gap-1.5 cursor-pointer whitespace-nowrap"
                :class="activeTrayTab === 'new'
                  ? 'bg-white text-slate-900 shadow-xs font-bold'
                  : 'text-slate-600 hover:text-slate-900 hover:bg-white/50'"
              >
                <i class="fa-solid fa-bolt text-amber-500 text-[11px]"></i>
                <span>New</span>
                <span
                  class="px-1.5 py-0.2 rounded-full text-[10px] font-bold leading-tight"
                  :class="activeTrayTab === 'new' ? 'bg-amber-100 text-amber-800' : 'bg-slate-300 text-slate-700'"
                >
                  {{ newTicketsCount }}
                </span>
              </button>

              <!-- Tab 2: In Progress -->
              <button
                type="button"
                @click="switchTrayTab('in_progress')"
                class="flex-1 min-w-[85px] py-1.5 px-2 rounded-lg transition-all flex items-center justify-center gap-1.5 cursor-pointer whitespace-nowrap"
                :class="activeTrayTab === 'in_progress'
                  ? 'bg-white text-slate-900 shadow-xs font-bold'
                  : 'text-slate-600 hover:text-slate-900 hover:bg-white/50'"
              >
                <i class="fa-solid fa-screwdriver-wrench text-blue-600 text-[11px]"></i>
                <span>In Prog</span>
                <span
                  class="px-1.5 py-0.2 rounded-full text-[10px] font-bold leading-tight"
                  :class="activeTrayTab === 'in_progress' ? 'bg-blue-100 text-blue-800' : 'bg-slate-300 text-slate-700'"
                >
                  {{ inProgTicketsCount }}
                </span>
              </button>

              <!-- Tab 3: No Rating -->
              <button
                type="button"
                @click="switchTrayTab('no_rating')"
                class="flex-1 min-w-[85px] py-1.5 px-2 rounded-lg transition-all flex items-center justify-center gap-1.5 cursor-pointer whitespace-nowrap"
                :class="activeTrayTab === 'no_rating'
                  ? 'bg-white text-slate-900 shadow-xs font-bold'
                  : 'text-slate-600 hover:text-slate-900 hover:bg-white/50'"
              >
                <i class="fa-solid fa-star-half-stroke text-purple-600 text-[11px]"></i>
                <span>No Rating</span>
                <span
                  class="px-1.5 py-0.2 rounded-full text-[10px] font-bold leading-tight"
                  :class="activeTrayTab === 'no_rating' ? 'bg-purple-100 text-purple-800' : 'bg-slate-300 text-slate-700'"
                >
                  {{ pendingRatingCount }}
                </span>
              </button>

              <!-- Tab 4: No CSM -->
              <button
                type="button"
                @click="switchTrayTab('no_csm')"
                class="flex-1 min-w-[75px] py-1.5 px-2 rounded-lg transition-all flex items-center justify-center gap-1.5 cursor-pointer whitespace-nowrap"
                :class="activeTrayTab === 'no_csm'
                  ? 'bg-white text-slate-900 shadow-xs font-bold'
                  : 'text-slate-600 hover:text-slate-900 hover:bg-white/50'"
              >
                <i class="fa-solid fa-file-circle-exclamation text-indigo-600 text-[11px]"></i>
                <span>No CSM</span>
                <span
                  class="px-1.5 py-0.2 rounded-full text-[10px] font-bold leading-tight"
                  :class="activeTrayTab === 'no_csm' ? 'bg-indigo-100 text-indigo-800' : 'bg-slate-300 text-slate-700'"
                >
                  {{ awaitingCsmCount }}
                </span>
              </button>
            </div>
          </div>

          <!-- Tray Tab Body Contents -->
          <div class="relative min-h-[300px]">
            <CardNewTickets
              v-show="activeTrayTab === 'new'"
              :tickets="myNewTickets"
              @attended="handleChanged"
            />
            <CardRecentTickets
              v-show="activeTrayTab === 'in_progress'"
              :tickets="localMyInProg"
              @completed="handleChanged"
            />
            <CardNoRatingTickets
              v-show="activeTrayTab === 'no_rating'"
              :pending-tickets="noRatingTickets"
            />
            <CardNoCSMTickets
              v-show="activeTrayTab === 'no_csm'"
              :tickets="noCsmTickets"
            />
          </div>
        </div>
      </div>
    </div>
  </AuthenticatedLayout>
</template>
