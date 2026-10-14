<script setup>
import { ref, computed, onMounted, onBeforeUnmount, nextTick } from 'vue'
import { Link, router, usePage } from '@inertiajs/vue3'
import { createPopper } from '@popperjs/core'

const props = defineProps({
  isDarkHeader: {
    type: Boolean,
    default: false,
  },
})

const page = usePage()
const btnDropdownRef = ref(null)
const popoverDropdownRef = ref(null)
const dropdownPopoverShow = ref(false)
let popperInstance = null

const user = computed(() => page.props?.auth?.user || {})
const userName = computed(() => user.value.name || 'User')
const userEmail = computed(() => user.value.email || '')
const userRole = computed(() => user.value.role || 'Staff')

const userInitials = computed(() => {
  const name = userName.value.trim()
  if (!name) return 'U'
  const parts = name.split(' ').filter(Boolean)
  if (parts.length === 1) return parts[0].charAt(0).toUpperCase()
  return (
    parts[0].charAt(0).toUpperCase() +
    parts[parts.length - 1].charAt(0).toUpperCase()
  )
})

const roleBadge = computed(() => {
  switch (userRole.value) {
    case 'Super Admin':
      return {
        label: 'Super Admin',
        icon: '👑',
        classes: 'bg-purple-100 text-purple-800 dark:bg-purple-950/80 dark:text-purple-300 border-purple-200 dark:border-purple-800',
      }
    case 'Admin':
      return {
        label: 'Admin',
        icon: '🛡️',
        classes: 'bg-blue-100 text-blue-800 dark:bg-blue-950/80 dark:text-blue-300 border-blue-200 dark:border-blue-800',
      }
    case 'Staff':
    default:
      return {
        label: 'Staff Specialist',
        icon: '⚡',
        classes: 'bg-emerald-100 text-emerald-800 dark:bg-emerald-950/80 dark:text-emerald-300 border-emerald-200 dark:border-emerald-800',
      }
  }
})

function handleClickOutside(e) {
  if (!dropdownPopoverShow.value) return
  const triggerEl = btnDropdownRef.value
  const popoverEl = popoverDropdownRef.value
  if (
    triggerEl && !triggerEl.contains(e.target) &&
    popoverEl && !popoverEl.contains(e.target)
  ) {
    dropdownPopoverShow.value = false
  }
}

function handleEscape(e) {
  if (e.key === 'Escape' && dropdownPopoverShow.value) {
    dropdownPopoverShow.value = false
  }
}

function toggleDropdown(event) {
  event?.preventDefault?.()
  dropdownPopoverShow.value = !dropdownPopoverShow.value
  if (dropdownPopoverShow.value) {
    nextTick(() => {
      if (!popperInstance && btnDropdownRef.value && popoverDropdownRef.value) {
        popperInstance = createPopper(btnDropdownRef.value, popoverDropdownRef.value, {
          placement: 'bottom-end',
          modifiers: [
            {
              name: 'offset',
              options: {
                offset: [0, 8],
              },
            },
          ],
        })
      } else if (popperInstance) {
        popperInstance.update()
      }
    })
  }
}

function closeDropdown() {
  dropdownPopoverShow.value = false
}

function logout() {
  closeDropdown()
  router.post(route('logout'))
}

onMounted(() => {
  document.addEventListener('click', handleClickOutside)
  document.addEventListener('keydown', handleEscape)
})

onBeforeUnmount(() => {
  document.removeEventListener('click', handleClickOutside)
  document.removeEventListener('keydown', handleEscape)
  if (popperInstance) {
    popperInstance.destroy()
    popperInstance = null
  }
})
</script>

