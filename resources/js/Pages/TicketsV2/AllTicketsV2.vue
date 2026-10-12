<script setup>
import { ref, watch, computed, onMounted, onUnmounted } from 'vue'
import { router, usePage } from '@inertiajs/vue3'
import Modal from '@/Components/Modal.vue'
import ModalAction_CreateTicket from '@/Pages/TicketsV2/ModalComponents/ModalAction_CreateTicket.vue'
import ModalTicketDetails from '@/Pages/TicketsV2/ModalComponents/ModalTicketDetails.vue'
import DeleteTicketButton from '@/Pages/TicketsV2/ModalComponents/ModalAction_DeleteTicket.vue'
import TicketMessageThread from '@/Components/TicketMessageThread.vue'
import { useToast } from 'vue-toastification'
import { useStaffList } from '@/Composables/useStaffList'
import { getTicketSlaStatus } from '@/Utils/slaHelper'
import {
  playNewTicketChime,
  playNewMessageChime,
  isSoundEnabled,
  setSoundEnabled,
} from '@/Utils/soundAlert'
import axios from 'axios'

const toast = useToast()
const { staffOptions } = useStaffList()

const REQUEST_TYPE_OPTIONS = [
  { value: 'Technical Support', label: 'Technical Support', icon: 'fa-solid fa-wrench' },
  { value: 'Livestream', label: 'Livestream', icon: 'fa-solid fa-video' },
  { value: 'Events', label: 'Events', icon: 'fa-solid fa-calendar-days' },
  { value: 'Others', label: 'Others', icon: 'fa-solid fa-ellipsis' },
]

// ── User Identity & Role ──────────────────────────────────────────────────────
const page = usePage()
const isAdmin = computed(() => page.props?.auth?.user?.role === 'Admin')

const props = defineProps({
  tickets: Object, // ← paginator returned by controller
  filters: Object, // ← from controller
  counts: Object,  // ← status count metrics
})

// ── State Initialization (Direct from Props, No Initial Double Fetch) ─────────
const counts = ref(props.counts ?? { All: 0, New: 0, Pending: 0, 'In Progress': 0, Completed: 0, Closed: 0, presets: {} })
const tickets = ref(Array.isArray(props.tickets?.data) ? props.tickets.data : [])
const isLoading = ref(false)

const pagination = ref({
  current_page: props.tickets?.current_page ?? 1,
  last_page: props.tickets?.last_page ?? 1,
  total: props.tickets?.total ?? 0,
  per_page: props.tickets?.per_page ?? 10,
  prev_page_url: props.tickets?.prev_page_url ?? null,
  next_page_url: props.tickets?.next_page_url ?? null,
  links: Array.isArray(props.tickets?.links) ? props.tickets.links : [],
})

const perPage = ref(
  Number(new URLSearchParams(window.location.search).get('perPage')) || (props.tickets?.per_page ?? 10)
)
const searchText = ref((new URLSearchParams(window.location.search).get('search')) ?? (props.filters?.search ?? ''))
const statusFilter = ref((new URLSearchParams(window.location.search).get('status')) ?? (props.filters?.status ?? ''))
const divisionFilter = ref((new URLSearchParams(window.location.search).get('division')) ?? (props.filters?.division ?? ''))
const yearFilter = ref(String(new URLSearchParams(window.location.search).get('year') ?? props.filters?.year ?? ''))
const presetFilter = ref((new URLSearchParams(window.location.search).get('preset')) ?? (props.filters?.preset ?? ''))
const assignedStaffFilter = ref((new URLSearchParams(window.location.search).get('assigned_staff')) ?? (props.filters?.assigned_staff ?? ''))

// ── Quick Date Range Filters ──────────────────────────────────────────────────
const datePreset = ref((new URLSearchParams(window.location.search).get('date_preset')) ?? (props.filters?.date_preset ?? ''))
const dateFrom = ref((new URLSearchParams(window.location.search).get('date_from')) ?? (props.filters?.date_from ?? ''))
const dateTo = ref((new URLSearchParams(window.location.search).get('date_to')) ?? (props.filters?.date_to ?? ''))

function setDatePreset(p) {
  if (datePreset.value === p) {
    datePreset.value = ''
    dateFrom.value = ''
    dateTo.value = ''
  } else {
    datePreset.value = p
    dateFrom.value = ''
    dateTo.value = ''
  }
  fetchTickets(1)
}

function onCustomDateChange() {
  datePreset.value = 'custom'
  fetchTickets(1)
}

// ── Real-Time Dispatch Mode & Audio Alert Monitoring ──────────────────────────
const soundAlertsActive = ref(isSoundEnabled())
const isDispatchLive = ref(true)
const isCheckingUpdates = ref(false)
const maxTicketId = ref(0)
const maxMessageId = ref(0)
const unreadIncomingCount = ref(0)
let dispatchHeartbeatTimer = null
let originalPageTitle = typeof document !== 'undefined' ? document.title : 'Tickets'
let titleFlashTimer = null

function toggleSoundAlerts() {
  soundAlertsActive.value = !soundAlertsActive.value
  setSoundEnabled(soundAlertsActive.value)
  if (soundAlertsActive.value) {
    playNewTicketChime()
    toast.success('Sound alerts enabled')
  } else {
    toast.info('Sound alerts muted')
  }
}

function testChimeSound() {
  playNewTicketChime()
  toast.info('Playing notification chime preview...')
}

function flashDocumentTitle(newCount) {
  if (typeof document === 'undefined') return
  if (titleFlashTimer) clearInterval(titleFlashTimer)

  let isFlashing = false
  titleFlashTimer = setInterval(() => {
    if (document.hidden) {
      document.title = isFlashing
        ? `(${newCount}) NEW TICKET! - All Tickets`
        : originalPageTitle
      isFlashing = !isFlashing
    } else {
      document.title = originalPageTitle
      clearInterval(titleFlashTimer)
      titleFlashTimer = null
    }
  }, 1200)
}

function onWindowFocus() {
  if (typeof document !== 'undefined') {
    document.title = originalPageTitle
  }
  if (titleFlashTimer) {
    clearInterval(titleFlashTimer)
    titleFlashTimer = null
  }
}

async function checkLiveUpdates(isFirst = false) {
  if (!isDispatchLive.value || isCheckingUpdates.value) return
  isCheckingUpdates.value = true

  try {
    const isInitial = isFirst || (maxTicketId.value === 0 && maxMessageId.value === 0)
    const params = {
      last_id: maxTicketId.value,
      last_message_id: maxMessageId.value,
      init: isInitial ? 1 : 0,
      include_counts: 1,
    }

    const { data } = await axios.get('/admin/tickets-v2/check-updates', { params })

    // On initial run, initialize IDs without playing alert
    if (isInitial) {
      maxTicketId.value = data.latest_id || 0
      maxMessageId.value = data.latest_message_id || 0
      if (data.counts) {
        counts.value = data.counts
      }
      isCheckingUpdates.value = false
      return
    }

    if (data.latest_id > maxTicketId.value) {
      maxTicketId.value = data.latest_id
    }
    if (data.latest_message_id > maxMessageId.value) {
      maxMessageId.value = data.latest_message_id
    }

    if (data.counts) {
      counts.value = data.counts
    }

    // Case 1: Newly arrived tickets
    if (data.has_new_tickets && Array.isArray(data.new_tickets) && data.new_tickets.length > 0) {
      playNewTicketChime()
      unreadIncomingCount.value += data.new_tickets.length

      const isViewingNewOrAll = !statusFilter.value || statusFilter.value === 'New'
      const isFiltering = !!(searchText.value || divisionFilter.value || yearFilter.value || presetFilter.value || dateFrom.value)

      data.new_tickets.forEach((newTicket) => {
        newTicket.isNewArrival = true
        const refNo = newTicket.client_referenceNo || `#${newTicket.client_id}`
        const clientName = newTicket.client_name || 'Client'
        toast.info(`New Ticket: ${refNo} from ${clientName}!`, { timeout: 6000 })

        if ((pagination.value.current_page ?? 1) === 1 && isViewingNewOrAll && !isFiltering) {
          const exists = tickets.value.some(
            (t) => (t.client_id ?? t.id) === (newTicket.client_id ?? newTicket.id)
          )
          if (!exists) {
            tickets.value.unshift(newTicket)
            pagination.value.total++
            if (pagination.value.per_page && tickets.value.length > pagination.value.per_page) {
              tickets.value.pop()
            }
          }
        }
      })

      flashDocumentTitle(data.new_tickets.length)
    }

    // Case 2: Newly arrived client messages
    if (data.has_new_messages && Array.isArray(data.new_messages) && data.new_messages.length > 0) {
      playNewMessageChime()
      data.new_messages.forEach((msg) => {
        const ticketRef = msg.client_referenceNo || 'Ticket'
        toast.info(`New client message on ${ticketRef}!`, { timeout: 5000 })

        const target = tickets.value.find(
          (t) => t.client_referenceNo === msg.client_referenceNo
        )
        if (target) {
          target.messages_count = (target.messages_count || 0) + 1
          target.latest_message = msg
        }
      })
    }
  } catch (err) {
    console.debug('Dispatch heartbeat check:', err?.message)
  } finally {
    isCheckingUpdates.value = false
  }
}

function startDispatchMonitoring() {
  if (dispatchHeartbeatTimer) {
    clearInterval(dispatchHeartbeatTimer)
    dispatchHeartbeatTimer = null
  }
  checkLiveUpdates(true)
  dispatchHeartbeatTimer = setInterval(() => checkLiveUpdates(false), 4000)
}

onMounted(() => {
  if (tickets.value.length > 0) {
    maxTicketId.value = Math.max(0, ...tickets.value.map((t) => Number(t.client_id ?? t.id ?? 0)))
  }
  startDispatchMonitoring()
  if (typeof window !== 'undefined') {
    window.addEventListener('focus', onWindowFocus)
  }
})

onUnmounted(() => {
  if (dispatchHeartbeatTimer) {
    clearInterval(dispatchHeartbeatTimer)
    dispatchHeartbeatTimer = null
  }
  if (titleFlashTimer) {
    clearInterval(titleFlashTimer)
    titleFlashTimer = null
  }
  if (typeof window !== 'undefined') {
    window.removeEventListener('focus', onWindowFocus)
  }
})

// ── Support Nature Pill Color Coding ──────────────────────────────────────────
function getNatureBadgeClass(nature) {
  const n = (nature || '').toLowerCase()
  if (n.includes('hardware & software') || n.includes('both')) {
    return 'bg-indigo-50 text-indigo-700 border-indigo-200'
  }
  if (n.includes('hardware')) {
    return 'bg-amber-50 text-amber-700 border-amber-200'
  }
  if (n.includes('software')) {
    return 'bg-sky-50 text-sky-700 border-sky-200'
  }
  if (n.includes('system') || n.includes('modification')) {
    return 'bg-teal-50 text-teal-700 border-teal-200'
  }
  if (n.includes('livestream')) {
    return 'bg-purple-50 text-purple-700 border-purple-200'
  }
  return 'bg-slate-100 text-slate-600 border-slate-200'
}

// ── Column Visibility & Display Options (GitHub / Linear Style) ───────────────
const isCompact = ref(typeof localStorage !== 'undefined' && localStorage.getItem('tickets_table_density') === 'compact')
const showDisplayMenu = ref(false)

function toggleDensity() {
  isCompact.value = !isCompact.value
  try {
    localStorage.setItem('tickets_table_density', isCompact.value ? 'compact' : 'comfortable')
  } catch (e) {}
}

function setDensity(mode) {
  isCompact.value = (mode === 'compact')
  try {
    localStorage.setItem('tickets_table_density', isCompact.value ? 'compact' : 'comfortable')
  } catch (e) {}
}

const defaultColumns = {
  order: true,
  ref: true,
  client: true,
  issuePreview: true,
  assignedStaff: true,
  date: true,
  status: true,
  actions: true,
}

const columnLabels = {
  order: '# Order Number',
  ref: 'Reference Number',
  client: 'Client & Division',
  issuePreview: 'Issue / Problem Snippet',
  assignedStaff: 'Assigned Staff',
  date: 'Requested Date',
  status: 'Status & SLA Badges',
  actions: 'Action Buttons',
}

function getStoredColumns() {
  if (typeof localStorage === 'undefined') return { ...defaultColumns }
  try {
    const raw = localStorage.getItem('tickets_table_columns_v2')
    if (raw) {
      const parsed = JSON.parse(raw)
      delete parsed.category
      delete parsed.messages
      return { ...defaultColumns, ...parsed }
    }
  } catch (e) {}
  return { ...defaultColumns }
}

const columns = ref(getStoredColumns())

function saveColumns() {
  try {
    localStorage.setItem('tickets_table_columns_v2', JSON.stringify(columns.value))
  } catch (e) {}
}

function toggleColumn(key) {
  columns.value[key] = !columns.value[key]
  saveColumns()
}

function showAllColumns() {
  Object.keys(columns.value).forEach((k) => {
    columns.value[k] = true
  })
  saveColumns()
}

function resetDefaultColumns() {
  columns.value = { ...defaultColumns }
  saveColumns()
}

const visibleColumnsCount = computed(() => {
  let count = 1 // Selection Checkbox is always visible
  if (columns.value.order) count++
  if (columns.value.ref || columns.value.client) count++
  if (columns.value.issuePreview) count++
  if (columns.value.assignedStaff) count++
  if (columns.value.date) count++
  if (columns.value.status) count++
  if (columns.value.actions) count++
  return count
})

// ── Message Peek & Quick Note State ──────────────────────────────────────────
const showPeekModal = ref(false)
const peekTicket = ref(null)
const peekTab = ref('messages') // 'messages' | 'notes'
const peekNoteText = ref('')
const isSavingNote = ref(false)

function openMessagePeek(ticket, tab = 'messages') {
  peekTicket.value = ticket
  peekTab.value = tab
  peekNoteText.value = ''
  showPeekModal.value = true
}

function closePeekModal() {
  showPeekModal.value = false
  peekTicket.value = null
  peekNoteText.value = ''
}

function openModalFromPeek(ticket) {
  closePeekModal()
  openModal(ticket)
}

function addQuickNoteChip(chip) {
  if (peekNoteText.value) {
    peekNoteText.value += ` • ${chip}`
  } else {
    peekNoteText.value = chip
  }
}

function savePeekNote() {
  if (!peekTicket.value || !peekNoteText.value.trim()) return

  const ticket = peekTicket.value
  const id = ticket.detail?.ticket_id || ticket.id || ticket.client_id
  const currentRemarks = ticket.detail?.ticket_remarks || ''
  const newRemarks = currentRemarks
    ? `${currentRemarks}\n[${new Date().toLocaleDateString('en-PH')}]: ${peekNoteText.value.trim()}`
    : peekNoteText.value.trim()

  isSavingNote.value = true
  router.put(`/tickets/${id}`, {
    ticket_remarks: newRemarks,
  }, {
    preserveScroll: true,
    onSuccess: () => {
      toast.success('Internal remarks updated successfully!')
      if (ticket.detail) {
        ticket.detail.ticket_remarks = newRemarks
      }
      peekNoteText.value = ''
      isSavingNote.value = false
      closePeekModal()
    },
    onError: (err) => {
      toast.error(err?.message || 'Failed to save remarks.')
      isSavingNote.value = false
    }
  })
}

// ── SLA Urgency Accent Helper ─────────────────────────────────────────────────
function getRowSlaClass(ticket) {
  const sla = getTicketSlaStatus(ticket)
  if (sla.type === 'sla' && sla.isBreached) {
    return 'border-l-4 border-l-rose-500 bg-rose-50/20'
  }
  if (sla.type === 'sla' && sla.isWarning) {
    return 'border-l-4 border-l-amber-400 bg-amber-50/10'
  }
  return 'border-l-4 border-l-transparent'
}


function setPreset(name) {
  if (presetFilter.value === name) {
    presetFilter.value = ''
  } else {
    presetFilter.value = name
    statusFilter.value = ''
  }
  fetchTickets(1)
}

function isPresetActive(name) {
  return presetFilter.value === name
}

