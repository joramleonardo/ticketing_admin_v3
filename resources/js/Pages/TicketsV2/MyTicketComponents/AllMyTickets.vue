<script setup>
import { Link, router, usePage } from '@inertiajs/vue3'
import { ref, computed, watch, onMounted } from 'vue'
import { useToast } from 'vue-toastification'
import Modal from '@/Components/Modal.vue'
import ModalAction_CreateTicket from '@/Pages/TicketsV2/ModalComponents/ModalAction_CreateTicket.vue'
import ModalAction_CompleteTicket from '@/Pages/TicketsV2/ModalComponents/ModalAction_CompleteTicket.vue'
import ModalTicketDetails from '@/Pages/TicketsV2/ModalComponents/ModalTicketDetails.vue'
import TicketSlaBadge from '@/Components/TicketSlaBadge.vue'

// ── Props from controller (tickets paginator + filters + stats) ────────────────
const props = defineProps({
  tickets: { type: Object, required: true },
  ticketStats: { type: Object, default: () => ({}) },
  filters: { type: Object, default: () => ({}) },
})

// ── Logged-in role (for Delete/Assign etc) ──────────────────────────────────────
const page = usePage()
const toast = useToast()
const isAdmin = computed(() => page.props?.auth?.user?.role === 'Admin')
const currentUserName = computed(() => page.props?.auth?.user?.name || 'Technician')

// ── Division mapping ────────────────────────────────────────────────────────────
const DIVISION_MAP = Object.freeze({ 1: 'FAD', 2: 'IRAD', 3: 'CRPD', 4: 'OD-MISPS' })

function mapDivision(value) {
  const v = (value ?? '').toString().trim()
  if (!v) return 'OTHERS'
  const n = Number(v)
  if (Number.isFinite(n) && DIVISION_MAP[n]) return DIVISION_MAP[n]
  const up = v.toUpperCase()
  if (['FAD', 'IRAD', 'CRPD', 'OD-MISPS'].includes(up)) return up
  if (up.includes('OTHER')) return 'OTHERS'
  return 'OTHERS'
}

const DIVISION_OPTIONS = [
  { value: '', label: 'All Divisions' },
  { value: '1', label: 'FAD' },
  { value: '2', label: 'IRAD' },
  { value: '3', label: 'CRPD' },
  { value: '4', label: 'OD-MISPS' },
  { value: 'OTHERS', label: 'OTHERS' },
]

const divisionFilter = ref(
  new URLSearchParams(typeof location !== 'undefined' ? location.search : '').get('division') ?? (props.filters?.division ?? '')
)

const divisionLabel = computed(() => {
  const found = DIVISION_OPTIONS.find(o => String(o.value) === String(divisionFilter.value))
  return found ? found.label : ''
})

// ── Date format helper ─────────────────────────────────────────────────────────
function formatDate(d) {
  if (!d) return '—'
  const date = new Date(d)
  if (isNaN(date.getTime())) return String(d)
  return date.toLocaleDateString(undefined, { year: 'numeric', month: 'short', day: 'numeric' })
}

// ── Rating helpers ─────────────────────────────────────────────────────────────
const getRatingLabel = (row) => {
  return row?.rating?.rating_result ?? null
}

const getRatingScore = (row) => {
  const label = (getRatingLabel(row) || '').toLowerCase().trim()
  if (!label) return null

  if (label.includes('excellent')) return 5
  if (label.includes('very')) return 4
  if (label.includes('satisfactory')) return 3
  if (label.includes('fair')) return 2
  if (label.includes('poor') || label.includes('needs improvement')) return 1

  return null
}

const isCompletedStatus = (row) => {
  const s = row?.detail?.ticket_status
  return s === 'Completed' || s === 'Closed'
}

const hasRating = (row) => {
  return (
    isCompletedStatus(row) &&
    row?.rating?.rating_status === 'Received' &&
    getRatingScore(row) !== null
  )
}

// ── Local state & pagination ───────────────────────────────────────────────────
const BASE_PATH = '/admin/my-tickets'

const tickets = ref(Array.isArray(props.tickets?.data) ? props.tickets.data : [])
const isLoading = ref(false)

const pagination = ref({
  current_page: props.tickets?.current_page ?? 1,
  last_page: props.tickets?.last_page ?? 1,
  total: props.tickets?.total ?? 0,
  prev_page_url: props.tickets?.prev_page_url ?? null,
  next_page_url: props.tickets?.next_page_url ?? null,
  links: Array.isArray(props.tickets?.links) ? props.tickets.links : [],
  per_page: props.tickets?.per_page ?? 10,
})

const perPage = ref(
  Number(new URLSearchParams(typeof location !== 'undefined' ? location.search : '').get('perPage')) ||
    (props.tickets?.per_page ?? 10)
)
const searchText = ref(
  new URLSearchParams(typeof location !== 'undefined' ? location.search : '').get('search') ?? (props.filters?.search ?? '')
)
const statusFilter = ref(
  new URLSearchParams(typeof location !== 'undefined' ? location.search : '').get('status') ?? (props.filters?.status ?? '')
)

// Sync local state when props change via parent reloads
watch(
  () => props.tickets,
  (val) => {
    if (val) {
      tickets.value = Array.isArray(val.data) ? val.data : []
      pagination.value = {
        current_page: val.current_page ?? 1,
        last_page: val.last_page ?? 1,
        total: val.total ?? 0,
        prev_page_url: val.prev_page_url ?? null,
        next_page_url: val.next_page_url ?? null,
        links: Array.isArray(val.links) ? val.links : [],
        per_page: val.per_page ?? perPage.value,
      }
      if (showDetailsModal.value && selectedRow.value) {
        const refNo = selectedRow.value.client_referenceNo
        const fresh = tickets.value.find((t) => t.client_referenceNo === refNo)
        if (fresh) {
          selectedRow.value = fresh
        }
      }
    }
  },
  { deep: true }
)

// ── Quick Status Filter Tabs ──────────────────────────────────────────────────
const statusTabs = computed(() => [
  {
    label: 'All',
    value: '',
    count: props.ticketStats?.total ?? pagination.value.total ?? 0,
    icon: 'fa-solid fa-layer-group',
    iconColor: 'text-slate-500',
  },
  {
    label: 'Pending',
    value: 'Pending',
    count: props.ticketStats?.pending ?? 0,
    icon: 'fa-solid fa-bolt',
    iconColor: 'text-amber-500',
  },
  {
    label: 'In Progress',
    value: 'In Progress',
    count: props.ticketStats?.in_progress ?? 0,
    icon: 'fa-solid fa-screwdriver-wrench',
    iconColor: 'text-blue-500',
  },
  {
    label: 'Completed',
    value: 'Completed',
    count: props.ticketStats?.completed ?? 0,
    icon: 'fa-solid fa-circle-check',
    iconColor: 'text-emerald-500',
  },
  {
    label: 'Closed',
    value: 'Closed',
    count: props.ticketStats?.closed ?? 0,
    icon: 'fa-solid fa-lock',
    iconColor: 'text-slate-500',
  },
])