<template>
  <div class="relative">
    <!-- Trigger -->
    <button
      type="button"
      ref="btnDropdownRef"
      @click="toggleDropdown"
      class="flex items-center gap-2 transition-colors cursor-pointer select-none focus:outline-hidden"
      :class="isDarkHeader
        ? 'text-slate-200 hover:text-white'
        : 'text-slate-700 dark:text-slate-100 hover:text-blue-600 dark:hover:text-blue-400'"
      aria-haspopup="true"
      :aria-expanded="dropdownPopoverShow ? 'true' : 'false'"
    >
      <!-- Avatar -->
      <div class="relative">
        <span
          class="w-9 h-9 text-xs font-semibold text-white bg-gradient-to-br from-blue-600 to-blue-800 inline-flex items-center justify-center rounded-full shadow-sm"
        >
          {{ userInitials }}
        </span>
        <!-- Online dot -->
        <span
          class="absolute -bottom-0.5 -right-0.5 h-2.5 w-2.5 rounded-full bg-emerald-500 border-[2px] border-white dark:border-neutral-900"
        ></span>
      </div>

      <!-- Name & Role (desktop only, keeps navbar compact on smaller widths) -->
      <div class="hidden md:flex flex-col items-start text-left">
        <span
          class="text-[10px] uppercase tracking-[0.16em]"
          :class="isDarkHeader ? 'text-slate-400' : 'text-gray-400 dark:text-gray-500'"
        >
          {{ userRole }}
        </span>
        <span
          class="text-sm font-medium truncate max-w-[140px]"
          :class="isDarkHeader ? 'text-slate-100' : 'text-slate-800 dark:text-slate-100'"
          :title="userName"
        >
          {{ userName }}
        </span>
      </div>

      <i
        class="fas fa-chevron-down text-[10px] ml-1 md:block hidden transition-transform duration-150"
        :class="[
          isDarkHeader ? 'text-slate-400' : 'text-gray-400',
          dropdownPopoverShow ? 'rotate-180' : ''
        ]"
      ></i>
    </button>

    <!-- Dropdown -->
    <div
      ref="popoverDropdownRef"
      class="mt-2 bg-white dark:bg-neutral-900 text-sm z-50 py-2 list-none text-left rounded-2xl shadow-2xl border border-slate-200 dark:border-neutral-800 min-w-64"
      :class="{ hidden: !dropdownPopoverShow, block: dropdownPopoverShow }"
    >
      <!-- Header / User Info -->
      <div class="px-4 py-2.5 bg-slate-50/80 dark:bg-neutral-800/50 border-b border-slate-100 dark:border-neutral-800">
        <div class="flex items-center justify-between gap-2 mb-1">
          <p class="text-[10px] uppercase tracking-[0.16em] font-semibold text-slate-400 dark:text-slate-400">
            Signed in as
          </p>
          <span
            class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-bold border"
            :class="roleBadge.classes"
          >
            <span>{{ roleBadge.icon }}</span>
            <span>{{ roleBadge.label }}</span>
          </span>
        </div>
        <p class="text-sm font-bold text-slate-900 dark:text-slate-50 truncate" :title="userName">
          {{ userName }}
        </p>
        <p v-if="userEmail" class="text-xs text-slate-500 dark:text-slate-400 truncate mt-0.5" :title="userEmail">
          {{ userEmail }}
        </p>
      </div>

      <!-- Role-Specific Quick Navigation Links -->
      <div class="py-1.5 border-b border-slate-100 dark:border-neutral-800">
        <!-- Super Admin: Users & Role Management -->
        <Link
          v-if="userRole === 'Super Admin'"
          :href="route('admin.users.index')"
          @click="closeDropdown"
          class="flex items-center gap-2.5 px-4 py-2 text-xs font-medium text-slate-700 dark:text-gray-200 hover:bg-purple-50 dark:hover:bg-purple-950/40 hover:text-purple-700 transition"
        >
          <div class="w-6 h-6 rounded-lg bg-purple-50 dark:bg-purple-950/70 text-purple-600 flex items-center justify-center shrink-0">
            <i class="fas fa-users-gear text-xs"></i>
          </div>
          <span>Users & Role Management</span>
        </Link>

        <!-- Admin or Super Admin: All Tickets Dispatcher -->
        <Link
          v-if="userRole === 'Admin' || userRole === 'Super Admin'"
          :href="route('tickets-v2.index')"
          @click="closeDropdown"
          class="flex items-center gap-2.5 px-4 py-2 text-xs font-medium text-slate-700 dark:text-gray-200 hover:bg-blue-50 dark:hover:bg-blue-950/40 hover:text-blue-700 transition"
        >
          <div class="w-6 h-6 rounded-lg bg-blue-50 dark:bg-blue-950/70 text-blue-600 flex items-center justify-center shrink-0">
            <i class="fas fa-ticket text-xs"></i>
          </div>
          <span>All Tickets Dispatcher</span>
        </Link>

        <!-- Staff: My Tickets Workspace -->
        <Link
          v-if="userRole === 'Staff' || userRole === 'Admin'"
          :href="route('admin.my-tickets')"
          @click="closeDropdown"
          class="flex items-center gap-2.5 px-4 py-2 text-xs font-medium text-slate-700 dark:text-gray-200 hover:bg-emerald-50 dark:hover:bg-emerald-950/40 hover:text-emerald-700 transition"
        >
          <div class="w-6 h-6 rounded-lg bg-emerald-50 dark:bg-emerald-950/70 text-emerald-600 flex items-center justify-center shrink-0">
            <i class="fas fa-list-check text-xs"></i>
          </div>
          <span>My Assigned Tickets</span>
        </Link>

        <!-- Customer Ratings -->
        <Link
          :href="route('ratings.dashboard')"
          @click="closeDropdown"
          class="flex items-center gap-2.5 px-4 py-2 text-xs font-medium text-slate-700 dark:text-gray-200 hover:bg-amber-50 dark:hover:bg-amber-950/40 hover:text-amber-700 transition"
        >
          <div class="w-6 h-6 rounded-lg bg-amber-50 dark:bg-amber-950/70 text-amber-600 flex items-center justify-center shrink-0">
            <i class="fas fa-star text-xs"></i>
          </div>
          <span>Customer Ratings</span>
        </Link>
      </div>

      <!-- Account Settings & Logout -->
      <div class="py-1">
        <Link
          :href="route('profile.edit')"
          @click="closeDropdown"
          class="flex items-center gap-2.5 px-4 py-2 text-xs font-medium text-slate-700 dark:text-gray-200 hover:bg-slate-50 dark:hover:bg-neutral-800/80 transition"
        >
          <div class="w-6 h-6 rounded-lg bg-slate-100 dark:bg-neutral-800 text-slate-500 flex items-center justify-center shrink-0">
            <i class="fas fa-user-gear text-xs"></i>
          </div>
          <span>Profile & Account Settings</span>
        </Link>

        <button
          type="button"
          @click="logout"
          class="w-full text-left flex items-center gap-2.5 px-4 py-2 text-xs font-medium text-rose-600 hover:bg-rose-50/80 dark:hover:bg-rose-950/30 cursor-pointer transition"
        >
          <div class="w-6 h-6 rounded-lg bg-rose-50 dark:bg-rose-950/70 text-rose-600 flex items-center justify-center shrink-0">
            <i class="fas fa-sign-out-alt text-xs"></i>
          </div>
          <span>Sign Out</span>
        </button>
      </div>
    </div>
  </div>
</template>
