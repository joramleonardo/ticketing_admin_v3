<script setup>
import { ref, computed, watch } from 'vue'
import { router } from '@inertiajs/vue3'
import ModalNoRatingTicketDetails from '@/Pages/TicketsV2/ModalComponents/ModalTicketDetails_NoRating.vue'
import ModalCompleteTicket from '@/Pages/TicketsV2/ModalComponents/ModalAction_CompleteTicket.vue'
import ModalTicketDetails_Pending from '@/Pages/TicketsV2/ModalComponents/ModalTicketDetails_InProgress.vue'


const emit = defineEmits(['completed'])   // 👈 add this

const props = defineProps({
  tickets: {
    type: Array,
    default: () => []
  }
})

const tickets = computed(() => props.tickets || [])
const perPage = 5
const currentPage = ref(1)

const total = computed(() => tickets.value.length)
const totalPages = computed(() => Math.max(1, Math.ceil(total.value / perPage)))

const pageSlice = computed(() => {
  const start = (currentPage.value - 1) * perPage
  return tickets.value.slice(start, start + perPage)
})

const from = computed(() => total.value === 0 ? 0 : (currentPage.value - 1) * perPage + 1)
const to = computed(() => Math.min(currentPage.value * perPage, total.value))

function goTo(p) {
  if (p < 1 || p > totalPages.value) return
  currentPage.value = p
}
function goFirst() { goTo(1) }
function goPrev()  { goTo(currentPage.value - 1) }
function goNext()  { goTo(currentPage.value + 1) }
function goLast()  { goTo(totalPages.value) }

// keep current page valid when data changes
watch(() => total.value, () => {
  if (currentPage.value > totalPages.value) currentPage.value = totalPages.value
  if (total.value === 0) currentPage.value = 1
})

// show a compact page list: 1 … c-1 c c+1 … N
const pageButtons = computed(() => {
  const t = totalPages.value
  const c = currentPage.value
  if (t <= 7) return Array.from({ length: t }, (_, i) => i + 1)
  const pages = new Set([1, 2, t - 1, t, c - 1, c, c + 1])
  const arr = [...pages].filter(p => p >= 1 && p <= t).sort((a, b) => a - b)
  // insert ellipses
  const out = []
  for (let i = 0; i < arr.length; i++) {
    out.push(arr[i])
    if (i < arr.length - 1 && arr[i + 1] !== arr[i] + 1) out.push('…')
  }
  return out
})

const selected = ref(null)
const showDetails = ref(false)
function openDetails(row){
  selected.value = row
  showDetailsModal_Pending.value = true
  console.log('Opening details for:', row)

}

function refresh() {
  router.reload({ only: ['myInProgTickets'] })
  currentPage.value = 1
}

// ── Complete Ticket ───────────────────────────
const showCompleteModal = ref(false)
const selectedTicket = ref(null)
function openCompleteModal(row) {
    console.log('Opening Complete Ticket modal for:', selectedTicket)
    selectedTicket.value = row
    showCompleteModal.value = true
}
const staffOptions = [
  'Etienne Wayne Amparado',
  'Mc Gyver Basaya',
  'Michael Binondo',
  'John Michael Cagadas',
  'Chester Francisco',
  'Aerone Dominhick Sebastian',
  'Joram Kate Leonardo',
  'Lloyd Mandapat',
  'Jomar Rabanera',
  'Mark Jayson Sison'
]

        function handleCompleteSuccess({ id }) {
          showCompleteModal.value = false
          // tell the page parent to refresh everything (NewTickets + AllMyTickets)
          emit('completed', { id })
        }

const showDetailsModal_Pending = ref(false)


function closeDetailsModal_Pending() { showDetailsModal_Pending.value = false; selected.value = null }

</script>

