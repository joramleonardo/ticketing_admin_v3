<script setup>
import { computed } from 'vue'
import { Link } from '@inertiajs/vue3'

const props = defineProps({
  items: {
    type: Array,
    default: () => [],
  },
  filters: {
    type: Object,
    default: () => ({}),
  },
})

function getStaffDrilldown(staffName, extra = {}) {
  const params = { assigned_staff: staffName, ...extra }
  if (props.filters?.year) params.year = props.filters.year
  if (props.filters?.months) params.months = props.filters.months
  else if (props.filters?.month) params.month = props.filters.month
  return route('tickets-v2.index', params)
}

function getInitials(name) {
  if (!name) return '?'
  const parts = name.trim().split(/\s+/)
  if (parts.length === 1) return parts[0].substring(0, 2).toUpperCase()
  return (parts[0][0] + parts[parts.length - 1][0]).toUpperCase()
}

function capacityBadgeClass(status) {
  switch (status) {
    case 'Available':
      return 'bg-emerald-50 text-emerald-700 border-emerald-200'
    case 'Normal':
      return 'bg-blue-50 text-blue-700 border-blue-200'
    case 'Busy':
      return 'bg-amber-50 text-amber-700 border-amber-200'
    case 'Overloaded':
      return 'bg-rose-50 text-rose-700 border-rose-200 animate-pulse'
    default:
      return 'bg-slate-100 text-slate-700 border-slate-200'
  }
}

function capacityBarColor(status) {
  switch (status) {
    case 'Available':
      return 'bg-emerald-500'
    case 'Normal':
      return 'bg-blue-600'
    case 'Busy':
      return 'bg-amber-500'
    case 'Overloaded':
      return 'bg-rose-600'
    default:
      return 'bg-slate-400'
  }
}

function capacityBarWidth(activeCount) {
  // Assume 8 active tickets is a 100% full capacity threshold
  const pct = Math.min(Math.round((activeCount / 8) * 100), 100)
  return Math.max(pct, activeCount > 0 ? 12 : 4)
}

const totalActiveWorkload = computed(() => {
  return props.items.reduce((acc, curr) => acc + Number(curr.active_count || 0), 0)
})

const totalResolvedWorkload = computed(() => {
  return props.items.reduce((acc, curr) => acc + Number(curr.resolved_count || 0), 0)
})
</script>

<template>
  <div class="relative flex flex-col min-w-0 break-words bg-white rounded-2xl border border-slate-200/90 shadow-sm p-5 transition-shadow hover:shadow-md">
    <!-- Card Header -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between pb-4 border-b border-slate-100 gap-2">
      <div class="flex items-center gap-2.5">
        <div class="p-2 rounded-xl bg-indigo-50 text-indigo-700 border border-indigo-100 shrink-0 shadow-xs">
          <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z" />
          </svg>
        </div>
        <div>
          <h2 class="text-base font-bold text-slate-800 tracking-tight flex items-center gap-2">
            Staff Workload & Capacity
            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-bold bg-slate-100 text-slate-700 border border-slate-200">
              {{ items.length }} Technicians
            </span>
          </h2>
          <p class="text-xs text-slate-400 font-medium">Real-time active ticket distribution and resolution output</p>
        </div>
      </div>

      <!-- Quick Summary Pills -->
      <div class="flex items-center gap-2 text-xs">
        <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg bg-amber-50 text-amber-800 border border-amber-200/80 font-bold">
          <span class="w-1.5 h-1.5 rounded-full bg-amber-500"></span>
          {{ totalActiveWorkload }} Active
        </span>
        <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg bg-emerald-50 text-emerald-800 border border-emerald-200/80 font-bold">
          <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span>
          {{ totalResolvedWorkload }} Resolved
        </span>
      </div>
    </div>

    <!-- Staff Workload List -->
    <div v-if="items && items.length > 0" class="mt-4 divide-y divide-slate-100">
      <div
        v-for="staff in items"
        :key="staff.name"
        class="py-3 first:pt-0 last:pb-0 group"
      >
        <div class="flex items-center justify-between gap-3 mb-2">
          <!-- Staff Info & 1-Click Drilldown -->
          <div class="flex items-center gap-3 min-w-0">
            <div class="w-9 h-9 rounded-xl bg-slate-100 text-slate-700 border border-slate-200 flex items-center justify-center font-bold text-xs shrink-0 group-hover:bg-indigo-50 group-hover:text-indigo-700 group-hover:border-indigo-200 transition-colors">
              {{ getInitials(staff.name) }}
            </div>
            <div class="min-w-0">
              <Link
                :href="getStaffDrilldown(staff.name)"
                class="text-sm font-bold text-slate-800 group-hover:text-indigo-600 transition-colors truncate block hover:underline"
                :title="`Click to view all tickets assigned to ${staff.name}`"
              >
                {{ staff.name }}
              </Link>
              <div class="text-[11px] text-slate-400 font-medium flex items-center gap-2 mt-0.5">
                <span>{{ staff.total_assigned }} assigned</span>
                <span>•</span>
                <span class="text-emerald-600 font-bold">{{ staff.resolved_count }} completed</span>
              </div>
            </div>
          </div>

          <!-- Capacity Badge & Active Count -->
          <div class="flex items-center gap-2 shrink-0">
            <span
              class="px-2.5 py-0.5 rounded-full text-xs font-bold border"
              :class="capacityBadgeClass(staff.capacity_status)"
            >
              {{ staff.capacity_status }}
            </span>
            <Link
              :href="getStaffDrilldown(staff.name, { status: 'In Progress' })"
              class="text-xs font-bold text-slate-700 bg-slate-50 hover:bg-slate-100 border border-slate-200 px-2 py-0.5 rounded-lg transition"
              :title="`View ${staff.active_count} active tickets for ${staff.name}`"
            >
              {{ staff.active_count }} active
            </Link>
          </div>
        </div>

        <!-- Capacity Load Indicator Bar -->
        <div class="w-full bg-slate-100 rounded-full h-1.5 overflow-hidden flex">
          <div
            :class="capacityBarColor(staff.capacity_status)"
            :style="{ width: capacityBarWidth(staff.active_count) + '%' }"
            class="h-full rounded-full transition-all duration-500"
            :title="`Load: ${staff.active_count} active tickets`"
          ></div>
        </div>
      </div>
    </div>

    <!-- Empty State -->
    <div
      v-else
      class="mt-4 flex flex-col items-center justify-center py-10 px-4 text-center border border-dashed border-slate-200 rounded-xl bg-slate-50/50"
    >
      <div class="w-12 h-12 rounded-full bg-slate-100 flex items-center justify-center text-slate-400 mb-2">
        <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z" />
        </svg>
      </div>
      <p class="text-sm font-bold text-slate-700">No Staff Workload Assigned</p>
      <p class="text-xs text-slate-400 mt-1 max-w-xs">
        No technicians have assigned tickets matching the selected timeframe.
      </p>
    </div>
  </div>
</template>

