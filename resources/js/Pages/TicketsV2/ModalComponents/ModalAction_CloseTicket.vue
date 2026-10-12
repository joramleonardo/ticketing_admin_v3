<script setup>
import { reactive, computed, watch } from 'vue'
import { router } from '@inertiajs/vue3'
import { useToast } from 'vue-toastification'
import Modal from '@/Components/Modal.vue'

const toast = useToast()

const props = defineProps({
  show: { type: Boolean, default: false },
  ticket: { type: Object, default: () => ({}) },
})

const emit = defineEmits(['close', 'closed'])

const form = reactive({
  ticket_remarks: '',
  busy: false,
})

watch(
  () => props.show,
  (val) => {
    if (val) {
      form.ticket_remarks = ''
      form.busy = false
    }
  }
)

const ticketId = computed(() => {
  return props.ticket?.ticket_id ?? props.ticket?.detail?.ticket_id ?? props.ticket?.id ?? null
})

const referenceNo = computed(() => {
  return props.ticket?.client_referenceNo ?? props.ticket?.detail?.client_referenceNo ?? 'Ticket'
})

function closeModal() {
  if (form.busy) return
  emit('close')
}

function submitClose() {
  if (!ticketId.value || form.busy) return

  form.busy = true
  router.put(`/tickets-close/${ticketId.value}`, {
    ticket_remarks: form.ticket_remarks.trim() || null,
  }, {
    preserveScroll: true,
    onSuccess: () => {
      toast.success(`Ticket ${referenceNo.value} has been closed.`)
      emit('closed', { id: ticketId.value })
      emit('close')
    },
    onError: (errors) => {
      const msg = errors?.message || 'Failed to close ticket.'
      toast.error(msg)
    },
    onFinish: () => {
      form.busy = false
    },
  })
}
</script>

<template>
  <Modal :show="show" @close="closeModal" maxWidth="md">
    <div class="p-6">
      <!-- Header -->
      <div class="flex items-center gap-3 mb-4">
        <div class="flex h-11 w-11 items-center justify-center rounded-full bg-slate-100 text-slate-700 dark:bg-slate-800 dark:text-slate-300">
          <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
            <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
          </svg>
        </div>
        <div>
          <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100">Close Ticket</h3>
          <p class="text-xs text-gray-500 dark:text-gray-400">
            Reference: <span class="font-mono font-medium text-gray-700 dark:text-gray-300">{{ referenceNo }}</span>
          </p>
        </div>
      </div>

      <!-- Informational banner -->
      <div class="mb-4 rounded-md border border-slate-200 bg-slate-50 p-3 text-xs text-slate-700 dark:border-slate-700 dark:bg-slate-800/60 dark:text-slate-300">
        <p>
          Formally closing a ticket completes its lifecycle and marks it as archived.
          Tickets are also automatically closed once client feedback is received.
        </p>
      </div>

      <!-- Form -->
      <div class="space-y-4 text-sm text-gray-700 dark:text-gray-200">
        <div>
          <label class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">
            Closing Remarks <span class="text-gray-400 font-normal">(optional)</span>
          </label>
          <textarea
            v-model="form.ticket_remarks"
            rows="3"
            class="w-full rounded-md border border-gray-300 bg-white px-3 py-2 text-sm text-gray-800 focus:border-slate-500 focus:ring-slate-500 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200"
            placeholder="Add any final sign-off notes or closing comments..."
          ></textarea>
        </div>
      </div>

      <!-- Actions -->
      <div class="mt-6 flex justify-end gap-2">
        <button
          type="button"
          class="rounded-md border border-gray-300 bg-white px-3.5 py-2 text-xs font-medium text-gray-700 hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-300 dark:hover:bg-gray-700"
          :disabled="form.busy"
          @click="closeModal"
        >
          Cancel
        </button>

        <button
          type="button"
          class="inline-flex items-center gap-1.5 rounded-md bg-slate-800 px-4 py-2 text-xs font-medium text-white shadow-sm hover:bg-slate-900 focus:ring-2 focus:ring-slate-500 disabled:opacity-50 dark:bg-slate-700 dark:hover:bg-slate-600"
          :disabled="form.busy"
          @click="submitClose"
        >
          <svg v-if="form.busy" class="h-3.5 w-3.5 animate-spin" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"></path>
          </svg>
          <span v-if="form.busy">Closing...</span>
          <span v-else>Confirm Close</span>
        </button>
      </div>
    </div>
  </Modal>
</template>
