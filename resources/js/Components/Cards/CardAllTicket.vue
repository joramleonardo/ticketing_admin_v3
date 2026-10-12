<script setup>
    import { ref, watch, computed, onMounted   } from 'vue';
    import { Link, router } from '@inertiajs/vue3';
    import Modal from "@/Components/Modal.vue";
    import Spinner from "@/Components/Spinner.vue";
    import CreateTicket from '@/Pages/TicketsV2/CreateTicket.vue'

    import { useToast } from 'vue-toastification'
    const toast = useToast()

    const showCreateModal = ref(false)

    function closeCreateModal() {
        showCreateModal.value = false
    }



    defineProps({
        tickets: Object
    });

    const showModal = ref(false);
    const selectedTicket = ref(null);

    function closeModal() {
        showModal.value = false;
        selectedTicket.value = null;
    }


    const showEditModal = ref(false);
    const editForm = ref({
        id: null,
        status: '',
        assignedStaff: '',
        problem: ''
    });

    function closeEditModal() {
        showEditModal.value = false;
    }

    watch(showEditModal, (newVal) => {
        if (newVal && selectedTicket.value) {
            const status = selectedTicket.value.status || {};
            editForm.value = {
                id: status.id || null,
                status: status.status || '',
                assignedStaff: status.assignedStaff || '',
                problem: status.problem_that_needed_support || 'No description provided'
            };
            staffSearch.value = status.assignedStaff || '';
        }
    });

    const updateTicket = async () => {
        const id = editForm.value?.id;

        if (!id) {
            console.error('No ticket selected for update');
            return;
        }

        await router.put(`/tickets/${id}`, {
                status: editForm.value.status,
                assignedStaff: editForm.value.assignedStaff,
                problem_that_needed_support: editForm.value.problem
            }, {
            onSuccess: () => {
                showEditModal.value = false;
                showModal.value = false;
                toast.success('Ticket updated successfully!')
            },
            onError: (err) => {
                console.error('Update failed', err);
                toast.error('Ticket update failed!')
            }
        });
    };

    const activityLogs = ref([]);

    const fetchActivityLogs = async (refCode) => {
        isLoadingLogs.value = true;
            try {
                const res = await fetch(`/tickets/logs/${refCode}`);
                activityLogs.value = await res.json();
            } catch (err) {
                console.error('Failed to fetch activity logs', err);
            } finally {
                isLoadingLogs.value = false;
            }
    };

    function getActivityDescription(id) {
        const map = {
            1: 'logged in',
            2: 'logged out',
            3: 'created a ticket',
            4: 'assigned a ticket',
            5: 'attended a ticket',
            6: 'completed a ticket',
            7: 'added a remark',
            8: 'edited ticket details',
            9: 'submitted a rating',
            10: 'copied CSM link'
        };
        return map[id] || 'performed an action';
    }

    const isLoadingLogs = ref(false);

    const staffList = [
        'Amparado, Etienne Wayne',
        'Basaya, Mc Gyver',
        'Binondo, Michael',
        'Cagadas, John Michael',
        'Francisco, Chester',
        'Sebastian, Aerone Dominhick',
        'Leonardo, Joram Kate',
        'Mandapat, Lloyd',
        'Rabanera, Jomar',
        'Sison, Mark Jayson',
    ];

    const groupedStaff = {
        'Technical Support': [
            'Michael Binondo',
            'Aerone Dominhick Sebastian',
            'Lloyd Mandapat'
        ],
        'Network Administrator': [
            'Mark Jayson Sison'
        ],
        'System Management': [
            'Etienne Wayne Amparado',
            'John Michael Cagadas',
            'Chester Francisco',
            'Joram Kate Leonardo'
        ],
        'STARBOOKS': [
            'Mc Gyver Basaya',
            'Jomar Rabanera'
        ]
    };

    const staffSearch = ref('');
    const showStaffDropdown = ref(false);

    const filteredStaff = computed(() => {
        return staffList.filter(staff =>
            staff.toLowerCase().includes(staffSearch.value.toLowerCase())
        );
    });

    // Optional: close dropdown when clicking outside
    onMounted(() => {
        document.addEventListener('click', (e) => {
            if (!e.target.closest('.relative')) {
            showStaffDropdown.value = false;
            }
        });
    });

    const showAssignModal = ref(false);

    function closeAssignModal() {
        showAssignModal.value = false;
    }

    const selectedStaff = ref('');
    const selectedSupportType = ref('');

    function selectStaff(name) {
        staffSearch.value = name;
        showStaffDropdown.value = false;

        if (showEditModal.value) {
            editForm.value.assignedStaff = name;
        } else {
            selectedStaff.value = name;
        }
    }

    function openModal(ticket) {
        selectedTicket.value = ticket;

        // Handle missing status
        if (!ticket.status) {
            console.warn("Ticket has no status, skipping modal open.");
            return;
        }

        fetchActivityLogs(ticket.reference_code);

        // If status is New, open assign modal instead
        if (ticket.status?.status === 'New') {
            showAssignModal.value = true;
        } else {
            showModal.value = true;
        }
    }

    async function assignStaff() {
        const ticketId = selectedTicket.value?.status?.id || selectedTicket.value?.id;

        if (!ticketId || !staffSearch.value || !selectedSupportType.value) {
            toast.warning('Please select both staff and support type.');
            return;
        }

        await router.put(`/tickets/${ticketId}`, {
            assignedStaff: selectedStaff.value,
            supportType_: selectedSupportType.value,
            status: 'Pending', // Move to Pending after assignment
        }, {
            onSuccess: () => {
                showAssignModal.value = false;
                showModal.value = false;
                toast.success('Ticket assigned successfully!')
            },
            onError: (err) => {
                toast.error('Assignment failed!')

            }
        });
    }

