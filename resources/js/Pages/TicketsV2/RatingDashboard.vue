<script setup>
import { Head } from '@inertiajs/vue3'
import { onMounted, onBeforeUnmount } from 'vue'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'

// Reuse the same components you already built
import NoRatingTicketsAll  from '@/Pages/TicketsV2/NoRatingTickets.vue'
import HasRatingTicketsAll from '@/Pages/TicketsV2/HasRatingTickets.vue'

const props = defineProps({
  noRatingTicketsAll:  { type: Array,  default: () => [] }, // tickets without ratings
  hasRatingTicketsAll: { type: Array,  default: () => [] }, // tickets with ratings
  filters:             { type: Object, default: () => ({ month: null, year: null }) },
  ticketStats:         { type: Object, default: () => ({}) },
})

const SHEET_URL =
  'https://docs.google.com/spreadsheets/d/1dygq5DCCiIgkeDbrPF741CQNxs5XeVQQBklsxg22gaI/edit?gid=1640731546#gid=1640731546'

const TMS_URL =
  'https://docs.google.com/spreadsheets/d/1JTtVD-3g5v_CXW_LQKx4QN723VDUaDF4q7uK97ApKdE/edit?gid=1371109605#gid=1371109605'

function openSheet () { window.open(SHEET_URL, '_blank', 'noopener') }
function openTms ()   { window.open(TMS_URL,   '_blank', 'noopener') }

function onKeyDown(e) {
  const isCtrlOrCmd = e.ctrlKey || e.metaKey
  if (!isCtrlOrCmd || !e.shiftKey) return
  const k = String(e.key || '').toLowerCase()
  if (k === 'a') { e.preventDefault(); openSheet() } // CSM Sheet
  if (k === 's') { e.preventDefault(); openTms() }   // TMS Summary
}
onMounted(() => window.addEventListener('keydown', onKeyDown))
onBeforeUnmount(() => window.removeEventListener('keydown', onKeyDown))
</script>

<template>
  <Head title="CSM Dashboard" />

  <AuthenticatedLayout :ticket-stats="ticketStats" :filters="filters">
    <div class="space-y-6">
      <!-- Shortcuts / buttons row -->
      <div class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
          <div class="flex items-center justify-between px-4 py-3 border-b border-gray-200">
            <p class="text-sm text-slate-500">
              Shortcuts:
              <kbd class="px-1 py-0.5 border rounded">Ctrl/Cmd</kbd> +
              <kbd class="px-1 py-0.5 border rounded">Shift</kbd> +
              <kbd class="px-1 py-0.5 border rounded">A</kbd> (Old CSM) ·
              <kbd class="px-1 py-0.5 border rounded">S</kbd> (New CSM)
            </p>

            <div class="flex flex-col sm:flex-row gap-2 sm:ml-auto">
              <button
                type="button"
                @click="openSheet"
                aria-label="Open CSM Google Sheet"
                class="inline-flex items-center justify-center gap-2 rounded-xl px-4 py-2 text-sm font-semibold
                       bg-emerald-600 text-white shadow hover:bg-emerald-700 active:bg-emerald-800
                       focus:outline-none focus:ring-2 focus:ring-emerald-400 focus:ring-offset-2
                       transition w-full sm:w-auto"
              >
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"
                     class="h-5 w-5" fill="currentColor" aria-hidden="true">
                  <path d="M8 2a2 2 0 0 0-2 2v16c0 1.1.9 2 2 2h8a2 2 0 0 0 2-2V8l-6-6H8z"/>
                  <path d="M14 3.5V8h4.5" fill="#fff" opacity=".25"/>
                  <rect x="7.5" y="11" width="9" height="1.5" rx=".25" fill="#fff" opacity=".9"/>
                  <rect x="7.5" y="14" width="9" height="1.5" rx=".25" fill="#fff" opacity=".9"/>
                  <rect x="7.5" y="17" width="6" height="1.5" rx=".25" fill="#fff" opacity=".9"/>
                </svg>
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
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"
                     class="h-5 w-5" fill="currentColor" aria-hidden="true">
                  <path d="M8 2a2 2 0 0 0-2 2v16c0 1.1.9 2 2 2h8a2 2 0 0 0 2-2V8l-6-6H8z"/>
                  <path d="M14 3.5V8h4.5" fill="#fff" opacity=".25"/>
                  <rect x="7.5" y="11" width="9" height="1.5" rx=".25" fill="#fff" opacity=".9"/>
                  <rect x="7.5" y="14" width="9" height="1.5" rx=".25" fill="#fff" opacity=".9"/>
                  <rect x="7.5" y="17" width="6" height="1.5" rx=".25" fill="#fff" opacity=".9"/>
                </svg>
                Open CSM Sheet (Old)
              </button>
            </div>
          </div>
        </div>

      <!-- Two columns: No Ratings vs With Ratings -->
      <div class="grid grid-cols-1 xl:grid-cols-2 gap-6 items-start">
        <div>
          <NoRatingTicketsAll
            :tickets="noRatingTicketsAll"
            :initial-month="filters?.month"
            :initial-year="filters?.year"
            reload-only="noRatingTicketsAll"
          />
        </div>

        <div>
          <HasRatingTicketsAll
            :tickets="hasRatingTicketsAll"
            :initial-month="filters?.month"
            :initial-year="filters?.year"
            reload-only="hasRatingTicketsAll"
          />
        </div>
      </div>
    </div>
  </AuthenticatedLayout>
</template>
