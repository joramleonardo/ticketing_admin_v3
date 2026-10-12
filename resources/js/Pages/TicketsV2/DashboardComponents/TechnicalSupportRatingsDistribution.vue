<template>
  <div class="relative flex flex-col min-w-0 break-words bg-white w-full rounded-2xl border border-slate-200/80 shadow-sm hover:shadow-md transition-all duration-200 p-5">
    <!-- Header -->
    <div class="flex items-center justify-between pb-4 mb-2 border-b border-slate-100">
      <div class="flex items-center gap-2.5">
        <div class="w-9 h-9 rounded-xl bg-sky-50 border border-sky-100 flex items-center justify-center text-sky-600 font-bold">
          <i class="fas fa-chart-column text-sm"></i>
        </div>
        <div>
          <h3 class="font-bold text-base text-slate-800">Ratings Distribution (1 to 5)</h3>
          <p class="text-xs text-slate-400">{{ total }} rated out of {{ totalAll }} completed</p>
        </div>
      </div>
      <Link
        :href="route('ratings.dashboard')"
        class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-semibold bg-slate-50 hover:bg-slate-100 text-slate-600 border border-slate-200 transition"
      >
        <span class="w-1.5 h-1.5 rounded-full bg-sky-500"></span>
        <span>CSM Details</span>
      </Link>
    </div>

    <!-- Chart -->
    <div class="mt-2">
      <div class="w-full" ref="wrapRef">
        <svg :viewBox="`0 0 ${vbWidth} ${vbHeight}`" class="w-full h-64 select-none"
          preserveAspectRatio="none" role="img" aria-label="Distribution of ratings 5..1"
          shape-rendering="crispEdges" text-rendering="optimizeLegibility"
        >
          <defs>
            <linearGradient id="r5Grad" x1="0" y1="0" x2="0" y2="1"><stop offset="0%" stop-color="#10b981" /><stop offset="100%" stop-color="#059669" /></linearGradient>
            <linearGradient id="r4Grad" x1="0" y1="0" x2="0" y2="1"><stop offset="0%" stop-color="#0ea5e9" /><stop offset="100%" stop-color="#0284c7" /></linearGradient>
            <linearGradient id="r3Grad" x1="0" y1="0" x2="0" y2="1"><stop offset="0%" stop-color="#64748b" /><stop offset="100%" stop-color="#475569" /></linearGradient>
            <linearGradient id="r2Grad" x1="0" y1="0" x2="0" y2="1"><stop offset="0%" stop-color="#f59e0b" /><stop offset="100%" stop-color="#d97706" /></linearGradient>
            <linearGradient id="r1Grad" x1="0" y1="0" x2="0" y2="1"><stop offset="0%" stop-color="#ef4444" /><stop offset="100%" stop-color="#dc2626" /></linearGradient>
          </defs>

          <!-- Axes -->
          <line :x1="r(chartLeft)" :y1="r(chartBottom)" :x2="r(chartRight)" :y2="r(chartBottom)" stroke="#e2e8f0" stroke-width="1.5" />
          <line :x1="r(chartLeft)" :y1="r(chartTop)"    :x2="r(chartLeft)"  :y2="r(chartBottom)" stroke="#e2e8f0" stroke-width="1.5" />

          <!-- Horizontal grid + y ticks -->
          <g v-for="t in yTicks" :key="t.value">
            <line :x1="r(chartLeft)" :x2="r(chartRight)" :y1="r(yScale(t.value))" :y2="r(yScale(t.value))" stroke="#f1f5f9" stroke-dasharray="3 3" />
            <text :x="r(chartLeft - 10)" :y="r(yScale(t.value))" text-anchor="end" dominant-baseline="middle"
                  font-size="11" fill="#64748b" class="font-medium tabular-nums select-none">{{ t.label }}</text>
          </g>

          <!-- Bars + labels (Clickable Drilldown) -->
          <g
            v-for="(row, i) in series"
            :key="row.label"
            class="cursor-pointer group"
            @click="drilldown(ratingNumbers[i])"
          >
            <rect
              :x="r(xScale(i) - barWidth/2)"
              :y="r(yScale(row.value))"
              :width="r(barWidth)"
              :height="Math.max(0, r(chartBottom - yScale(row.value)))"
              :fill="`url(#r${ratingNumbers[i]}Grad)`"
              rx="6"
              class="transition-all duration-200 hover:opacity-90 hover:brightness-110"
            >
              <title>{{ row.label }}: {{ row.value }} ratings (Click to view)</title>
            </rect>

            <!-- value above bar -->
            <text :x="r(xScale(i))" :y="r(yScale(row.value) - 8)" text-anchor="middle"
              font-size="11" fill="#1e293b" class="font-bold tabular-nums">
              {{ row.value }}
            </text>

            <!-- bottom numeric labels 5..1 -->
            <text :x="r(xScale(i))" :y="r(chartBottom + 14)" text-anchor="middle" dominant-baseline="hanging"
                  font-size="12" fill="#334155" class="font-black select-none group-hover:fill-sky-600 transition-colors">
              ★ {{ ratingNumbers[i] }}
            </text>
          </g>
        </svg>
      </div>

      <!-- Legend chips -->
      <div class="mt-2 grid grid-cols-2 sm:grid-cols-5 gap-2">
        <button
          v-for="l in LEGEND"
          :key="l.n"
          type="button"
          @click="drilldown(l.n)"
          :class="['flex items-center justify-between px-2.5 py-1.5 rounded-xl text-xs font-semibold border transition cursor-pointer hover:shadow-xs', l.bg, l.text, l.border]"
        >
          <div class="flex items-center gap-1.5 truncate">
            <span class="inline-block h-2 w-2 rounded-full" :style="{ backgroundColor: l.dot }"></span>
            <span class="truncate">{{ l.label }}</span>
          </div>
          <span class="tabular-nums font-black ml-1">★{{ l.n }}</span>
        </button>
      </div>

      <!-- Unrated Tickets Counter -->
      <div v-if="unrated > 0" class="mt-3 pt-3 border-t border-slate-100 flex items-center justify-between text-xs text-slate-500">
        <span class="flex items-center gap-1.5">
          <i class="fas fa-hourglass-half text-amber-500"></i>
          Awaiting client rating response:
        </span>
        <Link
          :href="route('ratings.pending')"
          class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full bg-slate-100 hover:bg-slate-200 text-slate-700 font-bold transition"
        >
          <span>{{ unrated }} Unrated Tickets</span>
          <i class="fas fa-arrow-right text-[9px]"></i>
        </Link>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted, onBeforeUnmount } from 'vue'
