<script setup>
import { Link } from '@inertiajs/vue3'

const props = defineProps({
  items: {
    type: Array,
    default: () => [],
  },
})

function ratingLabelClass(rating) {
  const r = (rating || '').toLowerCase()
  if (r.includes('excellent') || r.includes('5')) return 'bg-emerald-50 text-emerald-700 border-emerald-200'
  if (r.includes('very good') || r.includes('4')) return 'bg-blue-50 text-blue-700 border-blue-200'
  if (r.includes('good') || r.includes('3')) return 'bg-slate-50 text-slate-700 border-slate-200'
  return 'bg-rose-50 text-rose-700 border-rose-200 font-black'
}
</script>

<template>
  <div class="relative flex flex-col min-w-0 break-words bg-white rounded-2xl border border-slate-200/90 shadow-sm p-5 transition-shadow hover:shadow-md">
    <!-- Card Header -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between pb-4 border-b border-slate-100 gap-2">
      <div class="flex items-center gap-2.5">
        <div class="p-2 rounded-xl bg-amber-50 text-amber-600 border border-amber-100 shrink-0 shadow-xs">
          <svg class="h-5 w-5" fill="currentColor" viewBox="0 0 20 20">
            <path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z" />
          </svg>
        </div>
        <div>
          <h2 class="text-base font-bold text-slate-800 tracking-tight flex items-center gap-2">
            Recent Client Commendations & Feedback
            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-bold bg-amber-50 text-amber-700 border border-amber-200">
              Live Feed
            </span>
          </h2>
          <p class="text-xs text-slate-400 font-medium">Real-time ratings, comments, and client experience sentiments</p>
        </div>
      </div>

      <div class="text-xs text-slate-400 font-medium hidden sm:block">
        Showing latest verified reviews
      </div>
    </div>

    <!-- Feedback Stream -->
    <div v-if="items && items.length > 0" class="mt-4 grid grid-cols-1 md:grid-cols-2 gap-3.5">
      <div
        v-for="fb in items"
        :key="fb.client_referenceNo"
        class="rounded-xl border border-slate-100 bg-slate-50/50 p-4 transition-all hover:bg-white hover:border-slate-300 hover:shadow-sm flex flex-col justify-between"
      >
        <div>
          <!-- Header: Stars, Rating, Ref -->
          <div class="flex items-center justify-between gap-2 mb-2">
            <!-- Star Icons (1 to 5) -->
            <div class="flex items-center gap-0.5" :title="`${fb.stars} out of 5 stars`">
              <svg
                v-for="star in 5"
                :key="star"
                class="w-4 h-4"
                :class="star <= fb.stars ? 'text-amber-400 fill-amber-400' : 'text-slate-200 fill-slate-200'"
                viewBox="0 0 20 20"
              >
                <path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z" />
              </svg>
              <span
                class="ml-1.5 px-2 py-0.5 rounded-full text-[10px] font-bold border"
                :class="ratingLabelClass(fb.rating_result)"
              >
                {{ fb.rating_result }}
              </span>
            </div>

            <!-- Reference Link -->
            <Link
              :href="route('tickets-v2.index', { search: fb.client_referenceNo })"
              class="font-mono text-xs font-bold text-rose-700 hover:underline"
              :title="`Open ticket ${fb.client_referenceNo}`"
            >
              {{ fb.client_referenceNo }}
            </Link>
          </div>

          <!-- Attention Needed Alert for Low Ratings -->
          <div
            v-if="fb.stars <= 2"
            class="mb-2 px-2.5 py-1 rounded-lg bg-rose-50 border border-rose-200 text-rose-700 text-xs font-bold flex items-center gap-1.5"
          >
            <span class="w-2 h-2 rounded-full bg-rose-500 animate-ping"></span>
            <span>Needs Attention — Low Satisfaction Rating</span>
          </div>

          <!-- Written Comment / Suggestion -->
          <div class="mb-3">
            <p v-if="fb.comment" class="text-xs text-slate-700 italic bg-white p-2.5 rounded-lg border border-slate-100 shadow-2xs leading-relaxed">
              “{{ fb.comment }}”
            </p>
            <p v-else class="text-xs text-slate-400 italic">
              (No written comment provided)
            </p>
          </div>
        </div>

        <!-- Footer Meta: Client, Tech, Time -->
        <div class="pt-2 border-t border-slate-100/80 flex items-center justify-between text-[11px] text-slate-500 flex-wrap gap-1">
          <div class="flex items-center gap-1.5">
            <span class="font-bold text-slate-700">{{ fb.client_name }}</span>
            <span>({{ fb.division }})</span>
          </div>

          <div class="flex items-center gap-2">
            <span class="text-indigo-600 font-semibold" :title="`Assigned to ${fb.assigned_staff}`">
              👤 {{ fb.assigned_staff }}
            </span>
            <span>•</span>
            <span class="text-slate-400">{{ fb.created_at }}</span>
          </div>
        </div>
      </div>
    </div>

    <!-- Empty State -->
    <div
      v-else
      class="mt-4 flex flex-col items-center justify-center py-10 px-4 text-center border border-dashed border-slate-200 rounded-xl bg-slate-50/50"
    >
      <div class="w-12 h-12 rounded-full bg-amber-50 flex items-center justify-center text-amber-500 mb-2">
        <svg class="h-6 w-6" fill="currentColor" viewBox="0 0 20 20">
          <path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z" />
        </svg>
      </div>
      <p class="text-sm font-bold text-slate-700">No Feedback Received Yet</p>
      <p class="text-xs text-slate-400 mt-1 max-w-xs">
        Client ratings and commendations will automatically appear here once submitted.
      </p>
    </div>
  </div>
</template>

