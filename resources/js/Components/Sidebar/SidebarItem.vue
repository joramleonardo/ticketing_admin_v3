<template>
  <Link
    :href="href"
    class="group flex items-center gap-3 px-3 py-2.5 rounded-md text-base transition-colors duration-150"
    :class="
      active
        ? 'bg-slate-800 text-slate-50 font-semibold border-l-2 border-blue-400'
        : 'text-slate-300 hover:bg-slate-800/80 hover:text-slate-50'
    "
  >
    <!-- Inline SVG icon -->
    <span
      v-if="icon"
      class="w-4 h-4 flex-shrink-0 text-slate-400 group-hover:text-blue-300"
      v-html="icon"
    ></span>

    <span class="truncate">
      {{ label }}
    </span>
  </Link>
</template>

<script>
import { Link } from '@inertiajs/vue3'

export default {
  name: 'SidebarItem',
  components: { Link },
  props: {
    icon: {
      type: String,
      default: null,
    },
    label: {
      type: String,
      required: true,
    },
    href: {
      type: String,
      required: true,
    },
    active: {
      type: Boolean,
      default: false,
    },
  },
}
</script>
