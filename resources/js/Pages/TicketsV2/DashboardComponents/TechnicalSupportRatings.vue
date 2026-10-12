<template>
  <div class="relative flex flex-col min-w-0 break-words bg-white w-full rounded-2xl border border-slate-200/80 shadow-sm hover:shadow-md transition-all duration-200 p-5">
    <!-- Header -->
    <div class="flex items-center justify-between pb-4 mb-4 border-b border-slate-100">
      <div class="flex items-center gap-2.5">
        <div class="w-9 h-9 rounded-xl bg-amber-50 border border-amber-100 flex items-center justify-center text-amber-500 font-bold">
          <i class="fas fa-star text-sm"></i>
        </div>
        <div>
          <h3 class="font-bold text-base text-slate-800">Client Satisfaction Rate (CSAT)</h3>
          <p class="text-xs text-slate-400">Target: &ge; {{ TARGET }}% rated Very Satisfactory or higher</p>
        </div>
      </div>
      <Link
        :href="route('ratings.dashboard')"
        class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-bold bg-indigo-50 text-indigo-700 hover:bg-indigo-100 border border-indigo-200 transition"
      >
        <span>View Rating Dashboard</span>
        <i class="fas fa-arrow-right text-[10px]"></i>
      </Link>
    </div>

    <!-- Body -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-6 items-center">
      <!-- KPI + Status Badge -->
      <div class="flex items-center gap-4">
        <div
          aria-hidden="true"
          class="h-16 w-16 shrink-0 grid place-items-center rounded-2xl shadow-sm border"
          :class="metTarget ? 'bg-emerald-50 text-emerald-600 border-emerald-100' : 'bg-amber-50 text-amber-600 border-amber-100'"
        >
          <i :class="metTarget ? 'fas fa-thumbs-up' : 'fas fa-exclamation'" class="text-2xl"></i>
        </div>
        <div>
          <div class="text-xs font-bold uppercase tracking-wider text-slate-400">Satisfaction Score</div>
          <div class="flex items-baseline gap-2 mt-0.5">
            <span class="text-3xl font-black tracking-tight" :class="metTarget ? 'text-emerald-700' : 'text-amber-700'">
              {{ pctText }}
            </span>
            <span class="text-xs text-slate-500 font-medium">Very Satisfied+</span>
          </div>

          <!-- Status Chip -->
          <div class="mt-1.5">
            <span
              class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-xs font-bold border"
              :class="metTarget
                ? 'bg-emerald-50 text-emerald-700 border-emerald-200'
                : 'bg-amber-50 text-amber-700 border-amber-200'"
            >
              <span class="w-1.5 h-1.5 rounded-full" :class="metTarget ? 'bg-emerald-500' : 'bg-amber-500'"></span>
              {{ metTarget ? 'Target Achieved' : 'Below Target' }}
            </span>
          </div>
        </div>
      </div>

      <!-- Donut Gauge -->
      <div class="flex justify-center">
        <svg :viewBox="`0 0 160 160`" class="h-36 w-36">
          <circle cx="80" cy="80" r="64" stroke="#f1f5f9" stroke-width="12" fill="none"/>
          <circle
            cx="80" cy="80" r="64" fill="none" stroke-linecap="round"
            :stroke="gaugeColor"
            stroke-width="12"
            :stroke-dasharray="`${circumf * (satisfaction/100)} ${circumf}`"
            :transform="`rotate(-90 80 80)`"
            class="transition-all duration-700"
          />
          <line
            :x1="tickXY.x1" :y1="tickXY.y1" :x2="tickXY.x2" :y2="tickXY.y2"
            class="opacity-70"
            :stroke="metTarget ? '#10b981' : '#f59e0b'"
            stroke-width="3" stroke-linecap="round"
          />
          <text x="80" y="76" text-anchor="middle" class="font-black" fill="#1e293b" font-size="26">{{ pctText }}</text>
          <text x="80" y="96" text-anchor="middle" fill="#64748b" font-size="11" class="font-medium">target {{ TARGET }}%</text>
        </svg>
      </div>

      <!-- Breakdown Tiles (Interactive Drilldown Cards) -->
      <div class="grid gap-2.5">
        <Link
          :href="route('tickets-v2.index', { status: 'Closed' })"
          class="rounded-xl border border-emerald-100 bg-emerald-50/40 p-3.5 shadow-sm hover:bg-emerald-50 hover:border-emerald-200 transition group cursor-pointer"
          title="View rated tickets"
        >
          <div class="flex items-center justify-between">
            <div class="flex items-center gap-2 text-emerald-700 font-bold text-xs uppercase tracking-wide">
              <i class="fas fa-smile text-sm"></i>
              Satisfied (4–5)
            </div>
            <span class="text-xs text-emerald-600 font-semibold group-hover:translate-x-0.5 transition-transform">View &rarr;</span>
          </div>
          <div class="mt-1 text-2xl font-black text-emerald-800">{{ satisfied }}</div>
          <div class="text-[11px] text-emerald-600 mt-0.5">Rated Excellent or Very Good</div>
        </Link>

        <Link
          :href="route('tickets-v2.index', { status: 'Closed' })"
          class="rounded-xl border border-rose-100 bg-rose-50/40 p-3.5 shadow-sm hover:bg-rose-50 hover:border-rose-200 transition group cursor-pointer"
          title="View tickets needing follow-up"
        >
          <div class="flex items-center justify-between">
            <div class="flex items-center gap-2 text-rose-700 font-bold text-xs uppercase tracking-wide">
              <i class="fas fa-frown text-sm"></i>
              Needs Attention (1–3)
            </div>
            <span class="text-xs text-rose-600 font-semibold group-hover:translate-x-0.5 transition-transform">View &rarr;</span>
          </div>
          <div class="mt-1 text-2xl font-black text-rose-800">{{ notSatisfied }}</div>
          <div class="text-[11px] text-rose-600 mt-0.5">Rated Good, Poor, or Very Poor</div>
        </Link>
      </div>
    </div>

    <!-- Objective Footer Callout -->
    <div class="mt-4 pt-3 border-t border-slate-100 flex items-center justify-between text-xs text-slate-500">
      <div class="flex items-center gap-1.5">
        <i class="fas fa-bullseye text-indigo-500"></i>
        <span>Monthly Quality Objective: <strong>&ge; {{ TARGET }}%</strong> client satisfaction</span>
      </div>
      <span class="font-bold text-slate-700">{{ total }} Total Ratings Collected</span>
    </div>
  </div>
