<!-- resources/js/Pages/TicketsV2/Components/NoCSMTicketsAll.vue -->
<script setup>
import { ref, computed, watch } from 'vue'
import { router } from '@inertiajs/vue3'

const props = defineProps({
  tickets: { type: Array, default: () => [] },
  initialMonth: { type: [Number, String], default: null },
  initialYear:  { type: [Number, String], default: null },
})

/* ── Pagination ───────────────────────────────────────────── */
const perPage = 10
const currentPage = ref(1)

/* ── Filters (unchanged original) ─────────────────────────── */
const now = new Date()
const month = ref(props.initialMonth != null ? String(props.initialMonth) : String(now.getMonth() + 1))
const year  = ref(props.initialYear  != null ? String(props.initialYear)  : String(now.getFullYear()))
const staff = ref('all') // staff filter stays

// NEW: checkbox multi-select months (empty = All months, and dropdown applies)
const monthsSelected = ref([]) // array of string numbers: ["1","2",...]
const selectedMonthsSet = computed(() => new Set(monthsSelected.value.map(v => Number(v))))

const monthNames = [
  'January','February','March','April','May','June',
  'July','August','September','October','November','December'
]

// Always-show "current month & year" labels
const currentMonthLabel = monthNames[now.getMonth()]
const currentYearLabel  = String(now.getFullYear())

watch([() => props.tickets, month, year, staff, monthsSelected], () => { currentPage.value = 1 })

/* ── Helpers ──────────────────────────────────────────────── */
function parseYMDParts(val) {
  if (!val) return null
  if (typeof val === 'string') {
    const m = val.match(/^(\d{4})-(\d{2})-(\d{2})/)
    if (m) return { y: Number(m[1]), m: Number(m[2]), d: Number(m[3]) }
  }
  const d = new Date(val)
  if (Number.isNaN(d.getTime())) return null
  return { y: d.getFullYear(), m: d.getMonth() + 1, d: d.getDate() }
}

function splitStaff(value) {
  if (!value) return []
  return String(value).split(/[,&;/]+/g).map(s => s.trim()).filter(Boolean)
}
function hasStaff(t, target) {
  if (!target) return true
  const names = splitStaff(t?.assigned_staff).map(s => s.toLowerCase())
  return names.includes(target.toLowerCase())
}

const CSM_ORIGIN = import.meta.env.VITE_CSM_ORIGIN || 'http://ticketing.stii.local'
const rateUrl = (refNo) =>
  `${CSM_ORIGIN.replace(/\/$/, '')}/client/rate/${encodeURIComponent(refNo)}`

/* ── Data shaping ─────────────────────────────────────────── */
const base = computed(() => props.tickets || [])

const yearOptions = computed(() => {
  const set = new Set()
  for (const t of base.value) {
    const p = parseYMDParts(t?.date_completed)
    if (p) set.add(p.y)
  }
  return Array.from(set).sort((a,b) => b - a)
})

const staffOptions = computed(() => {
  const set = new Set()
  for (const t of base.value) splitStaff(t?.assigned_staff).forEach(n => set.add(n))
  return Array.from(set).sort((a,b) => a.localeCompare(b))
})

/* ── Filtering ────────────────────────────────────────────── */
const filtered = computed(() => {
  const m = month.value === 'all' ? null : Number(month.value)
  const y = year.value  === 'all' ? null : Number(year.value)
  const s = staff.value === 'all' ? null : staff.value
  const usingCheckboxes = selectedMonthsSet.value.size > 0

  return base.value.filter(t => {
    const p = parseYMDParts(t?.date_completed)
    if (!p) return false
    if (y && p.y !== y) return false

    // NEW: if any checkboxes selected, they override the dropdown
    if (usingCheckboxes) {
      if (!selectedMonthsSet.value.has(p.m)) return false
    } else {
      if (m && p.m !== m) return false
    }

    if (s && !hasStaff(t, s)) return false
    return true
  })
})

/* ── Pagination ───────────────────────────────────────────── */
const totalPages = computed(() => Math.max(1, Math.ceil(filtered.value.length / perPage)))
const startIndex = computed(() => (currentPage.value - 1) * perPage)
const pageSlice  = computed(() => filtered.value.slice(startIndex.value, startIndex.value + perPage))
function goTo(p) { if (p >= 1 && p <= totalPages.value) currentPage.value = p }

const pages = computed(() => {
  const total = totalPages.value, cur = currentPage.value
  const items = [], add = p => items.push({ type:'page', value:p }), gap = () => items.push({ type:'gap' })
  if (total <= 7) { for (let p=1; p<=total; p++) add(p) }
  else { add(1); if (cur>4) gap(); const s=Math.max(2,cur-1), e=Math.min(total-1,cur+1); for (let p=s;p<=e;p++) add(p); if (cur<total-3) gap(); add(total) }
  return items
})

