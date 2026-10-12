<template>
  <div class="relative flex flex-col min-w-0 break-words bg-white w-full rounded-2xl border border-slate-200/80 shadow-sm hover:shadow-md transition-all duration-200 p-5">
    <!-- Header -->
    <div class="flex items-center justify-between pb-4 mb-2 border-b border-slate-100">
      <div class="flex items-center gap-2.5">
        <div class="w-9 h-9 rounded-xl bg-indigo-50 border border-indigo-100 flex items-center justify-center text-indigo-600 font-bold">
          <i class="fas fa-building text-sm"></i>
        </div>
        <div>
          <h3 class="font-bold text-base text-slate-800">Support per Division / Agency</h3>
          <p class="text-xs text-slate-400">Click any division bar to view corresponding tickets</p>
        </div>
      </div>
      <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-bold bg-slate-50 text-slate-600 border border-slate-200">
        <span class="w-1.5 h-1.5 rounded-full bg-indigo-500"></span>
        {{ total }} Total Tickets
      </span>
    </div>

    <!-- Chart Body -->
    <div v-if="series.length" class="mt-2">
      <!-- Vertical bar chart (SVG) -->
      <svg :viewBox="`0 0 ${vbWidth} ${vbHeight}`" class="w-full h-72 select-none" role="img" aria-label="Tickets by Division bar chart">
        <defs>
          <linearGradient id="divBarGrad" x1="0" y1="0" x2="0" y2="1">
            <stop offset="0%" stop-color="#6366f1" />
            <stop offset="100%" stop-color="#4f46e5" />
          </linearGradient>
          <linearGradient id="divBarGradHover" x1="0" y1="0" x2="0" y2="1">
            <stop offset="0%" stop-color="#818cf8" />
            <stop offset="100%" stop-color="#6366f1" />
          </linearGradient>
        </defs>

        <!-- Axes -->
        <line :x1="chartLeft" :y1="chartBottom" :x2="chartRight" :y2="chartBottom" stroke="#e2e8f0" stroke-width="1.5" />
        <line :x1="chartLeft" :y1="chartTop" :x2="chartLeft" :y2="chartBottom" stroke="#e2e8f0" stroke-width="1.5" />

        <!-- Horizontal grid + y ticks -->
        <g v-for="t in yTicks" :key="t.value">
          <line
            :x1="chartLeft" :x2="chartRight"
            :y1="yScale(t.value)" :y2="yScale(t.value)"
            stroke="#f1f5f9"
            stroke-dasharray="3 3"
          />
          <text
            :x="chartLeft - 10"
            :y="yScale(t.value)"
            text-anchor="end"
            dominant-baseline="middle"
            font-size="11"
            fill="#64748b"
            class="font-medium tabular-nums select-none"
          >
            {{ t.label }}
          </text>
        </g>

        <!-- Bars (Interactive Click Targets) -->
        <g
          v-for="(row, i) in series"
          :key="row.label"
          class="cursor-pointer group"
          @click="drilldown(row.label)"
        >
          <rect
            :x="xScale(i) - barWidth/2"
            :y="yScale(row.value)"
            :width="barWidth"
            :height="Math.max(0, chartBottom - yScale(row.value))"
            fill="url(#divBarGrad)"
            rx="6"
            class="transition-all duration-200 hover:opacity-90 hover:brightness-110"
          >
            <title>{{ row.label }}: {{ row.value }} tickets (Click to filter tickets)</title>
          </rect>

          <!-- Value label above each bar -->
          <text
            :x="xScale(i)"
            :y="yScale(row.value) - 8"
            text-anchor="middle"
            dominant-baseline="baseline"
            font-size="11"
            fill="#1e293b"
            class="font-bold tabular-nums"
          >
            {{ row.value }}
          </text>

          <!-- Category label on bottom -->
          <text
            :x="xScale(i)"
            :y="chartBottom + 16"
            text-anchor="middle"
            dominant-baseline="hanging"
            font-size="11"
            fill="#475569"
            class="font-semibold select-none group-hover:fill-indigo-600 transition-colors"
            :transform="labelTransform(i)"
          >
            {{ row.label || '—' }}
          </text>
        </g>
      </svg>

      <!-- Division Legend Pill Bar -->
      <div class="mt-3 pt-3 border-t border-slate-100 flex flex-wrap items-center gap-2">
        <button
          v-for="row in series"
          :key="row.label"
          type="button"
          @click="drilldown(row.label)"
          class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg text-xs font-medium bg-slate-50 hover:bg-indigo-50 hover:text-indigo-600 border border-slate-200/80 transition cursor-pointer"
          :title="`Click to filter tickets by ${row.label}`"
        >
          <span class="w-2 h-2 rounded-full bg-indigo-500"></span>
          <span>{{ row.label }}</span>
          <span class="text-slate-400 font-bold">({{ row.value }})</span>
        </button>
      </div>
    </div>

    <!-- Empty State -->
    <div v-else class="py-12 text-center">
      <div class="w-12 h-12 rounded-2xl bg-slate-50 border border-slate-100 flex items-center justify-center text-slate-400 mx-auto mb-2">
        <i class="fas fa-inbox text-lg"></i>
      </div>
      <p class="text-sm font-semibold text-slate-600">No division data recorded</p>
      <p class="text-xs text-slate-400 mt-0.5">Tickets created in this filter range will display division volume here.</p>
    </div>
  </div>
