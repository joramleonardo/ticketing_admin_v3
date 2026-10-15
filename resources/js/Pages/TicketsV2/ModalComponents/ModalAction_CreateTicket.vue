<script setup>
import { ref, watch, computed, onMounted } from 'vue'
import { router, usePage } from '@inertiajs/vue3'
import axios from 'axios'
import { useToast } from 'vue-toastification'

const props = defineProps({
    initialMode: {
        type: String,
        default: 'standard', // 'standard' | 'late'
        validator: (v) => ['standard', 'late'].includes(v),
    },
})

const emit = defineEmits(['success', 'created', 'close'])
const toast = useToast()
const page = usePage()

// Current creation mode
const creationMode = ref(props.initialMode || 'standard')

// Employees list for internal lookup
const employees = ref([])
const selectedEmployee = ref(null)
const employeeSearchQuery = ref('')
const showEmployeeDropdown = ref(false)

function getInitialFormData() {
    const authName = page.props?.auth?.user?.name || ''
    const nowIso = new Date().toISOString().slice(0, 16)
    return {
        // Client Details
        client_type: 'Internal',
        client_name: '',
        client_empID: '',
        client_sex: '',
        client_division_agency: '',

        // Common Ticket Details
        ticket_type: 'Technical Support',
        ticket_problem_that_needed_support: '',

        // Events Specifics
        event_start_date: '',
        event_end_date: '',
        event_venue: '',
        event_remarks: '',
        other_request: '',

        // Late Request Specifics
        ticket_nature_of_support: 'Hardware',
        ticket_support_type: 'Computer / Laptop',
        ticket_attended_by: authName,
        ticket_assisted_by: [],
        ticket_actions_taken: '',
        ticket_remarks: '',
        date_requested: nowIso,
        date_completed: nowIso,
    }
}

const form = ref(getInitialFormData())
const errors = ref({})
const isSubmitting = ref(false)

function resetForm() {
    selectedEmployee.value = null
    employeeSearchQuery.value = ''
    showEmployeeDropdown.value = false
    errors.value = {}
    isSubmitting.value = false
    Object.assign(form.value, getInitialFormData())
}

function setCreationMode(mode) {
    if (creationMode.value === mode) return
    creationMode.value = mode
    resetForm()
}

function closeModal() {
    resetForm()
    emit('close')
}

watch(() => props.initialMode, (newVal) => {
    if (newVal && newVal !== creationMode.value) {
        creationMode.value = newVal
        resetForm()
    }
})

const groupedStaff = {
    'Technical Support': [
        'Aerone Dominhick Sebastian',
        'Michael Binondo',
        'Lloyd Mandapat',
    ],
    'Network Administrator': [
        'Mark Jayson Sison',
    ],
    'System Management': [
        'Etienne Wayne Amparado',
        'John Michael Cagadas',
        'Chester Francisco',
        'Joram Kate Leonardo',
    ],
    'STARBOOKS': [
        'Mc Gyver Basaya',
        'Jomar Rabanera',
    ],
}

// Nature of support options with dependent support types
const supportTypeOptions = computed(() => {
    switch (form.value.ticket_nature_of_support) {
        case 'Hardware':
            return ['Computer / Laptop', 'Printer / Scanner', 'UPS / Power Supply', 'Network Peripheral', 'Others']
        case 'Software':
            return ['Operating System', 'MS Office / Productivity', 'Antivirus / Security', 'Specialized Software', 'Others']
        case 'Hardware & Software':
            return ['System Diagnosis & Repair', 'Workstation Reformat & Setup', 'Others']
        case 'System Modification':
            return ['Website Update', 'Intranet Enhancement', 'Database Change', 'Others']
        case 'Events':
            return ['Audio & Visual Setup', 'Livestream Setup', 'Technical Floor Support', 'Others']
        default:
            return ['Others']
    }
})

// Fetch employees
const fetchEmployees = async () => {
    try {
        const res = await axios.get('/ticket-employees')
        employees.value = Array.isArray(res.data) ? res.data : []
    } catch (err) {
        console.error('Failed to fetch employees', err)
    }
}

onMounted(() => {
    fetchEmployees()
    // Default attended by to logged-in user if available
    const authName = page.props?.auth?.user?.name
    if (authName) {
        form.value.ticket_attended_by = authName
    }
})

