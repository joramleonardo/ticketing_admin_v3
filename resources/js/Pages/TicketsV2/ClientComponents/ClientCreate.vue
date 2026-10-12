<template>
  <div class="flex-auto px-4 lg:px-10 py-10 pt-0">
    <div class="text-blueGray-400 text-center mb-3 font-bold">
      <small>Submit your request to our support teams</small>
    </div>

    <form @submit.prevent="submit">
      <!-- Employee ID -->
      <div class="relative w-full mb-3">
        <label for="employee_code" class="block uppercase text-blueGray-600 text-xs font-bold mb-2">
          Employee ID
        </label>
        <input type="text" placeholder="Enter Employee ID..."
                id="employee_code" v-model="form.employee_code"
                class="px-3 py-3 placeholder-blueGray-300 text-blueGray-600 border-2 border-sky-500
                relative bg-white rounded text-sm shadow outline-none
                active:border-sky-500
                focus:outline-none focus:shadow-outline focus:border-sky-500 w-full"/>

        <p v-if="errors.employee_code" class="text-xs text-red-600 mt-1">{{ errors.employee_code }}</p>
      </div>

      <!-- Request Type -->
      <div class="relative w-full mb-3">
        <label for="ticket_type" class="block uppercase text-blueGray-600 text-xs font-bold mb-2">
          Request Type
        </label>
        <select
          id="ticket_type"
          v-model="form.ticket_type"
                class="px-3 py-3 placeholder-blueGray-300 text-blueGray-600 border-2 border-sky-500
                relative bg-white rounded text-sm shadow outline-none
                active:border-sky-500
                focus:outline-none focus:shadow-outline focus:border-sky-500 w-full"
        >
          <option disabled value="">Select</option>
          <option>Technical Support</option>
          <option>Livestream</option>
        </select>
        <p v-if="errors.ticket_type" class="text-xs text-red-600 mt-1">{{ errors.ticket_type }}</p>
      </div>

      <!-- Problem -->
      <div class="relative w-full mb-3">
        <label for="ticket_problem_that_needed_support" class="block uppercase text-blueGray-600 text-xs font-bold mb-2">
          Problem that needed support
        </label>
        <textarea
          id="ticket_problem_that_needed_support"
          v-model="form.ticket_problem_that_needed_support"
          rows="4"
            class="px-3 py-3 placeholder-blueGray-300 text-blueGray-600 border-2 border-sky-500
            relative bg-white rounded text-sm shadow outline-none
            active:border-sky-500
            focus:outline-none focus:shadow-outline focus:border-sky-500 w-full"
          placeholder="Describe the issue…"
        ></textarea>
        <p v-if="errors.ticket_problem_that_needed_support" class="text-xs text-red-600 mt-1">
          {{ errors.ticket_problem_that_needed_support }}
        </p>
      </div>

      <!-- Attachments (optional) -->
<div class="relative w-full mb-6">
  <label for="attachments" class="block uppercase text-blueGray-600 text-xs font-bold mb-2">
    Attachment (optional)
  </label>

  <input
    id="attachments"
    type="file"
    multiple
    accept="image/*,.pdf"
            class="px-3 py-3 placeholder-blueGray-300 text-blueGray-600 border-2 border-sky-500
            relative bg-white rounded text-sm shadow outline-none
            active:border-sky-500
            focus:outline-none focus:shadow-outline focus:border-sky-500 w-full"
    @change="onFilesSelected"
  />

  <!-- small helper text -->
  <p class="text-xs text-blueGray-400 mt-1">
    Allowed: images (jpg, jpeg, png, gif, webp) or PDF. Can Upload multiple files (Max ~10 MB per file).
  </p>

  <!-- simple preview of selected filenames -->
  <ul v-if="selectedFiles.length" class="mt-2 space-y-1">
    <li v-for="(f, i) in selectedFiles" :key="i" class="text-xs text-blueGray-600 truncate">
      • {{ f.name }}
    </li>
  </ul>

  <p v-if="errors.attachments" class="text-xs text-red-600 mt-1">{{ errors.attachments }}</p>
  <p v-if="errors['attachments.0']" class="text-xs text-red-600 mt-1">{{ errors['attachments.0'] }}</p>
