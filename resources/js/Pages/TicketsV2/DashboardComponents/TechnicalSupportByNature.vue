<!-- TechnicalSupportByNature.vue (Modernized with Gradients, Drilldowns & Empty State) -->
<template>
  <div class="relative flex flex-col min-w-0 break-words bg-white w-full rounded-2xl border border-slate-200/80 shadow-sm hover:shadow-md transition-all duration-200 p-5">
    <!-- Header -->
    <div class="flex items-center justify-between pb-4 mb-2 border-b border-slate-100">
      <div class="flex items-center gap-2.5">
        <div class="w-9 h-9 rounded-xl bg-violet-50 border border-violet-100 flex items-center justify-center text-violet-600 font-bold">
          <i class="fas fa-layer-group text-sm"></i>
        </div>
        <div>
          <h3 class="font-bold text-base text-slate-800">Support by Category / Nature</h3>
          <p class="text-xs text-slate-400">Click any category bar to filter matching tickets</p>
        </div>
      </div>
      <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-bold bg-slate-50 text-slate-600 border border-slate-200">
        <span class="w-1.5 h-1.5 rounded-full bg-violet-500"></span>
        {{ total }} Total Tickets
      </span>
    </div>

    <!-- Chart Body -->
    <div v-if="series.length" class="mt-2">
      <!-- scroll only when there are many categories -->
      <div :class="isScrollable ? 'w-full overflow-x-auto' : 'w-full'">
        <svg
          :viewBox="`0 0 ${vbWidth} ${vbHeight}`"
          class="h-72 w-full select-none"
          role="img"
          aria-label="Technical support by nature bar chart"
        >
          <defs>
            <linearGradient id="natureBarGrad" x1="0" y1="0" x2="0" y2="1">
              <stop offset="0%" stop-color="#8b5cf6" />
              <stop offset="100%" stop-color="#6366f1" />
            </linearGradient>
            <linearGradient id="natureBarGradHover" x1="0" y1="0" x2="0" y2="1">
              <stop offset="0%" stop-color="#a78bfa" />
              <stop offset="100%" stop-color="#8b5cf6" />
            </linearGradient>
          </defs>

          <!-- Axes -->
          <line :x1="chartLeft" :y1="chartBottom" :x2="chartRight" :y2="chartBottom" stroke="#e2e8f0" stroke-width="1.5" />
          <line :x1="chartLeft" :y1="chartTop"    :x2="chartLeft"  :y2="chartBottom" stroke="#e2e8f0" stroke-width="1.5" />

          <!-- Horizontal grid + y ticks -->
          <g v-for="t in yTicks" :key="t.value">
            <line :x1="chartLeft" :x2="chartRight" :y1="yScale(t.value)" :y2="yScale(t.value)" stroke="#f1f5f9" stroke-dasharray="3 3" />
            <text :x="chartLeft - 10" :y="yScale(t.value)" text-anchor="end" dominant-baseline="middle"
                  font-size="11" fill="#64748b" class="font-medium tabular-nums select-none">
              {{ t.label }}
            </text>
          </g>

          <!-- Bars + labels (Clickable Drilldowns) -->
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
              fill="url(#natureBarGrad)"
              rx="6"
              class="transition-all duration-200 hover:opacity-90 hover:brightness-110"
            >
              <title>{{ row.label }}: {{ row.value }} tickets (Click to filter)</title>
            </rect>

            <!-- Value above bar -->
            <text :x="xScale(i)" :y="yScale(row.value) - 8" text-anchor="middle" dominant-baseline="baseline"
                  font-size="11" fill="#1e293b" class="font-bold tabular-nums">
              {{ row.value }}
            </text>

            <!-- Bottom label (wrapped to multiple lines) -->
            <text :x="xScale(i)" :y="chartBottom + 14" text-anchor="middle"
                  font-size="11" fill="#475569" class="font-semibold select-none group-hover:fill-violet-600 transition-colors">
              <tspan
                v-for="(line, k) in wrap(row.label)"
                :key="k"
                :x="xScale(i)"
                :dy="k === 0 ? 0 : 13"
              >{{ line }}</tspan>
            </text>
          </g>
        </svg>
      </div>

      <!-- Category Pill List -->
      <div class="mt-3 pt-3 border-t border-slate-100 flex flex-wrap items-center gap-2">
        <button
          v-for="row in series"
          :key="row.label"
          type="button"
          @click="drilldown(row.label)"
          class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg text-xs font-medium bg-slate-50 hover:bg-violet-50 hover:text-violet-700 border border-slate-200/80 transition cursor-pointer"
          :title="`Click to filter tickets by ${row.label}`"
        >
          <span class="w-2 h-2 rounded-full bg-violet-500"></span>
          <span>{{ row.label }}</span>
          <span class="text-slate-400 font-bold">({{ row.value }})</span>
        </button>
      </div>
    </div>

    <!-- Empty State -->
    <div v-else class="py-12 text-center">
      <div class="w-12 h-12 rounded-2xl bg-slate-50 border border-slate-100 flex items-center justify-center text-slate-400 mx-auto mb-2">
        <i class="fas fa-layer-group text-lg"></i>
      </div>
      <p class="text-sm font-semibold text-slate-600">No categories recorded</p>
      <p class="text-xs text-slate-400 mt-0.5">Tickets categorized in this filter range will display here.</p>
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

