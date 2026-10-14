<template>
  <Teleport to="body">
    <div
      v-if="show"
      class="fixed inset-0 z-[70] overflow-hidden"
      role="dialog"
      aria-modal="true"
    >
      <!-- Backdrop (Optimized: No heavy backdrop-blur filter to eliminate scroll lag) -->
      <div
        class="fixed inset-0 bg-slate-900/40 transition-opacity"
        @click="close"
      ></div>

      <!-- Slide-Over Drawer or Centered Modal Card -->
      <div
        class="fixed z-[70] flex flex-col bg-white overflow-hidden text-slate-800 shadow-2xl transform-gpu will-change-transform"
        :class="viewMode === 'drawer'
          ? 'inset-y-0 right-0 w-full max-w-2xl xl:max-w-3xl h-full border-l border-slate-200'
          : 'top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 w-full max-w-4xl max-h-[90vh] rounded-2xl border border-slate-200'"
      >
        <!-- ── 1. Unified Command Header (Single-row, Triage + Identity) ──────── -->
        <div class="px-5 sm:px-6 py-3.5 border-b border-slate-200 bg-white flex items-center justify-between gap-3 shrink-0">
          <!-- Left: Identity -->
          <div class="flex items-center gap-3 min-w-0">
            <div class="w-9 h-9 rounded-xl bg-blue-50 text-blue-700 border border-blue-200/80 flex items-center justify-center shrink-0 shadow-2xs">
              <i class="fa-solid fa-ticket text-sm"></i>
            </div>
            <div class="min-w-0">
              <div class="flex items-center gap-2 flex-wrap">
                <button
                  type="button"
                  @click="copyReference"
                  class="font-mono font-bold text-xs sm:text-sm px-2 py-0.5 rounded-lg bg-slate-100 hover:bg-slate-200 text-slate-800 border border-slate-200 transition inline-flex items-center gap-1.5 cursor-pointer"
                  title="Click to copy reference number"
                >
                  <span>{{ ticketRef || '—' }}</span>
                  <i class="fa-regular fa-copy text-[10px] text-slate-400 hover:text-slate-700"></i>
                </button>
                <span class="text-slate-300 hidden sm:inline">·</span>
                <span class="text-xs font-semibold text-slate-700 truncate max-w-[150px] sm:max-w-[220px]">
                  {{ ticket?.client_name || ticketDetail.client_name || 'N/A' }}
                </span>
                <span class="text-[10px] px-2 py-0.5 rounded-full font-bold bg-slate-100 text-slate-600 border border-slate-200 shrink-0">
                  {{ mapDivision(ticket?.client_division_agency || ticketDetail.client_division_agency) }}
                </span>
              </div>
            </div>
          </div>

          <!-- Right: Triage, Edit, Mode Toggle, Close -->
          <div class="flex items-center gap-1.5 shrink-0">
            <!-- Prev / Next Triage Navigator -->
            <div class="inline-flex items-center rounded-lg border border-slate-200 bg-slate-50/80 p-0.5">
              <button
                type="button"
                :disabled="!hasPrev"
                @click="emit('prev')"
                class="h-7 px-2 rounded-md text-slate-600 hover:text-slate-900 hover:bg-white disabled:opacity-30 disabled:cursor-not-allowed transition inline-flex items-center gap-1 text-xs font-medium cursor-pointer"
                title="Previous ticket (Shortcut: K or ↑)"
              >
                <i class="fa-solid fa-chevron-left text-[10px]"></i>
                <span class="hidden sm:inline text-[11px]">Prev</span>
              </button>
              <div class="h-3.5 w-px bg-slate-200"></div>
              <button
                type="button"
                :disabled="!hasNext"
                @click="emit('next')"
                class="h-7 px-2 rounded-md text-slate-600 hover:text-slate-900 hover:bg-white disabled:opacity-30 disabled:cursor-not-allowed transition inline-flex items-center gap-1 text-xs font-medium cursor-pointer"
                title="Next ticket (Shortcut: J or ↓)"
              >
                <span class="hidden sm:inline text-[11px]">Next</span>
                <i class="fa-solid fa-chevron-right text-[10px]"></i>
              </button>
            </div>

            <!-- Edit Button -->
            <button
              v-if="ticket"
              type="button"
              @click="togglePanel('edit')"
              class="h-8 px-2.5 rounded-lg border text-xs font-semibold inline-flex items-center gap-1.5 transition cursor-pointer"
              :class="activeActionPanel === 'edit'
                ? 'bg-blue-900 text-white border-blue-900 shadow-2xs'
                : 'border-slate-200 text-slate-700 bg-white hover:bg-slate-50 hover:border-slate-300'"
              title="Edit ticket parameters"
            >
              <i class="fa-solid fa-pen-to-square text-[11px]"></i>
              <span class="hidden sm:inline">{{ activeActionPanel === 'edit' ? 'Close' : 'Edit' }}</span>
            </button>

            <!-- Delete Button (Admin & Super Admin) -->
            <button
              v-if="isAdmin && ticket"
              type="button"
              @click="emit('delete', ticket)"
              class="h-8 px-2.5 rounded-lg border border-rose-200 bg-rose-50 hover:bg-rose-100 text-rose-700 hover:text-rose-800 text-xs font-semibold inline-flex items-center gap-1.5 transition cursor-pointer shadow-2xs"
              title="Delete ticket (Admin / Super Admin)"
            >
              <i class="fa-regular fa-trash-can text-[11px] text-rose-600"></i>
              <span class="hidden sm:inline">Delete</span>
            </button>

            <!-- View Mode Switcher -->
            <button
              type="button"
              @click="toggleViewMode"
              class="h-8 w-8 rounded-lg border border-slate-200 text-slate-500 hover:text-slate-800 hover:bg-slate-50 transition inline-flex items-center justify-center cursor-pointer"
              :title="viewMode === 'drawer' ? 'Switch to centered dialog' : 'Switch to side drawer'"
            >
              <i :class="viewMode === 'drawer' ? 'fa-solid fa-expand text-xs' : 'fa-solid fa-table-columns text-xs'"></i>
            </button>

            <!-- Close Button -->
            <button
              type="button"
              @click="close"
              class="h-8 w-8 rounded-lg border border-transparent text-slate-400 hover:text-slate-700 hover:bg-slate-100 transition inline-flex items-center justify-center cursor-pointer"
              title="Close (Esc)"
            >
              <i class="fa-solid fa-xmark text-sm"></i>
            </button>
          </div>
        </div>

        <!-- ── 2. Modern Progress Stepper Strip ───────────────────────────────── -->
        <div class="px-5 sm:px-6 py-2.5 bg-slate-50/70 border-b border-slate-200/80 shrink-0">
          <TicketProgressStepper :status="status" compact />
        </div>

        <!-- ── 3. Content Navigation Tabs Strip ───────────────────────────────── -->
        <div class="px-5 sm:px-6 py-2 border-b border-slate-200/80 bg-white flex items-center justify-between gap-3 shrink-0">
          <div class="inline-flex items-center p-1 bg-slate-100 rounded-xl gap-0.5 text-xs font-semibold overflow-x-auto max-w-full">
            <button
              type="button"
              @click="activeTab = 'overview'"
              class="px-3.5 py-1.5 rounded-lg transition inline-flex items-center gap-1.5 cursor-pointer"
              :class="activeTab === 'overview'
                ? 'bg-white text-slate-900 shadow-xs font-bold'
                : 'text-slate-600 hover:text-slate-900 hover:bg-slate-200/60'"
            >
              <i class="fa-solid fa-circle-info text-xs" :class="activeTab === 'overview' ? 'text-blue-600' : 'text-slate-400'"></i>
              <span>Overview & Details</span>
            </button>

            <button
              type="button"
              @click="activeTab = 'messages'"
              class="px-3.5 py-1.5 rounded-lg transition inline-flex items-center gap-1.5 cursor-pointer"
              :class="activeTab === 'messages'
                ? 'bg-white text-slate-900 shadow-xs font-bold'
                : 'text-slate-600 hover:text-slate-900 hover:bg-slate-200/60'"
            >
              <i class="fa-regular fa-comment-dots text-xs" :class="activeTab === 'messages' ? 'text-blue-600' : 'text-slate-400'"></i>
              <span>Messages</span>
              <span
                v-if="ticket?.messages_count"
                class="text-[10px] px-1.5 py-0.2 rounded-full font-bold"
                :class="activeTab === 'messages' ? 'bg-blue-100 text-blue-800' : 'bg-slate-200 text-slate-600'"
              >
                {{ ticket.messages_count }}
              </span>
            </button>

            <button
              type="button"
              @click="activeTab = 'attachments'"
              class="px-3.5 py-1.5 rounded-lg transition inline-flex items-center gap-1.5 cursor-pointer"
              :class="activeTab === 'attachments'
                ? 'bg-white text-slate-900 shadow-xs font-bold'
                : 'text-slate-600 hover:text-slate-900 hover:bg-slate-200/60'"
            >
              <i class="fa-solid fa-paperclip text-xs" :class="activeTab === 'attachments' ? 'text-blue-600' : 'text-slate-400'"></i>
              <span>Attachments</span>
              <span
                v-if="attachments.length > 0"
                class="text-[10px] px-1.5 py-0.2 rounded-full font-bold"
                :class="activeTab === 'attachments' ? 'bg-blue-100 text-blue-800' : 'bg-slate-200 text-slate-600'"
              >
                {{ attachments.length }}
              </span>
            </button>

            <button
              type="button"
              @click="activeTab = 'activity'"
              class="px-3.5 py-1.5 rounded-lg transition inline-flex items-center gap-1.5 cursor-pointer"
              :class="activeTab === 'activity'
                ? 'bg-white text-slate-900 shadow-xs font-bold'
                : 'text-slate-600 hover:text-slate-900 hover:bg-slate-200/60'"
            >
              <i class="fa-solid fa-clock-rotate-left text-xs" :class="activeTab === 'activity' ? 'text-blue-600' : 'text-slate-400'"></i>
              <span>Activity Trail</span>
              <span
                v-if="activityLogs.length > 0"
                class="text-[10px] px-1.5 py-0.2 rounded-full font-bold"
                :class="activeTab === 'activity' ? 'bg-blue-100 text-blue-800' : 'bg-slate-200 text-slate-600'"
              >
                {{ activityLogs.length }}
              </span>
            </button>
          </div>
        </div>

        <!-- ── 4. Scrollable Body ─────────────────────────────────────────────── -->
        <div v-if="ticket" class="flex-1 overflow-y-auto overscroll-contain px-5 sm:px-6 py-4 space-y-4">

        <!-- ── INLINE EXPANDABLE ACTION PANELS (Eliminates Nested Modals) ── -->

        <!-- A) Inline Assign / Reassign Staff Panel -->
        <div
          v-if="activeActionPanel === 'assign'"
          class="p-4 rounded-xl border border-blue-200 bg-blue-50/70 space-y-3 transition-all"
        >
          <div class="flex items-center justify-between border-b border-blue-200/60 pb-2">
            <h3 class="text-sm font-bold text-blue-900 flex items-center gap-1.5">
              <i class="fa-solid fa-user-plus text-xs"></i> Assign Support Staff
            </h3>
            <button type="button" @click="activeActionPanel = null" class="text-xs text-blue-700 hover:underline">
              Cancel
            </button>
          </div>

          <div class="grid grid-cols-1 md:grid-cols-2 gap-3 text-sm">
            <div>
              <label class="block text-xs font-semibold text-slate-700 mb-1">Assign To Staff *</label>
              <select
                v-model="assignForm.staff"
                class="w-full rounded-lg border-slate-300 focus:ring-2 focus:ring-blue-500 focus:border-blue-500 text-sm py-2 px-3 bg-white"
              >
                <option value="">-- Select Staff --</option>
                <option v-for="staff in staffOptions" :key="staff" :value="staff">{{ staff }}</option>
              </select>
            </div>

            <div>
              <label class="block text-xs font-semibold text-slate-700 mb-1">Request Type *</label>
              <select
                v-model="assignForm.type"
                class="w-full rounded-lg border-slate-300 focus:ring-2 focus:ring-blue-500 focus:border-blue-500 text-sm py-2 px-3 bg-white"
              >
                <option value="Technical Support">Technical Support</option>
                <option value="Livestream">Livestream</option>
                <option value="Events">Events</option>
                <option value="Others">Others</option>
              </select>
            </div>

            <!-- Optional Events details if type is Events -->
            <template v-if="assignForm.type === 'Events'">
              <div>
                <label class="block text-xs font-semibold text-slate-700 mb-1">Event Start Date *</label>
                <input
                  v-model="assignForm.eventStartDate"
                  type="date"
                  class="w-full rounded-lg border-slate-300 focus:ring-2 focus:ring-blue-500 focus:border-blue-500 text-sm py-2 px-3 bg-white"
                />
              </div>
              <div>
                <label class="block text-xs font-semibold text-slate-700 mb-1">Event End Date</label>
                <input
                  v-model="assignForm.eventEndDate"
                  type="date"
                  class="w-full rounded-lg border-slate-300 focus:ring-2 focus:ring-blue-500 focus:border-blue-500 text-sm py-2 px-3 bg-white"
                />
              </div>
              <div class="md:col-span-2">
                <label class="block text-xs font-semibold text-slate-700 mb-1">Venue</label>
                <input
                  v-model="assignForm.eventVenue"
                  type="text"
                  placeholder="e.g. STII Boardroom"
                  class="w-full rounded-lg border-slate-300 focus:ring-2 focus:ring-blue-500 focus:border-blue-500 text-sm py-2 px-3 bg-white"
                />
              </div>
            </template>
          </div>

          <div v-if="actionError" class="text-xs text-red-600 font-semibold">
            {{ actionError }}
          </div>

          <div class="flex items-center justify-end gap-2 pt-2">
            <button
              type="button"
              @click="activeActionPanel = null"
              class="px-3 py-1.5 text-xs font-medium rounded-lg border border-slate-300 bg-white hover:bg-slate-50 text-slate-700"
            >
              Cancel
            </button>
            <button
              type="button"
              :disabled="busyAction"
              @click="submitAssign"
              class="px-4 py-1.5 text-xs font-bold uppercase rounded-lg shadow-sm bg-blue-900 hover:bg-blue-800 text-white transition disabled:opacity-60 inline-flex items-center gap-1.5"
            >
              <span v-if="busyAction" class="inline-block animate-spin">↻</span>
              <span>Confirm Assignment</span>
            </button>
          </div>
        </div>

        <!-- B) Inline Complete Ticket Panel -->
        <div
          v-if="activeActionPanel === 'complete'"
          class="p-4 rounded-xl border border-emerald-200 bg-emerald-50/70 space-y-3 transition-all"
        >
          <div class="flex items-center justify-between border-b border-emerald-200/60 pb-2">
            <h3 class="text-sm font-bold text-emerald-900 flex items-center gap-1.5">
              <i class="fa-solid fa-check text-xs"></i> Complete & Resolve Ticket
            </h3>
            <button type="button" @click="activeActionPanel = null" class="text-xs text-emerald-700 hover:underline">
              Cancel
            </button>
          </div>

          <div class="grid grid-cols-1 md:grid-cols-2 gap-3 text-sm">
            <div>
              <label class="block text-xs font-semibold text-slate-700 mb-1">Support Type *</label>
              <select
                v-model="completeForm.supportType"
                class="w-full rounded-lg border-slate-300 focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500 text-sm py-2 px-3 bg-white"
              >
                <option value="Technical Support">Technical Support</option>
                <option value="Livestream">Livestream</option>
                <option value="Events">Events</option>
              </select>
            </div>

            <!-- Nature of Support (if Technical Support) -->
            <div v-if="completeForm.supportType === 'Technical Support'">
              <label class="block text-xs font-semibold text-slate-700 mb-1">Nature of Support *</label>
              <select
                v-model="completeForm.nature"
                class="w-full rounded-lg border-slate-300 focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500 text-sm py-2 px-3 bg-white"
              >
                <option value="">-- Select Nature --</option>
                <option value="Hardware">Hardware</option>
                <option value="Software">Software</option>
                <option value="Hardware & Software">Hardware & Software</option>
                <option value="System Modification">System Modification</option>
                <option value="Others">Others</option>
              </select>
            </div>

            <!-- Subcategory / Support Type -->
            <div v-if="completeForm.supportType === 'Technical Support' && completeForm.nature !== 'System Modification'">
              <label class="block text-xs font-semibold text-slate-700 mb-1">Sub-Category *</label>
              <select
                v-model="completeForm.subCategory"
                class="w-full rounded-lg border-slate-300 focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500 text-sm py-2 px-3 bg-white"
              >
                <option value="">-- Select Sub-Category --</option>
                <option v-for="sub in subcategoryOptions" :key="sub" :value="sub">{{ sub }}</option>
              </select>
            </div>

            <!-- Related Type -->
            <div>
              <label class="block text-xs font-semibold text-slate-700 mb-1">Related Type</label>
              <select
                v-model="completeForm.relatedType"
                class="w-full rounded-lg border-slate-300 focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500 text-sm py-2 px-3 bg-white"
              >
                <option value="">-- Select Related Type (Optional) --</option>
                <option v-for="rel in relatedTypeOptions" :key="rel" :value="rel">{{ rel }}</option>
              </select>
            </div>

            <!-- Actions Taken -->
            <div class="md:col-span-2">
              <label class="block text-xs font-semibold text-slate-700 mb-1">Actions Taken *</label>
              <textarea
                v-model="completeForm.actionTaken"
                rows="2"
                placeholder="Describe resolution steps performed..."
                class="w-full rounded-lg border-slate-300 focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500 text-sm py-2 px-3 bg-white"
              ></textarea>
            </div>

            <!-- Remarks -->
            <div class="md:col-span-2">
              <label class="block text-xs font-semibold text-slate-700 mb-1">Remarks</label>
              <select
                v-model="completeForm.remarksOption"
                class="w-full rounded-lg border-slate-300 focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500 text-sm py-2 px-3 bg-white mb-2"
              >
                <option v-for="opt in remarksOptions" :key="opt" :value="opt">{{ opt }}</option>
              </select>
              <input
                v-if="completeForm.remarksOption === 'Others'"
                v-model="completeForm.customRemarks"
                type="text"
                placeholder="Specify custom remarks..."
                class="w-full rounded-lg border-slate-300 focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500 text-sm py-2 px-3 bg-white"
              />
            </div>
          </div>

          <div v-if="actionError" class="text-xs text-red-600 font-semibold">
            {{ actionError }}
          </div>

          <div class="flex items-center justify-end gap-2 pt-2">
            <button
              type="button"
              @click="activeActionPanel = null"
              class="px-3 py-1.5 text-xs font-medium rounded-lg border border-slate-300 bg-white hover:bg-slate-50 text-slate-700"
            >
              Cancel
            </button>
            <button
              type="button"
              :disabled="busyAction"
              @click="submitComplete"
              class="px-4 py-1.5 text-xs font-bold uppercase rounded-lg shadow-sm bg-emerald-700 hover:bg-emerald-800 text-white transition disabled:opacity-60 inline-flex items-center gap-1.5"
            >
              <span v-if="busyAction" class="inline-block animate-spin mr-1"><i class="fa-solid fa-spinner"></i></span>
              <span>Confirm Completion</span>
            </button>
          </div>
        </div>

        <!-- C) Inline Formally Close Panel -->
        <div
          v-if="activeActionPanel === 'close'"
          class="p-4 rounded-xl border border-slate-300 bg-slate-100/90 space-y-3 transition-all"
        >
          <div class="flex items-center justify-between border-b border-slate-200 pb-2">
            <h3 class="text-sm font-bold text-slate-900 flex items-center gap-1.5">
              <i class="fa-solid fa-lock text-xs"></i> Formally Close Ticket
            </h3>
            <button type="button" @click="activeActionPanel = null" class="text-xs text-slate-600 hover:underline">
              Cancel
            </button>
          </div>

          <p class="text-xs text-slate-600">
            Formally closing this completed ticket will finalize its status and mark the lifecycle complete.
          </p>

          <div>
            <label class="block text-xs font-semibold text-slate-700 mb-1">Closing Remarks (Optional)</label>
            <textarea
              v-model="closeForm.remarks"
              rows="2"
              placeholder="e.g. Client confirmed satisfactory resolution..."
              class="w-full rounded-lg border-slate-300 focus:ring-2 focus:ring-slate-500 focus:border-slate-500 text-sm py-2 px-3 bg-white"
            ></textarea>
          </div>

          <div v-if="actionError" class="text-xs text-red-600 font-semibold">
            {{ actionError }}
          </div>

          <div class="flex items-center justify-end gap-2 pt-2">
            <button
              type="button"
              @click="activeActionPanel = null"
              class="px-3 py-1.5 text-xs font-medium rounded-lg border border-slate-300 bg-white hover:bg-slate-50 text-slate-700"
            >
              Cancel
            </button>
            <button
              type="button"
              :disabled="busyAction"
              @click="submitClose"
              class="px-4 py-1.5 text-xs font-bold uppercase rounded-lg shadow-sm bg-slate-900 hover:bg-slate-800 text-white transition disabled:opacity-60 inline-flex items-center gap-1.5"
            >
              <span v-if="busyAction" class="inline-block animate-spin mr-1"><i class="fa-solid fa-spinner"></i></span>
              <span>Finalize & Close</span>
            </button>
          </div>
        </div>

        <!-- D) Inline Quick Edit Panel -->
        <div
          v-if="activeActionPanel === 'edit'"
          class="p-4 rounded-xl border border-blue-200 bg-blue-50/50 space-y-3 transition-all"
        >
          <div class="flex items-center justify-between border-b border-blue-200 pb-2">
            <h3 class="text-sm font-bold text-blue-900 flex items-center gap-1.5">
              <i class="fa-solid fa-pen-to-square text-xs"></i> Edit Ticket Parameters
            </h3>
            <button type="button" @click="activeActionPanel = null" class="text-xs text-blue-700 hover:underline">
              Cancel
            </button>
          </div>

          <div class="grid grid-cols-1 md:grid-cols-2 gap-3 text-sm">
            <div>
              <label class="block text-xs font-semibold text-slate-700 mb-1">Request Type *</label>
              <select
                v-model="editForm.type"
                class="w-full rounded-lg border-slate-300 focus:ring-2 focus:ring-blue-500 focus:border-blue-500 text-sm py-2 px-3 bg-white"
              >
                <option v-for="tType in ['Technical Support', 'Livestream', 'Events']" :key="tType" :value="tType">{{ tType }}</option>
              </select>
            </div>

            <div>
              <label class="block text-xs font-semibold text-slate-700 mb-1">Assigned / Attended Staff</label>
              <select
                v-model="editForm.staff"
                class="w-full rounded-lg border-slate-300 focus:ring-2 focus:ring-blue-500 focus:border-blue-500 text-sm py-2 px-3 bg-white"
              >
                <option value="">-- None --</option>
                <option v-for="staff in staffOptions" :key="staff" :value="staff">{{ staff }}</option>
              </select>
            </div>

            <!-- Optional Events details if type is Events -->
            <template v-if="editForm.type === 'Events'">
              <div>
                <label class="block text-xs font-semibold text-slate-700 mb-1">Event Start Date</label>
                <input
                  v-model="editForm.eventStartDate"
                  type="date"
                  class="w-full rounded-lg border-slate-300 focus:ring-2 focus:ring-blue-500 focus:border-blue-500 text-sm py-2 px-3 bg-white"
                />
              </div>
              <div>
                <label class="block text-xs font-semibold text-slate-700 mb-1">Event End Date</label>
                <input
                  v-model="editForm.eventEndDate"
                  type="date"
                  class="w-full rounded-lg border-slate-300 focus:ring-2 focus:ring-blue-500 focus:border-blue-500 text-sm py-2 px-3 bg-white"
                />
              </div>
              <div class="md:col-span-2">
                <label class="block text-xs font-semibold text-slate-700 mb-1">Event Venue</label>
                <input
                  v-model="editForm.eventVenue"
                  type="text"
                  placeholder="Location or virtual platform"
                  class="w-full rounded-lg border-slate-300 focus:ring-2 focus:ring-blue-500 focus:border-blue-500 text-sm py-2 px-3 bg-white"
                />
              </div>
            </template>

            <div class="md:col-span-2">
              <label class="block text-xs font-semibold text-slate-700 mb-1">Problem Description</label>
              <textarea
                v-model="editForm.problem"
                rows="3"
                class="w-full rounded-lg border-slate-300 focus:ring-2 focus:ring-blue-500 focus:border-blue-500 text-sm py-2 px-3 bg-white"
              ></textarea>
            </div>

            <!-- Additional resolution fields if Completed or Closed or actions logged -->
            <template v-if="status === 'Completed' || status === 'Closed' || ticketDetail.ticket_actions_taken">
              <div>
                <label class="block text-xs font-semibold text-slate-700 mb-1">Nature of Support</label>
                <select
                  v-model="editForm.nature"
                  class="w-full rounded-lg border-slate-300 focus:ring-2 focus:ring-blue-500 focus:border-blue-500 text-sm py-2 px-3 bg-white"
                >
                  <option value="">-- None / Select --</option>
                  <option v-for="nature in SUPPORT_NATURES" :key="nature" :value="nature">{{ nature }}</option>
                </select>
              </div>

              <div>
                <label class="block text-xs font-semibold text-slate-700 mb-1">Support Sub-Type</label>
                <input
                  v-model="editForm.subCategory"
                  type="text"
                  placeholder="e.g. Troubleshooting, Installation"
                  class="w-full rounded-lg border-slate-300 focus:ring-2 focus:ring-blue-500 focus:border-blue-500 text-sm py-2 px-3 bg-white"
                />
              </div>

              <div class="md:col-span-2">
                <label class="block text-xs font-semibold text-slate-700 mb-1">Resolution & Actions Taken</label>
                <textarea
                  v-model="editForm.actionTaken"
                  rows="2"
                  class="w-full rounded-lg border-slate-300 focus:ring-2 focus:ring-blue-500 focus:border-blue-500 text-sm py-2 px-3 bg-white"
                  placeholder="Details of actions taken to resolve"
                ></textarea>
              </div>

              <div class="md:col-span-2">
                <label class="block text-xs font-semibold text-slate-700 mb-1">Remarks</label>
                <input
                  v-model="editForm.remarks"
                  type="text"
                  class="w-full rounded-lg border-slate-300 focus:ring-2 focus:ring-blue-500 focus:border-blue-500 text-sm py-2 px-3 bg-white"
                  placeholder="Optional remarks"
                />
              </div>
            </template>
          </div>

          <div v-if="actionError" class="text-xs text-red-600 font-semibold">
            {{ actionError }}
          </div>

          <div class="flex items-center justify-end gap-2 pt-2">
            <button
              type="button"
              @click="activeActionPanel = null"
              class="px-3 py-1.5 text-xs font-medium rounded-lg border border-slate-300 bg-white hover:bg-slate-50 text-slate-700"
            >
              Cancel
            </button>
            <button
              type="button"
              :disabled="busyAction"
              @click="submitEdit"
              class="px-4 py-1.5 text-xs font-bold uppercase rounded-lg shadow-sm bg-blue-900 hover:bg-blue-800 text-white transition disabled:opacity-60 inline-flex items-center gap-1.5"
            >
              <span v-if="busyAction" class="inline-block animate-spin">↻</span>
              <span>Save Changes</span>
            </button>
          </div>
        </div>

        <!-- ── TAB 1: OVERVIEW & DETAILS (Option 1: Modern 2-Column Split) ── -->
        <div v-show="activeTab === 'overview'" class="space-y-4">
          <div class="grid grid-cols-1 lg:grid-cols-12 gap-4 sm:gap-5 items-start">
            <!-- LEFT COLUMN: Problem Narrative & Resolution (7 cols on lg) -->
            <div class="lg:col-span-7 space-y-4">
              <!-- Problem Narrative Block -->
              <div class="p-4 sm:p-5 rounded-2xl bg-white border border-slate-200/90 shadow-2xs space-y-3">
                <div class="flex items-center justify-between pb-2 border-b border-slate-100">
                  <h4 class="text-xs font-bold uppercase tracking-wider text-slate-400 flex items-center gap-2">
                    <i class="fa-solid fa-align-left text-blue-600 text-xs"></i>
                    Problem Description
                  </h4>
                  <span class="text-[11px] font-semibold px-2 py-0.5 rounded-md bg-slate-100 text-slate-600">
                    {{ ticketDetail.ticket_type || 'Technical Support' }}
                  </span>
                </div>

                <div class="text-xs sm:text-sm text-slate-800 font-normal leading-relaxed whitespace-pre-wrap bg-slate-50/70 p-3.5 rounded-xl border border-slate-200/60">
                  {{ ticketDetail.ticket_problem_that_needed_support || 'No description provided.' }}
                </div>
              </div>

              <!-- Event Information Block (Visible when Events type or event details exist) -->
              <div
                v-if="(ticketDetail.ticket_type === 'Events' || ticket?.event || ticketDetail.event_start_date) && (ticket?.event?.event_start_date || ticketDetail.event_start_date || ticket?.event?.event_venue || ticketDetail.event_venue)"
                class="p-4 sm:p-5 rounded-2xl bg-blue-50/40 border border-blue-200/80 shadow-2xs space-y-3"
              >
                <div class="flex items-center justify-between pb-2 border-b border-blue-200/60">
                  <h4 class="text-xs font-bold uppercase tracking-wider text-blue-800 flex items-center gap-2">
                    <i class="fa-solid fa-calendar-check text-blue-600 text-xs"></i>
                    Event Information
                  </h4>
                  <span class="text-[10px] font-bold px-2 py-0.5 rounded-full bg-blue-100 text-blue-800 border border-blue-200">
                    Event Details
                  </span>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 text-xs">
                  <div v-if="ticket?.event?.event_start_date || ticketDetail.event_start_date">
                    <span class="text-slate-400 block text-[11px]">Start Date:</span>
                    <span class="font-semibold text-slate-700">{{ formatDateTime(ticket?.event?.event_start_date || ticketDetail.event_start_date) }}</span>
                  </div>
                  <div v-if="ticket?.event?.event_end_date || ticketDetail.event_end_date">
                    <span class="text-slate-400 block text-[11px]">End Date:</span>
                    <span class="font-semibold text-slate-700">{{ formatDateTime(ticket?.event?.event_end_date || ticketDetail.event_end_date) }}</span>
                  </div>
                  <div v-if="ticket?.event?.event_venue || ticketDetail.event_venue" class="sm:col-span-2">
                    <span class="text-slate-400 block text-[11px]">Venue:</span>
                    <span class="font-semibold text-slate-700">{{ ticket?.event?.event_venue || ticketDetail.event_venue }}</span>
                  </div>
                </div>
              </div>

              <!-- Resolution & Actions Taken (Visible when completed, closed, or actions logged) -->
              <div
                v-if="status === 'Completed' || status === 'Closed' || ticketDetail.ticket_actions_taken"
                class="p-4 sm:p-5 rounded-2xl bg-emerald-50/40 border border-emerald-200/90 shadow-2xs space-y-3"
              >
                <div class="flex items-center justify-between pb-2 border-b border-emerald-200/60">
                  <h4 class="text-xs font-bold uppercase tracking-wider text-emerald-800 flex items-center gap-2">
                    <i class="fa-solid fa-circle-check text-emerald-600 text-xs"></i>
                    Resolution & Actions Taken
                  </h4>
                  <span class="text-[10px] font-bold px-2 py-0.5 rounded-full bg-emerald-100 text-emerald-800 border border-emerald-200">
                    Resolved
                  </span>
                </div>

                <div class="text-xs sm:text-sm text-slate-800 bg-white p-3.5 rounded-xl border border-emerald-200/80 whitespace-pre-wrap leading-relaxed shadow-2xs">
                  {{ ticketDetail.ticket_actions_taken || 'Resolution logged upon ticket completion.' }}
                </div>

                <div v-if="ticketDetail.ticket_nature_of_support || ticketDetail.ticket_support_type" class="grid grid-cols-2 gap-2 text-xs pt-1">
                  <div v-if="ticketDetail.ticket_nature_of_support">
                    <span class="text-slate-400 block text-[11px]">Nature of Support:</span>
                    <span class="font-semibold text-slate-700">{{ ticketDetail.ticket_nature_of_support }}</span>
                  </div>
                  <div v-if="ticketDetail.ticket_support_type">
                    <span class="text-slate-400 block text-[11px]">Support Sub-Type:</span>
                    <span class="font-semibold text-slate-700">{{ ticketDetail.ticket_support_type }}</span>
                  </div>
                </div>

                <div v-if="ticketDetail.ticket_remarks" class="pt-2 border-t border-emerald-200/60 text-xs">
                  <span class="text-slate-400 block text-[11px]">Remarks:</span>
                  <span class="font-medium text-slate-700">{{ ticketDetail.ticket_remarks }}</span>
                </div>
              </div>
            </div>

            <!-- RIGHT COLUMN: Compact Metadata Sidebar (5 cols on lg) -->
            <div class="lg:col-span-5 space-y-4">
              <div class="p-4 sm:p-5 rounded-2xl bg-slate-50/80 border border-slate-200/90 shadow-2xs space-y-3.5">
                <h4 class="text-xs font-bold uppercase tracking-wider text-slate-400 flex items-center gap-1.5 pb-2 border-b border-slate-200/80">
                  <i class="fa-solid fa-sliders text-xs text-slate-400"></i>
                  Ticket Attributes
                </h4>

                <div class="space-y-3 text-xs">
                  <!-- Status & SLA -->
                  <div class="flex items-center justify-between gap-2">
                    <span class="text-slate-500 font-medium shrink-0">Status</span>
                    <span
                      class="px-2 py-0.5 rounded-lg text-xs font-bold uppercase tracking-wide border shadow-2xs"
                      :class="{
                        'text-blue-700 bg-blue-100 border-blue-200': status === 'New',
                        'text-amber-700 bg-amber-100 border-amber-200': status === 'Pending',
                        'text-emerald-700 bg-emerald-100 border-emerald-200': status === 'In Progress',
                        'text-purple-700 bg-purple-100 border-purple-200': status === 'Completed',
                        'text-slate-700 bg-slate-200 border-slate-300': status === 'Closed'
                      }"
                    >
                      {{ status || 'N/A' }}
                    </span>
                  </div>

                  <div class="flex items-center justify-between gap-2 flex-wrap">
                    <span class="text-slate-500 font-medium shrink-0">SLA</span>
                    <TicketSlaBadge :ticket="ticket" :compact="true" />
                  </div>

                  <!-- Assigned Staff -->
                  <div class="flex items-center justify-between">
                    <span class="text-slate-500 font-medium">Assigned To</span>
                    <span class="font-semibold text-slate-800 inline-flex items-center gap-1.5">
                      <i class="fa-solid fa-user text-[11px] text-blue-600"></i>
                      {{ ticketDetail.ticket_assigned_staff || ticketDetail.ticket_attended_by || 'Unassigned' }}
                    </span>
                  </div>

                  <!-- Client Name -->
                  <div class="flex items-center justify-between">
                    <span class="text-slate-500 font-medium">Client</span>
                    <span class="font-semibold text-slate-800 text-right truncate max-w-[160px]">{{ ticket?.client_name || ticketDetail.client_name || 'N/A' }}</span>
                  </div>

                  <!-- Division -->
                  <div class="flex items-center justify-between">
                    <span class="text-slate-500 font-medium">Division / Agency</span>
                    <span class="font-semibold text-slate-800">{{ mapDivision(ticket?.client_division_agency || ticketDetail.client_division_agency) }}</span>
                  </div>

                  <!-- Client Type -->
                  <div v-if="ticket?.client_type || ticketDetail.client_type" class="flex items-center justify-between">
                    <span class="text-slate-500 font-medium">Client Type</span>
                    <span class="font-medium text-slate-700">{{ ticket?.client_type || ticketDetail.client_type }}</span>
                  </div>

                  <!-- Timestamps Section -->
                  <div class="pt-2.5 border-t border-slate-200/80 space-y-2 text-[11px]">
                    <div class="flex items-center justify-between text-slate-500">
                      <span>Requested:</span>
                      <span class="font-medium text-slate-700">{{ formatDateTime(ticket?.date_requested || ticketDetail.date_requested || ticket?.created_at || ticketDetail.created_at) }}</span>
                    </div>
                    <div v-if="ticketDetail.ticket_attended_at || ticketDetail.ticket_attended_by" class="flex items-center justify-between text-slate-500">
                      <span>Attended:</span>
                      <span class="font-medium text-slate-700">
                        {{ ticketDetail.ticket_attended_by || 'Staff' }}
                        <span v-if="ticketDetail.ticket_attended_at">({{ formatDateTime(ticketDetail.ticket_attended_at) }})</span>
                      </span>
                    </div>
                    <div v-if="ticketDetail.date_completed" class="flex items-center justify-between text-slate-500">
                      <span>Completed:</span>
                      <span class="font-medium text-slate-700">{{ formatDateTime(ticketDetail.date_completed) }}</span>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- ── TAB 2: MESSAGES & CHAT (Zero Nested Scroll Conflict) ──────── -->
        <div v-show="activeTab === 'messages'" class="pt-1 space-y-3">
          <!-- Client Email Notification Banner / Indicator -->
          <div
            v-if="clientEmail"
            class="flex items-center justify-between gap-3 px-3.5 py-2.5 rounded-xl border border-blue-200/80 bg-blue-50/70 text-xs shadow-2xs"
          >
            <div class="flex items-center gap-2.5 text-blue-900 min-w-0">
              <span class="w-7 h-7 rounded-lg bg-blue-600 text-white flex items-center justify-center shrink-0 shadow-2xs">
                <i class="fa-solid fa-envelope text-xs"></i>
              </span>
              <div class="truncate">
                <div class="font-bold text-slate-800 flex items-center gap-1.5">
                  <span>Client Email Notifications Active</span>
                  <span class="inline-flex items-center px-1.5 py-0.2 rounded-full text-[10px] font-bold bg-blue-200/80 text-blue-900">
                    Auto-alert
                  </span>
                </div>
                <p class="text-slate-600 text-[11px] truncate mt-0.5">
                  Messages you send here will be emailed directly to
                  <span class="font-semibold text-blue-700 underline">{{ clientEmail }}</span>
                  with a direct link to reply.
                </p>
              </div>
            </div>
            <span class="hidden md:inline-flex items-center gap-1 text-[11px] font-semibold text-blue-700 shrink-0">
              <i class="fa-solid fa-circle-check text-blue-600 text-[10px]"></i>
              <span>Deliverable</span>
            </span>
          </div>

          <div
            v-else
            class="flex items-center justify-between gap-3 px-3.5 py-2.5 rounded-xl border border-amber-200/80 bg-amber-50/70 text-xs shadow-2xs"
          >
            <div class="flex items-center gap-2.5 text-amber-900 min-w-0">
              <span class="w-7 h-7 rounded-lg bg-amber-500 text-white flex items-center justify-center shrink-0 shadow-2xs">
                <i class="fa-solid fa-triangle-exclamation text-xs"></i>
              </span>
              <div class="truncate">
                <div class="font-bold text-slate-800 flex items-center gap-1.5">
                  <span>No Client Email Registered</span>
                  <span class="inline-flex items-center px-1.5 py-0.2 rounded-full text-[10px] font-bold bg-amber-200/80 text-amber-900">
                    Web-only
                  </span>
                </div>
                <p class="text-slate-600 text-[11px] truncate mt-0.5">
                  The client did not provide an email. Messages are only visible when they visit their online tracking link.
                </p>
              </div>
            </div>
            <span class="hidden md:inline-flex items-center gap-1 text-[11px] font-semibold text-amber-700 shrink-0">
              <i class="fa-solid fa-circle-info text-amber-600 text-[10px]"></i>
              <span>Tracking Portal Only</span>
            </span>
          </div>

          <TicketMessageThread
            v-if="ticket?.client_referenceNo"
            :client-reference-no="ticket.client_referenceNo"
            sender-role="staff"
          />
        </div>

        <!-- ── TAB 3: ATTACHMENTS ───────────────────────────────────────── -->
        <div v-show="activeTab === 'attachments'" class="space-y-3">
          <div class="p-4 sm:p-5 rounded-2xl border border-slate-200 bg-white shadow-2xs space-y-3">
            <div class="flex items-center justify-between">
              <h4 class="text-xs font-bold uppercase text-slate-400 tracking-wider">
                Attachments ({{ attachments.length }})
              </h4>
              <span v-if="loadingAttachments" class="text-xs text-slate-400 animate-pulse">Loading files…</span>
            </div>

            <div v-if="!loadingAttachments && attachments.length === 0" class="text-xs text-slate-500 italic py-8 text-center">
              <i class="fa-solid fa-folder-open text-2xl text-slate-300 block mb-2"></i>
              No attachments submitted with this ticket.
            </div>

            <div v-else class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3">
              <div
                v-for="att in attachments"
                :key="att.id"
                class="border border-slate-200 rounded-xl p-2.5 bg-slate-50 flex flex-col justify-between"
              >
                <div class="flex items-center justify-center bg-white rounded-lg p-2 min-h-[90px] border border-slate-200/60 mb-2">
                  <img
                    v-if="att.is_image"
                    :src="att.url"
                    :alt="att.name"
                    loading="lazy"
                    class="max-h-24 object-contain rounded"
                  />
                  <div v-else class="text-center">
                    <i class="fa-solid fa-file text-2xl text-slate-400 mb-1 block"></i>
                    <p class="text-[10px] text-slate-500">{{ att.mime || 'file' }}</p>
                  </div>
                </div>

                <div class="text-xs font-medium text-slate-700 truncate mb-2" :title="att.name">
                  {{ att.name }}
                </div>

                <div class="flex items-center gap-2 mt-auto">
                  <a
                    :href="att.url"
                    target="_blank"
                    rel="noopener noreferrer"
                    class="flex-1 text-center text-xs py-1.5 rounded-lg border border-slate-300 bg-white hover:bg-slate-100 text-slate-700 font-medium inline-flex items-center justify-center gap-1 cursor-pointer"
                  >
                    <i class="fa-regular fa-eye text-[11px]"></i>
                    <span>View</span>
                  </a>
                  <a
                    :href="`/tickets/${encodeURIComponent(ticket.client_referenceNo)}/attachments/${att.id}`"
                    download
                    class="flex-1 text-center text-xs py-1.5 rounded-lg bg-blue-900 hover:bg-blue-800 text-white font-medium inline-flex items-center justify-center gap-1 cursor-pointer"
                  >
                    <i class="fa-solid fa-download text-[11px]"></i>
                    <span>Download</span>
                  </a>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- ── TAB 4: ACTIVITY TRAIL ────────────────────────────────────── -->
        <div v-show="activeTab === 'activity'" class="space-y-3">
          <div class="p-4 sm:p-5 rounded-2xl border border-slate-200 bg-white shadow-2xs">
            <h4 class="text-xs font-bold uppercase text-slate-400 tracking-wider mb-3">
              Activity History & Audit Trail ({{ activityLogs.length }})
            </h4>
            <TicketActivityTimeline :logs="activityLogs" :loading="loadingLogs" />
          </div>
        </div>
      </div>

      <!-- ── 5. Sticky Action Footer ────────────────────────────────────────── -->
      <div class="px-5 sm:px-6 py-3 border-t border-slate-200 bg-slate-50/95 flex flex-wrap items-center justify-between gap-3 shrink-0">
        <div class="flex items-center gap-2">
          <span class="text-xs text-slate-400 font-semibold uppercase tracking-wider">Status:</span>
          <span
            class="px-2.5 py-1 rounded-lg text-xs font-bold uppercase tracking-wide border shadow-2xs"
            :class="{
              'text-blue-700 bg-blue-100 border-blue-200': status === 'New',
              'text-amber-700 bg-amber-100 border-amber-200': status === 'Pending',
              'text-emerald-700 bg-emerald-100 border-emerald-200': status === 'In Progress',
              'text-purple-700 bg-purple-100 border-purple-200': status === 'Completed',
              'text-slate-700 bg-slate-200 border-slate-300': status === 'Closed'
            }"
          >
            {{ status || 'N/A' }}
          </span>
          <TicketSlaBadge :ticket="ticket" />
        </div>

        <!-- Dynamic Action Buttons based on status -->
        <div class="flex items-center gap-2 flex-wrap">
          <!-- Assign / Reassign Staff (Admin only) -->
          <button
            v-if="isAdmin && (status === 'New' || status === 'Pending' || status === 'In Progress')"
            type="button"
            @click="togglePanel('assign')"
            class="px-3.5 py-1.5 text-xs font-bold uppercase rounded-xl shadow-xs transition inline-flex items-center gap-1.5 cursor-pointer"
            :class="activeActionPanel === 'assign'
              ? 'bg-blue-800 text-white'
              : 'bg-blue-900 hover:bg-blue-800 text-white'"
          >
            <i class="fa-solid fa-user-plus text-xs"></i>
            <span>{{ activeActionPanel === 'assign' ? 'Cancel Assign' : (status === 'New' ? 'Assign Staff' : 'Reassign Staff') }}</span>
          </button>

          <!-- Attend Ticket (Pending) -->
          <button
            v-if="allowStatusChange && status === 'Pending'"
            type="button"
            :disabled="busyAction"
            @click="attendTicket"
            class="px-3.5 py-1.5 text-xs font-bold uppercase rounded-xl shadow-xs bg-amber-600 hover:bg-amber-700 text-white transition inline-flex items-center gap-1.5 disabled:opacity-60 cursor-pointer"
          >
            <i v-if="busyAction && activeActionType === 'attend'" class="fa-solid fa-spinner fa-spin text-xs"></i>
            <i v-else class="fa-solid fa-bolt text-xs"></i>
            <span>Attend Ticket</span>
          </button>

          <!-- Complete Ticket (In Progress) -->
          <button
            v-if="allowStatusChange && status === 'In Progress'"
            type="button"
            @click="togglePanel('complete')"
            class="px-3.5 py-1.5 text-xs font-bold uppercase rounded-xl shadow-xs transition inline-flex items-center gap-1.5 cursor-pointer"
            :class="activeActionPanel === 'complete'
              ? 'bg-emerald-800 text-white'
              : 'bg-emerald-700 hover:bg-emerald-800 text-white'"
          >
            <i class="fa-solid fa-check text-xs"></i>
            <span>{{ activeActionPanel === 'complete' ? 'Cancel Complete' : 'Mark as Complete' }}</span>
          </button>

          <!-- Formally Close Ticket (Completed) -->
          <button
            v-if="allowStatusChange && status === 'Completed'"
            type="button"
            @click="togglePanel('close')"
            class="px-3.5 py-1.5 text-xs font-bold uppercase rounded-xl shadow-xs transition inline-flex items-center gap-1.5 cursor-pointer"
            :class="activeActionPanel === 'close'
              ? 'bg-slate-900 text-white'
              : 'bg-slate-800 hover:bg-slate-900 text-white'"
          >
            <i class="fa-solid fa-lock text-xs"></i>
            <span>{{ activeActionPanel === 'close' ? 'Cancel Close' : 'Formally Close Ticket' }}</span>
          </button>

          <!-- Closed Finalized -->
          <span
            v-if="status === 'Closed'"
            class="text-xs font-semibold text-slate-600 bg-slate-200/80 px-3 py-1.5 rounded-xl inline-flex items-center gap-1.5"
          >
            <i class="fa-solid fa-check-double text-slate-500 text-xs"></i>
            <span>Lifecycle Finalized</span>
          </span>
        </div>
      </div>
    </div>
  </div>