function refresh() {
  router.reload({ only: ['noCsmTicketsAll'], preserveScroll: true })
  currentPage.value = 1
}

// ── UI state for the improved month picker ───────────────────
const showMonthsPanel = ref(false)

const monthNums = Array.from({ length: 12 }, (_, i) => i + 1)

const selectedMonthNames = computed(() => {
  const set = new Set(monthsSelected.value.map(v => Number(v)))
  return monthNums
    .filter(n => set.has(n))
    .map(n => monthNames[n - 1])
})

const monthsSummary = computed(() => {
  if (!monthsSelected.value.length) return 'All months'
  // show up to 3, then “+N more”
  const list = selectedMonthNames.value
  return list.length > 3
    ? `${list.slice(0,3).join(', ')} +${list.length - 3} more`
    : list.join(', ')
})

function toggleMonthValue(n) {
  const s = new Set(monthsSelected.value)
  const key = String(n)
  s.has(key) ? s.delete(key) : s.add(key)
  monthsSelected.value = Array.from(s)
}

function selectAllMonths() {
  monthsSelected.value = monthNums.map(n => String(n))
}

function clearMonths() {
  monthsSelected.value = []
}

function selectQuarter(q) {
  const map = { 1: [1,2,3], 2: [4,5,6], 3: [7,8,9], 4: [10,11,12] }
  monthsSelected.value = map[q].map(n => String(n))
}

function selectThisMonth() {
  monthsSelected.value = [String(now.getMonth() + 1)]
}

// ── Click-outside directive (local) ─────────────────────────
const vClickOutside = {
  beforeMount(el, binding) {
    el.__clickOutsideHandler__ = (e) => {
      if (!(el === e.target || el.contains(e.target))) {
        binding.value(e)
      }
    }
    document.addEventListener('mousedown', el.__clickOutsideHandler__)
    document.addEventListener('touchstart', el.__clickOutsideHandler__)
  },
  unmounted(el) {
    document.removeEventListener('mousedown', el.__clickOutsideHandler__)
    document.removeEventListener('touchstart', el.__clickOutsideHandler__)
    delete el.__clickOutsideHandler__
  },
}

// Format to YYYY-MM-DD (Google Form expects this shape for date fields)
function toYMD(val) {
  if (!val) return ''
  const d = new Date(val)
  if (Number.isNaN(d.getTime())) return ''
  const y = d.getFullYear()
  const m = String(d.getMonth() + 1).padStart(2, '0')
  const day = String(d.getDate()).padStart(2, '0')
  return `${y}-${m}-${day}`
}

// Prefilled Google Form (keep as-is; we only replace the placeholders)
const CSM_FORM_PREFILL =
  "https://docs.google.com/forms/d/e/1FAIpQLSfda5Huf3g5zB5kIoemYLrM-SPR41VmTYRQ2gyeGwaeTtXPsg/viewform?usp=pp_url" +
  "&entry.287019428=English" +
  "&entry.613601820=Government+(Employee+or+another+agency)" +
  "&entry.204278290=TicketCreatedDateHere" +
  "&entry.468511028=ClientSexHere" +

  "&entry.474414778=Technical+Support+Services" +
  "&entry.1425476613=1.+I+know+what+a+CC+is+and+I+saw+this+office's+CC." +
  "&entry.261531192=1.+Easy+to+see" +
  "&entry.496814402=1.+Helped+very+much" +
  "&entry.1618484929=5" +
  "&entry.518663308=5" +
  "&entry.1836447877=5" +
  "&entry.573118426=5" +
  "&entry.1045039657=5" +
  "&entry.1725600=0" +
  "&entry.214403062=5" +
  "&entry.1773001201=5" +
  "&entry.1619520798=5" +
  "&entry.298800992=RefNumberHere" +
  "&entry.855472974=ClientName"

const csmFormUrl = (t) => {
  const ref = encodeURIComponent(t?.client_referenceNo ?? "")
  const name = encodeURIComponent(t?.client_name ?? "")
  const clientSex = encodeURIComponent(t?.client_sex ?? "")
  const ymd   = encodeURIComponent(toYMD(t?.date_created))
  return CSM_FORM_PREFILL
    .replace("RefNumberHere", ref)
    .replace("ClientName", name)
    .replace("ClientSexHere", clientSex)
    .replace("TicketCreatedDateHere", ymd)
}

</script>

