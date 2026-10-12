<template>
  <div class="relative">
    <!-- Trigger -->
    <a
      href="#"
      ref="btnDropdownRef"
      @click.prevent="toggleDropdown"
      class="flex items-center gap-2 transition-colors"
      :class="isDarkHeader
        ? 'text-slate-200 hover:text-white'
        : 'text-slate-700 dark:text-slate-100 hover:text-blue-600 dark:hover:text-blue-400'"
    >
      <!-- Avatar -->
      <div class="relative">
        <span
          class="w-9 h-9 text-xs font-semibold text-white bg-gradient-to-br from-blue-600 to-blue-800 inline-flex items-center justify-center rounded-full shadow-sm"
        >
          {{ userInitials }}
        </span>
        <!-- Optional online dot -->
        <span
          class="absolute -bottom-0.5 -right-0.5 h-2.5 w-2.5 rounded-full bg-emerald-500 border-[2px] border-white dark:border-neutral-900"
        ></span>
      </div>

      <!-- Name (desktop only, keeps navbar compact on smaller widths) -->
      <div class="hidden md:flex flex-col items-start">
        <span
          class="text-[11px] uppercase tracking-[0.16em]"
          :class="isDarkHeader ? 'text-slate-400' : 'text-gray-400 dark:text-gray-500'"
        >
          Account
        </span>
        <span
          class="text-sm font-medium truncate max-w-[140px]"
          :class="isDarkHeader ? 'text-slate-100' : ''"
          :title="userName"
        >
          {{ userName }}
        </span>
      </div>

      <i
        class="fas fa-chevron-down text-[10px] ml-1 md:block hidden"
        :class="isDarkHeader ? 'text-slate-400' : 'text-gray-400'"
      ></i>
    </a>

    <!-- Dropdown -->
    <div
      ref="popoverDropdownRef"
      class="mt-2 bg-white dark:bg-neutral-900 text-sm z-50 py-2 list-none text-left rounded-xl shadow-xl ring-1 ring-black/5 min-w-56"
      :class="{ hidden: !dropdownPopoverShow, block: dropdownPopoverShow }"
    >
      <!-- Header / user info -->
      <div class="px-4 pb-2 pt-2.5">
        <p class="text-[11px] uppercase tracking-[0.16em] text-gray-400 dark:text-gray-500">
          Signed in as
        </p>
        <p
          class="text-sm font-semibold text-slate-900 dark:text-slate-50 truncate"
          :title="userName"
        >
          {{ userName }}
        </p>
      </div>

      <div class="border-t border-gray-100 dark:border-neutral-800 my-2"></div>

      <!-- Menu items -->
      <a
        href="/profile"
        class="flex items-center gap-2 px-4 py-2 text-sm font-normal text-blueGray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-neutral-800/80 cursor-pointer"
      >
        <i class="fas fa-user-cog text-xs w-4 text-gray-400"></i>
        <span>Edit Profile</span>
      </a>

      <button
        type="button"
        @click.prevent="logout"
        class="w-full text-left flex items-center gap-2 px-4 py-2 text-sm font-normal text-red-600 hover:bg-red-50/80 dark:hover:bg-red-900/30 cursor-pointer"
      >
        <i class="fas fa-sign-out-alt text-xs w-4"></i>
        <span>Sign Out</span>
      </button>
    </div>
  </div>
</template>

<script>
import { createPopper } from "@popperjs/core"
import { router } from "@inertiajs/vue3"

export default {
  props: {
    isDarkHeader: {
      type: Boolean,
      default: false,
    },
  },
  data() {
    return {
      dropdownPopoverShow: false,
    }
  },
  computed: {
    userName() {
      return this.$page?.props?.auth?.user?.name || "User"
    },
    userInitials() {
      const name = this.userName.trim()
      if (!name) return "U"
      const parts = name.split(" ").filter(Boolean)
      if (parts.length === 1) return parts[0].charAt(0).toUpperCase()
      return (
        parts[0].charAt(0).toUpperCase() +
        parts[parts.length - 1].charAt(0).toUpperCase()
      )
    },
  },
  methods: {
    toggleDropdown(event) {
      event?.preventDefault?.()
      this.dropdownPopoverShow = !this.dropdownPopoverShow
      if (this.dropdownPopoverShow) {
        createPopper(this.$refs.btnDropdownRef, this.$refs.popoverDropdownRef, {
          placement: "bottom-start",
        })
      }
    },
    logout() {
      this.dropdownPopoverShow = false
      router.post("/logout")
    },
  },
}
</script>
