<template>
  <div class="relative space-y-4">
    <!-- Summary Resolution & Health Strip -->
    <div class="bg-white rounded-2xl border border-slate-200/80 p-4 shadow-sm">
      <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
        <!-- Metric 1: Completion / Resolution Rate -->
        <div class="flex items-center gap-3">
          <div class="w-12 h-12 rounded-xl bg-emerald-50 border border-emerald-100 flex items-center justify-center text-emerald-600 font-black text-lg shrink-0 shadow-sm">
            {{ resolutionRate }}%
          </div>
          <div>
            <div class="flex items-center gap-2 flex-wrap">
              <span class="text-xs font-bold uppercase tracking-wider text-slate-500">Resolution Rate</span>
              <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-emerald-100 text-emerald-800">
                {{ fmt(resolvedCount) }} of {{ fmt(stats.total) }} Resolved
              </span>
              <!-- Trend Delta on Resolution -->
              <span
                v-if="trends?.resolved_diff_pct"
                class="px-2 py-0.5 rounded-full text-[10px] font-bold border inline-flex items-center gap-0.5"
                :class="trends.resolved_direction === 'up' ? 'bg-emerald-50 text-emerald-700 border-emerald-200' : (trends.resolved_direction === 'down' ? 'bg-rose-50 text-rose-700 border-rose-200' : 'bg-slate-100 text-slate-700 border-slate-200')"
                :title="`Resolved tickets ${trends.resolved_diff_pct} ${trends.period_label}`"
              >
                <span v-if="trends.resolved_direction === 'up'">↑</span>
                <span v-else-if="trends.resolved_direction === 'down'">↓</span>
                <span>{{ trends.resolved_diff_pct }} {{ trends.period_label }}</span>
              </span>
            </div>
            <p class="text-xs text-slate-500 mt-0.5">Tickets marked as Completed or Closed</p>
          </div>
        </div>

        <!-- Metric 2: Active Pipeline -->
        <div class="flex items-center gap-6">
          <Link
            :href="route('tickets-v2.index', getDrilldownParams({ status: 'In Progress' }))"
            class="flex items-center gap-2 group hover:opacity-80 transition cursor-pointer"
            title="View In Progress & Pending tickets"
          >
            <span class="w-2.5 h-2.5 rounded-full bg-amber-500 group-hover:scale-125 transition-transform"></span>
            <span class="text-xs text-slate-600 font-medium">
              <strong class="text-slate-900 font-bold group-hover:text-amber-600 transition-colors">{{ fmt(activePipelineCount) }}</strong> in Progress / Pending
            </span>
          </Link>

          <Link
            :href="route('tickets-v2.index', getDrilldownParams({ preset: 'unassigned' }))"
            class="flex items-center gap-2 group hover:opacity-80 transition cursor-pointer"
            title="View New tickets awaiting triage"
          >
            <span class="w-2.5 h-2.5 rounded-full bg-orange-500 group-hover:scale-125 transition-transform"></span>
            <span class="text-xs text-slate-600 font-medium">
              <strong class="text-slate-900 font-bold group-hover:text-orange-600 transition-colors">{{ fmt(stats.unassigned) }}</strong> Unassigned
            </span>
          </Link>
        </div>
      </div>

      <!-- Multi-segmented Progress Bar -->
      <div class="mt-3.5 pt-3 border-t border-slate-100">
        <div class="h-2 w-full rounded-full bg-slate-100 flex overflow-hidden">
          <div
            v-if="resolvedPct > 0"
            :style="{ width: resolvedPct + '%' }"
            class="bg-emerald-500 transition-all duration-500"
            :title="`Resolved: ${resolvedPct}%`"
          ></div>
          <div
            v-if="inProgressPct > 0"
            :style="{ width: inProgressPct + '%' }"
            class="bg-amber-500 transition-all duration-500"
            :title="`In Progress/Pending: ${inProgressPct}%`"
          ></div>
          <div
            v-if="unassignedPct > 0"
            :style="{ width: unassignedPct + '%' }"
            class="bg-orange-400 transition-all duration-500"
            :title="`New/Unassigned: ${unassignedPct}%`"
          ></div>
        </div>
      </div>
    </div>

    <!-- Metric Cards Grid (6 Interactive Click-Through Cards) -->
    <div class="grid grid-cols-2 md:grid-cols-3 xl:grid-cols-6 gap-3.5">
      <!-- 1. All Tickets -->
      <Link
        :href="route('tickets-v2.index', getDrilldownParams({ status: 'All' }))"
        class="block group transform hover:-translate-y-0.5 transition-all duration-150"
      >
        <card-stats
          statSubtitle="ALL TICKETS"
          :statTitle="fmt(stats.total)"
          :statPercent="trends?.total_diff_pct || '100%'"
          :statArrow="trends?.total_direction"
          :statPercentColor="trends?.total_direction === 'up' ? 'text-blue-500' : (trends?.total_direction === 'down' ? 'text-rose-500' : 'text-slate-500')"
          :statDescripiron="trends?.period_label || 'Click to view all'"
          statIconName="fa fa-list"
          statIconColor="bg-blue-600 group-hover:bg-blue-700"
          class="border-slate-200/80 group-hover:border-blue-400 group-hover:shadow-md cursor-pointer"
        />
      </Link>

      <!-- 2. New (Unassigned) -->
      <Link
        :href="route('tickets-v2.index', getDrilldownParams({ status: 'New' }))"
        class="block group transform hover:-translate-y-0.5 transition-all duration-150"
      >
        <card-stats
          statSubtitle="NEW"
          :statTitle="fmt(stats.unassigned)"
          :statPercent="percent(stats.unassigned)"
          statPercentColor="text-orange-500"
          statDescripiron="Needs assignment"
          statIconName="fa fa-bell"
          statIconColor="bg-orange-500 group-hover:bg-orange-600"
          class="border-slate-200/80 group-hover:border-orange-400 group-hover:shadow-md cursor-pointer"
        />
      </Link>

      <!-- 3. Pending -->
      <Link
        :href="route('tickets-v2.index', getDrilldownParams({ status: 'Pending' }))"
        class="block group transform hover:-translate-y-0.5 transition-all duration-150"
      >
        <card-stats
          statSubtitle="PENDING"
          :statTitle="fmt(stats.pending)"
          :statPercent="percent(stats.pending)"
          statPercentColor="text-red-500"
          statDescripiron="Assigned & pending"
          statIconName="fas fa-clock"
          statIconColor="bg-red-500 group-hover:bg-red-600"
          class="border-slate-200/80 group-hover:border-red-400 group-hover:shadow-md cursor-pointer"
        />
      </Link>

      <!-- 4. In Progress -->
      <Link
        :href="route('tickets-v2.index', getDrilldownParams({ status: 'In Progress' }))"
        class="block group transform hover:-translate-y-0.5 transition-all duration-150"
      >
        <card-stats
          statSubtitle="IN PROGRESS"
          :statTitle="fmt(stats.in_progress)"
          :statPercent="percent(stats.in_progress)"
          statPercentColor="text-amber-500"
          statDescripiron="Active support"
          statIconName="fas fa-spinner"
          statIconColor="bg-amber-500 group-hover:bg-amber-600"
          class="border-slate-200/80 group-hover:border-amber-400 group-hover:shadow-md cursor-pointer"
        />
      </Link>

      <!-- 5. Completed -->
      <Link
        :href="route('tickets-v2.index', getDrilldownParams({ status: 'Completed' }))"
        class="block group transform hover:-translate-y-0.5 transition-all duration-150"
      >
        <card-stats
          statSubtitle="COMPLETED"
          :statTitle="fmt(stats.completed)"
          :statPercent="trends?.resolved_diff_pct || percent(stats.completed)"
          :statArrow="trends?.resolved_direction || 'up'"
          :statPercentColor="trends?.resolved_direction === 'up' ? 'text-emerald-500' : (trends?.resolved_direction === 'down' ? 'text-rose-500' : 'text-emerald-500')"
          :statDescripiron="trends?.period_label ? (trends.period_label + ' resolved') : 'Awaiting rating/close'"
          statIconName="fas fa-check"
          statIconColor="bg-emerald-500 group-hover:bg-emerald-600"
          class="border-slate-200/80 group-hover:border-emerald-400 group-hover:shadow-md cursor-pointer"
        />
      </Link>

      <!-- 6. Closed -->
      <Link
        :href="route('tickets-v2.index', getDrilldownParams({ status: 'Closed' }))"
        class="block group transform hover:-translate-y-0.5 transition-all duration-150"
      >
        <card-stats
          statSubtitle="CLOSED"
          :statTitle="fmt(stats.closed)"
          :statPercent="percent(stats.closed)"
          statArrow="up"
          statPercentColor="text-emerald-500"
          statDescripiron="Archived & rated"
          statIconName="fas fa-archive"
          statIconColor="bg-slate-700 group-hover:bg-slate-800"
          class="border-slate-200/80 group-hover:border-slate-400 group-hover:shadow-md cursor-pointer"
        />
      </Link>
    </div>
  </div>