</div>


      <div class="text-center mt-6">
        <button
        type="submit"
        :disabled="isSubmitting"
        class=" bg-lightBlue-500 text-white w-full
                active:bg-lightBlue-600 font-bold uppercase text-sm px-6 py-3 rounded shadow
                hover:shadow-lg outline-none focus:outline-none mr-1 mb-1 ease-linear transition-all duration-150">
        {{ isSubmitting ? 'Submitting…' : 'Submit Ticket' }}
        </button>

      </div>
    </form>

    <!-- Success modal with reference number -->
    <Modal :show="showRefModal" @close="closeRefModal">
      <div class="p-6 w-full sm:max-w-lg mx-auto">
        <h2 class="text-xl font-bold text-gray-800 mb-3">Ticket Submitted</h2>
        <p class="text-sm text-gray-600 mb-4">
          Please keep your reference number for tracking:
        </p>

        <div class="bg-gray-100 border rounded px-4 py-3 text-center text-lg font-semibold tracking-wider mb-4">
          {{ generatedRefNo }}
        </div>

        <div class="flex gap-2 justify-end">
          <button
            type="button"
            class="px-4 py-2 rounded border text-sm hover:bg-gray-50"
            @click="copyRef"
          >
            Copy
          </button>
          <button
            type="button"
            class="px-4 py-2 rounded bg-blue-700 text-white text-sm hover:bg-blue-800"
            @click="closeRefModal"
          >
            Done
          </button>
        </div>
      </div>
    </Modal>
  </div>
</template>

<script setup>
import { ref } from 'vue'
import { router } from '@inertiajs/vue3'
import { useToast } from 'vue-toastification'
import Modal from '@/Components/Modal.vue'

const toast = useToast()

const form = ref({
  employee_code: '',
  ticket_type: '',
  ticket_problem_that_needed_support: '',
})

const errors = ref({})
const isSubmitting = ref(false)

// modal state
const showRefModal = ref(false)
const generatedRefNo = ref('')

function reset() {
  form.value.employee_code = ''
  form.value.ticket_type = ''
  form.value.ticket_problem_that_needed_support = ''
  errors.value = {}
}

function copyRef() {
  if (!generatedRefNo.value) return
  navigator.clipboard?.writeText(generatedRefNo.value)
  toast.success('Reference number copied.')
}

function closeRefModal() {
  showRefModal.value = false
}

function submit() {
  if (isSubmitting.value) return
  isSubmitting.value = true
  errors.value = {}

  const payload = {
    employee_code: form.value.employee_code,
    ticket_type: form.value.ticket_type,
    ticket_problem_that_needed_support: form.value.ticket_problem_that_needed_support,
    // name 'attachments[]' to match backend validation for array
    attachments: selectedFiles.value
  }

  router.post(
    '/client-tickets-v2',
    payload,
    {
      preserveScroll: true,
      forceFormData: true, // ensure multipart/form-data
      onSuccess: (page) => {
        const props = page?.props ?? router.page?.props ?? {}
        const ref = props.flash?.refNo ?? props.refNo ?? ''
        toast.success(ref ? `Ticket submitted. Ref: ${ref}` : 'Ticket submitted.')
        generatedRefNo.value = ref
        showRefModal.value = true
        reset()
        selectedFiles.value = []
        // also clear the <input type="file">
        const input = document.getElementById('attachments')
        if (input) input.value = ''
      },
      onError: (e) => {
        errors.value = e || {}
        const first = e && Object.values(e)[0]
        if (first) toast.error(first)
      },
      onFinish: () => { isSubmitting.value = false },
    }
  )
}


const selectedFiles = ref([])

function onFilesSelected(e) {
  const files = Array.from(e.target.files || [])
  // client-side filter: images or pdf only + ~10MB cap
  const ok = []
  for (const f of files) {
    const isImage = f.type.startsWith('image/')
    const isPdf = f.type === 'application/pdf'
    const under10mb = f.size <= 10 * 1024 * 1024
    if ((isImage || isPdf) && under10mb) ok.push(f)
  }
  selectedFiles.value = ok
}

</script>
