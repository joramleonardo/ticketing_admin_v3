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
            href="http://ticketing.stii.local/"
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
            <div class="w-full px-4 py-10 mx-auto max-w-md">
            <div class="relative flex flex-col min-w-0 break-words w-full mb-6 shadow-lg rounded-lg bg-blueGray-200 border-0">
                <div class="rounded-t mb-0 px-6 py-6">
                <div class="text-center mb-3">
                    <h6 class="text-blueGray-500 text-sm font-bold">
                    Sign in to your account
                    </h6>
                </div>
                </div>
                <div class="flex-auto px-4 lg:px-10 py-10 pt-0">
                <form @submit.prevent="submit">
                    <div v-if="form.errors.username || form.errors.password" class="mb-4 p-3 rounded-lg bg-red-50 border border-red-200 text-red-700 text-xs font-medium flex items-start gap-2 shadow-sm">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 shrink-0 text-red-500 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                        </svg>
                        <span>{{ form.errors.username || form.errors.password }}</span>
                    </div>

                    <div class="mb-4">
                        <label class="block uppercase text-blueGray-600 text-xs font-bold mb-2">
                            Username
                        </label>
                        <input
                            v-model="form.username"
                            class="border-0 px-3 py-3 placeholder-blueGray-300 text-blueGray-600 bg-white rounded text-sm shadow focus:outline-none w-full ease-linear transition-all duration-150"
                            type="text"
                            required
                            autofocus
                        />
                        <InputError class="mt-1.5" :message="form.errors.username" />
                    </div>

                    <div class="mb-4">
                    <label class="block uppercase text-blueGray-600 text-xs font-bold mb-2">
                        Password
                    </label>
                    <input
                        v-model="form.password"
                        class="border-0 px-3 py-3 placeholder-blueGray-300 text-blueGray-600 bg-white rounded text-sm shadow focus:outline-none w-full ease-linear transition-all duration-150"
                        type="password"
                        required
                        autocomplete="current-password"
                    />
                    <InputError class="mt-1.5" :message="form.errors.password" />
                    </div>

                    <div class="text-center mt-6">
                    <button
                        class="bg-blueGray-800 text-white active:bg-blueGray-600 text-sm font-bold uppercase px-6 py-3 rounded shadow hover:shadow-lg w-full"
                        type="submit"
                        :disabled="form.processing"
                    >
                        Sign In
                    </button>
                    </div>
                </form>
                </div>
            </div>
            </div>
        </div>



  </GuestLayout>
</template>

<script setup>
    import GuestLayout from '@/Layouts/GuestLayout.vue'
    import InputError from '@/Components/InputError.vue'
    import { useForm } from '@inertiajs/vue3'

    const form = useForm({
        username: '',
        password: '',
        remember: false,
    })

    function submit() {
        form.post(route('login'))
    }
</script>