// Filtered employees for internal client search
const filteredEmployees = computed(() => {
    const q = employeeSearchQuery.value.trim().toLowerCase()
    if (!q) return employees.value.slice(0, 20)
    return employees.value.filter(e =>
        (e.full_name && e.full_name.toLowerCase().includes(q)) ||
        (e.employee_code && String(e.employee_code).toLowerCase().includes(q))
    ).slice(0, 20)
})

function selectEmployee(emp) {
    selectedEmployee.value = emp
    form.value.client_name = emp.full_name
    form.value.client_empID = emp.employee_code
    form.value.client_sex = emp.sex || 'Prefer not to say'
    form.value.client_division_agency = emp.division_id || ''
    employeeSearchQuery.value = emp.full_name
    showEmployeeDropdown.value = false
}

// Client type change handler
watch(() => form.value.client_type, (newType) => {
    if (newType === 'External') {
        selectedEmployee.value = null
        employeeSearchQuery.value = ''
        form.value.client_empID = 'None'
    } else {
        form.value.client_empID = ''
    }
})

// Nature of support change handler
watch(() => form.value.ticket_nature_of_support, (val) => {
    if (val === 'Others') {
        form.value.ticket_support_type = 'NA'
    } else if (!form.value.ticket_support_type || form.value.ticket_support_type === 'NA') {
        form.value.ticket_support_type = supportTypeOptions.value[0] || 'Others'
    }
})

// Request type change handler
watch(() => form.value.ticket_type, (newType) => {
    if (newType === 'Events') {
        form.value.ticket_nature_of_support = 'Events'
        form.value.ticket_support_type = 'Audio & Visual Setup'
    } else if (form.value.ticket_nature_of_support === 'Events') {
        form.value.ticket_nature_of_support = 'Hardware'
        form.value.ticket_support_type = 'Computer / Laptop'
    }
})

// Toggle assisted by staff
function toggleAssistedStaff(name) {
    const idx = form.value.ticket_assisted_by.indexOf(name)
    if (idx === -1) {
        form.value.ticket_assisted_by.push(name)
    } else {
        form.value.ticket_assisted_by.splice(idx, 1)
    }
}