import { Link, router } from '@inertiajs/vue3'

const props = defineProps({
  items: { type: Array, default: () => [] },
  unratedTotal: { type: Number, default: 0 }
})

const ORDER = ['Excellent','Very Good','Good','Poor','Very Poor']
const DISPLAY = {
  Excellent:   '5 - Excellent',
  'Very Good': '4 - Very Good',
  Good:        '3 - Good',
  Poor:        '2 - Poor',
  'Very Poor': '1 - Very Poor',
}
const ratingNumbers = [5, 4, 3, 2, 1]
const unrated = computed(() => Number(props.unratedTotal || 0))
const totalAll = computed(() => unrated.value + total.value)

const series = computed(() => {
  const map = new Map((props.items || []).map(r => [String(r.rating || ''), Number(r.total || 0)]))
  return ORDER.map(key => ({ label: DISPLAY[key], value: map.get(key) ?? 0 }))
})

const total  = computed(() => series.value.reduce((s, r) => s + r.value, 0))
const maxVal = computed(() => Math.max(1, ...series.value.map(r => r.value)))

const WRAP_HEIGHT_PX = 256
const leftPad = 48, rightPad = 16, topPad = 16, bottomPad = 48
const chartTop = topPad
const chartBottomBase = 190
const chartBottom = chartTop + chartBottomBase
const vbHeight = topPad + chartBottomBase + bottomPad

const wrapRef = ref(null)
const wrapWidthPx = ref(760)
let ro

onMounted(() => {
  const el = wrapRef.value
  if (!el) return
  wrapWidthPx.value = el.getBoundingClientRect().width || 760
  ro = new ResizeObserver(entries => {
    for (const e of entries) wrapWidthPx.value = e.contentRect.width || wrapWidthPx.value
  })
  ro.observe(el)
})

onBeforeUnmount(() => {
  if (ro) ro.disconnect()
})

const vbWidth = computed(() => (wrapWidthPx.value / WRAP_HEIGHT_PX) * vbHeight)

const chartLeft  = leftPad
const chartRight = computed(() => vbWidth.value - rightPad)
const chartWidth = computed(() => chartRight.value - chartLeft)

const n = computed(() => Math.max(1, series.value.length))
const barGap = computed(() => {
  const g = chartWidth.value / (n.value * 6)
  return Math.max(12, Math.min(22, g))
})
const barWidth = computed(() => {
  const inner = chartWidth.value - (n.value - 1) * barGap.value
  return Math.max(32, inner / n.value)
})

function xScale(i) {
  const startX = chartLeft + barWidth.value / 2
  return startX + i * (barWidth.value + barGap.value)
}

function yScale(v) {
  const t = v / maxVal.value
  return chartBottom - t * (chartBottom - chartTop)
}

const yTicks = computed(() => {
  const steps = 4, out = []
  for (let i = 0; i <= steps; i++) {
    const val = Math.round((maxVal.value / steps) * i)
    out.push({ value: val, label: val.toString() })
  }
  return out
})

const r = v => Math.round(v)

const LEGEND = [
  { n: 5, label: 'Excellent',  dot: '#10b981', bg: 'bg-emerald-50/60', text: 'text-emerald-700', border: 'border-emerald-200' },
  { n: 4, label: 'Very Good',  dot: '#0ea5e9', bg: 'bg-sky-50/60',     text: 'text-sky-700',     border: 'border-sky-200' },
  { n: 3, label: 'Good',       dot: '#64748b', bg: 'bg-slate-50/60',   text: 'text-slate-700',   border: 'border-slate-200' },
  { n: 2, label: 'Poor',       dot: '#f59e0b', bg: 'bg-amber-50/60',   text: 'text-amber-700',   border: 'border-amber-200' },
  { n: 1, label: 'Very Poor',  dot: '#ef4444', bg: 'bg-rose-50/60',    text: 'text-rose-700',    border: 'border-rose-200' },
]

function drilldown() {
  router.visit(route('ratings.dashboard'))
}
</script>