// Keep local state in sync when Inertia reloads props
watch(
  () => props.tickets,
  (p) => {
    if (p) {
      tickets.value = Array.isArray(p.data) ? p.data : []
      pagination.value = {
        current_page: p.current_page ?? 1,
        last_page: p.last_page ?? 1,
        total: p.total ?? 0,
        per_page: p.per_page ?? perPage.value,
        prev_page_url: p.prev_page_url ?? null,
        next_page_url: p.next_page_url ?? null,
        links: Array.isArray(p.links) ? p.links : [],
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

watch(
  () => props.counts,
  (newCounts) => {
    if (newCounts) {
      counts.value = newCounts
    }
  },
  { deep: true }
)

// ── Fetch Tickets ─────────────────────────────────────────────────────────────
const buildUrl = (targetPage = 1) => {
  const params = new URLSearchParams()
  params.set('page', targetPage)
  if (perPage.value) params.set('perPage', perPage.value)

  const s = (searchText.value || '').trim()
  if (s) params.set('search', s)

  const st = (statusFilter.value || '').trim()
  if (st) params.set('status', st)

  const dv = (divisionFilter.value ?? '').toString().trim()
  if (dv) params.set('division', dv)

  const yr = (yearFilter.value || '').trim()
  if (yr) params.set('year', yr)

  const as = (assignedStaffFilter.value || '').trim()
  if (as) params.set('assigned_staff', as)

  const pr = (presetFilter.value || '').trim()
  if (pr) params.set('preset', pr)

  const dp = (datePreset.value || '').trim()
  if (dp) params.set('date_preset', dp)

  const df = (dateFrom.value || '').trim()
  if (df) params.set('date_from', df)

  const dt = (dateTo.value || '').trim()
  if (dt) params.set('date_to', dt)

  if (sortField.value) {
    params.set('sort', sortField.value)
    params.set('direction', sortDirection.value)
  }

  const set = selectedMonthsSet.value
  if (set.size === 1) {
    const [m] = Array.from(set)
    params.set('month', String(m))
  } else if (set.size > 1) {
    const q = detectQuarterFromSet(set)
    if (q) {
      params.set('quarter', q)
    } else {
      const csv = Array.from(set).sort((a, b) => a - b).join(',')
      params.set('months', csv)
    }
  }

  return `/admin/tickets-v2?${params.toString()}`
}

const fetchTickets = (target = 1) => {
  isLoading.value = true
  const url = typeof target === 'string' ? target : buildUrl(target)

  router.visit(url, {
    method: 'get',
    preserveState: true,
    preserveScroll: true,
    only: ['tickets', 'filters', 'counts'],
    onSuccess: (pageObj) => {
      const p = pageObj.props.tickets || {}
      tickets.value = Array.isArray(p.data) ? p.data : []
      pagination.value = {
        current_page: p.current_page ?? 1,
        last_page: p.last_page ?? 1,
        total: p.total ?? 0,
        per_page: p.per_page ?? perPage.value,
        prev_page_url: p.prev_page_url ?? null,
        next_page_url: p.next_page_url ?? null,
        links: Array.isArray(p.links) ? p.links : [],
      }
      if (pageObj.props.counts) {
        counts.value = pageObj.props.counts
      }
      if (tickets.value.length > 0) {
        const highest = Math.max(...tickets.value.map((t) => Number(t.client_id ?? t.id ?? 0)))
        if (highest > maxTicketId.value) {
          maxTicketId.value = highest
        }
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

// ── Live Debounced Search ─────────────────────────────────────────────────────
let searchDebounceTimer = null
watch(searchText, (newVal, oldVal) => {
  if (newVal === oldVal) return
  clearTimeout(searchDebounceTimer)
  searchDebounceTimer = setTimeout(() => {
    fetchTickets(1)
  }, 350)
})

function handleSearchEnter() {
  clearTimeout(searchDebounceTimer)
  fetchTickets(1)
}

function clearSearch() {
  clearTimeout(searchDebounceTimer)
  searchText.value = ''
  fetchTickets(1)
}

const clearFilters = () => {
  searchText.value = ''
  statusFilter.value = ''
  divisionFilter.value = ''
  yearFilter.value = ''
  assignedStaffFilter.value = ''
  presetFilter.value = ''
  datePreset.value = ''
  dateFrom.value = ''
  dateTo.value = ''
  monthsSelected.value = []
  fetchTickets(1)
}

// ── Computed Pagination Range ─────────────────────────────────────────────────
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

// ── Division & Helpers ────────────────────────────────────────────────────────
const DIVISION_OPTIONS = [
  { value: '', label: 'All Divisions' },
  { value: '1', label: 'FAD' },
  { value: '2', label: 'IRAD' },
  { value: '3', label: 'CRPD' },
  { value: '4', label: 'OD-MISPS' },
  { value: 'OTHERS', label: 'OTHERS' },
]

const DIVISION_MAP = Object.freeze({
  1: 'FAD',
  2: 'IRAD',
  3: 'CRPD',
  4: 'OD-MISPS',
})

function mapDivision(value) {
  const v = (value ?? '').toString().trim()
  if (!v) return 'OTHERS'
  const n = Number(v)
  if (Number.isFinite(n) && DIVISION_MAP[n]) return DIVISION_MAP[n]
  const up = v.toUpperCase()
  if (['FAD', 'IRAD', 'CRPD', 'OD-MISPS'].includes(up)) return up
  return 'OTHERS'
}

function formatDate(dateStr) {
  if (!dateStr) return '—'
  try {
    const d = new Date(dateStr)
    if (isNaN(d.getTime())) return String(dateStr)
    return d.toLocaleDateString('en-US', {
      year: 'numeric',
      month: 'short',
      day: 'numeric',
      hour: '2-digit',
      minute: '2-digit',
    })
  } catch {
    return String(dateStr)
  }
}

const divisionLabel = computed(() => {
  const v = (divisionFilter.value ?? '').toString()
  const found = DIVISION_OPTIONS.find((o) => String(o.value) === v)
  return found ? found.label : 'All'
})

// ── Year & Month Selectors ────────────────────────────────────────────────────
const now = new Date()
const thisYear = now.getFullYear()
const firstYear = 2020
const YEAR_OPTIONS = [{ value: '', label: 'All Years' }].concat(
  Array.from({ length: thisYear - firstYear + 1 }, (_, i) => {
    const y = thisYear - i
    return { value: String(y), label: String(y) }
  })
)

const showMonthsPanel = ref(false)
const monthsSelected = ref([])
const monthNames = [
  'January', 'February', 'March', 'April', 'May', 'June',
  'July', 'August', 'September', 'October', 'November', 'December',
]
const monthNums = Array.from({ length: 12 }, (_, i) => i + 1)
const selectedMonthsSet = computed(() => new Set(monthsSelected.value.map((v) => Number(v))))

function toggleMonthValue(n) {
  const s = new Set(monthsSelected.value)
  const k = String(n)
  if (s.has(k)) s.delete(k)
  else s.add(k)
  monthsSelected.value = Array.from(s)
}
function selectAllMonths() { monthsSelected.value = monthNums.map((n) => String(n)) }
function clearMonths() { monthsSelected.value = [] }
function selectQuarter(q) {
  const map = { 1: [1, 2, 3], 2: [4, 5, 6], 3: [7, 8, 9], 4: [10, 11, 12] }
  monthsSelected.value = (map[q] || []).map((n) => String(n))
}
function selectThisMonth() { monthsSelected.value = [String(now.getMonth() + 1)] }

const selectedMonthNames = computed(() => {
  const s = new Set(monthsSelected.value.map((v) => Number(v)))
  return monthNums.filter((n) => s.has(n)).map((n) => monthNames[n - 1])
})

const monthsSummary = computed(() => {
  if (!monthsSelected.value.length) return 'All months'
  const list = selectedMonthNames.value
  return list.length > 3 ? `${list.slice(0, 3).join(', ')} +${list.length - 3} more` : list.join(', ')
})

function detectQuarterFromSet(set) {
  const quarters = [
    { key: 'Q1', range: [1, 2, 3] },
    { key: 'Q2', range: [4, 5, 6] },
    { key: 'Q3', range: [7, 8, 9] },
    { key: 'Q4', range: [10, 11, 12] },
  ]
  for (const { key, range } of quarters) {
    if (range.every((m) => set.has(m)) && set.size === 3) return key
  }
  return null
}

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

// ── Quick Status Chips / Tabs ─────────────────────────────────────────────────
const TABS = ['All', 'New', 'Pending', 'In Progress', 'Completed', 'Closed']
const isActiveTab = (label) => {
  if (presetFilter.value) return false
  return (statusFilter.value || 'All') === label
}

function onTabClick(label) {
  statusFilter.value = label === 'All' ? '' : label
  presetFilter.value = ''
  fetchTickets(1)
}

const statusTabsConfig = computed(() => [
  { value: 'All', label: 'All', count: counts.value?.All ?? pagination.value.total ?? 0, icon: 'fa-solid fa-layer-group' },
  { value: 'New', label: 'New', count: counts.value?.New ?? 0, icon: 'fa-solid fa-envelope' },
  { value: 'Pending', label: 'Pending', count: counts.value?.Pending ?? 0, icon: 'fa-solid fa-hourglass-half' },
  { value: 'In Progress', label: 'In Progress', count: counts.value?.['In Progress'] ?? 0, icon: 'fa-solid fa-gears' },
  { value: 'Completed', label: 'Completed', count: counts.value?.Completed ?? 0, icon: 'fa-solid fa-circle-check' },
  { value: 'Closed', label: 'Closed', count: counts.value?.Closed ?? 0, icon: 'fa-solid fa-lock' },
])

// ── Status & SLA Column Differentiation Helpers ──────────────────────────────
function getSlaTagClass(ticket) {
  const sla = getTicketSlaStatus(ticket)
  if (sla.isBreached) {
    return 'bg-rose-50 text-rose-700 border-rose-300 font-semibold'
  }
  if (sla.isWarning) {
    return 'bg-amber-50 text-amber-800 border-amber-300 font-semibold'
  }
  if (sla.type === 'tat') {
    return 'bg-emerald-50 text-emerald-800 border-emerald-200'
  }
  return 'bg-slate-50 text-slate-600 border-slate-200'
}

function getSlaIcon(ticket) {
  const sla = getTicketSlaStatus(ticket)
  if (sla.isBreached) return 'fa-solid fa-triangle-exclamation text-rose-600'
  if (sla.isWarning) return 'fa-regular fa-clock text-amber-600'
  if (sla.type === 'tat') return 'fa-solid fa-check-double text-emerald-600'
  return 'fa-regular fa-clock text-slate-400'
}

function getSlaDisplayText(ticket) {
  const sla = getTicketSlaStatus(ticket)
  if (sla.type === 'tat') {
    return sla.label
  }
  if (sla.isBreached) {
    return `SLA: Overdue (${sla.formattedDuration})`
  }
  if (sla.isWarning) {
    return `SLA: Approaching (${sla.formattedDuration})`
  }
  return `SLA: On Track (${sla.formattedDuration})`
}

function getSlaTooltip(ticket) {
  const sla = getTicketSlaStatus(ticket)
  if (sla.isBreached) return 'Exceeded standard SLA target (>24h)'
  if (sla.isWarning) return 'Approaching standard SLA limit (24h-48h)'
  if (sla.type === 'tat') return 'Resolved turnaround time'
  return 'Within standard SLA turnaround'
}

// ── Consolidated Unified Modal & Slide-Over Drawer State ──────────────────────
const selectedRow = ref(null)
const selectedIndex = ref(-1)
const showDetailsModal = ref(false)
const modalInitialTab = ref('overview')

// ── Dropdown & Row Actions State ──────────────────────────────────────────────
const showCreateMenu = ref(false)
const showPresetsMenu = ref(false)
const showDateRangeMenu = ref(false)
const activeRowMenuId = ref(null)

const activePresetLabel = computed(() => {
  if (presetFilter.value === 'assigned_to_me') return 'Assigned to Me'
  if (presetFilter.value === 'overdue') return 'Overdue SLA'
  if (presetFilter.value === 'awaiting_rating') return 'Awaiting Rating'
  if (presetFilter.value === 'unassigned') return 'Unassigned'
  return null
})

const activePresetCount = computed(() => {
  if (!presetFilter.value || !counts.value?.presets) return null
  return counts.value.presets[presetFilter.value] ?? null
})

const dateRangeSummary = computed(() => {
  if (datePreset.value === 'today') return 'Today'
  if (datePreset.value === 'yesterday') return 'Yesterday'
  if (datePreset.value === 'last_7_days') return 'Last 7 Days'
  if (datePreset.value === 'this_month') return 'This Month'
  if (dateFrom.value || dateTo.value) {
    if (dateFrom.value && dateTo.value) return `${dateFrom.value} → ${dateTo.value}`
    if (dateFrom.value) return `From ${dateFrom.value}`
    if (dateTo.value) return `Until ${dateTo.value}`
  }
  if (monthsSelected.value.length || yearFilter.value) {
    const parts = []
    if (monthsSelected.value.length) parts.push(monthsSummary.value)
    if (yearFilter.value) parts.push(yearFilter.value)
    return parts.join(' • ')
  }
  return 'All Dates'
})

const isDateFilterActive = computed(() => {
  return !!(datePreset.value || dateFrom.value || dateTo.value || monthsSelected.value.length || yearFilter.value)
})

function clearDateFilters() {
  datePreset.value = ''
  dateFrom.value = ''
  dateTo.value = ''
  monthsSelected.value = []
  yearFilter.value = ''
  fetchTickets(1)
}

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
  const url = `${window.location.origin}/admin/tickets-v2?search=${encodeURIComponent(refNo)}`
  copyTextToClipboard(url, `Ticket link copied to clipboard!`)
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

function openModal(row, tab = 'overview') {
  if (row?.isNewArrival) {
    row.isNewArrival = false
  }
  selectedRow.value = row
  selectedIndex.value = tickets.value.findIndex(
    (t) => (t?.client_id ?? t?.id) === (row?.client_id ?? row?.id)
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

function onTicketDeleted({ id }) {
  const idx = tickets.value.findIndex(
    (r) => (r?.client_id ?? r?.id ?? r?.detail?.ticket_id ?? null) === id
  )
  if (idx !== -1) tickets.value.splice(idx, 1)
}

// ── Clickable Column Sorting ──────────────────────────────────────────────────
const sortField = ref(props.filters?.sort ?? '')
const sortDirection = ref(props.filters?.direction ?? 'desc')

function handleSort(field) {
  if (sortField.value === field) {
    sortDirection.value = sortDirection.value === 'asc' ? 'desc' : 'asc'
  } else {
    sortField.value = field
    sortDirection.value = (field === 'client_referenceNo' || field === 'client_name') ? 'asc' : 'desc'
  }
  fetchTickets(1)
}

// ── Multi-Row Selection & Bulk Actions ───────────────────────────────────────
const selectedIds = ref([]) // array of ticket reference codes / ticket_ids

const selectedCount = computed(() => selectedIds.value.length)

function getTicketKey(ticket) {
  return ticket?.client_referenceNo ?? ticket?.detail?.ticket_id ?? ticket?.id
}

const isAllPageSelected = computed(() => {
  if (!tickets.value.length) return false
  return tickets.value.every((t) => selectedIds.value.includes(getTicketKey(t)))
})

const isIndeterminate = computed(() => {
  return selectedCount.value > 0 && !isAllPageSelected.value
})

function toggleSelectAll() {
  if (isAllPageSelected.value) {
    const pageKeys = new Set(tickets.value.map(getTicketKey))
    selectedIds.value = selectedIds.value.filter((k) => !pageKeys.has(k))
  } else {
    const newSet = new Set(selectedIds.value)
    tickets.value.forEach((t) => newSet.add(getTicketKey(t)))
    selectedIds.value = Array.from(newSet)
  }
}

function toggleSelectRow(ticket) {
  const key = getTicketKey(ticket)
  const idx = selectedIds.value.indexOf(key)
  if (idx > -1) {
    selectedIds.value.splice(idx, 1)
  } else {
    selectedIds.value.push(key)
  }
}

function clearSelection() {
  selectedIds.value = []
}

function copyReference(refNo) {
  if (!refNo) return
  navigator.clipboard.writeText(refNo)
  toast.success(`Copied ${refNo} to clipboard!`)
}

// Bulk Assign
const showBulkAssignModal = ref(false)
const bulkAssignStaff = ref('')
const bulkAssignType = ref('Technical Support')
const isBulkAssigning = ref(false)

function openBulkAssignModal() {
  if (!selectedIds.value.length) return
  bulkAssignStaff.value = ''
  bulkAssignType.value = 'Technical Support'
  showBulkAssignModal.value = true
}

function closeBulkAssignModal() {
  showBulkAssignModal.value = false
}

function submitBulkAssign() {
  if (!bulkAssignStaff.value.trim()) {
    toast.warning('Please select a staff member to assign.')
    return
  }
  isBulkAssigning.value = true
  router.post('/admin/tickets-v2/bulk-assign', {
    ticket_ids: selectedIds.value,
    assigned_staff: bulkAssignStaff.value.trim(),
    ticket_type: bulkAssignType.value,
  }, {
    preserveScroll: true,
    onSuccess: () => {
      toast.success(`${selectedIds.value.length} tickets assigned to ${bulkAssignStaff.value}!`)
      clearSelection()
      closeBulkAssignModal()
      fetchTickets(pagination.value.current_page ?? 1)
    },
    onError: (err) => {
      toast.error(err?.message || 'Bulk assignment failed.')
    },
    onFinish: () => {
      isBulkAssigning.value = false
    },
  })
}

// Bulk Delete (Admin only)
const showBulkDeleteModal = ref(false)
const isBulkDeleting = ref(false)

function openBulkDeleteModal() {
  if (!selectedIds.value.length || !isAdmin.value) return
  showBulkDeleteModal.value = true
}

function closeBulkDeleteModal() {
  showBulkDeleteModal.value = false
}

function submitBulkDelete() {
  if (!selectedIds.value.length) return
  isBulkDeleting.value = true
  router.post('/admin/tickets-v2/bulk-delete', {
    ticket_ids: selectedIds.value,
  }, {
    preserveScroll: true,
    onSuccess: () => {
      toast.success(`${selectedIds.value.length} tickets and related logs deleted successfully!`)
      clearSelection()
      closeBulkDeleteModal()
      fetchTickets(pagination.value.current_page ?? 1)
    },
    onError: (err) => {
      toast.error(err?.message || 'Bulk delete failed.')
    },
    onFinish: () => {
      isBulkDeleting.value = false
    },
  })
}

// CSV Export
function exportCsv(selectedOnly = false) {
  const params = new URLSearchParams()
  if (selectedOnly && selectedIds.value.length) {
    params.set('ids', selectedIds.value.join(','))
  } else {
    const s = (searchText.value || '').trim()
    if (s) params.set('search', s)
    const st = (statusFilter.value || '').trim()
    if (st) params.set('status', st)
    const dv = (divisionFilter.value ?? '').toString().trim()
    if (dv) params.set('division', dv)
    const yr = (yearFilter.value || '').trim()
    if (yr) params.set('year', yr)
    const as = (assignedStaffFilter.value || '').trim()
    if (as) params.set('assigned_staff', as)
    const dp = (datePreset.value || '').trim()
    if (dp) params.set('date_preset', dp)
    const df = (dateFrom.value || '').trim()
    if (df) params.set('date_from', df)
    const dt = (dateTo.value || '').trim()
    if (dt) params.set('date_to', dt)

    const set = selectedMonthsSet.value
    if (set.size === 1) {
      const [m] = Array.from(set)
      params.set('month', String(m))
    } else if (set.size > 1) {
      const q = detectQuarterFromSet(set)
      if (q) params.set('quarter', q)
      else params.set('months', Array.from(set).sort((a, b) => a - b).join(','))
    }
  }

  if (sortField.value) {
    params.set('sort', sortField.value)
    params.set('direction', sortDirection.value)
  }

  toast.info('Downloading tickets CSV export...')
  window.location.href = `/admin/tickets-v2/export?${params.toString()}`
}

// ── Create Ticket Modal ───────────────────────────────────────────────────────
const showCreateModal = ref(false)
const createModalMode = ref('standard')

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

// ── Inline Quick Actions: Quick Assign ───────────────────────────────────────
const quickAssignTicket = ref(null)
const showQuickAssignModal = ref(false)
const quickAssignStaff = ref('')
const quickAssignType = ref('Technical Support')
const isQuickAssigning = ref(false)

function openQuickAssign(ticket) {
  quickAssignTicket.value = ticket
  quickAssignStaff.value = ticket.detail?.ticket_assigned_staff || ''
  quickAssignType.value = ticket.detail?.ticket_type || 'Technical Support'
  showQuickAssignModal.value = true
}

function closeQuickAssign() {
  showQuickAssignModal.value = false
  quickAssignTicket.value = null
  quickAssignStaff.value = ''
  quickAssignType.value = 'Technical Support'
}

function submitQuickAssign() {
  if (!quickAssignStaff.value.trim() || !quickAssignTicket.value) {
    toast.warning('Please select a staff member to assign.')
    return
  }
  isQuickAssigning.value = true
  const refNo = quickAssignTicket.value.client_referenceNo
  const staff = quickAssignStaff.value.trim()
  const type = quickAssignType.value || quickAssignTicket.value.detail?.ticket_type || 'Technical Support'

  router.post('/admin/tickets-v2/bulk-assign', {
    ticket_ids: [refNo],
    assigned_staff: staff,
    ticket_type: type,
  }, {
    preserveScroll: true,
    onSuccess: () => {
      toast.success(`Ticket ${refNo} assigned to ${staff}!`)
      closeQuickAssign()
      fetchTickets(pagination.value.current_page ?? 1)
    },
    onError: (err) => {
      toast.error(err?.message || 'Quick assignment failed.')
    },
    onFinish: () => {
      isQuickAssigning.value = false
    },
  })
}

// ── Active Filter Bar Computations ────────────────────────────────────────────
const hasActiveFilters = computed(() => {
  return !!(
    (searchText.value && searchText.value.trim()) ||
    statusFilter.value ||
    divisionFilter.value ||
    yearFilter.value ||
    assignedStaffFilter.value ||
    presetFilter.value ||
    datePreset.value ||
    dateFrom.value ||
    dateTo.value ||
    monthsSelected.value.length
  )
})

// ── GitHub 2-Column Right Sidebar State & Computations ────────────────────────
const showRightSidebar = ref(
  typeof window !== 'undefined' ? localStorage.getItem('ticket_sidebar_open') !== 'false' : true
)

function toggleRightSidebar() {
  showRightSidebar.value = !showRightSidebar.value
  if (typeof window !== 'undefined') {
    localStorage.setItem('ticket_sidebar_open', showRightSidebar.value ? 'true' : 'false')
  }
}

function getStaffName(s) {
  if (!s) return ''
  if (typeof s === 'string') return s
  return s.label || s.name || s.value || String(s)
}

function getStaffInitial(s) {
  const name = getStaffName(s).trim()
  return name ? name.charAt(0).toUpperCase() : '?'
}

function getStaffActiveCount(staffName) {
  const cleanName = getStaffName(staffName)
  if (!cleanName || !tickets.value.length) return 0
  const normalized = cleanName.toLowerCase().trim()
  return tickets.value.filter((t) => {
    const assigned = (t.detail?.ticket_assigned_staff || t.ticket_assigned_staff || '').toLowerCase().trim()
    const status = (t.detail?.ticket_status || t.ticket_status || '').toLowerCase().trim()
    return assigned.includes(normalized) && !['completed', 'closed', 'cancelled'].includes(status)
  }).length
}

function filterByStaff(staffValue) {
  if (assignedStaffFilter.value === staffValue) {
    assignedStaffFilter.value = ''
  } else {
    assignedStaffFilter.value = staffValue
  }
  fetchTickets(1)
}
const filterByTechnician = filterByStaff

const natureDistribution = computed(() => {
  if (!tickets.value.length) return []
  const countsMap = {}
  tickets.value.forEach((t) => {
    const nature = t.detail?.ticket_nature_of_support || t.ticket_nature_of_support || 'Troubleshooting'
    countsMap[nature] = (countsMap[nature] || 0) + 1
  })
  const total = tickets.value.length
  const palette = [
    { bg: 'bg-blue-500', dot: 'bg-blue-500' },
    { bg: 'bg-emerald-500', dot: 'bg-emerald-500' },
    { bg: 'bg-purple-500', dot: 'bg-purple-500' },
    { bg: 'bg-amber-500', dot: 'bg-amber-500' },
    { bg: 'bg-rose-500', dot: 'bg-rose-500' },
  ]
  return Object.entries(countsMap)
    .sort((a, b) => b[1] - a[1])
    .slice(0, 5)
    .map(([name, count], i) => ({
      name,
      count,
      pct: Math.max(4, Math.round((count / total) * 100)),
      color: palette[i % palette.length],
    }))
})
</script>

<template>
  <div class="w-full">
    <!-- ── GITHUB 2-COLUMN WORKSPACE LAYOUT ──────────────────────────────── -->
    <div class="flex flex-col xl:flex-row gap-6 items-start w-full">
      <!-- Main Left Column: Tickets Table & Management Controls -->
      <div class="flex-1 min-w-0 w-full transition-all duration-300">
        <div class="relative flex flex-col min-w-0 break-words bg-white w-full mb-6 shadow-md rounded-2xl border border-slate-200 overflow-hidden">
    <!-- ── Top Header Section ────────────────────────────────────────── -->
    <div class="flex items-center justify-between px-6 py-4 border-b border-slate-200 bg-white">
      <div>
        <h3 class="font-bold text-lg text-slate-900 tracking-tight">
          All Tickets
        </h3>
        <p class="text-xs text-slate-500 mt-0.5">Manage, triage, and track support requests across all divisions</p>
      </div>

      <div class="flex items-center gap-2 flex-wrap sm:flex-nowrap">
        <!-- Unified Creation Split Button -->
        <div class="relative inline-flex items-center shadow-xs rounded-xl" v-click-outside="() => (showCreateMenu = false)">
          <button
            type="button"
            @click="openCreateModal('standard')"
            class="inline-flex items-center gap-1.5 px-3.5 py-2 text-xs font-semibold rounded-l-xl text-white bg-blue-900 hover:bg-blue-800 transition cursor-pointer"
          >
            <i class="fa-solid fa-plus text-xs"></i>
            <span>New Request</span>
          </button>
          <button
            type="button"
            @click="showCreateMenu = !showCreateMenu"
            class="px-2.5 py-2 text-xs text-white bg-blue-950 hover:bg-blue-800 border-l border-blue-800/80 rounded-r-xl transition cursor-pointer"
            title="Additional ticket creation options"
          >
            <i class="fa-solid fa-chevron-down text-[10px] transition-transform" :class="{ 'rotate-180': showCreateMenu }"></i>
          </button>

          <!-- Dropdown Menu -->
          <div
            v-if="showCreateMenu"
            class="absolute left-0 sm:left-auto sm:right-0 top-full mt-1.5 w-56 bg-white rounded-xl shadow-xl border border-slate-200 py-1.5 z-40 divide-y divide-slate-100"
          >
            <div class="py-1">
              <button
                type="button"
                @click="openCreateModal('standard'); showCreateMenu = false"
                class="w-full text-left px-3.5 py-2 text-xs text-slate-700 hover:bg-blue-50 hover:text-blue-700 flex items-center gap-2.5 transition"
              >
                <div class="w-6 h-6 rounded-lg bg-blue-50 text-blue-600 flex items-center justify-center shrink-0">
                  <i class="fa-solid fa-file-circle-plus text-xs"></i>
                </div>
                <div>
                  <div class="font-semibold text-slate-800">Standard Request</div>
                  <div class="text-[10px] text-slate-400">Regular IT service request</div>
                </div>
              </button>
            </div>
            <div class="py-1">
              <button
                type="button"
                @click="openCreateModal('late'); showCreateMenu = false"
                class="w-full text-left px-3.5 py-2 text-xs text-slate-700 hover:bg-rose-50 hover:text-rose-700 flex items-center gap-2.5 transition"
              >
                <div class="w-6 h-6 rounded-lg bg-rose-50 text-rose-600 flex items-center justify-center shrink-0">
                  <i class="fa-solid fa-clock-rotate-left text-xs"></i>
                </div>
                <div>
                  <div class="font-semibold text-rose-700">Late / Backdated Request</div>
                  <div class="text-[10px] text-slate-400">Log prior or delayed tickets</div>
                </div>
              </button>
            </div>
          </div>
        </div>

        <!-- Refresh Table -->
        <button
          type="button"
          @click="fetchTickets(pagination.current_page)"
          class="inline-flex items-center gap-1.5 px-3 py-2 text-xs font-semibold rounded-xl border border-slate-200 text-slate-700 bg-white hover:bg-slate-50 hover:border-slate-300 transition shadow-2xs cursor-pointer"
          title="Refresh current page"
        >
          <i
            class="fa-solid fa-arrows-rotate text-blue-600 text-xs"
            :class="{ 'animate-spin': isLoading }"
          ></i>
          <span class="hidden sm:inline">Refresh</span>
        </button>

        <!-- Export CSV Button -->
        <button
          type="button"
          @click="exportCsv(false)"
          class="inline-flex items-center gap-1.5 px-3 py-2 text-xs font-semibold rounded-xl border border-emerald-200 text-emerald-800 bg-emerald-50 hover:bg-emerald-100 transition shadow-2xs cursor-pointer"
          title="Export current filtered tickets to CSV"
        >
          <i class="fa-solid fa-file-arrow-down text-emerald-600 text-xs"></i>
          <span class="hidden sm:inline">Export CSV</span>
        </button>

        <!-- GitHub / Linear Style Display Options Popover -->
        <div class="relative inline-block text-left" v-click-outside="() => (showDisplayMenu = false)">
          <button
            type="button"
            @click="showDisplayMenu = !showDisplayMenu"
            class="inline-flex items-center px-3 py-2 text-xs font-semibold rounded-xl border transition shadow-2xs gap-1.5 cursor-pointer"
            :class="showDisplayMenu
              ? 'border-blue-400 bg-blue-50 text-blue-900 ring-2 ring-blue-500/20'
              : 'border-slate-200 text-slate-700 bg-white hover:bg-slate-50 hover:border-slate-300'"
            title="Display options & column visibility (GitHub / Linear style)"
          >
            <i class="fa-solid fa-sliders text-slate-500 text-xs"></i>
            <span>Display</span>
            <span class="text-[10px] font-bold px-1.5 py-0.2 rounded bg-slate-100 text-slate-600 border border-slate-200">
              {{ visibleColumnsCount }}
            </span>
            <i class="fa-solid fa-chevron-down text-[10px] text-slate-400 transition-transform" :class="{ 'rotate-180': showDisplayMenu }"></i>
          </button>

          <!-- Dropdown Menu -->
          <div
            v-if="showDisplayMenu"
            class="absolute right-0 z-30 mt-1.5 w-64 rounded-xl bg-white p-3 shadow-2xl border border-slate-200 text-xs text-left"
          >
            <!-- Density Segmented Option -->
            <div class="pb-3 border-b border-slate-100">
              <div class="text-[10px] font-bold uppercase tracking-wider text-slate-400 mb-1.5">
                Density
              </div>
              <div class="grid grid-cols-2 gap-1 p-0.5 bg-slate-100 rounded-lg">
                <button
                  type="button"
                  @click="setDensity('comfortable')"
                  class="px-2.5 py-1 rounded-md text-xs font-semibold transition"
                  :class="!isCompact ? 'bg-white text-slate-900 shadow-xs' : 'text-slate-600 hover:text-slate-900'"
                >
                  Comfortable
                </button>
                <button
                  type="button"
                  @click="setDensity('compact')"
                  class="px-2.5 py-1 rounded-md text-xs font-semibold transition"
                  :class="isCompact ? 'bg-white text-slate-900 shadow-xs' : 'text-slate-600 hover:text-slate-900'"
                >
                  Compact
                </button>
              </div>
            </div>

            <!-- Column Visibility Toggles -->
            <div class="py-2.5 border-b border-slate-100">
              <div class="flex items-center justify-between text-[10px] font-bold uppercase tracking-wider text-slate-400 mb-1.5">
                <span>Columns</span>
                <span class="text-[9px] lowercase text-slate-400">click to toggle</span>
              </div>
              <div class="space-y-1 max-h-56 overflow-y-auto pr-1">
                <label
                  v-for="(label, key) in columnLabels"
                  :key="key"
                  class="flex items-center justify-between px-2 py-1 rounded-md hover:bg-slate-50 cursor-pointer select-none transition"
                >
                  <span class="text-slate-700 text-xs">{{ label }}</span>
                  <input
                    type="checkbox"
                    :checked="columns[key]"
                    @change="toggleColumn(key)"
                    class="rounded border-slate-300 text-blue-900 focus:ring-blue-500 h-3.5 w-3.5 cursor-pointer"
                  />
                </label>
              </div>
            </div>

            <!-- Footer Reset -->
            <div class="pt-2 flex items-center justify-between text-[11px] text-slate-500">
              <button type="button" @click="showAllColumns" class="hover:text-blue-700 font-medium">Show all</button>
              <button type="button" @click="resetDefaultColumns" class="hover:text-blue-700 font-medium">Reset default</button>
            </div>
          </div>
        </div>

        <!-- Toggle Right Sidebar (GitHub Split Rail View) -->
        <button
          type="button"
          @click="toggleRightSidebar"
          class="inline-flex items-center px-3 py-2 text-xs font-semibold rounded-lg border transition shadow-2xs gap-1.5"
          :class="showRightSidebar
            ? 'border-blue-300 bg-blue-50 text-blue-800 hover:bg-blue-100'
            : 'border-slate-300 text-slate-700 bg-white hover:bg-slate-50'"
          :title="showRightSidebar ? 'Hide right sidebar insights (Full Width mode)' : 'Show right sidebar insights'"
        >
          <svg class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor">
            <rect x="3" y="3" width="18" height="18" rx="2" stroke-width="2" />
            <line x1="15" y1="3" x2="15" y2="21" stroke-width="2" />
          </svg>
          <span class="hidden sm:inline">{{ showRightSidebar ? 'Sidebar' : 'Full Width' }}</span>
        </button>
      </div>
    </div>

    <!-- ── Option A: Segmented Status Control Bar & Workflow Presets ────── -->
    <div class="bg-slate-50/70 border-b border-slate-200 px-6 py-2.5 relative z-20">
      <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 py-0.5">
        <!-- Sleek Segmented Capsule Tabs (Linear / GitHub style) -->
        <div class="inline-flex items-center p-1 bg-slate-200/70 rounded-xl gap-0.5 overflow-x-auto shrink-0 select-none max-w-full">
          <button
            v-for="tab in statusTabsConfig"
            :key="tab.value"
            type="button"
            @click="onTabClick(tab.value)"
            class="px-3 py-1.5 text-xs font-semibold rounded-lg transition-all inline-flex items-center gap-1.5 shrink-0 cursor-pointer"
            :class="isActiveTab(tab.value)
              ? 'bg-white text-slate-900 shadow-xs font-bold'
              : 'text-slate-600 hover:text-slate-900 hover:bg-slate-100/60'"
            :title="`Filter tickets by status: ${tab.label}`"
          >
            <i :class="tab.icon" class="text-xs"></i>
            <span>{{ tab.label }}</span>
            <span
              class="text-[10px] rounded-full px-1.5 py-0.2 font-bold tracking-tight"
              :class="isActiveTab(tab.value) ? 'bg-blue-100 text-blue-800' : 'bg-slate-300/60 text-slate-600'"
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
                type="button"
                @click="setPreset('assigned_to_me'); showPresetsMenu = false"
                class="w-full text-left px-3 py-2 text-xs flex items-center justify-between hover:bg-slate-50 transition cursor-pointer"
                :class="isPresetActive('assigned_to_me') ? 'bg-indigo-50 font-bold text-indigo-900' : 'text-slate-700'"
              >
                <span class="inline-flex items-center gap-2">
                  <i class="fa-solid fa-user-check text-indigo-600 text-xs"></i>
                  <span>Assigned to Me</span>
                </span>
                <span class="text-[10px] px-1.5 py-0.5 rounded-full font-bold bg-indigo-100 text-indigo-700">
                  {{ counts?.presets?.assigned_to_me ?? 0 }}
                </span>
              </button>

              <button
                type="button"
                @click="setPreset('overdue'); showPresetsMenu = false"
                class="w-full text-left px-3 py-2 text-xs flex items-center justify-between hover:bg-slate-50 transition cursor-pointer"
                :class="isPresetActive('overdue') ? 'bg-rose-50 font-bold text-rose-900' : 'text-slate-700'"
              >
                <span class="inline-flex items-center gap-2">
                  <i class="fa-solid fa-triangle-exclamation text-rose-600 text-xs"></i>
                  <span>Overdue SLA</span>
                </span>
                <span
                  class="text-[10px] px-1.5 py-0.5 rounded-full font-bold"
                  :class="(counts?.presets?.overdue ?? 0) > 0 ? 'bg-rose-100 text-rose-700' : 'bg-slate-100 text-slate-600'"
                >
                  {{ counts?.presets?.overdue ?? 0 }}
                </span>
              </button>

              <button
                type="button"
                @click="setPreset('awaiting_rating'); showPresetsMenu = false"
                class="w-full text-left px-3 py-2 text-xs flex items-center justify-between hover:bg-slate-50 transition cursor-pointer"
                :class="isPresetActive('awaiting_rating') ? 'bg-purple-50 font-bold text-purple-900' : 'text-slate-700'"
              >
                <span class="inline-flex items-center gap-2">
                  <i class="fa-solid fa-star-half-stroke text-purple-600 text-xs"></i>
                  <span>Awaiting Rating</span>
                </span>
                <span class="text-[10px] px-1.5 py-0.5 rounded-full font-bold bg-purple-100 text-purple-700">
                  {{ counts?.presets?.awaiting_rating ?? 0 }}
                </span>
              </button>

              <button
                type="button"
                @click="setPreset('unassigned'); showPresetsMenu = false"
                class="w-full text-left px-3 py-2 text-xs flex items-center justify-between hover:bg-slate-50 transition cursor-pointer"
                :class="isPresetActive('unassigned') ? 'bg-orange-50 font-bold text-orange-900' : 'text-slate-700'"
              >
                <span class="inline-flex items-center gap-2">
                  <i class="fa-solid fa-user-slash text-orange-600 text-xs"></i>
                  <span>Unassigned</span>
                </span>
                <span
                  class="text-[10px] px-1.5 py-0.5 rounded-full font-bold"
                  :class="(counts?.presets?.unassigned ?? 0) > 0 ? 'bg-orange-100 text-orange-700 font-bold' : 'bg-slate-100 text-slate-600'"
                >
                  {{ counts?.presets?.unassigned ?? 0 }}
                </span>
              </button>
            </div>

            <div v-if="presetFilter" class="pt-1 pb-0.5 px-2">
              <button
                type="button"
                @click="setPreset(presetFilter); showPresetsMenu = false"
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

    <div class="block w-full overflow-x-auto p-6">
      <!-- ── Option A: Unified 1-Row Filters Toolbar ─────────────────── -->
      <div class="mb-5 bg-white border border-slate-200 rounded-2xl p-3 sm:p-4 shadow-xs relative z-10">
        <div class="flex flex-col md:flex-row md:items-center gap-2.5">
          <!-- Search Input (expands to fill space) -->
          <div class="relative flex-1 min-w-[220px]">
            <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none text-slate-400">
              <i class="fa-solid fa-magnifying-glass text-xs" :class="{ 'text-blue-600': searchText }"></i>
            </div>
            <input
              type="text"
              v-model="searchText"
              @keydown.enter.prevent="handleSearchEnter"
              placeholder="Search by Ref #, client, problem, or staff..."
              class="w-full h-9 rounded-xl border-slate-300 focus:ring-2 focus:ring-blue-500 focus:border-blue-500 pl-8 pr-14 text-xs placeholder:text-slate-400 bg-slate-50/50 hover:bg-white focus:bg-white transition"
            />
            <div class="absolute inset-y-0 right-0 pr-2.5 flex items-center gap-1.5">
              <i v-if="isLoading" class="fa-solid fa-spinner animate-spin text-[11px] text-blue-600"></i>
              <button
                v-if="searchText"
                type="button"
                @click="clearSearch"
                class="text-slate-400 hover:text-slate-600 hover:bg-slate-200/60 p-1 rounded-md transition cursor-pointer"
                title="Clear search"
              >
                <i class="fa-solid fa-xmark text-xs"></i>
              </button>
            </div>
          </div>

          <!-- Division Dropdown -->
          <div class="w-full md:w-44 shrink-0">
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

          <!-- Assigned Staff Dropdown -->
          <div class="w-full md:w-44 shrink-0">
            <div class="relative">
              <select
                v-model="assignedStaffFilter"
                @change="fetchTickets(1)"
                class="w-full h-9 rounded-xl border-slate-300 focus:ring-2 focus:ring-blue-500 focus:border-blue-500 pl-7 pr-7 text-xs bg-slate-50/50 hover:bg-white focus:bg-white transition appearance-none cursor-pointer"
                :class="assignedStaffFilter ? 'font-semibold text-blue-900 border-blue-300 bg-blue-50/30' : 'text-slate-700'"
              >
                <option value="">All Staff</option>
                <option v-for="st in staffOptions" :key="st" :value="st">
                  {{ st }}
                </option>
              </select>
              <div class="absolute inset-y-0 left-0 pl-2.5 flex items-center pointer-events-none text-slate-400">
                <i class="fa-solid fa-user-gear text-xs"></i>
              </div>
              <div class="absolute inset-y-0 right-0 pr-2.5 flex items-center pointer-events-none text-slate-400">
                <i class="fa-solid fa-chevron-down text-[10px]"></i>
              </div>
            </div>
          </div>

          <!-- Unified Date Range Popover Button -->
          <div class="relative w-full md:w-48 shrink-0" v-click-outside="() => (showDateRangeMenu = false)">
            <button
              type="button"
              @click="showDateRangeMenu = !showDateRangeMenu"
              class="w-full h-9 px-3 rounded-xl border text-xs font-medium inline-flex items-center justify-between gap-1.5 transition cursor-pointer"
              :class="isDateFilterActive
                ? 'bg-blue-50/50 border-blue-300 text-blue-900 font-semibold ring-1 ring-blue-500/20'
                : 'bg-slate-50/50 border-slate-300 text-slate-700 hover:bg-white hover:text-slate-900'"
              aria-haspopup="true"
              :aria-expanded="showDateRangeMenu ? 'true' : 'false'"
              title="Filter tickets by date range or horizon"
            >
              <span class="inline-flex items-center gap-1.5 truncate">
                <i class="fa-regular fa-calendar-days text-xs" :class="isDateFilterActive ? 'text-blue-600' : 'text-slate-400'"></i>
                <span class="truncate">{{ dateRangeSummary }}</span>
              </span>
              <i class="fa-solid fa-chevron-down text-[10px] text-slate-400 shrink-0 transition-transform" :class="{ 'rotate-180': showDateRangeMenu }"></i>
            </button>

            <!-- Comprehensive Date Range Dropdown Popover -->
            <div
              v-if="showDateRangeMenu"
              class="absolute left-0 sm:left-auto sm:right-0 top-full mt-1.5 w-[380px] max-w-[95vw] rounded-2xl border border-slate-200 bg-white shadow-2xl p-4 z-50"
            >
              <!-- Header -->
              <div class="flex items-center justify-between pb-3 border-b border-slate-100">
                <div class="flex items-center gap-1.5 text-xs font-bold text-slate-800">
                  <i class="fa-regular fa-calendar-days text-blue-600"></i>
                  <span>Date Filter & Horizons</span>
                </div>
                <button
                  v-if="isDateFilterActive"
                  type="button"
                  @click="clearDateFilters"
                  class="text-[11px] font-semibold text-rose-600 hover:text-rose-700 hover:underline cursor-pointer"
                >
                  Reset dates
                </button>
              </div>

              <!-- Quick Horizon Presets -->
              <div class="py-3 border-b border-slate-100">
                <label class="block text-[11px] font-bold uppercase tracking-wider text-slate-400 mb-1.5">
                  Quick Horizons
                </label>
                <div class="grid grid-cols-4 gap-1.5">
                  <button
                    type="button"
                    @click="setDatePreset('today')"
                    class="px-2 py-1.5 rounded-lg text-xs font-medium border text-center transition cursor-pointer"
                    :class="datePreset === 'today' ? 'bg-blue-900 border-blue-900 text-white font-bold' : 'bg-slate-50 hover:bg-slate-100 border-slate-200 text-slate-700'"
                  >
                    Today
                  </button>
                  <button
                    type="button"
                    @click="setDatePreset('yesterday')"
                    class="px-2 py-1.5 rounded-lg text-xs font-medium border text-center transition cursor-pointer"
                    :class="datePreset === 'yesterday' ? 'bg-blue-900 border-blue-900 text-white font-bold' : 'bg-slate-50 hover:bg-slate-100 border-slate-200 text-slate-700'"
                  >
                    Yesterday
                  </button>
                  <button
                    type="button"
                    @click="setDatePreset('last_7_days')"
                    class="px-2 py-1.5 rounded-lg text-xs font-medium border text-center transition cursor-pointer"
                    :class="datePreset === 'last_7_days' ? 'bg-blue-900 border-blue-900 text-white font-bold' : 'bg-slate-50 hover:bg-slate-100 border-slate-200 text-slate-700'"
                  >
                    Last 7 Days
                  </button>
                  <button
                    type="button"
                    @click="setDatePreset('this_month')"
                    class="px-2 py-1.5 rounded-lg text-xs font-medium border text-center transition cursor-pointer"
                    :class="datePreset === 'this_month' ? 'bg-blue-900 border-blue-900 text-white font-bold' : 'bg-slate-50 hover:bg-slate-100 border-slate-200 text-slate-700'"
                  >
                    This Month
                  </button>
                </div>
              </div>

              <!-- Custom Date Range (From / To) -->
              <div class="py-3 border-b border-slate-100">
                <label class="block text-[11px] font-bold uppercase tracking-wider text-slate-400 mb-1.5">
                  Custom Date Range
                </label>
                <div class="grid grid-cols-2 gap-2">
                  <div>
                    <span class="block text-[10px] text-slate-500 font-medium mb-0.5">From Date:</span>
                    <input
                      type="date"
                      v-model="dateFrom"
                      @change="onCustomDateChange"
                      class="w-full h-8 text-xs rounded-lg border-slate-300 focus:ring-1 focus:ring-blue-500 focus:border-blue-500 px-2 py-0 bg-slate-50/50 hover:bg-white"
                    />
                  </div>
                  <div>
                    <span class="block text-[10px] text-slate-500 font-medium mb-0.5">To Date:</span>
                    <input
                      type="date"
                      v-model="dateTo"
                      @change="onCustomDateChange"
                      class="w-full h-8 text-xs rounded-lg border-slate-300 focus:ring-1 focus:ring-blue-500 focus:border-blue-500 px-2 py-0 bg-slate-50/50 hover:bg-white"
                    />
                  </div>
                </div>
              </div>

              <!-- Months & Year Filter -->
              <div class="pt-3">
                <div class="flex items-center justify-between mb-1.5">
                  <label class="text-[11px] font-bold uppercase tracking-wider text-slate-400">
                    By Months & Year
                  </label>
                  <select
                    v-model="yearFilter"
                    @change="fetchTickets(1)"
                    class="h-6 text-[11px] font-semibold rounded-md border-slate-200 bg-slate-50 px-1 py-0 text-slate-700 cursor-pointer"
                  >
                    <option v-for="opt in YEAR_OPTIONS" :key="opt.value" :value="opt.value">{{ opt.label }}</option>
                  </select>
                </div>

                <!-- Quarter Shortcuts -->
                <div class="flex items-center gap-1 mb-2 flex-wrap">
                  <button type="button" class="text-[11px] px-1.5 py-0.5 border rounded bg-slate-50 hover:bg-slate-100 text-slate-700 cursor-pointer" @click="selectQuarter(1)">Q1</button>
                  <button type="button" class="text-[11px] px-1.5 py-0.5 border rounded bg-slate-50 hover:bg-slate-100 text-slate-700 cursor-pointer" @click="selectQuarter(2)">Q2</button>
                  <button type="button" class="text-[11px] px-1.5 py-0.5 border rounded bg-slate-50 hover:bg-slate-100 text-slate-700 cursor-pointer" @click="selectQuarter(3)">Q3</button>
                  <button type="button" class="text-[11px] px-1.5 py-0.5 border rounded bg-slate-50 hover:bg-slate-100 text-slate-700 cursor-pointer" @click="selectQuarter(4)">Q4</button>
                  <span class="flex-1"></span>
                  <button type="button" class="text-[11px] px-1.5 py-0.5 border rounded bg-slate-50 hover:bg-slate-100 text-slate-700 cursor-pointer" @click="selectAllMonths()">All</button>
                  <button type="button" class="text-[11px] px-1.5 py-0.5 border rounded bg-slate-50 hover:bg-slate-100 text-slate-700 cursor-pointer" @click="clearMonths()">Clear</button>
                </div>

                <!-- 12 Months Grid -->
                <div class="grid grid-cols-4 gap-1">
                  <button
                    v-for="n in 12"
                    :key="'pop-m-btn-' + n"
                    type="button"
                    class="text-[11px] py-1 border rounded text-center transition cursor-pointer"
                    :class="selectedMonthsSet.has(n) ? 'bg-blue-100 border-blue-400 font-bold text-blue-900' : 'bg-slate-50 hover:bg-slate-100 border-slate-200 text-slate-700'"
                    @click="toggleMonthValue(n)"
                  >
                    {{ monthNames[n - 1].substring(0, 3) }}
                  </button>
                </div>
              </div>

              <!-- Popover Footer -->
              <div class="mt-4 pt-3 border-t border-slate-100 flex items-center justify-end gap-2">
                <button
                  type="button"
                  class="text-xs font-semibold px-3 py-1.5 rounded-lg bg-blue-900 text-white hover:bg-blue-800 transition cursor-pointer"
                  @click="showDateRangeMenu = false; fetchTickets(1)"
                >
                  Apply Date Filter
                </button>
              </div>
            </div>
          </div>

          <!-- Reset All Filters Button -->
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

        <!-- Active Filter Badges Strip (only shown when filters are active) -->
        <div v-if="hasActiveFilters" class="mt-3 pt-2.5 border-t border-slate-100 flex flex-wrap items-center justify-between gap-2 text-xs">
          <div class="flex flex-wrap items-center gap-1.5">
            <span class="inline-flex items-center gap-1 text-[11px] font-bold uppercase tracking-wider text-slate-400 mr-0.5">
              <i class="fa-solid fa-filter text-blue-600 text-[10px]"></i>
              <span>Active:</span>
            </span>

            <!-- Preset Chip -->
            <span v-if="presetFilter" class="inline-flex items-center gap-1.5 px-2 py-0.5 rounded-md bg-indigo-50 text-indigo-700 border border-indigo-200 text-xs font-medium shadow-2xs">
              <i v-if="presetFilter === 'assigned_to_me'" class="fa-solid fa-user-check text-xs"></i>
              <i v-else-if="presetFilter === 'overdue'" class="fa-solid fa-triangle-exclamation text-xs"></i>
              <i v-else-if="presetFilter === 'awaiting_rating'" class="fa-solid fa-star-half-stroke text-xs"></i>
              <i v-else class="fa-solid fa-user-slash text-xs"></i>
              <span>Preset: <strong>{{ activePresetLabel }}</strong></span>
              <button type="button" class="text-indigo-400 hover:text-rose-600 hover:bg-indigo-100 rounded p-0.5 transition inline-flex items-center justify-center cursor-pointer" @click="setPreset(presetFilter)" title="Remove preset filter">
                <i class="fa-solid fa-xmark text-xs"></i>
              </button>
            </span>

            <!-- Staff Chip -->
            <span v-if="assignedStaffFilter" class="inline-flex items-center gap-1.5 px-2 py-0.5 rounded-md bg-blue-50 text-blue-700 border border-blue-200 text-xs font-medium shadow-2xs">
              <span>Staff: <strong>{{ assignedStaffFilter }}</strong></span>
              <button type="button" class="text-blue-400 hover:text-rose-600 hover:bg-blue-100 rounded p-0.5 transition inline-flex items-center justify-center cursor-pointer" @click="assignedStaffFilter = ''; fetchTickets(1)" title="Remove staff filter">
                <i class="fa-solid fa-xmark text-xs"></i>
              </button>
            </span>

            <!-- Date Range Chip -->
            <span v-if="isDateFilterActive" class="inline-flex items-center gap-1.5 px-2 py-0.5 rounded-md bg-slate-100 text-slate-700 border border-slate-300 text-xs font-medium shadow-2xs">
              <i class="fa-regular fa-calendar-days text-[11px] text-slate-500"></i>
              <span>Date: <strong>{{ dateRangeSummary }}</strong></span>
              <button type="button" class="text-slate-400 hover:text-rose-600 hover:bg-slate-200 rounded p-0.5 transition inline-flex items-center justify-center cursor-pointer" @click="clearDateFilters" title="Remove date filter">
                <i class="fa-solid fa-xmark text-xs"></i>
              </button>
            </span>

            <!-- Search Chip -->
            <span v-if="searchText" class="inline-flex items-center gap-1.5 px-2 py-0.5 rounded-md bg-slate-100 text-slate-700 border border-slate-300 text-xs font-medium shadow-2xs">
              <span>Search: <strong>"{{ searchText }}"</strong></span>
              <button type="button" class="text-slate-400 hover:text-rose-600 hover:bg-slate-200 rounded p-0.5 transition inline-flex items-center justify-center cursor-pointer" @click="clearSearch" title="Clear search">
                <i class="fa-solid fa-xmark text-xs"></i>
              </button>
            </span>

            <!-- Status Chip -->
            <span v-if="statusFilter" class="inline-flex items-center gap-1.5 px-2 py-0.5 rounded-md bg-slate-100 text-slate-700 border border-slate-300 text-xs font-medium shadow-2xs">
              <span>Status: <strong>{{ statusFilter }}</strong></span>
              <button type="button" class="text-slate-400 hover:text-rose-600 hover:bg-slate-200 rounded p-0.5 transition inline-flex items-center justify-center cursor-pointer" @click="statusFilter = ''; fetchTickets(1)" title="Remove status filter">
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

      <!-- ── Elevated Bulk Actions Floating Capsule ──────────────────────── -->
      <div
        v-if="selectedCount > 0"
        class="sticky bottom-4 z-30 mx-4 sm:mx-auto max-w-4xl bg-slate-900/95 backdrop-blur-md text-white px-5 py-3 rounded-2xl shadow-2xl border border-slate-700/70 flex flex-wrap items-center justify-between gap-3 transition-all animate-in fade-in slide-in-from-bottom-2 duration-200"
      >
        <div class="flex items-center gap-3">
          <span class="inline-flex items-center justify-center px-2.5 py-1 rounded-lg bg-blue-500/20 text-blue-300 text-xs font-bold border border-blue-500/40">
            <i class="fa-solid fa-list-check mr-1.5 text-blue-400"></i>
            {{ selectedCount }} Selected
          </span>
          <span class="text-xs text-slate-300 hidden sm:inline">Bulk actions for selected tickets:</span>
        </div>

        <div class="flex items-center gap-2 flex-wrap">
          <button
            type="button"
            @click="openBulkAssignModal"
            class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl bg-white/10 hover:bg-white/20 text-white text-xs font-semibold transition cursor-pointer border border-white/10"
          >
            <i class="fa-solid fa-user-plus text-xs text-blue-400"></i>
            <span>Assign Staff</span>
          </button>

          <button
            type="button"
            @click="exportCsv(true)"
            class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl bg-emerald-600/80 hover:bg-emerald-600 text-white text-xs font-semibold transition cursor-pointer shadow-xs border border-emerald-500/40"
          >
            <i class="fa-solid fa-file-csv text-xs"></i>
            <span>Export CSV</span>
          </button>

          <!-- Admin Bulk Delete -->
          <button
            v-if="isAdmin"
            type="button"
            @click="openBulkDeleteModal"
            class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl bg-rose-600 hover:bg-rose-700 text-white text-xs font-semibold transition cursor-pointer shadow-xs border border-rose-500/40"
            title="Permanently delete selected tickets (Admin only)"
          >
            <i class="fa-regular fa-trash-can text-xs"></i>
            <span>Delete Selected</span>
          </button>

          <button
            type="button"
            @click="clearSelection"
            class="px-3 py-1.5 rounded-xl text-xs text-slate-400 hover:text-white hover:bg-white/10 transition cursor-pointer"
          >
            Deselect
          </button>
        </div>
      </div>

      <!-- ── Table Container ─────────────────────────────────────────── -->
      <div class="rounded-xl border border-slate-200 overflow-hidden bg-white shadow-sm max-h-[calc(100vh-210px)] overflow-y-auto">
        <table class="w-full text-left border-collapse">
          <thead class="sticky top-0 z-10 bg-slate-50/95 backdrop-blur shadow-xs">
            <tr class="bg-slate-50/95 border-b border-slate-200 text-[11px] font-bold text-slate-600 uppercase tracking-wider select-none">
              <!-- Select All Checkbox -->
              <th :class="isCompact ? 'px-3 py-2 w-10 text-center' : 'px-3 py-3.5 w-10 text-center'">
                <input
                  type="checkbox"
                  :checked="isAllPageSelected"
                  :indeterminate="isIndeterminate"
                  @change="toggleSelectAll"
                  class="rounded border-slate-300 text-blue-900 focus:ring-blue-500 h-4 w-4 cursor-pointer"
                  title="Select all tickets on this page"
                />
              </th>

              <!-- Order / Number -->
              <th
                v-if="columns.order"
                :class="isCompact ? 'px-3 py-2 w-12 text-center cursor-pointer hover:bg-slate-100 transition-colors' : 'px-3 py-3.5 w-12 text-center cursor-pointer hover:bg-slate-100 transition-colors'"
                @click="handleSort('client_id')"
                title="Sort by ID"
              >
                <div class="inline-flex items-center gap-1">
                  <span>#</span>
                  <span v-if="sortField === 'client_id' || sortField === 'id'" class="text-blue-900">
                    {{ sortDirection === 'asc' ? '▲' : '▼' }}
                  </span>
                </div>
              </th>

              <!-- Details (Sort by Ref or Client) -->
              <th v-if="columns.ref || columns.client" :class="isCompact ? 'px-4 py-2' : 'px-6 py-3.5'">
                <div class="flex items-center gap-3">
                  <button
                    v-if="columns.ref"
                    type="button"
                    @click="handleSort('client_referenceNo')"
                    class="inline-flex items-center gap-1 hover:text-blue-900 transition-colors uppercase font-bold"
                  >
                    <span>Ref No.</span>
                    <span v-if="sortField === 'client_referenceNo'" class="text-blue-900 font-mono">
                      {{ sortDirection === 'asc' ? '▲' : '▼' }}
                    </span>
                    <span v-else class="text-slate-300 hover:text-slate-500">↕</span>
                  </button>

                  <span v-if="columns.ref && columns.client" class="text-slate-300">·</span>

                  <button
                    v-if="columns.client"
                    type="button"
                    @click="handleSort('client_name')"
                    class="inline-flex items-center gap-1 hover:text-blue-900 transition-colors uppercase font-bold"
                  >
                    <span>Client / Division</span>
                    <span v-if="sortField === 'client_name'" class="text-blue-900 font-mono">
                      {{ sortDirection === 'asc' ? '▲' : '▼' }}
                    </span>
                    <span v-else class="text-slate-300 hover:text-slate-500">↕</span>
                  </button>
                </div>
              </th>

              <!-- Category & Nature, Messages & Notes columns removed -->

              <!-- Assigned Staff -->
              <th v-if="columns.assignedStaff" :class="isCompact ? 'px-4 py-2' : 'px-6 py-3.5'">
                <span>Assigned Staff</span>
              </th>

              <!-- Requested Date -->
              <th
                v-if="columns.date"
                :class="isCompact ? 'px-4 py-2 cursor-pointer hover:bg-slate-100 transition-colors' : 'px-6 py-3.5 cursor-pointer hover:bg-slate-100 transition-colors'"
                @click="handleSort('date_requested')"
                title="Sort by requested date"
              >
                <div class="inline-flex items-center gap-1">
                  <span>Requested Date</span>
                  <span v-if="sortField === 'date_requested'" class="text-blue-900 font-mono">
                    {{ sortDirection === 'asc' ? '▲' : '▼' }}
                  </span>
                  <span v-else class="text-slate-300 hover:text-slate-500">↕</span>
                </div>
              </th>

              <!-- Status & SLA -->
              <th
                v-if="columns.status"
                :class="isCompact ? 'px-4 py-2 cursor-pointer hover:bg-slate-100 transition-colors' : 'px-6 py-3.5 cursor-pointer hover:bg-slate-100 transition-colors'"
                @click="handleSort('ticket_status')"
                title="Sort by status"
              >
                <div class="inline-flex items-center gap-1">
                  <span>Status & SLA</span>
                  <span v-if="sortField === 'ticket_status'" class="text-blue-900 font-mono">
                    {{ sortDirection === 'asc' ? '▲' : '▼' }}
                  </span>
                  <span v-else class="text-slate-300 hover:text-slate-500">↕</span>
                </div>
              </th>

              <th v-if="columns.actions" :class="isCompact ? 'px-4 py-2 text-right' : 'px-6 py-3.5 text-right'">
                Actions
              </th>
            </tr>
          </thead>

          <!-- ── Skeleton Loader State during Data Refetch ── -->
          <tbody v-if="isLoading" class="divide-y divide-slate-100">
            <tr v-for="i in 5" :key="'skel-' + i" class="animate-pulse">
              <td :colspan="visibleColumnsCount" class="px-6 py-4">
                <div class="flex items-center gap-4">
                  <div class="h-4 w-4 bg-slate-200 rounded"></div>
                  <div class="h-4 w-8 bg-slate-200 rounded"></div>
                  <div class="h-4 w-48 bg-slate-200 rounded"></div>
                  <div class="h-4 w-24 bg-slate-200 rounded"></div>
                  <div class="h-4 w-20 bg-slate-200 rounded"></div>
                  <div class="h-4 w-28 bg-slate-200 rounded"></div>
                  <div class="h-4 w-24 bg-slate-200 rounded"></div>
                </div>
              </td>
            </tr>
          </tbody>

          <!-- ── Empty State ── -->
          <tbody v-else-if="tickets.length === 0">
            <tr>
              <td :colspan="visibleColumnsCount" class="px-6 py-14 text-center">
                <div class="max-w-md mx-auto space-y-3">
                  <div class="w-12 h-12 mx-auto rounded-full bg-slate-100 flex items-center justify-center text-slate-400">
                    <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.172 16.172a4 4 0 015.656 0M9 10h.01M15 10h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                  </div>
                  <h3 class="text-sm font-bold text-slate-800">No tickets found</h3>
                  <p class="text-xs text-slate-500">
                    No tickets match your search criteria or active filters.
                  </p>
                  <button
                    type="button"
                    @click="clearFilters"
                    class="px-4 py-1.5 text-xs font-semibold rounded-lg border border-slate-300 bg-white hover:bg-slate-50 text-slate-700 shadow-sm"
                  >
                    Reset All Filters
                  </button>
                </div>
              </td>
            </tr>
          </tbody>

          <tbody v-else class="divide-y divide-slate-100 text-sm">
            <tr
              v-for="(ticket, index) in tickets"
              :key="getTicketKey(ticket)"
              @click="openModal(ticket)"
              class="hover:bg-slate-50/80 transition cursor-pointer"
              :class="[
                getRowSlaClass(ticket),
                ticket.isNewArrival ? 'bg-emerald-50/70 border-l-4 border-l-emerald-500' : '',
                {
                  'bg-blue-50/80 ring-2 ring-inset ring-blue-500/40': selectedRow && ((selectedRow.id && selectedRow.id === ticket.id) || (selectedRow.client_id && selectedRow.client_id === ticket.client_id)) && showDetailsModal,
                  'bg-blue-50/30': selectedIds.includes(getTicketKey(ticket))
                }
              ]"
            >
              <!-- Row Selection Checkbox -->
              <td :class="isCompact ? 'px-3 py-2 text-center' : 'px-3 py-4 text-center'" @click.stop>
                <input
                  type="checkbox"
                  :checked="selectedIds.includes(getTicketKey(ticket))"
                  @change="toggleSelectRow(ticket)"
                  class="rounded border-slate-300 text-blue-900 focus:ring-blue-500 h-4 w-4 cursor-pointer"
                  title="Select ticket"
                />
              </td>

              <!-- Order / Number -->
              <td
                v-if="columns.order"
                :class="isCompact ? 'px-3 py-2 text-center font-bold text-slate-400 text-xs' : 'px-3 py-4 text-center font-bold text-slate-400 text-xs'"
              >
                {{ pageFrom + index }}
              </td>

              <!-- Details (Ref & Client & Problem Snippet) -->
              <td v-if="columns.ref || columns.client" :class="isCompact ? 'px-4 py-2' : 'px-6 py-4'">
                <!-- Ref number -->
                <div v-if="columns.ref" class="flex items-center gap-2 flex-wrap">
                  <span class="text-xs text-slate-500">Ref:</span>
                  <button
                    type="button"
                    @click.stop="copyReference(ticket.client_referenceNo)"
                    class="font-mono font-bold text-rose-700 hover:underline inline-flex items-center gap-1 group"
                    title="Click to copy reference number"
                  >
                    <span>{{ ticket.client_referenceNo }}</span>
                    <svg class="w-3 h-3 text-slate-400 opacity-60 group-hover:opacity-100 transition-opacity" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z" />
                    </svg>
                  </button>

                  <!-- Fresh Arrival Badge -->
                  <span
                    v-if="ticket.isNewArrival"
                    class="inline-flex items-center gap-1 px-1.5 py-0.5 rounded-full text-[10px] font-bold bg-emerald-600 text-white shadow-xs"
                    title="Newly arrived ticket"
                  >
                    <span class="w-1.5 h-1.5 rounded-full bg-white animate-ping"></span>
                    NEW
                  </span>

                  <!-- Inline Staff fallback only if assignedStaff column is hidden -->
                  <span
                    v-if="!columns.assignedStaff && ticket.detail?.ticket_assigned_staff"
                    class="inline-flex items-center gap-1 px-1.5 py-0.2 rounded text-[10px] font-semibold bg-indigo-50 text-indigo-700 border border-indigo-200"
                    :title="`Assigned to: ${ticket.detail.ticket_assigned_staff}`"
                  >
                    <i class="fa-solid fa-user text-[10px]"></i>
                    <span>{{ ticket.detail.ticket_assigned_staff }}</span>
                  </span>
                </div>

                <!-- Client & Division -->
                <div v-if="columns.client" class="text-xs text-slate-500 mt-1 flex items-center gap-1.5 flex-wrap">
                  <span class="font-semibold text-blue-900 bg-blue-50 px-2 py-0.5 rounded border border-blue-100">
                    {{ mapDivision(ticket.client_division_agency) }}
                  </span>
                  <span>·</span>
                  <span class="font-bold text-slate-700">{{ ticket.client_name }}</span>
                </div>

                <!-- 1-Line Problem Snippet Preview -->
                <div
                  v-if="columns.issuePreview && ticket.detail?.ticket_problem_that_needed_support"
                  class="text-xs text-slate-500 mt-1 line-clamp-1 max-w-xl italic truncate"
                  :title="ticket.detail.ticket_problem_that_needed_support"
                >
                  “{{ ticket.detail.ticket_problem_that_needed_support }}”
                </div>
              </td>



              <!-- Assigned Staff -->
              <td v-if="columns.assignedStaff" :class="isCompact ? 'px-4 py-2' : 'px-6 py-4'" @click.stop>
                <span
                  v-if="ticket.detail?.ticket_assigned_staff"
                  class="inline-flex items-center gap-1 px-2 py-0.5 rounded-md text-[11px] font-semibold bg-indigo-50 text-indigo-700 border border-indigo-200"
                  :title="`Assigned to: ${ticket.detail.ticket_assigned_staff}`"
                >
                  <i class="fa-solid fa-user text-[10px]"></i>
                  <span class="truncate max-w-[130px]">{{ ticket.detail.ticket_assigned_staff }}</span>
                </span>
                <button
                  v-else
                  type="button"
                  @click="openQuickAssign(ticket)"
                  class="inline-flex items-center gap-1 px-2 py-0.5 rounded-md text-[11px] font-bold bg-amber-50 text-amber-700 border border-amber-200 hover:bg-amber-100 transition cursor-pointer"
                  title="No staff assigned yet. Click to assign IT staff."
                >
                  <span class="w-1.5 h-1.5 rounded-full bg-amber-500 animate-pulse"></span>
                  <span>Unassigned</span>
                </button>
              </td>

              <!-- Requested Date -->
              <td
                v-if="columns.date"
                :class="isCompact ? 'px-4 py-2 text-xs text-slate-600' : 'px-6 py-4 text-xs text-slate-600'"
              >
                <div class="font-medium text-slate-800">
                  {{ formatDate(ticket.detail?.date_requested || ticket.created_at) }}
                </div>
                <div v-if="ticket.detail?.date_completed" class="text-[11px] text-slate-400 mt-0.5">
                  Done: {{ formatDate(ticket.detail.date_completed) }}
                </div>
              </td>

              <!-- Status & SLA (Visually Distinct: Solid Status Pill + Rectangular SLA Tag) -->
              <td v-if="columns.status" :class="isCompact ? 'px-4 py-2' : 'px-6 py-4'">
                <div class="flex flex-col gap-1.5 items-start">
                  <!-- Lifecycle Status: Bold Solid Pill Badge -->
                  <span
                    class="inline-flex items-center gap-1.5 px-2.5 py-0.5 text-xs rounded-full font-bold uppercase tracking-wider text-white shadow-2xs select-none"
                    :class="{
                      'bg-blue-600': ticket.detail?.ticket_status === 'New',
                      'bg-amber-500': ticket.detail?.ticket_status === 'Pending',
                      'bg-emerald-600': ticket.detail?.ticket_status === 'In Progress',
                      'bg-purple-600': ticket.detail?.ticket_status === 'Completed',
                      'bg-slate-600': ticket.detail?.ticket_status === 'Closed' || !ticket.detail?.ticket_status,
                    }"
                  >
                    <i v-if="ticket.detail?.ticket_status === 'New'" class="fa-solid fa-envelope text-[10px]"></i>
                    <i v-else-if="ticket.detail?.ticket_status === 'Pending'" class="fa-solid fa-hourglass-half text-[10px]"></i>
                    <i v-else-if="ticket.detail?.ticket_status === 'In Progress'" class="fa-solid fa-gears text-[10px]"></i>
                    <i v-else-if="ticket.detail?.ticket_status === 'Completed'" class="fa-solid fa-circle-check text-[10px]"></i>
                    <i v-else class="fa-solid fa-lock text-[10px]"></i>
                    <span>{{ ticket.detail?.ticket_status || 'N/A' }}</span>
                  </span>

                  <!-- Turnaround / SLA Benchmark: Distinct Rectangular Micro-Tag -->
                  <div
                    class="inline-flex items-center gap-1.5 px-2 py-0.5 text-[11px] rounded-md border font-medium select-none"
                    :class="getSlaTagClass(ticket)"
                    :title="getSlaTooltip(ticket)"
                  >
                    <i :class="getSlaIcon(ticket)" class="text-[10px] shrink-0"></i>
                    <span class="whitespace-nowrap">{{ getSlaDisplayText(ticket) }}</span>
                  </div>
                </div>
              </td>

              <!-- Actions (Role-Focused: Dispatch & Management) -->
              <td v-if="columns.actions" :class="isCompact ? 'px-4 py-2 text-right' : 'px-6 py-3.5 text-right'" @click.stop>
                <div class="inline-flex items-center gap-1.5 justify-end">
                  <!-- 1. Primary Contextual Action Button (Only Staff Assignment allowed, NO status changing) -->
                  <!-- If New / Unassigned: Quick Assign to Staff -->
                  <button
                    v-if="ticket.detail?.ticket_status === 'New' || !ticket.detail?.ticket_assigned_staff"
                    type="button"
                    @click.stop="openQuickAssign(ticket)"
                    class="inline-flex items-center gap-1 px-2.5 py-1 rounded-lg border border-blue-200 bg-blue-50 hover:bg-blue-100 text-blue-800 font-semibold text-xs transition shadow-2xs cursor-pointer"
                    title="Assign to IT staff"
                  >
                    <i class="fa-solid fa-user-plus text-blue-600 text-xs"></i>
                    <span class="hidden md:inline">Assign</span>
                  </button>

                  <!-- If Pending / In Progress: Quick Reassign Staff -->
                  <button
                    v-else-if="ticket.detail?.ticket_status === 'Pending' || ticket.detail?.ticket_status === 'In Progress'"
                    type="button"
                    @click.stop="openQuickAssign(ticket)"
                    class="inline-flex items-center gap-1 px-2.5 py-1 rounded-lg border border-slate-200 bg-white hover:bg-slate-50 text-slate-700 font-semibold text-xs transition shadow-2xs cursor-pointer"
                    title="Reassign to another IT staff member"
                  >
                    <i class="fa-solid fa-user-gear text-slate-500 text-xs"></i>
                    <span class="hidden md:inline">Reassign</span>
                  </button>

                  <!-- If Completed / Closed: View Details (No status changing) -->
                  <button
                    v-else
                    type="button"
                    @click.stop="openModal(ticket, 'overview')"
                    class="inline-flex items-center gap-1 px-2.5 py-1 rounded-lg border border-slate-200 bg-white hover:bg-slate-50 text-slate-600 font-medium text-xs transition shadow-2xs cursor-pointer"
                    title="View ticket details"
                  >
                    <i class="fa-regular fa-eye text-slate-400 text-xs"></i>
                    <span class="hidden md:inline">View</span>
                  </button>

                  <!-- 2. More Actions ('···') Dropdown Menu (Strictly Non-Redundant & Non-Status) -->
                  <div class="relative inline-block text-left" v-click-outside="closeRowMenu">
                    <button
                      type="button"
                      @click.stop="toggleRowMenu(getTicketKey(ticket))"
                      class="p-1.5 rounded-lg border border-slate-200 text-slate-500 hover:bg-slate-100 hover:text-slate-800 transition cursor-pointer"
                      :class="{ 'bg-slate-100 border-slate-300 text-slate-800 ring-2 ring-blue-500/20': activeRowMenuId === getTicketKey(ticket) }"
                      title="More actions"
                    >
                      <i class="fa-solid fa-ellipsis-vertical text-xs"></i>
                    </button>

                    <!-- Dropdown Popover -->
                    <div
                      v-if="activeRowMenuId === getTicketKey(ticket)"
                      class="absolute right-0 w-48 bg-white rounded-xl shadow-xl border border-slate-200 py-1 z-40 divide-y divide-slate-100"
                      :class="isNearBottom(index) ? 'bottom-full mb-1.5' : 'top-full mt-1.5'"
                    >
                      <div class="py-1">
                        <button
                          type="button"
                          @click="openQuickAssign(ticket); closeRowMenu()"
                          class="w-full text-left px-3.5 py-1.5 text-xs text-slate-700 hover:bg-blue-50 hover:text-blue-700 flex items-center gap-2.5 transition cursor-pointer"
                        >
                          <i class="fa-solid fa-user-gear text-slate-400 w-3.5 text-center"></i>
                          <span>Assign / Reassign</span>
                        </button>
                        <button
                          type="button"
                          @click="openModal(ticket, 'overview'); closeRowMenu()"
                          class="w-full text-left px-3.5 py-1.5 text-xs text-slate-700 hover:bg-slate-50 flex items-center gap-2.5 transition cursor-pointer"
                        >
                          <i class="fa-regular fa-eye text-slate-400 w-3.5 text-center"></i>
                          <span>View Details</span>
                        </button>
                      </div>

                      <div class="py-1">
                        <button
                          type="button"
                          @click="copyReferenceNumber(ticket.client_referenceNo); closeRowMenu()"
                          class="w-full text-left px-3.5 py-1.5 text-xs text-slate-700 hover:bg-slate-50 flex items-center gap-2.5 transition cursor-pointer"
                        >
                          <i class="fa-regular fa-copy text-slate-400 w-3.5 text-center"></i>
                          <span>Copy Reference No.</span>
                        </button>
                        <button
                          type="button"
                          @click="copyTicketLink(ticket.client_referenceNo); closeRowMenu()"
                          class="w-full text-left px-3.5 py-1.5 text-xs text-slate-700 hover:bg-slate-50 flex items-center gap-2.5 transition cursor-pointer"
                        >
                          <i class="fa-solid fa-link text-slate-400 w-3.5 text-center"></i>
                          <span>Copy Ticket Link</span>
                        </button>
                      </div>

                      <div v-if="isAdmin" class="py-1">
                        <DeleteTicketButton
                          :ticket="ticket"
                          :is-admin="isAdmin"
                          @deleted="onTicketDeleted"
                        >
                          <template #trigger="{ open }">
                            <button
                              type="button"
                              @click="open(); closeRowMenu()"
                              class="w-full text-left px-3.5 py-1.5 text-xs text-rose-600 hover:bg-rose-50 flex items-center gap-2.5 transition font-medium cursor-pointer"
                            >
                              <i class="fa-regular fa-trash-can text-rose-500 w-3.5 text-center"></i>
                              <span>Delete Ticket</span>
                            </button>
                          </template>
                        </DeleteTicketButton>
                      </div>
                    </div>
                  </div>
                </div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <!-- ── Pagination Bar ──────────────────────────────────────────── -->
      <div class="mt-4 bg-white border border-slate-200 rounded-xl p-4 shadow-sm">
        <div class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between text-xs text-slate-600">
          <!-- Rows Per Page -->
          <div class="flex items-center gap-2">
            <span>Rows per page:</span>
            <select
              v-model.number="perPage"
              @change="fetchTickets(1)"
              class="text-xs rounded-lg border-slate-300 focus:ring-2 focus:ring-blue-500 focus:border-blue-500 px-2.5 py-1.5 bg-white"
            >
              <option :value="5">5</option>
              <option :value="10">10</option>
              <option :value="20">20</option>
              <option :value="50">50</option>
            </select>
          </div>

          <!-- Page Summary -->
          <div>
            <span>Page <strong>{{ pagination.current_page }}</strong> of {{ pagination.last_page }}</span>
            <span v-if="pagination.total" class="ml-2 text-slate-400">
              • Showing {{ pageFrom }}–{{ pageTo }} of {{ pagination.total }} tickets
            </span>
          </div>

          <!-- Pagination Navigation Controls -->
          <div class="flex items-center gap-1" v-if="pagination?.links?.length">
            <button
              type="button"
              class="inline-flex items-center gap-1 px-3 py-1.5 rounded-lg border border-slate-300 transition disabled:opacity-40 disabled:cursor-not-allowed hover:bg-slate-50"
              :disabled="!pagination.prev_page_url"
              @click="pagination.prev_page_url && fetchTickets(pagination.prev_page_url)"
            >
              Prev
            </button>

            <button
              type="button"
              v-for="(link, i) in pagination.links"
              :key="i"
              class="min-w-[2rem] px-2.5 py-1.5 rounded-lg border text-xs transition"
              :class="{
                'bg-blue-900 text-white border-blue-900 font-bold': !!link?.active,
                'border-slate-300 text-slate-700 hover:bg-slate-50': !link?.active,
                'opacity-40 cursor-not-allowed': !link?.url
              }"
              :disabled="!link?.url"
              @click="link?.url && fetchTickets(link.url)"
              v-html="link?.label ?? ''"
            ></button>

            <button
              type="button"
              class="inline-flex items-center gap-1 px-3 py-1.5 rounded-lg border border-slate-300 transition disabled:opacity-40 disabled:cursor-not-allowed hover:bg-slate-50"
              :disabled="!pagination.next_page_url"
              @click="pagination.next_page_url && fetchTickets(pagination.next_page_url)"
            >
              Next
            </button>
          </div>
        </div>
      </div>
    </div>
    </div>
    </div>

    <!-- ── RIGHT COLUMN: GITHUB "ABOUT & QUEUE INSIGHTS" RAIL ──────────────── -->
    <aside
      v-if="showRightSidebar"
      class="w-full xl:w-80 shrink-0 space-y-6"
    >
      <!-- 1. ABOUT & QUEUE HEALTH -->
      <div class="bg-white rounded-2xl border border-slate-200 p-5 shadow-xs">
        <div class="flex items-center justify-between pb-3 border-b border-slate-100">
          <div class="flex items-center gap-2">
            <span class="text-xs font-bold uppercase tracking-wider text-slate-800">About</span>
          </div>
          <button
            type="button"
            @click="toggleRightSidebar"
            class="text-slate-400 hover:text-slate-600 p-1 rounded-md transition"
            title="Collapse sidebar"
          >
            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
            </svg>
          </button>
        </div>

        <p class="text-xs text-slate-500 mt-3 leading-relaxed">
          Central support queue triage, IT staff allocation, and SLA compliance monitoring.
        </p>

        <!-- Key Metrics List (GitHub repo-style metadata) -->
        <div class="mt-4 space-y-2.5 text-xs">
          <!-- Total Volume -->
          <div class="flex items-center justify-between text-slate-600">
            <span class="flex items-center gap-2">
              <svg class="w-4 h-4 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10" />
              </svg>
              <span>Queue Volume</span>
            </span>
            <span class="font-bold text-slate-900">{{ counts.All || pagination.total || tickets.length }}</span>
          </div>

          <!-- Unassigned -->
          <button
            type="button"
            @click="setPreset('unassigned')"
            class="w-full flex items-center justify-between text-slate-600 hover:text-amber-700 transition group text-left cursor-pointer"
          >
            <span class="flex items-center gap-2">
              <svg class="w-4 h-4 text-amber-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
              </svg>
              <span class="group-hover:underline">Unassigned</span>
            </span>
            <span class="font-bold px-2 py-0.5 rounded-full text-[11px] bg-amber-50 text-amber-700 border border-amber-200">
              {{ counts.presets?.unassigned ?? counts.New ?? 0 }}
            </span>
          </button>

          <!-- SLA Overdue -->
          <button
            type="button"
            @click="setPreset('overdue')"
            class="w-full flex items-center justify-between text-slate-600 hover:text-rose-700 transition group text-left cursor-pointer"
          >
            <span class="flex items-center gap-2">
              <svg class="w-4 h-4 text-rose-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
              </svg>
              <span class="group-hover:underline">SLA Overdue</span>
            </span>
            <span class="font-bold px-2 py-0.5 rounded-full text-[11px] bg-rose-50 text-rose-700 border border-rose-200">
              {{ counts.presets?.overdue ?? 0 }}
            </span>
          </button>

          <!-- Awaiting Rating -->
          <button
            type="button"
            @click="setPreset('awaiting_rating')"
            class="w-full flex items-center justify-between text-slate-600 hover:text-indigo-700 transition group text-left cursor-pointer"
          >
            <span class="flex items-center gap-2">
              <svg class="w-4 h-4 text-indigo-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11.049 2.927c.3-.921 1.603-.921 1.902 0l1.519 4.674a1 1 0 00.95.69h4.915c.969 0 1.371 1.24.588 1.81l-3.976 2.888a1 1 0 00-.363 1.118l1.518 4.674c.3.922-.755 1.688-1.538 1.118l-3.976-2.888a1 1 0 00-1.176 0l-3.976 2.888c-.783.57-1.838-.197-1.538-1.118l1.518-4.674a1 1 0 00-.363-1.118l-3.976-2.888c-.784-.57-.38-1.81.588-1.81h4.914a1 1 0 00.951-.69l1.519-4.674z" />
              </svg>
              <span class="group-hover:underline">Awaiting Rating</span>
            </span>
            <span class="font-bold px-2 py-0.5 rounded-full text-[11px] bg-indigo-50 text-indigo-700 border border-indigo-200">
              {{ counts.presets?.awaiting_rating ?? 0 }}
            </span>
          </button>

          <!-- In Progress -->
          <div class="flex items-center justify-between text-slate-600 pt-1">
            <span class="flex items-center gap-2">
              <span class="w-2 h-2 rounded-full bg-blue-500"></span>
              <span>In Progress</span>
            </span>
            <span class="font-bold text-slate-900">{{ counts['In Progress'] ?? 0 }}</span>
          </div>
        </div>
      </div>

      <!-- 2. IT STAFF ON-DUTY (GITHUB CONTRIBUTORS STYLE) -->
      <div class="bg-white rounded-2xl border border-slate-200 p-5 shadow-xs">
        <div class="flex items-center justify-between pb-3 border-b border-slate-100">
          <div class="flex items-center gap-2">
            <span class="text-xs font-bold uppercase tracking-wider text-slate-800">On-Duty IT Staff</span>
            <span class="text-[10px] font-bold bg-slate-100 text-slate-600 px-2 py-0.5 rounded-full border border-slate-200">
              {{ staffOptions.length }}
            </span>
          </div>
          <button
            v-if="assignedStaffFilter"
            type="button"
            @click="filterByStaff('')"
            class="text-[11px] font-semibold text-blue-600 hover:underline cursor-pointer"
          >
            Clear Filter
          </button>
        </div>

        <!-- Staff List -->
        <div class="mt-3 divide-y divide-slate-100">
          <button
            v-for="staff in staffOptions"
            :key="getStaffName(staff)"
            type="button"
            @click="filterByStaff(getStaffName(staff))"
            class="w-full py-2.5 flex items-center justify-between hover:bg-slate-50 px-2 -mx-2 rounded-lg transition text-left group cursor-pointer"
            :class="{ 'bg-blue-50/70 text-blue-900': assignedStaffFilter === getStaffName(staff) }"
          >
            <div class="flex items-center gap-2.5 min-w-0">
              <!-- Avatar Circle -->
              <div class="relative shrink-0">
                <div class="w-7 h-7 rounded-full bg-gradient-to-tr from-slate-700 to-slate-900 text-white flex items-center justify-center font-bold text-[10px] shadow-xs">
                  {{ getStaffInitial(staff) }}
                </div>
                <span class="absolute -bottom-0.5 -right-0.5 w-2 h-2 rounded-full bg-emerald-500 ring-2 ring-white"></span>
              </div>
              <div class="truncate">
                <p class="text-xs font-semibold text-slate-800 group-hover:text-blue-600 truncate" :class="{ 'text-blue-900': assignedStaffFilter === getStaffName(staff) }">
                  {{ getStaffName(staff) }}
                </p>
                <p class="text-[10px] text-slate-400">Technical Support</p>
              </div>
            </div>

            <!-- Open workload badge -->
            <span
              class="shrink-0 text-[11px] font-semibold px-2 py-0.5 rounded-full border"
              :class="getStaffActiveCount(staff) > 0
                ? 'bg-blue-50 text-blue-700 border-blue-200'
                : 'bg-slate-50 text-slate-500 border-slate-200'"
            >
              {{ getStaffActiveCount(staff) }} assigned ticket
            </span>
          </button>
        </div>
      </div>

      <!-- 3. SUPPORT NATURE DISTRIBUTION (GITHUB LANGUAGES PROGRESS BAR STYLE) -->
      <div v-if="natureDistribution.length" class="bg-white rounded-2xl border border-slate-200 p-5 shadow-xs">
        <div class="pb-3 border-b border-slate-100">
          <span class="text-xs font-bold uppercase tracking-wider text-slate-800">Support Breakdown</span>
        </div>

        <!-- Continuous Progress Bar -->
        <div class="mt-3 w-full h-2 rounded-full overflow-hidden flex bg-slate-100">
          <div
            v-for="item in natureDistribution"
            :key="item.name"
            :class="item.color.bg"
            :style="{ width: item.pct + '%' }"
            :title="`${item.name}: ${item.count} tickets (${item.pct}%)`"
          ></div>
        </div>

        <!-- Legend Items -->
        <div class="mt-3 space-y-1.5">
          <div
            v-for="item in natureDistribution"
            :key="item.name"
            class="flex items-center justify-between text-xs"
          >
            <div class="flex items-center gap-2 truncate">
              <span class="w-2 h-2 rounded-full shrink-0" :class="item.color.dot"></span>
              <span class="text-slate-700 truncate">{{ item.name }}</span>
            </div>
            <span class="text-slate-400 font-medium shrink-0">{{ item.pct }}% ({{ item.count }})</span>
          </div>
        </div>
      </div>

      <!-- 4. SLA BENCHMARKS & GUIDELINES -->
      <div class="bg-white rounded-2xl border border-slate-200 p-5 shadow-xs">
        <div class="pb-3 border-b border-slate-100">
          <span class="text-xs font-bold uppercase tracking-wider text-slate-800">SLA Policy</span>
        </div>

        <div class="mt-3 space-y-2.5 text-xs text-slate-600">
          <div class="flex items-start gap-2">
            <i class="fa-solid fa-bolt text-emerald-600 mt-0.5"></i>
            <div>
              <p class="font-semibold text-slate-800">24-Hour Response</p>
              <p class="text-[11px] text-slate-500">Tickets must be assigned and attended within 24 hours.</p>
            </div>
          </div>
          <div class="flex items-start gap-2">
            <i class="fa-regular fa-clock text-blue-600 mt-0.5"></i>
            <div>
              <p class="font-semibold text-slate-800">48-Hour Resolution</p>
              <p class="text-[11px] text-slate-500">Standard issues completed within 48 hours.</p>
            </div>
          </div>
          <div class="flex items-start gap-2">
            <i class="fa-solid fa-triangle-exclamation text-rose-600 mt-0.5"></i>
            <div>
              <p class="font-semibold text-slate-800">Overdue Escalation</p>
              <p class="text-[11px] text-slate-500">Overdue tickets highlight red for urgent attention.</p>
            </div>
          </div>
        </div>
      </div>

      <!-- 5. QUICK ACTIONS & PORTALS (GITHUB SUGGESTED WORKFLOWS STYLE) -->
      <div class="bg-gradient-to-br from-slate-900 to-slate-800 text-white rounded-2xl p-5 shadow-xs">
        <div class="flex items-center gap-2">
          <div class="w-6 h-6 rounded-md bg-white/10 flex items-center justify-center text-xs">
            <i class="fa-solid fa-globe"></i>
          </div>
          <h4 class="text-xs font-bold uppercase tracking-wider text-slate-200">Client Portal</h4>
        </div>

        <p class="text-xs text-slate-300 mt-2 leading-relaxed">
          Open the public client ticket tracking and request submission interface.
        </p>

        <a
          href="/client"
          target="_blank"
          rel="noopener noreferrer"
          class="mt-3 inline-flex items-center justify-center gap-1.5 w-full px-3 py-2 text-xs font-semibold rounded-xl bg-white text-slate-900 hover:bg-slate-100 transition shadow-xs"
        >
          <span>Open Client Tracker</span>
          <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14" />
          </svg>
        </a>
      </div>
    </aside>
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
    :has-prev="hasPrevTicket"
    :has-next="hasNextTicket"
    :initial-tab="modalInitialTab"
    :allow-status-change="false"
    @prev="selectPrevTicket"
    @next="selectNextTicket"
    @close="closeDetailsModal"
    @updated="onTicketUpdated"
  />

  <!-- ── MODAL - Bulk Assign Staff ────────────────────────────────────── -->
  <Modal :show="showBulkAssignModal" @close="closeBulkAssignModal" maxWidth="lg">
    <div class="p-6 bg-white rounded-2xl">
      <!-- Modal Header -->
      <div class="flex items-center justify-between pb-3.5 border-b border-slate-100">
        <div class="flex items-center gap-2.5">
          <div class="w-9 h-9 rounded-xl bg-blue-100 text-blue-900 flex items-center justify-center font-bold text-sm shadow-xs shrink-0">
            <i class="fa-solid fa-users-gear"></i>
          </div>
          <div>
            <div class="flex items-center gap-2">
              <h3 class="text-base font-bold text-slate-900">Bulk Assign IT Staff</h3>
              <span class="text-xs font-bold text-blue-900 bg-blue-50 border border-blue-200 px-2 py-0.5 rounded-full">
                {{ selectedCount }} Selected
              </span>
            </div>
            <p class="text-xs text-slate-500 mt-0.5">
              Simultaneously allocate tickets to a designated IT staff member
            </p>
          </div>
        </div>
        <button
          type="button"
          @click="closeBulkAssignModal"
          class="text-slate-400 hover:text-slate-600 p-1.5 rounded-lg hover:bg-slate-100 transition cursor-pointer"
          title="Close modal"
        >
          <i class="fa-solid fa-xmark text-sm"></i>
        </button>
      </div>

      <!-- Info Banner -->
      <div class="my-4 p-3 bg-blue-50/70 rounded-xl border border-blue-100 flex items-start gap-2.5 text-xs text-blue-950">
        <i class="fa-solid fa-circle-info text-blue-600 mt-0.5 shrink-0 text-sm"></i>
        <div class="leading-relaxed">
          You are assigning <strong class="font-bold text-blue-900">{{ selectedCount }} selected tickets</strong>. Any selected tickets currently in <span class="font-semibold text-blue-900">New</span> status will automatically transition to <span class="font-semibold text-amber-800">Pending</span> status upon assignment.
        </div>
      </div>

      <!-- Form Inputs -->
      <div class="space-y-4 py-1 text-xs">
        <div>
          <div class="flex items-center justify-between mb-1.5">
            <label class="block text-xs font-bold text-slate-800">
              <i class="fa-solid fa-user-check text-blue-900 mr-1.5"></i>Assign To IT Staff <span class="text-rose-500">*</span>
            </label>
            <span class="text-[11px] text-slate-400 font-medium">
              {{ staffOptions.length }} on-duty members
            </span>
          </div>
          <select
            v-model="bulkAssignStaff"
            class="w-full h-10 pl-3 pr-8 rounded-xl border border-slate-300 focus:ring-2 focus:ring-blue-900 focus:border-blue-900 text-xs bg-white text-slate-800 shadow-2xs font-medium cursor-pointer"
          >
            <option value="" disabled>-- Select IT Staff Member --</option>
            <option v-for="st in staffOptions" :key="st" :value="st">
              {{ st }} • ({{ getStaffActiveCount(st) }} assigned {{ getStaffActiveCount(st) === 1 ? 'ticket' : 'tickets' }})
            </option>
          </select>
          <p class="text-[11px] text-slate-400 mt-1 flex items-center gap-1.5">
            <i class="fa-solid fa-circle-info text-[10px] text-slate-400"></i>
            <span>Parentheses show real-time assigned ticket count for each staff member.</span>
          </p>
        </div>

        <div>
          <label class="block text-xs font-bold text-slate-800 mb-1.5">
            <i class="fa-solid fa-tag text-blue-900 mr-1.5"></i>Request Type Classification
          </label>
          <div class="grid grid-cols-2 gap-2 mt-1">
            <button
              v-for="opt in REQUEST_TYPE_OPTIONS"
              :key="opt.value"
              type="button"
              @click="bulkAssignType = opt.value"
              class="flex items-center gap-2 p-2.5 rounded-xl border text-xs font-semibold transition cursor-pointer text-left"
              :class="bulkAssignType === opt.value
                ? 'bg-blue-50 border-blue-900 text-blue-900 ring-1 ring-blue-900 shadow-2xs'
                : 'bg-white border-slate-200 text-slate-700 hover:bg-slate-50 hover:border-slate-300'"
            >
              <div
                class="w-6 h-6 rounded-lg flex items-center justify-center shrink-0 text-xs"
                :class="bulkAssignType === opt.value ? 'bg-blue-900 text-white' : 'bg-slate-100 text-slate-600'"
              >
                <i :class="opt.icon"></i>
              </div>
              <span class="truncate">{{ opt.label }}</span>
            </button>
          </div>
        </div>
      </div>

      <!-- Footer -->
      <div class="flex items-center justify-end gap-2.5 pt-4 mt-3 border-t border-slate-100">
        <button
          type="button"
          @click="closeBulkAssignModal"
          class="px-4 py-2 text-xs font-semibold rounded-xl border border-slate-200 text-slate-700 hover:bg-slate-50 transition cursor-pointer"
        >
          Cancel
        </button>
        <button
          type="button"
          :disabled="isBulkAssigning || !bulkAssignStaff"
          @click="submitBulkAssign"
          class="inline-flex items-center gap-2 px-4 py-2 text-xs font-bold uppercase rounded-xl shadow-sm text-white bg-blue-900 hover:bg-blue-800 disabled:opacity-50 transition cursor-pointer"
        >
          <i v-if="isBulkAssigning" class="fa-solid fa-spinner animate-spin text-xs"></i>
          <i v-else class="fa-solid fa-check text-xs"></i>
          <span>
            {{ isBulkAssigning ? 'Assigning...' : `Assign (${selectedCount} Tickets)` }}
          </span>
        </button>
      </div>
    </div>
  </Modal>

  <!-- ── MODAL - Inline Quick Assign Staff ─────────────────────────────── -->
  <Modal :show="showQuickAssignModal" @close="closeQuickAssign" maxWidth="lg">
    <div class="p-6 bg-white rounded-2xl">
      <!-- Modal Header -->
      <div class="flex items-center justify-between pb-3.5 border-b border-slate-100">
        <div class="flex items-center gap-2.5">
          <div
            class="w-9 h-9 rounded-xl flex items-center justify-center font-bold text-sm shadow-xs shrink-0"
            :class="quickAssignTicket?.detail?.ticket_assigned_staff ? 'bg-indigo-100 text-indigo-800' : 'bg-blue-100 text-blue-900'"
          >
            <i :class="quickAssignTicket?.detail?.ticket_assigned_staff ? 'fa-solid fa-user-gear' : 'fa-solid fa-user-plus'"></i>
          </div>
          <div>
            <div class="flex items-center gap-2 flex-wrap">
              <h3 class="text-base font-bold text-slate-900">
                {{ quickAssignTicket?.detail?.ticket_assigned_staff ? 'Reassign IT Staff' : 'Assign IT Staff' }}
              </h3>
              <span v-if="quickAssignTicket" class="font-mono font-bold text-rose-700 text-xs px-2 py-0.5 rounded bg-rose-50 border border-rose-200">
                #{{ quickAssignTicket.client_referenceNo }}
              </span>
            </div>
            <p class="text-xs text-slate-500 mt-0.5">
              {{ quickAssignTicket?.detail?.ticket_assigned_staff ? 'Reallocate ticket responsibility to another IT staff member' : 'Allocate an IT staff member to take action on this ticket' }}
            </p>
          </div>
        </div>
        <button
          type="button"
          @click="closeQuickAssign"
          class="text-slate-400 hover:text-slate-600 p-1.5 rounded-lg hover:bg-slate-100 transition cursor-pointer"
          title="Close modal"
        >
          <i class="fa-solid fa-xmark text-sm"></i>
        </button>
      </div>

      <div class="space-y-4 py-4 text-xs">
        <!-- Ticket Context Card -->
        <div v-if="quickAssignTicket" class="p-3.5 bg-slate-50/80 rounded-xl border border-slate-200/80 space-y-2.5">
          <!-- Client & Status -->
          <div class="flex items-center justify-between gap-2">
            <div class="flex items-center gap-2 min-w-0">
              <div class="w-7 h-7 rounded-lg bg-blue-100 text-blue-900 flex items-center justify-center font-bold text-xs shrink-0">
                <i class="fa-solid fa-user text-[11px]"></i>
              </div>
              <div class="min-w-0">
                <p class="text-xs font-bold text-slate-900 truncate">{{ quickAssignTicket.client_name }}</p>
                <p class="text-[11px] text-slate-500 truncate flex items-center gap-1">
                  <span>{{ mapDivision(quickAssignTicket.client_division_agency) }}</span>
                  <span v-if="quickAssignTicket.client_email" class="text-slate-300">•</span>
                  <span v-if="quickAssignTicket.client_email" class="truncate">{{ quickAssignTicket.client_email }}</span>
                </p>
              </div>
            </div>

            <!-- Status Pill -->
            <span
              class="inline-flex items-center gap-1 px-2.5 py-0.5 text-[10px] rounded-full font-bold uppercase tracking-wider text-white shrink-0 shadow-2xs"
              :class="{
                'bg-blue-600': quickAssignTicket.detail?.ticket_status === 'New',
                'bg-amber-500': quickAssignTicket.detail?.ticket_status === 'Pending',
                'bg-emerald-600': quickAssignTicket.detail?.ticket_status === 'In Progress',
                'bg-purple-600': quickAssignTicket.detail?.ticket_status === 'Completed',
                'bg-slate-600': !quickAssignTicket.detail?.ticket_status || quickAssignTicket.detail?.ticket_status === 'Closed',
              }"
            >
              {{ quickAssignTicket.detail?.ticket_status || 'New' }}
            </span>
          </div>

          <!-- Problem snippet -->
          <div
            v-if="quickAssignTicket.detail?.ticket_problem_that_needed_support || quickAssignTicket.detail?.ticket_nature_of_support"
            class="text-xs text-slate-700 bg-white p-2.5 rounded-lg border border-slate-200/70"
          >
            <div class="text-[10px] font-bold uppercase tracking-wider text-slate-400 mb-0.5 flex items-center justify-between">
              <span>{{ quickAssignTicket.detail?.ticket_nature_of_support || 'Reported Issue' }}</span>
              <span v-if="quickAssignTicket.detail?.ticket_support_type" class="text-blue-700 font-semibold normal-case">
                {{ quickAssignTicket.detail.ticket_support_type }}
              </span>
            </div>
            <p class="text-xs text-slate-700 line-clamp-2 leading-relaxed">
              {{ quickAssignTicket.detail?.ticket_problem_that_needed_support || 'No specific description recorded.' }}
            </p>
          </div>


        </div>

        <!-- Form: IT Staff Selection -->
        <div>
          <div class="flex items-center justify-between mb-1.5">
            <label class="block text-xs font-bold text-slate-800">
              <i class="fa-solid fa-user-check text-blue-900 mr-1.5"></i>Assign to IT Staff <span class="text-rose-500">*</span>
            </label>
          </div>
          <div class="relative">
            <select
              v-model="quickAssignStaff"
              class="w-full h-10 pl-3 pr-8 rounded-xl border border-slate-300 focus:ring-2 focus:ring-blue-900 focus:border-blue-900 text-xs bg-white text-slate-800 shadow-2xs font-medium cursor-pointer"
            >
              <option value="" disabled>Select IT Staff here</option>
              <option v-for="st in staffOptions" :key="st" :value="st">
                {{ st }} • ({{ getStaffActiveCount(st) }} open {{ getStaffActiveCount(st) === 1 ? 'ticket' : 'tickets' }})
              </option>
            </select>
          </div>
          <p class="text-[11px] text-slate-400 mt-1 flex items-center gap-1.5">
            <i class="fa-solid fa-circle-info text-[10px] text-slate-400"></i>
            <span>Parentheses show real-time open ticket count for each staff member.</span>
          </p>
        </div>

        <!-- Form: Request Type Classification -->
        <div>
          <label class="block text-xs font-bold text-slate-800 mb-1.5">
            <i class="fa-solid fa-tag text-blue-900 mr-1.5"></i>Request Type
          </label>
          <div class="grid grid-cols-2 gap-2 mt-1">
            <button
              v-for="opt in REQUEST_TYPE_OPTIONS"
              :key="opt.value"
              type="button"
              @click="quickAssignType = opt.value"
              class="flex items-center gap-2 p-2.5 rounded-xl border text-xs font-semibold transition cursor-pointer text-left"
              :class="quickAssignType === opt.value
                ? 'bg-blue-50 border-blue-900 text-blue-900 ring-1 ring-blue-900 shadow-2xs'
                : 'bg-white border-slate-200 text-slate-700 hover:bg-slate-50 hover:border-slate-300'"
            >
              <div
                class="w-6 h-6 rounded-lg flex items-center justify-center shrink-0 text-xs"
                :class="quickAssignType === opt.value ? 'bg-blue-900 text-white' : 'bg-slate-100 text-slate-600'"
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
          @click="closeQuickAssign"
          class="px-4 py-2 text-xs font-semibold rounded-xl border border-slate-200 text-slate-700 hover:bg-slate-50 transition cursor-pointer"
        >
          Cancel
        </button>
        <button
          type="button"
          :disabled="isQuickAssigning || !quickAssignStaff"
          @click="submitQuickAssign"
          class="inline-flex items-center gap-2 px-4 py-2 text-xs font-bold uppercase rounded-xl shadow-sm text-white bg-blue-900 hover:bg-blue-800 disabled:opacity-50 transition cursor-pointer"
        >
          <i v-if="isQuickAssigning" class="fa-solid fa-spinner animate-spin text-xs"></i>
          <i v-else class="fa-solid fa-check text-xs"></i>
          <span>
            {{ isQuickAssigning ? 'Assigning...' : (quickAssignTicket?.detail?.ticket_assigned_staff ? 'Confirm Reassign' : 'Confirm Assignment') }}
          </span>
        </button>
      </div>
    </div>
  </Modal>

  <!-- ── MODAL - Bulk Delete Tickets (Admin only) ──────────────────────── -->
  <Modal :show="showBulkDeleteModal" @close="closeBulkDeleteModal" maxWidth="md">
    <div class="p-6 bg-white rounded-2xl">
      <div class="flex items-center justify-between pb-3 border-b border-rose-100">
        <div class="flex items-center gap-2">
          <div class="w-8 h-8 rounded-lg bg-rose-100 text-rose-700 flex items-center justify-center font-bold">
            <i class="fa-solid fa-triangle-exclamation text-rose-600 text-sm"></i>
          </div>
          <div>
            <h3 class="text-base font-bold text-slate-900">Confirm Bulk Deletion</h3>
            <p class="text-xs text-rose-600 font-medium mt-0.5">Permanent administrative action</p>
          </div>
        </div>
        <button type="button" @click="closeBulkDeleteModal" class="text-slate-400 hover:text-slate-600 cursor-pointer">
          <i class="fa-solid fa-xmark text-sm"></i>
        </button>
      </div>

      <div class="space-y-3 py-4 text-xs">
        <p class="text-slate-700">
          Are you sure you want to permanently delete <strong class="text-rose-700 font-bold">{{ selectedCount }} selected tickets</strong>?
        </p>
        <p class="text-slate-500 bg-rose-50 p-2.5 rounded-lg border border-rose-100">
          This will delete the tickets, their technical details, and all activity audit logs. This action <span class="font-bold text-rose-700">cannot be undone</span>.
        </p>
      </div>

      <div class="flex items-center justify-end gap-2 pt-3 border-t border-slate-100">
        <button
          type="button"
          @click="closeBulkDeleteModal"
          class="px-3.5 py-2 text-xs font-medium rounded-lg border border-slate-300 text-slate-700 hover:bg-slate-50 cursor-pointer"
        >
          Cancel
        </button>
        <button
          type="button"
          :disabled="isBulkDeleting"
          @click="submitBulkDelete"
          class="px-4 py-2 text-xs font-bold uppercase rounded-lg shadow-sm text-white bg-rose-600 hover:bg-rose-700 disabled:opacity-50 transition cursor-pointer"
        >
          {{ isBulkDeleting ? 'Deleting...' : `Delete ${selectedCount} Tickets` }}
        </button>
      </div>
    </div>
  </Modal>

  <!-- ── MODAL - Message Peek & Quick Note Indicator Flyout ────────────── -->
  <Modal :show="showPeekModal" @close="closePeekModal" maxWidth="xl">
    <div class="p-6 bg-white rounded-2xl">
      <!-- Header -->
      <div class="flex items-center justify-between pb-3 border-b border-slate-100">
        <div class="flex items-center gap-2.5">
          <div
            class="w-8 h-8 rounded-lg flex items-center justify-center font-bold text-sm shadow-xs"
            :class="peekTab === 'messages' ? 'bg-blue-100 text-blue-800' : 'bg-amber-100 text-amber-800'"
          >
            <i :class="peekTab === 'messages' ? 'fa-regular fa-comments' : 'fa-regular fa-note-sticky'"></i>
          </div>
          <div>
            <div class="flex items-center gap-2">
              <h3 class="text-base font-bold text-slate-900">
                {{ peekTab === 'messages' ? 'Ticket Messages' : 'Internal Notes & Remarks' }}
              </h3>
              <span class="font-mono font-bold text-rose-700 text-xs px-2 py-0.5 rounded bg-rose-50 border border-rose-200">
                {{ peekTicket?.client_referenceNo }}
              </span>
            </div>
            <p class="text-xs text-slate-500 mt-0.5">
              {{ peekTicket?.client_name }} · {{ mapDivision(peekTicket?.client_division_agency) }}
            </p>
          </div>
        </div>
        <button type="button" @click="closePeekModal" class="text-slate-400 hover:text-slate-600 p-1 rounded-lg">
          <i class="fa-solid fa-xmark text-sm"></i>
        </button>
      </div>

      <!-- Tab Switcher -->
      <div class="flex items-center gap-2 border-b border-slate-200 mt-3 pb-2 text-xs font-semibold">
        <button
          type="button"
          @click="peekTab = 'messages'"
          class="px-3 py-1.5 rounded-lg transition inline-flex items-center gap-1.5"
          :class="peekTab === 'messages' ? 'bg-blue-900 text-white shadow-xs' : 'text-slate-600 hover:bg-slate-100'"
        >
          <span class="inline-flex items-center gap-1.5">
            <i class="fa-regular fa-comments text-xs"></i>
            <span>Messages</span>
          </span>
          <span
            class="text-[10px] px-1.5 py-0.2 rounded-full font-bold"
            :class="peekTab === 'messages' ? 'bg-blue-800 text-white' : 'bg-slate-200 text-slate-700'"
          >
            {{ peekTicket?.messages_count ?? 0 }}
          </span>
        </button>

        <button
          type="button"
          @click="peekTab = 'notes'"
          class="px-3 py-1.5 rounded-lg transition inline-flex items-center gap-1.5"
          :class="peekTab === 'notes' ? 'bg-amber-600 text-white shadow-xs' : 'text-slate-600 hover:bg-slate-100'"
        >
          <span class="inline-flex items-center gap-1.5">
            <i class="fa-regular fa-note-sticky text-xs"></i>
            <span>Internal Notes</span>
          </span>
          <span v-if="peekTicket?.detail?.ticket_remarks" class="w-2 h-2 rounded-full bg-amber-400"></span>
        </button>

        <div class="flex-1"></div>

        <button
          type="button"
          @click="openModalFromPeek(peekTicket)"
          class="text-xs text-blue-700 hover:underline inline-flex items-center gap-1 font-semibold"
          title="Open full triage drawer"
        >
          <span>Full Drawer</span>
          <i class="fa-solid fa-arrow-up-right-from-square text-xs"></i>
        </button>
      </div>

      <!-- Tab Content -->
      <div class="mt-4">
        <!-- Messages Thread -->
        <div v-if="peekTab === 'messages'" class="h-[400px]">
          <TicketMessageThread
            v-if="peekTicket?.client_referenceNo"
            :client-reference-no="peekTicket.client_referenceNo"
            sender-role="staff"
          />
        </div>

        <!-- Quick Internal Note / Remarks -->
        <div v-else class="space-y-4 text-xs">
          <div>
            <label class="block font-semibold text-slate-700 mb-1.5">Current Technical Remarks / Internal Notes</label>
            <div
              v-if="peekTicket?.detail?.ticket_remarks"
              class="p-3 bg-amber-50/70 rounded-xl border border-amber-200 text-amber-950 whitespace-pre-wrap text-xs leading-relaxed max-h-48 overflow-y-auto"
            >
              {{ peekTicket.detail.ticket_remarks }}
            </div>
            <div v-else class="p-3 bg-slate-50 rounded-xl border border-slate-200 text-slate-400 italic text-xs">
              No internal remarks or notes recorded for this ticket yet.
            </div>
          </div>

          <div>
            <label class="block font-semibold text-slate-700 mb-1">
              {{ peekTicket?.detail?.ticket_remarks ? 'Append / Update Note' : 'Add Internal Note' }}
            </label>
            <div class="flex flex-wrap gap-1 mb-2">
              <button
                v-for="chip in ['Parts on order', 'Waiting on client reply', 'Tested & verified working', 'Followed up via phone', 'Escalated to senior dev']"
                :key="chip"
                type="button"
                @click="addQuickNoteChip(chip)"
                class="px-2 py-0.5 rounded-md text-[11px] bg-slate-100 hover:bg-slate-200 text-slate-700 border border-slate-200 transition"
              >
                + {{ chip }}
              </button>
            </div>
            <textarea
              v-model="peekNoteText"
              rows="3"
              class="w-full rounded-xl border-slate-300 focus:ring-2 focus:ring-amber-500 focus:border-amber-500 text-xs p-2.5 bg-white"
              placeholder="Type internal notes, findings, or IT staff remarks..."
            ></textarea>
          </div>

          <div class="flex items-center justify-end gap-2 pt-2 border-t border-slate-100">
            <button
              type="button"
              @click="closePeekModal"
              class="px-3.5 py-1.5 rounded-lg border border-slate-300 text-slate-700 hover:bg-slate-50"
            >
              Cancel
            </button>
            <button
              type="button"
              :disabled="isSavingNote || !peekNoteText.trim()"
              @click="savePeekNote"
              class="px-4 py-1.5 rounded-lg bg-amber-600 hover:bg-amber-700 text-white font-bold disabled:opacity-50 transition shadow-xs"
            >
              {{ isSavingNote ? 'Saving...' : 'Save Remarks' }}
            </button>
          </div>
        </div>
      </div>
    </div>
  </Modal>
  </div>
</template>
