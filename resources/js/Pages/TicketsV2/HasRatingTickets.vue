<script setup>
import { ref, computed, watch, onMounted } from 'vue'
import { router } from '@inertiajs/vue3'
import ModalNoRatingTicketDetails from '@/Pages/TicketsV2/ModalComponents/ModalTicketDetails_NoRating.vue'

const props = defineProps({
  // Data source (pass tickets that ALREADY HAVE ratings)
  tickets: { type: Array, default: () => [] },
  // Legacy compatibility if you happen to reuse a prop
  pendingTickets: { type: Array, default: () => [] },

  // Initial filters
  initialMonth: { type: [String, Number, null], default: null },
  initialYear:  { type: [String, Number, null], default: null },

  // Which server props to reload on refresh, if you wire this up
  reloadOnly:   { type: [String, Array], default: 'hasRatingTickets' },
})

/* Pagination */
const perPage = 10
const currentPage = ref(1)

/* Filters */
const now = new Date()
const month = ref('all')
const year  = ref('all')
const staff = ref('all') // staff filter

// Multi-month
const monthsSelected = ref([]) // ["1","2",...]
const selectedMonthsSet = computed(() => new Set(monthsSelected.value.map(v => Number(v))))

onMounted(() => {
  month.value = props.initialMonth != null && props.initialMonth !== '' ? String(props.initialMonth) : String(now.getMonth() + 1)
  year.value  = props.initialYear  != null && props.initialYear  !== '' ? String(props.initialYear)  : String(now.getFullYear())
})

const monthNames = [
  'January','February','March','April','May','June',
  'July','August','September','October','November','December'
]
const monthNums = Array.from({ length: 12 }, (_, i) => i + 1)

/* Helpers */
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
function recordStaff(t) {
  return t?.assigned_staff ?? t?.ticket_assigned_staff ?? ''
}
function hasStaff(t, target) {
  if (!target) return true
  const names = splitStaff(recordStaff(t)).map(s => s.toLowerCase())
  return names.includes(String(target).toLowerCase())
}

/* Rate/Review URL */
const CSM_ORIGIN = import.meta.env.VITE_CSM_ORIGIN || 'http://ticketing.stii.local'
const rateUrl = (refNo) => `${CSM_ORIGIN.replace(/\/$/, '')}/client/rate/${encodeURIComponent(refNo)}`

/* Base + options */
const base = computed(() => (props.tickets?.length ? props.tickets : props.pendingTickets) || [])

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
  for (const t of base.value) splitStaff(recordStaff(t)).forEach(n => n && set.add(n))
  return Array.from(set).sort((a,b) => a.localeCompare(b))
})

/* Filtering (same as NoRatingTickets.vue) */
const filtered = computed(() => {
  const m = month.value === 'all' ? null : Number(month.value)
  const y = year.value  === 'all' ? null : Number(year.value)
  const s = staff.value === 'all' ? null : staff.value
  const usingCheckboxes = selectedMonthsSet.value.size > 0

  return base.value.filter(t => {
    const p = parseYMDParts(t?.date_completed)
    if (!p) return false
    if (y && p.y !== y) return false

    if (usingCheckboxes) {
      if (!selectedMonthsSet.value.has(p.m)) return false
    } else if (m && p.m !== m) {
      return false
    }

    if (s && !hasStaff(t, s)) return false
    return true
  })
})

/* Pagination derived */
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

watch([base, month, year, staff, monthsSelected], () => { currentPage.value = 1 })

