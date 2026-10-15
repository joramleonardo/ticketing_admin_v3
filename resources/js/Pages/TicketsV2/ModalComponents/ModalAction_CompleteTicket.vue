<!-- ModalAction_CompleteTicket.vue -->
<script setup>
import { ref, reactive, computed, watch } from 'vue'
import { router } from '@inertiajs/vue3'
import { useToast } from 'vue-toastification'
import Modal from '@/Components/Modal.vue'
import { useStaffList } from '@/Composables/useStaffList'
import {
  TICKET_TYPES,
  SUPPORT_NATURES,
  SUBCATEGORIES_BY_NATURE,
  RELATED_TYPES,
  REMARKS_OPTIONS,
} from '@/Support/ticketClassifications'

const toast = useToast()
const { staffOptions: dynamicStaff } = useStaffList()

const DEFAULT_REMARKS = 'N/A'
const OTHER_REMARKS = 'Others'
const remarksOptions = REMARKS_OPTIONS

const props = defineProps({
  show: { type: Boolean, default: false },
  ticket: { type: Object, default: null },
  staffList: { type: Array, default: () => [] },
  subcategories: {
    type: Object,
    default: () => SUBCATEGORIES_BY_NATURE,
  },
  supportTypes: {
    type: Array,
    default: () => TICKET_TYPES,
  },
  relatedTypes: {
    type: Array,
    default: () => RELATED_TYPES,
  },
})

const emit = defineEmits(['close', 'success'])

const completionForm = reactive({
  // request type + basics
  supportType: 'Technical Support',
  nature: '',
  subCategory: '',
  relatedType: '',
  assistedBy: [],
  problem: '',
  actionTaken: '',
  remarksOption: DEFAULT_REMARKS,
  customRemarks: '',

  // events-only fields (v2_ticket_events)
  events: {
    startDate: '',   // date (required if Events)
    endDate: '',     // date (optional)
    venue: '',       // string (required if Events)
    remarks: '',     // text (optional)
    otherRequest: '', // text (optional)
  },
})

const submitting = ref(false)
const errorMsg = ref('')

const availableStaffList = computed(() => {
  return (props.staffList && props.staffList.length > 0)
    ? props.staffList
    : dynamicStaff.value
})

const isLivestream = computed(() => completionForm.supportType === 'Livestream')
const isEvents = computed(() => completionForm.supportType === 'Events')
const isTechnical = computed(() => completionForm.supportType === 'Technical Support')
const isCustomRemarks = computed(() => completionForm.remarksOption === OTHER_REMARKS)
const ticketReference = computed(() => readTicketRef() || 'No reference')

// Nature options
const fullNatureList = [
  'Events',
  'Hardware',
  'Software',
  'Hardware & Software',
  'System Modification',
  'Livestream',
  'Others',
]
const natureOptions = computed(() => {
  if (isLivestream.value) return ['Livestream']
  if (isEvents.value) return ['Events']
  return fullNatureList
})

// Technical Support: Support Type shows ALL options regardless of Nature
const allSupportTypeOptions = computed(() => {
  const set = new Set()
  Object.values(props.subcategories).forEach(arr => (arr || []).forEach(x => x && set.add(x)))
  return Array.from(set)
})

// Safely read event values from various ticket shapes
function readEventField(name) {
  // name is 'start_date' | 'end_date' | 'venue' | 'remarks' | 'other_request'
  const withPrefix = `event_${name}`

  return (
    // nested event object, plain names (start_date)
    props.ticket?.event?.[name] ??
    props.ticket?.detail?.event?.[name] ??

    // nested event object, prefixed names (event_start_date)
    props.ticket?.event?.[withPrefix] ??
    props.ticket?.detail?.event?.[withPrefix] ??

    // camelCase variants (otherRequest)
    props.ticket?.event?.[name === 'other_request' ? 'otherRequest' : name] ??
    props.ticket?.detail?.event?.[name === 'other_request' ? 'otherRequest' : name] ??

    // sibling shapes without nesting
    props.ticket?.events?.[name] ??
    props.ticket?.detail?.events?.[name] ??
    props.ticket?.[withPrefix] ??
    props.ticket?.detail?.[withPrefix] ??
    ''
  )
}
function readTicketRef() {
  return (
    props.ticket?.client_referenceNo ??
    props.ticket?.detail?.client_referenceNo ??
    ''
  )
}

