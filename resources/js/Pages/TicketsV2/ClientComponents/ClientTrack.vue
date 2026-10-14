<template>
  <div class="flex-auto px-4 lg:px-10 py-10 pt-0">
    <div class="text-blueGray-500 text-center mb-4">
      <h3 class="text-base font-bold text-gray-800">Track Support Ticket</h3>
      <p class="text-xs text-gray-500 mt-0.5">Enter your Employee ID or Ticket Reference Number to check status and progress</p>
    </div>

    <form @submit.prevent="trackTickets(false)">
      <div class="relative w-full mb-3">
        <label for="employee_code" class="block uppercase text-blueGray-600 text-xs font-bold mb-2">
          Employee ID or Ticket Reference Number
        </label>

        <div class="relative">
          <input
            v-model.trim="employeeCode"
            type="text"
            placeholder="e.g. 1024 or 20261007-001..."
            id="employee_code"
            class="px-4 py-3 placeholder-blueGray-400 text-blueGray-700 border-2 border-sky-500
                   relative bg-white rounded-xl text-sm shadow-sm outline-none
                   focus:outline-none focus:ring-2 focus:ring-sky-400 focus:border-sky-600 w-full"
          />
          <button
            v-if="employeeCode"
            type="button"
            @click="employeeCode = ''; searched = false; tickets = []"
            class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600 text-xs font-bold"
          >
            ✕
          </button>
        </div>

        <p v-if="errorMessage" class="text-xs text-red-600 mt-1.5 font-medium">
          {{ errorMessage }}
        </p>
      </div>

      <div class="text-center mt-4">
        <button
          type="submit"
          :disabled="loading"
          class="bg-sky-600 text-white w-full
                 active:bg-sky-700 font-bold uppercase text-sm px-6 py-3 rounded-xl shadow-md
                 hover:shadow-lg hover:bg-sky-700 outline-none focus:outline-none ease-linear transition-all duration-150 disabled:opacity-60 flex items-center justify-center gap-2">
          <span v-if="loading" class="inline-block animate-spin">↻</span>
          <span v-if="loading">Searching Records…</span>
          <span v-else>Track Request</span>
        </button>
      </div>
    </form>

    <!-- Results Section -->
    <div class="mt-8" v-if="searched">
      <div v-if="tickets.length > 0" class="space-y-6">
        <!-- Single Ticket Direct View (Prominent Stepper & Highlights) -->
        <div v-if="tickets.length === 1" class="p-5 bg-white rounded-2xl border border-sky-100 shadow-md space-y-4">
          <div class="flex flex-wrap items-center justify-between gap-2 border-b pb-3">
            <div>
              <span class="text-xs text-gray-400 uppercase font-semibold">Tracking Ticket</span>
              <h4 class="text-base font-bold text-sky-900 font-mono">{{ tickets[0].client_referenceNo }}</h4>
            </div>
            <div class="flex items-center gap-2 flex-wrap">
              <span
                class="inline-block px-2.5 py-1 text-xs rounded font-bold uppercase tracking-wide border"
                :class="{
                  'text-blue-700 bg-blue-100 border-blue-200': tickets[0].ticket_status === 'New',
                  'text-amber-700 bg-amber-100 border-amber-200': tickets[0].ticket_status === 'Pending',
                  'text-emerald-700 bg-emerald-100 border-emerald-200': tickets[0].ticket_status === 'In Progress',
                  'text-purple-700 bg-purple-100 border-purple-200': tickets[0].ticket_status === 'Completed',
                  'text-slate-700 bg-slate-100 border-slate-300': tickets[0].ticket_status === 'Closed'
                }"
              >
                {{ tickets[0].ticket_status }}
              </span>
              <TicketSlaBadge :ticket="tickets[0]" compact />
            </div>
          </div>

          <!-- Visual Progress Stepper for the found ticket -->
          <div class="pt-2">
            <TicketProgressStepper :status="tickets[0].ticket_status" />
          </div>

          <!-- Completed Ticket Rating Alert -->
          <div
            v-if="tickets[0].ticket_status === 'Completed'"
            class="p-3.5 bg-gradient-to-r from-purple-50 to-indigo-50 border border-purple-200 rounded-xl flex items-center justify-between gap-3"
          >
            <div class="text-xs text-purple-900">
              <strong>Resolution complete!</strong> Please submit your service feedback to close this ticket.
            </div>
            <button
              type="button"
              @click="openRate(tickets[0].client_referenceNo)"
              class="px-4 py-2 bg-purple-700 hover:bg-purple-800 text-white rounded-lg text-xs font-bold uppercase shadow transition shrink-0"
            >
              ★ Rate Service
            </button>
          </div>
        </div>

        <!-- Table Header & Pagination Controls -->
        <div>
          <div class="flex flex-wrap items-center justify-between mb-3 gap-2">
            <h3 class="text-sm font-bold text-gray-800">
              Found <span class="text-sky-700">{{ total }} ticket(s)</span> for {{ employeeLabel }}
            </h3>

            <!-- Pagination buttons -->
            <div class="flex items-center gap-1 text-xs" v-if="totalPages > 1">
              <button
                type="button"
                @click="prevPage"
                :disabled="currentPage === 1"
                class="px-2.5 py-1 border rounded bg-white hover:bg-gray-50 disabled:opacity-40"
              >
                ‹ Prev
              </button>
              <span class="px-2 text-gray-600 font-medium">Page {{ currentPage }} / {{ totalPages }}</span>
              <button
                type="button"
                @click="nextPage"
                :disabled="currentPage === totalPages"
                class="px-2.5 py-1 border rounded bg-white hover:bg-gray-50 disabled:opacity-40"
              >
                Next ›
              </button>
            </div>
          </div>

          <!-- Table Container -->
          <div class="overflow-x-auto rounded-xl border border-gray-200 bg-white shadow-sm">
            <table class="min-w-full text-xs sm:text-sm divide-y divide-gray-200">
              <thead class="bg-gray-50 text-gray-600 font-semibold uppercase text-[11px] tracking-wider">
                <tr>
                  <th class="px-4 py-3 text-left">Reference No</th>
                  <th class="px-4 py-3 text-left">Status & SLA</th>
                  <th class="px-4 py-3 text-left">Problem</th>
                  <th class="px-4 py-3 text-right">Actions</th>
                </tr>
              </thead>
              <tbody class="divide-y divide-gray-100">
                <tr v-for="t in pageSlice" :key="t.client_referenceNo" class="hover:bg-slate-50 transition">
                  <td class="px-4 py-3 font-mono font-bold text-sky-900 whitespace-nowrap">
                    {{ t.client_referenceNo }}
                  </td>
                  <td class="px-4 py-3 whitespace-nowrap">
                    <div class="flex flex-col gap-1 items-start">
                      <span
                        class="inline-block px-2 py-0.5 text-xs rounded font-bold uppercase tracking-wide border"
                        :class="{
                          'text-blue-700 bg-blue-100 border-blue-200': t.ticket_status === 'New',
                          'text-amber-700 bg-amber-100 border-amber-200': t.ticket_status === 'Pending',
                          'text-emerald-700 bg-emerald-100 border-emerald-200': t.ticket_status === 'In Progress',
                          'text-purple-700 bg-purple-100 border-purple-200': t.ticket_status === 'Completed',
                          'text-slate-700 bg-slate-100 border-slate-300': t.ticket_status === 'Closed'
                        }"
                      >
                        {{ t.ticket_status || 'N/A' }}
                      </span>
                      <TicketSlaBadge :ticket="t" compact />
                    </div>
                  </td>
                  <td class="px-4 py-3 max-w-[260px] truncate text-gray-700" :title="t.ticket_problem_that_needed_support">
                    {{ t.ticket_problem_that_needed_support }}
                  </td>
                  <td class="px-4 py-3 text-right whitespace-nowrap">
                    <div class="inline-flex items-center gap-1.5">
                      <button
                        @click="openView(t.client_referenceNo)"
                        type="button"
                        class="bg-sky-600 hover:bg-sky-700 text-white font-bold text-xs px-3 py-1.5 rounded-lg shadow-sm transition"
                      >
                        View & Chat
                      </button>

                      <button
                        v-if="t.ticket_status === 'Completed'"
                        @click="openRate(t.client_referenceNo)"
                        type="button"
                        class="bg-purple-600 hover:bg-purple-700 text-white font-bold text-xs px-3 py-1.5 rounded-lg shadow-sm transition animate-bounce"
                      >
                        ★ Rate
                      </button>
                      <span
                        v-else-if="t.ticket_status === 'Closed'"
                        class="inline-block text-[11px] font-semibold px-2 py-1 text-slate-500 bg-slate-100 rounded"
                      >
                        ✓ Rated
                      </span>
                    </div>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>
      </div>

      <div v-else class="text-center py-10 bg-gray-50 rounded-2xl border border-dashed border-gray-300">
        <svg xmlns="http://www.w3.org/2000/svg" class="w-10 h-10 mx-auto text-gray-400 mb-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.172 16.172a4 4 0 015.656 0M9 10h.01M15 10h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
        </svg>
        <p class="text-sm font-semibold text-gray-700">No tickets found</p>
        <p class="text-xs text-gray-500 mt-1">Please double check your Employee ID or Reference Number (e.g. 20261007-001).</p>
      </div>
    </div>
  </div>

  <!-- View Details & Chat Modal -->
  <Modal_ViewDetails :show="showView" :ticket-ref="selectedRef" @close="closeView" />

  <!-- Rate Ticket Modal -->
  <Modal_RateTicket :show="showRate" :ticket-ref="selectedRef" @close="closeRate" />
