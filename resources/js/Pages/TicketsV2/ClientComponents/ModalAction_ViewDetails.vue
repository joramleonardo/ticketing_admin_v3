<script setup>
import { ref, watch, computed } from "vue"
import axios from "axios"
import TicketProgressStepper from "@/Components/TicketProgressStepper.vue"
import TicketSlaBadge from "@/Components/TicketSlaBadge.vue"
import TicketMessageThread from "@/Components/TicketMessageThread.vue"

const props = defineProps({
  show: { type: Boolean, default: false },
  ticketRef: { type: String, default: null },
})
const emit = defineEmits(["close"])

const loading = ref(false)
const error = ref("")
const details = ref(null)

const loadDetails = async () => {
  if (!props.ticketRef) return
  error.value = ""
  loading.value = true
  try {
    const { data: d } = await axios.get(`/client/ticket/${encodeURIComponent(props.ticketRef)}`)
    details.value = d
  } catch (e) {
    error.value = "Failed to load ticket details."
  } finally {
    loading.value = false
  }
}

const openRatePage = () => {
  if (!props.ticketRef) return
  window.open(`/client/rate/${encodeURIComponent(props.ticketRef)}`, '_blank', 'noopener')
}

watch(
  () => props.show,
  (v) => { if (v) loadDetails() }
)
watch(
  () => props.ticketRef,
  () => { if (props.show) loadDetails() }
)
</script>

<template>
  <div v-if="show" class="fixed inset-0 z-50 flex items-center justify-center p-4">
    <!-- Overlay backdrop -->
    <div class="absolute inset-0 bg-black/50 backdrop-blur-sm" @click="$emit('close')"></div>

    <!-- Modal Card -->
    <div class="relative bg-white rounded-2xl shadow-2xl w-full max-w-4xl max-h-[90vh] flex flex-col overflow-hidden">
      <!-- Modal Header -->
      <div class="flex items-center justify-between px-6 py-4 bg-gradient-to-r from-sky-900 to-blue-900 text-white">
        <div class="flex items-center gap-3">
          <div class="w-8 h-8 rounded-lg bg-white/10 flex items-center justify-center">
            <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
            </svg>
          </div>
          <div>
            <h3 class="text-base font-bold">Ticket Progress & Details</h3>
            <p class="text-xs text-sky-200 font-mono">{{ details?.client_referenceNo || ticketRef }}</p>
          </div>
        </div>

        <button
          class="w-8 h-8 rounded-full bg-white/10 hover:bg-white/20 text-white flex items-center justify-center transition"
          @click="$emit('close')"
        >
          ✕
        </button>
      </div>

      <!-- Modal Body (Scrollable) -->
      <div class="p-6 overflow-y-auto space-y-6 flex-1">
        <div v-if="loading" class="flex items-center justify-center py-12 text-sm text-gray-500">
          <svg class="animate-spin -ml-1 mr-3 h-5 w-5 text-sky-600" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"></path>
          </svg>
          Loading ticket status and messages…
        </div>

        <div v-else-if="details" class="space-y-6">
          <div v-if="error" class="text-sm text-red-600 p-3 bg-red-50 rounded-lg">{{ error }}</div>

          <!-- 1. Lifecycle Progress Stepper -->
          <div class="p-4 bg-slate-50 border border-gray-200 rounded-xl">
            <TicketProgressStepper :status="details.ticket_status" />
          </div>

          <!-- Service Rating Callout if Completed -->
          <div
            v-if="details.ticket_status === 'Completed'"
            class="p-4 rounded-xl bg-gradient-to-r from-purple-50 to-indigo-50 border border-purple-200 flex flex-col sm:flex-row sm:items-center justify-between gap-4"
          >
            <div>
              <h4 class="font-bold text-sm text-purple-900 flex items-center gap-1.5">
                <span>★</span>
                <span>Your Request Has Been Resolved!</span>
              </h4>
              <p class="text-xs text-purple-700 mt-0.5">
                Help us improve DOST-STII IT support by providing a quick 1-minute satisfaction rating.
              </p>
            </div>
            <button
              type="button"
              @click="openRatePage"
              class="inline-flex items-center justify-center gap-2 px-5 py-2.5 rounded-lg bg-purple-700 hover:bg-purple-800 text-white font-bold text-xs uppercase shadow transition shrink-0"
            >
              <span>Rate Service Now</span>
              <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3" />
              </svg>
            </button>
          </div>

          <!-- 2. Ticket Details Summary Grid -->
          <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <div class="p-3.5 rounded-xl border bg-white shadow-sm">
              <div class="text-[11px] font-semibold uppercase tracking-wider text-gray-400">Current Status</div>
              <div class="flex items-center gap-2 mt-1.5 flex-wrap">
                <span class="font-bold text-sm text-gray-800">{{ details.ticket_status }}</span>
                <TicketSlaBadge :ticket="details" compact />
              </div>
            </div>

            <div class="p-3.5 rounded-xl border bg-white shadow-sm">
              <div class="text-[11px] font-semibold uppercase tracking-wider text-gray-400">Request Type</div>
              <div class="font-bold text-sm text-gray-800 mt-1.5">{{ details.ticket_type }}</div>
            </div>

            <div class="p-3.5 rounded-xl border bg-white shadow-sm">
              <div class="text-[11px] font-semibold uppercase tracking-wider text-gray-400">Client / Requested By</div>
              <div class="font-bold text-sm text-gray-800 mt-1.5 truncate">{{ details.client_name }}</div>
            </div>

            <div class="p-3.5 rounded-xl border bg-white shadow-sm md:col-span-3">
              <div class="text-[11px] font-semibold uppercase tracking-wider text-gray-400">Problem Description</div>
              <div class="text-sm text-gray-800 mt-1.5 whitespace-pre-wrap leading-relaxed">
                {{ details.ticket_problem_that_needed_support }}
              </div>
            </div>

            <div class="p-3.5 rounded-xl border bg-white shadow-sm">
              <div class="text-[11px] font-semibold uppercase tracking-wider text-gray-400">Date Requested</div>
              <div class="text-xs text-gray-700 mt-1">{{ details.date_requested || "—" }}</div>
            </div>

            <div class="p-3.5 rounded-xl border bg-white shadow-sm">
              <div class="text-[11px] font-semibold uppercase tracking-wider text-gray-400">Date Attended</div>
              <div class="text-xs text-gray-700 mt-1">{{ details.date_attended || "—" }}</div>
            </div>

            <div class="p-3.5 rounded-xl border bg-white shadow-sm">
              <div class="text-[11px] font-semibold uppercase tracking-wider text-gray-400">Date Completed</div>
              <div class="text-xs text-gray-700 mt-1">{{ details.date_completed || "—" }}</div>
            </div>
          </div>

          <!-- 3. Direct Two-Way Communication Thread -->
          <div>
            <TicketMessageThread
              :client-reference-no="details.client_referenceNo"
              sender-role="client"
              :sender-name="details.client_name"
            />
          </div>
        </div>
      </div>

      <!-- Modal Footer -->
      <div class="flex items-center justify-between px-6 py-4 bg-gray-50 border-t">
        <span class="text-xs text-gray-500">Need immediate help? Contact MISPS Technical Support directly.</span>
        <button
          type="button"
          class="px-5 py-2 text-xs font-semibold rounded-lg border border-gray-300 text-gray-700 hover:bg-gray-100 transition"
          @click="$emit('close')"
        >
          Close
        </button>
      </div>
    </div>
  </div>
</template>