</Teleport>
</template>

<script setup>
import { ref, computed, watch, reactive, onMounted, onBeforeUnmount } from 'vue'
import { router, usePage } from '@inertiajs/vue3'
import { useToast } from 'vue-toastification'
import TicketSlaBadge from '@/Components/TicketSlaBadge.vue'
import TicketProgressStepper from '@/Components/TicketProgressStepper.vue'
import TicketMessageThread from '@/Components/TicketMessageThread.vue'
import TicketActivityTimeline from '@/Components/TicketActivityTimeline.vue'
import { useStaffList } from '@/Composables/useStaffList'
import {
  TICKET_TYPES,
  SUPPORT_NATURES,
  SUBCATEGORIES_BY_NATURE,
  RELATED_TYPES,
  REMARKS_OPTIONS,
  mapDivision,
} from '@/Support/ticketClassifications'

const toast = useToast()
const page = usePage()
const { staffOptions } = useStaffList()

const props = defineProps({
  show: { type: Boolean, default: false },
  ticket: { type: Object, default: null },
  autoFetchLogs: { type: Boolean, default: true },
  fromMyTicket: { type: Boolean, default: false },
  allowStatusChange: { type: Boolean, default: true },
  defaultMode: { type: String, default: 'drawer' }, // 'drawer' | 'modal'
  hasPrev: { type: Boolean, default: false },
  hasNext: { type: Boolean, default: false },
  initialTab: { type: String, default: 'overview' }, // 'overview' | 'messages' | 'attachments' | 'activity'
  initialAction: { type: String, default: null }, // 'edit' | 'assign' | 'complete' | 'close' | null
})

