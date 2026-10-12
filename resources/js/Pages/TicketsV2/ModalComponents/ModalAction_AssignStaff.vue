<script setup>
    import { ref, computed, watch } from 'vue'
    import { router, usePage  } from '@inertiajs/vue3'
    import { useToast } from 'vue-toastification'
    import Modal from '@/Components/Modal.vue'
    import { useStaffList } from '@/Composables/useStaffList'
    import { TICKET_TYPES } from '@/Support/ticketClassifications'

    const toast = useToast()
    const { staffOptions: dynamicStaff } = useStaffList()

    const props = defineProps({
        show: { type: Boolean, default: false },
        ticket: { type: Object, default: null }, // expects ticket.detail.ticket_id
        staffOptions: { type: Array, default: () => [] },     // e.g. ['John Doe','Jane Smith',...]
        defaultStatus: { type: String, default: 'Pending' },  // status after assignment
        supportTypes: { type: Array, default: () => TICKET_TYPES },
    })

    const page = usePage()
    const currentUserName = computed(() =>
        page?.props?.auth?.user?.name || page?.props?.auth?.user?.username || ''
    )
    const currentUserId = computed(() => page?.props?.auth?.user?.id ?? null)
    const emit = defineEmits(['close', 'success'])

    const availableStaffOptions = computed(() => {
        return (props.staffOptions && props.staffOptions.length > 0)
            ? props.staffOptions
            : dynamicStaff.value
    })

    const staffSearch = ref('')
    const selectedStaff = ref('')
    const selectedSupportType = ref('')
    const showStaffDropdown = ref(false)
    const submitting = ref(false)
    const errorMsg = ref('')
    const eventDate = ref('')    // YYYY-MM-DD
    const eventStartDate = ref('') // YYYY-MM-DD
    const eventEndDate   = ref('') // YYYY-MM-DD (optional)
    const eventVenue = ref('')
    const eventRemarks = ref('')

    const filteredStaff = computed(() => {
        const q = (staffSearch.value || '').toLowerCase().trim()
        const list = availableStaffOptions.value
        if (!q) return list
        return list.filter(s => (s || '').toLowerCase().includes(q))
    })

    const supportTypeOptions = computed(() => {
        const base = Array.isArray(props.supportTypes) && props.supportTypes.length > 0
            ? [...props.supportTypes]
            : [...TICKET_TYPES]
        if (!base.includes('Events')) {
            const i = base.indexOf('Livestream')
            if (i >= 0) base.splice(i + 1, 0, 'Events')
            else base.push('Events')
        }
        return base
    })

    function selectStaff(name) {
        selectedStaff.value = name
        staffSearch.value = name
        showStaffDropdown.value = false
    }

    function resetForm() {
        staffSearch.value = ''
        selectedStaff.value = ''
        selectedSupportType.value = ''
        showStaffDropdown.value = false
        errorMsg.value = ''
        eventStartDate.value = ''
        eventEndDate.value = ''
        eventVenue.value = ''
        eventRemarks.value = ''
    }

    function close() {
        resetForm()
        emit('close')
    }

    watch(
        () => [props.show, props.ticket?.detail?.ticket_id],
        () => {
            if (!props.show || !props.ticket) return
            selectedSupportType.value = props.ticket?.detail?.ticket_type || props.ticket?.detail?.ticket_support_type || ''
            selectedStaff.value = ''
            staffSearch.value = ''
            errorMsg.value = ''
            eventStartDate.value = ''
            eventEndDate.value = ''
            eventVenue.value = ''
            eventRemarks.value = ''
        },
        { immediate: false }
    )

    // If user toggles away from "Events", clear those fields
    watch(selectedSupportType, (val) => {
        if (val !== 'Events') {
            eventStartDate.value = ''
            eventEndDate.value = ''
            eventVenue.value = ''
            eventRemarks.value = ''
        }
    })

    async function assignStaff() {
        const ticketId = props.ticket?.detail?.ticket_id
        const staffName = (selectedStaff.value && selectedStaff.value.trim()) || (staffSearch.value && staffSearch.value.trim()) || ''

        if (!ticketId || !staffName || !selectedSupportType.value) {
            errorMsg.value = 'Please select both staff and request type.'
            return
        }

        if (selectedSupportType.value === 'Events') {
            if (!eventStartDate.value) {
             errorMsg.value = 'For Events, please fill in Event Start Date.'
            return
            }
            if (eventEndDate.value) {
            const start = new Date(eventStartDate.value)
            const end   = new Date(eventEndDate.value)
            if (end < start) {
                errorMsg.value = 'Event End Date must be the same day or after the start date.'
                return
            }
            }
        }

        const payload = {
            ticket_type: selectedSupportType.value,
            ticket_assigned_staff: staffName,
            ticket_status: props.defaultStatus,
            ticket_assigned_by: currentUserName.value,
            client_referenceNo: props.ticket?.client_referenceNo || null,
            ticket_problem: props.ticket?.detail?.ticket_problem_that_needed_support || null,
            event_start_date: selectedSupportType.value === 'Events' ? eventStartDate.value : null,
            event_end_date:   selectedSupportType.value === 'Events' ? (eventEndDate.value || null) : null,
            event_venue:      selectedSupportType.value === 'Events'
                    ? (eventVenue.value?.trim() || null)
                    : null,
            event_remarks:    selectedSupportType.value === 'Events' ? (eventRemarks.value?.trim() || null) : null,
        }

        submitting.value = true
        try {
            await router.put(`/tickets/${ticketId}`, payload, {
                preserveScroll: true,
                onSuccess: () => {
                    toast.success(`Assigned to ${staffName} successfully!`)
                    emit('success', {
                        id: ticketId,
                        assignedTo: staffName,
                        assignedBy: currentUserName.value,
                        status: props.defaultStatus
                    })
                    close()
                },
                onError: (err) => {
                    const msg = err?.message || 'Assignment failed!'
                    errorMsg.value = msg
                    toast.error(msg)
                },
            })
        } finally {
            submitting.value = false
        }
    }