</template>

<script setup>
import { computed } from 'vue'
import { router } from '@inertiajs/vue3'

const props = defineProps({
  items: { type: Array, default: () => [] },
  filters: { type: Object, default: () => ({}) },
})

const series = computed(() =>
  [...props.items]
    .map(r => ({ label: r.division ?? '', value: Number(r.total || 0) }))
    .sort((a, b) => b.value - a.value)
)

const total = computed(() => series.value.reduce((s, r) => s + r.value, 0))
const maxVal = computed(() => Math.max(1, ...series.value.map(r => r.value)))

const vbWidth = 760
const leftPad = 48
const rightPad = 16
const topPad = 16
const bottomPad = 52
const chartLeft = leftPad
const chartRight = vbWidth - rightPad
const chartTop = topPad
const chartBottomBase = 260
const vbHeight = topPad + bottomPad + chartBottomBase
const chartBottom = chartTop + chartBottomBase
const chartWidth = chartRight - chartLeft

const n = computed(() => Math.max(1, series.value.length))
const barGap = 18
const barWidth = computed(() => {
  const inner = chartWidth - (n.value - 1) * barGap
  return Math.max(16, Math.min(64, inner / n.value))
})

function xScale(i) {
  const totalBarsWidth = n.value * barWidth.value + (n.value - 1) * barGap
  const startX = chartLeft + (chartWidth - totalBarsWidth) / 2
  return startX + i * (barWidth.value + barGap) + barWidth.value / 2
}

function yScale(v) {
  const t = (v / maxVal.value)
  return chartBottom - t * (chartBottom - chartTop)
}

const yTicks = computed(() => {
  const steps = 4
  const out = []
  for (let i = 0; i <= steps; i++) {
    const val = Math.round((maxVal.value / steps) * i)
    out.push({ value: val, label: val.toString() })
  }
  return out
})

function labelTransform(i) {
  if (n.value > 6) {
    const x = xScale(i)
    const y = chartBottom + 16
    return `rotate(-30 ${x} ${y})`
  }
  return undefined
}

function drilldown(division) {
  if (!division) return
  const params = { division }
  if (props.filters?.year) params.year = props.filters.year
  if (props.filters?.months) params.months = props.filters.months
  else if (props.filters?.month) params.month = props.filters.month
  if (props.filters?.assigned_staff) params.assigned_staff = props.filters.assigned_staff
  router.visit(route('tickets-v2.index', params))
}
</script>
