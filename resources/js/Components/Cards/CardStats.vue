<template>
  <div class="relative flex flex-col min-w-0 break-words bg-white rounded-2xl border border-slate-200/80 shadow-sm hover:shadow-md transition-all duration-200 p-4 xl:p-5">
    <div class="flex items-start justify-between gap-3">
      <div class="flex-1 min-w-0">
        <h5 class="text-[11px] font-bold uppercase tracking-wider text-slate-500 mb-1">
          {{ statSubtitle }}
        </h5>
        <div class="flex items-baseline gap-2 flex-wrap">
          <span class="text-2xl font-black text-slate-900 tracking-tight">
            {{ statTitle }}
          </span>
          <!-- Summary KPI Comparison Badge -->
          <span
            v-if="statPercent"
            class="inline-flex items-center gap-0.5 px-2 py-0.5 rounded-full text-xs font-bold border"
            :class="statPercentBadgeClasses"
          >
            <span v-if="statArrow === 'up'">↑</span>
            <span v-else-if="statArrow === 'down'">↓</span>
            <span>{{ statPercent }}</span>
          </span>
        </div>
      </div>

      <div
        class="text-white p-3 text-center inline-flex items-center justify-center w-11 h-11 shadow-sm rounded-xl shrink-0"
        :class="[statIconColor]"
      >
        <i :class="[statIconName]" class="text-base"></i>
      </div>
    </div>

    <!-- Description & Progress Bar -->
    <div class="mt-3 pt-2.5 border-t border-slate-100 flex items-center justify-between text-xs text-slate-500">
      <span class="truncate">{{ statDescripiron }}</span>
    </div>
  </div>
</template>

<script>
export default {
  name: "card-stats",
  props: {
    statSubtitle: {
      type: String,
      default: "Traffic",
    },
    statTitle: {
      type: String,
      default: "0",
    },
    statArrow: {
      default: "",
    },
    statPercent: {
      type: String,
      default: "",
    },
    statPercentColor: {
      type: String,
      default: "text-emerald-500",
    },
    statDescripiron: {
      type: String,
      default: "",
    },
    statIconName: {
      type: String,
      default: "fas fa-chart-bar",
    },
    statIconColor: {
      type: String,
      default: "bg-blue-600",
    },
  },
  computed: {
    statPercentBadgeClasses() {
      if (this.statPercentColor.includes('emerald') || this.statPercentColor.includes('green')) {
        return 'bg-emerald-50 text-emerald-700 border-emerald-200'
      }
      if (this.statPercentColor.includes('orange') || this.statPercentColor.includes('amber')) {
        return 'bg-amber-50 text-amber-700 border-amber-200'
      }
      if (this.statPercentColor.includes('red') || this.statPercentColor.includes('rose')) {
        return 'bg-rose-50 text-rose-700 border-rose-200'
      }
      if (this.statPercentColor.includes('blue') || this.statPercentColor.includes('indigo')) {
        return 'bg-blue-50 text-blue-700 border-blue-200'
      }
      return 'bg-slate-100 text-slate-700 border-slate-200'
    }
  }
};
</script>