</script>

<template>
  <Modal :show="show" @close="close">
    <div class="p-6 w-full sm:max-w-lg mx-auto flex flex-col max-h-[80vh]">
      <!-- Header -->
      <div class="flex items-center justify-between mb-4">
        <h2 class="text-lg font-semibold">Assign Staff</h2>
        <button @click="close" class="text-gray-500 hover:text-gray-700">✕</button>
      </div>

      <!-- Scrollable body -->
      <div class="flex-1 overflow-y-auto pr-1">
        <!-- Error -->
        <div v-if="errorMsg" class="mb-3 text-sm text-red-600">
          {{ errorMsg }}
        </div>

        <!-- Ticket ref (read-only) -->
        <div class="mb-3 text-sm text-gray-700">
          <span class="font-semibold">Reference No:</span>
          <span class="ml-1 font-bold">{{ ticket?.client_referenceNo || '—' }}</span>
        </div>
        <div class="mb-3 text-sm text-gray-700">
          <span class="font-semibold">Ticket Problem:</span>
          <span class="ml-1 font-bold">{{ props.ticket?.detail?.ticket_problem_that_needed_support || '—' }}</span>
        </div>

        <!-- Staff Dropdown (searchable) -->
        <div class="relative mb-4">
          <label class="block font-semibold mb-1 text-gray-700">Assign to</label>
          <input
            type="text"
            v-model="staffSearch"
            placeholder="Search staff..."
            class="w-full border rounded px-3 py-2 text-sm focus:ring focus:ring-blue-200"
            @focus="showStaffDropdown = true"
            @input="showStaffDropdown = true"
          />
          <ul
            v-if="showStaffDropdown && filteredStaff.length"
            class="absolute z-10 w-full mt-1 bg-white border border-gray-300 rounded shadow max-h-40 overflow-y-auto text-sm"
          >
            <li
              v-for="(staff, index) in filteredStaff"
              :key="index"
              @click="selectStaff(staff)"
              class="px-4 py-2 hover:bg-blue-100 cursor-pointer"
            >
              {{ staff }}
            </li>
          </ul>
        </div>

        <!-- Request Type -->
        <div class="mb-4">
          <label class="block font-semibold mb-1 text-gray-700">Request Type</label>
          <select v-model="selectedSupportType" class="w-full border rounded px-3 py-2 text-sm">
            <option disabled value="">Select request type</option>
            <option v-for="opt in supportTypeOptions" :key="opt" :value="opt">{{ opt }}</option>
          </select>
        </div>

        <!-- Events-only fields -->
        <div v-if="selectedSupportType === 'Events'" class="mb-1 grid grid-cols-1 gap-3">
          <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
            <div>
              <label class="block font-semibold mb-1 text-gray-700">Event start date</label>
              <input
                type="date"
                v-model="eventStartDate"
                class="w-full border rounded px-3 py-2 text-sm focus:ring focus:ring-blue-200"
              />
            </div>
            <div>
              <label class="block font-semibold mb-1 text-gray-700">Event end date (optional)</label>
              <input
                type="date"
                v-model="eventEndDate"
                class="w-full border rounded px-3 py-2 text-sm focus:ring focus:ring-blue-200"
              />
            </div>

            <p v-if="eventEndDate && eventStartDate && eventEndDate < eventStartDate"
                class="text-xs text-red-600 mt-1">
                End date can’t be before start date.
            </p>
          </div>

          <div>
            <label class="block font-semibold mb-1 text-gray-700">Event venue (optional)</label>
            <input
              type="text"
              v-model="eventVenue"
              placeholder="e.g., DOST–STII Auditorium, Taguig"
              class="w-full border rounded px-3 py-2 text-sm focus:ring focus:ring-blue-200"
            />
          </div>

          <div class="mb-2">
            <label class="block font-semibold mb-1 text-gray-700">Remarks (optional)</label>
            <textarea
              v-model="eventRemarks"
              rows="3"
              class="w-full border rounded px-3 py-2 text-sm focus:ring focus:ring-blue-200"
              placeholder="Special instructions, program notes, coverage scope, etc."
            ></textarea>
          </div>
        </div>
      </div>

      <!-- Footer -->
      <div class="mt-4 flex justify-end gap-2">
        <button @click="close" class="px-4 py-2 rounded border text-sm hover:bg-gray-50">Cancel</button>
        <button
          type="button"
          class="px-4 py-2 rounded shadow-sm border-2 border-blue-900 bg-blue-900 text-white text-sm hover:bg-blue-100 hover:text-blue-700 hover:border-blue-700 disabled:opacity-60"
          :disabled="submitting"
          @click="assignStaff"
        >
          {{ submitting ? 'Assigning…' : `Assign & set ${defaultStatus}` }}
        </button>
      </div>
    </div>
  </Modal>
</template>
