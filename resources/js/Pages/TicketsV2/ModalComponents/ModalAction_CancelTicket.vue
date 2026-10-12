<script setup>
defineProps({
    show: {
        type: Boolean,
        default: false,
    },
    ticket: {
        type: Object,
        default: null,
    },
})

defineEmits(['close', 'cancelled'])
</script>

<template>
    <!-- Deprecated stub: tickets are completed with "Ticket Cancelled" remarks -->
</template>

