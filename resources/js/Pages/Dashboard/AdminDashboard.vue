<script setup>
import TicketStats from "@/Pages/TicketsV2/DashboardComponents/TicketStats.vue"
import TicketSlaPerformance from "@/Pages/TicketsV2/DashboardComponents/TicketSlaPerformance.vue"
import TicketDivisionBar from "@/Pages/TicketsV2/DashboardComponents/TicketDivisionBar.vue"
import TechnicalSupportRatings from "@/Pages/TicketsV2/DashboardComponents/TechnicalSupportRatings.vue"
import TechnicalSupportByNature from "@/Pages/TicketsV2/DashboardComponents/TechnicalSupportByNature.vue"
import TechnicalSupportRatingsDistribution from "@/Pages/TicketsV2/DashboardComponents/TechnicalSupportRatingsDistribution.vue"
import StaffWorkloadCard from "@/Pages/TicketsV2/DashboardComponents/StaffWorkloadCard.vue"
import RecentFeedbackCard from "@/Pages/TicketsV2/DashboardComponents/RecentFeedbackCard.vue"
import TechnicalSupportBySupportType from "@/Pages/TicketsV2/DashboardComponents/TechnicalSupportBySupportType.vue"
import Modal from '@/Components/Modal.vue'
import ModalTicketDetails from '@/Pages/TicketsV2/ModalComponents/ModalTicketDetails.vue'
import { useStaffList } from '@/Composables/useStaffList'
import { TICKET_TYPES, mapDivision } from '@/Support/ticketClassifications'
import { Link, router } from '@inertiajs/vue3'
import { ref, computed } from 'vue'
import { useToast } from 'vue-toastification'

const toast = useToast()
const { staffOptions: dynamicStaff } = useStaffList()

const props = defineProps({
  ticketStats: { type: Object, default: () => ({}) },
  slaStats: { type: Object, default: () => ({}) },
  trends: { type: Object, default: () => ({}) },
  staffWorkload: { type: Array, default: () => [] },
  recentFeedback: { type: Array, default: () => [] },
  divisionCounts: { type: Array, default: () => [] },
  ratingCounts: { type: Array, default: () => [] },
  ratingUnratedTotal: { type: Number, default: 0 },
  natureCounts: { type: Array, default: () => [] },
  supportTypeCounts: { type: Array, default: () => [] },
  adminQueue: { type: Object, default: () => ({ unassigned_tickets: [], overdue_tickets: [] }) },
  assignedStaffOptions: { type: Array, default: () => [] },
  filters: { type: Object, default: () => ({}) },
})

const activeTab = ref('operations') // 'operations' | 'triage' | 'csm'

// ── In-Dashboard Dispatch Modal State ─────────────────────────────────────────
const showDispatchModal = ref(false)
const dispatchTicket = ref(null)
const dispatchStaff = ref('')
const dispatchType = ref('Technical Support')
const isDispatching = ref(false)

// ── Full Ticket Details Drawer State ──────────────────────────────────────────
const showDetailsModal = ref(false)
const selectedDetailsTicket = ref(null)

const availableStaff = computed(() => {
  if (Array.isArray(props.assignedStaffOptions) && props.assignedStaffOptions.length > 0) {
    return props.assignedStaffOptions
  }
  return dynamicStaff.value
})

const requestTypeOptions = computed(() => {
  return [
    { value: 'Technical Support', label: 'Technical Support', icon: 'fa-solid fa-wrench' },
    { value: 'Livestream', label: 'Livestream', icon: 'fa-solid fa-video' },
    { value: 'Events', label: 'Events Coverage', icon: 'fa-solid fa-calendar-check' },
  ]
})

function getStaffActiveCount(name) {
  if (!name || !props.staffWorkload) return 0
  const item = props.staffWorkload.find(s => (s.name || '').toLowerCase() === name.toLowerCase())
  return item ? (item.active || 0) : 0
}

function openDispatchModal(ticket) {
  dispatchTicket.value = ticket
  dispatchType.value = ticket.ticket_type || 'Technical Support'
  dispatchStaff.value = ticket.ticket_assigned_staff || ''
  showDispatchModal.value = true
}

function closeDispatchModal() {
  showDispatchModal.value = false
  dispatchTicket.value = null
  dispatchStaff.value = ''
}