const emit = defineEmits(['close', 'updated', 'prev', 'next', 'delete'])

// ── Tab Navigation State ──────────────────────────────────────────────────────
const activeTab = ref(props.initialTab || 'overview')

function copyReference() {
  if (!ticketRef.value) return
  if (navigator?.clipboard?.writeText) {
    navigator.clipboard.writeText(ticketRef.value)
    toast.success('Reference number copied!')
  }
}

// ── Prevent Background Page Scroll When Modal Is Open ────────────────────────
watch(
  () => props.show,
  (isOpen) => {
    if (typeof document !== 'undefined') {
      if (isOpen) {
        document.body.style.overflow = 'hidden'
      } else {
        document.body.style.overflow = ''
      }
    }
  },
  { immediate: true }
)

// ── View Mode: Slide-Over Drawer vs Centered Modal ────────────────────────────
const viewMode = ref(localStorage.getItem('ticket_details_view_mode') || props.defaultMode || 'drawer')

function toggleViewMode() {
  viewMode.value = viewMode.value === 'drawer' ? 'modal' : 'drawer'
  localStorage.setItem('ticket_details_view_mode', viewMode.value)
}

function handleKeyDown(e) {
  if (!props.show) return
  if (e.key === 'Escape') {
    close()
    return
  }
  const tag = (e.target && e.target.tagName) ? e.target.tagName.toLowerCase() : ''
  if (tag === 'input' || tag === 'textarea' || tag === 'select') return

  if (e.key === 'ArrowUp' || e.key === 'k') {
    e.preventDefault()
    if (props.hasPrev) emit('prev')
  } else if (e.key === 'ArrowDown' || e.key === 'j') {
    e.preventDefault()
    if (props.hasNext) emit('next')
  }
}

