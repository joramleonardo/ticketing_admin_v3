<script setup>
    import { Head } from '@inertiajs/vue3'
    import { onMounted, onBeforeUnmount } from 'vue'
    import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
    import NoCSMTicketsAll  from '@/Pages/TicketsV2/CSMComponents/NoCSMTicketsAll.vue'
    import HasCSMTicketsAll from '@/Pages/TicketsV2/CSMComponents/HasCSMTicketsAll.vue'

    const props = defineProps({
        noCsmTicketsAll:  { type: Array,  default: () => [] },
        hasCsmTicketsAll: { type: Array,  default: () => [] },
        filters:          { type: Object, default: () => ({ month: null, year: null }) },
        ticketStats:      { type: Object, default: () => ({}) },
    })

    const SHEET_URL =
        'https://docs.google.com/spreadsheets/d/1dygq5DCCiIgkeDbrPF741CQNxs5XeVQQBklsxg22gaI/edit?gid=1640731546#gid=1640731546'

    const FORMSHEET_URL =
        'https://docs.google.com/forms/d/e/1FAIpQLSfda5Huf3g5zB5kIoemYLrM-SPR41VmTYRQ2gyeGwaeTtXPsg/viewform?usp=dialog'


    const TMS_URL =
        'https://docs.google.com/spreadsheets/d/1JTtVD-3g5v_CXW_LQKx4QN723VDUaDF4q7uK97ApKdE/edit?gid=1371109605#gid=1371109605'

    function openSheet () {
        window.open(SHEET_URL, '_blank', 'noopener')
    }
    function openSheetForm () {
        window.open(FORMSHEET_URL, '_blank', 'noopener')
    }

    function openTms () {
        window.open(TMS_URL, '_blank', 'noopener')
    }

    function onKeyDown(e) {
        const isCtrlOrCmd = e.ctrlKey || e.metaKey
        if (!isCtrlOrCmd || !e.shiftKey) return

        const k = String(e.key || '').toLowerCase()
        if (k === 'a') { e.preventDefault(); openSheet() }   // CSM Sheet
        if (k === 's') { e.preventDefault(); openTms() }     // TMS Summary
    }

    onMounted(() => window.addEventListener('keydown', onKeyDown))
    onBeforeUnmount(() => window.removeEventListener('keydown', onKeyDown))
</script>

<template>
  <Head title="CSM Dashboard" />

  <AuthenticatedLayout :ticket-stats="ticketStats" :filters="filters">
    <div class="flex flex-wrap">
      <div class="w-full xl:w-12/12 mb-12 xl:mb-3 px-4 mt-2">
        <div class="relative flex flex-col min-w-0 break-words bg-white w-full mb-6 shadow-lg rounded">
          <div class="flex items-center justify-between px-4 py-3 border-b border-gray-200">
            <p class="text-sm text-slate-500">
              Shortcuts:
              <kbd class="px-1 py-0.5 border rounded">Ctrl/Cmd</kbd> +
              <kbd class="px-1 py-0.5 border rounded">Shift</kbd> +
              <kbd class="px-1 py-0.5 border rounded">A</kbd> (Old CSM) ·
              <kbd class="px-1 py-0.5 border rounded">S</kbd> (New CSM)
            </p>

            <!-- Button group -->
            <div class="flex flex-col sm:flex-row gap-2 sm:ml-auto">
              <button
                type="button"
                @click="openSheetForm"
                aria-label="Open CSM Google Sheet"
                class="inline-flex items-center justify-center gap-2 rounded-xl px-4 py-2 text-sm font-semibold
                       bg-orange-600 text-white shadow hover:bg-orange-700 active:bg-orange-800
                       focus:outline-none focus:ring-2 focus:ring-orange-400 focus:ring-offset-2
                       transition w-full sm:w-auto"
              >

                Google Form
              </button>
              <button
                type="button"
                @click="openSheet"
                aria-label="Open CSM Google Sheet"
                class="inline-flex items-center justify-center gap-2 rounded-xl px-4 py-2 text-sm font-semibold
                       bg-emerald-600 text-white shadow hover:bg-emerald-700 active:bg-emerald-800
                       focus:outline-none focus:ring-2 focus:ring-emerald-400 focus:ring-offset-2
                       transition w-full sm:w-auto"
              >
                Open CSM Sheet (New)
              </button>

              <button
                type="button"
                @click="openTms"
                aria-label="Open TMS Summary Report"
                class="inline-flex items-center justify-center gap-2 rounded-xl px-4 py-2 text-sm font-semibold
                       bg-indigo-600 text-white shadow hover:bg-indigo-700 active:bg-indigo-800
                       focus:outline-none focus:ring-2 focus:ring-indigo-400 focus:ring-offset-2
                       transition w-full sm:w-auto"
              >
                Open CSM Sheet (Old)
              </button>
            </div>
          </div>
        </div>
      </div>

      <div class="w-full xl:w-6/12 mb-12 xl:mb-8 px-4">
        <NoCSMTicketsAll
          :tickets="noCsmTicketsAll"
          :initial-month="filters?.month"
          :initial-year="filters?.year"
        />
      </div>

      <div class="w-full xl:w-6/12 mb-12 xl:mb-8 px-4">
        <HasCSMTicketsAll
          :tickets="hasCsmTicketsAll"
          :initial-month="filters?.month"
          :initial-year="filters?.year"
        />
      </div>
    </div>
  </AuthenticatedLayout>
</template>