// Submit handler
const submit = () => {
    if (isSubmitting.value) return
    isSubmitting.value = true
    errors.value = {}

    if (creationMode.value === 'standard') {
        // STANDARD (REAL-TIME) REQUEST -> /tickets-v2
        if (form.value.ticket_type === 'Events') {
            if (!form.value.event_start_date) {
                isSubmitting.value = false
                errors.value = { event_start_date: ['Event start date is required.'] }
                toast.error('Event start date is required.')
                return
            }
            if (!form.value.event_venue?.trim()) {
                isSubmitting.value = false
                errors.value = { event_venue: ['Event venue is required.'] }
                toast.error('Event venue is required.')
                return
            }
            if (form.value.event_end_date && form.value.event_end_date < form.value.event_start_date) {
                isSubmitting.value = false
                errors.value = { event_end_date: ['Event end date cannot be earlier than start date.'] }
                toast.error('Event end date cannot be earlier than start date.')
                return
            }
        }

        const standardProblemText = form.value.ticket_problem_that_needed_support?.trim()
            || (form.value.ticket_type === 'Events'
                ? (form.value.other_request?.trim() || form.value.event_remarks?.trim() || `Event Support: ${form.value.event_venue || 'Event'}`)
                : '')

        const payload = {
            client_type: form.value.client_type,
            client_name: form.value.client_type === 'Internal'
                ? (selectedEmployee.value?.full_name || form.value.client_name)
                : form.value.client_name,
            client_empID: form.value.client_empID || '',
            client_sex: form.value.client_sex || 'Prefer not to say',
            client_division_agency: form.value.client_division_agency || '',
            ticket_type: form.value.ticket_type,
            ticket_problem_that_needed_support: standardProblemText,
        }

        if (form.value.ticket_type === 'Events') {
            payload.event_start_date = form.value.event_start_date || null
            payload.event_end_date = form.value.event_end_date || null
            payload.event_venue = form.value.event_venue?.trim() || null
            payload.event_remarks = form.value.event_remarks?.trim() || null
            payload.other_request = form.value.other_request?.trim() || null
        }

        router.post('/tickets-v2', payload, {
            onSuccess: (pageObj) => {
                isSubmitting.value = false
                const refNo = pageObj.props?.flash?.refNo
                resetForm()
                emit('success')
                emit('created')
                emit('close')
            },
            onError: (err) => {
                isSubmitting.value = false
                errors.value = err || {}
                toast.error('Please check form fields and correct errors.')
            },
        })
    } else {
        // LATE / BACKDATED REQUEST -> /admin/tickets-v2
        if (form.value.date_requested && form.value.date_completed) {
            if (new Date(form.value.date_completed) < new Date(form.value.date_requested)) {
                isSubmitting.value = false
                errors.value = {
                    date_completed: ['Completion date cannot be earlier than request date.'],
                }
                toast.error('Completion date cannot be earlier than request date.')
                return
            }
        }

        const isEvents = form.value.ticket_type === 'Events' || form.value.ticket_nature_of_support === 'Events'

        if (isEvents && form.value.event_end_date && form.value.event_start_date && form.value.event_end_date < form.value.event_start_date) {
            isSubmitting.value = false
            errors.value = {
                event_end_date: ['Event end date cannot be earlier than start date.'],
            }
            toast.error('Event end date cannot be earlier than start date.')
            return
        }

        const lateProblemText = form.value.ticket_problem_that_needed_support?.trim()
            || (isEvents
                ? (form.value.other_request?.trim() || form.value.event_remarks?.trim() || `Event Support: ${form.value.event_venue || 'Event'}`)
                : '')

        const payload = {
            client_type: form.value.client_type,
            client_name: form.value.client_type === 'Internal'
                ? (selectedEmployee.value?.full_name || form.value.client_name)
                : form.value.client_name,
            client_division_agency: form.value.client_division_agency || 'OTHERS',
            client_empID: form.value.client_empID || 'None',
            client_sex: form.value.client_sex || 'Prefer not to say',

            ticket_type: form.value.ticket_type,
            ticket_nature_of_support: form.value.ticket_nature_of_support,
            ticket_support_type: form.value.ticket_nature_of_support === 'Others'
                ? 'NA'
                : (form.value.ticket_support_type || 'Others'),
            ticket_attended_by: form.value.ticket_attended_by || (page.props?.auth?.user?.name ?? 'Staff'),
            ticket_assisted_by: form.value.ticket_assisted_by.length
                ? form.value.ticket_assisted_by.join(', ')
                : 'None',
            ticket_problem_that_needed_support: lateProblemText,
            ticket_actions_taken: form.value.ticket_actions_taken || 'Actions logged per late request.',
            ticket_remarks: form.value.ticket_remarks || '',
            date_requested: form.value.date_requested,
            date_completed: form.value.date_completed,
        }

        if (isEvents) {
            payload.event_start_date = form.value.event_start_date || null
            payload.event_end_date = form.value.event_end_date || null
            payload.event_venue = form.value.event_venue?.trim() || null
            payload.event_remarks = form.value.event_remarks?.trim() || null
            payload.other_request = form.value.other_request?.trim() || null
        }

        router.post('/admin/tickets-v2', payload, {
            onSuccess: (pageObj) => {
                isSubmitting.value = false
                const refNo = pageObj?.props?.flash?.refNo
                toast.success(refNo ? `Late ticket #${refNo} recorded successfully!` : 'Late ticket entry recorded successfully!')
                resetForm()
                emit('success')
                emit('created')
                emit('close')
            },
            onError: (err) => {
                isSubmitting.value = false
                errors.value = err || {}
                toast.error('Failed to create late ticket. Please verify inputs.')
            },
        })
    }
}
</script>

