<script setup>
    import { ref, computed, watch, nextTick } from "vue"
    import axios from "axios"
    import { buildCsmUrl } from "@/Composables/useCsmPrefill"

    const props = defineProps({
        show: { type: Boolean, default: false },
        ticketRef: { type: String, default: null },
    })
    const emit = defineEmits(["close"])

    const loading = ref(false)
    const posting = ref(false)
    const error = ref("")
    const details = ref(null)    // ticket meta needed for CSM link + gating
    const existing = ref(null)   // existing rating (if any)

    const score = ref(0)         // 1..5
    const comment = ref("")

    const ratingMap = [
        { score: 5, label: "Excellent" },
        { score: 4, label: "Very Good" },
        { score: 3, label: "Good" },
        { score: 2, label: "Poor" },
        { score: 1, label: "Very Poor" },
    ]
    const labelForScore = (n) => ratingMap.find(r => r.score === n)?.label || ""

    const canSubmit = computed(() => score.value > 0 && !posting.value)

    const csmUrl = computed(() => {
        return buildCsmUrl(details.value || {})
    })

    const load = async () => {
        if (!props.ticketRef) return
        loading.value = true
        error.value = ""
        try {
            const [{ data: d }, { data: r }] = await Promise.all([
                axios.get(`/client/ticket/${encodeURIComponent(props.ticketRef)}`),
                axios.get(`/client/ticket/${encodeURIComponent(props.ticketRef)}/rating`),
            ])
            details.value  = d
            existing.value = r || null

            // Prefill if already rated
            if (existing.value?.rating_result) {
                const found = ratingMap.find(x => x.label === existing.value.rating_result)
                score.value = found?.score || 0
                comment.value = existing.value.rating_comment_suggestion || ""
            } else {
                score.value = 0
                comment.value = ""
            }
        } catch (e) {
            error.value = "Failed to load ticket/rating details."
        } finally {
            loading.value = false
        }
    }

    watch(() => props.show, (v) => { if (v) load() })
    watch(() => props.ticketRef, () => { if (props.show) load() })

    const submit = async () => {
        if (!canSubmit.value || !props.ticketRef) return
        posting.value = true
        error.value = ""
        try {
            await axios.post(`/client/ticket/${encodeURIComponent(props.ticketRef)}/rating`, {
            rating_result: labelForScore(score.value),
            rating_comment_suggestion: comment.value,
            })
            // reload to reflect saved state
            await load()
        } catch (e) {
            error.value = "Unable to submit rating."
        } finally {
            posting.value = false
        }
    }
</script>

<template>
  <div v-if="show" class="fixed inset-0 z-50 flex items-center justify-center">
    <div class="absolute inset-0 bg-black/40" @click="$emit('close')"></div>

    <div class="relative bg-white rounded-2xl shadow-xl w-full max-w-2xl mx-4">
      <div class="flex items-center justify-between px-5 py-4 border-b">
        <h3 class="text-lg font-semibold">Rate Ticket</h3>
        <button class="text-gray-500 hover:text-gray-700" @click="$emit('close')">✕</button>
      </div>

      <div class="p-5 space-y-5">
        <div v-if="loading" class="text-sm text-gray-500">Loading…</div>
        <template v-else>
          <div v-if="error" class="text-sm text-red-600">{{ error }}</div>

          <!-- Summary for CSM -->
          <div v-if="details" class="grid sm:grid-cols-2 gap-3 text-sm">
            <div class="p-3 border rounded">
              <div class="text-[11px] text-gray-500">Reference No</div>
              <div class="font-mono">{{ details.client_referenceNo }}</div>
            </div>
            <div class="p-3 border rounded">
              <div class="text-[11px] text-gray-500">Status</div>
              <div>{{ details.ticket_status }}</div>
            </div>
            <div class="p-3 border rounded">
              <div class="text-[11px] text-gray-500">Completed</div>
              <div>{{ formatUsDate(details.date_completed) || "—" }}</div>
            </div>
            <div class="p-3 border rounded">
              <div class="text-[11px] text-gray-500">Client</div>
              <div>{{ details.client_name }} <span v-if="details.client_sex" class="text-gray-500">({{ details.client_sex }})</span></div>
            </div>
          </div>

          <!-- Rating -->
          <div class="p-4 border rounded space-y-3">
            <div class="text-sm font-medium">Your Rating</div>
            <div class="flex items-center gap-2">
              <button
                v-for="n in 5"
                :key="n"
                type="button"
                class="p-1"
                @click="score = n"
                :aria-label="`Rate ${n} star(s)`"
              >
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" class="w-7 h-7"
                     :class="n <= score ? 'fill-yellow-400 stroke-yellow-500' : 'fill-transparent stroke-gray-400'">
                  <path stroke-width="1.5"
                        d="M10 2.5l2.6 5.2 5.7.8-4.1 4 1 5.7-5.2-2.7-5.2 2.7 1-5.7-4.1-4 5.7-.8L10 2.5z"/>
                </svg>
              </button>
              <div class="text-sm text-gray-700 min-w-[96px]">
                {{ score ? (['Very Poor','Poor','Good','Very Good','Excellent'][score-1]) : 'Select rating' }}
              </div>
            </div>

            <div>
              <label class="text-sm font-medium mb-1 block">Comments / Suggestions</label>
              <textarea
                v-model="comment"
                rows="3"
                class="w-full border rounded px-3 py-2 text-sm focus:outline-none focus:ring focus:ring-indigo-200"
                placeholder="Tell us more about your experience (optional)…"
              ></textarea>
            </div>

            <div class="flex flex-wrap gap-2 justify-between items-center">
              <a
                :href="csmUrl"
                target="_blank"
                class="px-4 py-2 rounded border text-indigo-700 hover:bg-indigo-50 bg-indigo-600"
              >
                Link for CSM
              </a>

              <button
                class="bg-indigo-600 text-white px-4 py-2 rounded shadow hover:shadow-md disabled:opacity-60"
                :disabled="!canSubmit"
                @click="submit"
              >
                {{ posting ? 'Submitting…' : (existing ? 'Update Feedback' : 'Submit Feedback') }}
              </button>
            </div>

            <p v-if="details && !details.date_completed" class="text-xs text-amber-600">
              Note: Ticket completion date is not set yet. You can still send feedback, but CSM link may require completion date.
            </p>
          </div>
        </template>
      </div>

      <div class="flex justify-end gap-2 px-5 py-4 border-t">
        <button class="px-4 py-2 rounded border hover:bg-gray-50" @click="$emit('close')">Close</button>
      </div>
    </div>
  </div>
</template>
