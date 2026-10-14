
<script setup>
import { reactive, watch, ref, computed, nextTick } from 'vue'
import { router, usePage } from '@inertiajs/vue3'
import Modal from '@/Components/Modal.vue'

import { useToast } from 'vue-toastification'
const toast = useToast()
const page = usePage()

const userRole = computed(() => String(page.props?.auth?.user?.role || '').trim().toLowerCase())
const isAdmin = computed(() => ['admin', 'super admin'].includes(userRole.value))

const emit = defineEmits(['close', 'updated'])

const props = defineProps({
  show: { type: Boolean, default: false },
  ticket: { type: Object, default: () => ({}) },
  groupedStaff: { type: Object, default: () => ({}) },
})

// ----- Form state -----
const form = reactive({
  // always present
  ticket_type: '',                     // "Livestream" | "Technical Support"
  // sometimes present
  ticket_attended_by: '',              // optional
  ticket_nature_of_support: '',        // e.g., Hardware | Software | Network | Others
  ticket_support_type: '',             // depends on nature (or free text if Others)
  ticket_remarks: '',                  // text
})

const selectedGroup = ref('')
const saving = ref(false)
const errors = reactive({ ticket_type: '' })

// ----- Status handling -----
const statusFromTicket = computed(() =>
  props.ticket?.detail?.ticket_status ??
  props.ticket?.ticket_status ??
  'New'
)

// which sections are editable for the CURRENT status?
const canEdit = computed(() => {
  const st = statusFromTicket.value
  return {
    // always true for all statuses listed
    type: ['New', 'Pending', 'In Progress', 'Completed'].includes(st),
    attendedBy: isAdmin.value && ['Pending', 'In Progress', 'Completed'].includes(st),
    completionFields: st === 'Completed', // nature/supportType/remarks
  }
})

// ----- Options (adjust as needed) -----
const ticketTypeOptions = ['Livestream', 'Technical Support']

// You can swap these with your actual options used elsewhere
const natureOptions = [
    'Hardware',
    'Software',
    'Hardware & Software',
    'Others']
const supportTypesByNature = {
  Hardware: [
    'Troubleshooting',
    'Evaluation',
    'Network',
    'Testing',
    'Consultation',
    'Assistance',
    'Others'
],
  Software: [
    'Installation',
    'Data Retrieval',
    'Virus/Malwares',
    'Data Conversion/Uploading/Processing',
    'Reformat',
    'Download',
    'Assistance',
    'Others'
],
  Network: [
    'Connectivity',
    'Configuration',
    'Monitoring'],
}

// If nature is Others → free text support type
const isOthersNature = computed(() => form.ticket_nature_of_support === 'Others')

const filteredSupportTypes = computed(() => {
   const n = form.ticket_nature_of_support
   if (!n) return []
   if (n === 'Hardware & Software') {
    const hw = supportTypesByNature.Hardware || []
    const sw = supportTypesByNature.Software || []
    return [...new Set([...hw, ...sw])].filter(s => s !== 'Others')
   }
   return supportTypesByNature[n] || []
})

watch(() => form.ticket_nature_of_support, async (n) => {
  // clear any previously selected support type when nature changes
  form.ticket_support_type = ''
  // optionally auto-pick first suggestion if not "Others"
  if (n && n !== 'Others') {
    await nextTick()
    form.ticket_support_type = filteredSupportTypes.value[0] || ''
  }
})

// ----- Grouped Staff helpers -----
const hasGroupedStaff = computed(() => Object.keys(props.groupedStaff || {}).length > 0)
const membersForSelectedGroup = computed(() =>
  selectedGroup.value ? props.groupedStaff[selectedGroup.value] || [] : []
)