const series = computed(() => {
  const rows = (props.items || []).map(r => ({
    label: String(r.nature ?? 'Unspecified'),
    value: Number(r.total ?? 0)
  }))
  rows.sort((a, b) => b.value - a.value)
  return rows
})

const total  = computed(() => series.value.reduce((s, r) => s + r.value, 0))
const maxVal = computed(() => Math.max(1, ...series.value.map(r => r.value)))

const baseVbWidth = 760
const leftPad   = 48
const rightPad  = 16
const topPad    = 16
const bottomPad = 52
const chartTop  = topPad
const chartBottomBase = 260
const chartBottom = chartTop + chartBottomBase
const vbHeight = topPad + bottomPad + chartBottomBase

const n = computed(() => Math.max(1, series.value.length))
const barGap = 18
const maxBarsNoScroll = 8
const targetBarWidth = 42

const isScrollable = computed(() => n.value > maxBarsNoScroll)
const vbWidth = computed(() => {
  if (!isScrollable.value) return baseVbWidth
  return leftPad + rightPad + n.value * targetBarWidth + (n.value - 1) * barGap
})

const chartLeft  = leftPad
const chartRight = computed(() => vbWidth.value - rightPad)
const chartWidth = computed(() => chartRight.value - chartLeft)

const barWidth = computed(() => {
  if (isScrollable.value) return targetBarWidth
  const inner = chartWidth.value - (n.value - 1) * barGap
  return Math.max(20, Math.min(60, inner / n.value))
})

function xScale(i) {
  const totalBarsWidth = n.value * barWidth.value + (n.value - 1) * barGap
  const startX = chartLeft + (chartWidth.value - totalBarsWidth) / 2
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

function wrap(label, maxLen = 14) {
  const trySplit = label.split(/[/\-]/).flatMap(s => s.split(' '))
  const lines = []
  let line = ''
  for (const word of trySplit) {
    if (!line.length) { line = word; continue }
    if ((line + ' ' + word).length <= maxLen) line += ' ' + word
    else { lines.push(line); line = word }
  }
  if (line) lines.push(line)
  return lines.flatMap(L => {
    if (L.length <= maxLen) return [L]
    const chunks = []
    for (let i = 0; i < L.length; i += maxLen) chunks.push(L.slice(i, i + maxLen))
    return chunks
  })
}

function drilldown(nature) {
  if (!nature) return
  const params = { search: nature }
  if (props.filters?.year) params.year = props.filters.year
  if (props.filters?.months) params.months = props.filters.months
  else if (props.filters?.month) params.month = props.filters.month
  if (props.filters?.assigned_staff) params.assigned_staff = props.filters.assigned_staff
  router.visit(route('tickets-v2.index', params))
}
</script>
