<script setup>
import { ref, computed, onMounted, onBeforeUnmount } from 'vue'
import { Link, usePage, router } from '@inertiajs/vue3'
import UserDropdown from '@/Components/Dropdowns/UserDropdown.vue'
import FooterAdmin from '@/Components/Footers/FooterAdmin.vue'

import { useToast } from 'vue-toastification'
import { isSoundEnabled, setSoundEnabled, playNewTicketChime } from '@/Utils/soundAlert'

defineProps({
  ticketStats: Object,
  filters: {
    type: Object,
    default: () => ({ month: '', year: '' }),
  },
})

const page = usePage()
const toast = useToast()
const isDrawerOpen = ref(false)
const soundAlertsActive = ref(isSoundEnabled())

function toggleDrawer() {
  isDrawerOpen.value = !isDrawerOpen.value
}

function closeDrawer() {
  isDrawerOpen.value = false
}

function toggleSoundAlerts() {
  soundAlertsActive.value = !soundAlertsActive.value
  setSoundEnabled(soundAlertsActive.value)
  if (soundAlertsActive.value) {
    playNewTicketChime()
    toast.success('Sound alerts enabled')
  } else {
    toast.info('Sound alerts muted')
  }
}

function handleSoundToggled(event) {
  if (event?.detail?.enabled !== undefined) {
    soundAlertsActive.value = event.detail.enabled
  }
}

// Handle Escape key to close drawer
function handleKeydown(e) {
  if (e.key === 'Escape' && isDrawerOpen.value) {
    closeDrawer()
  }
}

let removeNavListener = null

onMounted(() => {
  window.addEventListener('keydown', handleKeydown)
  window.addEventListener('tickets:sound-toggled', handleSoundToggled)
  removeNavListener = router.on('navigate', () => {
    isDrawerOpen.value = false
  })
})

onBeforeUnmount(() => {
  window.removeEventListener('keydown', handleKeydown)
  window.removeEventListener('tickets:sound-toggled', handleSoundToggled)
  if (removeNavListener) removeNavListener()
})

const userRole = computed(() => {
  return page.props?.auth?.user?.role || 'Staff'
})

const userName = computed(() => {
  return page.props?.auth?.user?.name || 'User'
})

const userEmail = computed(() => {
  return page.props?.auth?.user?.email || ''
})

const currentBreadcrumb = computed(() => {
  const url = page.url || ''
  if (url.startsWith('/dashboard')) return { section: 'Overview', title: 'Dashboard' }
  if (url.startsWith('/admin/tickets-v2') || url.startsWith('/tickets-v2') || url.startsWith('/all-tickets')) {
    return { section: 'Tickets', title: 'All Tickets' }
  }
  if (url.startsWith('/admin/my-tickets')) return { section: 'Tickets', title: 'My Tickets' }
  if (url.startsWith('/ratings/dashboard')) return { section: 'Analytics', title: 'Customer Ratings' }
  if (url.startsWith('/csm') || url.startsWith('/reports')) return { section: 'Analytics', title: 'CSM Reports' }
  if (url.startsWith('/profile')) return { section: 'Settings', title: 'Profile' }
  return { section: 'Support', title: 'IT Helpdesk' }
})

// Tab navigation configuration
const navTabs = computed(() => [
  {
    name: 'Dashboard',
    label: 'Dashboard',
    route: 'dashboard',
    href: route('dashboard'),
    active: route().current('dashboard'),
    icon: 'dashboard',
  },
  {
    name: 'All Tickets',
    label: 'All Tickets',
    route: 'tickets-v2.index',
    href: route('tickets-v2.index'),
    active: route().current('tickets-v2.*') || page.url.startsWith('/admin/tickets-v2'),
    icon: 'tickets',
  },
  {
    name: 'My Tickets',
    label: 'My Tickets',
    route: 'admin.my-tickets',
    href: route('admin.my-tickets'),
    active: route().current('admin.my-tickets') || page.url.startsWith('/admin/my-tickets'),
    icon: 'user',
  },
  {
    name: 'Customer Ratings',
    label: 'Customer Ratings',
    route: 'ratings.dashboard',
    href: route('ratings.dashboard'),
    active: route().current('ratings.*') || page.url.startsWith('/ratings'),
    icon: 'star',
  },
  {
    name: 'CSM Reports',
    label: 'CSM Reports',
    route: 'reports.no-csm-all',
    href: route().has('reports.no-csm-all') ? route('reports.no-csm-all') : '#',
    active: route().current('reports.*') || page.url.startsWith('/csm') || page.url.startsWith('/reports'),
    icon: 'report',
  },
  {
    name: 'Settings',
    label: 'Settings',
    route: 'profile.edit',
    href: route('profile.edit'),
    active: route().current('profile.*'),
    icon: 'settings',
  },
])
</script>

