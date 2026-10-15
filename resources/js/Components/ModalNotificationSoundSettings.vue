<script setup>
import { ref, computed, watch, onMounted } from 'vue'
import { usePage } from '@inertiajs/vue3'
import Modal from '@/Components/Modal.vue'
import { useToast } from 'vue-toastification'
import axios from 'axios'
import {
  AVAILABLE_SOUNDS,
  DEFAULT_SOUND_SETTINGS,
  getSoundSettings,
  setSoundSettings,
  previewSound,
  registerCustomSounds,
} from '@/Utils/soundAlert'

const props = defineProps({
  show: {
    type: Boolean,
    default: false,
  },
})

const emit = defineEmits(['close'])

const page = usePage()
const toast = useToast()

const userRole = computed(() => String(page.props?.auth?.user?.role || '').trim().toLowerCase())
const isAdmin = computed(() => ['admin', 'super admin'].includes(userRole.value))

const form = ref({
  ticket_sound: 'chime',
  message_sound: 'pop',
  volume: 0.8,
  enabled: true,
})

const isSaving = ref(false)
const isLoadingSettings = ref(false)
const canManage = ref(isAdmin.value)
const activePreviewingId = ref(null)
const availableSounds = ref([...AVAILABLE_SOUNDS])

// Initialize local form from localStorage and server props
function initSettings() {
  const local = getSoundSettings()
  const propSettings = page.props?.notificationSound

  form.value = {
    ...DEFAULT_SOUND_SETTINGS,
    ...local,
    ...(propSettings && typeof propSettings === 'object' ? propSettings : {}),
  }
}

// Fetch current authoritative settings from backend
async function fetchRemoteSettings() {
  isLoadingSettings.value = true
  try {
    const { data } = await axios.get('/admin/settings/notification-sound')
    if (data?.settings) {
      form.value = {
        ...DEFAULT_SOUND_SETTINGS,
        ...data.settings,
      }
      setSoundSettings(data.settings)
    }
    if (typeof data?.can_manage === 'boolean') {
      canManage.value = data.can_manage
    }
    if (Array.isArray(data?.sounds) && data.sounds.length > 0) {
      availableSounds.value = data.sounds.map((s) => ({
        ...s,
        icon: s.icon || (s.is_custom ? 'fa-solid fa-file-audio' : 'fa-solid fa-bell'),
      }))
      registerCustomSounds(data.sounds)
    }
  } catch (err) {
    // Fall back to local settings silently
  } finally {
    isLoadingSettings.value = false
  }
}

watch(
  () => props.show,
  (val) => {
    if (val) {
      initSettings()
      fetchRemoteSettings()
    }
  }
)

onMounted(() => {
  initSettings()
})

function onPreview(soundId) {
  activePreviewingId.value = soundId
  previewSound(soundId, form.value.volume)
  setTimeout(() => {
    if (activePreviewingId.value === soundId) {
      activePreviewingId.value = null
    }
  }, 1000)
}

function testCurrentTicketSound() {
  onPreview(form.value.ticket_sound)
}

function testCurrentMessageSound() {
  onPreview(form.value.message_sound)
}

async function saveSettings() {
  if (!canManage.value) {
    toast.error('Only administrators are permitted to update notification sound settings.')
    return
  }

  isSaving.value = true
  try {
    const payload = {
      ticket_sound: form.value.ticket_sound,
      message_sound: form.value.message_sound,
      volume: Number(form.value.volume),
      enabled: Boolean(form.value.enabled),
    }

    const { data } = await axios.put('/admin/settings/notification-sound', payload)

    // Update local storage and reactive event bus
    setSoundSettings(data.settings || payload)
    toast.success(data.message || 'Notification sound settings updated!')
    emit('close')
  } catch (err) {
    const msg = err?.response?.data?.message || 'Failed to update sound settings. Please try again.'
    toast.error(msg)
  } finally {
    isSaving.value = false
  }
}
</script>