onMounted(() => window.addEventListener('keydown', handleKeyDown))
onBeforeUnmount(() => {
  window.removeEventListener('keydown', handleKeyDown)
  if (typeof document !== 'undefined') {
    document.body.style.overflow = ''
  }
})

// ── Roles & Identity ──────────────────────────────────────────────────────────
const userRole = computed(() => String(page.props?.auth?.user?.role || '').trim().toLowerCase())
const isAdmin = computed(() => ['admin', 'super admin'].includes(userRole.value))
const currentUserName = computed(() => page.props?.auth?.user?.name || page.props?.auth?.user?.username || 'Staff')

// ── Reactive Local Ticket (For Immediate UI Updates After Edits/Actions) ───────
const localTicket = ref(null)

watch(
  () => props.ticket,
  (newTicket) => {
    if (newTicket) {
      const cloned = JSON.parse(JSON.stringify(newTicket))
      // Ensure cloned.detail exists even if caller passed a flat object
      if (!cloned.detail) {
        cloned.detail = { ...cloned }
      }
      if (!cloned.event && cloned.event_start_date) {
        cloned.event = {
          event_start_date: cloned.event_start_date,
          event_end_date: cloned.event_end_date,
          event_venue: cloned.event_venue,
        }
      }
      localTicket.value = cloned
      if (activeActionPanel.value) {
        initPanelData(activeActionPanel.value)
      }
    } else {
      localTicket.value = null
    }
  },
  { immediate: true, deep: true }
)

