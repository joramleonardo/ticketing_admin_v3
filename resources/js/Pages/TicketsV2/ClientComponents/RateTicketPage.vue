<script setup>
    import { ref, computed, watch, onMounted } from "vue"
    import axios from "axios"
    import registerBg2 from "@/assets/img/register_bg_2.png";
    import { useToast } from "vue-toastification"

    const props = defineProps({
        refNo: { type: String, required: true }, // from Inertia props
    })

    // ---- state
    const loading = ref(false)
    const posting = ref(false)
    const error = ref("")
    const details = ref(null)
    const existing = ref(null)
    const toast = useToast()
    const score = ref(0)
    const comment = ref("")

    const ratingMap = [
    { score: 5, label: "Excellent" },
    { score: 4, label: "Very Good" },
    { score: 3, label: "Good" },
    { score: 2, label: "Poor" },
    { score: 1, label: "Very Poor" },
    ]
    const labelForScore = (n) => ratingMap.find(r => r.score === n)?.label || ""
    // const canSubmit = computed(() => score.value > 0 && !posting.value)
    // Disable submit when already rated
    const isRated = computed(() => !!existing.value && (
      existing.value.rating_status === 'Submitted' || !!existing.value.rating_result
    ))
    const canSubmit = computed(() => score.value > 0 && !posting.value && !isRated.value)

    // ====== CSM link (Google Form prefill) ======
    const CSM_BASE =
    "https://docs.google.com/forms/d/e/1FAIpQLSfda5Huf3g5zB5kIoemYLrM-SPR41VmTYRQ2gyeGwaeTtXPsg/viewform?usp=pp_url"

    const ENTRY = {
    CAT_ENG: "entry.613601820",
    DATE_ENG: "entry.204278290",
    SEX_ENG: "entry.468511028",
    SERVICE_ENG: "entry.474414778",

    CAT_TAG: "entry.1426259693",
    DATE_TAG: "entry.1416427164",
    SEX_TAG: "entry.2016337212",
    SERVICE_TAG: "entry.2008685738",

    STATIC_1: "entry.1725600",
    STATIC_2: "entry.1819805617",

    REF_ENG: "entry.298800992",
    NAME_ENG: "entry.855472974",
    REF_TAG: "entry.1449935561",
    NAME_TAG: "entry.1990048049",
    }

    const STATIC_PREFILL = new URLSearchParams({
    [ENTRY.CAT_ENG]: "Government (Employee or another agency)",
    [ENTRY.SERVICE_ENG]: "Technical Support Services",
    [ENTRY.CAT_TAG]: "Gobyerno (Empleyado o Ahensya)",
    [ENTRY.SERVICE_TAG]: "Technical Support Services",
    [ENTRY.STATIC_1]: "0",
    [ENTRY.STATIC_2]: "0",
    })

    const formatYmd = (val) => {
    if (!val) return ""
    if (typeof val === "string") {
        const m = val.match(/^(\d{4}-\d{2}-\d{2})/)
        if (m) return m[1]
    }
    const d = new Date(val)
    if (Number.isNaN(d.getTime())) return ""
    const pad = (n) => String(n).padStart(2, "0")
    return `${d.getFullYear()}-${pad(d.getMonth()+1)}-${pad(d.getDate())}`
    }

    const csmUrl = computed(() => {
    const url = new URL(CSM_BASE)
    const params = new URLSearchParams(url.search)

    STATIC_PREFILL.forEach((v, k) => params.set(k, v))

    if (details.value) {
        const completed = formatYmd(details.value.date_completed)
        const sex = details.value.client_sex || ""
        const ref = details.value.client_referenceNo || ""
        const name = details.value.client_name || ""

        if (completed) {
        params.set(ENTRY.DATE_ENG, completed)
        params.set(ENTRY.DATE_TAG, completed)
        }
        if (sex) {
        params.set(ENTRY.SEX_ENG, sex)
        params.set(ENTRY.SEX_TAG, sex)
        }
        if (ref) {
        params.set(ENTRY.REF_ENG, ref)
        params.set(ENTRY.REF_TAG, ref)
        }
        if (name) {
        params.set(ENTRY.NAME_ENG, name)
        params.set(ENTRY.NAME_TAG, name)
        }
    }

    url.search = params.toString()
    return url.toString()
    })

    // ---- data loading
    const load = async () => {
    if (!props.refNo) return
    loading.value = true
    error.value = ""
    try {
        const [{ data: d }, { data: r }] = await Promise.all([
        axios.get(`/client/ticket/${encodeURIComponent(props.refNo)}`),
        axios.get(`/client/ticket/${encodeURIComponent(props.refNo)}/rating`),
        ])
        details.value  = d
        existing.value = r || null

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

    onMounted(load)
    watch(() => props.refNo, load)

    // ---- submit
    const submit = async () => {
        if (!canSubmit.value || !props.refNo) return
            posting.value = true
            error.value = ""
        try {
            await axios.post(`/client/ticket/${encodeURIComponent(props.refNo)}/rating`, {
                rating_result: labelForScore(score.value),
                rating_comment_suggestion: comment.value,
            })
            await load()
            toast.success("Thank you! Your feedback has been submitted.")
        } catch (e) {
            error.value = "Unable to submit rating."
            toast.error("Unable to submit rating. Please try again.")
        } finally {
            posting.value = false
        }
    }
</script>

<template>
  <div>
    <!-- <navbar /> -->
    <main>
      <section class="relative w-full h-full py-40 min-h-screen">
        <div
          class="absolute top-0 w-full h-full bg-blueGray-800 bg-no-repeat bg-full"
          :style="`background-image: url('${registerBg2}');`"
            >
        </div>
        <div class="container mx-auto px-4 h-full">
            <div class="flex content-center items-center justify-center h-full">
                <div class="w-full lg:w-6/12 px-4">
                    <div class="relative flex flex-col min-w-0 break-words w-full mb-6 shadow-lg rounded-lg bg-blueGray-200 border-0" >
                        <div class="rounded-t mb-0 px-6 py-6">
                            <div class="text-center mb-3">
                                <h3 class="text-blueGray-500 font-bold">
                                TICKETING MANAGEMENT SYSTEM
                                </h3>
                            </div>
                            <div class="flex border-t border-gray-300">
                                <div class="flex-auto px-4 lg:px-10 py-10 pt-0">
                                    <div class="text-blueGray-400 text-center mb-3 mt-5 font-bold">
                                        <small>CLIENT FEEDBACK</small>
                                    </div>

                                    <div v-if="loading" class="text-sm text-gray-500">Loading…</div>
                                    <template v-else>
                                        <div v-if="error" class="text-sm text-red-600">{{ error }}</div>

                                        <!-- Simple summary -->
                                        <div v-if="details" class="grid sm:grid-cols-2 gap-3 text-sm">
                                            <div class="p-3 border rounded">
                                            <label for="employee_code" class="block uppercase text-blueGray-600 text-xs font-bold mb-2">
                                                Employee ID
                                            </label>
                                            <div class="font-mono">{{ details.client_referenceNo }}</div>
                                            </div>
                                            <div class="p-3 border rounded">
                                            <label for="employee_code" class="block uppercase text-blueGray-600 text-xs font-bold mb-2">
                                                Status
                                            </label>
                                            <div>{{ details.ticket_status }}</div>
                                            </div>
                                            <div class="p-3 border rounded">
                                            <label for="employee_code" class="block uppercase text-blueGray-600 text-xs font-bold mb-2">
                                                Date Completed
                                            </label>
                                            <div>{{ formatYmd(details.date_completed) || "—" }}</div>
                                            </div>
                                            <div class="p-3 border rounded">
                                            <label for="employee_code" class="block uppercase text-blueGray-600 text-xs font-bold mb-2">
                                                Client Name
                                            </label>
                                            <div>
                                                {{ details.client_name }}
                                            </div>
                                            </div>
                                        </div>

                                        <!-- Stars + comment -->
                                        <div class="p-4 border rounded space-y-3">

                                            <div class="text-sm font-medium">Please help us determine the quality of our services by ticking the appropriate option.</div>
                                            <div class="flex items-center gap-2">
                                                <button
                                                    v-for="n in 5"
                                                    :key="n"
                                                    type="button"
                                                    class="p-1"
                                                     :class="isRated ? 'opacity-50 cursor-not-allowed pointer-events-none' : ''"
                                                     @click="!isRated && (score = n)"
                                                    :aria-label="`Rate ${n} star(s)`"
                                                >
                                                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" class="w-7 h-7"
                                                        :class="n <= score ? 'fill-yellow-400 stroke-yellow-500' : 'fill-transparent stroke-gray-400'">
                                                    <path stroke-width="1.5"
                                                            d="M10 2.5l2.6 5.2 5.7.8-4.1 4 1 5.7-5.2-2.7-5.2 2.7 1-5.7-4.1-4 5.7-.8L10 2.5z"/>
                                                    </svg>
                                                </button>
                                                <div class="text-sm text-gray-700 min-w-[120px]">
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
                                                    :disabled="isRated"
                                                ></textarea>
                                            </div>

                                            <div v-if="isRated" class="text-xs px-3 py-2 rounded bg-green-50 text-green-700 border border-green-200">
                                                Feedback already submitted for this ticket. Thank you!
                                            </div>

                                            <div class="flex flex-wrap gap-2 justify-between items-center">
                                                <a
                                                    :href="csmUrl"
                                                    target="_blank"
                                                    class="px-4 py-2 rounded border text-white bg-lightBlue-500
                                                            hover:shadow-lg outline-none focus:outline-none
                                                    "
                                                >
                                                    Answer CSM
                                                </a>

                                                <button
                                                    class="bg-indigo-600 text-white px-4 py-2 rounded shadow hover:shadow-md disabled:opacity-60"
                                                    :disabled="!canSubmit"
                                                    @click="submit"
                                                >
                                                    {{ posting ? 'Submitting…' : (isRated ? 'Feedback Submitted' : 'Submit Feedback') }}
                                                </button>
                                            </div>

                                            <p v-if="details && !details.date_completed" class="text-xs text-amber-600">
                                                Note: Ticket completion date is not set yet. You can still send feedback, but the CSM link may require a completion date.
                                            </p>
                                        </div>
                                    </template>

                                </div>
                            </div>

                        </div>
                    </div>
                </div>
            </div>

        </div>
      </section>
    </main>
  </div>
</template>
