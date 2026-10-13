<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import Modal from '@/Components/Modal.vue'
import { Head, Link, useForm, router } from '@inertiajs/vue3'
import { ref, computed } from 'vue'
import { useToast } from 'vue-toastification'

const toast = useToast()

const props = defineProps({
  users: { type: Object, default: () => ({ data: [], links: [] }) },
  summary: { type: Object, default: () => ({}) },
  filters: { type: Object, default: () => ({}) },
  currentUserId: { type: Number, default: null },
})

// ── Search & Filter State ─────────────────────────────────────────────────────
const searchInput = ref(props.filters?.search || '')
const selectedRole = ref(props.filters?.role || '')
const selectedStatus = ref(props.filters?.status || '')

function applyFilters() {
  router.get(route('admin.users.index'), {
    search: searchInput.value || undefined,
    role: selectedRole.value || undefined,
    status: selectedStatus.value || undefined,
  }, {
    preserveState: true,
    preserveScroll: true,
  })
}

function resetFilters() {
  searchInput.value = ''
  selectedRole.value = ''
  selectedStatus.value = ''
  applyFilters()
}

// ── Modals State ──────────────────────────────────────────────────────────────
const showCreateModal = ref(false)
const showEditModal = ref(false)
const showDeleteModal = ref(false)
const userToDelete = ref(null)
const editingUser = ref(null)

// ── Create User Form ──────────────────────────────────────────────────────────
const createForm = useForm({
  name: '',
  username: '',
  email: '',
  password: '',
  password_confirmation: '',
  role: 'Staff',
  isActivated: '1',
})

function openCreateModal() {
  createForm.reset()
  createForm.clearErrors()
  createForm.role = 'Staff'
  createForm.isActivated = '1'
  showCreateModal.value = true
}

function submitCreate() {
  createForm.post(route('admin.users.store'), {
    preserveScroll: true,
    onSuccess: () => {
      toast.success(`User '${createForm.name}' created successfully!`)
      showCreateModal.value = false
      createForm.reset()
    },
    onError: (err) => {
      toast.error('Please fix the errors in the form.')
    },
  })
}

// ── Edit User Form ────────────────────────────────────────────────────────────
const editForm = useForm({
  id: null,
  name: '',
  username: '',
  email: '',
  password: '',
  password_confirmation: '',
  role: 'Staff',
  isActivated: '1',
})

function openEditModal(user) {
  editingUser.value = user
  editForm.reset()
  editForm.clearErrors()
  editForm.id = user.id
  editForm.name = user.name
  editForm.username = user.username
  editForm.email = user.email || ''
  editForm.role = user.role
  editForm.isActivated = user.isActivated ? '1' : '0'
  editForm.password = ''
  editForm.password_confirmation = ''
  showEditModal.value = true
}

function submitEdit() {
  if (!editForm.id) return
  editForm.put(route('admin.users.update', editForm.id), {
    preserveScroll: true,
    onSuccess: () => {
      toast.success(`User '${editForm.name}' updated successfully!`)
      showEditModal.value = false
    },
    onError: (err) => {
      toast.error('Please check validation errors.')
    },
  })
}

// ── Quick Toggle Status ───────────────────────────────────────────────────────
function toggleUserStatus(user) {
  if (user.id === props.currentUserId) {
    toast.error('You cannot deactivate your own account.')
    return
  }

  router.patch(route('admin.users.toggle-status', user.id), {}, {
    preserveScroll: true,
    onSuccess: () => {
      toast.success(`Status updated for ${user.name}!`)
    },
    onError: (err) => {
      toast.error(err?.error || 'Failed to update account status.')
    },
  })
}

// ── Delete User ───────────────────────────────────────────────────────────────
function confirmDelete(user) {
  if (user.id === props.currentUserId) {
    toast.error('You cannot delete your own account.')
    return
  }
  userToDelete.value = user
  showDeleteModal.value = true
}

function executeDelete() {
  if (!userToDelete.value) return
  router.delete(route('admin.users.destroy', userToDelete.value.id), {
    preserveScroll: true,
    onSuccess: () => {
      toast.success(`User '${userToDelete.value.name}' deleted.`)
      showDeleteModal.value = false
      userToDelete.value = null
    },
    onError: (err) => {
      toast.error(err?.error || 'Failed to delete user.')
    },
  })
}

function getInitials(name) {
  if (!name) return 'U'
  return name.split(' ').map(n => n[0]).slice(0, 2).join('').toUpperCase()
}

function formatDate(val) {
  if (!val) return '—'
  try {
    return new Date(val).toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' })
  } catch {
    return val
  }
}
</script>

