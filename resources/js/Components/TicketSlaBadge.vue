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
})

const sla = computed(() => getTicketSlaStatus(props.ticket))
</script>

<template>
    <div
        class="inline-flex items-center gap-1.5 border rounded-full font-medium"
        :class="[
            sla.badgeClass,
            compact ? 'px-2 py-0.5 text-[11px]' : 'px-2.5 py-1 text-xs'
        ]"
        :title="sla.isBreached ? 'Exceeded standard SLA target (>24h)' : sla.isWarning ? 'Approaching SLA target (24h-48h)' : 'Within standard SLA turnaround'"
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
</template>