<template>
  <div class="relative flex flex-col min-w-0 break-words w-full">
    <!-- Subheader with queue label, count & refresh -->
    <div class="flex items-center justify-between px-4 py-2.5 bg-slate-50/70 border-b border-slate-200">
      <div class="flex items-center gap-2">
        <span class="w-2 h-2 rounded-full bg-blue-600 animate-pulse"></span>
        <span class="text-xs font-bold text-slate-800">In Progress Tickets</span>
      </div>
      <div class="flex items-center gap-2">
        <span class="text-[11px] font-semibold text-slate-500">{{ total }} ticket{{ total === 1 ? '' : 's' }}</span>
        <button
          type="button"
          class="text-[11px] text-blue-600 hover:text-blue-800 font-semibold flex items-center gap-1 cursor-pointer transition"
          @click="refresh"
          title="Refresh in-progress queue"
        >
          <i class="fa-solid fa-arrows-rotate text-[10px]"></i>
          <span>Refresh</span>
        </button>
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
          <tr v-if="total === 0">
            <td colspan="3" class="px-4 py-10 text-center">
              <div class="w-10 h-10 rounded-full bg-slate-100 text-slate-400 mx-auto flex items-center justify-center text-base mb-2">
                <i class="fa-solid fa-screwdriver-wrench"></i>
              </div>
              <p class="text-xs font-semibold text-slate-700">No in progress tickets.</p>
              <p class="text-[11px] text-slate-400 mt-0.5">Tickets you attend will appear here for active troubleshooting.</p>
            </td>
          </tr>

          <tr v-for="t in pageSlice" :key="t.client_referenceNo" class="hover:bg-blue-50/40 transition-colors">
            <td class="px-4 py-2.5 text-xs font-semibold text-slate-800 whitespace-nowrap">
              {{ t.client_referenceNo }}
            </td>
            <td class="px-4 py-2.5 text-xs text-slate-600 truncate max-w-[150px]">
              {{ t.client_name || '-' }}
            </td>
            <td class="px-4 py-2.5 text-xs text-right whitespace-nowrap">
              <div class="inline-flex items-center gap-1.5 justify-end">
                <button
                  type="button"
                  title="View Details"
                  class="p-1.5 rounded-lg border border-slate-300 text-slate-600 hover:bg-blue-50 hover:text-blue-600 hover:border-blue-300 transition"
                  @click="openDetails(t)"
                >
                  <i class="fa-solid fa-eye text-xs"></i>
                </button>

                <button
                  type="button"
                  title="Mark as Complete"
                  class="inline-flex items-center gap-1 px-2.5 py-1.5 rounded-lg bg-emerald-600 hover:bg-emerald-700 text-white font-semibold text-xs shadow-2xs transition"
                  @click="openCompleteModal(t)"
                >
                  <i class="fa-solid fa-circle-check text-xs"></i>
                  <span>Complete</span>
                </button>
              </div>
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- Footer: range + pagination -->
    <div v-if="total > 0" class="px-4 py-2.5 border-t border-slate-200 bg-slate-50/50 flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
      <div class="text-[11px] text-slate-500">
        Showing <span class="font-semibold text-slate-700">{{ from }}</span>–<span class="font-semibold text-slate-700">{{ to }}</span> of
        <span class="font-semibold text-slate-700">{{ total }}</span>
      </div>

      <div class="flex items-center gap-1">
        <button
          class="px-2 py-1 text-xs border border-slate-200 rounded-md bg-white hover:bg-slate-50 disabled:opacity-40"
          :disabled="currentPage === 1"
          @click="goFirst"
          aria-label="First page"
        >« First</button>

        <button
          class="px-2 py-1 text-xs border border-slate-200 rounded-md bg-white hover:bg-slate-50 disabled:opacity-40"
          :disabled="currentPage === 1"
          @click="goPrev"
          aria-label="Previous page"
        >‹ Prev</button>

        <template v-for="p in pageButtons" :key="`p-${p}`">
          <button
            v-if="p !== '…'"
            class="px-2 py-1 text-xs border rounded-md font-medium"
            :class="p === currentPage ? 'bg-blue-600 border-blue-600 text-white' : 'border-slate-200 bg-white text-slate-700 hover:bg-slate-50'"
            @click="goTo(p)"
          >{{ p }}</button>
          <span v-else class="px-2 py-1 text-xs text-slate-400 select-none">…</span>
        </template>

        <button
          class="px-2 py-1 text-xs border border-slate-200 rounded-md bg-white hover:bg-slate-50 disabled:opacity-40"
          :disabled="currentPage === totalPages"
          @click="goNext"
          aria-label="Next page"
        >Next ›</button>

        <button
          class="px-2 py-1 text-xs border border-slate-200 rounded-md bg-white hover:bg-slate-50 disabled:opacity-40"
          :disabled="currentPage === totalPages"
          @click="goLast"
          aria-label="Last page"
        >Last »</button>
      </div>
    </div>

    <ModalTicketDetails_Pending
      :show="showDetailsModal_Pending"
      :ticket="selected"
      :from-my-ticket="true"
      @close="closeDetailsModal_Pending"
    />
  </div>

  <!-- Complete Ticket modal -->
  <ModalCompleteTicket
    :show="showCompleteModal"
    :ticket="selectedTicket"
    :staff-list="staffOptions"
    :subcategories="{
      Hardware: ['Troubleshooting','Evaluation','Network','Testing','Consultation','Assistance','Others'],
      Software: ['Installation','Data Retrieval','Virus/Malwares','Data Conversion/Uploading/Processing','Reformat','Download','Assistance','Others'],
      'Hardware & Software': ['Troubleshooting','Evaluation','Network','Testing','Consultation','Assistance','Installation','Data Retrieval','Virus/Malwares','Data Conversion/Uploading/Processing','Reformat','Download','Assistance','Others'],
      'System Modification': []
    }"
    @close="showCompleteModal = false"
    @success="handleCompleteSuccess"
  />
</template>