// ----- Init/Prefill -----
watch(
  () => [props.show, props.ticket],
  () => {
    if (!props.show) return

    // Pre-fill from either ticket.detail or top-level ticket
    form.ticket_type =
        props.ticket?.detail?.ticket_type ??
        props.ticket?.ticket_type ??
        ''
    form.ticket_attended_by =
        props.ticket?.detail?.ticket_assigned_staff ??
        props.ticket?.ticket_assigned_staff ??
        props.ticket?.detail?.ticket_attended_by ??   // backward-compat (old data)
        props.ticket?.ticket_attended_by ??           // backward-compat (old data)
        ''

    // If we have groups, pre-select the group that contains the current assignee
    if (hasGroupedStaff.value && form.ticket_attended_by) {
        const match = Object.entries(props.groupedStaff)
           .find(([, arr]) => Array.isArray(arr) && arr.some(n => String(n).trim() === String(form.ticket_attended_by).trim()))
        if (match) selectedGroup.value = match[0]
    }

    form.ticket_nature_of_support =
      props.ticket?.detail?.ticket_nature_of_support ??
      props.ticket?.ticket_nature_of_support ??
      ''

    form.ticket_support_type =
      props.ticket?.detail?.ticket_support_type ??
      props.ticket?.ticket_support_type ??
      ''

    form.ticket_remarks =
      props.ticket?.detail?.ticket_remarks ??
      props.ticket?.ticket_remarks ??
      ''

    if (!selectedGroup.value && hasGroupedStaff.value) {
      selectedGroup.value = Object.keys(props.groupedStaff)[0]
    }
  },
  { immediate: true, deep: true }
)

// Compute the id to use for PUT (supports your common shapes)
const ticketId = computed(() =>
  props.ticket?.detail?.ticket_id ??
  props.ticket?.ticket_id ??
  props.ticket?.id ??
  null
)

// ----- Validation -----
function validate() {
  errors.ticket_type = ''
  if (canEdit.value.type && !form.ticket_type?.trim()) {
    errors.ticket_type = 'Support type is required.'
  }
  // Attended by remains optional per your requirement.
  // Completion fields are optional unless you want to enforce them — left optional here.
  return !errors.ticket_type
}

// ----- Emit helpers -----
function buildPayload() {
  const payload = {}

  if (canEdit.value.type) {
    payload.ticket_type = form.ticket_type.trim()
  }
  if (canEdit.value.attendedBy) {
    payload.ticket_assigned_staff = form.ticket_attended_by?.trim() || null
  }
  if (canEdit.value.completionFields) {
    payload.ticket_nature_of_support = form.ticket_nature_of_support || null
    payload.ticket_support_type = form.ticket_support_type?.trim() || null
    payload.ticket_remarks = form.ticket_remarks?.trim() || null
  }

  return payload
}

// ----- Events -----
function onClose() {
  emit('close')
}


async function onSubmit() {
  if (!validate()) return

  const id = ticketId.value
  if (!id) {
    console.warn('No ticket id found for update.')
    return
  }

  const payload = buildPayload()
  if (Object.keys(payload).length === 0) {
    // nothing to update; optionally show a toast here
    return
  }

  saving.value = true
  router.put(`/tickets/${id}`, payload, {
    preserveScroll: true,
    onSuccess: () => {
        emit('updated', payload) // let parent patch UI immediately
        emit('close')
        toast.success('Ticket updated successfully!')
    },
    onError: () => {
      // optionally show a toast; keeping silent here
    },
    onFinish: () => {
      saving.value = false
    },
  })
}
</script>

