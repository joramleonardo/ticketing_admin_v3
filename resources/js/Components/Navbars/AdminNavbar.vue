<template>
  <header
    class="sticky top-0 z-30 bg-white/95 dark:bg-neutral-900/95
           backdrop-blur border-b border-slate-200/80 dark:border-neutral-800
           px-4 sm:px-6 lg:px-8 py-3 flex items-center justify-between shadow-xs transition-all"
  >
    <!-- Left: Contextual Breadcrumb & Page Title -->
    <div class="flex items-center gap-3 pl-12 md:pl-0">
      <div class="flex flex-col">
        <div class="flex items-center gap-1.5 text-[11px] font-semibold text-slate-400">
          <span>Ticketing Admin</span>
          <span>/</span>
          <span class="text-slate-600 capitalize">{{ currentSection }}</span>
        </div>
        <h1 class="text-sm font-bold text-slate-900 dark:text-white tracking-tight">
          {{ pageTitle }}
        </h1>
      </div>
    </div>

    <!-- Right: Role Pill, Public Portal Link & User Dropdown -->
    <div class="flex items-center gap-3 ml-auto">
      <!-- Role Badge -->
      <span
        v-if="userRole"
        class="hidden lg:inline-flex items-center px-2.5 py-0.5 rounded-full text-[11px] font-bold tracking-wide uppercase border"
        :class="userRole === 'Admin' || userRole === 'Super Admin'
          ? 'bg-purple-50 text-purple-700 border-purple-200'
          : 'bg-blue-50 text-blue-700 border-blue-200'"
      >
        {{ userRole }}
      </span>

      <!-- Quick Link to Public Client Portal -->
      <a
        href="/client"
        target="_blank"
        rel="noopener noreferrer"
        class="hidden sm:inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-semibold rounded-lg border border-slate-200 bg-slate-50 text-slate-700 hover:bg-slate-100 hover:border-slate-300 transition"
        title="Open client ticket tracker & submission portal in new tab"
      >
        <span>Client Tracker</span>
        <svg xmlns="http://www.w3.org/2000/svg" class="w-3.5 h-3.5 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14" />
        </svg>
      </a>

      <!-- User Dropdown Menu -->
      <ul class="flex items-center">
        <UserDropdown />
      </ul>
    </div>
  </header>
</template>

<script setup>
import { computed } from 'vue'
import { usePage } from '@inertiajs/vue3'
import UserDropdown from '@/Components/Dropdowns/UserDropdown.vue'

const page = usePage()

const userRole = computed(() => {
  return page.props?.auth?.user?.role || ''
})

const currentSection = computed(() => {
  const url = page.url || ''
  if (url.startsWith('/dashboard')) return 'Overview'
  if (url.startsWith('/admin/tickets-v2') || url.startsWith('/tickets-v2')) return 'Tickets'
  if (url.startsWith('/admin/my-tickets')) return 'Tickets'
  if (url.startsWith('/ratings') || url.startsWith('/csm')) return 'CSM & Ratings'
  if (url.startsWith('/profile')) return 'Account'
  return 'Support'
})

const pageTitle = computed(() => {
  const url = page.url || ''
  if (url.startsWith('/dashboard')) return 'Ticket Overview & Analytics'
  if (url.startsWith('/admin/tickets-v2') || url.startsWith('/tickets-v2')) return 'All Support Tickets'
  if (url.startsWith('/admin/my-tickets')) return 'My Assigned Tickets'
  if (url.startsWith('/ratings/dashboard')) return 'Customer Ratings Dashboard'
  if (url.startsWith('/csm/dashboard') || url.startsWith('/reports')) return 'CSM Reports'
  if (url.startsWith('/profile')) return 'Profile Settings'
  return 'IT Support Desk'
})
</script>
