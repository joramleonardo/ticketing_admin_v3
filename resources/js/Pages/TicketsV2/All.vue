<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import CardAllTicket from '@/Pages/TicketsV2/AllTicketsV2.vue'
import { Head } from '@inertiajs/vue3'

const props = defineProps({
  ticketStats: { type: Object, default: () => ({}) },
  filters:     { type: Object, default: () => ({}) },
  tickets:     { type: Object, default: () => ({}) }, // paginator object
  counts:      { type: Object, default: () => ({ All: 0, New: 0, Pending: 0, 'In Progress': 0, Completed: 0 }) },
})
</script>

<template>
  <Head title="Ticket" />

  <AuthenticatedLayout
    :ticket-stats="ticketStats"
    :filters="filters"
  >
    <div class="w-full">
      <!-- Pass everything AllTicketsV2 uses -->
      <CardAllTicket
        :tickets="tickets"
        :filters="filters"
        :counts="counts"
      />
    </div>
  </AuthenticatedLayout>
</template>