// Prefill when opening / ticket changes
watch(
  () => [props.show, props.ticket?.detail?.ticket_id],
  () => {
    if (!props.show || !props.ticket) return

    completionForm.supportType =
      props.ticket.ticket_type || props.ticket.detail?.ticket_type || 'Technical Support'

    completionForm.relatedType =
      props.ticket.ticket_related_type ||
      props.ticket.detail?.ticket_related_type ||
      ''

    completionForm.problem =
      props.ticket.ticket_problem_that_needed_support ||
      props.ticket.detail?.ticket_problem_that_needed_support ||
      ''

    // Reset basics
    completionForm.nature = ''
    completionForm.subCategory = ''
    completionForm.assistedBy = []
    completionForm.actionTaken = ''
    applyRemarksValue(
      props.ticket.ticket_remarks ||
      props.ticket.detail?.ticket_remarks ||
      DEFAULT_REMARKS
    )
    errorMsg.value = ''

    // Prefill events block from v2_ticket_events
    completionForm.events.startDate    = readEventField('start_date') || ''
    completionForm.events.endDate      = readEventField('end_date') || ''
    completionForm.events.venue        = readEventField('venue') || ''
    completionForm.events.remarks      = readEventField('remarks') || ''
    completionForm.events.otherRequest = readEventField('other_request') || ''

    applyTypeRules(completionForm.supportType, /*isInitial*/ true)
  },
  { immediate: false }
)

// Apply rules whenever Request Type changes
watch(
  () => completionForm.supportType,
  (val) => applyTypeRules(val, /*isInitial*/ false),
  { immediate: true }
)

function applyTypeRules(type, isInitial) {
  if (type === 'Livestream') {
    completionForm.nature = 'Livestream'
    completionForm.subCategory = ''
    if (!completionForm.relatedType || !isInitial) {
      completionForm.relatedType = 'Livestream related'
    }
  } else if (type === 'Events') {
    completionForm.nature = 'Events'
    completionForm.subCategory = ''
    if (!completionForm.relatedType || !isInitial) {
      completionForm.relatedType = 'Events related'
    }
    // keep events fields as prefilled/typed by user
  } else {
    if (completionForm.nature === 'Livestream' || completionForm.nature === 'Events') {
      completionForm.nature = ''
    }
    completionForm.subCategory = ''
  }
}

// Validation
const canSubmit = computed(() => {
  if (!completionForm.actionTaken?.trim()) return false
  if (isCustomRemarks.value && !completionForm.customRemarks?.trim()) return false

  // Events: require startDate & venue
  if (isEvents.value) {
    if (!completionForm.events.startDate) return false
    if (!completionForm.events.venue?.trim()) return false
    if (completionForm.events.endDate && completionForm.events.endDate < completionForm.events.startDate) return false
    return true
  }

  // Livestream: no support type required
  if (isLivestream.value) return true

  // Technical Support
  if (!completionForm.nature) return false
  if (completionForm.nature !== 'System Modification' && !completionForm.subCategory) return false
  return true
})

function closeModal() { emit('close') }

function applyRemarksValue(value) {
  const remarks = String(value || DEFAULT_REMARKS).trim()
  if (remarksOptions.includes(remarks)) {
    completionForm.remarksOption = remarks
    completionForm.customRemarks = ''
    return
  }

  completionForm.remarksOption = OTHER_REMARKS
  completionForm.customRemarks = remarks
}

function selectedRemarks() {
  if (isCustomRemarks.value) {
    return completionForm.customRemarks?.trim() || DEFAULT_REMARKS
  }

  return completionForm.remarksOption || DEFAULT_REMARKS
}

