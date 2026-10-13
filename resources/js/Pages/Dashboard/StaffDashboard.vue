<script setup>
import TicketSlaBadge from '@/Components/TicketSlaBadge.vue'
import { Link, usePage } from '@inertiajs/vue3'
import { computed } from 'vue'

const props = defineProps({
  staffPersonal: {
    type: Object,
    default: () => ({
      stats: { pending: 0, in_progress: 0, completed: 0, total: 0 },
      urgent_tickets: [],
      avg_rating: 5.0,
      total_ratings: 0,
      recent_feedback: [],
    }),
  },
  filters: { type: Object, default: () => ({}) },
})

const page = usePage()
const currentUserName = computed(() => page.props?.auth?.user?.name || 'Support Specialist')

function formatDate(val) {
  if (!val) return 'N/A'
  try {
    const d = new Date(val)
    return d.toLocaleDateString('en-US', { month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit' })
  } catch {
    return val
  }
}
</script>

<template>
  <div class="space-y-6">
    <!-- Staff Personal Workspace Hero Banner -->
    <div class="rounded-2xl border border-blue-200/90 bg-gradient-to-r from-blue-900 via-indigo-950 to-slate-900 p-5 sm:p-6 text-white shadow-sm flex flex-col md:flex-row md:items-center justify-between gap-4">
      <div class="flex items-center gap-3.5">
        <div class="w-12 h-12 rounded-2xl bg-blue-500/20 border border-blue-400/30 flex items-center justify-center font-black text-blue-200 text-lg shadow-inner shrink-0">
          {{ currentUserName.charAt(0).toUpperCase() }}
        </div>
        <div>
          <div class="flex items-center gap-2">
            <h2 class="text-xl font-black tracking-tight">Welcome, {{ currentUserName }}!</h2>
            <span class="px-2 py-0.5 rounded-full text-[10px] font-bold uppercase tracking-wider bg-emerald-500/20 text-emerald-300 border border-emerald-400/30">
              Staff Workspace
            </span>
          </div>
          <p class="text-xs text-blue-200/80 mt-0.5">
            Your personal productivity dashboard: review assigned requests, monitor turnaround times, and track customer ratings.
          </p>
        </div>
      </div>

      <!-- Quick Jump Button to My Tickets -->
      <div class="flex items-center gap-2 shrink-0">
        <Link
          :href="route('admin.my-tickets')"
          class="inline-flex items-center gap-2 px-4 py-2.5 rounded-xl bg-blue-600 hover:bg-blue-500 text-white font-bold text-xs shadow-sm transition cursor-pointer"
        >
          <i class="fa-solid fa-ticket text-xs"></i>
          <span>Open My Tickets Workspace</span>
        </Link>
      </div>
    </div>

    <!-- Personal 4-Card KPI Stat Row -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
      <!-- 1. Active In Progress -->
      <div class="p-5 rounded-2xl bg-white border border-slate-200/90 shadow-2xs space-y-2">
        <div class="flex items-center justify-between text-xs text-slate-500 font-semibold">
          <span>Active In Progress</span>
          <span class="w-2 h-2 rounded-full bg-emerald-500 animate-pulse"></span>
        </div>
        <div class="text-3xl font-black text-emerald-600 tracking-tight">
          {{ staffPersonal?.stats?.in_progress ?? 0 }}
        </div>
        <p class="text-[11px] text-slate-400">
          Tickets currently being attended by you
        </p>
      </div>

      <!-- 2. Pending Tickets -->
      <div class="p-5 rounded-2xl bg-white border border-slate-200/90 shadow-2xs space-y-2">
        <div class="flex items-center justify-between text-xs text-slate-500 font-semibold">
          <span>Pending Tickets</span>
          <i class="fa-solid fa-hourglass-half text-amber-500 text-xs"></i>
        </div>
        <div class="text-3xl font-black text-amber-600 tracking-tight">
          {{ staffPersonal?.stats?.pending ?? 0 }}
        </div>
        <p class="text-[11px] text-slate-400">
          Assigned requests waiting to be attended
        </p>
      </div>

      <!-- 3. Resolved This Period -->
      <div class="p-5 rounded-2xl bg-white border border-slate-200/90 shadow-2xs space-y-2">
        <div class="flex items-center justify-between text-xs text-slate-500 font-semibold">
          <span>Resolved / Completed</span>
          <i class="fa-solid fa-circle-check text-purple-500 text-xs"></i>
        </div>
        <div class="text-3xl font-black text-purple-600 tracking-tight">
          {{ staffPersonal?.stats?.completed ?? 0 }}
        </div>
        <p class="text-[11px] text-slate-400">
          Successfully resolved in selected period
        </p>
      </div>

      <!-- 4. Personal CSAT Rating -->
      <div class="p-5 rounded-2xl bg-white border border-slate-200/90 shadow-2xs space-y-2">
        <div class="flex items-center justify-between text-xs text-slate-500 font-semibold">
          <span>My CSAT Average</span>
          <i class="fa-solid fa-star text-amber-400 text-xs"></i>
        </div>
        <div class="flex items-baseline gap-2">
          <span class="text-3xl font-black text-slate-800 tracking-tight">
            {{ staffPersonal?.avg_rating ?? '5.0' }}
          </span>
          <span class="text-xs font-bold text-amber-500">★★★★★</span>
        </div>
        <p class="text-[11px] text-slate-400">
          Based on {{ staffPersonal?.total_ratings ?? 0 }} client feedback ratings
        </p>
      </div>
    </div>

    <!-- Main Grid: Urgent Attention Queue + Personal Client Commendations -->
    <div class="grid grid-cols-1 gap-6 xl:grid-cols-12">
      <!-- Left Column (8 cols): Needs My Attention Queue -->
      <div class="xl:col-span-8 bg-white rounded-2xl border border-slate-200/90 p-5 shadow-2xs space-y-4">
        <div class="flex items-center justify-between pb-3 border-b border-slate-100">
          <div class="flex items-center gap-2.5">
            <div class="w-8 h-8 rounded-lg bg-blue-50 border border-blue-200 flex items-center justify-center text-blue-600">
              <i class="fa-solid fa-bell text-xs"></i>
            </div>
            <div>
              <h3 class="text-sm font-bold text-slate-900">Needs My Attention</h3>
              <p class="text-[11px] text-slate-500">Active tickets assigned to you sorted by date requested</p>
            </div>
          </div>
          <Link
            :href="route('admin.my-tickets')"
            class="text-xs font-semibold text-blue-600 hover:text-blue-700 hover:underline inline-flex items-center gap-1"
          >
            <span>Full Workspace</span>
            <i class="fa-solid fa-arrow-right text-[10px]"></i>
          </Link>
        </div>

        <div v-if="staffPersonal?.urgent_tickets?.length" class="space-y-3">
          <div
            v-for="ticket in staffPersonal.urgent_tickets"
            :key="ticket.client_referenceNo"
            class="p-3.5 rounded-xl border border-slate-100 bg-slate-50/50 hover:bg-blue-50/40 hover:border-blue-200 transition flex flex-col sm:flex-row sm:items-center justify-between gap-3 text-xs"
          >
            <div class="min-w-0 space-y-1">
              <div class="flex items-center gap-2 flex-wrap">
                <span class="font-bold text-blue-900 tracking-tight">{{ ticket.client_referenceNo }}</span>
                <span
                  class="px-2 py-0.5 rounded-md text-[10px] font-bold uppercase border shadow-2xs"
                  :class="{
                    'bg-blue-50 text-blue-700 border-blue-200': ticket.ticket_status === 'New',
                    'bg-amber-50 text-amber-700 border-amber-200': ticket.ticket_status === 'Pending',
                    'bg-emerald-50 text-emerald-700 border-emerald-200': ticket.ticket_status === 'In Progress'
                  }"
                >
                  {{ ticket.ticket_status }}
                </span>
                <TicketSlaBadge :ticket="ticket" compact />
              </div>
              <p class="font-semibold text-slate-800 truncate">
                {{ ticket.client_name }} <span class="text-slate-400 font-normal">({{ ticket.client_division_agency }})</span>
              </p>
              <p class="text-[11px] text-slate-500 line-clamp-1">
                {{ ticket.ticket_problem_that_needed_support || 'No description provided' }}
              </p>
              <p class="text-[10px] text-slate-400">
                Requested: {{ formatDate(ticket.date_requested || ticket.created_at) }}
              </p>
            </div>

            <div class="flex items-center gap-2 shrink-0 self-end sm:self-center">
              <Link
                :href="route('admin.my-tickets', { open_ref: ticket.client_referenceNo })"
                class="px-3 py-1.5 rounded-xl bg-blue-600 hover:bg-blue-700 text-white font-semibold text-xs shadow-2xs transition inline-flex items-center gap-1.5"
              >
                <span>Attend Ticket</span>
                <i class="fa-solid fa-arrow-up-right-from-square text-[10px]"></i>
              </Link>
            </div>
          </div>
        </div>
        <div v-else class="text-center py-12 text-slate-400 text-xs">
          <i class="fa-solid fa-circle-check text-emerald-500 text-3xl mb-2 block"></i>
          <p class="font-bold text-slate-700 text-sm">All caught up!</p>
          <p class="mt-0.5 text-slate-400">You currently have no pending tickets requiring immediate action.</p>
        </div>
      </div>

      <!-- Right Column (4 cols): Personal Client Ratings & Feedback -->
      <div class="xl:col-span-4 bg-white rounded-2xl border border-slate-200/90 p-5 shadow-2xs space-y-4">
        <div class="flex items-center justify-between pb-3 border-b border-slate-100">
          <div class="flex items-center gap-2.5">
            <div class="w-8 h-8 rounded-lg bg-amber-50 border border-amber-200 flex items-center justify-center text-amber-500">
              <i class="fa-solid fa-comment-dots text-xs"></i>
            </div>
            <div>
              <h3 class="text-sm font-bold text-slate-900">Recent Feedback</h3>
              <p class="text-[11px] text-slate-500">From clients you attended</p>
            </div>
          </div>
          <span class="text-xs font-bold text-amber-600">
            {{ staffPersonal?.avg_rating ?? '5.0' }} ★
          </span>
        </div>

        <div v-if="staffPersonal?.recent_feedback?.length" class="space-y-3">
          <div
            v-for="fb in staffPersonal.recent_feedback"
            :key="fb.client_referenceNo"
            class="p-3 rounded-xl border border-slate-100 bg-slate-50/60 text-xs space-y-1.5"
          >
            <div class="flex items-center justify-between text-[11px]">
              <span class="font-bold text-blue-900">{{ fb.client_referenceNo }}</span>
              <span class="text-slate-400">{{ fb.created_at }}</span>
            </div>
            <div class="flex items-center gap-1 text-amber-400 text-xs">
              <span v-for="i in fb.stars" :key="i">★</span>
              <span class="text-[11px] font-semibold text-slate-600 ml-1">{{ fb.rating_result }}</span>
            </div>
            <p v-if="fb.comment" class="text-[11px] text-slate-600 italic bg-white p-2 rounded-lg border border-slate-100">
              "{{ fb.comment }}"
            </p>
          </div>
        </div>
        <div v-else class="text-center py-8 text-slate-400 text-xs">
          <i class="fa-regular fa-star text-slate-300 text-2xl mb-1 block"></i>
          No feedback ratings yet for this period.
        </div>
      </div>
    </div>
  </div>
</template>