<template>
  <div class="relative flex flex-col min-w-0 break-words bg-white w-full mb-6 shadow-lg rounded">
    <div class="flex items-center justify-between px-4 py-3 border-b border-gray-200">
      <h3 class="font-semibold text-base">
        No CSM
        <span class="ml-2 text-xs text-gray-500">({{ filtered.length }})</span>
      </h3>

      <!-- Always show current month/year -->
      <div class="text-xs text-gray-600">
        Current: <span class="font-medium">{{ currentMonthLabel }} {{ currentYearLabel }}</span>
      </div>
    </div>

    <!-- Filters -->
    <div class="px-4 py-2 flex items-center gap-4 flex-wrap">
        <!-- Keep existing dropdown -->


            <!-- Improved: Compact month picker w/ chips + quick picks -->
            <div class="relative">
            <button
                type="button"
                class="text-xs border rounded px-2 py-1 bg-white hover:bg-gray-50 inline-flex items-center gap-2"
                @click="showMonthsPanel = !showMonthsPanel"
            >
                <span class="text-[11px] text-gray-500">Months</span>
                <span class="truncate max-w-[220px]">{{ monthsSummary }}</span>
                <svg class="w-3 h-3 opacity-70" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M5.23 7.21a.75.75 0 011.06.02L10 11.174l3.71-3.943a.75.75 0 111.08 1.04l-4.24 4.5a.75.75 0 01-1.08 0l-4.24-4.5a.75.75 0 01.02-1.06z" clip-rule="evenodd"/></svg>
            </button>

            <div
                v-if="showMonthsPanel"
                v-click-outside="() => (showMonthsPanel = false)"
                class="absolute z-20 mt-2 w-[420px] max-w-[90vw] rounded-lg border bg-white shadow-lg"
            >
                <div class="p-3 border-b bg-gray-50 rounded-t-lg">
                <div class="flex flex-wrap gap-1">
                    <button class="text-[11px] px-2 py-1 border rounded hover:bg-white" @click="selectThisMonth()">This month</button>
                    <button class="text-[11px] px-2 py-1 border rounded hover:bg-white" @click="selectQuarter(1)">Q1</button>
                    <button class="text-[11px] px-2 py-1 border rounded hover:bg-white" @click="selectQuarter(2)">Q2</button>
                    <button class="text-[11px] px-2 py-1 border rounded hover:bg-white" @click="selectQuarter(3)">Q3</button>
                    <button class="text-[11px] px-2 py-1 border rounded hover:bg-white" @click="selectQuarter(4)">Q4</button>
                    <span class="flex-1"></span>
                    <button class="text-[11px] px-2 py-1 border rounded hover:bg-white" @click="selectAllMonths()">Select all</button>
                    <button class="text-[11px] px-2 py-1 border rounded hover:bg-white" @click="clearMonths()">Clear</button>
                </div>
                </div>

                <div class="p-3">
                <div class="grid grid-cols-3 gap-2">
                    <button
                    v-for="n in 12"
                    :key="'m-btn-'+n"
                    type="button"
                    class="text-xs px-2 py-1 border rounded hover:bg-gray-50 text-left"
                    :class="selectedMonthsSet.has(n) ? 'bg-indigo-50 border-indigo-300' : 'bg-white'"
                    @click="toggleMonthValue(n)"
                    >
                    {{ monthNames[n-1] }}
                    </button>
                </div>

                <!-- Selected chips -->
                <div v-if="monthsSelected.length" class="mt-3 flex flex-wrap gap-1">
                    <span
                    v-for="(name, i) in selectedMonthNames"
                    :key="'chip-'+i"
                    class="text-[11px] px-2 py-0.5 rounded-full bg-indigo-50 border border-indigo-200 text-indigo-700"
                    >
                    {{ name }}
                    </span>
                </div>
                </div>

                <div class="p-2 border-t bg-gray-50 rounded-b-lg flex items-center justify-end gap-2">
                <button class="text-[11px] px-2 py-1 border rounded hover:bg-white" @click="showMonthsPanel=false">Done</button>
                </div>
            </div>
            </div>


        <div class="flex items-center gap-2">
            <label class="text-[11px] text-gray-500">Year</label>
            <select v-model="year" class="text-xs border rounded px-2 py-1 bg-white">
            <option value="all">All</option>
            <option v-for="y in yearOptions" :key="y" :value="String(y)">{{ y }}</option>
            </select>
        </div>

        <div class="flex items-center gap-2">
            <label class="text-[11px] text-gray-500">Staff</label>
            <select v-model="staff" class="text-xs border rounded px-2 py-1 bg-white max-w-[220px]">
            <option value="all">All</option>
            <option v-for="name in staffOptions" :key="name" :value="name">{{ name }}</option>
            </select>
        </div>
    </div>

    <div class="block w-full overflow-x-auto">
      <table class="items-center w-full bg-transparent border-collapse">
        <thead>
          <tr>
            <th class="px-6 bg-blueGray-50 text-blueGray-500 align-middle border border-blueGray-100 py-3 text-xs uppercase font-semibold text-left">Reference No.</th>
            <th class="px-6 bg-blueGray-50 text-blueGray-500 align-middle border border-blueGray-100 py-3 text-xs uppercase font-semibold text-left">Client Name</th>
            <th class="px-6 bg-blueGray-50 text-blueGray-500 align-middle border border-blueGray-100 py-3 text-xs uppercase font-semibold text-left">Assigned Staff</th>
            <th class="px-6 bg-blueGray-50 text-blueGray-500 align-middle border border-blueGray-100 py-3 text-xs uppercase font-semibold text-left">Action</th>
          </tr>
        </thead>

        <tbody>
          <tr v-if="filtered.length === 0">
            <td colspan="4" class="px-6 py-6 text-center text-sm text-gray-500">
              No tickets pending CSM submission.
            </td>
          </tr>

          <tr v-for="t in pageSlice" :key="t.client_referenceNo" class="hover:bg-gray-50">
            <th class="border-t-0 px-6 align-middle border-l-0 border-r-0 text-xs whitespace-nowrap p-4 text-left">
              {{ t.client_referenceNo }}
            </th>
            <td class="border-t-0 px-6 align-middle border-l-0 border-r-0 text-xs whitespace-nowrap p-4">
              {{ t.client_name || '-' }}
            </td>
            <td class="border-t-0 px-6 align-middle border-l-0 border-r-0 text-xs whitespace-nowrap p-4">
              {{ t.assigned_staff || '-' }}
            </td>
                <td class="border-t-0 px-6 align-middle border-l-0 border-r-0 text-xs whitespace-nowrap p-4">
                    <div class="flex items-center gap-2">
                        <!-- Rate / CSM Button (filled blue) -->
                        <a
                        :href="rateUrl(t.client_referenceNo)"
                        target="_blank"
                        rel="noopener noreferrer"
                        class="inline-flex items-center gap-1 px-3 py-1.5 rounded-lg text-xs font-medium
                                bg-indigo-600 text-white hover:bg-indigo-700 active:bg-indigo-800
                                focus:outline-none focus:ring-2 focus:ring-indigo-400 focus:ring-offset-2
                                transition-colors"
                        title="Open Rate/CSM page in a new tab"
                        >
                            <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M11.049 2.927c.3-.921 1.603-.921 1.902 0l1.286 3.946a1 1 0 00.95.69h4.148c.969 0 1.371 1.24.588 1.81l-3.36 2.44a1 1 0 00-.364 1.118l1.286 3.946c.3.921-.755 1.688-1.54 1.118l-3.36-2.44a1 1 0 00-1.176 0l-3.36 2.44c-.784.57-1.838-.197-1.539-1.118l1.285-3.946a1 1 0 00-.364-1.118L2.97 9.373c-.783-.57-.38-1.81.588-1.81h4.149a1 1 0 00.95-.69l1.286-3.946z" />
                            </svg>
                            <span>Rate</span>
                        </a>

                        <!-- Answer CSM Button (filled green) -->
                        <a
                        :href="csmFormUrl(t)"
                        target="_blank"
                        rel="noopener noreferrer"
                        class="inline-flex items-center gap-1 px-3 py-1.5 rounded-lg text-xs font-medium
                                bg-green-600 text-white hover:bg-green-700 active:bg-green-800
                                focus:outline-none focus:ring-2 focus:ring-green-400 focus:ring-offset-2
                                transition-colors"
                        title="Answer the CSM form"
                        >
                            <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5M18.5 2.5a2.121 2.121 0 113 3L12 15l-4 1 1-4 9.5-9.5z" />
                            </svg>
                            <span>CSM</span>
                        </a>
                    </div>
                </td>



          </tr>
        </tbody>
      </table>
    </div>

    <!-- Pagination -->
    <div v-if="filtered.length > perPage" class="px-4 py-3 border-t bg-gray-50">
      <div class="flex items-center justify-between gap-3 flex-wrap">
        <div class="text-xs text-gray-500">Page {{ currentPage }} of {{ totalPages }}</div>
        <div class="flex items-center gap-1 overflow-x-auto max-w-full whitespace-nowrap">
          <button class="px-2 py-1 text-xs border rounded disabled:opacity-50" :disabled="currentPage === 1" @click="goTo(currentPage - 1)">Prev</button>
          <template v-for="(it, idx) in pages" :key="idx">
            <button v-if="it.type === 'page'" class="px-2 py-1 text-xs border rounded" :class="it.value === currentPage ? 'bg-blue-600 text-white' : 'bg-white'" @click="goTo(it.value)">{{ it.value }}</button>
            <span v-else class="px-2 text-xs text-gray-500 select-none">…</span>
          </template>
          <button class="px-2 py-1 text-xs border rounded disabled:opacity-50" :disabled="currentPage === totalPages" @click="goTo(currentPage + 1)">Next</button>
        </div>
      </div>
    </div>
  </div>
</template>
