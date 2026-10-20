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
    initialTicketType: {
        type: String,
        default: '',
    },
    initialStartDate: {
        type: String,
        default: '',
    },
    initialEndDate: {
        type: String,
        default: '',
    },
    initialVenue: {
        type: String,
        default: '',
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

const COMMON_VENUES = [
    'DOST-STII Atrium',
    'Cast Room',
    'Training Room',
    'Conference Room',
    'Mini Theater',
    'Zoom / Online',
]

const selectedVenueOption = ref('')
const customVenueInput = ref('')

function syncVenueFields(venueValue) {
    const val = (venueValue || '').trim()
    if (!val) {
        selectedVenueOption.value = ''
        customVenueInput.value = ''
    } else if (COMMON_VENUES.includes(val)) {
        selectedVenueOption.value = val
        customVenueInput.value = ''
    } else if (val === 'Others') {
        selectedVenueOption.value = 'Others'
        customVenueInput.value = ''
    } else {
        selectedVenueOption.value = 'Others'
        customVenueInput.value = val
    }
}

function selectVenueOption(option) {
    selectedVenueOption.value = option
    if (option === 'Others') {
        form.value.event_venue = customVenueInput.value.trim()
    } else {
        form.value.event_venue = option
    }
}

watch(customVenueInput, (val) => {
    if (selectedVenueOption.value === 'Others') {
        form.value.event_venue = val
    }
})

function getInitialFormData() {
    const authName = page.props?.auth?.user?.name || ''
    const nowIso = new Date().toISOString().slice(0, 16)
    const defaultType = props.initialTicketType || 'Technical Support'
    const isEvents = defaultType === 'Events'
    const startDt = props.initialStartDate || ''
    const endDt = props.initialEndDate || startDt
    const initVenue = props.initialVenue === 'Others' ? '' : (props.initialVenue || '')

    return {
        // Client Details
        client_type: 'Internal',
        client_name: '',
        client_empID: '',
        client_sex: '',
        client_division_agency: '',

        // Common Ticket Details
        ticket_type: defaultType,
        ticket_problem_that_needed_support: '',

        // Events Specifics
        event_start_date: startDt,
        event_end_date: endDt,
        event_venue: initVenue,
        event_remarks: '',
        other_request: '',

        // Late Request Specifics
        ticket_nature_of_support: isEvents ? 'Events' : 'Hardware',
        ticket_support_type: isEvents ? 'Audio & Visual Setup' : 'Computer / Laptop',
        ticket_attended_by: authName,
        ticket_assisted_by: [],
        ticket_actions_taken: '',
        ticket_remarks: '',
        date_requested: nowIso,
        date_completed: nowIso,
    }
}

const form = ref(getInitialFormData())
syncVenueFields(props.initialVenue || form.value.event_venue)
const errors = ref({})
const isSubmitting = ref(false)

function resetForm() {
    selectedEmployee.value = null
    employeeSearchQuery.value = ''
    showEmployeeDropdown.value = false
    errors.value = {}
    isSubmitting.value = false
    Object.assign(form.value, getInitialFormData())
    syncVenueFields(props.initialVenue || form.value.event_venue)
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

watch(
    () => [props.initialTicketType, props.initialStartDate, props.initialEndDate, props.initialVenue],
    ([newType, newStart, newEnd, newVenue]) => {
        if (newType) {
            form.value.ticket_type = newType
        }
        if (newStart !== undefined) {
            form.value.event_start_date = newStart
            if (!form.value.event_end_date || form.value.event_end_date < newStart) {
                form.value.event_end_date = newEnd || newStart
            }
        }
        if (newEnd !== undefined && newEnd !== '') {
            form.value.event_end_date = newEnd
        }
        if (newVenue !== undefined) {
            form.value.event_venue = newVenue === 'Others' ? '' : newVenue
            syncVenueFields(newVenue)
        }
    }
)

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
                const refNo = pageObj?.props?.flash?.refNo
                toast.success(refNo ? `Ticket #${refNo} created successfully!` : 'Support ticket created successfully!')
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

const eventDurationDays = computed(() => {
    const start = form.value.event_start_date
    if (!start) return 0
    const end = form.value.event_end_date || start
    const s = new Date(start)
    const e = new Date(end)
    if (isNaN(s.getTime()) || isNaN(e.getTime()) || e < s) return 1
    return Math.round((e - s) / (1000 * 60 * 60 * 24)) + 1
})
</script>

<template>
    <div class="bg-white rounded-2xl overflow-hidden shadow-2xl border border-slate-200 flex flex-col max-h-[90vh] w-full max-w-3xl mx-auto text-slate-800">
        <!-- Modal Top Bar (System Navy Gradient Theme) -->
        <div class="px-6 py-4 bg-gradient-to-r from-blue-950 via-blue-900 to-indigo-900 text-white flex items-center justify-between shrink-0">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-xl bg-white/10 border border-white/15 flex items-center justify-center shadow-xs">
                    <i class="fa-solid fa-file-circle-plus text-base text-blue-200"></i>
                </div>
                <div>
                    <div class="flex items-center gap-2">
                        <h3 class="font-bold text-base leading-tight tracking-tight">Create Support Ticket</h3>
                        <span
                            v-if="form.ticket_type === 'Events'"
                            class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-blue-500/30 text-blue-100 border border-blue-400/30"
                        >
                            Events Schedule
                        </span>
                    </div>
                    <p class="text-xs text-blue-200/90 mt-0.5">Unified entry workflow for standard and backdated requests</p>
                </div>
            </div>

            <button
                type="button"
                @click="closeModal"
                class="w-8 h-8 rounded-lg bg-white/10 hover:bg-white/20 flex items-center justify-center text-white/80 hover:text-white transition cursor-pointer"
                title="Close (Esc)"
            >
                <i class="fa-solid fa-xmark text-sm"></i>
            </button>
        </div>

        <!-- Mode Segmented Selector -->
        <div class="px-6 pt-3.5 pb-3 bg-slate-50/90 border-b border-slate-200 shrink-0">
            <div class="flex p-1 bg-slate-200/80 rounded-xl max-w-md mx-auto">
                <button
                    type="button"
                    @click="setCreationMode('standard')"
                    class="flex-1 py-1.5 px-3 rounded-lg text-xs font-bold transition-all flex items-center justify-center gap-2 cursor-pointer"
                    :class="creationMode === 'standard'
                        ? 'bg-white text-blue-950 shadow-xs'
                        : 'text-slate-600 hover:text-slate-900'"
                >
                    <span class="w-2 h-2 rounded-full" :class="creationMode === 'standard' ? 'bg-blue-600' : 'bg-slate-400'"></span>
                    <span>Standard Request</span>
                    <span class="text-[10px] py-0.2 px-1.5 rounded-full font-bold" :class="creationMode === 'standard' ? 'bg-blue-100 text-blue-800' : 'bg-slate-300/70 text-slate-600'">Active</span>
                </button>

                <button
                    type="button"
                    @click="setCreationMode('late')"
                    class="flex-1 py-1.5 px-3 rounded-lg text-xs font-bold transition-all flex items-center justify-center gap-2 cursor-pointer"
                    :class="creationMode === 'late'
                        ? 'bg-white text-purple-950 shadow-xs'
                        : 'text-slate-600 hover:text-slate-900'"
                >
                    <span class="w-2 h-2 rounded-full" :class="creationMode === 'late' ? 'bg-purple-600' : 'bg-slate-400'"></span>
                    <span>Late / Backdated</span>
                    <span class="text-[10px] py-0.2 px-1.5 rounded-full font-bold" :class="creationMode === 'late' ? 'bg-purple-100 text-purple-800' : 'bg-slate-300/70 text-slate-600'">Historical</span>
                </button>
            </div>

            <!-- Mode Notice Banner -->
            <div class="mt-2 text-center text-[11px] text-slate-500">
                <span v-if="creationMode === 'standard'" class="inline-flex items-center gap-1.5">
                    <i class="fa-solid fa-bolt text-emerald-600 text-[10px]"></i>
                    Creates an active ticket set to <strong class="text-emerald-700 font-bold">In Progress</strong> for ongoing support.
                </span>
                <span v-else class="inline-flex items-center gap-1.5">
                    <i class="fa-solid fa-clock-rotate-left text-purple-600 text-[10px]"></i>
                    Logs a previously resolved ticket set to <strong class="text-purple-700 font-bold">Completed</strong> with custom dates and actions taken.
                </span>
            </div>
        </div>

        <!-- Scrollable Form Body -->
        <form @submit.prevent="submit" class="flex-1 overflow-y-auto p-6 space-y-5">
            <!-- High-Visibility Validation Error Alert Banner at top -->
            <div
                v-if="Object.keys(errors).length > 0"
                class="p-4 rounded-xl bg-rose-50 border border-rose-300 text-rose-900 text-xs shadow-2xs flex items-start gap-3"
            >
                <div class="w-6 h-6 rounded-lg bg-rose-200/80 text-rose-700 flex items-center justify-center shrink-0 mt-0.5">
                    <i class="fa-solid fa-triangle-exclamation text-xs"></i>
                </div>
                <div class="flex-1">
                    <h5 class="font-bold text-rose-900 text-xs uppercase tracking-wider">Please correct the following:</h5>
                    <ul class="list-disc list-inside mt-1.5 space-y-1 font-medium text-rose-800">
                        <li v-for="(msg, field) in errors" :key="field">
                            {{ Array.isArray(msg) ? msg[0] : msg }}
                        </li>
                    </ul>
                </div>
                <button
                    type="button"
                    @click="errors = {}"
                    class="text-rose-400 hover:text-rose-700 font-bold text-sm leading-none p-1 cursor-pointer"
                >
                    ✕
                </button>
            </div>

            <!-- Section 1: Client Information Card -->
            <div class="p-4 sm:p-5 rounded-2xl bg-slate-50/70 border border-slate-200/90 space-y-4">
                <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2.5 border-b border-slate-200/80 pb-3">
                    <h4 class="text-xs font-bold uppercase tracking-wider text-slate-700 flex items-center gap-2">
                        <span class="w-5 h-5 rounded-lg bg-blue-900 text-white flex items-center justify-center text-[11px] font-bold">1</span>
                        <span>Client &amp; Request Information</span>
                    </h4>
                    <!-- Segmented Client Type Pill Toggle -->
                    <div class="inline-flex p-0.5 bg-slate-200/80 rounded-lg text-xs">
                        <button
                            type="button"
                            @click="form.client_type = 'Internal'"
                            class="px-2.5 py-1 rounded-md text-[11px] font-semibold transition cursor-pointer flex items-center gap-1.5"
                            :class="form.client_type === 'Internal'
                                ? 'bg-white text-blue-900 shadow-2xs font-bold'
                                : 'text-slate-600 hover:text-slate-900'"
                        >
                            <i class="fa-solid fa-id-badge text-[10px]" :class="form.client_type === 'Internal' ? 'text-blue-600' : 'text-slate-400'"></i>
                            <span>Internal Staff</span>
                        </button>
                        <button
                            type="button"
                            @click="form.client_type = 'External'"
                            class="px-2.5 py-1 rounded-md text-[11px] font-semibold transition cursor-pointer flex items-center gap-1.5"
                            :class="form.client_type === 'External'
                                ? 'bg-white text-blue-900 shadow-2xs font-bold'
                                : 'text-slate-600 hover:text-slate-900'"
                        >
                            <i class="fa-solid fa-user-tag text-[10px]" :class="form.client_type === 'External' ? 'text-blue-600' : 'text-slate-400'"></i>
                            <span>External Client</span>
                        </button>
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-3.5">
                    <!-- Internal: Searchable Employee -->
                    <div v-if="form.client_type === 'Internal'" class="relative md:col-span-2">
                        <label class="block text-xs font-semibold text-slate-700 mb-1">Search Employee *</label>
                        <div class="relative">
                            <div class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3 text-slate-400">
                                <i class="fa-solid fa-magnifying-glass text-xs"></i>
                            </div>
                            <input
                                type="text"
                                v-model="employeeSearchQuery"
                                @focus="showEmployeeDropdown = true"
                                placeholder="Type employee name or ID..."
                                class="w-full text-xs sm:text-sm rounded-xl border-slate-300 bg-white focus:ring-2 focus:ring-blue-500 focus:border-blue-500 pl-8 pr-20 py-2 transition"
                            />
                            <button
                                v-if="selectedEmployee || employeeSearchQuery"
                                type="button"
                                @click="selectedEmployee = null; employeeSearchQuery = ''; form.client_name = ''"
                                class="absolute right-2.5 top-1/2 -translate-y-1/2 px-2 py-0.5 rounded-md bg-slate-100 hover:bg-slate-200 text-slate-500 hover:text-slate-700 text-[11px] font-medium transition cursor-pointer"
                            >
                                ✕ Clear
                            </button>
                        </div>

                        <!-- Dropdown list -->
                        <div
                            v-if="showEmployeeDropdown && filteredEmployees.length > 0"
                            class="absolute z-30 mt-1.5 w-full bg-white border border-slate-200 rounded-xl shadow-2xl max-h-56 overflow-y-auto divide-y divide-slate-100"
                        >
                            <div
                                v-for="emp in filteredEmployees"
                                :key="emp.id"
                                @click="selectEmployee(emp)"
                                class="px-3.5 py-2 text-xs hover:bg-blue-50/80 cursor-pointer flex items-center justify-between transition"
                            >
                                <span class="font-semibold text-slate-800 flex items-center gap-2">
                                    <span class="w-5 h-5 rounded-full bg-blue-100 text-blue-800 text-[10px] font-bold flex items-center justify-center">
                                        {{ emp.full_name ? emp.full_name.charAt(0).toUpperCase() : 'U' }}
                                    </span>
                                    {{ emp.full_name }}
                                </span>
                                <span class="text-slate-400 text-[11px] font-mono">{{ emp.employee_code }} · {{ emp.division_id || 'N/A' }}</span>
                            </div>
                        </div>
                    </div>

                    <!-- External: Name input -->
                    <div v-else class="md:col-span-2">
                        <label class="block text-xs font-semibold text-slate-700 mb-1">Client Full Name *</label>
                        <div class="relative">
                            <div class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3 text-slate-400">
                                <i class="fa-regular fa-user text-xs"></i>
                            </div>
                            <input
                                type="text"
                                v-model="form.client_name"
                                required
                                placeholder="Enter client full name"
                                class="w-full text-xs sm:text-sm rounded-xl border-slate-300 bg-white focus:ring-2 focus:ring-blue-500 focus:border-blue-500 pl-8 pr-3 py-2 transition"
                            />
                        </div>
                    </div>

                    <!-- Division / Agency -->
                    <div>
                        <label class="block text-xs font-semibold text-slate-700 mb-1">Division / Agency</label>
                        <div class="relative">
                            <div class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3 text-slate-400">
                                <i class="fa-solid fa-building text-xs"></i>
                            </div>
                            <input
                                type="text"
                                v-model="form.client_division_agency"
                                :readonly="form.client_type === 'Internal' && !!selectedEmployee"
                                placeholder="e.g. FAD, IRAD, CRPD, OD-MISPS"
                                class="w-full text-xs sm:text-sm rounded-xl border-slate-300 focus:ring-2 focus:ring-blue-500 focus:border-blue-500 pl-8 pr-3 py-2 transition"
                                :class="form.client_type === 'Internal' && !!selectedEmployee ? 'bg-slate-100 text-slate-600' : 'bg-white'"
                            />
                        </div>
                    </div>

                    <!-- Employee ID -->
                    <div>
                        <label class="block text-xs font-semibold text-slate-700 mb-1">Employee ID</label>
                        <div class="relative">
                            <div class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3 text-slate-400">
                                <i class="fa-solid fa-hashtag text-xs"></i>
                            </div>
                            <input
                                type="text"
                                v-model="form.client_empID"
                                :readonly="form.client_type === 'Internal'"
                                class="w-full text-xs sm:text-sm rounded-xl border-slate-300 focus:ring-2 focus:ring-blue-500 focus:border-blue-500 pl-8 pr-3 py-2 transition"
                                :class="form.client_type === 'Internal' ? 'bg-slate-100 text-slate-600' : 'bg-white'"
                            />
                        </div>
                    </div>

                    <!-- Sex Selection -->
                    <div>
                        <label class="block text-xs font-semibold text-slate-700 mb-1">Sex</label>
                        <div class="relative">
                            <div class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3 text-slate-400">
                                <i class="fa-solid fa-venus-mars text-xs"></i>
                            </div>
                            <select
                                v-model="form.client_sex"
                                class="w-full text-xs sm:text-sm rounded-xl border-slate-300 bg-white focus:ring-2 focus:ring-blue-500 focus:border-blue-500 pl-8 pr-8 py-2 appearance-none cursor-pointer transition"
                            >
                                <option value="">Select Sex</option>
                                <option value="Female">Female</option>
                                <option value="Male">Male</option>
                                <option value="Prefer not to say">Prefer not to say</option>
                            </select>
                            <div class="pointer-events-none absolute inset-y-0 right-0 flex items-center pr-3 text-slate-400">
                                <i class="fa-solid fa-chevron-down text-[10px]"></i>
                            </div>
                        </div>
                    </div>

                    <!-- Request Type -->
                    <div>
                        <label class="block text-xs font-semibold text-slate-700 mb-1">Request Type *</label>
                        <div class="relative">
                            <div class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3 text-blue-600">
                                <i
                                    :class="{
                                        'fa-solid fa-calendar-days': form.ticket_type === 'Events',
                                        'fa-solid fa-video': form.ticket_type === 'Livestream',
                                        'fa-solid fa-screwdriver-wrench': form.ticket_type === 'Technical Support',
                                    }"
                                    class="text-xs"
                                ></i>
                            </div>
                            <select
                                v-model="form.ticket_type"
                                required
                                class="w-full text-xs sm:text-sm rounded-xl border-slate-300 bg-white focus:ring-2 focus:ring-blue-500 focus:border-blue-500 pl-8 pr-8 py-2 font-semibold text-slate-800 appearance-none cursor-pointer transition"
                            >
                                <option value="Technical Support">Technical Support</option>
                                <option value="Livestream">Livestream</option>
                                <option value="Events">Events</option>
                            </select>
                            <div class="pointer-events-none absolute inset-y-0 right-0 flex items-center pr-3 text-slate-400">
                                <i class="fa-solid fa-chevron-down text-[10px]"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Event Specific Fields (When Events is selected — System Blue Theme) -->
            <div v-if="form.ticket_type === 'Events'" class="p-4 sm:p-5 rounded-2xl bg-blue-50/50 border border-blue-200/90 space-y-4 shadow-2xs">
                <div class="border-b border-blue-200/70 pb-2.5 flex items-center justify-between flex-wrap gap-2">
                    <h4 class="text-xs font-bold uppercase tracking-wider text-blue-950 flex items-center gap-2">
                        <span class="w-6 h-6 rounded-lg bg-blue-900 text-white flex items-center justify-center text-xs">
                            <i class="fa-solid fa-calendar-check text-[11px]"></i>
                        </span>
                        <span>Event Schedule &amp; Venue Details</span>
                    </h4>
                    <div class="flex items-center gap-2">
                        <span
                            v-if="eventDurationDays > 0"
                            class="text-[11px] font-bold text-white bg-blue-900 px-2.5 py-0.5 rounded-full shadow-2xs"
                        >
                            {{ eventDurationDays }} Day{{ eventDurationDays > 1 ? 's' : '' }} Scheduled
                        </span>
                        <span class="text-[10px] font-semibold text-blue-800 bg-blue-100/90 px-2 py-0.5 rounded-md border border-blue-200">
                            Calendar Plotted
                        </span>
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-3.5">
                    <div>
                        <label class="block text-xs font-semibold text-slate-700 mb-1">Event Start Date *</label>
                        <input
                            type="date"
                            v-model="form.event_start_date"
                            required
                            class="w-full text-xs sm:text-sm rounded-xl border-slate-300 focus:ring-2 focus:ring-blue-500 focus:border-blue-500 px-3 py-2 bg-white font-medium"
                        />
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-slate-700 mb-1">Event End Date (Optional)</label>
                        <input
                            type="date"
                            v-model="form.event_end_date"
                            :min="form.event_start_date || undefined"
                            class="w-full text-xs sm:text-sm rounded-xl border-slate-300 focus:ring-2 focus:ring-blue-500 focus:border-blue-500 px-3 py-2 bg-white font-medium"
                        />
                    </div>

                    <div class="md:col-span-2 space-y-2">
                        <label class="block text-xs font-semibold text-slate-700">Event Venue *</label>
                        <div class="relative">
                            <div class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3 text-rose-500">
                                <i class="fa-solid fa-location-dot text-xs"></i>
                            </div>
                            <select
                                v-model="selectedVenueOption"
                                @change="selectVenueOption(selectedVenueOption)"
                                required
                                class="w-full text-xs sm:text-sm rounded-xl border-slate-300 focus:ring-2 focus:ring-blue-500 focus:border-blue-500 pl-8 pr-8 py-2 bg-white font-medium text-slate-700"
                            >
                                <option value="" disabled>-- Select Event Venue --</option>
                                <option v-for="venue in COMMON_VENUES" :key="venue" :value="venue">
                                    {{ venue }}
                                </option>
                                <option value="Others">Others (Specify custom venue...)</option>
                            </select>
                        </div>

                        <!-- Conditional Custom Venue Input when 'Others' is selected -->
                        <div v-if="selectedVenueOption === 'Others'" class="p-2.5 rounded-xl bg-blue-50/70 border border-blue-200/80 space-y-1">
                            <label class="block text-[11px] font-bold text-blue-900">
                                Specify Custom / External Venue *
                            </label>
                            <div class="relative">
                                <div class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3 text-blue-600">
                                    <i class="fa-solid fa-pen-to-square text-xs"></i>
                                </div>
                                <input
                                    type="text"
                                    v-model="customVenueInput"
                                    required
                                    placeholder="Enter custom venue name (e.g. PICC, Hotel Ballroom, Regional Office)..."
                                    class="w-full text-xs sm:text-sm rounded-lg border-blue-300 focus:ring-2 focus:ring-blue-500 focus:border-blue-500 pl-8 pr-3 py-1.5 bg-white"
                                />
                            </div>
                        </div>

                        <!-- Quick Venue Presets -->
                        <div class="flex items-center gap-1.5 flex-wrap pt-0.5">
                            <span class="text-[10px] font-semibold text-slate-400 uppercase tracking-wider mr-0.5">Quick select:</span>
                            <button
                                v-for="venue in COMMON_VENUES"
                                :key="venue"
                                type="button"
                                @click="selectVenueOption(venue)"
                                class="px-2 py-0.5 rounded-md text-[10px] font-semibold border transition cursor-pointer"
                                :class="selectedVenueOption === venue
                                    ? 'bg-blue-900 text-white border-blue-900'
                                    : 'bg-white text-slate-600 border-slate-200 hover:border-blue-300 hover:text-blue-800'"
                            >
                                {{ venue }}
                            </button>
                            <button
                                type="button"
                                @click="selectVenueOption('Others')"
                                class="px-2 py-0.5 rounded-md text-[10px] font-semibold border transition cursor-pointer"
                                :class="selectedVenueOption === 'Others'
                                    ? 'bg-blue-900 text-white border-blue-900'
                                    : 'bg-white text-slate-600 border-slate-200 hover:border-blue-300 hover:text-blue-800'"
                            >
                                Others
                            </button>
                        </div>
                    </div>

                    <div class="md:col-span-2">
                        <label class="block text-xs font-semibold text-slate-700 mb-1">Event Remarks (Optional)</label>
                        <textarea
                            v-model="form.event_remarks"
                            rows="2"
                            placeholder="Program details, coverage notes, or special setup instructions..."
                            class="w-full text-xs sm:text-sm rounded-xl border-slate-300 focus:ring-2 focus:ring-blue-500 focus:border-blue-500 p-2.5 bg-white"
                        ></textarea>
                    </div>

                    <div class="md:col-span-2">
                        <label class="block text-xs font-semibold text-slate-700 mb-1">Other Support Requests / Equipment (Optional)</label>
                        <textarea
                            v-model="form.other_request"
                            rows="2"
                            placeholder="Equipment requests, accessories, microphones, clickers, cables, LED wall, etc..."
                            class="w-full text-xs sm:text-sm rounded-xl border-slate-300 focus:ring-2 focus:ring-blue-500 focus:border-blue-500 p-2.5 bg-white"
                        ></textarea>
                    </div>
                </div>
            </div>

            <!-- Section 2: Problem Description -->
            <div class="p-4 sm:p-5 rounded-2xl bg-slate-50/70 border border-slate-200/90 space-y-3">
                <div class="border-b border-slate-200/80 pb-2.5">
                    <h4 class="text-xs font-bold uppercase tracking-wider text-slate-700 flex items-center gap-2">
                        <span class="w-5 h-5 rounded-lg bg-blue-900 text-white flex items-center justify-center text-[11px] font-bold">2</span>
                        <span>Support Description</span>
                    </h4>
                </div>

                <div>
                    <label class="block text-xs font-semibold text-slate-700 mb-1">
                        {{ form.ticket_type === 'Events' ? 'Problem / Additional Assistance Description (Optional)' : 'Problem / Assistance Needed *' }}
                    </label>
                    <textarea
                        v-model="form.ticket_problem_that_needed_support"
                        :required="form.ticket_type !== 'Events'"
                        rows="3"
                        :placeholder="form.ticket_type === 'Events' ? 'Optional description if not covered by event details above...' : 'Describe the issue or assistance requested in detail...'"
                        class="w-full text-xs sm:text-sm rounded-xl border-slate-300 bg-white focus:ring-2 focus:ring-blue-500 focus:border-blue-500 p-3"
                    ></textarea>
                </div>
            </div>

            <!-- Section 3: Late Request Specifics (Only visible in Late Mode) -->
            <div v-if="creationMode === 'late'" class="p-4 sm:p-5 rounded-2xl bg-purple-50/40 border border-purple-200/90 space-y-4">
                <div class="border-b border-purple-200/80 pb-2.5 flex items-center justify-between">
                    <h4 class="text-xs font-bold uppercase tracking-wider text-purple-950 flex items-center gap-2">
                        <span class="w-5 h-5 rounded-lg bg-purple-700 text-white flex items-center justify-center text-[11px] font-bold">3</span>
                        <span>Historical Resolution Details</span>
                    </h4>
                    <span class="text-[10px] font-bold text-purple-800 bg-purple-100 px-2 py-0.5 rounded-full border border-purple-200">
                        Backdated Record
                    </span>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-3.5">
                    <!-- Nature of support -->
                    <div>
                        <label class="block text-xs font-semibold text-slate-700 mb-1">Nature of Support *</label>
                        <div class="relative">
                            <select
                                v-model="form.ticket_nature_of_support"
                                class="w-full text-xs sm:text-sm rounded-xl border-slate-300 bg-white focus:ring-2 focus:ring-purple-500 focus:border-purple-500 px-3 pr-8 py-2 appearance-none cursor-pointer"
                            >
                                <option value="Hardware">Hardware</option>
                                <option value="Software">Software</option>
                                <option value="Hardware & Software">Hardware &amp; Software</option>
                                <option value="System Modification">System Modification</option>
                                <option value="Events">Events</option>
                                <option value="Others">Others</option>
                            </select>
                            <div class="pointer-events-none absolute inset-y-0 right-0 flex items-center pr-3 text-slate-400">
                                <i class="fa-solid fa-chevron-down text-[10px]"></i>
                            </div>
                        </div>
                    </div>

                    <!-- Support type -->
                    <div>
                        <label class="block text-xs font-semibold text-slate-700 mb-1">Support Type</label>
                        <input
                            v-if="form.ticket_nature_of_support === 'Others'"
                            type="text"
                            value="NA"
                            readonly
                            class="w-full text-xs sm:text-sm rounded-xl border-slate-300 bg-slate-100 px-3 py-2"
                        />
                        <div v-else class="relative">
                            <select
                                v-model="form.ticket_support_type"
                                class="w-full text-xs sm:text-sm rounded-xl border-slate-300 bg-white focus:ring-2 focus:ring-purple-500 focus:border-purple-500 px-3 pr-8 py-2 appearance-none cursor-pointer"
                            >
                                <option v-for="st in supportTypeOptions" :key="st" :value="st">{{ st }}</option>
                            </select>
                            <div class="pointer-events-none absolute inset-y-0 right-0 flex items-center pr-3 text-slate-400">
                                <i class="fa-solid fa-chevron-down text-[10px]"></i>
                            </div>
                        </div>
                    </div>

                    <!-- Date requested -->
                    <div>
                        <label class="block text-xs font-semibold text-slate-700 mb-1">Date Requested *</label>
                        <input
                            type="datetime-local"
                            v-model="form.date_requested"
                            required
                            class="w-full text-xs sm:text-sm rounded-xl border-slate-300 bg-white focus:ring-2 focus:ring-purple-500 focus:border-purple-500 px-3 py-2"
                        />
                    </div>

                    <!-- Date completed -->
                    <div>
                        <label class="block text-xs font-semibold text-slate-700 mb-1">Date Completed *</label>
                        <input
                            type="datetime-local"
                            v-model="form.date_completed"
                            required
                            class="w-full text-xs sm:text-sm rounded-xl border px-3 py-2 transition bg-white"
                            :class="errors.date_completed
                                ? 'border-rose-500 bg-rose-50/50 text-rose-900 focus:ring-2 focus:ring-rose-500 focus:border-rose-500'
                                : 'border-slate-300 focus:ring-2 focus:ring-purple-500 focus:border-purple-500'"
                        />
                        <p v-if="errors.date_completed" class="mt-1 text-xs text-rose-600 font-semibold flex items-center gap-1.5">
                            <i class="fa-solid fa-circle-exclamation text-rose-500"></i>
                            {{ Array.isArray(errors.date_completed) ? errors.date_completed[0] : errors.date_completed }}
                        </p>
                    </div>

                    <!-- Attended by -->
                    <div class="md:col-span-2">
                        <label class="block text-xs font-semibold text-slate-700 mb-1">Attended By (Lead Specialist) *</label>
                        <div class="relative">
                            <div class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3 text-slate-400">
                                <i class="fa-solid fa-user-gear text-xs"></i>
                            </div>
                            <select
                                v-model="form.ticket_attended_by"
                                required
                                class="w-full text-xs sm:text-sm rounded-xl border-slate-300 bg-white focus:ring-2 focus:ring-purple-500 focus:border-purple-500 pl-8 pr-8 py-2 appearance-none cursor-pointer"
                            >
                                <optgroup v-for="(members, group) in groupedStaff" :key="group" :label="group">
                                    <option v-for="staffName in members" :key="staffName" :value="staffName">
                                        {{ staffName }}
                                    </option>
                                </optgroup>
                            </select>
                            <div class="pointer-events-none absolute inset-y-0 right-0 flex items-center pr-3 text-slate-400">
                                <i class="fa-solid fa-chevron-down text-[10px]"></i>
                            </div>
                        </div>
                    </div>

                    <!-- Assisted by multi-picker -->
                    <div class="md:col-span-2">
                        <label class="block text-xs font-semibold text-slate-700 mb-1">Assisted By (Optional)</label>
                        <div class="flex flex-wrap gap-1.5 p-2.5 border border-slate-200 rounded-xl bg-white max-h-32 overflow-y-auto">
                            <template v-for="(members, group) in groupedStaff" :key="group">
                                <button
                                    v-for="s in members"
                                    :key="s"
                                    type="button"
                                    @click="toggleAssistedStaff(s)"
                                    class="text-[11px] px-2.5 py-1 rounded-lg border transition-all cursor-pointer"
                                    :class="form.ticket_assisted_by.includes(s)
                                        ? 'bg-blue-900 text-white border-blue-900 font-semibold shadow-2xs'
                                        : 'bg-slate-50 text-slate-700 border-slate-200 hover:bg-slate-100'"
                                >
                                    {{ form.ticket_assisted_by.includes(s) ? '✓ ' : '+ ' }}{{ s }}
                                </button>
                            </template>
                        </div>
                    </div>

                    <!-- Actions Taken -->
                    <div class="md:col-span-2">
                        <label class="block text-xs font-semibold text-slate-700 mb-1">Actions Taken *</label>
                        <textarea
                            v-model="form.ticket_actions_taken"
                            required
                            rows="2"
                            placeholder="Detail what was done to fix or resolve this request..."
                            class="w-full text-xs sm:text-sm rounded-xl border-slate-300 bg-white focus:ring-2 focus:ring-purple-500 focus:border-purple-500 p-2.5"
                        ></textarea>
                    </div>

                    <!-- Remarks -->
                    <div class="md:col-span-2">
                        <label class="block text-xs font-semibold text-slate-700 mb-1">Remarks</label>
                        <input
                            type="text"
                            v-model="form.ticket_remarks"
                            placeholder="Optional remarks or notes"
                            class="w-full text-xs sm:text-sm rounded-xl border-slate-300 bg-white focus:ring-2 focus:ring-purple-500 focus:border-purple-500 px-3 py-2"
                        />
                    </div>
                </div>
            </div>

        </form>

        <!-- Modal Footer -->
        <div class="px-6 py-4 bg-slate-50 border-t border-slate-200 flex items-center justify-between shrink-0">
            <button
                type="button"
                @click="closeModal"
                class="px-4 py-2 text-xs font-semibold rounded-xl border border-slate-300 bg-white text-slate-700 hover:bg-slate-100 transition cursor-pointer"
            >
                Cancel
            </button>

            <button
                type="button"
                :disabled="isSubmitting"
                @click="submit"
                class="inline-flex items-center gap-2 px-5 py-2 text-xs font-bold rounded-xl text-white shadow-sm transition disabled:opacity-50 cursor-pointer"
                :class="creationMode === 'standard'
                    ? 'bg-blue-900 hover:bg-blue-800'
                    : 'bg-purple-700 hover:bg-purple-800'"
            >
                <i v-if="isSubmitting" class="fa-solid fa-spinner animate-spin text-xs"></i>
                <i v-else :class="creationMode === 'standard' ? 'fa-solid fa-plus text-xs' : 'fa-solid fa-clock-rotate-left text-xs'"></i>
                <span>
                    {{ creationMode === 'standard' ? 'Create Active Ticket' : 'Save Late Ticket Entry' }}
                </span>
            </button>
        </div>
    </div>
</template>