function setStatusFilter(status) {
  statusFilter.value = status
  fetchTickets(1)
}

// ── Table Density Toggle (Comfortable vs Compact) ───────────────────────────
const isCompact = ref(
  typeof localStorage !== 'undefined' && localStorage.getItem('my_tickets_density') === 'compact'
)

function toggleDensity() {
  isCompact.value = !isCompact.value
  try {
    localStorage.setItem('my_tickets_density', isCompact.value ? 'compact' : 'comfortable')
  } catch (e) {}
}

// ── Smart Workflow Presets ───────────────────────────────────────────────────
const presetFilter = ref(
  new URLSearchParams(typeof location !== 'undefined' ? location.search : '').get('preset') ??
    (props.filters?.preset ?? '')
)

const presetTabs = computed(() => [
  {
    label: 'All Tickets',
    value: '',
    icon: 'fa-solid fa-list-check',
    count: null,
  },
  {
    label: 'Needs Attention',
    value: 'needs_attention',
    icon: 'fa-solid fa-triangle-exclamation',
    count: props.ticketStats?.presets?.needs_attention ?? 0,
    badgeClass: 'bg-amber-100 text-amber-800',
  },
  {
    label: 'Overdue SLA',
    value: 'overdue',
    icon: 'fa-solid fa-clock-rotate-left',
    count: props.ticketStats?.presets?.overdue ?? 0,
    badgeClass: 'bg-rose-100 text-rose-800',
  },
  {
    label: 'Awaiting Rating',
    value: 'awaiting_rating',
    icon: 'fa-solid fa-star-half-stroke',
    count: props.ticketStats?.presets?.awaiting_rating ?? 0,
    badgeClass: 'bg-purple-100 text-purple-800',
  },
  {
    label: 'Completed Today',
    value: 'completed_today',
    icon: 'fa-solid fa-calendar-check',
    count: props.ticketStats?.presets?.completed_today ?? 0,
    badgeClass: 'bg-emerald-100 text-emerald-800',
  },
])

function setPresetFilter(preset) {
  presetFilter.value = presetFilter.value === preset ? '' : preset
  fetchTickets(1)
}

const showPresetsMenu = ref(false)

const activePresetLabel = computed(() => {
  const found = presetTabs.value.find(p => p.value === presetFilter.value)
  return found && found.value ? found.label : null
})

const activePresetCount = computed(() => {
  const found = presetTabs.value.find(p => p.value === presetFilter.value)
  return found ? found.count : null
})

const hasActiveFilters = computed(() => {
  return !!(
    (searchText.value && searchText.value.trim()) ||
    statusFilter.value ||
    divisionFilter.value ||
    presetFilter.value
  )
})

// ── Export URL for current view ──────────────────────────────────────────────
const exportUrl = computed(() => {
  const params = new URLSearchParams()
  if (searchText.value) params.set('search', searchText.value)
  if (statusFilter.value) params.set('status', statusFilter.value)
  if (divisionFilter.value) params.set('division', divisionFilter.value)
  if (presetFilter.value) params.set('preset', presetFilter.value)
  const qs = params.toString()
  return qs ? `/admin/my-tickets/export?${qs}` : '/admin/my-tickets/export'
})

const buildUrl = (page = 1) => {
  const params = new URLSearchParams()
  params.set('page', page)
  if (perPage.value) params.set('perPage', perPage.value)
  const s = (searchText.value || '').trim()
  if (s) params.set('search', s)
  const st = (statusFilter.value || '').trim()
  if (st) params.set('status', st)
  const dv = (divisionFilter.value ?? '').toString().trim()
  if (dv) params.set('division', dv)
  const pr = (presetFilter.value || '').trim()
  if (pr) params.set('preset', pr)
  return `${BASE_PATH}?${params.toString()}`
}

const pageFrom = computed(() => {
  const p = pagination.value
  if (!p?.total) return 0
  return (p.current_page - 1) * (p.per_page ?? perPage.value) + 1
})
const pageTo = computed(() => {
  const p = pagination.value
  if (!p?.total) return 0
  const end = p.current_page * (p.per_page ?? perPage.value)
  return end > p.total ? p.total : end
})

// ── Fetch Tickets ─────────────────────────────────────────────────────────────
const fetchTickets = (target = 1) => {
  isLoading.value = true
  const url = typeof target === 'string' ? target : buildUrl(target)
  router.visit(url, {
    method: 'get',
    preserveState: true,
    preserveScroll: true,
    only: ['tickets', 'filters'],
    onSuccess: (pageObj) => {
      const p = pageObj.props.tickets || {}
      tickets.value = Array.isArray(p.data) ? p.data : []
      pagination.value = {
        current_page: p.current_page ?? 1,
        last_page: p.last_page ?? 1,
        total: p.total ?? 0,
        prev_page_url: p.prev_page_url ?? null,
        next_page_url: p.next_page_url ?? null,
        links: Array.isArray(p.links) ? p.links : [],
        per_page: p.per_page ?? perPage.value,
      }
      if (pageObj.props.filters) {
        searchText.value = pageObj.props.filters.search ?? searchText.value
        statusFilter.value = pageObj.props.filters.status ?? statusFilter.value
        perPage.value = pageObj.props.filters.perPage ?? perPage.value
        divisionFilter.value = pageObj.props.filters.division ?? divisionFilter.value
        presetFilter.value = pageObj.props.filters.preset ?? presetFilter.value
      }
      if (showDetailsModal.value && selectedRow.value) {
        const refNo = selectedRow.value.client_referenceNo
        const fresh = tickets.value.find((t) => t.client_referenceNo === refNo)
        if (fresh) {
          selectedRow.value = fresh
        }
      }
      isLoading.value = false
    },
    onError: () => {
      isLoading.value = false
    },
  })
}

let searchTimer
const onSearchInput = () => {
  clearTimeout(searchTimer)
  const s = (searchText.value || '').trim()
  if (s.length > 0 && s.length < 2) return
  searchTimer = setTimeout(() => fetchTickets(1), 500)
}

const clearFilters = () => {
  searchText.value = ''
  statusFilter.value = ''
  divisionFilter.value = ''
  presetFilter.value = ''
  fetchTickets(1)
}

// ── Click Outside Directive ──────────────────────────────────────────────────
const vClickOutside = {
  beforeMount(el, binding) {
    el.__co = (e) => {
      if (!(el === e.target || el.contains(e.target))) binding.value(e)
    }
    document.addEventListener('mousedown', el.__co)
    document.addEventListener('touchstart', el.__co)
  },
  unmounted(el) {
    document.removeEventListener('mousedown', el.__co)
    document.removeEventListener('touchstart', el.__co)
    delete el.__co
  },
}

// ── View Details Modal / Slide-Over ───────────────────────────────────────────
const selectedRow = ref(null)
const selectedIndex = ref(-1)
const showDetailsModal = ref(false)
const modalInitialTab = ref('overview')

