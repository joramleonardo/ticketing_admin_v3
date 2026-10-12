<script setup>
    import { ref, onMounted, onBeforeUnmount, nextTick, watch, computed } from 'vue'
    import { router } from '@inertiajs/vue3'
    import axios from 'axios'

    import { useToast } from 'vue-toastification'
    const toast = useToast()

    const emit = defineEmits(['success'])

    const employees = ref([])
    const selectedEmployee = ref(null)

    const form = ref({
        client_type: '',
        client_name: '',
        client_division_agency: '',
        client_empID: '',
        client_sex: '',


        // Ticket Details Fields
        ticket_type: '',
        ticket_nature_of_support: '',
        ticket_support_type: '',
        ticket_attended_by: '',
        ticket_assisted_by: [],
        ticket_problem_that_needed_support: '',
        ticket_actions_taken: '',
        ticket_remarks: ''
    })

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

    const errors = ref({})

    // Fetch employees
    const fetchEmployees = async () => {
        try {
            const res = await axios.get('/ticket-employees')
            employees.value = res.data
            console.log('Employees fetched successfully', employees.value)
        } catch (error) {
            console.error('Failed to fetch employees', error)
        }
    }

    // Auto-fill based on selected name
    watch(() => form.value.client_name, (name) => {
        const emp = employees.value.find(e => e.full_name === name)
        if (emp) {
            selectedEmployee.value = emp
            form.value.client_division_agency = emp.division_id
            form.value.client_empID = emp.employee_code
            form.value.client_sex = emp.sex
        }
    })

    watch(() => form.value.client_type, (newType) => {
        if (newType === 'External') {
            form.value.client_empID = 'None'
            form.value.client_sex = '' // Optional: reset previous selection
            selectedEmployee.value = null // Clear internal user data
        }
    })

    watch(() => form.value.ticket_nature_of_support, (val) => {
        if (val === 'Others') {
            form.value.ticket_support_type = 'NA'; // auto set to NA
        } else {
            form.value.ticket_support_type = ''; // reset for other selections
        }
    });

    onMounted(fetchEmployees)

    const submit = () => {
        // Prepare a copy of the form with assisted_by converted to string
        const payload = {
            ...form.value,
            ticket_assisted_by: form.value.ticket_assisted_by.length
                ? form.value.ticket_assisted_by.join(', ')
                : 'None' // ✅ Default to "none" if no names are selected
        }

        router.post('/admin/tickets-v2', payload, {
            onError: (err) => {
                errors.value = err
            },
            onSuccess: () => {
                emit('success')
                // alert('Ticket created successfullyss!')
                toast.success('Ticket created successfully!')
                errors.value = {}

                // Reset form
                form.value = {
                    client_type: '',
                    client_name: '',
                    client_division_agency: '',
                    client_empID: '',
                    client_sex: '',
                    ticket_attended_by: '',
                    ticket_assisted_by: []
                }
            }
        })
    }

    const attendedBySearch = ref('')
    const showAttendedDropdown = ref(false)

    const filteredAttendees = computed(() => {
        if (!attendedBySearch.value.trim()) return Object.entries(groupedStaff)
        return Object.entries(groupedStaff).map(([group, names]) => {
            const filteredNames = names.filter(name =>
                name.toLowerCase().includes(attendedBySearch.value.toLowerCase())
            )
            return [group, filteredNames]
        }).filter(([, names]) => names.length > 0)
    })

    const clientNameSearch = ref('')
    const showClientDropdown = ref(false)

    const selectClient = (emp) => {
        form.value.client_name = emp.full_name;
        form.value.client_division_agency = emp.division_id;
        form.value.client_empID = emp.employee_code;
        form.value.client_sex = emp.sex;
        selectedEmployee.value = emp;
        showClientDropdown.value = false;
    }

    const natureOptions = [
        'Hardware',
        'Software',
        'Hardware & Software',
        'System Modification',
        'Others'
    ];

    const supportTypeOptions = {
        Hardware: ['Troubleshooting', 'Evaluation', 'Network', 'Testing', 'Consultation', 'Assistance'],
        Software: ['Installation', 'Data retrieval', 'Virus/malwares', 'Data conversion/uploading/processing', 'Reformat', 'Download', 'Assistance'],
        'Hardware & Software': [
            'Troubleshooting', 'Evaluation', 'Network', 'Testing', 'Consultation', 'Assistance',
            'Installation', 'Data retrieval', 'Virus/malwares', 'Data conversion/uploading/processing',
            'Reformat', 'Download'
        ],
        'System Modification': [] // Optional: no support type required
    };

    const filteredSupportTypes = computed(() => {
        return supportTypeOptions[form.value.ticket_nature_of_support] || [];
    });

    onMounted(() => {
        document.addEventListener('click', handleClickOutside)
    })

    const clientDropdownRef = ref(null)
    const clientSearchInput = ref(null)

    const toggleClientDropdown = async () => {
        showClientDropdown.value = !showClientDropdown.value
        if (showClientDropdown.value) {
            clientNameSearch.value = ''
            await nextTick()
            clientSearchInput.value?.focus()
        }
    }

    const handleClickOutside = (e) => {
        const el = clientDropdownRef.value
        if (el && !el.contains(e.target)) showClientDropdown.value = false
    }

    onMounted(() => document.addEventListener('click', handleClickOutside))
    onBeforeUnmount(() => document.removeEventListener('click', handleClickOutside))

    // keep your filteredEmployees, but make sure it handles blanks safely
    const filteredEmployees = computed(() => {
        const q = clientNameSearch.value.trim().toLowerCase()
        if (!q) return employees.value
        return employees.value.filter(emp =>
            (emp.full_name || '').toLowerCase().includes(q)
        )
    })