<template>
  <Modal :show="show" @close="onClose">
    <div class="p-6">
      <!-- Header -->
      <div class="mb-4 flex items-center justify-between">
        <h2 class="text-lg font-semibold">Edit Ticket</h2>
        <span
          class="rounded-full border px-2 py-0.5 text-xs"
          :class="{
            'border-gray-300 text-gray-700': statusFromTicket === 'New',
            'border-yellow-400 text-yellow-700': statusFromTicket === 'Pending',
            'border-blue-400 text-blue-700': statusFromTicket === 'In Progress',
            'border-green-400 text-green-700': statusFromTicket === 'Completed',
          }"
          title="Current Status"
        >
          {{ statusFromTicket }}
        </span>
      </div>

      <form @submit.prevent="onSubmit" class="space-y-5">

        <!-- Ticket/Support Type (always editable per rules) -->
        <div v-if="canEdit.type">
          <label class="mb-1 block text-sm font-medium">Request Type</label>
          <select
            v-model="form.ticket_type"
            class="w-full rounded border px-3 py-2 text-sm"
          >
            <option disabled value="">Select</option>
            <option v-for="opt in ticketTypeOptions" :key="opt" :value="opt">
              {{ opt }}
            </option>
          </select>
          <p v-if="errors.ticket_type" class="mt-1 text-xs text-red-600">
            {{ errors.ticket_type }}
          </p>
        </div>

        <!-- Assigned Staff / Attended By (Pending | In Progress | Completed) -->
        <div v-if="canEdit.attendedBy">
          <div class="mb-1 flex items-center justify-between">
            <label class="block text-sm font-medium">Assigned Staff (optional)</label>
            <button
              class="text-xs underline hover:opacity-80"
              type="button"
              @click="form.ticket_attended_by = ''"
            >
              Clear
            </button>
          </div>

          <div v-if="hasGroupedStaff" class="grid grid-cols-1 gap-2 sm:grid-cols-2">
            <select v-model="selectedGroup" class="rounded border px-3 py-2 text-sm">
              <option disabled value="">Select group</option>
              <option
                v-for="(members, g) in groupedStaff"
                :key="g"
                :value="g"
              >
                {{ g }}
              </option>
            </select>

            <select v-model="form.ticket_attended_by" class="rounded border px-3 py-2 text-sm">
              <option value="">— None —</option>
              <option v-for="name in membersForSelectedGroup" :key="name" :value="name">
                {{ name }}
              </option>
            </select>
          </div>

          <input
            v-else
            v-model.trim="form.ticket_attended_by"
            type="text"
            class="w-full rounded border px-3 py-2 text-sm"
            placeholder="Type a name (optional)"
          />
        </div>

        <!-- Completion fields (Completed) -->
        <div v-if="canEdit.completionFields" class="space-y-4">
          <!-- Nature of Support -->
          <div>
            <label class="mb-1 block text-sm font-medium">Nature of Support</label>
            <select
              v-model="form.ticket_nature_of_support"
              class="w-full rounded border px-3 py-2 text-sm"
            >
              <option disabled value="">Select</option>
              <option v-for="n in natureOptions" :key="n" :value="n">{{ n }}</option>
            </select>
          </div>

          <!-- Support Type -->
          <div>
            <label class="mb-1 block text-sm font-medium">Support Type</label>

            <!-- If Others → free text -->
            <input
              v-if="isOthersNature"
              v-model.trim="form.ticket_support_type"
              type="text"
              class="w-full rounded border px-3 py-2 text-sm"
              placeholder="Describe support type"
            />

            <!-- Else → pick from list -->
            <select
              v-else
              v-model="form.ticket_support_type"
              class="w-full rounded border px-3 py-2 text-sm"
            >
              <option disabled value="">Select</option>
              <option
                v-for="t in filteredSupportTypes"
                :key="t"
                :value="t"
              >
                {{ t }}
              </option>
            </select>
          </div>

          <!-- Remarks -->
          <div>
            <label class="mb-1 block text-sm font-medium">Remarks</label>
            <textarea
              v-model.trim="form.ticket_remarks"
              rows="3"
              class="w-full resize-y rounded border px-3 py-2 text-sm"
              placeholder="Add any final notes or remarks"
            ></textarea>
          </div>
        </div>

        <!-- Actions -->
        <div class="flex justify-end gap-2 pt-2">
          <button type="button" class="rounded border px-3 py-1.5 text-sm" @click="onClose">
            Cancel
          </button>
          <button
            type="submit"
            class="rounded bg-blue-600 px-4 py-2 text-sm text-white hover:bg-blue-700 disabled:opacity-50"
            :disabled="saving"
          >
            <span v-if="!saving">Save</span>
            <span v-else>Saving…</span>
          </button>
        </div>
      </form>
    </div>
  </Modal>
</template>



<style scoped>
    .fade-enter-active,
    .fade-leave-active {
        transition: opacity 150ms ease;
    }
    .fade-enter-from,
    .fade-leave-to {
        opacity: 0;
    }
</style>
