<script setup>
import { ref, computed, watch } from 'vue'
import { router } from '@inertiajs/vue3'
import ModalNoRatingTicketDetails from '@/Pages/TicketsV2/ModalComponents/ModalTicketDetails_NoRating.vue'

const props = defineProps({
  // Completed tickets with NO row yet in v2_ticket_rating_details
  pendingTickets: { type: Array, default: () => [] },
})

/* ── Pagination ───────────────────────────────────────────── */
const perPage = 5
const currentPage = ref(1)

/* ── Filters (month/year) ─────────────────────────────────── */
const now = new Date()
const month = ref(String(now.getMonth() + 1)) // e.g. "9"
const year  = ref(String(now.getFullYear()))  // e.g. "2025"
// const month = ref('all')
// const year  = ref('all')

const monthNames = [
  'January','February','March','April','May','June',
  'July','August','September','October','November','December'
]

// Reset page when list or filters change
watch([() => props.pendingTickets, month, year], () => { currentPage.value = 1 })

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

/* ── Base + filtered lists ────────────────────────────────── */
const base = computed(() => props.pendingTickets || [])

// Build Year options dynamically from data (descending)
const yearOptions = computed(() => {
  const set = new Set()
  for (const t of base.value) {
    const p = parseYMDParts(t?.date_completed)
    if (p) set.add(p.y)
  }
  return Array.from(set).sort((a,b) => b - a)
})

const filtered = computed(() => {
  const m = month.value === 'all' ? null : Number(month.value)
  const y = year.value  === 'all' ? null : Number(year.value)
  if (!m && !y) return base.value

  return base.value.filter(t => {
    const p = parseYMDParts(t?.date_completed)
    if (!p) return false
    if (y && p.y !== y) return false
    if (m && p.m !== m) return false
    return true
  })
})

/* ── Paging derived from filtered list ────────────────────── */
const totalPages = computed(() => Math.max(1, Math.ceil(filtered.value.length / perPage)))
const startIndex = computed(() => (currentPage.value - 1) * perPage)
const pageSlice  = computed(() => filtered.value.slice(startIndex.value, startIndex.value + perPage))

function goTo(p) {
  if (p < 1 || p > totalPages.value) return
  currentPage.value = p
}

/* ── Compact pager items (with ellipses) ──────────────────── */
const pages = computed(() => {
  const total = totalPages.value
  const cur = currentPage.value
  const items = []
  const add = (p) => items.push({ type: 'page', value: p })
  const gap = () => items.push({ type: 'gap' })

  if (total <= 7) {
    for (let p = 1; p <= total; p++) add(p)
  } else {
    add(1)
    if (cur > 4) gap()
    const start = Math.max(2, cur - 1)
    const end   = Math.min(total - 1, cur + 1)
    for (let p = start; p <= end; p++) add(p)
    if (cur < total - 3) gap()
    add(total)
  }
  return items
})

/* ── Modal + refresh ──────────────────────────────────────── */
const selected = ref(null)
const showDetails = ref(false)
function openDetails(ticket) { selected.value = ticket; showDetails.value = true }

function refresh() {
  router.reload({ only: ['noRatingTickets'], preserveScroll: true })
  currentPage.value = 1
}
</script>