<template>
  <Modal :show="show" @close="emit('close')" max-width="2xl">
    <div class="bg-slate-900 text-slate-100 rounded-2xl overflow-hidden shadow-2xl border border-slate-800">
      <!-- Modal Header -->
      <div class="px-6 py-5 bg-gradient-to-r from-slate-900 via-blue-950 to-slate-900 border-b border-slate-800 flex items-center justify-between">
        <div class="flex items-center gap-3">
          <div class="w-10 h-10 rounded-xl bg-sky-500/20 text-sky-400 border border-sky-500/30 flex items-center justify-center shrink-0">
            <i class="fa-solid fa-volume-high text-lg"></i>
          </div>
          <div>
            <h3 class="text-base font-bold text-white tracking-wide">Notification Ringtone & Sound Settings</h3>
            <p class="text-xs text-slate-400">Configure real-time audio chimes for new tickets and messages</p>
          </div>
        </div>
        <button
          type="button"
          @click="emit('close')"
          class="w-8 h-8 rounded-lg bg-slate-800/80 hover:bg-slate-700 text-slate-400 hover:text-white flex items-center justify-center transition"
          title="Close modal"
        >
          <i class="fa-solid fa-xmark text-sm"></i>
        </button>
      </div>

      <!-- Modal Body -->
      <div class="p-6 space-y-6 max-h-[75vh] overflow-y-auto">
        <!-- Role Access Notice -->
        <div
          v-if="canManage"
          class="p-3.5 rounded-xl bg-sky-950/60 border border-sky-800/80 text-sky-200 text-xs flex items-start gap-3 shadow-inner"
        >
          <i class="fa-solid fa-shield-halved text-sky-400 mt-0.5 text-sm shrink-0"></i>
          <div>
            <span class="font-bold">Administrator Access Granted:</span>
            <p class="text-[11px] text-sky-300/80 mt-0.5 leading-relaxed">
              Updating these settings will set the system-wide sound notifications for all IT Staff and Administrators.
            </p>
          </div>
        </div>
        <div
          v-else
          class="p-3.5 rounded-xl bg-amber-950/60 border border-amber-800/80 text-amber-200 text-xs flex items-start gap-3 shadow-inner"
        >
          <i class="fa-solid fa-lock text-amber-400 mt-0.5 text-sm shrink-0"></i>
          <div>
            <span class="font-bold">Technician / Staff View:</span>
            <p class="text-[11px] text-amber-300/80 mt-0.5 leading-relaxed">
              Only Administrators have permission to modify system-wide ringtone settings. You can preview all available notification sounds below.
            </p>
          </div>
        </div>

        <!-- Master Sound Toggle & Volume Bar -->
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 p-4 rounded-xl bg-slate-950/60 border border-slate-800/80">
          <!-- Toggle -->
          <div class="flex items-center justify-between sm:justify-start sm:gap-4">
            <div>
              <span class="text-xs font-bold text-slate-200 uppercase tracking-wider block">Sound Alerts</span>
              <span class="text-[11px] text-slate-400">{{ form.enabled ? 'Audio chime active' : 'Audio alerts muted' }}</span>
            </div>
            <label class="relative inline-flex items-center cursor-pointer">
              <input
                type="checkbox"
                v-model="form.enabled"
                class="sr-only peer"
                :disabled="!canManage"
              />
              <div
                class="w-11 h-6 bg-slate-800 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-slate-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-emerald-600"
              ></div>
            </label>
          </div>

          <!-- Volume Slider -->
          <div>
            <div class="flex items-center justify-between mb-1.5">
              <span class="text-xs font-bold text-slate-200 uppercase tracking-wider flex items-center gap-1.5">
                <i :class="form.volume > 0.5 ? 'fa-solid fa-volume-high text-sky-400' : 'fa-solid fa-volume-low text-sky-400'"></i>
                Volume
              </span>
              <span class="text-xs font-mono font-bold text-sky-400">{{ Math.round(form.volume * 100) }}%</span>
            </div>
            <input
              type="range"
              min="0.1"
              max="1.0"
              step="0.05"
              v-model.number="form.volume"
              :disabled="!canManage"
              class="w-full h-2 bg-slate-800 rounded-lg appearance-none cursor-pointer accent-sky-500"
            />
          </div>
        </div>

        <!-- 1. New Ticket Ringtone Selection Grid -->
        <div>
          <div class="flex items-center justify-between mb-2.5">
            <div>
              <h4 class="text-xs font-bold uppercase tracking-wider text-slate-200">
                1. New Ticket Notification Ringtone
              </h4>
              <p class="text-[11px] text-slate-400 mt-0.5">
                Sound played when an unassigned or new support request arrives in the queue
              </p>
            </div>
            <button
              type="button"
              @click="testCurrentTicketSound"
              class="inline-flex items-center gap-1.5 px-2.5 py-1 text-xs font-semibold rounded-lg bg-sky-900/60 hover:bg-sky-800 text-sky-200 border border-sky-700/80 transition"
              title="Test current selected ticket ringtone"
            >
              <i class="fa-solid fa-play text-[10px]"></i>
              <span>Test Alert</span>
            </button>
          </div>

          <div class="grid grid-cols-1 sm:grid-cols-2 gap-2.5">
            <div
              v-for="snd in availableSounds"
              :key="`ticket-${snd.id}`"
              @click="canManage && (form.ticket_sound = snd.id)"
              class="p-3 rounded-xl border transition flex items-center justify-between gap-3"
              :class="[
                form.ticket_sound === snd.id
                  ? 'border-sky-500 bg-sky-950/50 shadow-md shadow-sky-950'
                  : 'border-slate-800 bg-slate-950/40 hover:border-slate-700 hover:bg-slate-900/60',
                canManage ? 'cursor-pointer' : 'cursor-default'
              ]"
            >
              <div class="flex items-center gap-3 min-w-0">
                <div
                  class="w-8 h-8 rounded-lg flex items-center justify-center shrink-0"
                  :class="[
                    form.ticket_sound === snd.id
                      ? 'bg-sky-500 text-white'
                      : (snd.is_custom ? 'bg-amber-500/20 text-amber-400 border border-amber-500/30' : 'bg-slate-800 text-slate-400')
                  ]"
                >
                  <i :class="snd.icon || 'fa-solid fa-bell'" class="text-xs"></i>
                </div>
                <div class="truncate">
                  <div class="flex items-center gap-2">
                    <span class="text-xs font-bold text-slate-100 truncate">{{ snd.name }}</span>
                    <span
                      v-if="snd.is_custom"
                      class="text-[9px] font-bold uppercase tracking-wider bg-amber-500/20 text-amber-300 px-1.5 py-0.5 rounded border border-amber-500/30"
                    >
                      Custom MP3
                    </span>
                    <span
                      v-if="form.ticket_sound === snd.id"
                      class="text-[9px] font-bold uppercase tracking-wider bg-sky-500/20 text-sky-300 px-1.5 py-0.5 rounded border border-sky-500/30"
                    >
                      Active
                    </span>
                  </div>
                  <p class="text-[10px] text-slate-400 truncate">{{ snd.description }}</p>
                </div>
              </div>

              <!-- Preview Button -->
              <button
                type="button"
                @click.stop="onPreview(snd.id)"
                class="w-7 h-7 rounded-lg bg-slate-800 hover:bg-sky-600 text-slate-300 hover:text-white flex items-center justify-center transition shrink-0"
                :title="`Preview ${snd.name}`"
              >
                <i
                  :class="activePreviewingId === snd.id ? 'fa-solid fa-volume-high animate-pulse text-sky-400' : 'fa-solid fa-play text-[10px]'"
                ></i>
              </button>
            </div>
          </div>
        </div>

        <!-- 2. New Message Sound Selection -->
        <div>
          <div class="flex items-center justify-between mb-2.5">
            <div>
              <h4 class="text-xs font-bold uppercase tracking-wider text-slate-200">
                2. Incoming Chat Message Sound
              </h4>
              <p class="text-[11px] text-slate-400 mt-0.5">
                Sound played when client or staff sends a message in a ticket thread
              </p>
            </div>
            <button
              type="button"
              @click="testCurrentMessageSound"
              class="inline-flex items-center gap-1.5 px-2.5 py-1 text-xs font-semibold rounded-lg bg-indigo-900/60 hover:bg-indigo-800 text-indigo-200 border border-indigo-700/80 transition"
              title="Test current selected message sound"
            >
              <i class="fa-solid fa-play text-[10px]"></i>
              <span>Test Chat</span>
            </button>
          </div>

          <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-2.5">
            <div
              v-for="snd in availableSounds"
              :key="`msg-${snd.id}`"
              @click="canManage && (form.message_sound = snd.id)"
              class="p-2.5 rounded-xl border transition flex items-center justify-between gap-2"
              :class="[
                form.message_sound === snd.id
                  ? 'border-indigo-500 bg-indigo-950/50 shadow-md shadow-indigo-950'
                  : 'border-slate-800 bg-slate-950/40 hover:border-slate-700',
                canManage ? 'cursor-pointer' : 'cursor-default'
              ]"
            >
              <div class="flex items-center gap-2 truncate">
                <div
                  class="w-6 h-6 rounded-md flex items-center justify-center shrink-0"
                  :class="[
                    form.message_sound === snd.id
                      ? 'bg-indigo-500 text-white'
                      : (snd.is_custom ? 'bg-amber-500/20 text-amber-400 border border-amber-500/30' : 'bg-slate-800 text-slate-400')
                  ]"
                >
                  <i :class="snd.icon || 'fa-solid fa-bell'" class="text-[10px]"></i>
                </div>
                <div class="truncate">
                  <div class="flex items-center gap-1 truncate">
                    <span class="text-xs font-bold text-slate-200 truncate">{{ snd.name }}</span>
                    <span
                      v-if="snd.is_custom"
                      class="text-[8px] font-bold uppercase bg-amber-500/20 text-amber-300 px-1 py-0.2 rounded border border-amber-500/30 shrink-0"
                    >
                      MP3
                    </span>
                  </div>
                </div>
              </div>
              <button
                type="button"
                @click.stop="onPreview(snd.id)"
                class="w-6 h-6 rounded-md bg-slate-800 hover:bg-indigo-600 text-slate-300 hover:text-white flex items-center justify-center transition shrink-0"
                :title="`Preview ${snd.name}`"
              >
                <i :class="activePreviewingId === snd.id ? 'fa-solid fa-volume-high text-[10px] text-indigo-300' : 'fa-solid fa-play text-[9px]'"></i>
              </button>
            </div>
          </div>
        </div>

        <!-- Custom Sounds Folder Info Box -->
        <div class="p-4 rounded-xl bg-slate-950/70 border border-slate-800 text-xs flex items-start gap-3">
          <div class="w-8 h-8 rounded-lg bg-amber-500/20 text-amber-400 border border-amber-500/30 flex items-center justify-center shrink-0 mt-0.5">
            <i class="fa-solid fa-folder-open text-xs"></i>
          </div>
          <div class="leading-relaxed">
            <div class="flex items-center gap-2">
              <span class="font-bold text-slate-100">Add Your Own Custom Sounds (.mp3 / .wav / .ogg)</span>
              <span class="text-[10px] text-amber-300 bg-amber-950/80 border border-amber-800/80 px-1.5 py-0.5 rounded font-mono font-medium">Quick Drop-in</span>
            </div>
            <p class="text-[11px] text-slate-400 mt-1">
              Simply drop your downloaded sound files directly into your project's folder:
            </p>
            <p class="text-[11px] font-mono text-sky-400 mt-1.5 bg-slate-900/90 border border-slate-800 px-2.5 py-1 rounded select-all inline-block">
              public/sounds/
            </p>
            <p class="text-[10px] text-slate-400 mt-1">
              Any audio files placed in <code class="text-sky-300 font-mono">public/sounds/</code> are instantly detected and appear here in the sound library for preview and selection!
            </p>
          </div>
        </div>
      </div>

      <!-- Modal Footer -->
      <div class="px-6 py-4 bg-slate-950 border-t border-slate-800 flex flex-col sm:flex-row items-center justify-between gap-3">
        <div class="text-[11px] text-slate-400 flex items-center gap-2">
          <i class="fa-solid fa-bolt text-amber-400"></i>
          <span>Zero-latency Web Audio API synthesizers. No audio files to download.</span>
        </div>

        <div class="flex items-center gap-2.5 w-full sm:w-auto">
          <button
            type="button"
            @click="emit('close')"
            class="w-full sm:w-auto px-4 py-2 text-xs font-semibold rounded-xl bg-slate-800 hover:bg-slate-700 text-slate-300 transition"
          >
            Close
          </button>

          <button
            v-if="canManage"
            type="button"
            @click="saveSettings"
            :disabled="isSaving || isLoadingSettings"
            class="w-full sm:w-auto inline-flex items-center justify-center gap-2 px-5 py-2 text-xs font-bold rounded-xl bg-gradient-to-r from-sky-500 to-blue-600 hover:from-sky-600 hover:to-blue-700 text-white shadow-md shadow-sky-600/30 transition disabled:opacity-60 disabled:cursor-not-allowed"
          >
            <i v-if="isSaving" class="fa-solid fa-circle-notch fa-spin text-xs"></i>
            <i v-else class="fa-solid fa-check text-xs"></i>
            <span>{{ isSaving ? 'Saving Settings…' : 'Save Sound Settings' }}</span>
          </button>
        </div>
      </div>
    </div>
  </Modal>
</template>