/* Month panel */
const showMonthsPanel = ref(false)
function toggleMonthValue(n){const s=new Set(monthsSelected.value);const k=String(n);s.has(k)?s.delete(k):s.add(k);monthsSelected.value=Array.from(s)}
function selectAllMonths(){monthsSelected.value=monthNums.map(n=>String(n))}
function clearMonths(){monthsSelected.value=[]}
function selectQuarter(q){const map={1:[1,2,3],2:[4,5,6],3:[7,8,9],4:[10,11,12]};monthsSelected.value=map[q].map(n=>String(n))}
function selectThisMonth(){monthsSelected.value=[String(now.getMonth()+1)]}
const selectedMonthNames = computed(()=>{const s=new Set(monthsSelected.value.map(v=>Number(v)));return monthNums.filter(n=>s.has(n)).map(n=>monthNames[n-1])})
const monthsSummary = computed(()=>{if(!monthsSelected.value.length) return 'All months'; const list=selectedMonthNames.value; return list.length>3?`${list.slice(0,3).join(', ')} +${list.length-3} more`:list.join(', ')})
const vClickOutside = { beforeMount(el,b){el.__co=e=>{if(!(el===e.target||el.contains(e.target))) b.value(e)};document.addEventListener('mousedown',el.__co);document.addEventListener('touchstart',el.__co)}, unmounted(el){document.removeEventListener('mousedown',el.__co);document.removeEventListener('touchstart',el.__co);delete el.__co} }

/* Modal + refresh */
const selected = ref(null)
const showDetails = ref(false)
function openDetails(ticket){ selected.value = ticket; showDetails.value = true }
function refresh(){ const only = Array.isArray(props.reloadOnly)?props.reloadOnly:[props.reloadOnly]; router.reload({ only, preserveScroll: true }); currentPage.value = 1 }
</script>