<template>
  <div class="min-h-screen bg-slate-100 dark:bg-neutral-950 flex flex-col text-slate-800 dark:text-neutral-200 antialiased font-sans">

    <!-- ── 1. GITHUB TOP GLOBAL NAVBAR ──────────────────────────────────────── -->
    <header class="sticky top-0 z-40 bg-blue-950 dark:bg-blue-950 border-b border-blue-900/80 transition-colors shadow-xs">
      <div class="w-full max-w-[1720px] mx-auto px-3 sm:px-6 h-14 flex items-center justify-between gap-3">

        <!-- Left: Hamburger Toggle + Logo + Breadcrumbs -->
        <div class="flex items-center gap-2.5 sm:gap-3 min-w-0">
          <!-- Slide-out Drawer Hamburger Button (GitHub Style) -->
          <button
            type="button"
            @click="toggleDrawer"
            class="p-1.5 rounded-md text-blue-200 hover:text-white hover:bg-blue-900 dark:text-blue-200 dark:hover:text-white dark:hover:bg-blue-900 border border-blue-800 transition"
            aria-label="Open navigation menu"
            title="Open navigation menu"
          >
            <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
            </svg>
          </button>

          <!-- GitHub / Ticketing Mark -->
          <Link
                :href="route('dashboard')"
                class="flex items-center gap-2 shrink-0 group focus:outline-hidden"
                title="Ticketing System Dashboard"
          >
            <div class="w-8 h-8 rounded-lg bg-blue-600 dark:bg-blue-600 text-white flex items-center justify-center font-black text-xs tracking-wider shadow-sm ring-1 ring-white/20 group-hover:scale-105 transition-transform">
              TS
            </div>
          </Link>

          <!-- Breadcrumbs (GitHub Repo Style) -->
          <div class="flex items-center gap-1.5 text-xs sm:text-sm font-semibold truncate">
            <Link
              :href="route('dashboard')"
              class="text-blue-200 hover:text-white hover:underline truncate"
            >
              Ticketing System
            </Link>
            <span class="text-blue-400/60 font-normal">/</span>
            <span class="text-white font-bold truncate">
              {{ currentBreadcrumb.title }}
            </span>

            <!-- Private / Internal Badge -->
            <span class="hidden md:inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-semibold tracking-wide border border-blue-800 bg-blue-900/60 text-blue-200">
              Internal
            </span>
          </div>
        </div>

        <!-- Center: Search Input (GitHub Style 'Type / to search') -->
        <div class="hidden lg:flex items-center flex-1 max-w-sm mx-4">
          <div class="relative w-full">
            <div class="absolute inset-y-0 left-0 pl-2.5 flex items-center pointer-events-none text-blue-300/60">
              <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
              </svg>
            </div>
            <input
              type="text"
              placeholder="Type / to search tickets..."
              class="w-full pl-8 pr-10 py-1.5 text-xs bg-blue-900/40 dark:bg-blue-900/40 border border-blue-800 rounded-lg text-white placeholder-blue-300/60 focus:outline-hidden focus:ring-1 focus:ring-blue-400 focus:bg-blue-900/70 transition"
              @keydown.slash.prevent="$event.target.focus()"
            />
            <div class="absolute inset-y-0 right-0 pr-2 flex items-center pointer-events-none">
              <kbd class="px-1.5 py-0.5 text-[10px] font-mono text-blue-300 bg-blue-950 border border-blue-800 rounded shadow-2xs">
                /
              </kbd>
            </div>
          </div>
        </div>

        <!-- Right: Actions & User Profile -->
        <div class="flex items-center gap-2 sm:gap-3 shrink-0">
          <!-- User Role Badge -->
          <span
            class="hidden sm:inline-flex items-center px-2.5 py-0.5 rounded-full text-[11px] font-bold tracking-wide uppercase border"
            :class="userRole === 'Admin' || userRole === 'Super Admin'
              ? 'bg-purple-950/60 text-purple-300 border-purple-800/80 dark:bg-purple-950/40 dark:text-purple-300 dark:border-purple-800'
              : 'bg-blue-900/60 text-blue-200 border-blue-700/80'"
          >
            {{ userRole }}
          </span>

          <!-- Public Client Tracker Portal Link -->
          <a
            href="/client"
            target="_blank"
            rel="noopener noreferrer"
            class="hidden md:inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-semibold rounded-lg border border-blue-800 bg-blue-900/40 text-blue-100 hover:bg-blue-800/60 transition"
            title="Open client ticket tracker & submission portal in new tab"
          >
            <span>Client Tracker</span>
            <svg class="w-3.5 h-3.5 text-blue-300" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14" />
            </svg>
          </a>

          <!-- User Dropdown Menu -->
          <UserDropdown :is-dark-header="true" />
        </div>
      </div>

      <!-- ── 2. GITHUB SUB-NAVIGATION HORIZONTAL TABS ────────────────────────── -->
      <div class="border-t border-blue-900/80 bg-blue-950/95 dark:bg-blue-950/95 backdrop-blur-xs">
        <div class="w-full max-w-[1720px] mx-auto px-3 sm:px-6 flex items-center justify-between gap-3">
          <nav class="flex items-center space-x-1 sm:space-x-2 overflow-x-auto no-scrollbar py-0.5 min-w-0" aria-label="Tabs">
            <Link
              v-for="tab in navTabs"
              :key="tab.name"
              :href="tab.href"
              class="group relative inline-flex items-center gap-2 px-3 py-2.5 text-xs sm:text-sm font-medium whitespace-nowrap transition-colors shrink-0"
              :class="tab.active
                ? 'text-white dark:text-white font-semibold'
                : 'text-blue-200/75 hover:text-white hover:bg-blue-900/60 rounded-md my-0.5'"
            >
              <!-- Icons -->
              <span class="shrink-0 text-blue-300/75 group-hover:text-blue-100" :class="{ 'text-white dark:text-white': tab.active }">
                <!-- Dashboard Icon -->
                <svg v-if="tab.icon === 'dashboard'" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zM14 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zM14 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z" />
                </svg>

                <!-- Tickets Icon -->
                <svg v-else-if="tab.icon === 'tickets'" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 5v2m0 4v2m0 4v2M5 5a2 2 0 00-2 2v3a2 2 0 110 4v3a2 2 0 002 2h14a2 2 0 002-2v-3a2 2 0 110-4V7a2 2 0 00-2-2H5z" />
                </svg>

                <!-- My Tickets (User) Icon -->
                <svg v-else-if="tab.icon === 'user'" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                </svg>

                <!-- Star (Ratings) Icon -->
                <svg v-else-if="tab.icon === 'star'" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11.049 2.927c.3-.921 1.603-.921 1.902 0l1.519 4.674a1 1 0 00.95.69h4.915c.969 0 1.371 1.24.588 1.81l-3.976 2.888a1 1 0 00-.363 1.118l1.518 4.674c.3.922-.755 1.688-1.538 1.118l-3.976-2.888a1 1 0 00-1.176 0l-3.976 2.888c-.783.57-1.838-.197-1.538-1.118l1.518-4.674a1 1 0 00-.363-1.118l-3.976-2.888c-.784-.57-.38-1.81.588-1.81h4.914a1 1 0 00.951-.69l1.519-4.674z" />
                </svg>

                <!-- Report (CSM) Icon -->
                <svg v-else-if="tab.icon === 'report'" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                </svg>

                <!-- Settings Icon -->
                <svg v-else-if="tab.icon === 'settings'" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z" />
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                </svg>
              </span>

              <span>{{ tab.label }}</span>

              <!-- Active Tab Theme Indicator Accent -->
              <span
                v-if="tab.active"
                class="absolute bottom-0 left-0 right-0 h-0.5 bg-blue-400 dark:bg-blue-400 rounded-t-sm shadow-xs"
              ></span>
            </Link>
          </nav>

          <!-- Right side: Live Dispatch Mode & Sound Notification Controls -->
          <div class="flex items-center gap-2 shrink-0 py-1.5">
            <div class="inline-flex items-center gap-1.5 bg-blue-900/50 p-1 rounded-xl border border-blue-800/80">
              <!-- Pulse Live Indicator -->
              <div
                class="inline-flex items-center px-2 py-1 text-xs font-semibold rounded-lg bg-emerald-950/70 text-emerald-300 border border-emerald-800/70 gap-1.5 select-none"
                title="Real-Time Dispatch Mode active: monitoring for new tickets and messages"
              >
                <span class="relative flex h-2 w-2">
                  <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-emerald-400 opacity-75"></span>
                  <span class="relative inline-flex rounded-full h-2 w-2 bg-emerald-500"></span>
                </span>
                <span class="hidden sm:inline">Live Dispatch</span>
              </div>

              <!-- Sound Mute/Unmute Toggle Button -->
              <button
                type="button"
                @click="toggleSoundAlerts"
                class="inline-flex items-center px-2.5 py-1 text-xs font-semibold rounded-lg border transition gap-1.5 shadow-2xs cursor-pointer"
                :class="soundAlertsActive
                  ? 'border-blue-700 bg-blue-800/90 text-blue-100 hover:bg-blue-700'
                  : 'border-blue-900/70 bg-blue-950/80 text-blue-300/60 hover:text-blue-200 hover:bg-blue-900/40'"
                :title="soundAlertsActive ? 'Sound notifications active (Click to mute)' : 'Sound notifications muted (Click to unmute)'"
              >
                <i :class="soundAlertsActive ? 'fa-solid fa-volume-high text-blue-400' : 'fa-solid fa-volume-xmark text-slate-400'"></i>
                <span class="hidden md:inline">{{ soundAlertsActive ? 'Sound On' : 'Muted' }}</span>
              </button>
            </div>
          </div>
        </div>
      </div>
    </header>

    <!-- ── 3. GITHUB SLIDE-OUT LEFT DRAWER ──────────────────────────────────── -->
    <!-- Drawer Overlay Backdrop -->
    <div
      v-if="isDrawerOpen"
      class="fixed inset-0 z-50 bg-black/50 backdrop-blur-xs transition-opacity"
      @click="closeDrawer"
      aria-hidden="true"
    ></div>

    <!-- Drawer Panel (Slides out from left) -->
    <aside
      class="fixed top-0 bottom-0 left-0 z-50 w-80 max-w-[85vw] bg-white dark:bg-[#0d1117] border-r border-slate-200 dark:border-neutral-800 shadow-2xl flex flex-col transition-transform duration-200 ease-in-out"
      :class="isDrawerOpen ? 'translate-x-0' : '-translate-x-full'"
      role="dialog"
      aria-modal="true"
      aria-label="Application Navigation Drawer"
    >
      <!-- Drawer Header -->
      <div class="px-5 py-4 border-b border-slate-200 dark:border-neutral-800 flex items-center justify-between">
        <div class="flex items-center gap-3">
          <div class="w-8 h-8 rounded-lg bg-blue-600 text-white flex items-center justify-center font-bold text-xs shadow-xs">
            TS
          </div>
          <div>
            <h2 class="text-sm font-bold text-slate-900 dark:text-white leading-tight">Ticketing System</h2>
            <p class="text-[11px] text-slate-500 dark:text-neutral-400">IT Support Administration</p>
          </div>
        </div>

        <button
          type="button"
          @click="closeDrawer"
          class="p-1.5 rounded-lg text-slate-400 hover:text-slate-700 dark:hover:text-white hover:bg-slate-100 dark:hover:bg-neutral-800 transition"
          aria-label="Close navigation drawer"
        >
          <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
          </svg>
        </button>
      </div>

      <!-- Drawer Menu List -->
      <div class="flex-1 overflow-y-auto px-3 py-4 space-y-6">
        <!-- Main Navigation -->
        <div>
          <div class="px-3 pb-2 text-[10px] font-bold uppercase tracking-wider text-slate-400 dark:text-neutral-500">
            Workspace
          </div>
          <div class="space-y-0.5">
            <Link
              v-for="item in navTabs"
              :key="item.name"
              :href="item.href"
              @click="closeDrawer"
              class="flex items-center gap-3 px-3 py-2 rounded-lg text-xs font-medium transition"
              :class="item.active
                ? 'bg-blue-50 text-blue-700 font-semibold dark:bg-blue-950/80 dark:text-blue-300'
                : 'text-slate-600 dark:text-neutral-300 hover:bg-slate-50 dark:hover:bg-neutral-800/60 hover:text-slate-900 dark:hover:text-white'"
            >
              <span :class="item.active ? 'text-blue-600 dark:text-blue-400' : 'text-slate-400 dark:text-neutral-500'">
                <svg v-if="item.icon === 'dashboard'" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zM14 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zM14 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z" /></svg>
                <svg v-else-if="item.icon === 'tickets'" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 5v2m0 4v2m0 4v2M5 5a2 2 0 00-2 2v3a2 2 0 110 4v3a2 2 0 002 2h14a2 2 0 002-2v-3a2 2 0 110-4V7a2 2 0 00-2-2H5z" /></svg>
                <svg v-else-if="item.icon === 'user'" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" /></svg>
                <svg v-else-if="item.icon === 'star'" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11.049 2.927c.3-.921 1.603-.921 1.902 0l1.519 4.674a1 1 0 00.95.69h4.915c.969 0 1.371 1.24.588 1.81l-3.976 2.888a1 1 0 00-.363 1.118l1.518 4.674c.3.922-.755 1.688-1.538 1.118l-3.976-2.888a1 1 0 00-1.176 0l-3.976 2.888c-.783.57-1.838-.197-1.538-1.118l1.518-4.674a1 1 0 00-.363-1.118l-3.976-2.888c-.784-.57-.38-1.81.588-1.81h4.914a1 1 0 00.951-.69l1.519-4.674z" /></svg>
                <svg v-else-if="item.icon === 'report'" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" /></svg>
                <svg v-else-if="item.icon === 'settings'" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z" /><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" /></svg>
              </span>
              <span>{{ item.label }}</span>
            </Link>
          </div>
        </div>

        <!-- External Portals -->
        <div>
          <div class="px-3 pb-2 text-[10px] font-bold uppercase tracking-wider text-slate-400 dark:text-neutral-500">
            External Portals
          </div>
          <a
            href="/client"
            target="_blank"
            class="flex items-center justify-between px-3 py-2 rounded-lg text-xs font-medium text-slate-600 dark:text-neutral-300 hover:bg-slate-50 dark:hover:bg-neutral-800/60 hover:text-slate-900 transition"
          >
            <div class="flex items-center gap-3">
              <svg class="w-4 h-4 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 12a9 9 0 01-9 9m9-9a9 9 0 00-9-9m9 9H3m9 9a9 9 0 01-9-9m9 9c1.657 0 3-4.03 3-9s-1.343-9-3-9m0 18c-1.657 0-3-4.03-3-9s1.343-9 3-9m-9 9a9 9 0 019-9" />
              </svg>
              <span>Client Ticket Portal</span>
            </div>
            <svg class="w-3.5 h-3.5 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14" />
            </svg>
          </a>
        </div>
      </div>

      <!-- Drawer Footer User Profile -->
      <div class="p-4 border-t border-slate-200 dark:border-neutral-800 bg-slate-50/50 dark:bg-neutral-900/50 flex items-center justify-between">
        <div class="flex items-center gap-2.5 min-w-0">
          <div class="w-8 h-8 rounded-full bg-gradient-to-tr from-blue-600 to-blue-800 text-white flex items-center justify-center font-bold text-xs shrink-0">
            {{ userName.charAt(0).toUpperCase() }}
          </div>
          <div class="min-w-0">
            <p class="text-xs font-semibold text-slate-900 dark:text-white truncate">{{ userName }}</p>
            <p class="text-[10px] text-slate-500 truncate">{{ userEmail }}</p>
          </div>
        </div>

        <button
          type="button"
          @click="router.post('/logout')"
          class="p-1.5 rounded-lg text-slate-400 hover:text-red-600 hover:bg-red-50 dark:hover:bg-red-950/40 transition"
          title="Sign Out"
        >
          <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1" />
          </svg>
        </button>
      </div>
    </aside>

    <!-- ── 4. FULL-WIDTH MAIN CONTENT AREA ─────────────────────────────────── -->
    <main class="flex-1 w-full max-w-[1720px] mx-auto px-3 sm:px-6 lg:px-8 py-5">
      <slot />
    </main>

    <!-- ── 5. FOOTER ───────────────────────────────────────────────────────── -->
    <FooterAdmin />
  </div>
</template>
