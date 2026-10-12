<script setup>
import { ref, reactive, computed, nextTick } from 'vue'
import { router } from '@inertiajs/vue3'
import { useToast } from 'vue-toastification'
import Modal from '@/Components/Modal.vue'

const toast = useToast()

const props = defineProps({
  ticket: { type: Object, required: true },
  // Pass down whether current user is Admin; keeps this component reusable elsewhere
  isAdmin: { type: Boolean, default: false },
  // If your route ever changes, override this; by default uses /admin/tickets-v2/:id
  buildDeleteUrl: {
    type: Function,
    default: (id) => `/admin/tickets-v2/${id}`,
  },
})

const emit = defineEmits(['deleted'])

const deletingId = ref(null)

function getTicketId(t) {
  return t?.client_id ?? t?.id ?? t?.detail?.ticket_id ?? null
}

// ---- Confirm delete modal state ----
const confirmDelete = reactive({
  open: false,
  id: null,
  ref: '',
  name: '',
  busy: false,
  typed: '',
})
const confirmInputRef = ref(null)
const canConfirm = computed(() => confirmDelete.typed.trim() === confirmDelete.ref)

// Open modal
function openConfirmDelete() {
  const t = props.ticket
  confirmDelete.id = getTicketId(t)
  confirmDelete.ref = t?.client_referenceNo ?? t?.detail?.client_referenceNo ?? ''
  confirmDelete.name = t?.client_name ?? ''
  confirmDelete.typed = ''
  confirmDelete.open = true
  nextTick(() => confirmInputRef.value?.focus?.())
}

// Close modal
function closeConfirmDelete() {
  confirmDelete.open = false
}

// Do delete
function performDelete() {
  if (!confirmDelete.id || !canConfirm.value || confirmDelete.busy) return
  confirmDelete.busy = true
  deletingId.value = confirmDelete.id

  router.delete(props.buildDeleteUrl(confirmDelete.id), {
    preserveScroll: true,
    onSuccess: () => {
      toast.success(`Ticket ${confirmDelete.ref} deleted.`)
      // Let parent remove the item from its list
      emit('deleted', { id: confirmDelete.id, ref: confirmDelete.ref })
      closeConfirmDelete()
    },
    onError: () => {
      toast.error('Failed to delete ticket.')
    },
    onFinish: () => {
      confirmDelete.busy = false
      deletingId.value = null
    },
  })
}
</script>

<template>
  <div>
    <!-- Delete button or custom slot trigger -->
    <slot :open="openConfirmDelete">
      <button
        v-if="isAdmin"
        :disabled="deletingId === (ticket?.client_id ?? ticket?.id ?? ticket?.detail?.ticket_id)"
        @click="openConfirmDelete"
        class="p-2 rounded-full bg-red-600 text-white hover:bg-red-100 hover:text-red-700 disabled:opacity-60 cursor-pointer"
        title="Delete Ticket"
      >
        <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" fill="currentColor" viewBox="0 0 16 16">
          <path d="M5.5 5.5a.5.5 0 0 1 .5.5v6a.5.5 0 0 1-1 0v-6a.5.5 0 0 1 .5-.5m2.5 0a.5.5 0 0 1 .5.5v6a.5.5 0 0 1-1 0v-6a.5.5 0 0 1 .5-.5m3 .5a.5.5 0 0 0-1 0v6a.5.5 0 0 0 1 0z"/>
          <path d="M14.5 3a1 1 0 0 1-1 1H13v9a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V4h-.5a1 1 0 0 1 0-2H5V1a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1v1h2.5a1 1 0 0 1 1 1M6 1v1h4V1zM4.118 4 4 4.059V13a1 1 0 0 0 1 1h6a1 1 0 0 0 1-1V4.059L11.882 4z"/>
        </svg>
      </button>
    </slot>

    <!-- Modal -->
    <Modal :show="confirmDelete.open" @close="closeConfirmDelete" maxWidth="sm">
      <div class="p-6">
        <!-- Header -->
        <div class="flex items-center gap-3 mb-4">
          <div class="flex h-10 w-10 items-center justify-center rounded-full bg-red-100 text-red-600">
            <!-- warning icon -->
            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
              <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.72-1.36 3.485 0l6.518 11.59c.75 1.334-.213 2.991-1.742 2.991H3.48c-1.53 0-2.493-1.657-1.743-2.99L8.257 3.1zM11 14a1 1 0 10-2 0 1 1 0 002 0zm-.293-6.707a1 1 0 00-1.414 0L9 7.586V11a1 1 0 102 0V7.586l-.293-.293z" clip-rule="evenodd"/>
            </svg>
          </div>
          <h3 class="text-lg font-semibold text-gray-900">Delete ticket?</h3>
        </div>

        <!-- Body -->
        <div class="space-y-3 text-sm text-gray-700">
          <p>
            This will permanently delete
            <span class="font-semibold">Ticket {{ confirmDelete.ref }}</span>
            <span v-if="confirmDelete.name">
              for <span class="font-semibold">{{ confirmDelete.name }}</span>
            </span>.
            This action cannot be undone.
          </p>
          <p class="text-gray-600">
            Type <span class="font-mono font-semibold">{{ confirmDelete.ref }}</span> to confirm.
          </p>

          <input
            ref="confirmInputRef"
            v-model.trim="confirmDelete.typed"
            type="text"
            class="mt-1 w-full rounded border px-3 py-2 text-sm"
            :placeholder="confirmDelete.ref"
            @keyup.enter="canConfirm ? performDelete() : null"
          />
        </div>

        <!-- Actions -->
        <div class="mt-6 flex justify-end gap-2">
          <button
            type="button"
            class="rounded border px-3 py-1.5 text-sm"
            @click="closeConfirmDelete"
            :disabled="confirmDelete.busy"
          >
            Cancel
          </button>

          <button
            type="button"
            class="rounded bg-red-600 px-4 py-2 text-sm text-white hover:bg-red-700 disabled:opacity-50"
            :disabled="!canConfirm || confirmDelete.busy"
            @click="performDelete"
          >
            <span v-if="!confirmDelete.busy">Delete</span>
            <span v-else>Deleting…</span>
          </button>
        </div>
      </div>
    </Modal>
  </div>
</template>
