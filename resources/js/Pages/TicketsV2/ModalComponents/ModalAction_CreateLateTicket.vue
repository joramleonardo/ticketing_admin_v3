<script setup>
import ModalAction_CreateTicket from '@/Pages/TicketsV2/ModalComponents/ModalAction_CreateTicket.vue'

const emit = defineEmits(['success', 'created', 'close'])
</script>

<template>
    <ModalAction_CreateTicket
        initial-mode="late"
        @success="emit('success')"
        @created="emit('created')"
        @close="emit('close')"
    />
</template>
