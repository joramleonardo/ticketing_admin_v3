<script setup>
import { computed } from 'vue'
import { getTicketSlaStatus } from '@/Utils/slaHelper'

const props = defineProps({
    ticket: {
        type: Object,
        required: true,
    },
    compact: {
        type: Boolean,
        default: false,
    },
    showResponseTime: {
        type: Boolean,
        default: false,
    },
})

const sla = computed(() => getTicketSlaStatus(props.ticket))
</script>

<template>
    <div class="inline-flex items-center gap-1.5 flex-wrap">
        <div
            class="inline-flex items-center gap-1.5 border rounded-full font-medium"
            :class="[
                sla.badgeClass,
                compact ? 'px-2 py-0.5 text-[11px]' : 'px-2.5 py-1 text-xs'
            ]"
            :title="sla.tooltip"
        >
            <span
                class="w-1.5 h-1.5 rounded-full"
                :class="sla.dotClass"
            ></span>

            <!-- Clock icon -->
            <svg xmlns="http://www.w3.org/2000/svg" class="w-3 h-3 opacity-75 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
            </svg>

            <span class="whitespace-nowrap">{{ sla.label }}</span>
        </div>

        <!-- Optional Response Time Pill (Assigned -> Attended) -->
        <span
            v-if="showResponseTime && sla.responseFormatted && sla.type !== 'response'"
            class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-semibold bg-indigo-50 text-indigo-700 border border-indigo-200 whitespace-nowrap"
            :title="`Response Time (Assigned → Attended): ${sla.responseFormatted}`"
        >
            <i class="fa-solid fa-reply text-[9px] opacity-75"></i>
            Resp: {{ sla.responseFormatted }}
        </span>
    </div>
</template>
