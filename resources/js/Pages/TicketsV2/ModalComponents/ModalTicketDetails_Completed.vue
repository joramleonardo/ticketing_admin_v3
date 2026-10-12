<template>
  <ModalTicketDetails
    :show="show"
    :ticket="ticket"
    :auto-fetch-logs="autoFetchLogs"
    :from-my-ticket="fromMyTicket"
    @close="$emit('close')"
    @updated="$emit('updated', $event)"
  />
</template>

<script setup>
import ModalTicketDetails from '@/Pages/TicketsV2/ModalComponents/ModalTicketDetails.vue'

defineProps({
  show: { type: Boolean, default: false },
  ticket: { type: Object, default: null },
  autoFetchLogs: { type: Boolean, default: true },
  fromMyTicket: { type: Boolean, default: false },
})

defineEmits(['close', 'updated'])
</script>
