<template>
  <ModalTicketDetails
    :show="open"
    :ticket="ticket"
    :auto-fetch-logs="autoFetchLogs"
    @close="$emit('update:open', false)"
    @updated="$emit('updated', $event)"
  />
</template>

<script setup>
import ModalTicketDetails from '@/Pages/TicketsV2/ModalComponents/ModalTicketDetails.vue'

defineProps({
  open: { type: Boolean, default: false },
  ticket: { type: Object, default: null },
  autoFetchLogs: { type: Boolean, default: true },
})

defineEmits(['update:open', 'updated'])
</script>
