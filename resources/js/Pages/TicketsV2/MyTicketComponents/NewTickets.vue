<script setup>
import { ref, watch, computed, onMounted, onBeforeUnmount } from 'vue'
import { router, usePage } from '@inertiajs/vue3'
import { useToast } from 'vue-toastification'
import ModalTicketDetails_Pending from '@/Pages/TicketsV2/ModalComponents/ModalTicketDetails_Pending.vue'

const toast = useToast()

const props = defineProps({
  tickets: { type: Array, default: () => [] }
})
const emit = defineEmits(['attended'])

/** Local copy so we can optimistically update */
const items = ref([])
watch(
  () => props.tickets,
  (val) => { items.value = Array.isArray(val) ? [...val] : [] },
  { immediate: true, deep: true }
)

/** Pagination (5 per page) */
const perPage = 5
const currentPage = ref(1)
const total = computed(() => items.value.length)
const totalPages = computed(() => Math.max(1, Math.ceil(total.value / perPage)))
const pageSlice = computed(() => {
  const start = (currentPage.value - 1) * perPage
  return items.value.slice(start, start + perPage)
})
const from = computed(() => (total.value ? (currentPage.value - 1) * perPage + 1 : 0))
const to = computed(() => Math.min(currentPage.value * perPage, total.value))
function goTo(p){ if (p >= 1 && p <= totalPages.value) currentPage.value = p }
function prev(){ goTo(currentPage.value - 1) }
function next(){ goTo(currentPage.value + 1) }
watch(total, () => {
  if (currentPage.value > totalPages.value) currentPage.value = totalPages.value
  if (total.value === 0) currentPage.value = 1
})

/** Details modal */
const showDetails = ref(false)
const selected = ref(null)
function openDetails(row){
  selected.value = row
  showDetailsModal_Pending.value = true
  console.log('Opening details for:', row)

}

const page = usePage()
const currentUserName = page.props?.auth?.user?.name || null   // or .username / .id as needed


/** Disable the button while attending */
const attendingId = ref(null)

/** Attend Ticket + optimistic removal + partial reload */
async function attendTicketAction(ticketId) {
  if (!ticketId) {
    toast.error('No ticket ID found.')
    return
  }
  if (attendingId.value) return
  attendingId.value = ticketId

  await router.put(
    `/tickets-attend/${ticketId}`,
    {
        ticket_status: 'In Progress',
        ticket_attended_by: currentUserName,
    },
    {
      preserveScroll: true,
      onSuccess: (page) => {
        // 1) Optimistic UI: remove the ticket from local list
        items.value = items.value.filter(t => t.ticket_id !== ticketId)

        // 2) Notify + emit
        const flashMsg = page?.props?.flash?.success || 'Ticket attended successfully and now in progress!'
        toast.success(flashMsg)
        router.reload({
           only: ['myNewTickets','myInProgTickets','ticketStats'],
          preserveScroll: true,
           preserveState: true,
           onFinish: () => {
             attendingId.value = null
             // 👉 Now tell the parent to refresh the table
             emit('attended', { ticketId })
             // (optional) also fire your global event
             window.dispatchEvent(new CustomEvent('tickets:attended', { detail: { ticketId } }))
           }
         })
      },
      onError: () => {
        attendingId.value = null
        toast.error('Failed to attend ticket.')
      },
    }
  )
}


const showDetailsModal_Pending = ref(false)


function closeDetailsModal_Pending() { showDetailsModal_Pending.value = false; selected.value = null }

function handleTicketUpdated(data) {
  router.reload({
    only: ['myNewTickets', 'myInProgTickets', 'ticketStats'],
    preserveScroll: true,
    preserveState: true,
    onFinish: () => {
      if (data?.status === 'In Progress') {
        emit('attended', { ticketId: data.id })
      }
    }
  })
}

function onAttended() {

}

onMounted(() => window.addEventListener('tickets:attended', onAttended))
onBeforeUnmount(() => window.removeEventListener('tickets:attended', onAttended))