const showCompleteModal = ref(false);
const completionForm = ref({
    nature: '',
    subCategory: '',
    supportType: '',
    assistedBy: [],
    problem: '',
    actionTaken: '',
    remarks: ''
});

const subcategories = {
    'Hardware': ['Troubleshooting', 'Evaluation', 'Network'],
    'Software': ['Installation', 'Data Retrieval', 'Download'],
    'Hardware & Software': ['Troubleshooting', 'Evaluation', 'Network', 'Installation', 'Data Retrieval', 'Download']
};

function openCompleteModal() {
    const status = selectedTicket.value?.status || {};

    completionForm.value.problem = status.problem_that_needed_support || '';
    completionForm.value.supportType = status.supportType_ || '';
    showCompleteModal.value = true;
}

function closeCompleteModal() {
    showCompleteModal.value = false;
}

async function submitCompletion() {
    const ticketId = selectedTicket.value?.status?.id;
    if (!ticketId) return;

    await router.put(`/tickets/${ticketId}`, {
        status: 'Completed',
        problem_that_needed_support: completionForm.value.problem,
        supportType_: completionForm.value.supportType,
        nature_of_support: completionForm.value.nature,
        sub_category: completionForm.value.subCategory || null,
        assisted_by: completionForm.value.assistedBy.join(', '),
        action_taken: completionForm.value.actionTaken,
        remarks: completionForm.value.remarks
    }, {
        onSuccess: () => {
            toast.success('Ticket marked as completed!');
            showCompleteModal.value = false;
            showEditModal.value = false;
            showModal.value = false;
        },
        onError: () => {
            toast.error('Failed to mark ticket as complete.');
        }
    });
}



</script>


