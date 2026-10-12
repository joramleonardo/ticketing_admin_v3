<script setup>
    import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
    import CardAllTicket from "@/Components/Cards/CardAllTicket.vue";
    import { Head } from '@inertiajs/vue3';

    const props = defineProps({
        ticketStats: Object,
        filters: Object,
        tickets: Array, // ✅ define tickets prop
    });

</script>

<template>
    <Head title="Ticket" />

    <AuthenticatedLayout
        :ticket-stats="ticketStats"
        :filters="filters"
    >
        <div>
            <div class="flex flex-wrap mt-4">
                <div class="w-full xl:w-12/12 mb-12 xl:mb-0 px-4">
                    <CardAllTicket :tickets="tickets" />
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