<template>
  <div class="relative flex flex-col min-w-0 break-words w-full">
    <!-- Subheader with filters -->
    <div class="flex items-center justify-between px-4 py-2.5 bg-slate-50/70 border-b border-slate-200 text-xs gap-2 flex-wrap">
      <div class="flex items-center gap-2">
        <span class="w-2 h-2 rounded-full bg-purple-600"></span>
        <span class="font-bold text-slate-800">Pending Rating</span>
        <span class="text-[11px] font-semibold text-slate-500">({{ filtered.length }})</span>
      </div>

      <!-- Filters -->
      <div class="flex items-center gap-2">
        <label class="text-[11px] text-slate-500 font-medium">Month</label>
        <select v-model="month" class="text-xs border border-slate-200 rounded-lg px-2 py-1 bg-white text-slate-700 focus:ring-1 focus:ring-blue-500">
          <option value="all">All</option>
          <option v-for="(name, idx) in monthNames" :key="idx" :value="String(idx + 1)">
            {{ name }}
          </option>
        </select>

        <label class="text-[11px] text-slate-500 font-medium ml-1">Year</label>
        <select v-model="year" class="text-xs border border-slate-200 rounded-lg px-2 py-1 bg-white text-slate-700 focus:ring-1 focus:ring-blue-500">
          <option value="all">All</option>
          <option v-for="y in yearOptions" :key="y" :value="String(y)">{{ y }}</option>
        </select>
      </div>
    </div>

    <div class="block w-full overflow-x-auto">
      <table class="items-center w-full bg-transparent border-collapse">
        <thead>
          <tr class="bg-slate-50 text-slate-500 border-b border-slate-200">
            <th class="px-4 py-2.5 text-xs uppercase font-semibold text-left">
              Reference No.
            </th>
            <th class="px-4 py-2.5 text-xs uppercase font-semibold text-left">
              Client Name
            </th>
            <th class="px-4 py-2.5 text-xs uppercase font-semibold text-right">
              Action
            </th>
          </tr>
        </thead>

        <tbody class="divide-y divide-slate-100">
          <tr v-if="filtered.length === 0">
            <td colspan="3" class="px-4 py-10 text-center">
              <div class="w-10 h-10 rounded-full bg-slate-100 text-slate-400 mx-auto flex items-center justify-center text-base mb-2">
                <i class="fa-solid fa-star"></i>
              </div>
              <p class="text-xs font-semibold text-slate-700">All completed tickets have been rated!</p>
              <p class="text-[11px] text-slate-400 mt-0.5">No pending ratings found for the selected month/year.</p>
            </td>
          </tr>

          <tr v-for="t in pageSlice" :key="t.client_referenceNo" class="hover:bg-purple-50/40 transition-colors">
            <td class="px-4 py-2.5 text-xs font-semibold text-slate-800 whitespace-nowrap">
              {{ t.client_referenceNo }}
            </td>
            <td class="px-4 py-2.5 text-xs text-slate-600 truncate max-w-[150px]">
              {{ t.client_name || '-' }}
            </td>
            <td class="px-4 py-2.5 text-xs text-right whitespace-nowrap">
              <button
                type="button"
                class="inline-flex items-center gap-1 px-2.5 py-1.5 rounded-lg border border-purple-200 text-purple-700 bg-purple-50 hover:bg-purple-100 font-semibold text-xs transition"
                @click="openDetails(t)"
              >
                <i class="fa-solid fa-eye text-xs"></i>
                <span>Details</span>
              </button>
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- Pagination -->
    <div v-if="filtered.length > perPage" class="px-4 py-2.5 border-t border-slate-200 bg-slate-50/50 flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
      <div class="text-[11px] text-slate-500">
        Showing <span class="font-semibold text-slate-700">{{ startIndex + 1 }}</span>–<span class="font-semibold text-slate-700">{{ Math.min(startIndex + perPage, filtered.length) }}</span> of
        <span class="font-semibold text-slate-700">{{ filtered.length }}</span>
      </div>

      <div class="flex items-center gap-1 overflow-x-auto max-w-full whitespace-nowrap">
        <button
          class="px-2 py-1 text-xs border border-slate-200 rounded-md bg-white hover:bg-slate-50 disabled:opacity-40"
          :disabled="currentPage === 1"
          @click="goTo(currentPage - 1)"
        >Prev</button>

        <template v-for="(it, idx) in pages" :key="idx">
          <button
            v-if="it.type === 'page'"
            class="px-2 py-1 text-xs border rounded-md font-medium"
            :class="it.value === currentPage ? 'bg-purple-600 border-purple-600 text-white' : 'border-slate-200 bg-white text-slate-700 hover:bg-slate-50'"
            @click="goTo(it.value)"
          >{{ it.value }}</button>
          <span v-else class="px-2 text-xs text-slate-400 select-none">…</span>
        </template>

        <button
          class="px-2 py-1 text-xs border border-slate-200 rounded-md bg-white hover:bg-slate-50 disabled:opacity-40"
          :disabled="currentPage === totalPages"
          @click="goTo(currentPage + 1)"
        >Next</button>
      </div>
    </div>

    <ModalNoRatingTicketDetails v-model:open="showDetails" :ticket="selected" />
  </div>
</template>