const ticket = computed(() => localTicket.value || props.ticket)
const ticketDetail = computed(() => ticket.value?.detail || ticket.value || {})
const ticketEvent = computed(() => ticket.value?.event || ticket.value || {})

// ── Core Computed Properties ──────────────────────────────────────────────────
const ticketId = computed(() => {
  return ticket.value?.detail?.ticket_id ?? ticket.value?.ticket_id ?? ticket.value?.id ?? null
})

const ticketRef = computed(() => {
  return ticket.value?.client_referenceNo ?? ticket.value?.detail?.client_referenceNo ?? ''
})

const status = computed(() => {
  return (ticket.value?.detail?.ticket_status ?? ticket.value?.ticket_status ?? 'New').trim()
})

const clientEmail = computed(() => {
  return (
    ticket.value?.client_email ||
    ticket.value?.detail?.client_email ||
    ticketDetail.value?.client_email ||
    ''
  ).trim()
})

// ── Sub-Category & Related Options (Centralized) ──────────────────────────────
const subcategoryOptions = computed(() => {
  const nature = completeForm.nature || 'Hardware'
  return SUBCATEGORIES_BY_NATURE[nature] || SUBCATEGORIES_BY_NATURE['Hardware'] || []
})

const relatedTypeOptions = RELATED_TYPES
const remarksOptions = REMARKS_OPTIONS

