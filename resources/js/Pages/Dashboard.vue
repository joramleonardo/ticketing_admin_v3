<script setup>
    import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
    import TicketStats from "@/Pages/TicketsV2/DashboardComponents/TicketStats.vue"
    import TicketSlaPerformance from "@/Pages/TicketsV2/DashboardComponents/TicketSlaPerformance.vue"
    import TicketDivisionBar from "@/Pages/TicketsV2/DashboardComponents/TicketDivisionBar.vue"
    import TechnicalSupportRatings from "@/Pages/TicketsV2/DashboardComponents/TechnicalSupportRatings.vue"
    import TechnicalSupportByNature from "@/Pages/TicketsV2/DashboardComponents/TechnicalSupportByNature.vue"
    import TechnicalSupportRatingsDistribution from "@/Pages/TicketsV2/DashboardComponents/TechnicalSupportRatingsDistribution.vue"
    import StaffWorkloadCard from "@/Pages/TicketsV2/DashboardComponents/StaffWorkloadCard.vue"
    import RecentFeedbackCard from "@/Pages/TicketsV2/DashboardComponents/RecentFeedbackCard.vue"
    import TechnicalSupportBySupportType from "@/Pages/TicketsV2/DashboardComponents/TechnicalSupportBySupportType.vue"

    import { Head, router } from '@inertiajs/vue3'
    import { ref, computed, onMounted, onBeforeUnmount } from 'vue'

    const props = defineProps({
        ticketStats: { type: Object, default: () => ({}) },
        slaStats: { type: Object, default: () => ({}) },
        trends: { type: Object, default: () => ({}) },
        staffWorkload: { type: Array, default: () => [] },
        recentFeedback: { type: Array, default: () => [] },
        divisionCounts: { type: Array, default: () => [] },
        ratingCounts:    { type: Array,  default: () => [] },
        ratingUnratedTotal: { type: Number, default: 0 },
        natureCounts:  { type: Array,  default: () => [] },
        supportTypeCounts: { type: Array, default: () => [] },
        assignedStaffOptions: { type: Array, default: () => [] },
        filters: { type: Object, default: () => ({}) },
    })

    const now = new Date()

    // --- Tab Navigation ---
    const activeTab = ref('operations') // 'operations' | 'csm' | 'all'

    // --- Live Monitoring & Auto-Refresh ---
    const isAutoRefresh = ref(false)
    const isRefreshing = ref(false)
    const lastUpdated = ref(now.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit', second: '2-digit' }))
    let autoRefreshTimer = null

    function manualRefresh() {
        isRefreshing.value = true
        router.reload({
            only: ['ticketStats', 'slaStats', 'trends', 'staffWorkload', 'recentFeedback', 'divisionCounts', 'ratingCounts', 'ratingUnratedTotal', 'natureCounts', 'supportTypeCounts'],
            preserveScroll: true,
            preserveState: true,
            onFinish: () => {
                isRefreshing.value = false
                lastUpdated.value = new Date().toLocaleTimeString([], { hour: '2-digit', minute: '2-digit', second: '2-digit' })
            }
        })
    }

    function toggleAutoRefresh() {
        isAutoRefresh.value = !isAutoRefresh.value
        if (isAutoRefresh.value) {
            autoRefreshTimer = setInterval(() => {
                manualRefresh()
            }, 30000) // 30-second polling
        } else if (autoRefreshTimer) {
            clearInterval(autoRefreshTimer)
            autoRefreshTimer = null
        }
    }

    function printDashboard() {
        window.print()
    }

    // year list
    const startYear = 2023
    const years = computed(() => {
        const yNow = now.getFullYear()
        return Array.from({ length: (yNow - startYear + 1) }, (_, i) => String(yNow - i))
    })

    const TMS_URL = 'https://docs.google.com/spreadsheets/d/1dQ5nvz3Ae57sZP4_efNq9g4FsHaESCdU0Gcb0h8Xquw/edit?gid=0#gid=0'

    function openTms () {
        window.open(TMS_URL, '_blank', 'noopener')
    }

    // --- Month picker state ---
    const showMonths = ref(false)

    const monthNames = [
        'January','February','March','April','May','June',
        'July','August','September','October','November','December'
    ]
    const monthsList = monthNames.map((m, i) => ({ label: m, value: String(i + 1) }))

    const selYear  = ref(String(props.filters?.year  ?? now.getFullYear()))
    const initialMonths = (props.filters?.months && String(props.filters.months).split(',').filter(Boolean)) ||
                            [ String(props.filters?.month ?? (now.getMonth() + 1)) ]
    const selMonths = ref(initialMonths)
    const selAssignedStaff = ref(String(props.filters?.assigned_staff ?? ''))

    const monthsLabel = computed(() => {
        if (!selMonths.value.length) return 'No months'
        if (selMonths.value.length === 12) return 'All months'
        if (selMonths.value.length === 1) {
            const m = Number(selMonths.value[0]) - 1
            return monthNames[m]
        }
        return `${selMonths.value.length} months`
    })

    const assignedStaffLabel = computed(() => selAssignedStaff.value || 'All staff')

    const filterSummary = computed(() => {
        const year = selYear.value
        return `${monthsLabel.value} ${year} · ${assignedStaffLabel.value}`
    })

    const exportUrl = computed(() => route('dashboard.export', {
        year: selYear.value,
        months: selMonths.value.join(','),
        assigned_staff: selAssignedStaff.value || undefined,
    }))

    const currentFilters = computed(() => ({
        year: selYear.value,
        months: selMonths.value.join(','),
        assigned_staff: selAssignedStaff.value || undefined,
    }))

    function toggleMonth(v) {
        const s = new Set(selMonths.value)
        if (s.has(v)) s.delete(v); else s.add(v)
        selMonths.value = Array.from(s).sort((a,b) => Number(a)-Number(b))
        applyFilters()
    }

    function setQuarter(q) {
        const map = { Q1:['1','2','3'], Q2:['4','5','6'], Q3:['7','8','9'], Q4:['10','11','12'] }
        selMonths.value = map[q]
        showMonths.value = false
        applyFilters()
    }

    function thisMonth() {
        selMonths.value = [ String(new Date().getMonth() + 1) ]
        showMonths.value = false
        applyFilters()
    }

    function selectAll()  {
        selMonths.value = monthsList.map(m => m.value)
        applyFilters()
    }

    function clearMonths(){
        selMonths.value = []
        applyFilters()
    }

    function applyMonthsAndClose() {
        showMonths.value = false; applyFilters()
    }

    function applyFilters () {
        router.get(
            route('dashboard'),
            {
                year: selYear.value,
                months: selMonths.value.join(','),
                assigned_staff: selAssignedStaff.value || undefined,
            },
            { preserveState: true, preserveScroll: true, replace: true }
        )
    }

    function resetFilters() {
        selYear.value = String(now.getFullYear())
        selMonths.value = [ String(now.getMonth() + 1) ]
        selAssignedStaff.value = ''
        applyFilters()
    }

    const monthsWrapRef = ref(null)

    function onDocClick(e) {
        if (!showMonths.value) return
        const el = monthsWrapRef.value
        if (el && !el.contains(e.target)) {
            showMonths.value = false
        }
    }

    onMounted(() => document.addEventListener('click', onDocClick))
    onBeforeUnmount(() => {
        document.removeEventListener('click', onDocClick)
        if (autoRefreshTimer) {
            clearInterval(autoRefreshTimer)
        }
    })
</script>

<template>
    <Head title="Dashboard" />

    <AuthenticatedLayout>
        <div class="space-y-6">
            <!-- Top Controls Card -->
            <div class="relative z-40 flex flex-col gap-4 rounded-2xl border border-slate-200/90 bg-white p-5 shadow-sm print:hidden">
                <div class="flex flex-col gap-3 border-b border-slate-100 pb-4 lg:flex-row lg:items-center lg:justify-between">
                    <div>
                        <div class="flex items-center gap-2.5">
                            <h1 class="text-xl font-black text-slate-800 tracking-tight">Ticket Analytics & KPIs</h1>
                            <!-- Live status indicator -->
                            <button
                                type="button"
                                @click="toggleAutoRefresh"
                                class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-xs font-bold border transition cursor-pointer"
                                :class="isAutoRefresh
                                    ? 'bg-emerald-50 text-emerald-700 border-emerald-200'
                                    : 'bg-slate-50 text-slate-600 border-slate-200 hover:bg-slate-100'"
                                :title="isAutoRefresh ? 'Click to turn off 30s auto-refresh' : 'Click to enable 30s auto-refresh'"
                            >
                                <span
                                    class="w-2 h-2 rounded-full"
                                    :class="isAutoRefresh ? 'bg-emerald-500 animate-pulse' : 'bg-slate-400'"
                                ></span>
                                <span>{{ isAutoRefresh ? 'Live (30s)' : 'Live Off' }}</span>
                            </button>

                            <span class="text-xs text-slate-400 hidden sm:inline">
                                Updated {{ lastUpdated }}
                            </span>
                        </div>
                        <p class="mt-1 text-sm text-slate-500 font-medium">
                            {{ filterSummary }}
                        </p>
                    </div>

                    <!-- Header Action Buttons -->
                    <div class="relative z-50 flex flex-wrap items-center gap-2">
                        <!-- Manual Refresh Button -->
                        <button
                            type="button"
                            @click="manualRefresh"
                            :disabled="isRefreshing"
                            class="inline-flex items-center justify-center gap-1.5 rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50 shadow-sm transition disabled:opacity-50"
                            title="Refresh dashboard metrics"
                        >
                            <svg
                                class="h-4 w-4 text-slate-500"
                                :class="{ 'animate-spin text-indigo-600': isRefreshing }"
                                fill="none"
                                viewBox="0 0 24 24"
                                stroke="currentColor"
                            >
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                            </svg>
                            <span class="hidden sm:inline">Refresh</span>
                        </button>

                        <!-- Print / PDF Button -->
                        <!-- <button
                            type="button"
                            @click="printDashboard"
                            class="inline-flex items-center justify-center gap-1.5 rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50 shadow-sm transition"
                            title="Print executive dashboard summary"
                        >
                            <i class="fas fa-print text-slate-400 text-xs"></i>
                            <span class="hidden sm:inline">Print</span>
                        </button> -->

                        <!-- TMS Summary External Link -->
                        <button
                            type="button"
                            @click="openTms"
                            aria-label="Open TMS Summary Report"
                            class="inline-flex items-center justify-center gap-2 rounded-xl px-3.5 py-2 text-sm font-semibold bg-indigo-50 text-indigo-700 border border-indigo-200 hover:bg-indigo-100 transition shadow-sm"
                        >
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" class="h-4 w-4" fill="currentColor" aria-hidden="true">
                                <rect x="3" y="11" width="3" height="8" rx="1"></rect>
                                <rect x="10.5" y="7" width="3" height="12" rx="1"></rect>
                                <rect x="18" y="3" width="3" height="16" rx="1"></rect>
                            </svg>
                            TMS Summary
                        </button>

                        <!-- Export CSV Button -->
                        <a
                            :href="exportUrl"
                            class="relative z-50 inline-flex items-center justify-center gap-2 rounded-xl bg-emerald-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-emerald-700 transition"
                        >
                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                            </svg>
                            Export CSV
                        </a>
                    </div>
                </div>

                <!-- Sleek Filter Toolbar -->
                <div class="flex flex-wrap items-center gap-3">
                    <!-- Month & Year Popover Trigger -->
                    <div class="relative" ref="monthsWrapRef">
                        <button
                            type="button"
                            @click="showMonths = !showMonths"
                            class="inline-flex items-center gap-2 rounded-xl border border-slate-200 bg-slate-50/70 hover:bg-slate-100/80 px-3.5 py-2 text-sm font-semibold text-slate-700 transition shadow-sm focus:outline-none focus:ring-2 focus:ring-indigo-300"
                            aria-haspopup="dialog"
                            :aria-expanded="showMonths ? 'true' : 'false'"
                        >
                            <svg class="h-4 w-4 text-slate-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                            </svg>
                            <span>{{ monthsLabel }} {{ selYear }}</span>
                            <svg class="h-4 w-4 text-slate-400" viewBox="0 0 20 20" fill="currentColor">
                                <path fill-rule="evenodd" d="M5.23 7.21a.75.75 0 011.06.02L10 10.585l3.71-3.354a.75.75 0 111.02 1.096l-4.22 3.814a.75.75 0 01-1.02 0L5.25 8.326a.75.75 0 01-.02-1.115z" clip-rule="evenodd"/>
                            </svg>
                        </button>

                        <!-- Month & Year Popover Dropdown -->
                        <div
                            v-if="showMonths"
                            class="absolute left-0 z-50 mt-2 w-[min(28rem,calc(100vw-2rem))] rounded-2xl border border-slate-200 bg-white p-4 shadow-xl ring-1 ring-slate-900/5 animate-in fade-in zoom-in-95 duration-100"
                        >
                            <!-- Popover Header: Year selector & presets -->
                            <div class="flex items-center justify-between gap-3 pb-3 border-b border-slate-100 mb-3">
                                <div class="flex items-center gap-2">
                                    <span class="text-xs font-bold uppercase tracking-wider text-slate-400">Year</span>
                                    <select
                                        v-model="selYear"
                                        @change="applyFilters"
                                        class="rounded-lg border border-slate-200 bg-slate-50 py-1 px-2.5 text-xs font-bold text-slate-700 focus:outline-none focus:ring-2 focus:ring-indigo-300"
                                    >
                                        <option v-for="y in years" :key="y" :value="y">{{ y }}</option>
                                    </select>
                                </div>

                                <div class="flex items-center gap-1.5">
                                    <button
                                        type="button"
                                        class="rounded-md border border-slate-200 px-2 py-0.5 text-xs font-medium text-slate-600 hover:bg-slate-50 transition"
                                        @click="selectAll"
                                    >
                                        All
                                    </button>
                                    <button
                                        type="button"
                                        class="rounded-md border border-slate-200 px-2 py-0.5 text-xs font-medium text-slate-600 hover:bg-slate-50 transition"
                                        @click="clearMonths"
                                    >
                                        Clear
                                    </button>
                                </div>
                            </div>

                            <!-- Preset shortcuts -->
                            <div class="flex flex-wrap gap-1.5 mb-3">
                                <button
                                    type="button"
                                    class="rounded-lg border border-slate-200 bg-white px-2.5 py-1 text-xs font-semibold text-slate-600 hover:bg-indigo-50 hover:text-indigo-600 hover:border-indigo-200 transition"
                                    @click="thisMonth"
                                >
                                    Current Month
                                </button>
                                <button
                                    v-for="q in ['Q1','Q2','Q3','Q4']"
                                    :key="q"
                                    type="button"
                                    class="rounded-lg border border-slate-200 bg-white px-2.5 py-1 text-xs font-semibold text-slate-600 hover:bg-indigo-50 hover:text-indigo-600 hover:border-indigo-200 transition"
                                    @click="setQuarter(q)"
                                >
                                    {{ q }}
                                </button>
                            </div>

                            <!-- 12-Month Grid -->
                            <div class="grid grid-cols-3 gap-2">
                                <button
                                    v-for="m in monthsList"
                                    :key="m.value"
                                    type="button"
                                    @click="toggleMonth(m.value)"
                                    class="rounded-xl border px-3 py-2 text-center text-xs font-semibold transition"
                                    :class="selMonths.includes(m.value)
                                        ? 'border-indigo-500 bg-indigo-50 text-indigo-700 ring-2 ring-indigo-200'
                                        : 'border-slate-200 bg-slate-50/50 text-slate-600 hover:bg-slate-100'"
                                >
                                    {{ m.label }}
                                </button>
                            </div>

                            <!-- Popover Footer -->
                            <div class="mt-4 pt-3 border-t border-slate-100 flex items-center justify-between">
                                <span class="text-xs text-slate-500 font-medium">
                                    {{ selMonths.length }} of 12 months selected
                                </span>
                                <button
                                    type="button"
                                    class="rounded-xl bg-slate-900 px-4 py-1.5 text-xs font-bold text-white shadow-sm hover:bg-slate-800 transition"
                                    @click="applyMonthsAndClose"
                                >
                                    Done
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- Staff Filter Dropdown -->
                    <div class="relative min-w-[12rem]">
                        <div class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3 text-slate-400">
                            <i class="fa-solid fa-user text-xs"></i>
                        </div>
                        <select
                            id="assigned_staff"
                            v-model="selAssignedStaff"
                            class="w-full appearance-none rounded-xl border border-slate-200 bg-slate-50/70 hover:bg-slate-100/80 pl-8 pr-8 py-2 text-sm font-semibold text-slate-700 transition shadow-sm focus:outline-none focus:ring-2 focus:ring-indigo-300"
                            aria-label="Filter by assigned staff"
                            @change="applyFilters"
                        >
                            <option value="">All Assigned Staff</option>
                            <option v-for="staff in assignedStaffOptions" :key="staff" :value="staff">
                                {{ staff }}
                            </option>
                        </select>
                        <div class="pointer-events-none absolute inset-y-0 right-0 flex items-center px-2.5 text-slate-400">
                            <svg class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor">
                                <path fill-rule="evenodd" d="M5.23 7.21a.75.75 0 011.06.02L10 10.585l3.71-3.354a.75.75 0 111.02 1.096l-4.22 3.814a.75.75 0 01-1.02 0L5.25 8.326a.75.75 0 01-.02-1.115z" clip-rule="evenodd"/>
                            </svg>
                        </div>
                    </div>

                    <!-- Reset Filters Button -->
                    <button
                        type="button"
                        class="inline-flex items-center gap-1.5 rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm font-semibold text-slate-600 shadow-sm hover:bg-slate-50 transition"
                        @click="resetFilters"
                        title="Reset filters to current month and year"
                    >
                        <svg class="h-3.5 w-3.5 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                        </svg>
                        <span>Reset</span>
                    </button>
                </div>
            </div>

            <!-- Executive KPI Row: TicketStats (6-card interactive grid) -->
            <div>
                <TicketStats :stats="ticketStats" :trends="trends" :filters="currentFilters" />
            </div>

            <!-- View Tabs: Operations vs. CSM Satisfaction -->
            <div class="flex items-center justify-between border-b border-slate-200/80 pb-3 print:hidden">
                <div class="flex items-center gap-2">
                    <button
                        type="button"
                        @click="activeTab = 'operations'"
                        class="inline-flex items-center gap-2 px-4 py-2 rounded-xl text-sm font-bold transition"
                        :class="activeTab === 'operations'
                            ? 'bg-indigo-600 text-white shadow-sm'
                            : 'bg-white border border-slate-200/80 text-slate-600 hover:bg-slate-50'"
                    >
                        <i class="fas fa-chart-line text-xs"></i>
                        <span>Operations & SLA</span>
                    </button>

                    <button
                        type="button"
                        @click="activeTab = 'csm'"
                        class="inline-flex items-center gap-2 px-4 py-2 rounded-xl text-sm font-bold transition"
                        :class="activeTab === 'csm'
                            ? 'bg-indigo-600 text-white shadow-sm'
                            : 'bg-white border border-slate-200/80 text-slate-600 hover:bg-slate-50'"
                    >
                        <i class="fas fa-star text-xs text-amber-300"></i>
                        <span>Client Satisfaction (CSAT)</span>
                    </button>

                    <button
                        type="button"
                        @click="activeTab = 'all'"
                        class="inline-flex items-center gap-2 px-4 py-2 rounded-xl text-sm font-bold transition"
                        :class="activeTab === 'all'
                            ? 'bg-indigo-600 text-white shadow-sm'
                            : 'bg-white border border-slate-200/80 text-slate-600 hover:bg-slate-50'"
                    >
                        <i class="fas fa-table-cells-large text-xs"></i>
                        <span>All Views</span>
                    </button>
                </div>

                <div class="text-xs text-slate-400 font-medium hidden md:block">
                    Click chart bars or KPI badges to drill down into filtered tickets
                </div>
            </div>

            <!-- Tab 1 / Tab All: SLA & Operations Section -->
            <div v-if="activeTab === 'operations' || activeTab === 'all'" class="space-y-6">
                <!-- SLA & Turnaround Time Performance Widget -->
                <TicketSlaPerformance :sla-stats="slaStats" :filters="currentFilters" />

                <!-- Staff Workload & Capacity Card -->
                <StaffWorkloadCard :items="staffWorkload" :filters="currentFilters" />

                <!-- Categories & Division Breakdown Grid -->
                <div class="grid grid-cols-1 gap-6 xl:grid-cols-12">
                    <div class="xl:col-span-6">
                        <TechnicalSupportByNature :items="natureCounts" :filters="currentFilters" />
                    </div>
                    <div class="xl:col-span-6">
                        <TicketDivisionBar :items="divisionCounts" :filters="currentFilters" />
                    </div>
                </div>

                <!-- Technical Support Type / Action Breakdown -->
                <div>
                    <TechnicalSupportBySupportType :items="supportTypeCounts" :filters="currentFilters" />
                </div>
            </div>

            <!-- Tab 2 / Tab All: Client Satisfaction Section -->
            <div v-if="activeTab === 'csm' || activeTab === 'all'" class="space-y-6">
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

                <!-- Recent Client Commendations & Feedback Stream -->
                <RecentFeedbackCard :items="recentFeedback" />
            </div>
        </div>
    </AuthenticatedLayout>
</template>
