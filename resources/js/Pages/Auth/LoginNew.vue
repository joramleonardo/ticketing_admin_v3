<template>
  <GuestLayout>
    <!-- Top navbar with Home -->
    <nav class="w-full bg-white/80 backdrop-blur border-b border-gray-200">
      <div class="max-w-md mx-auto px-4 py-3 flex items-center justify-between">
        <div class="flex items-center gap-2">
          <span class="inline-flex h-8 w-8 items-center justify-center rounded-lg bg-indigo-600 text-white font-bold">T</span>
          <span class="text-sm font-semibold text-gray-800">Ticketing Home</span>
        </div>
        <a
          href="http://10.10.141.43/ticketing_v2/public/"
          class="inline-flex items-center gap-2 text-sm font-medium text-indigo-600 hover:text-indigo-700"
        >
          Visit
          <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="m9 5 7 7-7 7"/>
          </svg>
        </a>
      </div>
    </nav>

    <!-- Page body -->
    <div class="min-h-[calc(60vh-57px)] flex items-center">
      <div class="w-full px-4 py-10 mx-auto max-w-md bg-gradient-to-br from-blue-50 to-indigo-50">
        <div class="relative flex flex-col w-full mb-6 shadow-xl rounded-2xl bg-white border border-gray-100">
          <div class="rounded-t px-6 pt-6 pb-4">
            <h1 class="text-center text-lg font-semibold text-gray-800">Sign in to your account</h1>
            <p class="mt-1 text-center text-xs text-gray-500">Use your assigned username and password</p>
          </div>

          <div class="flex-auto px-6 pb-6 pt-2">
            <!-- Validation errors -->
            <div v-if="hasErrors" class="mb-4 rounded-lg border border-red-200 bg-red-50 p-3 text-sm text-red-700">
              <ul class="list-disc list-inside space-y-1">
                <li v-for="(msg, key) in form.errors" :key="key">{{ msg }}</li>
              </ul>
            </div>

            <form @submit.prevent="submit" class="space-y-4">
              <!-- Username -->
              <div>
                <label class="block text-xs font-semibold text-gray-700 mb-1">Username</label>
                <input
                  v-model.trim="form.username"
                  class="w-full rounded-lg border border-gray-200 px-3 py-2.5 text-sm text-gray-800 placeholder-gray-400 bg-white shadow-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent transition"
                  type="text"
                  required
                  autofocus
                  placeholder="e.g. jdelacruz"
                />
              </div>

<!-- Password with show/hide -->
<div>
  <label class="block text-xs font-semibold text-gray-700 mb-1">Password</label>

  <div class="relative">
    <input
      :type="showPassword ? 'text' : 'password'"
      v-model="form.password"
      class="w-full rounded-lg border border-gray-200 px-3 py-2.5 pr-16 text-sm text-gray-800 placeholder-gray-400 bg-white shadow-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent transition"
      required
      autocomplete="current-password"
      placeholder="••••••••"
      @keyup.enter="submit"
    />

    <!-- Show/Hide button inside the input -->
    <button
      type="button"
      @click="showPassword = !showPassword"
      :aria-pressed="showPassword ? 'true' : 'false'"
      class="absolute right-2 top-1/2 -translate-y-1/2 text-xs font-medium px-2 py-1 rounded-md
             text-indigo-600 hover:text-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-500"
      title="Toggle password visibility"
    >
      {{ showPassword ? 'Hide' : 'Show' }}
    </button>
  </div>
</div>



              <!-- Submit -->
              <button
                class="w-full inline-flex items-center justify-center gap-2 rounded-lg bg-indigo-600 text-white text-sm font-semibold px-4 py-2.5 shadow hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 disabled:opacity-70 disabled:cursor-not-allowed transition"
                type="submit"
                :disabled="form.processing"
              >
                <svg v-if="form.processing" class="h-4 w-4 animate-spin" viewBox="0 0 24 24" fill="none">
                  <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                  <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"/>
                </svg>
                <span>{{ form.processing ? 'Signing in…' : 'Sign In' }}</span>
              </button>

            </form>
          </div>
        </div>

      </div>
    </div>
  </GuestLayout>
</template>

<script setup>
import GuestLayout from '@/Layouts/GuestLayout.vue'
import { useForm } from '@inertiajs/vue3'
import { computed, ref } from 'vue'

const showPassword = ref(false)

const form = useForm({
  username: '',
  password: '',
  remember: false,
})

const hasErrors = computed(() => Object.keys(form.errors || {}).length > 0)

function submit() {
  form.post(route('login'))
}
</script>