// ── Inline Action Panel State ─────────────────────────────────────────────────
const activeActionPanel = ref(null) // 'assign' | 'complete' | 'close' | 'edit' | null
const busyAction = ref(false)
const activeActionType = ref('')
const actionError = ref('')
const showLogs = ref(false)

function togglePanel(panel) {
  if (!props.allowStatusChange && (panel === 'complete' || panel === 'close')) {
    return
  }
  actionError.value = ''
  if (activeActionPanel.value === panel) {
    activeActionPanel.value = null
  } else {
    activeActionPanel.value = panel
    initPanelData(panel)
  }
}

// ── Form States for Inline Actions ────────────────────────────────────────────
const assignForm = reactive({
  staff: '',
  type: 'Technical Support',
  eventStartDate: '',
  eventEndDate: '',
  eventVenue: '',
})

const completeForm = reactive({
  supportType: 'Technical Support',
  nature: 'Hardware',
  subCategory: 'Troubleshoot',
  relatedType: '',
  actionTaken: '',
  remarksOption: 'N/A',
  customRemarks: '',
})

const closeForm = reactive({
  remarks: '',
})

const editForm = reactive({
  type: 'Technical Support',
  staff: '',
  problem: '',
  nature: '',
  subCategory: '',
  actionTaken: '',
  remarks: '',
  eventStartDate: '',
  eventEndDate: '',
  eventVenue: '',
})

