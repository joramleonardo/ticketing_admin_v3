<script setup>
import TicketStats from "@/Pages/TicketsV2/DashboardComponents/TicketStats.vue"
import TicketSlaPerformance from "@/Pages/TicketsV2/DashboardComponents/TicketSlaPerformance.vue"
import TicketDivisionBar from "@/Pages/TicketsV2/DashboardComponents/TicketDivisionBar.vue"
import TechnicalSupportRatings from "@/Pages/TicketsV2/DashboardComponents/TechnicalSupportRatings.vue"
import TechnicalSupportByNature from "@/Pages/TicketsV2/DashboardComponents/TechnicalSupportByNature.vue"
import TechnicalSupportRatingsDistribution from "@/Pages/TicketsV2/DashboardComponents/TechnicalSupportRatingsDistribution.vue"
import StaffWorkloadCard from "@/Pages/TicketsV2/DashboardComponents/StaffWorkloadCard.vue"
import RecentFeedbackCard from "@/Pages/TicketsV2/DashboardComponents/RecentFeedbackCard.vue"
import TechnicalSupportBySupportType from "@/Pages/TicketsV2/DashboardComponents/TechnicalSupportBySupportType.vue"
import { ref } from 'vue'

const props = defineProps({
  ticketStats: { type: Object, default: () => ({}) },
  slaStats: { type: Object, default: () => ({}) },
  trends: { type: Object, default: () => ({}) },
  staffWorkload: { type: Array, default: () => [] },
  recentFeedback: { type: Array, default: () => [] },
  divisionCounts: { type: Array, default: () => [] },
  ratingCounts: { type: Array, default: () => [] },
  ratingUnratedTotal: { type: Number, default: 0 },
  natureCounts: { type: Array, default: () => [] },
  supportTypeCounts: { type: Array, default: () => [] },
  filters: { type: Object, default: () => ({}) },
})

const activeTab = ref('executive') // 'executive' | 'operations' | 'csm'
</script>