function submitDispatch() {
  if (!dispatchTicket.value) return
  const staff = (dispatchStaff.value || '').trim()
  if (!staff) {
    toast.error('Please select an IT staff member to assign.')
    return
  }

  const refNo = dispatchTicket.value.client_referenceNo
  isDispatching.value = true

  router.post('/admin/tickets-v2/bulk-assign', {
    ticket_ids: [refNo],
    assigned_staff: staff,
    ticket_type: dispatchType.value || 'Technical Support',
  }, {
    preserveScroll: true,
    onSuccess: () => {
      toast.success(`Ticket #${refNo} dispatched to ${staff}!`)
      closeDispatchModal()
      router.reload({ only: ['adminQueue', 'ticketStats', 'staffWorkload', 'slaStats'] })
    },
    onError: (err) => {
      toast.error(err?.message || 'Dispatch assignment failed.')
    },
    onFinish: () => {
      isDispatching.value = false
    },
  })
}

function formatTicketForModal(raw) {
  if (!raw) return null
  const eventData = raw.event || raw.detail?.event || (raw.event_start_date ? {
    event_start_date: raw.event_start_date,
    event_end_date: raw.event_end_date,
    event_venue: raw.event_venue,
    event_remarks: raw.event_remarks,
    other_request: raw.other_request,
  } : null)

  return {
    client_id: raw.client_id,
    client_referenceNo: raw.client_referenceNo,
    client_name: raw.client_name,
    client_division_agency: raw.client_division_agency,
    client_email: raw.client_email,
    date_requested: raw.date_requested,
    created_at: raw.created_at,
    event: eventData,
    detail: {
      ticket_id: raw.ticket_id,
      ticket_type: raw.ticket_type,
      ticket_support_type: raw.ticket_support_type,
      ticket_nature_of_support: raw.ticket_nature_of_support,
      ticket_problem_that_needed_support: raw.ticket_problem_that_needed_support,
      ticket_assigned_staff: raw.ticket_assigned_staff,
      ticket_status: raw.ticket_status,
      date_requested: raw.date_requested,
      created_at: raw.created_at,
      event: eventData,
    },
  }
}

function openTicketDetails(ticket) {
  selectedDetailsTicket.value = formatTicketForModal(ticket)
  showDetailsModal.value = true
}

function closeTicketDetails() {
  showDetailsModal.value = false
  selectedDetailsTicket.value = null
}

function onTicketUpdated() {
  router.reload({ only: ['adminQueue', 'ticketStats', 'staffWorkload', 'slaStats'] })
}