</template>

<script setup>
import { computed } from "vue"
import { Link } from "@inertiajs/vue3"
import CardStats from "@/Components/Cards/CardStats.vue"

const props = defineProps({
  stats: {
    type: Object,
    default: () => ({ unassigned: 0, pending: 0, in_progress: 0, completed: 0, closed: 0, total: 0 })
  },
  trends: {
    type: Object,
    default: () => ({})
  },
  filters: {
    type: Object,
    default: () => ({})
  }
})

function getDrilldownParams(extra = {}) {
  const params = { ...extra }
  if (props.filters?.year) params.year = props.filters.year
  if (props.filters?.months) params.months = props.filters.months
  else if (props.filters?.month) params.month = props.filters.month
  if (props.filters?.assigned_staff) params.assigned_staff = props.filters.assigned_staff
  return params
}

function fmt(val) {
  const n = Number(val || 0)
  return n.toLocaleString()
}

function percent(val) {
  const total = Number(props.stats.total || 0)
  if (!total) return '0%'
  return `${Math.round((Number(val || 0) / total) * 100)}%`
}

const resolvedCount = computed(() => {
  return Number(props.stats.completed || 0) + Number(props.stats.closed || 0)
})

const activePipelineCount = computed(() => {
  return Number(props.stats.pending || 0) + Number(props.stats.in_progress || 0)
})

const resolutionRate = computed(() => {
  const total = Number(props.stats.total || 0)
  if (!total) return 0
  return Math.round((resolvedCount.value / total) * 100)
})

const resolvedPct = computed(() => {
  const total = Number(props.stats.total || 0)
  return total ? Math.round((resolvedCount.value / total) * 100) : 0
})

const inProgressPct = computed(() => {
  const total = Number(props.stats.total || 0)
  return total ? Math.round((activePipelineCount.value / total) * 100) : 0
})

const unassignedPct = computed(() => {
  const total = Number(props.stats.total || 0)
  return total ? Math.round((Number(props.stats.unassigned || 0) / total) * 100) : 0
})
</script>