// avoid double-clicks while processing
async function submitCompletion() {
  if (submitting.value) return

  const ticketId =
    props.ticket?.detail?.ticket_id ??
    props.ticket?.ticket_id ??
    props.ticket?.id ??
    props.ticket?.detail?.id

  if (!ticketId) { errorMsg.value = 'No ticket ID found.'; return }
  if (!canSubmit.value) { errorMsg.value = 'Please complete the required fields.'; return }

  const ticket_nature_of_support = isLivestream.value
    ? 'Livestream'
    : isEvents.value
      ? 'Events'
      : completionForm.nature

  const ticket_support_type = (isLivestream.value || isEvents.value)
    ? null
    : (completionForm.subCategory || null)

  const payload = {
    ticket_status: 'Completed',
    ticket_type: completionForm.supportType,
    ticket_nature_of_support,
    ticket_support_type,
    ticket_related_type: completionForm.relatedType || null,
    ticket_assisted_by: completionForm.assistedBy.join(', '),
    ticket_problem_that_needed_support: completionForm.problem,
    ticket_actions_taken: completionForm.actionTaken,
    ticket_remarks: selectedRemarks(),

    // helpful for backend linkage if you prefer using reference no.
    client_referenceNo: readTicketRef(),
  }

  // Include Events fields when applicable
  if (isEvents.value) {
    payload.event_start_date = completionForm.events.startDate || null
    payload.event_end_date   = completionForm.events.endDate || null
    payload.event_venue      = completionForm.events.venue?.trim() || null
    payload.event_remarks    = completionForm.events.remarks?.trim() || null
    payload.other_request    = completionForm.events.otherRequest?.trim() || null
  }

  submitting.value = true
  errorMsg.value = ''
  try {
    await router.put(`/tickets-complete/${ticketId}`, payload, {
      preserveScroll: true,
      onStart: () => { /* already guarded by submitting flag */ },
      onSuccess: (page) => {
        const flashMsg = page?.props?.flash?.success || 'Ticket completed successfully and client notified via email!'
        toast.success(flashMsg)
        emit('success', { id: ticketId })
        closeModal()
      },
      onError: () => {
        errorMsg.value = 'Completion failed. Please try again.'
        toast.error('Completion failed!')
      },
      onFinish: () => { submitting.value = false },
    })
  } catch (e) {
    submitting.value = false
    throw e
  }
}
</script>