<template>
    <div class="bg-white rounded-2xl overflow-hidden shadow-2xl flex flex-col max-h-[90vh] w-full max-w-3xl mx-auto">
        <!-- Modal Top Bar -->
        <div class="px-6 py-4 bg-gradient-to-r from-blue-950 via-blue-900 to-indigo-900 text-white flex items-center justify-between">
            <div class="flex items-center gap-3">
                <div class="w-9 h-9 rounded-lg bg-white/10 flex items-center justify-center">
                    <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v3m0 0v3m0-3h3m-3 0H9m12 0a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                </div>
                <div>
                    <h3 class="font-bold text-base leading-tight">Create Support Ticket</h3>
                    <p class="text-xs text-blue-200">Unified entry workflow for standard and backdated requests</p>
                </div>
            </div>

            <button
                type="button"
                @click="closeModal"
                class="w-8 h-8 rounded-full bg-white/10 hover:bg-white/20 flex items-center justify-center text-white transition"
            >
                ✕
            </button>
        </div>

        <!-- Mode Segmented Selector -->
        <div class="px-6 pt-4 pb-2 bg-slate-50 border-b border-gray-200">
            <div class="flex p-1 bg-gray-200/80 rounded-xl max-w-md mx-auto">
                <button
                    type="button"
                    @click="setCreationMode('standard')"
                    class="flex-1 py-2 px-3 rounded-lg text-xs font-bold transition-all flex items-center justify-center gap-2"
                    :class="creationMode === 'standard'
                        ? 'bg-white text-blue-900 shadow-sm'
                        : 'text-gray-600 hover:text-gray-900'"
                >
                    <span class="w-2 h-2 rounded-full" :class="creationMode === 'standard' ? 'bg-blue-600' : 'bg-gray-400'"></span>
                    Standard Request
                    <span class="text-[10px] py-0.5 px-1.5 rounded font-normal" :class="creationMode === 'standard' ? 'bg-blue-50 text-blue-800' : 'text-gray-500'">Active</span>
                </button>

                <button
                    type="button"
                    @click="setCreationMode('late')"
                    class="flex-1 py-2 px-3 rounded-lg text-xs font-bold transition-all flex items-center justify-center gap-2"
                    :class="creationMode === 'late'
                        ? 'bg-white text-rose-900 shadow-sm'
                        : 'text-gray-600 hover:text-gray-900'"
                >
                    <span class="w-2 h-2 rounded-full" :class="creationMode === 'late' ? 'bg-rose-600' : 'bg-gray-400'"></span>
                    Late / Backdated Request
                    <span class="text-[10px] py-0.5 px-1.5 rounded font-normal" :class="creationMode === 'late' ? 'bg-rose-50 text-rose-800' : 'text-gray-500'">Historical</span>
                </button>
            </div>

            <!-- Mode Notice Banner -->
            <div class="mt-3 text-center text-xs text-gray-500">
                <span v-if="creationMode === 'standard'">
                    Creates an active ticket set to <strong class="text-emerald-700">In Progress</strong> for ongoing support.
                </span>
                <span v-else>
                    Logs a previously resolved ticket set to <strong class="text-purple-700">Completed</strong> with custom dates and actions taken.
                </span>
            </div>
        </div>

        <!-- Scrollable Form Body -->
        <form @submit.prevent="submit" class="flex-1 overflow-y-auto p-6 space-y-6">
            <!-- High-Visibility Validation Error Alert Banner at top -->
            <div
                v-if="Object.keys(errors).length > 0"
                class="p-4 rounded-xl bg-red-50 border-2 border-red-300 text-red-900 text-xs shadow-sm flex items-start gap-3"
            >
                <div class="w-6 h-6 rounded-full bg-red-200 text-red-700 flex items-center justify-center shrink-0 mt-0.5">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                    </svg>
                </div>
                <div class="flex-1">
                    <h5 class="font-bold text-red-900 text-xs uppercase tracking-wider">Please correct the following:</h5>
                    <ul class="list-disc list-inside mt-1.5 space-y-1 font-medium text-red-800">
                        <li v-for="(msg, field) in errors" :key="field">
                            {{ Array.isArray(msg) ? msg[0] : msg }}
                        </li>
                    </ul>
                </div>
                <button
                    type="button"
                    @click="errors = {}"
                    class="text-red-400 hover:text-red-700 font-bold text-sm leading-none p-1"
                >
                    ✕
                </button>
            </div>

            <!-- Section 1: Client Information -->
            <div class="space-y-4">
                <div class="flex items-center justify-between border-b pb-2">
                    <h4 class="text-xs font-bold uppercase tracking-wider text-gray-700 flex items-center gap-2">
                        <span class="w-5 h-5 rounded-full bg-blue-100 text-blue-900 flex items-center justify-center text-xs">1</span>
                        Client Information
                    </h4>
                    <!-- Client Type Radio -->
                    <div class="flex items-center gap-4 text-xs">
                        <label class="flex items-center gap-1.5 cursor-pointer font-medium text-gray-700">
                            <input type="radio" v-model="form.client_type" value="Internal" class="text-blue-900 focus:ring-blue-900" />
                            Internal (DOST-STII Staff)
                        </label>
                        <label class="flex items-center gap-1.5 cursor-pointer font-medium text-gray-700">
                            <input type="radio" v-model="form.client_type" value="External" class="text-blue-900 focus:ring-blue-900" />
                            External Client
                        </label>
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <!-- Internal: Searchable Employee -->
                    <div v-if="form.client_type === 'Internal'" class="relative md:col-span-2">
                        <label class="block text-xs font-semibold text-gray-700 mb-1">Search Employee *</label>
                        <div class="relative">
                            <input
                                type="text"
                                v-model="employeeSearchQuery"
                                @focus="showEmployeeDropdown = true"
                                placeholder="Type employee name or ID..."
                                class="w-full text-xs sm:text-sm rounded-lg border-gray-300 focus:ring-2 focus:ring-blue-900 focus:border-blue-900 px-3 py-2"
                            />
                            <button
                                v-if="selectedEmployee"
                                type="button"
                                @click="selectedEmployee = null; employeeSearchQuery = ''; form.client_name = ''"
                                class="absolute right-2.5 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600 text-xs"
                            >
                                ✕ Clear
                            </button>
                        </div>

                        <!-- Dropdown list -->
                        <div
                            v-if="showEmployeeDropdown && filteredEmployees.length > 0"
                            class="absolute z-30 mt-1 w-full bg-white border border-gray-200 rounded-lg shadow-xl max-h-56 overflow-y-auto"
                        >
                            <div
                                v-for="emp in filteredEmployees"
                                :key="emp.id"
                                @click="selectEmployee(emp)"
                                class="px-3 py-2 text-xs hover:bg-blue-50 cursor-pointer border-b last:border-b-0 flex items-center justify-between"
                            >
                                <span class="font-semibold text-gray-800">{{ emp.full_name }}</span>
                                <span class="text-gray-400 text-[11px]">{{ emp.employee_code }} · {{ emp.division_id || 'N/A' }}</span>
                            </div>
                        </div>
                    </div>

                    <!-- External: Name input -->
                    <div v-else class="md:col-span-2">
                        <label class="block text-xs font-semibold text-gray-700 mb-1">Client Full Name *</label>
                        <input
                            type="text"
                            v-model="form.client_name"
                            required
                            placeholder="Enter client name"
                            class="w-full text-xs sm:text-sm rounded-lg border-gray-300 focus:ring-2 focus:ring-blue-900 focus:border-blue-900 px-3 py-2"
                        />
                    </div>

                    <!-- Division / Agency -->
                    <div>
                        <label class="block text-xs font-semibold text-gray-700 mb-1">Division / Agency</label>
                        <input
                            type="text"
                            v-model="form.client_division_agency"
                            :readonly="form.client_type === 'Internal' && !!selectedEmployee"
                            placeholder="e.g. FAD, IRAD, CRPD, or Agency name"
                            class="w-full text-xs sm:text-sm rounded-lg border-gray-300 focus:ring-2 focus:ring-blue-900 focus:border-blue-900 px-3 py-2 bg-gray-50"
                        />
                    </div>

                    <!-- Employee ID -->
                    <div>
                        <label class="block text-xs font-semibold text-gray-700 mb-1">Employee ID</label>
                        <input
                            type="text"
                            v-model="form.client_empID"
                            :readonly="form.client_type === 'Internal'"
                            class="w-full text-xs sm:text-sm rounded-lg border-gray-300 focus:ring-2 focus:ring-blue-900 focus:border-blue-900 px-3 py-2 bg-gray-50"
                        />
                    </div>

                    <!-- Sex Selection -->
                    <div>
                        <label class="block text-xs font-semibold text-gray-700 mb-1">Sex</label>
                        <select
                            v-model="form.client_sex"
                            class="w-full text-xs sm:text-sm rounded-lg border-gray-300 focus:ring-2 focus:ring-blue-900 focus:border-blue-900 px-3 py-2"
                        >
                            <option value="">Select Sex</option>
                            <option value="Female">Female</option>
                            <option value="Male">Male</option>
                            <option value="Prefer not to say">Prefer not to say</option>
                        </select>
                    </div>

                    <!-- Request Type -->
                    <div>
                        <label class="block text-xs font-semibold text-gray-700 mb-1">Request Type *</label>
                        <select
                            v-model="form.ticket_type"
                            required
                            class="w-full text-xs sm:text-sm rounded-lg border-gray-300 focus:ring-2 focus:ring-blue-900 focus:border-blue-900 px-3 py-2 font-medium"
                        >
                            <option value="Technical Support">Technical Support</option>
                            <option value="Livestream">Livestream</option>
                            <option value="Events">Events</option>
                        </select>
                    </div>
                </div>
            </div>

            <!-- Event Specific Fields (When Events is selected) -->
            <div v-if="form.ticket_type === 'Events'" class="p-4 rounded-xl bg-amber-50/70 border border-amber-200 space-y-4">
                <div class="border-b border-amber-200/80 pb-2 flex items-center justify-between">
                    <h4 class="text-xs font-bold uppercase tracking-wider text-amber-900 flex items-center gap-2">
                        <span class="w-5 h-5 rounded-full bg-amber-200 text-amber-900 flex items-center justify-center text-xs font-bold">📅</span>
                        Event Details
                    </h4>
                    <span class="text-[11px] font-semibold text-amber-800 bg-amber-100/80 px-2 py-0.5 rounded border border-amber-200">
                        v2_ticket_events
                    </span>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-semibold text-gray-700 mb-1">Event Start Date *</label>
                        <input
                            type="date"
                            v-model="form.event_start_date"
                            required
                            class="w-full text-xs sm:text-sm rounded-lg border-gray-300 focus:ring-2 focus:ring-amber-500 focus:border-amber-500 px-3 py-2 bg-white"
                        />
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-gray-700 mb-1">Event End Date (Optional)</label>
                        <input
                            type="date"
                            v-model="form.event_end_date"
                            class="w-full text-xs sm:text-sm rounded-lg border-gray-300 focus:ring-2 focus:ring-amber-500 focus:border-amber-500 px-3 py-2 bg-white"
                        />
                    </div>

                    <div class="md:col-span-2">
                        <label class="block text-xs font-semibold text-gray-700 mb-1">Event Venue *</label>
                        <input
                            type="text"
                            v-model="form.event_venue"
                            required
                            placeholder="e.g. DOST-STII Cast Room / Atrium / Zoom"
                            class="w-full text-xs sm:text-sm rounded-lg border-gray-300 focus:ring-2 focus:ring-amber-500 focus:border-amber-500 px-3 py-2 bg-white"
                        />
                    </div>

                    <div class="md:col-span-2">
                        <label class="block text-xs font-semibold text-gray-700 mb-1">Event Remarks (Optional)</label>
                        <textarea
                            v-model="form.event_remarks"
                            rows="2"
                            placeholder="Program details, coverage notes, or special setup instructions..."
                            class="w-full text-xs sm:text-sm rounded-lg border-gray-300 focus:ring-2 focus:ring-amber-500 focus:border-amber-500 p-2.5 bg-white"
                        ></textarea>
                    </div>

                    <div class="md:col-span-2">
                        <label class="block text-xs font-semibold text-gray-700 mb-1">Other Request (Optional)</label>
                        <textarea
                            v-model="form.other_request"
                            rows="2"
                            placeholder="Equipment requests, accessories, microphones, clickers, cables, etc..."
                            class="w-full text-xs sm:text-sm rounded-lg border-gray-300 focus:ring-2 focus:ring-amber-500 focus:border-amber-500 p-2.5 bg-white"
                        ></textarea>
                    </div>
                </div>
            </div>

            <!-- Section 2: Problem Description -->
            <div class="space-y-4">
                <div class="border-b pb-2">
                    <h4 class="text-xs font-bold uppercase tracking-wider text-gray-700 flex items-center gap-2">
                        <span class="w-5 h-5 rounded-full bg-blue-100 text-blue-900 flex items-center justify-center text-xs">2</span>
                        Support Description
                    </h4>
                </div>

                <div>
                    <label class="block text-xs font-semibold text-gray-700 mb-1">
                        {{ form.ticket_type === 'Events' ? 'Problem / Additional Assistance Description (Optional)' : 'Problem / Assistance Needed *' }}
                    </label>
                    <textarea
                        v-model="form.ticket_problem_that_needed_support"
                        :required="form.ticket_type !== 'Events'"
                        rows="3"
                        :placeholder="form.ticket_type === 'Events' ? 'Optional description if not covered by event details above...' : 'Describe the issue or assistance requested in detail...'"
                        class="w-full text-xs sm:text-sm rounded-lg border-gray-300 focus:ring-2 focus:ring-blue-900 focus:border-blue-900 p-3"
                    ></textarea>
                </div>
            </div>

            <!-- Section 3: Late Request Specifics (Only visible in Late Mode) -->
            <div v-if="creationMode === 'late'" class="space-y-4 pt-2 border-t border-rose-200">
                <div class="border-b border-rose-200 pb-2 flex items-center justify-between">
                    <h4 class="text-xs font-bold uppercase tracking-wider text-rose-900 flex items-center gap-2">
                        <span class="w-5 h-5 rounded-full bg-rose-100 text-rose-900 flex items-center justify-center text-xs">3</span>
                        Historical Resolution Details
                    </h4>
                    <span class="text-[11px] text-rose-700 bg-rose-50 px-2 py-0.5 rounded border border-rose-200">
                        Backdated Record
                    </span>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <!-- Nature of support -->
                    <div>
                        <label class="block text-xs font-semibold text-gray-700 mb-1">Nature of Support *</label>
                        <select
                            v-model="form.ticket_nature_of_support"
                            class="w-full text-xs sm:text-sm rounded-lg border-gray-300 focus:ring-2 focus:ring-rose-500 focus:border-rose-500 px-3 py-2"
                        >
                            <option value="Hardware">Hardware</option>
                            <option value="Software">Software</option>
                            <option value="Hardware & Software">Hardware & Software</option>
                            <option value="System Modification">System Modification</option>
                            <option value="Events">Events</option>
                            <option value="Others">Others</option>
                        </select>
                    </div>

                    <!-- Support type -->
                    <div>
                        <label class="block text-xs font-semibold text-gray-700 mb-1">Support Type</label>
                        <input
                            v-if="form.ticket_nature_of_support === 'Others'"
                            type="text"
                            value="NA"
                            readonly
                            class="w-full text-xs sm:text-sm rounded-lg border-gray-300 bg-gray-100 px-3 py-2"
                        />
                        <select
                            v-else
                            v-model="form.ticket_support_type"
                            class="w-full text-xs sm:text-sm rounded-lg border-gray-300 focus:ring-2 focus:ring-rose-500 focus:border-rose-500 px-3 py-2"
                        >
                            <option v-for="st in supportTypeOptions" :key="st" :value="st">{{ st }}</option>
                        </select>
                    </div>

                    <!-- Date requested -->
                    <div>
                        <label class="block text-xs font-semibold text-gray-700 mb-1">Date Requested *</label>
                        <input
                            type="datetime-local"
                            v-model="form.date_requested"
                            required
                            class="w-full text-xs sm:text-sm rounded-lg border-gray-300 focus:ring-2 focus:ring-rose-500 focus:border-rose-500 px-3 py-2"
                        />
                    </div>

                    <!-- Date completed -->
                    <div>
                        <label class="block text-xs font-semibold text-gray-700 mb-1">Date Completed *</label>
                        <input
                            type="datetime-local"
                            v-model="form.date_completed"
                            required
                            class="w-full text-xs sm:text-sm rounded-lg border px-3 py-2 transition"
                            :class="errors.date_completed
                                ? 'border-red-500 bg-red-50/50 text-red-900 focus:ring-2 focus:ring-red-500 focus:border-red-500'
                                : 'border-gray-300 focus:ring-2 focus:ring-rose-500 focus:border-rose-500'"
                        />
                        <p v-if="errors.date_completed" class="mt-1 text-xs text-red-600 font-semibold flex items-center gap-1.5">
                            <svg class="w-3.5 h-3.5 shrink-0 text-red-500" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd" />
                            </svg>
                            {{ Array.isArray(errors.date_completed) ? errors.date_completed[0] : errors.date_completed }}
                        </p>
                    </div>

                    <!-- Attended by -->
                    <div class="md:col-span-2">
                        <label class="block text-xs font-semibold text-gray-700 mb-1">Attended By (Lead Specialist) *</label>
                        <select
                            v-model="form.ticket_attended_by"
                            required
                            class="w-full text-xs sm:text-sm rounded-lg border-gray-300 focus:ring-2 focus:ring-rose-500 focus:border-rose-500 px-3 py-2"
                        >
                            <optgroup v-for="(members, group) in groupedStaff" :key="group" :label="group">
                                <option v-for="staffName in members" :key="staffName" :value="staffName">
                                    {{ staffName }}
                                </option>
                            </optgroup>
                        </select>
                    </div>

                    <!-- Assisted by multi-picker -->
                    <div class="md:col-span-2">
                        <label class="block text-xs font-semibold text-gray-700 mb-1">Assisted By (Optional)</label>
                        <div class="flex flex-wrap gap-1.5 p-2 border rounded-lg bg-gray-50 max-h-32 overflow-y-auto">
                            <template v-for="(members, group) in groupedStaff" :key="group">
                                <button
                                    v-for="s in members"
                                    :key="s"
                                    type="button"
                                    @click="toggleAssistedStaff(s)"
                                    class="text-[11px] px-2.5 py-1 rounded-full border transition-all"
                                    :class="form.ticket_assisted_by.includes(s)
                                        ? 'bg-rose-600 text-white border-rose-600 font-semibold'
                                        : 'bg-white text-gray-700 border-gray-300 hover:bg-gray-100'"
                                >
                                    {{ form.ticket_assisted_by.includes(s) ? '✓ ' : '+ ' }}{{ s }}
                                </button>
                            </template>
                        </div>
                    </div>

                    <!-- Actions Taken -->
                    <div class="md:col-span-2">
                        <label class="block text-xs font-semibold text-gray-700 mb-1">Actions Taken *</label>
                        <textarea
                            v-model="form.ticket_actions_taken"
                            required
                            rows="2"
                            placeholder="Detail what was done to fix or resolve this request..."
                            class="w-full text-xs sm:text-sm rounded-lg border-gray-300 focus:ring-2 focus:ring-rose-500 focus:border-rose-500 p-2.5"
                        ></textarea>
                    </div>

                    <!-- Remarks -->
                    <div class="md:col-span-2">
                        <label class="block text-xs font-semibold text-gray-700 mb-1">Remarks</label>
                        <input
                            type="text"
                            v-model="form.ticket_remarks"
                            placeholder="Optional remarks or notes"
                            class="w-full text-xs sm:text-sm rounded-lg border-gray-300 focus:ring-2 focus:ring-rose-500 focus:border-rose-500 px-3 py-2"
                        />
                    </div>
                </div>
            </div>

        </form>

        <!-- Modal Footer -->
        <div class="px-6 py-4 bg-gray-50 border-t border-gray-200 flex items-center justify-between">
            <button
                type="button"
                @click="closeModal"
                class="px-4 py-2 text-xs font-semibold rounded-lg border border-gray-300 text-gray-700 hover:bg-gray-100 transition"
            >
                Cancel
            </button>

            <button
                type="button"
                :disabled="isSubmitting"
                @click="submit"
                class="inline-flex items-center gap-2 px-5 py-2 text-xs font-bold rounded-lg text-white shadow-md transition disabled:opacity-50"
                :class="creationMode === 'standard'
                    ? 'bg-blue-900 hover:bg-blue-800'
                    : 'bg-rose-700 hover:bg-rose-600'"
            >
                <span v-if="isSubmitting" class="inline-block animate-spin">↻</span>
                <span v-else>
                    {{ creationMode === 'standard' ? 'Create Active Ticket' : 'Save Late Ticket Entry' }}
                </span>
            </button>
        </div>
    </div>
</template>