</script>


<template>
    <div class="max-h-[80vh] overflow-y-auto p-6">
        <h1 class="text-2xl font-bold text-blue-900 mb-6 border-b pb-2 flex items-center gap-2">
            <!-- Icon -->
            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-pencil-fill" viewBox="0 0 16 16">
                <path d="M12.854.146a.5.5 0 0 0-.707 0L10.5 1.793 14.207 5.5l1.647-1.646a.5.5 0 0 0 0-.708zm.646 6.061L9.793 2.5 3.293 9H3.5a.5.5 0 0 1 .5.5v.5h.5a.5.5 0 0 1 .5.5v.5h.5a.5.5 0 0 1 .5.5v.5h.5a.5.5 0 0 1 .5.5v.207zm-7.468 7.468A.5.5 0 0 1 6 13.5V13h-.5a.5.5 0 0 1-.5-.5V12h-.5a.5.5 0 0 1-.5-.5V11h-.5a.5.5 0 0 1-.5-.5V10h-.5a.5.5 0 0 1-.175-.032l-.179.178a.5.5 0 0 0-.11.168l-2 5a.5.5 0 0 0 .65.65l5-2a.5.5 0 0 0 .168-.11z"/>
            </svg>
            Create Late Ticket
        </h1>

        <form @submit.prevent="submit" class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <!-- Client Type -->
            <div>
                <label class="block text-sm font-medium">Client Type</label>
                <select v-model="form.client_type" class="w-full border px-3 py-2 rounded">
                    <option disabled value="">Select type</option>
                    <option>Internal</option>
                    <option>External</option>
                </select>
                <div v-if="errors.client_type" class="text-red-500 text-sm">{{ errors.client_type }}</div>
            </div>

            <!-- Client Name (Searchable Dropdown) -->
            <div>
                <label class="block text-sm font-medium mb-1">Client Name</label>

                <!-- If Internal, show searchable dropdown -->
                <div v-if="form.client_type === 'Internal'" class="relative" ref="clientDropdownRef" >
                    <div
                        @click="toggleClientDropdown"
                        class="w-full border px-3 py-2 rounded cursor-pointer bg-white relative z-10"
                    >
                        {{ selectedEmployee?.full_name || 'Select Employee' }}
                    </div>

                    <div v-if="showClientDropdown" class="absolute bg-white border w-full mt-1 rounded shadow-md z-20" @click.stop >
                        <!-- keep clicks inside -->
                        <input
                            ref="clientSearchInput"
                            v-model="clientNameSearch"
                            type="text"
                            placeholder="Search..."
                            class="w-full border-b px-3 py-2 text-sm outline-none"
                        />
                        <ul class="max-h-60 overflow-y-auto">
                            <li
                            v-for="emp in filteredEmployees"
                            :key="emp.id"
                            @click="selectClient(emp)"
                            class="px-3 py-1 hover:bg-blue-100 cursor-pointer text-sm"
                            >
                            {{ emp.full_name }}
                            </li>
                        </ul>
                    </div>
                </div>

                <!-- If External, show text input -->
                <div v-else>
                    <input v-model="form.client_name" type="text" placeholder="Enter client name"
                        class="w-full border px-3 py-2 rounded" />
                </div>
                <div v-if="errors.client_name" class="text-red-500 text-sm mt-1">{{ errors.client_name }}</div>
            </div>

            <!-- Employee ID and Sex -->
            <div class="md:col-span-2 text-sm">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <!-- Employee ID -->
                    <div>
                        <label class="block text-sm font-medium mb-1">Employee ID</label>
                        <input
                            v-model="form.client_empID"
                            type="text"
                            disabled
                            class="w-full border px-3 py-2 rounded bg-gray-100 text-gray-600"
                        />
                    </div>

                    <!-- Sex -->
                    <div>
                        <label class="block text-sm font-medium mb-1">Sex</label>
                        <select
                            v-model="form.client_sex"
                            :disabled="form.client_type === 'Internal'"
                            class="w-full border px-3 py-2 rounded bg-white disabled:bg-gray-100 disabled:text-gray-500"
                        >
                            <option disabled value="">Select sex</option>
                            <option>Male</option>
                            <option>Female</option>
                            <option>Prefer not to say</option>
                        </select>
                        <div v-if="errors.client_sex" class="text-red-500 text-sm mt-1">{{ errors.client_sex }}</div>
                    </div>
                </div>
            </div>

            <!-- Division/Agency Field -->
            <div class="md:col-span-2 text-sm">
                <div>
                    <label class="block text-sm font-medium mb-1">Division/Agency</label>
                    <input v-model="form.client_division_agency" type="text" placeholder="Enter division/agency"
                    :disabled="form.client_type === 'Internal'"
                    class="w-full border px-3 py-2 rounded disabled:bg-gray-100 disabled:text-gray-500"
                    />
                    <div v-if="errors.client_division_agency" class="text-red-500 text-sm mt-1">{{ errors.client_division_agency }}</div>
                </div>
            </div>

            <!-- Date Requested -->
            <div>
                <label class="block text-sm font-medium mb-1">Date Requested</label>
                <input type="date" v-model="form.date_requested" class="w-full border px-3 py-2 rounded" />
            </div>

            <!-- Date Completed -->
            <div>
                <label class="block text-sm font-medium mb-1">Date Completed</label>
                <input type="date" v-model="form.date_completed" class="w-full border px-3 py-2 rounded" />
            </div>

            <!-- Request Type -->
            <div>
                <label class="block text-sm font-medium">Request Type</label>
                <select v-model="form.ticket_type" class="w-full border px-3 py-2 rounded">
                    <option disabled value="">Select</option>
                    <option>Technical Support</option>
                    <option>Livestream</option>
                </select>
            </div>

            <!-- Nature of Support -->
            <div class="mb-4">
                <label class="block text-sm font-semibold mb-1">Nature of Support</label>
                <select v-model="form.ticket_nature_of_support" class="w-full border rounded px-3 py-2 text-sm">
                    <option disabled value="">Select</option>
                    <option v-for="option in natureOptions" :key="option" :value="option">
                        {{ option }}
                    </option>
                </select>
            </div>

            <!-- Support Type (depends on nature) -->
            <div v-if="form.ticket_nature_of_support !== 'Others'">
                <select v-model="form.ticket_support_type" class="w-full border px-2 py-1 rounded">
                    <option disabled value="">Select Support Type</option>
                    <option v-for="type in filteredSupportTypes" :key="type" :value="type">
                    {{ type }}
                    </option>
                </select>
            </div>

            <div v-else>
                <input type="text" class="w-full border px-2 py-1 rounded bg-gray-100" value="NA" readonly />
            </div>

            <!-- Attended By (Grouped Dropdown) -->
            <div class="md:col-span-2 relative">
                <label class="block text-sm font-medium mb-1">Attended By</label>

                <!-- Clickable Display -->
                <div
                    @click="showAttendedDropdown = !showAttendedDropdown"
                    class="w-full border px-3 py-2 rounded cursor-pointer bg-white"
                >
                    {{ form.ticket_attended_by || 'Select Staff' }}
                </div>

                <!-- Dropdown Menu -->
                <div
                    v-if="showAttendedDropdown"
                    class="absolute left-0 right-0 bg-white border w-full mt-1 rounded shadow-md z-50 max-h-60 overflow-y-auto"
                >
                    <input
                        v-model="attendedBySearch"
                        type="text"
                        placeholder="Search..."
                        class="w-full border-b px-3 py-2 text-sm outline-none"
                    />

                    <div
                        v-for="[group, names] in filteredAttendees"
                        :key="group"
                        class="p-2 border-b last:border-0"
                    >
                        <p class="text-xs font-semibold text-gray-500 mb-1">{{ group }}</p>
                        <ul>
                            <li
                                v-for="name in names"
                                :key="name"
                                @click="form.ticket_attended_by = name; showAttendedDropdown = false"
                                class="px-3 py-1 hover:bg-blue-100 cursor-pointer text-sm"
                            >
                                {{ name }}
                            </li>
                        </ul>
                    </div>
                </div>
            </div>

            <!-- Assisted By (Grouped Checkboxes) -->
            <div class="md:col-span-2">
                <label class="block text-sm font-medium mb-2">Assisted By</label>
                <div class="border p-3 rounded max-h-52 overflow-y-auto space-y-2 bg-gray-50">
                    <div v-for="(staffList, group) in groupedStaff" :key="group">
                        <p class="font-semibold text-sm text-gray-700">{{ group }}</p>
                        <div class="ml-4 mt-1 space-y-1">
                            <label v-for="staff in staffList" :key="staff" class="flex items-center space-x-2 text-sm">
                                <input
                                    type="checkbox"
                                    :value="staff"
                                    v-model="form.ticket_assisted_by"
                                    class="text-blue-600 border-gray-300 rounded"
                                />
                                <span>{{ staff }}</span>
                            </label>
                        </div>
                    </div>
                </div>
                <div v-if="errors.ticket_assisted_by" class="text-red-500 text-sm mt-1">{{ errors.ticket_assisted_by }}</div>
            </div>

            <!-- Problem Description -->
            <div class="md:col-span-2">
                <label class="block text-sm font-medium">Problem That Needed Support</label>
                <textarea v-model="form.ticket_problem_that_needed_support" class="w-full border px-3 py-2 rounded"></textarea>
            </div>

            <!-- Actions Taken -->
            <div class="md:col-span-2">
                <label class="block text-sm font-medium">Actions Taken</label>
                <textarea v-model="form.ticket_actions_taken" class="w-full border px-3 py-2 rounded"></textarea>
            </div>

            <!-- Remarks -->
            <div class="md:col-span-2">
                <label class="block text-sm font-medium">Remarks</label>
                <textarea v-model="form.ticket_remarks" class="w-full border px-3 py-2 rounded"></textarea>
            </div>

            <!-- Submit -->
            <div class="md:col-span-2 text-right">
                <button type="submit" class="bg-blue-900 text-white px-6 py-2 rounded hover:bg-blue-800">
                    Submit Ticket
                </button>
            </div>
        </form>

    </div>
</template>