<template>
  <div class="relative flex flex-col min-w-0 break-words bg-white w-full mb-6 shadow-lg rounded">
    <div class="flex items-center justify-between px-4 py-3 border-b border-gray-200">
      <h3 class="font-semibold text-base">
        With Ratings
        <span class="ml-2 text-xs text-gray-500">({{ filtered.length }})</span>
      </h3>

      <div class="text-xs text-gray-600">
        Current: <span class="font-medium">{{ monthNames[new Date().getMonth()] }} {{ new Date().getFullYear() }}</span>
      </div>
    </div>

    <!-- Filters (same as NoRatingTickets) -->
    <div class="px-4 py-2 flex items-center gap-4 flex-wrap">
      <!-- Multi-month picker -->
      <div class="relative">
        <button type="button" class="text-xs border rounded px-2 py-1 bg-white hover:bg-gray-50 inline-flex items-center gap-2"
                @click="showMonthsPanel = !showMonthsPanel" aria-haspopup="true" :aria-expanded="showMonthsPanel ? 'true' : 'false'">
          <span class="text-[11px] text-gray-500">Months</span>
          <span class="truncate max-w-[220px]">{{ monthsSummary }}</span>
          <svg class="w-3 h-3 opacity-70" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M5.23 7.21a.75.75 0 011.06.02L10 11.174l3.71-3.943a.75.75 0 111.08 1.04l-4.24 4.5a.75.75 0 01-1.08 0l-4.24-4.5a.75.75 0 01.02-1.06z" clip-rule="evenodd"/></svg>
        </button>

        <div v-if="showMonthsPanel" v-click-outside="() => (showMonthsPanel = false)"
             class="absolute z-20 mt-2 w-[420px] max-w-[90vw] rounded-lg border bg-white shadow-lg">
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
              <button v-for="n in 12" :key="'m-btn-'+n" type="button"
                      class="text-xs px-2 py-1 border rounded hover:bg-gray-50 text-left"
                      :class="selectedMonthsSet.has(n) ? 'bg-indigo-50 border-indigo-300' : 'bg-white'"
                      @click="toggleMonthValue(n)">
                {{ monthNames[n-1] }}
              </button>
            </div>

            <div v-if="monthsSelected.length" class="mt-3 flex flex-wrap gap-1">
              <span v-for="(name, i) in selectedMonthNames" :key="'chip-'+i"
                    class="text-[11px] px-2 py-0.5 rounded-full bg-indigo-50 border border-indigo-200 text-indigo-700">
                {{ name }}
              </span>
            </div>
          </div>

          <div class="p-2 border-t bg-gray-50 rounded-b-lg flex items-center justify-end gap-2">
            <button class="text-[11px] px-2 py-1 border rounded hover:bg-white" @click="showMonthsPanel=false">Done</button>
          </div>
        </div>
      </div>

      <!-- Year -->
      <div class="flex items-center gap-2">
        <label class="text-[11px] text-gray-500">Year</label>
        <select v-model="year" class="text-xs border rounded px-2 py-1 bg-white">
          <option value="all">All</option>
          <option v-for="y in yearOptions" :key="y" :value="String(y)">{{ y }}</option>
        </select>
      </div>

      <!-- Staff -->
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
            <th class="px-6 bg-blueGray-50 text-blueGray-500 align-middle border border-blueGray-100 py-3 text-xs uppercase font-semibold text-left">
              Reference No.
            </th>
            <th class="px-6 bg-blueGray-50 text-blueGray-500 align-middle border border-blueGray-100 py-3 text-xs uppercase font-semibold text-left">
              Client Name
            </th>
            <th class="px-6 bg-blueGray-50 text-blueGray-500 align-middle border border-blueGray-100 py-3 text-xs uppercase font-semibold text-left">
              Assigned Staff
            </th>
            <th class="px-6 bg-blueGray-50 text-blueGray-500 align-middle border border-blueGray-100 py-3 text-xs uppercase font-semibold text-left">
              Action
            </th>
          </tr>
        </thead>

        <tbody>
          <tr v-if="filtered.length === 0">
            <td colspan="4" class="px-6 py-6 text-center text-sm text-gray-500">
              No tickets with ratings match your filters.
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
              {{ (t.assigned_staff ?? t.ticket_assigned_staff) || '-' }}
            </td>

            <td class="border-t-0 px-6 align-middle border-l-0 border-r-0 text-xs whitespace-nowrap p-4">
              <div class="flex items-center gap-2">
                <!-- View rating (filled indigo) -->
                <a
                  :href="rateUrl(t.client_referenceNo)"
                  target="_blank"
                  rel="noopener noreferrer"
                  class="inline-flex items-center gap-1 px-3 py-1.5 rounded-lg text-xs font-medium
                         bg-indigo-600 text-white hover:bg-indigo-700 active:bg-indigo-800
                         focus:outline-none focus:ring-2 focus:ring-indigo-400 focus:ring-offset-2
                         transition-colors"
                  title="Open current rating page in a new tab"
                >
                  <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                      d="M11.049 2.927c.3-.921 1.603-.921 1.902 0l1.286 3.946a1 1 0 00.95.69h4.148c.969 0 1.371 1.24.588 1.81l-3.36 2.44a1 1 0 00-.364 1.118l1.286 3.946c.3.921-.755 1.688-1.54 1.118l-3.36-2.44a1 1 0 00-1.176 0l-3.36 2.44c-.784.57-1.838-.197-1.539-1.118l1.285-3.946a1 1 0 00-.364-1.118L2.97 9.373c-.783-.57-.38-1.81.588-1.81h4.149a1 1 0 00.95-.69l1.286-3.946z" />
                  </svg>
                  <span>View Rating</span>
                </a>

                <!-- View details (filled blue) -->
                <!-- <button
                  type="button"
                  class="inline-flex items-center px-3 py-1.5 rounded-lg text-xs font-medium
                         bg-blue-600 text-white hover:bg-blue-700 active:bg-blue-800
                         focus:outline-none focus:ring-2 focus:ring-blue-400 focus:ring-offset-2
                         transition-colors"
                  @click="openDetails(t)"
                >
                  <span>View Details</span>
                </button> -->
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

    <ModalNoRatingTicketDetails v-model:open="showDetails" :ticket="selected" />
  </div>
</template>