<template>
  <div class="space-y-6">
    <!-- Executive Role Banner -->
    <div class="rounded-2xl border border-purple-200/80 bg-gradient-to-r from-purple-900 via-indigo-950 to-slate-900 p-5 text-white shadow-sm flex flex-col md:flex-row md:items-center justify-between gap-4">
      <div class="flex items-center gap-3.5">
        <div class="w-11 h-11 rounded-xl bg-purple-500/20 border border-purple-400/30 flex items-center justify-center shrink-0">
          <i class="fa-solid fa-crown text-purple-300 text-lg"></i>
        </div>
        <div>
          <div class="flex items-center gap-2">
            <h2 class="text-lg font-black tracking-tight">Executive Directorate Dashboard</h2>
            <span class="px-2 py-0.5 rounded-full text-[10px] font-bold uppercase tracking-wider bg-purple-400/20 text-purple-200 border border-purple-400/30">
              Super Admin
            </span>
          </div>
          <p class="text-xs text-purple-200/80 mt-0.5">
            Macro-level agency performance, division demand distribution, SLA adherence, and client satisfaction trends.
          </p>
        </div>
      </div>

      <div class="flex items-center gap-3 text-xs shrink-0 flex-wrap">
        <div class="px-3.5 py-2 rounded-xl bg-white/10 border border-white/10">
          <span class="text-slate-300 block text-[10px] uppercase font-bold tracking-wider">SLA Compliance</span>
          <span class="text-base font-black text-emerald-400">{{ slaStats?.compliance_rate ?? 100 }}%</span>
        </div>
        <div class="px-3.5 py-2 rounded-xl bg-white/10 border border-white/10">
          <span class="text-slate-300 block text-[10px] uppercase font-bold tracking-wider">Avg Turnaround</span>
          <span class="text-base font-black text-blue-300">{{ slaStats?.avg_tat_formatted ?? '< 1 hr' }}</span>
        </div>
        <Link
          :href="route('admin.users.index')"
          class="px-3.5 py-2.5 rounded-xl bg-purple-600 hover:bg-purple-500 text-white font-bold text-xs uppercase tracking-wider transition shadow-sm inline-flex items-center gap-2 cursor-pointer"
          title="Manage system users and access roles"
        >
          <i class="fa-solid fa-users-gear text-xs"></i>
          <span>Users & Roles</span>
        </Link>
      </div>
    </div>

    <!-- Executive KPI Row: TicketStats -->
    <div>
      <TicketStats :stats="ticketStats" :trends="trends" :filters="filters" />
    </div>

    <!-- View Tabs: Executive Overview vs. Operations vs. CSAT -->
    <div class="flex items-center justify-between border-b border-slate-200/80 pb-3 print:hidden">
      <div class="flex items-center gap-2">
        <button
          type="button"
          @click="activeTab = 'executive'"
          class="inline-flex items-center gap-2 px-4 py-2 rounded-xl text-sm font-bold transition cursor-pointer"
          :class="activeTab === 'executive'
            ? 'bg-purple-700 text-white shadow-sm'
            : 'bg-white border border-slate-200/80 text-slate-600 hover:bg-slate-50'"
        >
          <i class="fa-solid fa-chart-pie text-xs"></i>
          <span>Division Demand & Strategy</span>
        </button>

        <button
          type="button"
          @click="activeTab = 'operations'"
          class="inline-flex items-center gap-2 px-4 py-2 rounded-xl text-sm font-bold transition cursor-pointer"
          :class="activeTab === 'operations'
            ? 'bg-purple-700 text-white shadow-sm'
            : 'bg-white border border-slate-200/80 text-slate-600 hover:bg-slate-50'"
        >
          <i class="fa-solid fa-chart-line text-xs"></i>
          <span>Operations & SLA</span>
        </button>

        <button
          type="button"
          @click="activeTab = 'csm'"
          class="inline-flex items-center gap-2 px-4 py-2 rounded-xl text-sm font-bold transition cursor-pointer"
          :class="activeTab === 'csm'
            ? 'bg-purple-700 text-white shadow-sm'
            : 'bg-white border border-slate-200/80 text-slate-600 hover:bg-slate-50'"
        >
          <i class="fa-solid fa-star text-xs text-amber-300"></i>
          <span>Client Satisfaction (CSAT)</span>
        </button>
      </div>

      <div class="text-xs text-slate-400 font-medium hidden md:block">
        Executive macro view • Excluded from ticket assignments
      </div>
    </div>

    <!-- Tab 1: Division Demand & Strategic Breakdown -->
    <div v-if="activeTab === 'executive'" class="space-y-6">
      <div class="grid grid-cols-1 gap-6 xl:grid-cols-12">
        <div class="xl:col-span-6">
          <TicketDivisionBar :items="divisionCounts" :filters="filters" />
        </div>
        <div class="xl:col-span-6">
          <StaffWorkloadCard :items="staffWorkload" :filters="filters" />
        </div>
      </div>

      <div class="grid grid-cols-1 gap-6 xl:grid-cols-12">
        <div class="xl:col-span-6">
          <TechnicalSupportByNature :items="natureCounts" :filters="filters" />
        </div>
        <div class="xl:col-span-6">
          <TechnicalSupportBySupportType :items="supportTypeCounts" :filters="filters" />
        </div>
      </div>
    </div>

    <!-- Tab 2: Operations & SLA -->
    <div v-if="activeTab === 'operations'" class="space-y-6">
      <TicketSlaPerformance :sla-stats="slaStats" :filters="filters" />

      <div class="grid grid-cols-1 gap-6 xl:grid-cols-12">
        <div class="xl:col-span-7">
          <StaffWorkloadCard :items="staffWorkload" :filters="filters" />
        </div>
        <div class="xl:col-span-5">
          <TicketDivisionBar :items="divisionCounts" :filters="filters" />
        </div>
      </div>
    </div>

    <!-- Tab 3: Client Satisfaction (CSAT) -->
    <div v-if="activeTab === 'csm'" class="space-y-6">
      <div class="grid grid-cols-1 gap-6 xl:grid-cols-12">
        <div class="xl:col-span-8">
          <TechnicalSupportRatings :items="ratingCounts" />
        </div>
        <div class="xl:col-span-4">
          <TechnicalSupportRatingsDistribution
            :items="ratingCounts"
            :unrated-total="ratingUnratedTotal"
          />
        </div>
      </div>

      <RecentFeedbackCard :items="recentFeedback" />
    </div>
  </div>
</template>