</template>

<script setup>
import { ref, computed, watch, onMounted } from "vue"
import axios from "axios"
import TicketProgressStepper from "@/Components/TicketProgressStepper.vue"
import TicketSlaBadge from "@/Components/TicketSlaBadge.vue"
import Modal_ViewDetails from "@/Pages/TicketsV2/ClientComponents/ModalAction_ViewDetails.vue"
import Modal_RateTicket from "@/Pages/TicketsV2/ClientComponents/ModalAction_RateTicket.vue"

const props = defineProps({
  initialCode: { type: String, default: null },
  autoOpen: { type: Boolean, default: false },
})

const employeeCode = ref("")
const tickets = ref([])
const loading = ref(false)
const errorMessage = ref("")
const searched = ref(false)
const employee = ref(null)
const showView = ref(false)
const showRate = ref(false)
const selectedRef = ref(null)

/** Pagination state */
const perPage = ref(5)
const currentPage = ref(1)
const total = computed(() => tickets.value.length)
const totalPages = computed(() => Math.max(1, Math.ceil(total.value / perPage.value)))
const from = computed(() => (total.value === 0 ? 0 : (currentPage.value - 1) * perPage.value + 1))
const to = computed(() => Math.min(currentPage.value * perPage.value, total.value))
const pageSlice = computed(() => {
  const start = (currentPage.value - 1) * perPage.value
  return tickets.value.slice(start, start + perPage.value)
})