<template>
  <Head title="User & Role Management" />

  <AuthenticatedLayout>
    <div class="space-y-6">

      <!-- ── 1. Top Header Banner ────────────────────────────────────────────── -->
      <div class="rounded-2xl border border-purple-200/90 bg-gradient-to-r from-purple-950 via-indigo-950 to-slate-900 p-6 text-white shadow-sm flex flex-col md:flex-row md:items-center justify-between gap-4">
        <div class="flex items-center gap-4">
          <div class="w-12 h-12 rounded-2xl bg-purple-500/20 border border-purple-400/30 flex items-center justify-center shrink-0">
            <i class="fa-solid fa-users-gear text-purple-300 text-xl"></i>
          </div>
          <div>
            <div class="flex items-center gap-2">
              <h1 class="text-xl font-black tracking-tight">System User & Role Governance</h1>
              <span class="px-2 py-0.5 rounded-full text-[10px] font-bold uppercase tracking-wider bg-purple-400/20 text-purple-200 border border-purple-400/30">
                Super Admin Only
              </span>
            </div>
            <p class="text-xs text-purple-200/80 mt-0.5">
              Control authentication roles, dispatch eligibility, and team permissions across the ticketing system.
            </p>
          </div>
        </div>

        <button
          type="button"
          @click="openCreateModal"
          class="inline-flex items-center gap-2 px-4 py-2.5 rounded-xl bg-purple-600 hover:bg-purple-500 text-white font-bold text-xs uppercase tracking-wider shadow-sm transition shrink-0 cursor-pointer"
        >
          <i class="fa-solid fa-user-plus text-xs"></i>
          <span>Create New User</span>
        </button>
      </div>

      <!-- ── 2. KPI Summary Counters ─────────────────────────────────────────── -->
      <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-3.5">
        <div class="bg-white rounded-xl border border-slate-200/90 p-4 shadow-2xs">
          <span class="text-[10px] font-bold uppercase tracking-wider text-slate-400 block">Total Accounts</span>
          <span class="text-xl font-black text-slate-900 mt-1 block">{{ summary.total ?? 0 }}</span>
        </div>

        <div class="bg-white rounded-xl border border-purple-200/90 p-4 shadow-2xs">
          <div class="flex items-center justify-between">
            <span class="text-[10px] font-bold uppercase tracking-wider text-purple-600 block">Super Admins</span>
            <span class="w-2 h-2 rounded-full bg-purple-600"></span>
          </div>
          <span class="text-xl font-black text-purple-950 mt-1 block">{{ summary.super_admins ?? 0 }}</span>
        </div>

        <div class="bg-white rounded-xl border border-blue-200/90 p-4 shadow-2xs">
          <div class="flex items-center justify-between">
            <span class="text-[10px] font-bold uppercase tracking-wider text-blue-600 block">Admins</span>
            <span class="w-2 h-2 rounded-full bg-blue-600"></span>
          </div>
          <span class="text-xl font-black text-blue-950 mt-1 block">{{ summary.admins ?? 0 }}</span>
        </div>

        <div class="bg-white rounded-xl border border-slate-200/90 p-4 shadow-2xs">
          <div class="flex items-center justify-between">
            <span class="text-[10px] font-bold uppercase tracking-wider text-slate-600 block">Support Staff</span>
            <span class="w-2 h-2 rounded-full bg-slate-500"></span>
          </div>
          <span class="text-xl font-black text-slate-900 mt-1 block">{{ summary.staff ?? 0 }}</span>
        </div>

        <div class="bg-white rounded-xl border border-emerald-200/90 p-4 shadow-2xs">
          <div class="flex items-center justify-between">
            <span class="text-[10px] font-bold uppercase tracking-wider text-emerald-600 block">Active Status</span>
            <span class="w-2 h-2 rounded-full bg-emerald-500"></span>
          </div>
          <span class="text-xl font-black text-emerald-950 mt-1 block">{{ summary.active ?? 0 }}</span>
        </div>

        <div class="bg-white rounded-xl border border-rose-200/90 p-4 shadow-2xs">
          <div class="flex items-center justify-between">
            <span class="text-[10px] font-bold uppercase tracking-wider text-rose-600 block">Deactivated</span>
            <span class="w-2 h-2 rounded-full bg-rose-500"></span>
          </div>
          <span class="text-xl font-black text-rose-950 mt-1 block">{{ summary.inactive ?? 0 }}</span>
        </div>
      </div>

      <!-- ── 3. Filters & Search Bar ─────────────────────────────────────────── -->
      <div class="bg-white rounded-2xl border border-slate-200/90 p-4 shadow-2xs flex flex-col md:flex-row items-center justify-between gap-3">
        <!-- Search Input -->
        <div class="relative w-full md:w-80">
          <i class="fa-solid fa-magnifying-glass absolute left-3.5 top-1/2 -translate-y-1/2 text-slate-400 text-xs"></i>
          <input
            v-model="searchInput"
            @keydown.enter="applyFilters"
            type="text"
            placeholder="Search by name, username, or email..."
            class="w-full pl-9 pr-3 py-2 text-xs rounded-xl border border-slate-200 focus:ring-2 focus:ring-purple-900 focus:border-purple-900 bg-white shadow-2xs placeholder-slate-400"
          />
        </div>

        <!-- Role & Status Filter Badges -->
        <div class="flex items-center gap-2 flex-wrap w-full md:w-auto">
          <!-- Role Filters -->
          <div class="flex items-center p-0.5 bg-slate-100 rounded-xl border border-slate-200/80 text-xs">
            <button
              type="button"
              @click="selectedRole = ''; applyFilters()"
              class="px-2.5 py-1 rounded-lg font-semibold transition cursor-pointer"
              :class="!selectedRole ? 'bg-white text-slate-900 shadow-2xs' : 'text-slate-600 hover:text-slate-900'"
            >
              All Roles
            </button>
            <button
              type="button"
              @click="selectedRole = 'Super Admin'; applyFilters()"
              class="px-2.5 py-1 rounded-lg font-semibold transition cursor-pointer"
              :class="selectedRole === 'Super Admin' ? 'bg-purple-700 text-white shadow-2xs' : 'text-slate-600 hover:text-slate-900'"
            >
              Super Admin
            </button>
            <button
              type="button"
              @click="selectedRole = 'Admin'; applyFilters()"
              class="px-2.5 py-1 rounded-lg font-semibold transition cursor-pointer"
              :class="selectedRole === 'Admin' ? 'bg-blue-600 text-white shadow-2xs' : 'text-slate-600 hover:text-slate-900'"
            >
              Admin
            </button>
            <button
              type="button"
              @click="selectedRole = 'Staff'; applyFilters()"
              class="px-2.5 py-1 rounded-lg font-semibold transition cursor-pointer"
              :class="selectedRole === 'Staff' ? 'bg-slate-800 text-white shadow-2xs' : 'text-slate-600 hover:text-slate-900'"
            >
              Staff
            </button>
          </div>

          <!-- Status Filters -->
          <div class="flex items-center p-0.5 bg-slate-100 rounded-xl border border-slate-200/80 text-xs">
            <button
              type="button"
              @click="selectedStatus = ''; applyFilters()"
              class="px-2.5 py-1 rounded-lg font-semibold transition cursor-pointer"
              :class="!selectedStatus ? 'bg-white text-slate-900 shadow-2xs' : 'text-slate-600 hover:text-slate-900'"
            >
              All
            </button>
            <button
              type="button"
              @click="selectedStatus = 'active'; applyFilters()"
              class="px-2.5 py-1 rounded-lg font-semibold transition cursor-pointer"
              :class="selectedStatus === 'active' ? 'bg-emerald-600 text-white shadow-2xs' : 'text-slate-600 hover:text-slate-900'"
            >
              Active
            </button>
            <button
              type="button"
              @click="selectedStatus = 'inactive'; applyFilters()"
              class="px-2.5 py-1 rounded-lg font-semibold transition cursor-pointer"
              :class="selectedStatus === 'inactive' ? 'bg-rose-600 text-white shadow-2xs' : 'text-slate-600 hover:text-slate-900'"
            >
              Deactivated
            </button>
          </div>

          <!-- Reset Filter -->
          <button
            v-if="searchInput || selectedRole || selectedStatus"
            type="button"
            @click="resetFilters"
            class="px-2.5 py-1.5 rounded-lg border border-slate-200 hover:bg-slate-50 text-slate-600 text-xs font-semibold transition cursor-pointer"
            title="Reset filters"
          >
            <i class="fa-solid fa-rotate-left mr-1"></i>Reset
          </button>
        </div>
      </div>

      <!-- ── 4. Users Table ─────────────────────────────────────────────────── -->
      <div class="bg-white rounded-2xl border border-slate-200/90 shadow-2xs overflow-hidden">
        <div class="overflow-x-auto">
          <table class="w-full text-left text-xs">
            <thead class="bg-slate-50/80 border-b border-slate-200/80 text-slate-500 font-bold uppercase tracking-wider text-[10px]">
              <tr>
                <th class="py-3.5 px-4 sm:px-6">User Identity</th>
                <th class="py-3.5 px-4">Contact</th>
                <th class="py-3.5 px-4">System Role</th>
                <th class="py-3.5 px-4">Active Status</th>
                <th class="py-3.5 px-4">Assigned Workload</th>
                <th class="py-3.5 px-4">Created Date</th>
                <th class="py-3.5 px-4 sm:px-6 text-right">Actions</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
              <tr
                v-for="user in users.data"
                :key="user.id"
                class="hover:bg-slate-50/70 transition-colors"
                :class="{ 'bg-purple-50/30': user.id === currentUserId }"
              >
                <!-- Identity -->
                <td class="py-3 px-4 sm:px-6">
                  <div class="flex items-center gap-3">
                    <div
                      class="w-9 h-9 rounded-xl font-black text-xs flex items-center justify-center shrink-0 shadow-2xs"
                      :class="{
                        'bg-purple-100 text-purple-800 border border-purple-200': user.role === 'Super Admin',
                        'bg-blue-100 text-blue-800 border border-blue-200': user.role === 'Admin',
                        'bg-slate-100 text-slate-700 border border-slate-200': user.role === 'Staff',
                      }"
                    >
                      {{ getInitials(user.name) }}
                    </div>
                    <div class="min-w-0">
                      <div class="flex items-center gap-1.5 flex-wrap">
                        <span class="font-bold text-slate-900 truncate">{{ user.name }}</span>
                        <span
                          v-if="user.id === currentUserId"
                          class="px-1.5 py-0.2 rounded text-[10px] font-bold bg-purple-100 text-purple-700 border border-purple-200"
                        >
                          You
                        </span>
                      </div>
                      <span class="text-slate-400 font-mono text-[11px] block mt-0.5">@{{ user.username }}</span>
                    </div>
                  </div>
                </td>

                <!-- Email -->
                <td class="py-3 px-4 text-slate-600">
                  <span v-if="user.email" class="truncate block max-w-[180px]">{{ user.email }}</span>
                  <span v-else class="text-slate-300 italic">No email</span>
                </td>

                <!-- Role Badge -->
                <td class="py-3 px-4">
                  <span
                    class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[11px] font-bold tracking-wide shadow-2xs"
                    :class="{
                      'bg-purple-100 text-purple-800 border border-purple-300': user.role === 'Super Admin',
                      'bg-blue-100 text-blue-800 border border-blue-300': user.role === 'Admin',
                      'bg-slate-100 text-slate-700 border border-slate-300': user.role === 'Staff',
                    }"
                  >
                    <i v-if="user.role === 'Super Admin'" class="fa-solid fa-crown text-[10px] text-purple-600"></i>
                    <i v-else-if="user.role === 'Admin'" class="fa-solid fa-shield-halved text-[10px] text-blue-600"></i>
                    <i v-else class="fa-solid fa-user-gear text-[10px] text-slate-500"></i>
                    <span>{{ user.role }}</span>
                  </span>
                </td>

                <!-- Status Badge -->
                <td class="py-3 px-4">
                  <button
                    type="button"
                    :disabled="user.id === currentUserId"
                    @click="toggleUserStatus(user)"
                    class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[11px] font-semibold border transition cursor-pointer disabled:cursor-not-allowed disabled:opacity-60"
                    :class="user.isActivated
                      ? 'bg-emerald-50 text-emerald-800 border-emerald-200 hover:bg-emerald-100'
                      : 'bg-rose-50 text-rose-800 border-rose-200 hover:bg-rose-100'"
                    :title="user.id === currentUserId ? 'You cannot deactivate your own account' : 'Click to toggle status'"
                  >
                    <span
                      class="w-1.5 h-1.5 rounded-full"
                      :class="user.isActivated ? 'bg-emerald-500' : 'bg-rose-500'"
                    ></span>
                    <span>{{ user.isActivated ? 'Active' : 'Deactivated' }}</span>
                  </button>
                </td>

                <!-- Workload -->
                <td class="py-3 px-4">
                  <div v-if="user.role === 'Super Admin'" class="text-slate-400 italic text-[11px]">
                    No tickets (Excluded)
                  </div>
                  <div v-else class="flex items-center gap-1.5">
                    <span
                      class="px-2 py-0.5 rounded-md font-bold text-xs"
                      :class="user.open_tickets_count > 0 ? 'bg-amber-100 text-amber-900 border border-amber-200' : 'bg-slate-100 text-slate-600'"
                    >
                      {{ user.open_tickets_count }}
                    </span>
                    <span class="text-slate-500 text-[11px]">open {{ user.open_tickets_count === 1 ? 'ticket' : 'tickets' }}</span>
                  </div>
                </td>

                <!-- Created Date -->
                <td class="py-3 px-4 text-slate-500 text-[11px]">
                  {{ formatDate(user.created_at) }}
                </td>

                <!-- Action Buttons -->
                <td class="py-3 px-4 sm:px-6 text-right">
                  <div class="inline-flex items-center gap-1.5">
                    <button
                      type="button"
                      @click="openEditModal(user)"
                      class="p-1.5 rounded-lg border border-slate-200 hover:bg-slate-100 text-slate-700 transition cursor-pointer"
                      title="Edit user details and role"
                    >
                      <i class="fa-solid fa-pen-to-square text-xs"></i>
                    </button>

                    <button
                      type="button"
                      :disabled="user.id === currentUserId"
                      @click="confirmDelete(user)"
                      class="p-1.5 rounded-lg border border-slate-200 hover:bg-rose-50 hover:border-rose-300 text-slate-400 hover:text-rose-600 transition cursor-pointer disabled:opacity-30 disabled:cursor-not-allowed"
                      :title="user.id === currentUserId ? 'You cannot delete yourself' : 'Delete user account'"
                    >
                      <i class="fa-solid fa-trash-can text-xs"></i>
                    </button>
                  </div>
                </td>
              </tr>

              <tr v-if="!users.data?.length">
                <td colspan="7" class="py-12 text-center text-slate-400">
                  <i class="fa-solid fa-users text-2xl mb-1 block"></i>
                  No users found matching your filters.
                </td>
              </tr>
            </tbody>
          </table>
        </div>

        <!-- Pagination -->
        <div v-if="users.links?.length > 3" class="px-5 py-3 border-t border-slate-100 bg-slate-50/50 flex items-center justify-between">
          <span class="text-xs text-slate-500">
            Showing {{ users.from }} to {{ users.to }} of {{ users.total }} users
          </span>
          <div class="flex items-center gap-1">
            <template v-for="(link, i) in users.links" :key="i">
              <Link
                v-if="link.url"
                :href="link.url"
                class="px-2.5 py-1 text-xs rounded-md border transition"
                :class="link.active ? 'bg-purple-900 text-white border-purple-900 font-bold' : 'bg-white border-slate-200 text-slate-700 hover:bg-slate-50'"
                v-html="link.label"
              />
              <span
                v-else
                class="px-2.5 py-1 text-xs text-slate-300 border border-transparent"
                v-html="link.label"
              />
            </template>
          </div>
        </div>
      </div>

    </div>

    <!-- ── CREATE USER MODAL ───────────────────────────────────────────────── -->
    <Modal :show="showCreateModal" @close="showCreateModal = false" maxWidth="lg">
      <div class="p-6 bg-white rounded-2xl">
        <div class="flex items-center justify-between pb-3.5 border-b border-slate-100">
          <div class="flex items-center gap-2.5">
            <div class="w-9 h-9 rounded-xl bg-purple-100 text-purple-800 flex items-center justify-center font-bold text-sm shadow-xs">
              <i class="fa-solid fa-user-plus"></i>
            </div>
            <div>
              <h3 class="text-base font-bold text-slate-900">Create New System User</h3>
              <p class="text-xs text-slate-500">Add an IT staff member or administrator</p>
            </div>
          </div>
          <button type="button" @click="showCreateModal = false" class="text-slate-400 hover:text-slate-600 cursor-pointer">
            <i class="fa-solid fa-xmark text-sm"></i>
          </button>
        </div>

        <form @submit.prevent="submitCreate" class="space-y-4 py-4 text-xs">
          <!-- Full Name -->
          <div>
            <label class="block font-bold text-slate-800 mb-1">
              Full Name <span class="text-rose-500">*</span>
            </label>
            <input
              v-model="createForm.name"
              type="text"
              placeholder="e.g. John Doe"
              class="w-full h-10 px-3 rounded-xl border border-slate-300 focus:ring-2 focus:ring-purple-900 focus:border-purple-900 text-xs bg-white text-slate-800 shadow-2xs font-medium"
              required
            />
            <p v-if="createForm.errors.name" class="text-rose-600 text-[11px] mt-1">{{ createForm.errors.name }}</p>
          </div>

          <!-- Username & Email -->
          <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
            <div>
              <label class="block font-bold text-slate-800 mb-1">
                Username <span class="text-rose-500">*</span>
              </label>
              <input
                v-model="createForm.username"
                type="text"
                placeholder="e.g. jdoe"
                class="w-full h-10 px-3 rounded-xl border border-slate-300 focus:ring-2 focus:ring-purple-900 focus:border-purple-900 text-xs bg-white text-slate-800 shadow-2xs font-medium"
                required
              />
              <p v-if="createForm.errors.username" class="text-rose-600 text-[11px] mt-1">{{ createForm.errors.username }}</p>
            </div>

            <div>
              <label class="block font-bold text-slate-800 mb-1">
                Email Address
              </label>
              <input
                v-model="createForm.email"
                type="email"
                placeholder="e.g. jdoe@stii.dost.gov.ph"
                class="w-full h-10 px-3 rounded-xl border border-slate-300 focus:ring-2 focus:ring-purple-900 focus:border-purple-900 text-xs bg-white text-slate-800 shadow-2xs font-medium"
              />
              <p v-if="createForm.errors.email" class="text-rose-600 text-[11px] mt-1">{{ createForm.errors.email }}</p>
            </div>
          </div>

          <!-- Role Selection Tiles -->
          <div>
            <label class="block font-bold text-slate-800 mb-1.5">
              Account Role <span class="text-rose-500">*</span>
            </label>
            <div class="grid grid-cols-3 gap-2">
              <button
                type="button"
                @click="createForm.role = 'Staff'"
                class="p-2.5 rounded-xl border text-left transition cursor-pointer"
                :class="createForm.role === 'Staff' ? 'bg-slate-50 border-slate-900 ring-1 ring-slate-900 text-slate-900 shadow-2xs' : 'border-slate-200 text-slate-600 hover:bg-slate-50'"
              >
                <div class="flex items-center gap-1.5 font-bold text-xs">
                  <i class="fa-solid fa-user text-[11px]"></i>
                  <span>Staff</span>
                </div>
                <p class="text-[10px] text-slate-400 mt-0.5">Receives assigned tickets in personal queue</p>
              </button>

              <button
                type="button"
                @click="createForm.role = 'Admin'"
                class="p-2.5 rounded-xl border text-left transition cursor-pointer"
                :class="createForm.role === 'Admin' ? 'bg-blue-50 border-blue-900 ring-1 ring-blue-900 text-blue-900 shadow-2xs' : 'border-slate-200 text-slate-600 hover:bg-slate-50'"
              >
                <div class="flex items-center gap-1.5 font-bold text-xs">
                  <i class="fa-solid fa-shield-halved text-[11px]"></i>
                  <span>Admin</span>
                </div>
                <p class="text-[10px] text-slate-400 mt-0.5">Triage, dispatch, and assign tickets</p>
              </button>

              <button
                type="button"
                @click="createForm.role = 'Super Admin'"
                class="p-2.5 rounded-xl border text-left transition cursor-pointer"
                :class="createForm.role === 'Super Admin' ? 'bg-purple-50 border-purple-900 ring-1 ring-purple-900 text-purple-900 shadow-2xs' : 'border-slate-200 text-slate-600 hover:bg-slate-50'"
              >
                <div class="flex items-center gap-1.5 font-bold text-xs">
                  <i class="fa-solid fa-crown text-[11px] text-purple-700"></i>
                  <span>Super Admin</span>
                </div>
                <p class="text-[10px] text-slate-400 mt-0.5">Executive overview, user management (no tickets)</p>
              </button>
            </div>
            <p v-if="createForm.errors.role" class="text-rose-600 text-[11px] mt-1">{{ createForm.errors.role }}</p>
          </div>

          <!-- Passwords -->
          <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
            <div>
              <label class="block font-bold text-slate-800 mb-1">
                Password <span class="text-rose-500">*</span>
              </label>
              <input
                v-model="createForm.password"
                type="password"
                placeholder="Minimum 8 characters"
                class="w-full h-10 px-3 rounded-xl border border-slate-300 focus:ring-2 focus:ring-purple-900 focus:border-purple-900 text-xs bg-white text-slate-800 shadow-2xs font-medium"
                required
              />
              <p v-if="createForm.errors.password" class="text-rose-600 text-[11px] mt-1">{{ createForm.errors.password }}</p>
            </div>

            <div>
              <label class="block font-bold text-slate-800 mb-1">
                Confirm Password <span class="text-rose-500">*</span>
              </label>
              <input
                v-model="createForm.password_confirmation"
                type="password"
                placeholder="Repeat password"
                class="w-full h-10 px-3 rounded-xl border border-slate-300 focus:ring-2 focus:ring-purple-900 focus:border-purple-900 text-xs bg-white text-slate-800 shadow-2xs font-medium"
                required
              />
            </div>
          </div>

          <!-- Active Status Toggle -->
          <div class="flex items-center gap-3 pt-2">
            <input
              id="create_isActivated"
              v-model="createForm.isActivated"
              type="checkbox"
              true-value="1"
              false-value="0"
              class="w-4 h-4 rounded text-purple-900 focus:ring-purple-900 border-slate-300 cursor-pointer"
            />
            <label for="create_isActivated" class="font-bold text-slate-800 cursor-pointer">
              Account Active (Eligible for ticket assignment and system login)
            </label>
          </div>

          <!-- Footer Buttons -->
          <div class="flex items-center justify-end gap-2.5 pt-3.5 border-t border-slate-100">
            <button
              type="button"
              @click="showCreateModal = false"
              class="px-4 py-2 text-xs font-semibold rounded-xl border border-slate-200 text-slate-700 hover:bg-slate-50 transition cursor-pointer"
            >
              Cancel
            </button>
            <button
              type="submit"
              :disabled="createForm.processing"
              class="inline-flex items-center gap-2 px-4 py-2 text-xs font-bold uppercase rounded-xl shadow-sm text-white bg-purple-900 hover:bg-purple-800 disabled:opacity-50 transition cursor-pointer"
            >
              <i v-if="createForm.processing" class="fa-solid fa-spinner animate-spin text-xs"></i>
              <i v-else class="fa-solid fa-check text-xs"></i>
              <span>Create Account</span>
            </button>
          </div>
        </form>
      </div>
    </Modal>

    <!-- ── EDIT USER MODAL ─────────────────────────────────────────────────── -->
    <Modal :show="showEditModal" @close="showEditModal = false" maxWidth="lg">
      <div class="p-6 bg-white rounded-2xl">
        <div class="flex items-center justify-between pb-3.5 border-b border-slate-100">
          <div class="flex items-center gap-2.5">
            <div class="w-9 h-9 rounded-xl bg-purple-100 text-purple-800 flex items-center justify-center font-bold text-sm shadow-xs">
              <i class="fa-solid fa-user-pen"></i>
            </div>
            <div>
              <h3 class="text-base font-bold text-slate-900">Edit User & Role</h3>
              <p class="text-xs text-slate-500">Update account credentials and governance</p>
            </div>
          </div>
          <button type="button" @click="showEditModal = false" class="text-slate-400 hover:text-slate-600 cursor-pointer">
            <i class="fa-solid fa-xmark text-sm"></i>
          </button>
        </div>

        <form @submit.prevent="submitEdit" class="space-y-4 py-4 text-xs">
          <!-- Full Name -->
          <div>
            <label class="block font-bold text-slate-800 mb-1">
              Full Name <span class="text-rose-500">*</span>
            </label>
            <input
              v-model="editForm.name"
              type="text"
              class="w-full h-10 px-3 rounded-xl border border-slate-300 focus:ring-2 focus:ring-purple-900 focus:border-purple-900 text-xs bg-white text-slate-800 shadow-2xs font-medium"
              required
            />
            <p v-if="editForm.errors.name" class="text-rose-600 text-[11px] mt-1">{{ editForm.errors.name }}</p>
          </div>

          <!-- Username & Email -->
          <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
            <div>
              <label class="block font-bold text-slate-800 mb-1">
                Username <span class="text-rose-500">*</span>
              </label>
              <input
                v-model="editForm.username"
                type="text"
                class="w-full h-10 px-3 rounded-xl border border-slate-300 focus:ring-2 focus:ring-purple-900 focus:border-purple-900 text-xs bg-white text-slate-800 shadow-2xs font-medium"
                required
              />
              <p v-if="editForm.errors.username" class="text-rose-600 text-[11px] mt-1">{{ editForm.errors.username }}</p>
            </div>

            <div>
              <label class="block font-bold text-slate-800 mb-1">
                Email Address
              </label>
              <input
                v-model="editForm.email"
                type="email"
                class="w-full h-10 px-3 rounded-xl border border-slate-300 focus:ring-2 focus:ring-purple-900 focus:border-purple-900 text-xs bg-white text-slate-800 shadow-2xs font-medium"
              />
              <p v-if="editForm.errors.email" class="text-rose-600 text-[11px] mt-1">{{ editForm.errors.email }}</p>
            </div>
          </div>

          <!-- Role Selection Tiles -->
          <div>
            <div class="flex items-center justify-between mb-1.5">
              <label class="block font-bold text-slate-800">
                System Role <span class="text-rose-500">*</span>
              </label>
              <span v-if="editForm.id === currentUserId" class="text-[11px] text-amber-600 font-semibold">
                (Cannot change your own role)
              </span>
            </div>
            <div class="grid grid-cols-3 gap-2">
              <button
                type="button"
                :disabled="editForm.id === currentUserId"
                @click="editForm.role = 'Staff'"
                class="p-2.5 rounded-xl border text-left transition cursor-pointer disabled:opacity-50 disabled:cursor-not-allowed"
                :class="editForm.role === 'Staff' ? 'bg-slate-50 border-slate-900 ring-1 ring-slate-900 text-slate-900 shadow-2xs' : 'border-slate-200 text-slate-600 hover:bg-slate-50'"
              >
                <div class="flex items-center gap-1.5 font-bold text-xs">
                  <i class="fa-solid fa-user text-[11px]"></i>
                  <span>Staff</span>
                </div>
                <p class="text-[10px] text-slate-400 mt-0.5">Receives assigned tickets</p>
              </button>

              <button
                type="button"
                :disabled="editForm.id === currentUserId"
                @click="editForm.role = 'Admin'"
                class="p-2.5 rounded-xl border text-left transition cursor-pointer disabled:opacity-50 disabled:cursor-not-allowed"
                :class="editForm.role === 'Admin' ? 'bg-blue-50 border-blue-900 ring-1 ring-blue-900 text-blue-900 shadow-2xs' : 'border-slate-200 text-slate-600 hover:bg-slate-50'"
              >
                <div class="flex items-center gap-1.5 font-bold text-xs">
                  <i class="fa-solid fa-shield-halved text-[11px]"></i>
                  <span>Admin</span>
                </div>
                <p class="text-[10px] text-slate-400 mt-0.5">Triage and assign tickets</p>
              </button>

              <button
                type="button"
                :disabled="editForm.id === currentUserId"
                @click="editForm.role = 'Super Admin'"
                class="p-2.5 rounded-xl border text-left transition cursor-pointer disabled:opacity-50 disabled:cursor-not-allowed"
                :class="editForm.role === 'Super Admin' ? 'bg-purple-50 border-purple-900 ring-1 ring-purple-900 text-purple-900 shadow-2xs' : 'border-slate-200 text-slate-600 hover:bg-slate-50'"
              >
                <div class="flex items-center gap-1.5 font-bold text-xs">
                  <i class="fa-solid fa-crown text-[11px] text-purple-700"></i>
                  <span>Super Admin</span>
                </div>
                <p class="text-[10px] text-slate-400 mt-0.5">Executive & user governance</p>
              </button>
            </div>
            <p v-if="editForm.errors.role" class="text-rose-600 text-[11px] mt-1">{{ editForm.errors.role }}</p>
          </div>

          <!-- Reset Password (Optional) -->
          <div class="p-3 bg-slate-50/70 rounded-xl border border-slate-200/70 space-y-2.5">
            <span class="block font-bold text-slate-700 text-xs">
              Change Password <span class="font-normal text-slate-400">(leave blank to keep current password)</span>
            </span>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-2.5">
              <div>
                <input
                  v-model="editForm.password"
                  type="password"
                  placeholder="New password (optional)"
                  class="w-full h-9 px-3 rounded-lg border border-slate-300 focus:ring-2 focus:ring-purple-900 focus:border-purple-900 text-xs bg-white text-slate-800 font-medium"
                />
                <p v-if="editForm.errors.password" class="text-rose-600 text-[11px] mt-1">{{ editForm.errors.password }}</p>
              </div>

              <div>
                <input
                  v-model="editForm.password_confirmation"
                  type="password"
                  placeholder="Confirm new password"
                  class="w-full h-9 px-3 rounded-lg border border-slate-300 focus:ring-2 focus:ring-purple-900 focus:border-purple-900 text-xs bg-white text-slate-800 font-medium"
                />
              </div>
            </div>
          </div>

          <!-- Active Status Toggle -->
          <div class="flex items-center gap-3 pt-1">
            <input
              id="edit_isActivated"
              v-model="editForm.isActivated"
              type="checkbox"
              true-value="1"
              false-value="0"
              :disabled="editForm.id === currentUserId"
              class="w-4 h-4 rounded text-purple-900 focus:ring-purple-900 border-slate-300 cursor-pointer disabled:opacity-50"
            />
            <label for="edit_isActivated" class="font-bold text-slate-800 cursor-pointer">
              Account Active (Eligible for assignment and login)
            </label>
          </div>

          <!-- Footer Buttons -->
          <div class="flex items-center justify-end gap-2.5 pt-3.5 border-t border-slate-100">
            <button
              type="button"
              @click="showEditModal = false"
              class="px-4 py-2 text-xs font-semibold rounded-xl border border-slate-200 text-slate-700 hover:bg-slate-50 transition cursor-pointer"
            >
              Cancel
            </button>
            <button
              type="submit"
              :disabled="editForm.processing"
              class="inline-flex items-center gap-2 px-4 py-2 text-xs font-bold uppercase rounded-xl shadow-sm text-white bg-purple-900 hover:bg-purple-800 disabled:opacity-50 transition cursor-pointer"
            >
              <i v-if="editForm.processing" class="fa-solid fa-spinner animate-spin text-xs"></i>
              <i v-else class="fa-solid fa-check text-xs"></i>
              <span>Save Changes</span>
            </button>
          </div>
        </form>
      </div>
    </Modal>

    <!-- ── CONFIRM DELETE USER MODAL ───────────────────────────────────────── -->
    <Modal :show="showDeleteModal" @close="showDeleteModal = false" maxWidth="md">
      <div class="p-6 bg-white rounded-2xl">
        <div class="flex items-center justify-between pb-3.5 border-b border-rose-100">
          <div class="flex items-center gap-2.5">
            <div class="w-9 h-9 rounded-xl bg-rose-100 text-rose-700 flex items-center justify-center font-bold text-sm shadow-xs">
              <i class="fa-solid fa-triangle-exclamation"></i>
            </div>
            <div>
              <h3 class="text-base font-bold text-slate-900">Delete User Account</h3>
              <p class="text-xs text-rose-600">Permanent action</p>
            </div>
          </div>
          <button type="button" @click="showDeleteModal = false" class="text-slate-400 hover:text-slate-600 cursor-pointer">
            <i class="fa-solid fa-xmark text-sm"></i>
          </button>
        </div>

        <div class="space-y-3 py-4 text-xs text-slate-700">
          <p>
            Are you sure you want to permanently delete account for
            <strong class="font-bold text-slate-900">{{ userToDelete?.name }}</strong> (@{{ userToDelete?.username }})?
          </p>
          <p class="p-3 rounded-xl bg-rose-50 border border-rose-100 text-rose-800 text-[11px] leading-relaxed">
            This account will lose all access to the ticketing system immediately. Historical tickets and activity logs assigned to this user will remain for audit purposes.
          </p>
        </div>

        <div class="flex items-center justify-end gap-2.5 pt-3.5 border-t border-slate-100">
          <button
            type="button"
            @click="showDeleteModal = false"
            class="px-4 py-2 text-xs font-semibold rounded-xl border border-slate-200 text-slate-700 hover:bg-slate-50 transition cursor-pointer"
          >
            Cancel
          </button>
          <button
            type="button"
            @click="executeDelete"
            class="inline-flex items-center gap-2 px-4 py-2 text-xs font-bold uppercase rounded-xl shadow-sm text-white bg-rose-700 hover:bg-rose-800 transition cursor-pointer"
          >
            <i class="fa-solid fa-trash-can text-xs"></i>
            <span>Confirm Delete</span>
          </button>
        </div>
      </div>
    </Modal>
  </AuthenticatedLayout>
</template>