</template>

<script setup>
import { computed } from 'vue'
import { Link } from '@inertiajs/vue3'

const props = defineProps({
  items: { type: Array, default: () => [] }
})

const ORDER = ['Excellent','Very Good','Good','Poor','Very Poor']
const DISPLAY = {
  'Excellent': '5 - Excellent',
  'Very Good': '4 - Very Good',
  'Good':      '3 - Good',
  'Poor':      '2 - Poor',
  'Very Poor': '1 - Very Poor',
}
const series = computed(() => {
  const map = new Map((props.items || []).map(r => [String(r.rating || ''), Number(r.total || 0)]))
  return ORDER.map(key => ({ label: DISPLAY[key], rating: key, value: map.get(key) ?? 0 }))
})

const total = computed(() => series.value.reduce((s,r)=>s+r.value,0))

const satisfied = computed(() => {
  const m = new Map(series.value.map(r => [r.rating, r.value]))
  return (m.get('Excellent')||0) + (m.get('Very Good')||0)
})
const notSatisfied = computed(() => Math.max(0, total.value - satisfied.value))

const satisfaction = computed(() => total.value ? (satisfied.value/total.value)*100 : 0)
const pctText = computed(() => `${Math.round(satisfaction.value)}%`)

const TARGET = 90
const metTarget = computed(() => satisfaction.value >= TARGET)

const R = 64
const circumf = 2 * Math.PI * R
const gaugeColor = computed(() => metTarget.value ? '#10b981' : '#f59e0b')

const tickAngle = computed(() => (TARGET/100)*2*Math.PI - Math.PI/2)
const tickLen = 10
const tickXY = computed(() => {
  const x1 = 80 + (R+2) * Math.cos(tickAngle.value)
  const y1 = 80 + (R+2) * Math.sin(tickAngle.value)
  const x2 = 80 + (R+2+tickLen) * Math.cos(tickAngle.value)
  const y2 = 80 + (R+2+tickLen) * Math.sin(tickAngle.value)
  return { x1,y1,x2,y2 }
})
</script>
