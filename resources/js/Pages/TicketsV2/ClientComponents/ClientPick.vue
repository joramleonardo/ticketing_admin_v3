
<script setup>
    import { ref } from "vue"
    import ClientCreate from "@/Pages/TicketsV2/ClientComponents/ClientCreate.vue"
    import ClientTrack from "@/Pages/TicketsV2/ClientComponents/ClientTrack.vue"

    // Track which view is active
    // Default tab: create
    const activeView = ref("create")
</script>


<template>
    <div class="container mx-auto px-4 h-full">
        <div class="flex content-center items-center justify-center h-full">
            <div class="w-full lg:w-6/12 px-4">
                <div
                class="relative flex flex-col min-w-0 break-words w-full mb-6 shadow-lg rounded-lg bg-blueGray-200 border-0"
                >

                    <div class="rounded-t mb-0 px-6 py-6">
                        <div class="text-center mb-3">
                            <h3 class="text-blueGray-500 font-bold">
                               TICKETING MANAGEMENT SYSTEM
                            </h3>
                        </div>
                        <!-- Tabs -->
                        <div class="flex border-b border-gray-300">
                            <button
                                class="px-4 py-2 -mb-px "
                                :class="activeView === 'create'
                                ? 'bg-lightBlue-500 text-white active:bg-lightBlue-600 font-bold uppercase text-sm px-6 py-3 hover:shadow-lg outline-none focus:outline-none'
                                : 'text-gray-500 active:bg-lightBlue-600 font-bold uppercase text-sm px-6 py-3 hover:bg-blueGray-600 hover:text-white outline-none focus:outline-none'"
                                @click="activeView = 'create'"
                            >
                                Create Ticket
                            </button>

                            <button
                                class="px-4 py-2 -mb-px "
                                :class="activeView === 'track'
                                ? 'bg-lightBlue-500 text-white active:bg-lightBlue-600 font-bold uppercase text-sm px-6 py-3 hover:shadow-lg outline-none focus:outline-none'
                                : 'text-gray-500 active:bg-lightBlue-600 font-bold uppercase text-sm px-6 py-3 hover:bg-blueGray-600 hover:text-white outline-none focus:outline-none'"
                                @click="activeView = 'track'"
                            >
                                Track Ticket
                            </button>
                        </div>
                    </div>
                    <ClientCreate v-if="activeView === 'create'" />
                    <ClientTrack v-if="activeView === 'track'" />
                    <!-- Request Form or Tracking Form -->
                    <!-- Display here-->
                </div>
            </div>
        </div>
    </div>
</template>