function initPanelData(panel) {
  const d = ticketDetail.value
  const ev = ticketEvent.value
  if (panel === 'assign') {
    assignForm.staff = d?.ticket_assigned_staff || d?.ticket_attended_by || ''
    assignForm.type = d?.ticket_type || 'Technical Support'
    assignForm.eventStartDate = ev?.event_start_date || ''
    assignForm.eventEndDate = ev?.event_end_date || ''
    assignForm.eventVenue = ev?.event_venue || ''
  } else if (panel === 'complete') {
    completeForm.supportType = d?.ticket_type || 'Technical Support'
    completeForm.nature = d?.ticket_nature_of_support || 'Hardware'
    completeForm.subCategory = d?.ticket_support_type || 'Troubleshoot'
    completeForm.relatedType = d?.ticket_related_type || ''
    completeForm.actionTaken = d?.ticket_actions_taken || ''
    completeForm.remarksOption = d?.ticket_remarks || 'N/A'
    completeForm.customRemarks = ''
  } else if (panel === 'close') {
    closeForm.remarks = ''
  } else if (panel === 'edit') {
    editForm.type = d?.ticket_type || 'Technical Support'
    editForm.staff = d?.ticket_assigned_staff || d?.ticket_attended_by || ''
    editForm.problem = d?.ticket_problem_that_needed_support || ''
    editForm.nature = d?.ticket_nature_of_support || ''
    editForm.subCategory = d?.ticket_support_type || ''
    editForm.actionTaken = d?.ticket_actions_taken || ''
    editForm.remarks = d?.ticket_remarks || ''
    editForm.eventStartDate = ev?.event_start_date || ''
    editForm.eventEndDate = ev?.event_end_date || ''
    editForm.eventVenue = ev?.event_venue || ''
  }
}

// ── Action Submissions ────────────────────────────────────────────────────────
async function submitAssign() {
  if (!assignForm.staff) {
    actionError.value = 'Please select a staff member to assign.'
    return
  }
  busyAction.value = true
  actionError.value = ''

  const payload = {
    ticket_type: assignForm.type,
    ticket_assigned_staff: assignForm.staff,
    ticket_status: 'Pending',
    ticket_assigned_by: currentUserName.value,
  }

  if (assignForm.type === 'Events') {
    if (!assignForm.eventStartDate) {
      actionError.value = 'Please enter an event start date.'
      busyAction.value = false
      return
    }
    payload.event_start_date = assignForm.eventStartDate
    payload.event_end_date = assignForm.eventEndDate || null
    payload.event_venue = assignForm.eventVenue || null
  }

  router.put(`/tickets/${ticketId.value}`, payload, {
    preserveScroll: true,
    onSuccess: () => {
      toast.success(`Assigned to ${assignForm.staff} (Pending)`)
      activeActionPanel.value = null

      if (localTicket.value) {
        if (!localTicket.value.detail) localTicket.value.detail = {}
        localTicket.value.detail.ticket_type = assignForm.type
        localTicket.value.detail.ticket_assigned_staff = assignForm.staff
        localTicket.value.detail.ticket_status = 'Pending'
        localTicket.value.ticket_status = 'Pending'
      }
      if (props.ticket && props.ticket.detail) {
        props.ticket.detail.ticket_type = assignForm.type
        props.ticket.detail.ticket_assigned_staff = assignForm.staff
        props.ticket.detail.ticket_status = 'Pending'
        props.ticket.ticket_status = 'Pending'
      }

      fetchActivityLogs(ticketRef.value)
      emit('updated', { id: ticketId.value, ref: ticketRef.value, status: 'Pending', assignedTo: assignForm.staff })
    },
    onError: (err) => {
      actionError.value = err?.message || 'Failed to assign staff.'
    },
    onFinish: () => {
      busyAction.value = false
    },
  })
}

async function attendTicket() {
  if (!props.allowStatusChange || !ticketId.value) return
  busyAction.value = true
  activeActionType.value = 'attend'

  router.put(`/tickets-attend/${ticketId.value}`, {
    ticket_status: 'In Progress',
  }, {
    preserveScroll: true,
    onSuccess: (page) => {
      const flashMsg = page?.props?.flash?.success || 'Ticket attended and now In Progress!'
      toast.success(flashMsg)
      if (localTicket.value) {
        if (!localTicket.value.detail) localTicket.value.detail = {}
        localTicket.value.detail.ticket_status = 'In Progress'
        localTicket.value.ticket_status = 'In Progress'
        localTicket.value.detail.ticket_attended_by = currentUserName.value
        localTicket.value.detail.ticket_attended_at = new Date().toISOString()
      }
      if (props.ticket && props.ticket.detail) {
        props.ticket.detail.ticket_status = 'In Progress'
        props.ticket.ticket_status = 'In Progress'
        props.ticket.detail.ticket_attended_by = currentUserName.value
        props.ticket.detail.ticket_attended_at = new Date().toISOString()
      }

      fetchActivityLogs(ticketRef.value)
      emit('updated', { id: ticketId.value, ref: ticketRef.value, status: 'In Progress' })
    },
    onError: (err) => {
      toast.error(err?.message || 'Failed to attend ticket.')
    },
    onFinish: () => {
      busyAction.value = false
      activeActionType.value = ''
    },
  })
}

async function submitComplete() {
  if (!completeForm.actionTaken.trim()) {
    actionError.value = 'Please provide details for Actions Taken.'
    return
  }

  busyAction.value = true
  actionError.value = ''

  const remarks = completeForm.remarksOption === 'Others'
    ? (completeForm.customRemarks.trim() || 'Others')
    : completeForm.remarksOption

  const payload = {
    ticket_status: 'Completed',
    ticket_type: completeForm.supportType,
    ticket_nature_of_support: completeForm.nature,
    ticket_support_type: completeForm.subCategory,
    ticket_related_type: completeForm.relatedType || null,
    ticket_actions_taken: completeForm.actionTaken.trim(),
    ticket_remarks: remarks,
  }

  router.put(`/tickets-complete/${ticketId.value}`, payload, {
    preserveScroll: true,
    onSuccess: () => {
      toast.success('Ticket completed successfully!')
      activeActionPanel.value = null

      if (localTicket.value) {
        if (!localTicket.value.detail) localTicket.value.detail = {}
        localTicket.value.detail.ticket_status = 'Completed'
        localTicket.value.ticket_status = 'Completed'
        localTicket.value.detail.ticket_type = completeForm.supportType
        localTicket.value.detail.ticket_nature_of_support = completeForm.nature
        localTicket.value.detail.ticket_support_type = completeForm.subCategory
        localTicket.value.detail.ticket_related_type = completeForm.relatedType || null
        localTicket.value.detail.ticket_actions_taken = completeForm.actionTaken.trim()
        localTicket.value.detail.ticket_remarks = remarks
        localTicket.value.detail.date_completed = new Date().toISOString()
      }
      if (props.ticket && props.ticket.detail) {
        props.ticket.detail.ticket_status = 'Completed'
        props.ticket.ticket_status = 'Completed'
        props.ticket.detail.ticket_type = completeForm.supportType
        props.ticket.detail.ticket_nature_of_support = completeForm.nature
        props.ticket.detail.ticket_support_type = completeForm.subCategory
        props.ticket.detail.ticket_related_type = completeForm.relatedType || null
        props.ticket.detail.ticket_actions_taken = completeForm.actionTaken.trim()
        props.ticket.detail.ticket_remarks = remarks
        props.ticket.detail.date_completed = new Date().toISOString()
      }

      fetchActivityLogs(ticketRef.value)
      emit('updated', { id: ticketId.value, ref: ticketRef.value, status: 'Completed' })
    },
    onError: (err) => {
      actionError.value = err?.message || 'Failed to complete ticket.'
    },
    onFinish: () => {
      busyAction.value = false
    },
  })
}