// ── Dropdown & Row Actions State ──────────────────────────────────────────────
const activeRowMenuId = ref(null)

function toggleRowMenu(id) {
  activeRowMenuId.value = activeRowMenuId.value === id ? null : id
}

function closeRowMenu() {
  activeRowMenuId.value = null
}

function isNearBottom(index) {
  return index >= (tickets.value.length - 2)
}

function copyReferenceNumber(refNo) {
  if (!refNo) return
  copyTextToClipboard(refNo, `Reference #${refNo} copied to clipboard!`)
}

function copyTicketLink(refNo) {
  if (!refNo) return
  const url = `${window.location.origin}/admin/my-tickets?search=${encodeURIComponent(refNo)}`
  copyTextToClipboard(url, `Ticket link copied to clipboard!`)
}

function copyRatingLink(refNo) {
  if (!refNo) return
  const url = `${window.location.origin}/client/rate/${encodeURIComponent(refNo)}`
  copyTextToClipboard(url, `Client feedback/rating link copied to clipboard!`)
}

function copyTextToClipboard(text, successMsg) {
  if (navigator?.clipboard?.writeText) {
    navigator.clipboard.writeText(text).then(() => {
      toast.success(successMsg)
    }).catch(() => {
      fallbackCopy(text, successMsg)
    })
  } else {
    fallbackCopy(text, successMsg)
  }
}

function fallbackCopy(text, successMsg) {
  try {
    const el = document.createElement('textarea')
    el.value = text
    el.setAttribute('readonly', '')
    el.style.position = 'fixed'
    el.style.left = '-9999px'
    document.body.appendChild(el)
    el.select()
    document.execCommand('copy')
    document.body.removeChild(el)
    toast.success(successMsg)
  } catch (e) {
    toast.error('Unable to copy to clipboard')
  }
}

const emit = defineEmits(['attended', 'completed'])

function openModal(row, tab = 'overview') {
  selectedRow.value = row
  selectedIndex.value = tickets.value.findIndex(
    (t) => (t?.client_id ?? t?.id ?? t?.client_referenceNo) === (row?.client_id ?? row?.id ?? row?.client_referenceNo)
  )
  modalInitialTab.value = tab
  showDetailsModal.value = true
}

function closeDetailsModal() {
  showDetailsModal.value = false
  selectedRow.value = null
  selectedIndex.value = -1
}

const hasPrevTicket = computed(() => selectedIndex.value > 0)
const hasNextTicket = computed(() => selectedIndex.value >= 0 && selectedIndex.value < tickets.value.length - 1)

function selectPrevTicket() {
  if (hasPrevTicket.value) {
    selectedIndex.value--
    selectedRow.value = tickets.value[selectedIndex.value]
  }
}

function selectNextTicket() {
  if (hasNextTicket.value) {
    selectedIndex.value++
    selectedRow.value = tickets.value[selectedIndex.value]
  }
}

function onTicketUpdated(updatedData) {
  if (selectedRow.value && updatedData) {
    const currentRef = selectedRow.value.client_referenceNo || updatedData.ref
    const targetTicket = tickets.value.find(
      (t) => (currentRef && t?.client_referenceNo === currentRef) ||
             (t?.detail?.ticket_id === updatedData.id) ||
             (t?.client_id === updatedData.id) ||
             (t?.id === updatedData.id)
    )
    if (targetTicket) {
      if (!targetTicket.detail) targetTicket.detail = {}
      if (updatedData.ticket_type !== undefined) targetTicket.detail.ticket_type = updatedData.ticket_type
      if (updatedData.ticket_assigned_staff !== undefined) targetTicket.detail.ticket_assigned_staff = updatedData.ticket_assigned_staff
      if (updatedData.ticket_problem_that_needed_support !== undefined) targetTicket.detail.ticket_problem_that_needed_support = updatedData.ticket_problem_that_needed_support
      if (updatedData.ticket_nature_of_support !== undefined) targetTicket.detail.ticket_nature_of_support = updatedData.ticket_nature_of_support
      if (updatedData.ticket_support_type !== undefined) targetTicket.detail.ticket_support_type = updatedData.ticket_support_type
      if (updatedData.ticket_actions_taken !== undefined) targetTicket.detail.ticket_actions_taken = updatedData.ticket_actions_taken
      if (updatedData.ticket_remarks !== undefined) targetTicket.detail.ticket_remarks = updatedData.ticket_remarks
      if (updatedData.status !== undefined) {
        targetTicket.detail.ticket_status = updatedData.status
        targetTicket.ticket_status = updatedData.status
      }
      if (updatedData.assignedTo !== undefined) {
        targetTicket.detail.ticket_assigned_staff = updatedData.assignedTo
      }
      selectedRow.value = targetTicket
    }
  }
  fetchTickets(pagination.value.current_page ?? 1)
}

function onTicketAttended(payload) {
  fetchTickets(pagination.value.current_page ?? 1)
  emit('attended', payload)
}

// ── Create Ticket Modal ───────────────────────────────────────────────────────
const showCreateModal = ref(false)
const createModalMode = ref('standard')
const showCreateMenu = ref(false)

function openCreateModal(mode = 'standard') {
  createModalMode.value = mode
  showCreateModal.value = true
}
function closeCreateModal() {
  showCreateModal.value = false
}
function onTicketCreated() {
  fetchTickets(1)
}

function refresh() {
  fetchTickets(pagination.value.current_page ?? 1)
}

function onChildCompleted({ id }) {
  const idx = tickets.value.findIndex(
    t =>
      t?.detail?.ticket_id === id ||
      t?.ticket_id === id ||
      t?.id === id ||
      t?.detail?.id === id
  )
  if (idx !== -1) {
    tickets.value[idx] = {
      ...tickets.value[idx],
      detail: { ...(tickets.value[idx]?.detail || {}), ticket_status: 'Completed' },
    }
  }
  fetchTickets(pagination.value.current_page ?? 1)
  emit('completed', { id })
}

// ── Inline Row Quick Actions (Attend & Complete) ────────────────────────────
const attendingRef = ref(null)

function attendTicket(row) {
  const ticketId = row.detail?.ticket_id
  if (!ticketId) {
    toast.error('Ticket ID not found.')
    return
  }
  if (attendingRef.value) return
  attendingRef.value = row.client_referenceNo

  router.put(
    `/tickets-attend/${ticketId}`,
    {
      ticket_status: 'In Progress',
      ticket_attended_by: currentUserName.value,
    },
    {
      preserveScroll: true,
      onSuccess: () => {
        toast.success(`Ticket ${row.client_referenceNo} is now In Progress!`)
        attendingRef.value = null
        if (row.detail) {
          row.detail.ticket_status = 'In Progress'
          row.detail.ticket_attended_by = currentUserName.value
        }
        fetchTickets(pagination.value.current_page ?? 1)
        emit('attended', { ticketId, referenceNo: row.client_referenceNo })
      },
      onError: () => {
        attendingRef.value = null
        toast.error('Failed to attend ticket.')
      },
    }
  )
}

