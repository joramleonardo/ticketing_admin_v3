<script setup>
import { computed } from 'vue'

const props = defineProps({
    status: {
        type: String,
        default: 'New',
    },
    compact: {
        type: Boolean,
        default: false,
    },
})

const STEPS = [
    { key: 'New', label: 'Submitted', desc: 'Ticket received' },
    { key: 'Pending', label: 'Assigned', desc: 'Staff allocated' },
    { key: 'In Progress', label: 'In Progress', desc: 'Work underway' },
    { key: 'Completed', label: 'Completed', desc: 'Solution ready' },
    { key: 'Closed', label: 'Closed / Rated', desc: 'Finished' },
]

const STATUS_ORDER = {
    'New': 1,
    'Pending': 2,
    'In Progress': 3,
    'Completed': 4,
    'Closed': 5,
}

const currentStepIndex = computed(() => {
    return STATUS_ORDER[props.status] || 1
})

const statusExplanation = computed(() => {
    switch (props.status) {
        case 'New':
            return {
                title: 'Ticket Submitted',
                message: 'Your request has been logged successfully and is queued for staff review.',
                bgClass: 'bg-blue-50 border-blue-200 text-blue-900',
                iconColor: 'text-blue-600',
            }
        case 'Pending':
            return {
                title: 'Assigned to Staff',
                message: 'Your request is assigned to a specialist and is scheduled to be attended shortly.',
                bgClass: 'bg-amber-50 border-amber-200 text-amber-900',
                iconColor: 'text-amber-600',
            }
        case 'In Progress':
            return {
                title: 'Actively Being Handled',
                message: 'Our technical support team is currently working on diagnosing and solving your request.',
                bgClass: 'bg-emerald-50 border-emerald-200 text-emerald-900',
                iconColor: 'text-emerald-600',
            }
        case 'Completed':
            return {
                title: 'Work Completed — Rating Requested',
                message: 'The requested work is finished! Please rate your service experience below to officially close this ticket.',
                bgClass: 'bg-purple-50 border-purple-200 text-purple-900',
                iconColor: 'text-purple-600',
            }
        case 'Closed':
            return {
                title: 'Ticket Closed & Rated',
                message: 'This ticket has been completed and verified with client satisfaction feedback. Thank you!',
                bgClass: 'bg-slate-50 border-slate-200 text-slate-800',
                iconColor: 'text-slate-600',
            }
        default:
            return {
                title: 'Status: ' + props.status,
                message: 'Processing ticket workflow.',
                bgClass: 'bg-gray-50 border-gray-200 text-gray-800',
                iconColor: 'text-gray-600',
            }
    }
})
</script>

<template>
    <div class="w-full">
        <!-- Visual Stepper Progress Bar -->
        <div class="relative flex items-center justify-between px-3" :class="compact ? 'mb-1' : 'mb-4'">
            <!-- Connecting background line (aligned between first and last node centers) -->
            <div
                class="absolute left-5 right-5 h-0.5 bg-slate-200 -z-0"
                :class="compact ? 'top-3' : 'top-4'"
            ></div>
            <!-- Connecting active colored line -->
            <div
                class="absolute left-5 h-0.5 bg-blue-600 transition-all duration-500 -z-0"
                :class="compact ? 'top-3' : 'top-4'"
                :style="{
                    width: currentStepIndex > 1
                        ? `calc(${((currentStepIndex - 1) / (STEPS.length - 1)) * 100}% - ${(STEPS.length - currentStepIndex) * 2}px)`
                        : '0%'
                }"
            ></div>

            <!-- Steps -->
            <div
                v-for="(step, idx) in STEPS"
                :key="step.key"
                class="flex flex-col items-center relative z-10"
            >
                <!-- Circle node -->
                <div
                    class="rounded-full flex items-center justify-center font-bold transition-all duration-300 shadow-2xs"
                    :class="[
                        compact ? 'w-6 h-6 text-[10px]' : 'w-8 h-8 text-xs',
                        idx + 1 < currentStepIndex
                            ? 'bg-blue-600 text-white ring-3 ring-blue-100'
                            : idx + 1 === currentStepIndex
                                ? 'bg-white border-2 border-blue-600 text-blue-700 ring-4 ring-blue-100/90 font-black'
                                : 'bg-slate-100 border border-slate-300 text-slate-400'
                    ]"
                >
                    <!-- Check icon for finished steps -->
                    <i v-if="idx + 1 < currentStepIndex" class="fa-solid fa-check text-[10px]"></i>
                    <!-- Current / future number -->
                    <span v-else>{{ idx + 1 }}</span>
                </div>

                <!-- Label -->
                <span
                    class="text-center transition-colors select-none"
                    :class="[
                        compact ? 'text-[10px] mt-1' : 'text-xs mt-1.5',
                        idx + 1 === currentStepIndex
                            ? 'text-blue-900 font-bold'
                            : idx + 1 < currentStepIndex
                                ? 'text-slate-700 font-medium'
                                : 'text-slate-400 font-normal'
                    ]"
                >
                    {{ step.label }}
                </span>
            </div>
        </div>

        <!-- Human-Friendly Status Explanation Callout -->
        <div
            v-if="!compact"
            class="p-3.5 rounded-xl border flex items-start gap-3 transition-colors"
            :class="statusExplanation.bgClass"
        >
            <div class="mt-0.5 shrink-0" :class="statusExplanation.iconColor">
                <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
            </div>
            <div>
                <h5 class="text-xs sm:text-sm font-bold">{{ statusExplanation.title }}</h5>
                <p class="text-xs opacity-90 mt-0.5">{{ statusExplanation.message }}</p>
            </div>
        </div>
    </div>
</template>