function formatDate(val) {
  if (!val) return 'N/A'
  try {
    const d = new Date(val)
    return d.toLocaleDateString('en-US', { month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit' })
  } catch {
    return val
  }
}
</script>

<template>
  <div class="space-y-6">
    <!-- Admin Operations Banner & Real-Time Alerts -->
    <div class="rounded-2xl border border-blue-200/90 bg-gradient-to-r from-blue-900 via-indigo-950 to-slate-900 p-5 text-white shadow-sm flex flex-col md:flex-row md:items-center justify-between gap-4">
      <div class="flex items-center gap-3.5">
        <div class="w-11 h-11 rounded-xl bg-blue-500/20 border border-blue-400/30 flex items-center justify-center shrink-0">
          <i class="fa-solid fa-headset text-blue-300 text-lg"></i>
        </div>
        <div>
          <div class="flex items-center gap-2">
            <h2 class="text-lg font-black tracking-tight">Operations & Dispatch Management</h2>
            <span class="px-2 py-0.5 rounded-full text-[10px] font-bold uppercase tracking-wider bg-blue-400/20 text-blue-200 border border-blue-400/30">
              Admin
            </span>
          </div>
          <p class="text-xs text-blue-200/80 mt-0.5">
            Active ticket triage, staff workload balancing, dispatch queues, and SLA turnaround enforcement.
          </p>
        </div>
      </div>

      <!-- Quick Action Queue Counters -->
      <div class="flex items-center gap-2.5 text-xs flex-wrap">
        <Link
          :href="route('tickets-v2.index', { preset: 'unassigned' })"
          class="px-3.5 py-2 rounded-xl bg-amber-500/20 border border-amber-400/30 hover:bg-amber-500/30 transition flex items-center gap-2.5 cursor-pointer"
          title="View unassigned tickets"
        >
          <span class="w-2 h-2 rounded-full bg-amber-400 animate-pulse"></span>
          <div>
            <span class="text-amber-200 block text-[10px] uppercase font-bold tracking-wider">Unassigned</span>
            <span class="text-base font-black text-white">{{ ticketStats?.unassigned ?? 0 }}</span>
          </div>
        </Link>

        <Link
          :href="route('tickets-v2.index', { preset: 'overdue' })"
          class="px-3.5 py-2 rounded-xl bg-rose-500/20 border border-rose-400/30 hover:bg-rose-500/30 transition flex items-center gap-2.5 cursor-pointer"
          title="View tickets breaching SLA"
        >
          <span class="w-2 h-2 rounded-full bg-rose-400 animate-pulse"></span>
          <div>
            <span class="text-rose-200 block text-[10px] uppercase font-bold tracking-wider">SLA Overdue</span>
            <span class="text-base font-black text-white">{{ slaStats?.active_breached ?? 0 }}</span>
          </div>
        </Link>

        <Link
          :href="route('tickets-v2.index', { status: 'In Progress' })"
          class="px-3.5 py-2 rounded-xl bg-emerald-500/20 border border-emerald-400/30 hover:bg-emerald-500/30 transition flex items-center gap-2.5 cursor-pointer"
        >
          <div>
            <span class="text-emerald-200 block text-[10px] uppercase font-bold tracking-wider">In Progress</span>
            <span class="text-base font-black text-white">{{ ticketStats?.in_progress ?? 0 }}</span>
          </div>
        </Link>
      </div>
    </div>

    <!-- Executive KPI Row: TicketStats (6-card interactive grid) -->
    <div>
      <TicketStats :stats="ticketStats" :trends="trends" :filters="filters" />
    </div>

    <!-- View Tabs: Operations vs. Triage Queues vs. CSAT -->
    <div class="flex items-center justify-between border-b border-slate-200/80 pb-3 print:hidden">
      <div class="flex items-center gap-2">
        <button
          type="button"
          @click="activeTab = 'operations'"
          class="inline-flex items-center gap-2 px-4 py-2 rounded-xl text-sm font-bold transition cursor-pointer"
          :class="activeTab === 'operations'
            ? 'bg-blue-600 text-white shadow-sm'
            : 'bg-white border border-slate-200/80 text-slate-600 hover:bg-slate-50'"
        >
          <i class="fa-solid fa-chart-line text-xs"></i>
          <span>Operations & SLA</span>
        </button>

        <button
          type="button"
          @click="activeTab = 'triage'"
          class="inline-flex items-center gap-2 px-4 py-2 rounded-xl text-sm font-bold transition cursor-pointer"
          :class="activeTab === 'triage'
            ? 'bg-blue-600 text-white shadow-sm'
            : 'bg-white border border-slate-200/80 text-slate-600 hover:bg-slate-50'"
        >
          <i class="fa-solid fa-list-check text-xs"></i>
          <span>Triage & Queues</span>
          <span
            v-if="(ticketStats?.unassigned ?? 0) > 0"
            class="px-1.5 py-0.2 rounded-full text-[10px] font-bold"
            :class="activeTab === 'triage' ? 'bg-white text-blue-700' : 'bg-amber-100 text-amber-800'"
          >
            {{ ticketStats?.unassigned }}
          </span>
        </button>

        <button
          type="button"
          @click="activeTab = 'csm'"
          class="inline-flex items-center gap-2 px-4 py-2 rounded-xl text-sm font-bold transition cursor-pointer"
          :class="activeTab === 'csm'
            ? 'bg-blue-600 text-white shadow-sm'
            : 'bg-white border border-slate-200/80 text-slate-600 hover:bg-slate-50'"
        >
          <i class="fa-solid fa-star text-xs text-amber-300"></i>
          <span>Client Satisfaction (CSAT)</span>
        </button>
      </div>

      <div class="text-xs text-slate-400 font-medium hidden md:block">
        Authorized to assign, reassign, edit, and dispatch tickets
      </div>
    </div>

    <!-- Tab 1: Operations & SLA -->
    <div v-if="activeTab === 'operations'" class="space-y-6">
      <TicketSlaPerformance :sla-stats="slaStats" :filters="filters" />

      <!-- Staff Workload & Capacity Card (Crucial for Admin assignment decisions) -->
      <StaffWorkloadCard :items="staffWorkload" :filters="filters" />

      <div class="grid grid-cols-1 gap-6 xl:grid-cols-12">
        <div class="xl:col-span-6">
          <TechnicalSupportByNature :items="natureCounts" :filters="filters" />
        </div>
        <div class="xl:col-span-6">
          <TicketDivisionBar :items="divisionCounts" :filters="filters" />
        </div>
      </div>

      <div>
        <TechnicalSupportBySupportType :items="supportTypeCounts" :filters="filters" />
      </div>
    </div>

    <!-- Tab 2: Triage & Queues (Immediate Dispatch & Overdue Watchlist) -->
    <div v-if="activeTab === 'triage'" class="space-y-6">
      <div class="grid grid-cols-1 gap-6 xl:grid-cols-12">
        <!-- Unassigned Tickets Requiring Dispatch -->
        <div class="xl:col-span-6 bg-white rounded-2xl border border-slate-200/90 p-5 shadow-2xs space-y-4">
          <div class="flex items-center justify-between pb-3 border-b border-slate-100">
            <div class="flex items-center gap-2.5">
              <div class="w-8 h-8 rounded-lg bg-amber-50 border border-amber-200 flex items-center justify-center text-amber-600">
                <i class="fa-solid fa-user-plus text-xs"></i>
              </div>
              <div>
                <h3 class="text-sm font-bold text-slate-900">Unassigned Tickets (Needs Dispatch)</h3>
                <p class="text-[11px] text-slate-500">Tickets awaiting technician assignment</p>
              </div>
            </div>
            <Link
              :href="route('tickets-v2.index', { preset: 'unassigned' })"
              class="text-xs font-semibold text-blue-600 hover:text-blue-700 hover:underline"
            >
              View All &rarr;
            </Link>
          </div>

          <div v-if="adminQueue?.unassigned_tickets?.length" class="space-y-2.5">
            <div
              v-for="ticket in adminQueue.unassigned_tickets"
              :key="ticket.client_referenceNo"
              class="p-3 rounded-xl border border-slate-100 bg-slate-50/60 hover:bg-blue-50/40 transition flex items-center justify-between gap-3 text-xs"
            >
              <div class="min-w-0">
                <div class="flex items-center gap-2">
                  <button
                    type="button"
                    @click="openTicketDetails(ticket)"
                    class="font-mono font-bold text-blue-900 hover:text-blue-700 hover:underline cursor-pointer"
                    title="Click to inspect ticket details"
                  >
                    {{ ticket.client_referenceNo }}
                  </button>
                  <span class="px-1.5 py-0.5 rounded text-[10px] font-semibold bg-amber-100 text-amber-800">
                    {{ ticket.ticket_status }}
                  </span>
                  <span class="text-[10px] text-slate-400">
                    {{ formatDate(ticket.date_requested || ticket.created_at) }}
                  </span>
                </div>
                <p class="font-medium text-slate-800 truncate mt-0.5">{{ ticket.client_name }} • {{ mapDivision(ticket.client_division_agency) }}</p>
                <p class="text-[11px] text-slate-500 truncate mt-0.5">{{ ticket.ticket_problem_that_needed_support || 'No description provided' }}</p>
              </div>

              <div class="flex items-center gap-1.5 shrink-0">
                <button
                  type="button"
                  @click="openTicketDetails(ticket)"
                  class="px-2 py-1 rounded-lg border border-slate-200 bg-white hover:bg-slate-50 text-slate-700 font-semibold text-xs transition cursor-pointer shadow-2xs"
                  title="View full details and conversation"
                >
                  <i class="fa-regular fa-eye mr-1"></i>View
                </button>
                <button
                  type="button"
                  @click="openDispatchModal(ticket)"
                  class="px-3 py-1 rounded-lg bg-blue-600 hover:bg-blue-700 text-white font-bold text-xs transition cursor-pointer shadow-2xs inline-flex items-center gap-1.5"
                  title="Quick dispatch technician"
                >
                  <i class="fa-solid fa-user-plus text-[11px]"></i>
                  <span>Assign</span>
                </button>
              </div>
            </div>
          </div>
          <div v-else class="text-center py-8 text-slate-400 text-xs">
            <i class="fa-solid fa-circle-check text-emerald-500 text-2xl mb-1 block"></i>
            No unassigned tickets! Great job on dispatching.
          </div>
        </div>

        <!-- Overdue & At-Risk Watchlist -->
        <div class="xl:col-span-6 bg-white rounded-2xl border border-slate-200/90 p-5 shadow-2xs space-y-4">
          <div class="flex items-center justify-between pb-3 border-b border-slate-100">
            <div class="flex items-center gap-2.5">
              <div class="w-8 h-8 rounded-lg bg-rose-50 border border-rose-200 flex items-center justify-center text-rose-600">
                <i class="fa-solid fa-clock-rotate-left text-xs"></i>
              </div>
              <div>
                <h3 class="text-sm font-bold text-slate-900">SLA At-Risk Watchlist (>24h)</h3>
                <p class="text-[11px] text-slate-500">Active tickets requiring prompt resolution</p>
              </div>
            </div>
            <Link
              :href="route('tickets-v2.index', { preset: 'overdue' })"
              class="text-xs font-semibold text-rose-600 hover:text-rose-700 hover:underline"
            >
              View All &rarr;
            </Link>
          </div>

          <div v-if="adminQueue?.overdue_tickets?.length" class="space-y-2.5">
            <div
              v-for="ticket in adminQueue.overdue_tickets"
              :key="ticket.client_referenceNo"
              class="p-3 rounded-xl border border-rose-100 bg-rose-50/40 hover:bg-rose-50 transition flex items-center justify-between gap-3 text-xs"
            >
              <div class="min-w-0">
                <div class="flex items-center gap-2">
                  <button
                    type="button"
                    @click="openTicketDetails(ticket)"
                    class="font-mono font-bold text-rose-900 hover:text-rose-700 hover:underline cursor-pointer"
                    title="Click to inspect ticket details"
                  >
                    {{ ticket.client_referenceNo }}
                  </button>
                  <span class="px-1.5 py-0.5 rounded text-[10px] font-semibold bg-rose-100 text-rose-800">
                    Overdue
                  </span>
                  <span class="text-[11px] text-slate-500 truncate">
                    Assigned: <strong>{{ ticket.ticket_assigned_staff || 'Unassigned' }}</strong>
                  </span>
                </div>
                <p class="font-medium text-slate-800 truncate mt-0.5">{{ ticket.client_name }} • {{ mapDivision(ticket.client_division_agency) }}</p>
                <p class="text-[11px] text-slate-500 truncate mt-0.5">{{ ticket.ticket_problem_that_needed_support || 'No description provided' }}</p>
              </div>

              <div class="flex items-center gap-1.5 shrink-0">
                <button
                  type="button"
                  @click="openTicketDetails(ticket)"
                  class="px-2.5 py-1 rounded-lg border border-slate-200 bg-white hover:bg-slate-50 text-slate-700 font-semibold text-xs shrink-0 transition shadow-2xs cursor-pointer"
                  title="Inspect ticket details and timeline"
                >
                  Inspect
                </button>
                <button
                  type="button"
                  @click="openDispatchModal(ticket)"
                  class="px-2.5 py-1 rounded-lg bg-slate-800 hover:bg-slate-900 text-white font-semibold text-xs shrink-0 transition shadow-2xs inline-flex items-center gap-1 cursor-pointer"
                  title="Reassign to another technician"
                >
                  <i class="fa-solid fa-user-gear text-[11px]"></i>
                  <span>Reassign</span>
                </button>
              </div>
            </div>
          </div>
          <div v-else class="text-center py-8 text-slate-400 text-xs">
            <i class="fa-solid fa-circle-check text-emerald-500 text-2xl mb-1 block"></i>
            No overdue active tickets! SLA compliance is on track.
          </div>
        </div>
      </div>

      <!-- Staff Workload Heatmap for Rebalancing -->
      <StaffWorkloadCard :items="staffWorkload" :filters="filters" />
    </div>

    <!-- Tab 3: Client Satisfaction (CSAT) -->
    <div v-if="activeTab === 'csm'" class="space-y-6">
      <div class="grid grid-cols-1 gap-6 xl:grid-cols-12">
        <div class="xl:col-span-8">
          <TechnicalSupportRatings :items="ratingCounts" />
        </div>
        <div class="xl:col-span-4">
          <TechnicalSupportRatingsDistribution
            :items="ratingCounts"
            :unrated-total="ratingUnratedTotal"
          />
        </div>
      </div>

      <RecentFeedbackCard :items="recentFeedback" />
    </div>

    <!-- ── IN-DASHBOARD QUICK DISPATCH MODAL ────────────────────────────── -->
    <Modal :show="showDispatchModal" @close="closeDispatchModal" maxWidth="lg">
      <div class="p-6 bg-white rounded-2xl">
        <div class="flex items-center justify-between pb-3.5 border-b border-slate-100">
          <div class="flex items-center gap-2.5">
            <div
              class="w-9 h-9 rounded-xl flex items-center justify-center font-bold text-sm shadow-xs shrink-0"
              :class="dispatchTicket?.ticket_assigned_staff ? 'bg-indigo-100 text-indigo-800' : 'bg-blue-100 text-blue-900'"
            >
              <i :class="dispatchTicket?.ticket_assigned_staff ? 'fa-solid fa-user-gear' : 'fa-solid fa-user-plus'"></i>
            </div>
            <div>
              <div class="flex items-center gap-2 flex-wrap">
                <h3 class="text-base font-bold text-slate-900">
                  {{ dispatchTicket?.ticket_assigned_staff ? 'Reassign IT Staff' : 'Dispatch IT Staff' }}
                </h3>
                <span v-if="dispatchTicket" class="font-mono font-bold text-blue-700 text-xs px-2 py-0.5 rounded bg-blue-50 border border-blue-200">
                  #{{ dispatchTicket.client_referenceNo }}
                </span>
              </div>
              <p class="text-xs text-slate-500 mt-0.5">
                {{ dispatchTicket?.ticket_assigned_staff ? 'Reallocate ticket responsibility to another IT staff member' : 'Allocate an IT staff member to take action on this ticket' }}
              </p>
            </div>
          </div>
          <button
            type="button"
            @click="closeDispatchModal"
            class="text-slate-400 hover:text-slate-600 p-1.5 rounded-lg hover:bg-slate-100 transition cursor-pointer"
            title="Close modal"
          >
            <i class="fa-solid fa-xmark text-sm"></i>
          </button>
        </div>

        <div class="space-y-4 py-4 text-xs">
          <!-- Ticket Context Card -->
          <div v-if="dispatchTicket" class="p-3.5 bg-slate-50/80 rounded-xl border border-slate-200/80 space-y-2.5">
            <div class="flex items-center justify-between gap-2">
              <div class="flex items-center gap-2 min-w-0">
                <div class="w-7 h-7 rounded-lg bg-blue-100 text-blue-900 flex items-center justify-center font-bold text-xs shrink-0">
                  <i class="fa-solid fa-user text-[11px]"></i>
                </div>
                <div class="min-w-0">
                  <p class="text-xs font-bold text-slate-900 truncate">{{ dispatchTicket.client_name }}</p>
                  <p class="text-[11px] text-slate-500 truncate flex items-center gap-1">
                    <span>{{ mapDivision(dispatchTicket.client_division_agency) }}</span>
                    <span v-if="dispatchTicket.client_email" class="text-slate-300">•</span>
                    <span v-if="dispatchTicket.client_email" class="truncate">{{ dispatchTicket.client_email }}</span>
                  </p>
                </div>
              </div>

              <span
                class="inline-flex items-center gap-1 px-2.5 py-0.5 text-[10px] rounded-full font-bold uppercase tracking-wider text-white shrink-0 shadow-2xs"
                :class="{
                  'bg-blue-600': dispatchTicket.ticket_status === 'New',
                  'bg-amber-500': dispatchTicket.ticket_status === 'Pending',
                  'bg-emerald-600': dispatchTicket.ticket_status === 'In Progress',
                  'bg-slate-600': !dispatchTicket.ticket_status || dispatchTicket.ticket_status === 'Closed',
                }"
              >
                {{ dispatchTicket.ticket_status || 'New' }}
              </span>
            </div>

            <!-- Problem snippet -->
            <div
              v-if="dispatchTicket.ticket_problem_that_needed_support || dispatchTicket.ticket_nature_of_support"
              class="text-xs text-slate-700 bg-white p-2.5 rounded-lg border border-slate-200/70"
            >
              <div class="text-[10px] font-bold uppercase tracking-wider text-slate-400 mb-0.5 flex items-center justify-between">
                <span>{{ dispatchTicket.ticket_nature_of_support || 'Reported Issue' }}</span>
                <span v-if="dispatchTicket.ticket_support_type" class="text-blue-700 font-semibold normal-case">
                  {{ dispatchTicket.ticket_support_type }}
                </span>
              </div>
              <p class="text-xs text-slate-700 line-clamp-2 leading-relaxed">
                {{ dispatchTicket.ticket_problem_that_needed_support || 'No specific description recorded.' }}
              </p>
            </div>
          </div>

          <!-- Staff Selection -->
          <div>
            <div class="flex items-center justify-between mb-1.5">
              <label class="block text-xs font-bold text-slate-800">
                <i class="fa-solid fa-user-check text-blue-900 mr-1.5"></i>Assign to IT Staff <span class="text-rose-500">*</span>
              </label>
            </div>
            <div class="relative">
              <select
                v-model="dispatchStaff"
                class="w-full h-10 pl-3 pr-8 rounded-xl border border-slate-300 focus:ring-2 focus:ring-blue-900 focus:border-blue-900 text-xs bg-white text-slate-800 shadow-2xs font-medium cursor-pointer"
              >
                <option value="" disabled>Select IT Staff here</option>
                <option v-for="st in availableStaff" :key="st" :value="st">
                  {{ st }} • ({{ getStaffActiveCount(st) }} open {{ getStaffActiveCount(st) === 1 ? 'ticket' : 'tickets' }})
                </option>
              </select>
            </div>
            <p class="text-[11px] text-slate-400 mt-1 flex items-center gap-1.5">
              <i class="fa-solid fa-circle-info text-[10px] text-slate-400"></i>
              <span>Parentheses show real-time open ticket count for each staff member.</span>
            </p>
          </div>

          <!-- Request Type Selection -->
          <div>
            <label class="block text-xs font-bold text-slate-800 mb-1.5">
              <i class="fa-solid fa-tag text-blue-900 mr-1.5"></i>Request Type
            </label>
            <div class="grid grid-cols-3 gap-2 mt-1">
              <button
                v-for="opt in requestTypeOptions"
                :key="opt.value"
                type="button"
                @click="dispatchType = opt.value"
                class="flex items-center gap-2 p-2 rounded-xl border text-xs font-semibold transition cursor-pointer text-left"
                :class="dispatchType === opt.value
                  ? 'bg-blue-50 border-blue-900 text-blue-900 ring-1 ring-blue-900 shadow-2xs'
                  : 'bg-white border-slate-200 text-slate-700 hover:bg-slate-50 hover:border-slate-300'"
              >
                <div
                  class="w-6 h-6 rounded-lg flex items-center justify-center shrink-0 text-xs"
                  :class="dispatchType === opt.value ? 'bg-blue-900 text-white' : 'bg-slate-100 text-slate-600'"
                >
                  <i :class="opt.icon"></i>
                </div>
                <span class="truncate">{{ opt.label }}</span>
              </button>
            </div>
          </div>
        </div>

        <!-- Footer Buttons -->
        <div class="flex items-center justify-end gap-2.5 pt-3.5 border-t border-slate-100">
          <button
            type="button"
            @click="closeDispatchModal"
            class="px-4 py-2 text-xs font-semibold rounded-xl border border-slate-200 text-slate-700 hover:bg-slate-50 transition cursor-pointer"
          >
            Cancel
          </button>
          <button
            type="button"
            :disabled="isDispatching || !dispatchStaff"
            @click="submitDispatch"
            class="inline-flex items-center gap-2 px-4 py-2 text-xs font-bold uppercase rounded-xl shadow-sm text-white bg-blue-900 hover:bg-blue-800 disabled:opacity-50 transition cursor-pointer"
          >
            <i v-if="isDispatching" class="fa-solid fa-spinner animate-spin text-xs"></i>
            <i v-else class="fa-solid fa-check text-xs"></i>
            <span>
              {{ isDispatching ? 'Dispatching...' : (dispatchTicket?.ticket_assigned_staff ? 'Confirm Reassign' : 'Confirm Dispatch') }}
            </span>
          </button>
        </div>
      </div>
    </Modal>

    <!-- ── SLIDE-OVER DRAWER - Unified Ticket Details ───────────────────── -->
    <ModalTicketDetails
      :show="showDetailsModal"
      :ticket="selectedDetailsTicket"
      :allow-status-change="false"
      @close="closeTicketDetails"
      @updated="onTicketUpdated"
    />
  </div>
</template>