const showCompleteModal = ref(false)
const selectedCompleteTicket = ref(null)

function openCompleteModal(row) {
  selectedCompleteTicket.value = row
  showCompleteModal.value = true
}

function closeCompleteModal() {
  showCompleteModal.value = false
  selectedCompleteTicket.value = null
}

function handleCompleteSuccess({ id }) {
  showCompleteModal.value = false
  selectedCompleteTicket.value = null
  toast.success('Ticket marked as completed!')
  onChildCompleted({ id })
}

// Expose methods & state to parent
defineExpose({
  refresh,
  setStatusFilter,
  statusFilter,
  setPresetFilter,
  presetFilter,
})
</script>

<template>
  <div class="relative flex flex-col min-w-0 break-words bg-white w-full shadow-sm rounded-2xl border border-slate-200 overflow-hidden">
    <!-- ── Card Top Header ─────────────────────────────────────────────── -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between px-5 py-4 border-b border-slate-200 gap-3 bg-white">
      <div>
        <h3 class="font-bold text-base text-slate-900 tracking-tight flex items-center gap-2">
          <i class="fa-solid fa-list-check text-blue-600"></i>
          All Assigned Tickets
        </h3>
        <p class="text-xs text-slate-500 mt-0.5">Search, monitor SLAs, and manage your full ticket workload</p>
      </div>

      <div class="flex items-center gap-2 flex-wrap">
        <!-- Table Density Toggle -->
        <button
          type="button"
          @click="toggleDensity"
          class="inline-flex items-center gap-1.5 px-3 py-2 text-xs font-semibold rounded-xl border border-slate-200 bg-white hover:bg-slate-50 text-slate-700 transition shadow-2xs cursor-pointer"
          :title="isCompact ? 'Current: Compact view. Click for Comfortable' : 'Current: Comfortable view. Click for Compact'"
        >
          <i :class="isCompact ? 'fa-solid fa-table-cells-large text-blue-600' : 'fa-solid fa-bars text-slate-500'"></i>
          <span class="hidden sm:inline">{{ isCompact ? 'Compact' : 'Comfortable' }}</span>
        </button>

        <!-- Export CSV Button -->
        <a
          :href="exportUrl"
          class="inline-flex items-center gap-1.5 px-3 py-2 text-xs font-semibold rounded-xl border border-emerald-200 bg-emerald-50 hover:bg-emerald-100 text-emerald-800 transition shadow-2xs cursor-pointer"
          title="Export currently filtered ticket list as CSV"
        >
          <i class="fa-solid fa-file-arrow-down text-emerald-600"></i>
          <span class="hidden sm:inline">Export CSV</span>
        </a>

        <!-- Unified Creation Split Button -->
        <div class="relative inline-flex items-center shadow-xs rounded-xl" v-click-outside="() => (showCreateMenu = false)">
          <button
            type="button"
            @click="openCreateModal('standard')"
            class="inline-flex items-center gap-1.5 px-3.5 py-2 text-xs font-semibold rounded-l-xl text-white bg-blue-600 hover:bg-blue-700 transition cursor-pointer"
          >
            <i class="fa-solid fa-plus text-xs"></i>
            <span>New Ticket</span>
          </button>
          <button
            type="button"
            @click="showCreateMenu = !showCreateMenu"
            class="px-2.5 py-2 text-xs text-white bg-blue-700 hover:bg-blue-800 border-l border-blue-500/60 rounded-r-xl transition cursor-pointer"
            title="Additional ticket options"
          >
            <i class="fa-solid fa-chevron-down text-[10px] transition-transform" :class="{ 'rotate-180': showCreateMenu }"></i>
          </button>

          <!-- Dropdown Menu -->
          <div
            v-if="showCreateMenu"
            class="absolute right-0 top-full mt-1.5 w-56 bg-white rounded-xl shadow-xl border border-slate-200 py-1.5 z-40 divide-y divide-slate-100"
          >
            <div class="py-1">
              <button
                type="button"
                @click="openCreateModal('standard'); showCreateMenu = false"
                class="w-full text-left px-3.5 py-2 text-xs text-slate-700 hover:bg-blue-50 hover:text-blue-700 flex items-center gap-2.5 transition cursor-pointer"
              >
                <div class="w-6 h-6 rounded-lg bg-blue-50 text-blue-600 flex items-center justify-center shrink-0">
                  <i class="fa-solid fa-file-circle-plus text-xs"></i>
                </div>
                <div>
                  <div class="font-semibold text-slate-800">Standard Ticket</div>
                  <div class="text-[10px] text-slate-400">Regular IT service request</div>
                </div>
              </button>
            </div>
            <div class="py-1">
              <button
                type="button"
                @click="openCreateModal('late'); showCreateMenu = false"
                class="w-full text-left px-3.5 py-2 text-xs text-slate-700 hover:bg-rose-50 hover:text-rose-700 flex items-center gap-2.5 transition cursor-pointer"
              >
                <div class="w-6 h-6 rounded-lg bg-rose-50 text-rose-600 flex items-center justify-center shrink-0">
                  <i class="fa-solid fa-clock-rotate-left text-xs"></i>
                </div>
                <div>
                  <div class="font-semibold text-rose-700">Late Ticket</div>
                  <div class="text-[10px] text-slate-400">Log prior or delayed ticket</div>
                </div>
              </button>
            </div>
          </div>
        </div>

        <!-- Refresh Table -->
        <button
          type="button"
          @click="fetchTickets(pagination.current_page)"
          class="inline-flex items-center gap-1.5 px-3 py-2 text-xs font-semibold rounded-xl border border-slate-200 bg-white hover:bg-slate-50 text-slate-700 transition shadow-2xs cursor-pointer"
          title="Refresh ticket list"
        >
          <i class="fa-solid fa-arrows-rotate text-blue-600 text-xs" :class="{ 'animate-spin': isLoading }"></i>
          <span class="hidden sm:inline">Refresh</span>
        </button>
      </div>
    </div>

    <!-- ── Option A: Segmented Status Control Bar & Workflow Presets ────── -->
    <div class="bg-slate-50/70 border-b border-slate-200 px-5 py-2.5 relative z-20">
      <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 py-0.5">
        <!-- Sleek Segmented Capsule Tabs (Linear / GitHub style) -->
        <div class="inline-flex items-center p-1 bg-slate-200/70 rounded-xl gap-0.5 overflow-x-auto shrink-0 select-none max-w-full">
          <button
            v-for="tab in statusTabs"
            :key="tab.value"
            type="button"
            @click="setStatusFilter(tab.value)"
            class="px-3 py-1.5 text-xs font-semibold rounded-lg transition-all inline-flex items-center gap-1.5 shrink-0 cursor-pointer"
            :class="(statusFilter || '') === tab.value
              ? 'bg-white text-slate-900 shadow-xs font-bold'
              : 'text-slate-600 hover:text-slate-900 hover:bg-slate-100/60'"
            :title="`Filter tickets by status: ${tab.label}`"
          >
            <i :class="[tab.icon, (statusFilter || '') === tab.value ? 'text-blue-600' : 'text-slate-400']" class="text-xs"></i>
            <span>{{ tab.label }}</span>
            <span
              class="text-[10px] rounded-full px-1.5 py-0.2 font-bold tracking-tight"
              :class="(statusFilter || '') === tab.value ? 'bg-blue-100 text-blue-800' : 'bg-slate-300/60 text-slate-600'"
            >
              {{ tab.count }}
            </span>
          </button>
        </div>

        <!-- Workflow Presets Popover Dropdown -->
        <div class="relative shrink-0" v-click-outside="() => (showPresetsMenu = false)">
          <button
            type="button"
            @click="showPresetsMenu = !showPresetsMenu"
            class="h-9 px-3 rounded-xl border text-xs font-semibold inline-flex items-center gap-2 transition shadow-2xs cursor-pointer"
            :class="presetFilter
              ? 'bg-indigo-50 border-indigo-300 text-indigo-900 ring-2 ring-indigo-500/20'
              : 'bg-white border-slate-300 text-slate-700 hover:bg-slate-50 hover:text-slate-900'"
            aria-haspopup="true"
            :aria-expanded="showPresetsMenu ? 'true' : 'false'"
            title="Workflow presets filter"
          >
            <i class="fa-solid fa-bolt text-amber-500 text-xs"></i>
            <span>{{ activePresetLabel ? `${activePresetLabel} (${activePresetCount ?? 0})` : 'Workflow Presets' }}</span>
            <i class="fa-solid fa-chevron-down text-[10px] text-slate-400 transition-transform" :class="{ 'rotate-180': showPresetsMenu }"></i>
          </button>

          <!-- Presets Popover Menu -->
          <div
            v-if="showPresetsMenu"
            class="absolute left-0 sm:left-auto sm:right-0 top-full mt-1.5 w-64 rounded-xl border border-slate-200 bg-white shadow-2xl py-1.5 z-50 divide-y divide-slate-100"
          >
            <div class="px-3 py-1.5 text-[11px] font-bold text-slate-400 uppercase tracking-wider flex items-center justify-between">
              <span>Workflow Presets</span>
              <span v-if="presetFilter" class="text-[10px] text-indigo-600 font-semibold lowercase">active</span>
            </div>

            <div class="py-1">
              <button
                v-for="preset in presetTabs.filter(p => p.value)"
                :key="preset.value"
                type="button"
                @click="setPresetFilter(preset.value); showPresetsMenu = false"
                class="w-full text-left px-3 py-2 text-xs flex items-center justify-between hover:bg-slate-50 transition cursor-pointer"
                :class="(presetFilter || '') === preset.value ? 'bg-indigo-50 font-bold text-indigo-900' : 'text-slate-700'"
              >
                <span class="inline-flex items-center gap-2">
                  <i class="text-xs" :class="[preset.icon, (presetFilter || '') === preset.value ? 'text-indigo-600' : 'text-slate-400']"></i>
                  <span>{{ preset.label }}</span>
                </span>
                <span
                  v-if="preset.count !== null"
                  class="text-[10px] px-1.5 py-0.5 rounded-full font-bold"
                  :class="(presetFilter || '') === preset.value ? 'bg-indigo-100 text-indigo-700' : (preset.count > 0 ? (preset.badgeClass || 'bg-slate-100 text-slate-600') : 'bg-slate-100 text-slate-500')"
                >
                  {{ preset.count }}
                </span>
              </button>
            </div>

            <div v-if="presetFilter" class="pt-1 pb-0.5 px-2">
              <button
                type="button"
                @click="setPresetFilter(''); showPresetsMenu = false"
                class="w-full text-center py-1.5 text-xs font-semibold text-rose-600 hover:bg-rose-50 rounded-lg cursor-pointer transition flex items-center justify-center gap-1.5"
              >
                <i class="fa-solid fa-xmark text-xs"></i>
                <span>Clear preset filter</span>
              </button>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- ── Option A: Unified 1-Row Filters Toolbar ─────────────────── -->
    <div class="p-4 border-b border-slate-200 bg-white relative z-10">
      <div class="flex flex-col sm:flex-row sm:items-center gap-2.5">
        <!-- Search Input (expands to fill space) -->
        <div class="relative flex-1 min-w-[200px]">
          <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none text-slate-400">
            <i class="fa-solid fa-magnifying-glass text-xs"></i>
          </div>
          <input
            type="text"
            v-model="searchText"
            @input="onSearchInput"
            placeholder="Search by Ref #, client, problem..."
            class="w-full h-9 rounded-xl border-slate-300 focus:ring-2 focus:ring-blue-500 focus:border-blue-500 pl-8 pr-8 text-xs placeholder:text-slate-400 bg-slate-50/50 hover:bg-white focus:bg-white transition"
          />
          <button
            v-if="searchText"
            type="button"
            @click="searchText = ''; fetchTickets(1)"
            class="absolute inset-y-0 right-0 pr-2.5 flex items-center text-slate-400 hover:text-slate-600 cursor-pointer"
            title="Clear search"
          >
            <i class="fa-solid fa-xmark text-xs"></i>
          </button>
        </div>

        <!-- Division Dropdown -->
        <div class="w-full sm:w-48 shrink-0">
          <div class="relative">
            <select
              v-model="divisionFilter"
              @change="fetchTickets(1)"
              class="w-full h-9 rounded-xl border-slate-300 focus:ring-2 focus:ring-blue-500 focus:border-blue-500 pl-7 pr-7 text-xs bg-slate-50/50 hover:bg-white focus:bg-white transition appearance-none cursor-pointer"
              :class="divisionFilter ? 'font-semibold text-blue-900 border-blue-300 bg-blue-50/30' : 'text-slate-700'"
            >
              <option v-for="opt in DIVISION_OPTIONS" :key="opt.value" :value="opt.value">
                {{ opt.label }}
              </option>
            </select>
            <div class="absolute inset-y-0 left-0 pl-2.5 flex items-center pointer-events-none text-slate-400">
              <i class="fa-solid fa-building text-xs"></i>
            </div>
            <div class="absolute inset-y-0 right-0 pr-2.5 flex items-center pointer-events-none text-slate-400">
              <i class="fa-solid fa-chevron-down text-[10px]"></i>
            </div>
          </div>
        </div>

        <!-- Reset Button -->
        <div class="shrink-0">
          <button
            type="button"
            @click="clearFilters"
            :disabled="!hasActiveFilters"
            class="h-9 px-3 rounded-xl border text-xs font-semibold inline-flex items-center gap-1.5 transition cursor-pointer"
            :class="hasActiveFilters
              ? 'border-rose-200 bg-rose-50 text-rose-700 hover:bg-rose-100 shadow-2xs'
              : 'border-slate-200 bg-slate-100/60 text-slate-400 cursor-not-allowed opacity-60'"
            title="Reset all active filters"
          >
            <i class="fa-solid fa-rotate-left text-xs"></i>
            <span class="hidden sm:inline">Reset</span>
          </button>
        </div>
      </div>

      <!-- Active Filter Badges Strip (only shown when filters active) -->
      <div v-if="hasActiveFilters" class="mt-3 pt-2.5 border-t border-slate-100 flex flex-wrap items-center justify-between gap-2 text-xs">
        <div class="flex flex-wrap items-center gap-1.5">
          <span class="inline-flex items-center gap-1 text-[11px] font-bold uppercase tracking-wider text-slate-400 mr-0.5">
            <i class="fa-solid fa-filter text-blue-600 text-[10px]"></i>
            <span>Active:</span>
          </span>

          <!-- Preset Chip -->
          <span v-if="presetFilter" class="inline-flex items-center gap-1.5 px-2 py-0.5 rounded-md bg-indigo-50 text-indigo-700 border border-indigo-200 text-xs font-medium shadow-2xs">
            <i class="fa-solid fa-bolt text-xs text-amber-500"></i>
            <span>Preset: <strong>{{ activePresetLabel }}</strong></span>
            <button type="button" class="text-indigo-400 hover:text-rose-600 hover:bg-indigo-100 rounded p-0.5 transition inline-flex items-center justify-center cursor-pointer" @click="setPresetFilter('')" title="Remove preset filter">
              <i class="fa-solid fa-xmark text-xs"></i>
            </button>
          </span>

          <!-- Status Chip -->
          <span v-if="statusFilter" class="inline-flex items-center gap-1.5 px-2 py-0.5 rounded-md bg-slate-100 text-slate-700 border border-slate-300 text-xs font-medium shadow-2xs">
            <span>Status: <strong>{{ statusFilter }}</strong></span>
            <button type="button" class="text-slate-400 hover:text-rose-600 hover:bg-slate-200 rounded p-0.5 transition inline-flex items-center justify-center cursor-pointer" @click="setStatusFilter('')" title="Remove status filter">
              <i class="fa-solid fa-xmark text-xs"></i>
            </button>
          </span>

          <!-- Division Chip -->
          <span v-if="divisionFilter" class="inline-flex items-center gap-1.5 px-2 py-0.5 rounded-md bg-slate-100 text-slate-700 border border-slate-300 text-xs font-medium shadow-2xs">
            <span>Division: <strong>{{ divisionLabel }}</strong></span>
            <button type="button" class="text-slate-400 hover:text-rose-600 hover:bg-slate-200 rounded p-0.5 transition inline-flex items-center justify-center cursor-pointer" @click="divisionFilter = ''; fetchTickets(1)" title="Remove division filter">
              <i class="fa-solid fa-xmark text-xs"></i>
            </button>
          </span>

          <!-- Search Chip -->
          <span v-if="searchText" class="inline-flex items-center gap-1.5 px-2 py-0.5 rounded-md bg-slate-100 text-slate-700 border border-slate-300 text-xs font-medium shadow-2xs">
            <span>Search: <strong>"{{ searchText }}"</strong></span>
            <button type="button" class="text-slate-400 hover:text-rose-600 hover:bg-slate-200 rounded p-0.5 transition inline-flex items-center justify-center cursor-pointer" @click="searchText = ''; fetchTickets(1)" title="Clear search">
              <i class="fa-solid fa-xmark text-xs"></i>
            </button>
          </span>
        </div>

        <div class="flex items-center gap-2.5 ml-auto">
          <span class="text-[11px] text-slate-400 hidden sm:inline">
            <strong>{{ pagination.total }}</strong> tickets match
          </span>
          <button
            type="button"
            @click="clearFilters"
            class="inline-flex items-center gap-1 px-2 py-0.5 rounded-md text-[11px] font-semibold text-rose-700 hover:text-rose-800 bg-rose-50 hover:bg-rose-100 border border-rose-200 transition shadow-2xs cursor-pointer"
            title="Reset all active filters"
          >
            <i class="fa-solid fa-xmark text-[10px]"></i>
            <span>Clear all</span>
          </button>
        </div>
      </div>
    </div>

    <!-- ── Main Table ──────────────────────────────────────────────────── -->
    <div class="block w-full overflow-x-auto">
      <table class="items-center w-full bg-transparent border-collapse">
        <thead>
          <tr
            class="bg-slate-50 text-slate-600 border-b border-slate-200 uppercase font-semibold tracking-wider"
            :class="isCompact ? 'text-[10px]' : 'text-[11px]'"
          >
            <th class="text-left w-12" :class="isCompact ? 'px-3 py-2' : 'px-4 py-3'">#</th>
            <th class="text-left" :class="isCompact ? 'px-3 py-2' : 'px-5 py-3'">Ticket Details</th>
            <th class="text-left" :class="isCompact ? 'px-3 py-2' : 'px-5 py-3'">Status & SLA</th>
            <th class="text-left" :class="isCompact ? 'px-3 py-2' : 'px-5 py-3'">Rating</th>
            <th class="text-right" :class="isCompact ? 'px-3 py-2' : 'px-5 py-3'">Action</th>
          </tr>
        </thead>

        <tbody v-if="tickets.length" class="divide-y divide-slate-100">
          <tr
            v-for="(row, index) in tickets"
            :key="row.client_referenceNo"
            class="hover:bg-blue-50/30 transition-colors duration-150 cursor-pointer text-xs"
            :class="{
              'bg-blue-50/70 ring-1 ring-inset ring-blue-500/30':
                selectedRow && (selectedRow.client_referenceNo === row.client_referenceNo),
            }"
            @click="openModal(row)"
          >
            <!-- Order Index -->
            <td
              class="text-slate-400 font-mono font-medium"
              :class="isCompact ? 'px-3 py-2' : 'px-4 py-3.5'"
            >
              {{ index + 1 + (pagination.current_page - 1) * perPage }}
            </td>

            <!-- Ticket Details (Ref, Client, Dates) -->
            <td :class="isCompact ? 'px-3 py-2' : 'px-5 py-3.5'">
              <div :class="isCompact ? 'space-y-0.5' : 'space-y-1'">
                <div class="flex items-center gap-2 flex-wrap">
                  <span class="font-mono font-bold text-blue-700 bg-blue-50 px-2 py-0.5 rounded-md border border-blue-200/60 text-[11px]">
                    {{ row.client_referenceNo }}
                  </span>
                  <span class="inline-flex items-center px-1.5 py-0.2 rounded text-[10px] font-bold bg-slate-100 text-slate-700">
                    {{ mapDivision(row.client_division_agency) }}
                  </span>
                </div>

                <div class="font-semibold text-slate-800 text-xs">
                  {{ row.client_name }}
                </div>

                <!-- Problem summary if available -->
                <p v-if="row.detail?.ticket_problem_that_needed_support" class="text-[11px] text-slate-500 line-clamp-1 max-w-md">
                  {{ row.detail.ticket_problem_that_needed_support }}
                </p>

                <!-- Dates -->
                <div class="flex items-center gap-3 text-[11px] text-slate-400 pt-0.5">
                  <span>
                    <i class="fa-regular fa-calendar-plus mr-1"></i>
                    {{ formatDate(row.detail?.date_requested || row.created_at) }}
                  </span>
                  <span v-if="row.detail?.date_completed" class="text-emerald-600 font-medium">
                    <i class="fa-regular fa-circle-check mr-1"></i>
                    Done: {{ formatDate(row.detail.date_completed) }}
                  </span>
                </div>
              </div>
            </td>

            <!-- Status & SLA Badges Column -->
            <td :class="isCompact ? 'px-3 py-2 align-middle' : 'px-5 py-3.5 align-middle'">
              <div class="flex flex-col gap-1.5 items-start">
                <!-- Status Pill -->
                <span
                  class="inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-bold uppercase tracking-wider border"
                  :class="{
                    'bg-sky-50 text-sky-700 border-sky-200': row.detail?.ticket_status === 'New',
                    'bg-amber-50 text-amber-700 border-amber-200': row.detail?.ticket_status === 'Pending',
                    'bg-blue-50 text-blue-700 border-blue-200': row.detail?.ticket_status === 'In Progress',
                    'bg-emerald-50 text-emerald-700 border-emerald-200': row.detail?.ticket_status === 'Completed',
                    'bg-rose-50 text-rose-700 border-rose-200': row.detail?.ticket_status === 'Cancelled',
                    'bg-slate-100 text-slate-700 border-slate-200': row.detail?.ticket_status === 'Closed'
                  }"
                >
                  {{ row.detail?.ticket_status || 'N/A' }}
                </span>

                <!-- SLA & TAT Urgency Badge -->
                <TicketSlaBadge :ticket="row" :compact="true" />
              </div>
            </td>

            <!-- Rating Column -->
            <td :class="isCompact ? 'px-3 py-2 align-middle' : 'px-5 py-3.5 align-middle'">
              <div v-if="isCompletedStatus(row)">
                <div v-if="hasRating(row)" class="space-y-0.5">
                  <div class="flex items-center text-amber-400 text-xs">
                    <i
                      v-for="i in 5"
                      :key="i"
                      class="fa-solid fa-star mr-0.5"
                      :class="i <= getRatingScore(row) ? 'text-amber-400' : 'text-slate-200'"
                    ></i>
                  </div>
                  <span class="text-[11px] font-semibold text-slate-700 block">
                    {{ getRatingLabel(row) }}
                  </span>
                </div>
                <span v-else class="text-[11px] text-slate-400 italic flex items-center gap-1">
                  <i class="fa-regular fa-clock text-[10px]"></i>
                  Awaiting rating
                </span>
              </div>
              <span v-else class="text-[11px] text-slate-400">
                —
              </span>
            </td>

            <!-- Action Column (Focused: Technician Operations) -->
            <td :class="isCompact ? 'px-3 py-2 text-right whitespace-nowrap' : 'px-5 py-3.5 text-right whitespace-nowrap'">
              <div class="inline-flex items-center gap-1.5 justify-end" @click.stop>
                <!-- 1. Primary Contextual Action Button -->
                <!-- Quick Attend Button (for Pending Tickets) -->
                <button
                  v-if="row.detail?.ticket_status === 'Pending'"
                  type="button"
                  @click.stop="attendTicket(row)"
                  :disabled="attendingRef === row.client_referenceNo"
                  class="inline-flex items-center gap-1 px-2.5 py-1 rounded-lg border border-amber-300 bg-amber-50 hover:bg-amber-100 text-amber-800 font-semibold text-xs transition shadow-2xs cursor-pointer disabled:opacity-60"
                  title="Attend this ticket now (Mark In Progress)"
                >
                  <i v-if="attendingRef === row.client_referenceNo" class="fa-solid fa-spinner animate-spin text-amber-600 text-xs"></i>
                  <i v-else class="fa-solid fa-user-check text-amber-600 text-xs"></i>
                  <span class="hidden md:inline">Attend</span>
                </button>

                <!-- Quick Complete Button (for In Progress Tickets) -->
                <button
                  v-else-if="row.detail?.ticket_status === 'In Progress'"
                  type="button"
                  @click.stop="openCompleteModal(row)"
                  class="inline-flex items-center gap-1 px-2.5 py-1 rounded-lg border border-emerald-300 bg-emerald-50 hover:bg-emerald-100 text-emerald-800 font-semibold text-xs transition shadow-2xs cursor-pointer"
                  title="Complete ticket & log actions taken"
                >
                  <i class="fa-solid fa-circle-check text-emerald-600 text-xs"></i>
                  <span class="hidden md:inline">Complete</span>
                </button>

                <!-- 2. Client Chat Button -->
                <button
                  type="button"
                  @click.stop="openModal(row, 'messages')"
                  class="p-1.5 rounded-lg border border-slate-200 text-slate-500 hover:bg-blue-50 hover:text-blue-600 hover:border-blue-200 transition cursor-pointer"
                  title="Chat with client"
                >
                  <i class="fa-regular fa-comment-dots text-xs"></i>
                </button>

                <!-- 3. More Actions ('···') Dropdown Menu (Strictly Non-Redundant) -->
                <div class="relative inline-block text-left" v-click-outside="closeRowMenu">
                  <button
                    type="button"
                    @click.stop="toggleRowMenu(row.client_referenceNo)"
                    class="p-1.5 rounded-lg border border-slate-200 text-slate-500 hover:bg-slate-100 hover:text-slate-800 transition cursor-pointer"
                    :class="{ 'bg-slate-100 border-slate-300 text-slate-800 ring-2 ring-blue-500/20': activeRowMenuId === row.client_referenceNo }"
                    title="More actions"
                  >
                    <i class="fa-solid fa-ellipsis-vertical text-xs"></i>
                  </button>

                  <!-- Dropdown Popover -->
                  <div
                    v-if="activeRowMenuId === row.client_referenceNo"
                    class="absolute right-0 w-52 bg-white rounded-xl shadow-xl border border-slate-200 py-1 z-40 divide-y divide-slate-100"
                    :class="isNearBottom(index) ? 'bottom-full mb-1.5' : 'top-full mt-1.5'"
                  >
                    <div class="py-1">
                      <button
                        type="button"
                        @click="openModal(row, 'overview'); closeRowMenu()"
                        class="w-full text-left px-3.5 py-1.5 text-xs text-slate-700 hover:bg-slate-50 flex items-center gap-2.5 transition cursor-pointer"
                      >
                        <i class="fa-regular fa-eye text-slate-400 w-3.5 text-center"></i>
                        <span>View Details</span>
                      </button>
                      <button
                        type="button"
                        @click="copyReferenceNumber(row.client_referenceNo); closeRowMenu()"
                        class="w-full text-left px-3.5 py-1.5 text-xs text-slate-700 hover:bg-slate-50 flex items-center gap-2.5 transition cursor-pointer"
                      >
                        <i class="fa-regular fa-copy text-slate-400 w-3.5 text-center"></i>
                        <span>Copy Reference No.</span>
                      </button>
                      <button
                        type="button"
                        @click="copyRatingLink(row.client_referenceNo); closeRowMenu()"
                        class="w-full text-left px-3.5 py-1.5 text-xs text-amber-700 hover:bg-amber-50 flex items-center gap-2.5 transition cursor-pointer font-medium"
                      >
                        <i class="fa-solid fa-star text-amber-500 w-3.5 text-center"></i>
                        <span>Copy Client Rating Link</span>
                      </button>
                      <button
                        type="button"
                        @click="copyTicketLink(row.client_referenceNo); closeRowMenu()"
                        class="w-full text-left px-3.5 py-1.5 text-xs text-slate-700 hover:bg-slate-50 flex items-center gap-2.5 transition cursor-pointer"
                      >
                        <i class="fa-solid fa-link text-slate-400 w-3.5 text-center"></i>
                        <span>Copy Ticket Link</span>
                      </button>
                    </div>

                    <div v-if="row.detail?.ticket_status === 'Pending' || row.detail?.ticket_status === 'In Progress'" class="py-1">
                      <button
                        v-if="row.detail?.ticket_status === 'Pending'"
                        type="button"
                        @click="attendTicket(row); closeRowMenu()"
                        class="w-full text-left px-3.5 py-1.5 text-xs text-amber-700 hover:bg-amber-50 flex items-center gap-2.5 transition font-medium cursor-pointer"
                      >
                        <i class="fa-solid fa-user-check text-amber-600 w-3.5 text-center"></i>
                        <span>Attend Ticket</span>
                      </button>
                      <button
                        v-else-if="row.detail?.ticket_status === 'In Progress'"
                        type="button"
                        @click="openCompleteModal(row); closeRowMenu()"
                        class="w-full text-left px-3.5 py-1.5 text-xs text-emerald-700 hover:bg-emerald-50 flex items-center gap-2.5 transition font-medium cursor-pointer"
                      >
                        <i class="fa-solid fa-circle-check text-emerald-600 w-3.5 text-center"></i>
                        <span>Complete Ticket</span>
                      </button>
                    </div>
                  </div>
                </div>
              </div>
            </td>
          </tr>
        </tbody>

        <!-- Empty State -->
        <tbody v-else>
          <tr>
            <td colspan="5" class="px-6 py-12 text-center">
              <div class="w-12 h-12 rounded-full bg-slate-100 text-slate-400 mx-auto flex items-center justify-center text-lg mb-2">
                <i class="fa-solid fa-inbox"></i>
              </div>
              <p class="text-xs font-semibold text-slate-700">No tickets found</p>
              <p class="text-[11px] text-slate-400 mt-0.5">
                {{ searchText || statusFilter || divisionFilter ? 'Try clearing or adjusting your active filters' : 'You have no tickets assigned in this category' }}
              </p>
              <button
                v-if="searchText || statusFilter || divisionFilter"
                @click="clearFilters"
                class="mt-3 inline-flex items-center gap-1 px-3 py-1.5 text-xs font-semibold rounded-lg bg-slate-100 hover:bg-slate-200 text-slate-700 transition cursor-pointer"
              >
                <i class="fa-solid fa-filter-circle-xmark mr-1"></i>
                Clear Filters
              </button>
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- ── Pagination Footer ───────────────────────────────────────────── -->
    <div class="px-5 py-3 border-t border-slate-200 bg-slate-50/60 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 text-xs">
      <div class="flex items-center gap-3">
        <label class="text-slate-500">Rows per page:</label>
        <select
          v-model.number="perPage"
          @change="fetchTickets(1)"
          class="rounded-lg border-slate-200 bg-white text-xs px-2.5 py-1 text-slate-700 focus:ring-1 focus:ring-blue-500"
        >
          <option :value="5">5</option>
          <option :value="10">10</option>
          <option :value="20">20</option>
          <option :value="50">50</option>
        </select>
        <span class="text-slate-500">
          Showing <span class="font-semibold text-slate-700">{{ pageFrom }}</span>–<span class="font-semibold text-slate-700">{{ pageTo }}</span> of
          <span class="font-semibold text-slate-700">{{ pagination.total }}</span> tickets
        </span>
      </div>

      <!-- Pager Links -->
      <div class="flex items-center gap-1 flex-wrap" v-if="pagination?.links?.length">
        <button
          type="button"
          class="px-2.5 py-1 rounded-lg border border-slate-200 text-xs transition disabled:opacity-40 disabled:cursor-not-allowed bg-white hover:bg-slate-100 cursor-pointer"
          :disabled="!pagination.prev_page_url"
          @click="pagination.prev_page_url && fetchTickets(pagination.prev_page_url)"
        >
          ‹ Prev
        </button>

        <button
          type="button"
          v-for="(link, i) in pagination.links.slice(1, -1)"
          :key="i"
          class="min-w-[2rem] px-2 py-1 rounded-lg border text-xs font-medium transition cursor-pointer"
          :class="{
            'bg-blue-600 text-white border-blue-600 shadow-2xs': !!link?.active,
            'bg-white text-slate-700 border-slate-200 hover:bg-slate-100': !link?.active,
            'opacity-40 cursor-not-allowed': !link?.url
          }"
          :disabled="!link?.url"
          @click="link?.url && fetchTickets(link.url)"
          v-html="link?.label ?? ''"
        ></button>

        <button
          type="button"
          class="px-2.5 py-1 rounded-lg border border-slate-200 text-xs transition disabled:opacity-40 disabled:cursor-not-allowed bg-white hover:bg-slate-100 cursor-pointer"
          :disabled="!pagination.next_page_url"
          @click="pagination.next_page_url && fetchTickets(pagination.next_page_url)"
        >
          Next ›
        </button>
      </div>
    </div>
  </div>

  <!-- ── MODAL - Create Ticket (Unified Creation Workflow) ───────────── -->
  <Modal :show="showCreateModal" @close="closeCreateModal" maxWidth="3xl">
    <ModalAction_CreateTicket
      :initial-mode="createModalMode"
      @success="closeCreateModal"
      @created="onTicketCreated"
      @close="closeCreateModal"
    />
  </Modal>

  <!-- ── MODAL / SLIDE-OVER DRAWER - Unified Ticket Details ──────────── -->
  <ModalTicketDetails
    :show="showDetailsModal"
    :ticket="selectedRow"
    :from-my-ticket="true"
    :has-prev="hasPrevTicket"
    :hasNext="hasNextTicket"
    :has-next="hasNextTicket"
    :initial-tab="modalInitialTab"
    @prev="selectPrevTicket"
    @next="selectNextTicket"
    @close="closeDetailsModal"
    @updated="onTicketUpdated"
    @attended="onTicketAttended"
    @completed="onChildCompleted"
  />

  <!-- ── MODAL - Quick Complete Ticket ──────────────────────────────── -->
  <ModalAction_CompleteTicket
    :show="showCompleteModal"
    :ticket="selectedCompleteTicket"
    @close="closeCompleteModal"
    @success="handleCompleteSuccess"
  />
</template>
