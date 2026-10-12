<template>
  <!-- Header -->
  <div class="relative bg-blue-900 md:pt-32 pb-32 pt-12">

    <div class="px-4 md:px-10 mx-auto w-full">
      <div>
        <!-- Filters -->
        <div class="flex gap-4 px-4 mb-4">
            <select v-model="selectedMonth" @change="applyFilter" class="border p-2 rounded">
                <option value="">All Months</option>
                <option v-for="m in 12" :key="m" :value="m">{{ monthName(m) }}</option>
            </select>

            <select v-model="selectedYear" @change="applyFilter" class="border p-2 rounded">
                <option value="">All Years</option>
                <option v-for="year in years" :key="year" :value="year">{{ year }}</option>
            </select>
        </div>
        <!-- Card stats -->
        <div class="flex flex-wrap">
          <div class="w-full lg:w-6/12 xl:w-3/12 px-4">
            <card-stats
              statSubtitle="UNASSIGNED"
              :statTitle="ticketStats.unassigned"
              statPercentColor="text-emerald-500"
              statDescripiron="Not yet assigned to anyone"
              statIconName="far fa-plus"
              statIconColor="bg-red-500"
            />
          </div>
          <div class="w-full lg:w-6/12 xl:w-3/12 px-4">
            <card-stats
              statSubtitle="PENDING"
              :statTitle="ticketStats.pending"
              statPercentColor="text-red-500"
              statDescripiron="Waiting for action or response"
              statIconName="fas fa-exclamation"
              statIconColor="bg-orange-500"
            />
          </div>
          <div class="w-full lg:w-6/12 xl:w-3/12 px-4">
            <card-stats
              statSubtitle="IN PROGRESS"
            :statTitle="ticketStats.in_progress"
              statPercentColor="text-orange-500"
              statDescripiron="Currently being worked on"
              statIconName="fas fa-spinner"
              statIconColor="bg-pink-500"
            />
          </div>
          <div class="w-full lg:w-6/12 xl:w-3/12 px-4">
            <card-stats
              statSubtitle="COMPLETED"
            :statTitle="ticketStats.completed"
              statPercentColor="text-emerald-500"
              statDescripiron="Ticket has been resolved"
              statIconName="fas fa-check"
              statIconColor="bg-emerald-500"
            />
          </div>
        </div>
      </div>
    </div>

  </div>
</template>

<script setup>
    import CardStats from "@/Components/Cards/CardStats.vue";
    import { router } from '@inertiajs/vue3';
    import { ref } from 'vue';

    const props = defineProps({
    ticketStats: Object,
    initialMonth: String,
    initialYear: String
    });

    const selectedMonth = ref(props.initialMonth || '');
    const selectedYear = ref(props.initialYear || '');

    const years = Array.from({ length: 5 }, (_, i) => new Date().getFullYear() - i);

    function monthName(month) {
    return new Date(2000, month - 1).toLocaleString('default', { month: 'long' });
    }

    function applyFilter() {
    router.get('/dashboard', {
        month: selectedMonth.value,
        year: selectedYear.value,
    }, {
        preserveState: true,
        preserveScroll: true,
    });
    }
</script>