async function submitClose() {
  busyAction.value = true
  actionError.value = ''

  router.put(`/tickets-close/${ticketId.value}`, {
    ticket_remarks: closeForm.remarks.trim() || null,
  }, {
    preserveScroll: true,
    onSuccess: () => {
      toast.success('Ticket has been formally closed.')
      activeActionPanel.value = null

      if (localTicket.value) {
        if (!localTicket.value.detail) localTicket.value.detail = {}
        localTicket.value.detail.ticket_status = 'Closed'
        localTicket.value.ticket_status = 'Closed'
        if (closeForm.remarks.trim()) {
          localTicket.value.detail.ticket_remarks = closeForm.remarks.trim()
        }
      }
      if (props.ticket && props.ticket.detail) {
        props.ticket.detail.ticket_status = 'Closed'
        props.ticket.ticket_status = 'Closed'
        if (closeForm.remarks.trim()) {
          props.ticket.detail.ticket_remarks = closeForm.remarks.trim()
        }
      }

      fetchActivityLogs(ticketRef.value)
      emit('updated', { id: ticketId.value, ref: ticketRef.value, status: 'Closed' })
    },
    onError: (err) => {
      actionError.value = err?.message || 'Failed to close ticket.'
    },
    onFinish: () => {
      busyAction.value = false
    },
  })
}

async function submitEdit() {
  busyAction.value = true
  actionError.value = ''

  const payload = {
    ticket_type: editForm.type,
    ticket_assigned_staff: editForm.staff || null,
    ticket_problem_that_needed_support: editForm.problem,
  }

  if (editForm.nature) payload.ticket_nature_of_support = editForm.nature
  if (editForm.subCategory) payload.ticket_support_type = editForm.subCategory
  if (editForm.actionTaken) payload.ticket_actions_taken = editForm.actionTaken
  if (editForm.remarks) payload.ticket_remarks = editForm.remarks
  if (editForm.type === 'Events') {
    if (editForm.eventStartDate) payload.event_start_date = editForm.eventStartDate
    if (editForm.eventEndDate) payload.event_end_date = editForm.eventEndDate
    if (editForm.eventVenue) payload.event_venue = editForm.eventVenue
  }

  router.put(`/tickets/${ticketId.value}`, payload, {
    preserveScroll: true,
    onSuccess: () => {
      toast.success('Ticket details updated!')
      activeActionPanel.value = null

      // Immediately apply changes to localTicket so the drawer/modal view updates instantly!
      if (localTicket.value) {
        if (!localTicket.value.detail) {
          localTicket.value.detail = {}
        }
        localTicket.value.detail.ticket_type = editForm.type
        localTicket.value.ticket_type = editForm.type
        localTicket.value.detail.ticket_assigned_staff = editForm.staff || null
        localTicket.value.ticket_assigned_staff = editForm.staff || null
        localTicket.value.detail.ticket_problem_that_needed_support = editForm.problem
        localTicket.value.ticket_problem_that_needed_support = editForm.problem
        if (payload.ticket_nature_of_support !== undefined) {
          localTicket.value.detail.ticket_nature_of_support = payload.ticket_nature_of_support
          localTicket.value.ticket_nature_of_support = payload.ticket_nature_of_support
        }
        if (payload.ticket_support_type !== undefined) {
          localTicket.value.detail.ticket_support_type = payload.ticket_support_type
          localTicket.value.ticket_support_type = payload.ticket_support_type
        }
        if (payload.ticket_actions_taken !== undefined) {
          localTicket.value.detail.ticket_actions_taken = payload.ticket_actions_taken
          localTicket.value.ticket_actions_taken = payload.ticket_actions_taken
        }
        if (payload.ticket_remarks !== undefined) {
          localTicket.value.detail.ticket_remarks = payload.ticket_remarks
          localTicket.value.ticket_remarks = payload.ticket_remarks
        }
        if (editForm.type === 'Events') {
          if (!localTicket.value.event) localTicket.value.event = {}
          localTicket.value.event.event_start_date = editForm.eventStartDate
          localTicket.value.event.event_end_date = editForm.eventEndDate
          localTicket.value.event.event_venue = editForm.eventVenue
        }
      }

      // Also update props.ticket in place so parent references stay synchronized
      if (props.ticket) {
        if (!props.ticket.detail) {
          props.ticket.detail = {}
        }
        props.ticket.detail.ticket_type = editForm.type
        props.ticket.ticket_type = editForm.type
        props.ticket.detail.ticket_assigned_staff = editForm.staff || null
        props.ticket.ticket_assigned_staff = editForm.staff || null
        props.ticket.detail.ticket_problem_that_needed_support = editForm.problem
        props.ticket.ticket_problem_that_needed_support = editForm.problem
        if (payload.ticket_nature_of_support !== undefined) {
          props.ticket.detail.ticket_nature_of_support = payload.ticket_nature_of_support
          props.ticket.ticket_nature_of_support = payload.ticket_nature_of_support
        }
        if (payload.ticket_support_type !== undefined) {
          props.ticket.detail.ticket_support_type = payload.ticket_support_type
          props.ticket.ticket_support_type = payload.ticket_support_type
        }
        if (payload.ticket_actions_taken !== undefined) {
          props.ticket.detail.ticket_actions_taken = payload.ticket_actions_taken
          props.ticket.ticket_actions_taken = payload.ticket_actions_taken
        }
        if (payload.ticket_remarks !== undefined) {
          props.ticket.detail.ticket_remarks = payload.ticket_remarks
          props.ticket.ticket_remarks = payload.ticket_remarks
        }
        if (editForm.type === 'Events') {
          if (!props.ticket.event) props.ticket.event = {}
          props.ticket.event.event_start_date = editForm.eventStartDate
          props.ticket.event.event_end_date = editForm.eventEndDate
          props.ticket.event.event_venue = editForm.eventVenue
        }
      }

      // Refresh activity logs so the new activity appears in the Activity Trail tab
      fetchActivityLogs(ticketRef.value)

      // Emit updated event to parent
      emit('updated', {
        id: ticketId.value,
        ref: ticketRef.value,
        ...payload,
      })
    },
    onError: (err) => {
      actionError.value = err?.message || 'Failed to update ticket.'
    },
    onFinish: () => {
      busyAction.value = false
    },
  })
}

// ── Attachments & Activity Logs ───────────────────────────────────────────────
const attachments = ref([])
const loadingAttachments = ref(false)
const activityLogs = ref([])
const loadingLogs = ref(false)

async function fetchAttachments(refCode) {
  if (!refCode) { attachments.value = []; return }
  loadingAttachments.value = true
  try {
    const res = await fetch(`/tickets/${encodeURIComponent(refCode)}/attachments`)
    attachments.value = await res.json()
  } catch (err) {
    attachments.value = []
  } finally {
    loadingAttachments.value = false
  }
}

async function fetchActivityLogs(refCode) {
  if (!refCode) { activityLogs.value = []; return }
  loadingLogs.value = true
  try {
    const res = await fetch(`/tickets/logs/${encodeURIComponent(refCode)}`)
    activityLogs.value = await res.json()
  } catch (err) {
    activityLogs.value = []
  } finally {
    loadingLogs.value = false
  }
}

watch(
  () => [props.show, ticketRef.value, props.initialTab, props.initialAction],
  ([showNow, refCode, newTab, newAction]) => {
    if (showNow) {
      activeActionPanel.value = newAction || null
      if (newAction) {
        initPanelData(newAction)
      }
      actionError.value = ''
      activeTab.value = newTab || 'overview'
      if (refCode) {
        fetchAttachments(refCode)
        if (props.autoFetchLogs) {
          fetchActivityLogs(refCode)
        }
      }
    }
  },
  { immediate: true }
)

function close() {
  activeActionPanel.value = null
  actionError.value = ''
  emit('close')
}

// ── Formatting Helpers ────────────────────────────────────────────────────────

function parseLaravelDate(d) {
  if (!d) return null
  if (d.endsWith('Z') || /[+-]\d{2}:?\d{2}$/.test(d)) return new Date(d)
  return new Date(d.replace(' ', 'T') + 'Z')
}

function formatDateTime(d) {
  if (!d) return '—'
  const date = parseLaravelDate(d)
  if (!date || Number.isNaN(date.getTime())) return String(d)
  return date.toLocaleString('en-PH', {
    year: 'numeric',
    month: 'short',
    day: '2-digit',
    hour: '2-digit',
    minute: '2-digit',
    hour12: true,
  })
}
</script>

