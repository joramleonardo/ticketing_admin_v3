<script setup>
    import { Link } from '@inertiajs/vue3';
</script>


<template>
    <div
        class="relative flex flex-col min-w-0 break-words bg-white w-full mb-6 shadow-lg rounded"
    >
        <div class="rounded-t mb-0 px-4 py-3 border-0">
            <div class="flex flex-wrap items-center">
                <div class="relative w-full px-4 max-w-full flex-grow flex-1">
                    <h3 class="font-semibold text-base text-blueGray-700">
                        My Tickets
                    </h3>
                </div>
                <div
                    class="relative w-full px-4 max-w-full flex-grow flex-1 text-right"
                >
                    <Link :href="route('admin.my-tickets')">
                        <button
                            class="bg-blue-900 text-white hover:bg-blue-100 hover:text-blue-700 text-xs font-bold uppercase px-3 py-1 rounded outline-none focus:outline-none mr-1 mb-1 ease-linear transition-all duration-150"
                            type="button"
                        >
                            See all
                        </button>
                      </Link>
                </div>
            </div>
        </div>
        <div class="block w-full overflow-x-auto">
            <!-- Projects table -->
            <table class="items-center w-full bg-transparent border-collapse">
                <thead class="thead-light">
                    <tr>
                        <th
                            class="px-6 bg-blueGray-50 text-blueGray-500 align-middle border border-solid border-blueGray-100 py-3 text-xs uppercase border-l-0 border-r-0 whitespace-nowrap font-semibold text-left"
                        >
                            Client Name
                        </th>
                        <th
                            class="px-6 bg-blueGray-50 text-blueGray-500 align-middle border border-solid border-blueGray-100 py-3 text-xs uppercase border-l-0 border-r-0 whitespace-nowrap font-semibold text-left"
                        >
                            Reference No.
                        </th>
                        <th
                            class="px-6 bg-blueGray-50 text-blueGray-500 align-middle border border-solid border-blueGray-100 py-3 text-xs uppercase border-l-0 border-r-0 whitespace-nowrap font-semibold text-left"
                        >
                            Status
                        </th>
                        <!-- <th
                            class="px-6 bg-blueGray-50 text-blueGray-500 align-middle border border-solid border-blueGray-100 py-3 text-xs uppercase border-l-0 border-r-0 whitespace-nowrap font-semibold text-left min-w-140-px"
                        ></th> -->
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <th
                        class="border-t-0 px-6 align-middle border-l-0 border-r-0 text-xs whitespace-nowrap p-4 text-left"
                        >
                        Technical Support
                        </th>
                        <td
                        class="border-t-0 px-6 align-middle border-l-0 border-r-0 text-xs whitespace-nowrap p-4"
                        >
                        000-000-00
                        </td>
                        <td
                        class="border-t-0 px-6 align-middle border-l-0 border-r-0 text-xs whitespace-nowrap p-4"
                        >
                        <div class="flex items-center">
                            <div class="relative w-full">
                            <div
                                class="overflow-hidden h-2 text-xs flex rounded bg-red-200"
                            >
                                <div
                                style="width: 20%;"
                                class="shadow-none flex flex-col text-center whitespace-nowrap text-white justify-center bg-red-500"
                                ></div>
                            </div>
                            </div>
                        </div>
                        </td>
                    </tr>
                    <tr>
                        <th
                        class="border-t-0 px-6 align-middle border-l-0 border-r-0 text-xs whitespace-nowrap p-4 text-left"
                        >
                        Technical Support
                        </th>
                        <td
                        class="border-t-0 px-6 align-middle border-l-0 border-r-0 text-xs whitespace-nowrap p-4"
                        >
                        000-000-00
                        </td>
                        <td
                        class="border-t-0 px-6 align-middle border-l-0 border-r-0 text-xs whitespace-nowrap p-4"
                        >
                        <div class="flex items-center">
                            <div class="relative w-full">
                            <div
                                class="overflow-hidden h-2 text-xs flex rounded bg-yellow-200"
                            >
                                <div
                                style="width: 50%;"
                                class="shadow-none flex flex-col text-center whitespace-nowrap text-white justify-center bg-yellow-500"
                                ></div>
                            </div>
                            </div>
                        </div>
                        </td>
                    </tr>
                    <tr>
                        <th
                        class="border-t-0 px-6 align-middle border-l-0 border-r-0 text-xs whitespace-nowrap p-4 text-left"
                        >
                        Technical Support
                        </th>
                        <td
                        class="border-t-0 px-6 align-middle border-l-0 border-r-0 text-xs whitespace-nowrap p-4"
                        >
                        000-000-00
                        </td>
                        <td
                        class="border-t-0 px-6 align-middle border-l-0 border-r-0 text-xs whitespace-nowrap p-4"
                        >
                        <div class="flex items-center">
                            <div class="relative w-full">
                            <div
                                class="overflow-hidden h-2 text-xs flex rounded bg-red-200"
                            >
                                <div
                                style="width: 100%;"
                                class="shadow-none flex flex-col text-center whitespace-nowrap text-white justify-center bg-green-500"
                                ></div>
                            </div>
                            </div>
                        </div>
                        </td>
                    </tr>
                    <tr>
                        <th
                        class="border-t-0 px-6 align-middle border-l-0 border-r-0 text-xs whitespace-nowrap p-4 text-left"
                        >
                        Technical Support
                        </th>
                        <td
                        class="border-t-0 px-6 align-middle border-l-0 border-r-0 text-xs whitespace-nowrap p-4"
                        >
                        000-000-00
                        </td>
                        <td
                        class="border-t-0 px-6 align-middle border-l-0 border-r-0 text-xs whitespace-nowrap p-4"
                        >
                        <div class="flex items-center">
                            <div class="relative w-full">
                            <div
                                class="overflow-hidden h-2 text-xs flex rounded bg-red-200"
                            >
                                <div
                                style="width: 20%;"
                                class="shadow-none flex flex-col text-center whitespace-nowrap text-white justify-center bg-red-500"
                                ></div>
                            </div>
                            </div>
                        </div>
                        </td>
                    </tr>
                    <tr>
                        <th
                        class="border-t-0 px-6 align-middle border-l-0 border-r-0 text-xs whitespace-nowrap p-4 text-left"
                        >
                        Technical Support
                        </th>
                        <td
                        class="border-t-0 px-6 align-middle border-l-0 border-r-0 text-xs whitespace-nowrap p-4"
                        >
                        000-000-00
                        </td>
                        <td
                        class="border-t-0 px-6 align-middle border-l-0 border-r-0 text-xs whitespace-nowrap p-4"
                        >
                        <div class="flex items-center">
                            <div class="relative w-full">
                            <div
                                class="overflow-hidden h-2 text-xs flex rounded bg-yellow-200"
                            >
                                <div
                                style="width: 50%;"
                                class="shadow-none flex flex-col text-center whitespace-nowrap text-white justify-center bg-yellow-500"
                                ></div>
                            </div>
                            </div>
                        </div>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</template>
