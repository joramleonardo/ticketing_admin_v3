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
const perPage = 20
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
</script>

<template>
  <div class="relative flex flex-col min-w-0 break-words bg-white w-full mb-6 shadow-lg rounded">
    <div class="flex items-center justify-between px-4 py-3 border-b border-gray-200">
      <h3 class="font-semibold text-base">
        No CSM Yet — All
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
      <div class="flex items-center gap-2">
        <label class="text-[11px] text-gray-500">Month</label>
        <select v-model="month" class="text-xs border rounded px-2 py-1 bg-white">
          <option value="all">All</option>
          <option v-for="(name, idx) in monthNames" :key="idx" :value="String(idx + 1)">{{ name }}</option>
        </select>
      </div>

      <!-- NEW: checkbox multi-select months -->
      <div class="flex items-center gap-2 flex-wrap">
        <span class="text-[11px] text-gray-500">Or select months:</span>
        <div class="grid grid-cols-4 gap-x-3 gap-y-1">
          <label v-for="(name, idx) in monthNames" :key="'cb-'+idx" class="inline-flex items-center gap-1 text-xs">
            <input
              type="checkbox"
              class="rounded border-gray-300"
              :value="String(idx + 1)"
              v-model="monthsSelected"
            />
            <span>{{ name }}</span>
          </label>
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
              <a
                :href="rateUrl(t.client_referenceNo)"
                target="_blank"
                class="px-3 py-1.5 rounded-lg border text-xs hover:bg-indigo-50 border-indigo-600 text-indigo-700"
                title="Open Rate/CSM page in a new tab"
              >
                Rate / CSM
              </a>
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