<template>
  <Modal :show="show" max-width="xl" @close="closeModal">
    <div class="flex max-h-[85vh] w-full flex-col overflow-hidden bg-white">
      <div class="flex items-start justify-between gap-4 border-b border-gray-200 bg-slate-50 px-6 py-4">
        <div class="flex items-center gap-3">
          <span class="inline-flex h-9 w-9 items-center justify-center rounded-full bg-green-100 text-green-700">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 16 16" fill="currentColor" aria-hidden="true">
              <path d="M16 8A8 8 0 1 1 0 8a8 8 0 0 1 16 0m-3.97-3.03a.75.75 0 0 0-1.08.022L7.477 9.417 5.384 7.323a.75.75 0 0 0-1.06 1.06L6.97 11.03a.75.75 0 0 0 1.079-.02l3.992-4.99a.75.75 0 0 0-.01-1.05z"/>
            </svg>
          </span>
          <div>
            <h2 class="text-lg font-bold text-gray-900">Complete Ticket</h2>
            <p class="text-sm text-gray-500">{{ ticketReference }}</p>
          </div>
        </div>

        <button
          type="button"
          class="rounded-full p-2 text-gray-400 transition hover:bg-white hover:text-gray-600"
          aria-label="Close complete ticket modal"
          @click="closeModal"
        >
          <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
            <path fill-rule="evenodd" d="M10 8.586 15.657 2.93a1 1 0 1 1 1.414 1.414L11.414 10l5.657 5.657a1 1 0 0 1-1.414 1.414L10 11.414l-5.657 5.657a1 1 0 0 1-1.414-1.414L8.586 10 2.929 4.343A1 1 0 0 1 4.343 2.93z" clip-rule="evenodd" />
          </svg>
        </button>
      </div>

      <div class="flex-1 overflow-y-auto space-y-4 px-6 py-5">
        <div v-if="errorMsg" class="rounded border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700">{{ errorMsg }}</div>

        <div class="flex items-center gap-2 rounded-lg border border-emerald-200 bg-emerald-50/80 px-3 py-2 text-xs text-emerald-800">
          <i class="fa-solid fa-envelope-circle-check text-emerald-600 text-sm shrink-0"></i>
          <span>An email notification with rating and resolution details will automatically be sent to the client upon completion.</span>
        </div>

        <!-- Request Type -->
        <div>
          <label class="mb-1 block text-sm font-semibold text-gray-700">Request Type</label>
          <select v-model="completionForm.supportType" class="w-full rounded border border-gray-300 px-3 py-2 text-sm focus:border-green-500 focus:ring-green-500">
            <option v-for="opt in supportTypes" :key="opt" :value="opt">{{ opt }}</option>
          </select>
        </div>

        <!-- Nature of Support -->
        <div>
          <label class="mb-1 block text-sm font-semibold text-gray-700">Nature of Support</label>
          <select
            v-model="completionForm.nature"
            class="w-full rounded border border-gray-300 px-3 py-2 text-sm disabled:bg-gray-100 disabled:text-gray-500 focus:border-green-500 focus:ring-green-500"
            :disabled="isLivestream || isEvents"
          >
            <option disabled value="">Select</option>
            <option v-for="opt in natureOptions" :key="opt" :value="opt">{{ opt }}</option>
          </select>
          <p v-if="isLivestream" class="mt-1 text-xs text-gray-500">Auto-set to <strong>Livestream</strong>.</p>
          <p v-else-if="isEvents" class="mt-1 text-xs text-gray-500">Auto-set to <strong>Events</strong>.</p>
        </div>

        <!-- Support Type (Technical only) -->
        <div v-if="isTechnical && completionForm.nature !== 'System Modification'">
          <label class="mb-1 block text-sm font-semibold text-gray-700">Support Type</label>
          <select v-model="completionForm.subCategory" class="w-full rounded border border-gray-300 px-3 py-2 text-sm focus:border-green-500 focus:ring-green-500">
            <option disabled value="">Select</option>
            <option v-for="sub in allSupportTypeOptions" :key="sub" :value="sub">
              {{ sub }}
            </option>
          </select>
          <p class="mt-1 text-xs text-gray-500">Showing all Support Type options.</p>
        </div>

        <!-- Related Type -->
        <div>
          <label class="mb-1 block text-sm font-semibold text-gray-700">Related Type</label>
          <select v-model="completionForm.relatedType" class="w-full rounded border border-gray-300 px-3 py-2 text-sm focus:border-green-500 focus:ring-green-500">
            <option disabled value="">Select</option>
            <option v-for="opt in relatedTypes" :key="opt" :value="opt">{{ opt }}</option>
          </select>
        </div>

        <!-- Events Only Fields -->
        <div v-if="isEvents" class="space-y-3 rounded-lg border border-amber-200 bg-amber-50 p-4">
          <h3 class="text-sm font-semibold uppercase text-amber-700">Event Details</h3>
          <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
            <div>
              <label class="mb-1 block text-sm font-medium text-gray-700">Event start date <span class="text-red-600">*</span></label>
              <input type="date" v-model="completionForm.events.startDate" class="w-full rounded border border-gray-300 px-3 py-2 text-sm focus:border-green-500 focus:ring-green-500" />
            </div>
            <div>
              <label class="mb-1 block text-sm font-medium text-gray-700">Event end date (optional)</label>
              <input type="date" v-model="completionForm.events.endDate" class="w-full rounded border border-gray-300 px-3 py-2 text-sm focus:border-green-500 focus:ring-green-500" />
            </div>
            <p v-if="completionForm.events.endDate && completionForm.events.startDate && completionForm.events.endDate < completionForm.events.startDate"
                class="text-xs text-red-600 sm:col-span-2">
                End date cannot be before start date.
            </p>
          </div>
          <div>
            <label class="mb-1 block text-sm font-medium text-gray-700">Event venue <span class="text-red-600">*</span></label>
            <input type="text" v-model="completionForm.events.venue" class="w-full rounded border border-gray-300 px-3 py-2 text-sm focus:border-green-500 focus:ring-green-500" placeholder="e.g., DOST-STII Atrium" />
          </div>
          <div>
            <label class="mb-1 block text-sm font-medium text-gray-700">Event Remarks (optional)</label>
            <textarea v-model="completionForm.events.remarks" rows="2" class="w-full rounded border border-gray-300 px-3 py-2 text-sm focus:border-green-500 focus:ring-green-500" placeholder="Program notes, setup details, etc..."></textarea>
          </div>
          <div>
            <label class="mb-1 block text-sm font-medium text-gray-700">Other Request (optional)</label>
            <textarea v-model="completionForm.events.otherRequest" rows="2" class="w-full rounded border border-gray-300 px-3 py-2 text-sm focus:border-green-500 focus:ring-green-500" placeholder="Equipment, accessories, or other requests..."></textarea>
          </div>
        </div>

        <!-- Assisted By -->
        <div>
          <label class="mb-2 block text-sm font-semibold text-gray-700">Assisted By</label>
          <div class="grid grid-cols-1 gap-2 text-sm sm:grid-cols-2">
            <label v-for="staff in availableStaffList" :key="staff" class="flex items-center gap-2 rounded border border-gray-200 px-3 py-2 hover:bg-gray-50">
              <input type="checkbox" :value="staff" v-model="completionForm.assistedBy" class="rounded border-gray-300 text-green-600 focus:ring-green-500" />
              {{ staff }}
            </label>
          </div>
        </div>

        <!-- Problem -->
        <div>
          <label class="mb-1 block text-sm font-semibold text-gray-700">Problem that needed support</label>
          <textarea v-model="completionForm.problem" rows="2" class="w-full rounded border border-gray-300 px-3 py-2 text-sm focus:border-green-500 focus:ring-green-500"></textarea>
        </div>

        <!-- Action Taken -->
        <div>
          <label class="mb-1 block text-sm font-semibold text-gray-700">Describe Action Taken <span class="text-red-600">*</span></label>
          <textarea v-model="completionForm.actionTaken" rows="3" class="w-full rounded border border-gray-300 px-3 py-2 text-sm focus:border-green-500 focus:ring-green-500"></textarea>
        </div>

        <!-- Remarks -->
        <div>
          <label class="mb-1 block text-sm font-semibold text-gray-700">Remarks</label>
          <select
            v-model="completionForm.remarksOption"
            class="w-full rounded border border-gray-300 px-3 py-2 text-sm focus:border-green-500 focus:ring-green-500"
          >
            <option v-for="remark in remarksOptions" :key="remark" :value="remark">
              {{ remark }}
            </option>
          </select>
        </div>

        <div v-if="isCustomRemarks">
          <label class="mb-1 block text-sm font-semibold text-gray-700">Other Remarks <span class="text-red-600">*</span></label>
          <textarea
            v-model="completionForm.customRemarks"
            rows="2"
            class="w-full rounded border border-gray-300 px-3 py-2 text-sm focus:border-green-500 focus:ring-green-500"
            placeholder="Enter remarks"
          ></textarea>
        </div>
      </div>

      <div class="flex flex-col-reverse gap-2 border-t border-gray-200 bg-white px-6 py-4 sm:flex-row sm:justify-end">
        <button type="button" @click="closeModal" class="rounded border border-gray-300 px-4 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50">Cancel</button>
        <button
          type="button"
          class="rounded bg-green-700 px-4 py-2 text-sm font-semibold text-white hover:bg-green-800 disabled:pointer-events-none disabled:opacity-60"
          :disabled="submitting || !canSubmit"
          @click="submitCompletion"
          :aria-busy="submitting ? 'true' : 'false'"
        >
          <span v-if="submitting">Completing...</span>
          <span v-else>Confirm Completion</span>
        </button>
      </div>
    </div>
  </Modal>
</template>