</script>

<template>
  <div class="relative flex flex-col min-w-0 break-words w-full">
    <!-- Subheader with queue label & count -->
    <div class="flex items-center justify-between px-4 py-2.5 bg-slate-50/70 border-b border-slate-200">
      <div class="flex items-center gap-2">
        <span class="w-2 h-2 rounded-full bg-amber-500"></span>
        <span class="text-xs font-bold text-slate-800">Pending Tickets</span>
      </div>
      <span class="text-[11px] font-semibold text-slate-500">
        {{ total }} ticket{{ total === 1 ? '' : 's' }}
      </span>
    </div>

    <div class="block w-full overflow-x-auto">
      <table class="items-center w-full bg-transparent border-collapse">
        <thead>
          <tr class="bg-slate-50 text-slate-500 border-b border-slate-200">
            <th class="px-4 py-2.5 text-xs uppercase font-semibold text-left">Reference No.</th>
            <th class="px-4 py-2.5 text-xs uppercase font-semibold text-left">Client Name</th>
            <th class="px-4 py-2.5 text-xs uppercase font-semibold text-right">Action</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-slate-100">
          <tr v-if="total === 0">
            <td colspan="3" class="px-4 py-10 text-center">
              <div class="w-10 h-10 rounded-full bg-slate-100 text-slate-400 mx-auto flex items-center justify-center text-base mb-2">
                <i class="fa-solid fa-clipboard-check"></i>
              </div>
              <p class="text-xs font-semibold text-slate-700">No new tickets assigned to you.</p>
              <p class="text-[11px] text-slate-400 mt-0.5">Tickets assigned to you will appear here immediately.</p>
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
                  title="Attend Ticket & Notify Client"
                  class="inline-flex items-center gap-1 px-2.5 py-1.5 rounded-lg bg-amber-500 hover:bg-amber-600 text-white font-semibold text-xs shadow-2xs transition disabled:opacity-50"
                  :disabled="attendingId === t.ticket_id"
                  @click="attendTicketAction(t.ticket_id)"
                >
                  <i v-if="attendingId !== t.ticket_id" class="fa-solid fa-user-check text-xs"></i>
                  <i v-else class="fa-solid fa-spinner fa-spin text-xs"></i>
                  <span>Attend</span>
                </button>
              </div>
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- Footer: pagination -->
    <div v-if="total > 0" class="px-4 py-2.5 border-t border-slate-200 bg-slate-50/50 flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
      <div class="text-[11px] text-slate-500">
        Showing <span class="font-semibold text-slate-700">{{ from }}</span>–<span class="font-semibold text-slate-700">{{ to }}</span> of
        <span class="font-semibold text-slate-700">{{ total }}</span>
      </div>

      <div class="flex items-center gap-1">
        <button class="px-2 py-1 text-xs border border-slate-200 rounded-md bg-white hover:bg-slate-50 disabled:opacity-40" :disabled="currentPage === 1" @click="prev">Prev</button>
        <button
          v-for="p in totalPages"
          :key="p"
          class="px-2 py-1 text-xs border rounded-md font-medium"
          :class="p === currentPage ? 'bg-blue-600 border-blue-600 text-white' : 'border-slate-200 bg-white text-slate-700 hover:bg-slate-50'"
          @click="goTo(p)"
        >
          {{ p }}
        </button>
        <button class="px-2 py-1 text-xs border border-slate-200 rounded-md bg-white hover:bg-slate-50 disabled:opacity-40" :disabled="currentPage === totalPages" @click="next">Next</button>
      </div>
    </div>

    <!-- Details modal -->
    <ModalTicketDetails_Pending
      :show="showDetailsModal_Pending"
      :ticket="selected"
      :from-my-ticket="true"
      @close="closeDetailsModal_Pending"
      @updated="handleTicketUpdated"
    />
  </div>
</template>
