<template>
  <div class="relative flex flex-col min-w-0 break-words bg-white w-full rounded-2xl border border-slate-200/80 shadow-sm hover:shadow-md transition-all duration-200 p-5">
    <!-- Header -->
    <div class="flex items-center justify-between pb-4 mb-4 border-b border-slate-100">
      <div class="flex items-center gap-2.5">
        <div class="w-9 h-9 rounded-xl bg-indigo-50 border border-indigo-100 flex items-center justify-center text-indigo-600 font-bold">
          <i class="fas fa-stopwatch text-sm"></i>
        </div>
        <div>
          <h3 class="font-bold text-base text-slate-800">SLA & Turnaround Time (TAT)</h3>
          <p class="text-xs text-slate-400">Resolution performance against standard 24h & 48h targets</p>
        </div>
      </div>
      <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-bold border"
        :class="slaStats.compliance_rate >= 90
          ? 'bg-emerald-50 text-emerald-700 border-emerald-200'
          : (slaStats.compliance_rate >= 75
            ? 'bg-amber-50 text-amber-700 border-amber-200'
            : 'bg-rose-50 text-rose-700 border-rose-200')"
      >
        <span class="w-1.5 h-1.5 rounded-full"
          :class="slaStats.compliance_rate >= 90 ? 'bg-emerald-500' : (slaStats.compliance_rate >= 75 ? 'bg-amber-500' : 'bg-rose-500')"
        ></span>
        {{ slaStats.compliance_rate >= 90 ? 'Target Met' : (slaStats.compliance_rate >= 75 ? 'Near Target' : 'Below Target') }}
      </span>
    </div>

    <!-- Metrics Grid -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-6 items-center">
      <!-- 1. Compliance Rate & Donut Ring -->
      <div class="flex items-center gap-4">
        <div class="relative w-20 h-20 shrink-0">
          <svg viewBox="0 0 80 80" class="w-full h-full transform -rotate-90">
            <circle
              cx="40"
              cy="40"
              r="34"
              stroke="#f1f5f9"
              stroke-width="7"
              fill="none"
            />
            <circle
              cx="40"
              cy="40"
              r="34"
              fill="none"
              stroke-linecap="round"
              :stroke="gaugeColor"
              stroke-width="7"
              :stroke-dasharray="`${circumference * (clampedRate / 100)} ${circumference}`"
              class="transition-all duration-700"
            />
          </svg>
          <div class="absolute inset-0 flex items-center justify-center text-xs font-black text-slate-800">
            {{ Math.round(slaStats.compliance_rate) }}%
          </div>
        </div>

        <div>
          <div class="text-xs font-bold uppercase tracking-wider text-slate-400">SLA Compliance</div>
          <div class="text-xl font-black text-slate-800 tracking-tight mt-0.5">
            {{ slaStats.compliance_rate }}%
          </div>
          <div class="text-xs text-slate-500 mt-0.5">
            <strong>{{ slaStats.resolved_within_sla }}</strong> of {{ slaStats.resolved_total }} resolved in &le; 24h
          </div>
        </div>
      </div>

      <!-- 2. Average Turnaround Time (TAT) & Response Time -->
      <div class="flex items-center gap-4 border-t md:border-t-0 md:border-l border-slate-100 pt-4 md:pt-0 md:pl-6">
        <div class="w-12 h-12 rounded-2xl bg-indigo-50 border border-indigo-100 flex items-center justify-center text-indigo-600 font-bold shrink-0">
          <i class="fas fa-bolt text-lg"></i>
        </div>
        <div class="flex-1 min-w-0">
          <div class="flex items-center justify-between gap-2">
            <div>
              <div class="text-xs font-bold uppercase tracking-wider text-slate-400">Avg Resolution TAT</div>
              <div class="text-xl font-black text-slate-800 tracking-tight mt-0.5">
                {{ slaStats.avg_tat_formatted || '—' }}
              </div>
            </div>
            <div class="pl-3 border-l border-slate-100">
              <div class="text-[10px] font-bold uppercase tracking-wider text-slate-400">Avg Response</div>
              <div class="text-sm font-black text-indigo-600 tracking-tight mt-0.5">
                {{ slaStats.avg_response_formatted || '—' }}
              </div>
            </div>
          </div>
          <div class="text-[11px] text-slate-500 mt-1 flex flex-wrap items-center gap-x-2 gap-y-0.5">
            <span><strong class="text-slate-600">TAT:</strong> Attended &rarr; Completed</span>
            <span class="text-slate-300">&bull;</span>
            <span><strong class="text-slate-600">Resp:</strong> Assigned &rarr; Attended</span>
          </div>
        </div>
      </div>

      <!-- 3. Active Workload Status (Interactive Drilldown Chips) -->
      <div class="border-t md:border-t-0 md:border-l border-slate-100 pt-4 md:pt-0 md:pl-6 space-y-2">
        <div class="flex items-center justify-between gap-2 mb-1.5">
          <span class="text-xs font-bold uppercase tracking-wider text-slate-400">
            Active Assigned Workload ({{ slaStats.active_total }} Total)
          </span>
          <span
            v-if="slaStats.awaiting_assignment > 0"
            class="text-[10px] font-semibold px-2 py-0.5 rounded-full bg-slate-100 text-slate-600 border border-slate-200"
            title="New unassigned tickets do not count against technician SLA until assigned"
          >
            {{ slaStats.awaiting_assignment }} Unassigned
          </span>
        </div>

        <div class="grid grid-cols-3 gap-2">
          <!-- On Track -->
          <Link
            :href="route('tickets-v2.index', getDrilldownParams({ status: 'In Progress' }))"
            class="p-2.5 rounded-xl bg-emerald-50/60 border border-emerald-100 text-center hover:bg-emerald-100 transition group cursor-pointer"
            title="View active on-track tickets"
          >
            <div class="text-emerald-700 font-black text-sm group-hover:scale-110 transition-transform">{{ slaStats.active_on_track }}</div>
            <div class="text-[10px] font-semibold text-emerald-600 uppercase">&lt; 24h On Track</div>
          </Link>

          <!-- Due Soon (Warning) -->
          <Link
            :href="route('tickets-v2.index', getDrilldownParams({ status: 'In Progress' }))"
            class="p-2.5 rounded-xl bg-amber-50/60 border border-amber-200 text-center hover:bg-amber-100 transition group cursor-pointer"
            title="View active tickets approaching SLA threshold"
          >
            <div class="text-amber-700 font-black text-sm group-hover:scale-110 transition-transform">
              {{ slaStats.active_warning }}
            </div>
            <div class="text-[10px] font-semibold text-amber-600 uppercase">24-48h Due Soon</div>
          </Link>

          <!-- Breached (Overdue) -->
          <Link
            :href="route('tickets-v2.index', getDrilldownParams({ preset: 'overdue' }))"
            class="p-2.5 rounded-xl bg-rose-50/60 border border-rose-200 text-center hover:bg-rose-100 transition group cursor-pointer"
            title="View overdue active tickets"
          >
            <div class="text-rose-700 font-black text-sm group-hover:scale-110 transition-transform">
              {{ slaStats.active_breached }}
            </div>
            <div class="text-[10px] font-semibold text-rose-600 uppercase">&gt; 48h Overdue</div>
          </Link>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { computed } from 'vue'
import { Link } from '@inertiajs/vue3'

const props = defineProps({
  slaStats: {
    type: Object,
    default: () => ({
      compliance_rate: 100,
      avg_tat_formatted: '—',
      avg_tat_hours: 0,
      avg_response_formatted: '—',
      avg_response_hours: 0,
      resolved_total: 0,
      resolved_within_sla: 0,
      resolved_overdue: 0,
      active_total: 0,
      active_on_track: 0,
      active_warning: 0,
      active_breached: 0,
      awaiting_assignment: 0
    })
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

const radius = 34
const circumference = 2 * Math.PI * radius

const clampedRate = computed(() => {
  const rate = Number(props.slaStats?.compliance_rate || 0)
  return Math.max(0, Math.min(100, rate))
})

const gaugeColor = computed(() => {
  const rate = clampedRate.value
  if (rate >= 90) return '#10b981' // emerald-500
  if (rate >= 75) return '#f59e0b' // amber-500
  return '#ef4444' // rose-500
})
</script>