watch(tickets, () => {
  currentPage.value = 1
})

const nextPage = () => { if (currentPage.value < totalPages.value) currentPage.value++ }
const prevPage = () => { if (currentPage.value > 1) currentPage.value-- }

const openView = (refNo) => {
  selectedRef.value = refNo
  showView.value = true
}
const closeView = () => {
  showView.value = false
  selectedRef.value = null
}

const openRate = (refNo) => {
  selectedRef.value = refNo
  showRate.value = true
}

const closeRate = () => {
  showRate.value = false
  selectedRef.value = null
  // Re-fetch tracked tickets to reflect newly closed/rated status
  if (employeeCode.value.trim()) {
    trackTickets(false)
  }
}

const openRateTab = (refNo) => {
  const url = `/client/rate/${encodeURIComponent(refNo)}`
  window.open(url, '_blank', 'noopener')
}

const employeeLabel = computed(() => {
  if (employee.value?.name && employee.value?.code) {
    return `${employee.value.name} (${employee.value.code})`
  }
  return employeeCode.value || "Client"
})

const trackTickets = async (shouldAutoOpen = false) => {
  errorMessage.value = ""
  tickets.value = []
  employee.value = null
  searched.value = false
  currentPage.value = 1

  const q = employeeCode.value.trim()
  if (!q) {
    errorMessage.value = "Please enter an Employee ID or Ticket Reference Number."
    return
  }

  loading.value = true
  try {
    const { data } = await axios.get(`/client/track/${encodeURIComponent(q)}`)
    tickets.value = Array.isArray(data?.tickets) ? data.tickets : []
    employee.value = data?.employee || null
    searched.value = true

    if (shouldAutoOpen && tickets.value.length === 1) {
      openView(tickets.value[0].client_referenceNo)
    }
  } catch (e) {
    errorMessage.value = "No records found matching this identifier or server error."
    searched.value = true
  } finally {
    loading.value = false
  }
}

watch(() => props.initialCode, (newCode) => {
  if (newCode && newCode !== employeeCode.value) {
    employeeCode.value = newCode
    trackTickets(props.autoOpen)
  }
})

onMounted(() => {
  // Check if track query parameter or prop was provided
  const params = new URLSearchParams(window.location.search)
  const trackQuery = props.initialCode || params.get('track') || params.get('ref')
  const shouldOpen = props.autoOpen || params.get('open') === '1' || !!trackQuery
  if (trackQuery) {
    employeeCode.value = trackQuery
    trackTickets(shouldOpen)
  }
})
</script>