<template>
    <div class="relative flex flex-col min-w-0 break-words bg-white w-full mb-6 shadow-lg rounded">
        <div class="rounded-t mb-0 px-4 py-3 border-0">
            <div class="flex flex-wrap items-center">
                <div class="relative w-full px-4 max-w-full flex-grow flex-1">
                    <h3 class="font-semibold text-base text-blueGray-700">
                        All Tickets
                    </h3>


                    <!-- Create Ticket Button -->
                    <button
                        @click="showCreateModal = true"
                        class="inline-flex items-center px-4 py-2 text-sm font-medium text-white bg-blue-900 border border-blue-900 rounded shadow hover:bg-blue-100 hover:text-blue-900 hover:border-blue-700 transition duration-200"
                    >
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                        </svg>
                        Create Ticket
                    </button>
                </div>

            </div>
        </div>
        <div class="block w-full overflow-x-auto">
            <table class="items-center w-full bg-transparent border-collapse">
                <thead>
                    <tr>
                        <th class="px-6 bg-blueGray-50 text-blueGray-500 align-middle border border-solid border-blueGray-100 py-3 text-xs uppercase border-l-0 border-r-0 whitespace-nowrap font-semibold text-left">
                            <label class="flex items-center space-x-3">
                                <input type="checkbox" class="form-checkbox h-4 w-4 text-blue-900 transition duration-150 ease-in-out">
                            </label>
                        </th>
                        <th class="px-6 bg-blueGray-50 text-blueGray-500 align-middle border border-solid border-blueGray-100 py-3 text-xs uppercase border-l-0 border-r-0 whitespace-nowrap font-semibold text-left">

                            Details
                        </th>
                        <th class="px-6 bg-blueGray-50 text-blueGray-500 align-middle border border-solid border-blueGray-100 py-3 text-xs uppercase border-l-0 border-r-0 whitespace-nowrap font-semibold text-left">

                            Status
                        </th>
                        <th class="px-6 bg-blueGray-50 text-blueGray-500 align-middle border border-solid border-blueGray-100 py-3 text-xs uppercase border-l-0 border-r-0 whitespace-nowrap font-semibold text-left">

                            Assigned Staff
                        </th>
                        <th class="px-6 bg-blueGray-50 text-blueGray-500 align-middle border border-solid border-blueGray-100 py-3 text-xs uppercase border-l-0 border-r-0 whitespace-nowrap font-semibold text-left">

                            Action
                        </th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="ticket in tickets.data" :key="ticket.id">
                        <td class="border-t-0 px-6 py-4">
                            <input type="checkbox" />
                        </td>
                        <td class="border-t-0 px-6 py-4 text-sm text-gray-700">
                            <div>
                                <span class="text-gray-500 font-medium">({{ ticket.reference_code }})</span>
                                <span
                                class="font-semibold text-gray-900 block max-w-xs truncate"
                                :title="ticket.status?.problem_that_needed_support"
                                >
                                    {{ ticket.status?.problem_that_needed_support || 'No Details' }}
                                </span>

                            </div>
                            <div class="text-gray-500">
                                <span class="text-blue-900 font-medium">{{ ticket.concerned_division || 'OTHERS'}}</span>
                                <span class="mx-1">·</span>
                                <span class="text-gray-600">{{ ticket.externalName }}</span>
                            </div>
                            <div class="text-xs text-gray-400">
                                Created <span class="font-bold">{{ ticket.ticket_created }}</span>
                            </div>
                        </td>
                        <td class="border-t-0 px-6 py-2 mt-8 inline-block text-xs rounded font-medium"
                            :class="{
                                        'bg-blue-200 text-blue-800': ticket.status?.status === 'New',
                                        'bg-red-200 text-red-800': ticket.status?.status === 'Pending',
                                        'bg-yellow-200 text-yellow-800': ticket.status?.status === 'In Progress',
                                        'bg-green-200 text-green-800': ticket.status?.status === 'Completed'
                                    }">
                            {{ ticket.status?.status || 'N/A' }}
                        </td>
                        <td class="border-t-0 px-6 py-4">
                            {{ ticket.status?.assignedStaff || 'Unassigned' }}
                        </td>
                        <td class="border-t-0 px-6 py-4">
                            <button @click="openModal(ticket)" class="p-2 rounded-full bg-blue-900 text-white hover:bg-blue-100 hover:text-blue-700" title="View Details">

                                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-eye-fill" viewBox="0 0 16 16">
                                    <path d="M10.5 8a2.5 2.5 0 1 1-5 0 2.5 2.5 0 0 1 5 0"/>
                                    <path d="M0 8s3-5.5 8-5.5S16 8 16 8s-3 5.5-8 5.5S0 8 0 8m8 3.5a3.5 3.5 0 1 0 0-7 3.5 3.5 0 0 0 0 7"/>
                                </svg>
                            </button>
                        </td>
                    </tr>
                </tbody>

            </table>

            <div class="flex justify-end px-6 py-4">
                <div class="flex space-x-1">
                    <template v-for="(link, index) in tickets.links" :key="index">
                        <button
                            v-if="link.url"
                            @click="$inertia.get(link.url, { preserveScroll: true })"
                            v-html="link.label"
                            class="px-3 py-1 rounded border text-sm"
                            :class="{
                                'bg-blue-900 text-white': link.active,
                                'hover:bg-blue-100 hover:text-blue-700': !link.active
                            }"
                        />
                        <span
                            v-else
                            v-html="link.label"
                            class="px-3 py-1 text-gray-400 text-sm"
                        ></span>
                    </template>
                </div>
            </div>

            <!-- Ticket Details Modal -->
            <Modal :show="showModal" @close="closeModal" >
                <div class="p-6 max-w-l">
                    <div class="flex items-center justify-between mb-6 border-b pb-2">
                        <h2 class="text-2xl font-bold text-gray-800 uppercase flex items-center gap-2">
                            <!-- SVG Icon -->
                            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" fill="currentColor" class="bi bi-file-text-fill text-gray-700" viewBox="0 0 16 16">
                            <path d="M12 0H4a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h8a2 2 0 0 0 2-2V2a2 2 0 0 0-2-2M5 4h6a.5.5 0 0 1 0 1H5a.5.5 0 0 1 0-1m-.5 2.5A.5.5 0 0 1 5 6h6a.5.5 0 0 1 0 1H5a.5.5 0 0 1-.5-.5M5 8h6a.5.5 0 0 1 0 1H5a.5.5 0 0 1 0-1m0 2h3a.5.5 0 0 1 0 1H5a.5.5 0 0 1 0-1"/>
                            </svg>
                            Ticket Details
                        </h2>

                        <!-- Buttons aligned to right of title -->
                        <div class="flex gap-2">


                            <button
                            @click="showEditModal = true"
                            class="px-4 py-2 rounded shadow-sm border-2 border-solid border-blue-900 bg-blue-900 text-white hover:bg-blue-100 hover:text-blue-700 hover:border-blue-700"
                            >
                                Edit
                            </button>
                            <button
                            @click="closeModal"
                            class="px-4 py-2 rounded shadow-sm border-2 border-solid border-gray-700 bg-gray-700 text-white hover:bg-gray-400 hover:text-gray-900 hover:border-gray-900"
                            >
                                Close
                            </button>
                        </div>
                    </div>

                    <div v-if="selectedTicket" class="grid grid-cols-1 sm:grid-cols-2 gap-4 text-sm text-gray-700">
                        <div>
                            <p class="font-semibold text-gray-600">Reference Code:</p>
                            <p class="text-gray-900">{{ selectedTicket.reference_code }}</p>
                        </div>
                        <div>
                            <p class="font-semibold text-gray-600">Concerned Division:</p>
                            <p class="text-gray-900">{{ selectedTicket.concerned_division || 'N/A' }}</p>
                        </div>

                        <div>
                            <p class="font-semibold text-gray-600">Client Name:</p>
                            <p class="text-gray-900">{{ selectedTicket.externalName || 'N/A' }}</p>
                        </div>
                        <div>
                            <p class="font-semibold text-gray-600">Status:</p>
                            <span class="inline-block px-2 py-1 text-xs rounded font-medium"
                                    :class="{
                                        'bg-blue-200 text-blue-800': selectedTicket.status?.status === 'New',
                                        'bg-red-200 text-red-800': selectedTicket.status?.status === 'Pending',
                                        'bg-yellow-200 text-yellow-800': selectedTicket.status?.status === 'In Progress',
                                        'bg-green-200 text-green-800': selectedTicket.status?.status === 'Completed'
                                    }"
                            >
                                {{ selectedTicket.status?.status || 'N/A' }}
                            </span>
                        </div>

                        <div>
                            <p class="font-semibold text-gray-600">Assigned Staff:</p>
                            <p class="text-gray-900">{{ selectedTicket.status?.assignedStaff || 'Unassigned' }}</p>
                        </div>
                        <div>
                            <p class="font-semibold text-gray-600">Created:</p>
                            <p class="text-gray-900">{{ selectedTicket.ticket_created }}</p>
                        </div>

                        <div class="sm:col-span-2">
                            <p class="font-semibold text-gray-600">Support Type:</p>
                            <p class="text-gray-900 whitespace-pre-line">
                                {{ selectedTicket.status?.supportType_ || 'No details provided.' }}
                            </p>
                        </div>

                        <div class="sm:col-span-2">
                            <p class="font-semibold text-gray-600">Problem:</p>
                            <p class="text-gray-900 whitespace-pre-line">
                                {{ selectedTicket.status?.problem_that_needed_support || 'No details provided.' }}
                            </p>
                        </div>

                        <div class="sm:col-span-2">
                            <button
                                type="button"
                                class="px-4 py-2 rounded shadow-sm border-2 border-green-700 bg-green-700 text-white hover:bg-green-100 hover:text-green-700 hover:border-green-700"
                                @click="openCompleteModal"
                            >
                                Mark as Complete
                            </button>
                        </div>

                    </div>

                    <div class="mt-6">
                        <h2 class="text-2xl font-bold text-gray-800 mb-6 border-b pb-2 uppercase flex items-center gap-2">
                            <!-- SVG Icon -->
                            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" fill="currentColor" class="bi bi-clock-fill text-gray-700" viewBox="0 0 16 16">
                                <path d="M16 8A8 8 0 1 1 0 8a8 8 0 0 1 16 0M8 3.5a.5.5 0 0 0-1 0V9a.5.5 0 0 0 .252.434l3.5 2a.5.5 0 0 0 .496-.868L8 8.71z"/>
                            </svg>

                            Activity Logs
                        </h2>
                            <div v-if="isLoadingLogs">
                                <Spinner />
                            </div>

                            <ul v-else-if="activityLogs.length" class="space-y-2 text-sm">
                                <li v-for="(log, index) in activityLogs" :key="index" class="border-b pb-2">
                                <p>
                                    <strong>{{ log.username || selectedTicket.externalName || 'Unknown User' }}</strong>
                                    {{ getActivityDescription(log.activity_id) }}
                                    <span class="text-gray-500 text-xs">({{ log.activity_date }})</span>
                                </p>
                                </li>
                            </ul>

                            <p v-else class="text-gray-400 italic text-sm">No activity logs found.</p>
                    </div>
                </div>
            </Modal>

            <!-- Edit Ticket Modal -->
            <Modal :show="showEditModal" @close="closeEditModal" maxWidth="sm">
                <div class="p-4 max-w-sm w-full">
                    <h2 class="text-2xl font-bold text-gray-800 mb-6 border-b pb-2 flex items-center gap-2">
                        <!-- Icon -->
                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-pencil-fill" viewBox="0 0 16 16">
                            <path d="M12.854.146a.5.5 0 0 0-.707 0L10.5 1.793 14.207 5.5l1.647-1.646a.5.5 0 0 0 0-.708zm.646 6.061L9.793 2.5 3.293 9H3.5a.5.5 0 0 1 .5.5v.5h.5a.5.5 0 0 1 .5.5v.5h.5a.5.5 0 0 1 .5.5v.5h.5a.5.5 0 0 1 .5.5v.207zm-7.468 7.468A.5.5 0 0 1 6 13.5V13h-.5a.5.5 0 0 1-.5-.5V12h-.5a.5.5 0 0 1-.5-.5V11h-.5a.5.5 0 0 1-.5-.5V10h-.5a.5.5 0 0 1-.175-.032l-.179.178a.5.5 0 0 0-.11.168l-2 5a.5.5 0 0 0 .65.65l5-2a.5.5 0 0 0 .168-.11z"/>
                        </svg>
                        Edit Ticket
                    </h2>

                    <form @submit.prevent="updateTicket" class="space-y-5">
                        <!-- Status -->
                        <div>
                            <label class="block font-semibold mb-1 text-gray-700">Status</label>
                            <select v-model="editForm.status" class="w-full border rounded px-3 py-2 text-sm focus:ring focus:ring-blue-200">
                                <option value="Unassigned">Unassigned</option>
                                <option value="Pending">Pending</option>
                                <option value="In Progress">In Progress</option>
                                <option value="Completed">Completed</option>
                            </select>
                        </div>

                        <!-- Enhanced Assigned Staff Dropdown with Grouping -->
                        <div class="relative" ref="staffDropdownRef">
                            <label class="block font-semibold mb-1 text-gray-700">Assigned Staff</label>
                            <input
                                type="text"
                                v-model="staffSearch"
                                placeholder="Search staff..."
                                class="w-full border rounded px-3 py-2 text-sm focus:ring focus:ring-blue-200"
                                @focus="showStaffDropdown = true"
                                @input="showStaffDropdown = true"
                                @click="showStaffDropdown = true"
                            />
                            <ul
                                v-if="showStaffDropdown"
                                class="absolute z-10 w-full mt-1 bg-white border border-gray-300 rounded shadow max-h-52 overflow-y-auto text-sm"
                            >
                                <template v-for="(group, groupName) in groupedStaff" :key="groupName">
                                <li class="px-4 py-2 font-semibold text-gray-600 border-t bg-gray-100">{{ groupName }}</li>
                                <li
                                    v-for="staff in group"
                                    :key="staff"
                                    v-show="staff.toLowerCase().includes(staffSearch.toLowerCase())"
                                    @click="selectStaff(staff)"
                                    class="px-4 py-2 hover:bg-blue-100 cursor-pointer"
                                >
                                    {{ staff }}
                                </li>
                                </template>
                            </ul>
                        </div>


                        <!-- Problem Description -->
                        <div>
                            <label class="block font-semibold mb-1 text-gray-700">Problem Description</label>
                            <textarea
                            v-model="editForm.problem"
                            rows="3"
                            class="w-full border rounded px-3 py-2 text-sm focus:ring focus:ring-blue-200 resize-none"
                            >
                            </textarea>
                        </div>

                        <!-- Actions -->
                        <div class="pt-4 text-right">


                            <button
                            type="submit"
                            class="px-4 py-2 rounded shadow-sm border-2 border-solid border-blue-900 bg-blue-900 text-white hover:bg-blue-100 hover:text-blue-700 hover:border-blue-700"
                            >
                                Save Changes
                            </button>


                        </div>
                    </form>
                </div>
            </Modal>

            <!-- Assign Staff Modal for 'New' Tickets -->
            <Modal :show="showAssignModal" @close="closeAssignModal" maxWidth="sm">
                <div class="p-4 max-w-sm w-full">
                    <h2 class="text-2xl font-bold text-gray-800 mb-6 border-b pb-2 flex items-center gap-2">
                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-person-plus-fill" viewBox="0 0 16 16">
                            <path d="M1 14s-1 0-1-1 1-4 6-4 6 3 6 4-1 1-1 1zm5-6a3 3 0 1 0 0-6 3 3 0 0 0 0 6"/>
                            <path fill-rule="evenodd" d="M13.5 5a.5.5 0 0 1 .5.5V7h1.5a.5.5 0 0 1 0 1H14v1.5a.5.5 0 0 1-1 0V8h-1.5a.5.5 0 0 1 0-1H13V5.5a.5.5 0 0 1 .5-.5"/>
                        </svg>

                        Assign Staff
                    </h2>

                    <!-- Ticket Info Section -->
                    <div class="grid grid-cols-1  gap-4 text-sm text-gray-700 border-b mb-3 pb-2">
                        <div>
                            <p class="font-semibold text-gray-600">Reference Code:</p>
                            <p class="text-gray-900">{{ selectedTicket?.reference_code }}</p>
                        </div>
                        <div>
                            <p class="font-semibold text-gray-600">Client Name:</p>
                            <p class="text-gray-900">{{ selectedTicket.externalName || 'N/A' }}</p>
                        </div>

                        <div class="sm:col-span-2">
                            <p class="font-semibold text-gray-600">Support Type:</p>
                            <p class="text-gray-900 whitespace-pre-line">
                                {{ selectedTicket.status?.supportType_ || 'No details provided.' }}
                            </p>
                        </div>

                        <div>
                            <p class="font-semibold text-gray-600">Problem:</p>
                            <p class="text-gray-900">{{ selectedTicket?.status?.problem_that_needed_support || 'N/A' }}</p>
                        </div>

                    </div>


                    <form @submit.prevent="assignStaff">

                        <!-- Staff Dropdown -->
                        <div class="relative mb-4">
                            <label class="block font-semibold mb-1 text-gray-700">Assign to</label>
                            <input
                                type="text"
                                v-model="staffSearch"
                                placeholder="Search staff..."
                                class="w-full border rounded px-3 py-2 text-sm focus:ring focus:ring-blue-200"
                                @focus="showStaffDropdown = true"
                                @input="showStaffDropdown = true"
                            />
                            <ul
                                v-if="showStaffDropdown && filteredStaff.length"
                                class="absolute z-10 w-full mt-1 bg-white border border-gray-300 rounded shadow max-h-40 overflow-y-auto text-sm"
                            >
                                <li
                                    v-for="(staff, index) in filteredStaff"
                                    :key="index"
                                    @click="selectStaff(staff)"
                                    class="px-4 py-2 hover:bg-blue-100 cursor-pointer"
                                >
                                    {{ staff }}
                                </li>
                            </ul>
                        </div>

                        <!-- Support Type Dropdown -->
                        <div class="mb-4">
                            <label class="block font-semibold mb-1 text-gray-700">Support Type</label>
                            <select v-model="selectedSupportType" class="w-full border rounded px-3 py-2 text-sm">
                                <option disabled value="">Select support type</option>
                                <option value="Technical Support">Technical Support</option>
                                <option value="Livestream">Livestream</option>
                            </select>
                        </div>

                        <!-- Submit -->
                        <div class="text-right pt-4">

                            <button type="submit" class="px-4 py-2 rounded shadow-sm border-2 border-solid border-blue-900 bg-blue-900 text-white hover:bg-blue-100 hover:text-blue-700 hover:border-blue-700">
                                Assign
                            </button>
                        </div>

                    </form>
                </div>
            </Modal>

            <!-- Mark Ticket as Complete Modal -->
            <Modal :show="showCompleteModal" @close="closeCompleteModal">
                <div class="p-6 w-full max-w-l">
                    <h2 class="text-xl font-bold mb-4 text-gray-800 border-b pb-2">Complete Ticket</h2>

                    <form @submit.prevent="submitCompletion">

                        <!-- 1. Nature of Support -->
                        <div class="mb-4">
                            <label class="block font-semibold mb-1">Nature of Support</label>
                            <select v-model="completionForm.nature" class="w-full border rounded px-3 py-2 text-sm">
                                <option disabled value="">Select</option>
                                <option>Hardware</option>
                                <option>Software</option>
                                <option>Hardware & Software</option>
                                <option>System Modification</option>
                            </select>
                        </div>

                        <!-- 2. Subcategory (conditional) -->
                        <div v-if="completionForm.nature !== 'System Modification'" class="mb-4">
                            <label class="block font-semibold mb-1">Sub Category</label>
                            <select v-model="completionForm.subCategory" class="w-full border rounded px-3 py-2 text-sm">
                                <option disabled value="">Select</option>
                                <option v-for="sub in subcategories[completionForm.nature]" :key="sub">{{ sub }}</option>
                            </select>
                        </div>

                        <!-- 3. Support Type -->
                        <div class="mb-4">
                            <label class="block font-semibold mb-1">Support Type</label>
                            <select v-model="completionForm.supportType" class="w-full border rounded px-3 py-2 text-sm">
                                <option>Technical Support</option>
                                <option>Livestream</option>
                            </select>
                        </div>

                        <!-- 4. Assisted By (checkbox multi-select) -->
                        <div class="mb-4">
                            <label class="block font-semibold mb-1">Assisted By</label>
                            <div class="grid grid-cols-2 gap-2 text-sm">
                                <label v-for="staff in staffList" :key="staff" class="flex items-center gap-2">
                                    <input type="checkbox" :value="staff" v-model="completionForm.assistedBy" />
                                    {{ staff }}
                                </label>
                            </div>
                        </div>

                        <!-- 5. Problem -->
                        <div class="mb-4">
                            <label class="block font-semibold mb-1">Problem</label>
                            <textarea v-model="completionForm.problem" rows="2" class="w-full border rounded px-3 py-2 text-sm"></textarea>
                        </div>

                        <!-- 6. Action Taken -->
                        <div class="mb-4">
                            <label class="block font-semibold mb-1">Describe Action Taken</label>
                            <textarea v-model="completionForm.actionTaken" rows="3" class="w-full border rounded px-3 py-2 text-sm"></textarea>
                        </div>

                        <!-- 7. Remarks -->
                        <div class="mb-4">
                            <label class="block font-semibold mb-1">Remarks</label>
                            <textarea v-model="completionForm.remarks" rows="2" class="w-full border rounded px-3 py-2 text-sm"></textarea>
                        </div>

                        <!-- Submit -->
                        <div class="text-right">
                            <button type="submit" class="bg-green-700 hover:bg-green-800 text-white px-4 py-2 rounded">
                                Confirm Completion
                            </button>
                        </div>
                    </form>
                </div>
            </Modal>

            <Modal :show="showCreateModal" @close="closeCreateModal" maxWidth="2xl">
                <CreateTicket @success="closeCreateModal" />
            </Modal>




        </div>
    </div>
</template>
