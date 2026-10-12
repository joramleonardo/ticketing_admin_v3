<script setup>
    import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
    import CardNewTickets from "@/Components/Cards/CardNewTickets.vue";
    import CardMyTickets from "@/Components/Cards/CardMyTickets.vue";

    import { Head } from '@inertiajs/vue3';

    const props = defineProps({
        ticketStats: Object,
        filters: Object,
    });

</script>

<template>
    <Head title="My Ticket" />

    <AuthenticatedLayout
    :ticket-stats="ticketStats"
    :filters="filters"
    >
        <div>
            <div class="flex flex-wrap mt-4">
                <div class="w-full xl:w-8/12 mb-12 xl:mb-0 px-4">
                    <CardNewTickets />
                </div>
                <div class="w-full xl:w-4/12 px-4">
                    <CardMyTickets />
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
