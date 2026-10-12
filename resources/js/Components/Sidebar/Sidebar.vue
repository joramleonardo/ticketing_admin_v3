<template>
  <div>
    <!-- Overlay (only mobile, when sidebar is open) -->
    <div
      v-if="isOpen"
      class="fixed inset-0 bg-black/40 z-30 md:hidden"
      @click="toggleSidebar"
    ></div>

    <!-- Sidebar -->
    <nav
      class="fixed top-0 left-0 z-40 h-screen w-64 md:w-72
             bg-slate-900 text-slate-100
             border-r border-slate-800
             shadow-xl backdrop-blur flex flex-col
             transition-transform duration-300 ease-in-out md:translate-x-0"
      :class="{ '-translate-x-full': !isOpen }"
      role="navigation"
      aria-label="Main"
    >
      <!-- Header / Brand -->
      <div
        class="flex items-center justify-between px-6 py-4 border-b border-slate-800"
      >
        <div class="flex items-center gap-3">
          <div
            class="h-9 w-9 rounded-xl bg-blue-500/20 text-blue-300 flex items-center justify-center text-sm font-semibold"
          >
            TS
          </div>
          <div class="flex flex-col">
            <Link
              :href="route('dashboard')"
              class="text-sm font-semibold text-slate-50 tracking-tight"
            >
              Ticketing System
            </Link>
            <!-- <span class="text-[11px] text-slate-400">
              Internal Support Desk
            </span> -->
          </div>
        </div>

        <!-- Close button (mobile) -->
        <button
          @click="toggleSidebar"
          class="md:hidden text-slate-300 hover:text-white transition-colors"
          aria-label="Close sidebar"
        >
          <i class="fas fa-times text-lg"></i>
        </button>
      </div>

      <!-- User Dropdown (Mobile Only) -->
      <div
        class="md:hidden flex items-center justify-between px-6 py-3 border-b border-slate-800 bg-slate-800/80"
      >
        <notification-dropdown />
        <user-dropdown />
      </div>

      <!-- Menu -->
      <ul class="flex-1 mt-2 px-2 pb-3 pt-1 space-y-1 overflow-y-auto">
        <!-- Overview section -->
        <li
          class="px-3 pt-2 text-[11px] font-semibold tracking-[0.16em] text-slate-500 select-none"
        >
          OVERVIEW
        </li>

        <SidebarItem
          :icon="`<svg xmlns='http://www.w3.org/2000/svg' width='16' height='16' fill='currentColor' class='bi bi-bar-chart-fill' viewBox='0 0 16 16'><path d='M1 11a1 1 0 0 1 1-1h2a1 1 0 0 1 1 1v3a1 1 0 0 1-1 1H2a1 1 0 0 1-1-1zm5-4a1 1 0 0 1 1-1h2a1 1 0 0 1 1 1v7a1 1 0 0 1-1 1H7a1 1 0 0 1-1-1zm5-5a1 1 0 0 1 1-1h2a1 1 0 0 1 1 1v12a1 1 0 0 1-1 1h-2a1 1 0 0 1-1-1z'/></svg>`"
          label="Dashboard"
          :href="route('dashboard')"
          :active="route().current('dashboard')"
          @click.native="closeOnMobile"
        />

        <!-- Tickets section -->
        <li
          class="px-3 pt-4 text-[11px] font-semibold tracking-[0.16em] text-slate-500 select-none"
        >
          TICKETS
        </li>

        <SidebarItem
          :icon="`<svg xmlns='http://www.w3.org/2000/svg' fill='currentColor' viewBox='0 0 16 16'><path d='M3 4h10v2H3V4zm0 4h10v2H3V8zm0 4h10v2H3v-2z'/></svg>`"
          label="Tickets"
          :href="route('tickets-v2.index')"
          :active="route().current('tickets-v2.index')"
          @click.native="closeOnMobile"
        />

        <SidebarItem
          :icon="`<svg xmlns='http://www.w3.org/2000/svg' width='16' height='16' fill='currentColor' class='bi bi-person-lines-fill' viewBox='0 0 16 16'><path d='M6 8a3 3 0 1 0 0-6 3 3 0 0 0 0 6m-5 6s-1 0-1-1 1-4 6-4 6 3 6 4-1 1-1 1zM11 3.5a.5.5 0 0 1 .5-.5h4a.5.5 0 0 1 0 1h-4a.5.5 0 0 1-.5-.5m.5 2.5a.5.5 0 0 0 0 1h4a.5.5 0 0 0 0-1zm2 3a.5.5 0 0 0 0 1h2a.5.5 0 0 0 0-1zm0 3a.5.5 0 0 0 0 1h2a.5.5 0 0 0 0-1z'/></svg>`"
          label="My Tickets"
          :href="route('admin.my-tickets')"
          :active="route().current('admin.my-tickets')"
          @click.native="closeOnMobile"
        />

        <!-- CSM / Ratings -->
        <li
          class="px-3 pt-4 text-[11px] font-semibold tracking-[0.16em] text-slate-500 select-none"
        >
          CSM / RATINGS
        </li>

        <SidebarItem
          :icon="`<svg xmlns='http://www.w3.org/2000/svg' width='16' height='16' fill='currentColor' class='bi bi-star-fill' viewBox='0 0 16 16'><path d='M3.612 15.443c-.386.198-.824-.149-.746-.592l.83-4.73L.173 6.765c-.329-.314-.158-.888.283-.95l4.898-.696L7.538.792c.197-.39.73-.39.927 0l2.184 4.327 4.898.696c.441.062.612.636.282.95l-3.522 3.356.83 4.73c.078.443-.36.79-.746.592L8 13.187l-4.389 2.256z'/></svg>`"
          label="Ratings Dashboard"
          :href="route('ratings.dashboard')"
          :active="route().current('ratings.dashboard')"
          @click.native="closeOnMobile"
        />

        <SidebarItem
          v-if="route().has('reports.no-csm-all')"
          :icon="`<svg xmlns='http://www.w3.org/2000/svg' width='16' height='16' fill='currentColor' class='bi bi-check-circle-fill' viewBox='0 0 16 16'><path d='M16 8A8 8 0 1 1 0 8a8 8 0 0 1 16 0m-3.97-3.03a.75.75 0 0 0-1.08.022L7.477 9.417 5.384 7.323a.75.75 0 0 0-1.06 1.06L6.97 11.03a.75.75 0 0 0 1.079-.02l3.992-4.99a.75.75 0 0 0-.01-1.05z'/></svg>`"
          label="CSM Dashboard"
          :href="route().has('reports.no-csm-all') ? route('reports.no-csm-all') : '#'"
          :active="route().current('reports.no-csm-all')"
          @click.native="closeOnMobile"
        />
      </ul>

      <!-- Footer (always visible) -->
      <!-- <div
        class="border-t border-slate-800 px-4 py-3 text-[11px] text-slate-500 flex items-center justify-between"
      >
        <span>Ticketing v2</span>
        <span class="uppercase tracking-[0.12em]">Admin</span>
      </div> -->
    </nav>

    <!-- Toggle button (mobile only) -->
    <button
      @click="toggleSidebar"
      class="fixed top-4 left-4 z-50 p-2.5 bg-blue-600 text-white rounded-lg shadow-lg md:hidden hover:bg-blue-700 active:scale-[0.97] transition"
      aria-label="Open sidebar"
    >
      <i class="fas fa-bars text-sm"></i>
    </button>
  </div>
</template>

<script>
import { Link } from '@inertiajs/vue3'
import NotificationDropdown from '@/Components/Dropdowns/NotificationDropdown.vue'
import UserDropdown from '@/Components/Dropdowns/UserDropdown.vue'
import SidebarItem from '@/Components/Sidebar/SidebarItem.vue'

export default {
  components: { NotificationDropdown, UserDropdown, SidebarItem, Link },
  data() {
    return { isOpen: false }
  },
  methods: {
    toggleSidebar() {
      this.isOpen = !this.isOpen
    },
    closeOnMobile() {
      if (window.innerWidth < 768) this.isOpen = false
    },
  },
  watch: {
    '$page.url'() {
      if (window.innerWidth < 768) this.isOpen = false
    },
  },
}
</script>
