/**
 * Web Audio API Notification Sound Synthesizer & Ringtone Manager
 *
 * Provides crisp, zero-latency notification sounds without requiring external audio files.
 * Supports multiple ringtone choices, volume adjustments, and dynamic configuration.
 */

let audioCtx = null

export const DEFAULT_SOUND_SETTINGS = {
  ticket_sound: 'chime',
  message_sound: 'pop',
  volume: 0.8,
  enabled: true,
}

export const AVAILABLE_SOUNDS = [
  {
    id: 'chime',
    name: 'Classic Chime',
    description: 'Bright 3-tone ascending chime (Slack / Messenger style)',
    category: 'chime',
    icon: 'fa-solid fa-bell',
  },
  {
    id: 'crystal_bell',
    name: 'Crystal Bell',
    description: 'Rich two-tone acoustic chime with resonant overtone decay',
    category: 'bell',
    icon: 'fa-solid fa-gem',
  },
  {
    id: 'radar',
    name: 'Radar Pulse',
    description: 'Crisp dual sonar chirp (Modern & Tech)',
    category: 'modern',
    icon: 'fa-solid fa-satellite-dish',
  },
  {
    id: 'marimba',
    name: 'Marimba Melody',
    description: 'Warm 4-note ascending wooden acoustic arpeggio',
    category: 'melodic',
    icon: 'fa-solid fa-music',
  },
  {
    id: 'digital_beep',
    name: 'Digital Pager',
    description: 'Crisp double electronic alert beep',
    category: 'electronic',
    icon: 'fa-solid fa-calculator',
  },
  {
    id: 'subtle_ping',
    name: 'Subtle Glass Ping',
    description: 'Soft, unobtrusive high-frequency glass ping',
    category: 'minimal',
    icon: 'fa-solid fa-circle-dot',
  },
  {
    id: 'elevator',
    name: 'Elevator Ding',
    description: 'Warm, resonant classic brass arrival bell',
    category: 'bell',
    icon: 'fa-solid fa-door-open',
  },
  {
    id: 'gentle_harp',
    name: 'Gentle Harp',
    description: 'Soothing 3-note descending melodic glissando',
    category: 'melodic',
    icon: 'fa-solid fa-feather',
  },
  {
    id: 'pop',
    name: 'Bubble Pop',
    description: 'Playful water droplet pop (ideal for chat messages)',
    category: 'playful',
    icon: 'fa-solid fa-comment-dots',
  },
]

// Registry of custom audio files (e.g. mp3/wav dropped into public/sounds/)
const customSoundMap = new Map()

// Hydrate custom sounds from localStorage if present
if (typeof localStorage !== 'undefined') {
  try {
    const rawCustom = localStorage.getItem('tickets_notification_custom_sounds')
    if (rawCustom) {
      const list = JSON.parse(rawCustom)
      if (Array.isArray(list)) {
        list.forEach((s) => {
          if (s && s.id) {
            customSoundMap.set(s.id, s)
          }
        })
      }
    }
  } catch {
    // ignore json error
  }
}

/**
 * Register custom sounds discovered from public/sounds/ or server API
 */
export function registerCustomSounds(soundList) {
  if (!Array.isArray(soundList)) return
  const valid = []
  soundList.forEach((s) => {
    if (s && s.id && (s.is_custom || s.url || String(s.id).includes('.'))) {
      customSoundMap.set(s.id, s)
      valid.push(s)
    }
  })
  if (typeof localStorage !== 'undefined' && valid.length > 0) {
    try {
      localStorage.setItem('tickets_notification_custom_sounds', JSON.stringify(valid))
    } catch {
      // ignore
    }
  }
}

/**
 * Play custom audio file (MP3, WAV, OGG)
 */
function playAudioFile(url, volume) {
  try {
    const audio = new Audio(url)
    audio.volume = Math.max(0, Math.min(1, volume))
    const playPromise = audio.play()
    if (playPromise !== undefined) {
      playPromise.catch((err) => {
        console.warn('Custom notification audio playback error:', err)
      })
    }
  } catch (err) {
    console.warn('Audio element error:', err)
  }
}

function getAudioContext() {
  if (typeof window === 'undefined') return null

  if (!audioCtx) {
    const AudioContextClass = window.AudioContext || window.webkitAudioContext
    if (AudioContextClass) {
      audioCtx = new AudioContextClass()
    }
  }

  if (audioCtx && audioCtx.state === 'suspended') {
    audioCtx.resume().catch(() => {})
  }

  return audioCtx
}

// Unlock audio context on first user interaction (browser autoplay policy)
if (typeof window !== 'undefined') {
  const unlockAudio = () => {
    const ctx = getAudioContext()
    if (ctx && ctx.state === 'suspended') {
      ctx.resume().catch(() => {})
    }
    window.removeEventListener('click', unlockAudio)
    window.removeEventListener('keydown', unlockAudio)
    window.removeEventListener('touchstart', unlockAudio)
  }

  window.addEventListener('click', unlockAudio, { passive: true })
  window.addEventListener('keydown', unlockAudio, { passive: true })
  window.addEventListener('touchstart', unlockAudio, { passive: true })
}

/**
 * Get current sound notification settings
 */
export function getSoundSettings() {
  if (typeof localStorage === 'undefined') return { ...DEFAULT_SOUND_SETTINGS }

  try {
    const raw = localStorage.getItem('tickets_notification_sound_settings')
    if (raw) {
      const parsed = JSON.parse(raw)
      return { ...DEFAULT_SOUND_SETTINGS, ...parsed }
    }

    // Legacy fallback check
    const legacySound = localStorage.getItem('tickets_dispatch_sound')
    if (legacySound !== null) {
      return {
        ...DEFAULT_SOUND_SETTINGS,
        enabled: legacySound !== 'false',
      }
    }
  } catch {
    // ignore json parse error
  }

  return { ...DEFAULT_SOUND_SETTINGS }
}

/**
 * Save sound notification settings to localStorage & notify listeners
 */
export function setSoundSettings(settings) {
  const merged = { ...getSoundSettings(), ...settings }

  if (typeof localStorage !== 'undefined') {
    localStorage.setItem('tickets_notification_sound_settings', JSON.stringify(merged))
    localStorage.setItem('tickets_dispatch_sound', merged.enabled ? 'true' : 'false')
  }

  if (typeof window !== 'undefined') {
    window.dispatchEvent(new CustomEvent('tickets:sound-settings-changed', { detail: merged }))
    window.dispatchEvent(new CustomEvent('tickets:sound-toggled', { detail: { enabled: merged.enabled } }))
  }

  return merged
}

/**
 * Sync server sound settings (e.g., from Inertia page props or API)
 */
export function syncSoundSettings(serverSettings) {
  if (!serverSettings || typeof serverSettings !== 'object') return
  setSoundSettings(serverSettings)
}

/**
 * Check if notification audio is enabled
 */
export function isSoundEnabled() {
  return getSoundSettings().enabled
}

/**
 * Enable or disable notification audio
 */
export function setSoundEnabled(enabled) {
  setSoundSettings({ enabled: Boolean(enabled) })
}

// ─────────────────────────────────────────────────────────────────────────────
// SOUND SYNTHESIZERS (Pure Web Audio API)
// ─────────────────────────────────────────────────────────────────────────────

function synthChime(ctx, vol) {
  const now = ctx.currentTime
  const masterGain = ctx.createGain()
  masterGain.gain.setValueAtTime(vol, now)
  masterGain.connect(ctx.destination)

  // Tone 1: 587.33 Hz (D5)
  const osc1 = ctx.createOscillator()
  const g1 = ctx.createGain()
  osc1.type = 'sine'
  osc1.frequency.setValueAtTime(587.33, now)
  g1.gain.setValueAtTime(0, now)
  g1.gain.linearRampToValueAtTime(0.25, now + 0.02)
  g1.gain.exponentialRampToValueAtTime(0.001, now + 0.35)
  osc1.connect(g1)
  g1.connect(masterGain)
  osc1.start(now)
  osc1.stop(now + 0.35)

  // Tone 2: 880 Hz (A5)
  const osc2 = ctx.createOscillator()
  const g2 = ctx.createGain()
  osc2.type = 'sine'
  osc2.frequency.setValueAtTime(880, now + 0.07)
  g2.gain.setValueAtTime(0, now + 0.07)
  g2.gain.linearRampToValueAtTime(0.3, now + 0.09)
  g2.gain.exponentialRampToValueAtTime(0.001, now + 0.55)
  osc2.connect(g2)
  g2.connect(masterGain)
  osc2.start(now + 0.07)
  osc2.stop(now + 0.55)

  // Tone 3: 1174.66 Hz (D6)
  const osc3 = ctx.createOscillator()
  const g3 = ctx.createGain()
  osc3.type = 'triangle'
  osc3.frequency.setValueAtTime(1174.66, now + 0.12)
  g3.gain.setValueAtTime(0, now + 0.12)
  g3.gain.linearRampToValueAtTime(0.15, now + 0.14)
  g3.gain.exponentialRampToValueAtTime(0.001, now + 0.5)
  osc3.connect(g3)
  g3.connect(masterGain)
  osc3.start(now + 0.12)
  osc3.stop(now + 0.5)
}

function synthCrystalBell(ctx, vol) {
  const now = ctx.currentTime
  const master = ctx.createGain()
  master.gain.setValueAtTime(vol, now)
  master.connect(ctx.destination)

  // Strike 1: G5 (783.99 Hz)
  const o1 = ctx.createOscillator()
  const g1 = ctx.createGain()
  o1.type = 'sine'
  o1.frequency.setValueAtTime(783.99, now)
  g1.gain.setValueAtTime(0, now)
  g1.gain.linearRampToValueAtTime(0.35, now + 0.015)
  g1.gain.exponentialRampToValueAtTime(0.001, now + 0.8)
  o1.connect(g1)
  g1.connect(master)
  o1.start(now)
  o1.stop(now + 0.8)

  // Strike 2: C6 (1046.5 Hz) slightly delayed
  const o2 = ctx.createOscillator()
  const g2 = ctx.createGain()
  o2.type = 'sine'
  o2.frequency.setValueAtTime(1046.5, now + 0.12)
  g2.gain.setValueAtTime(0, now + 0.12)
  g2.gain.linearRampToValueAtTime(0.4, now + 0.14)
  g2.gain.exponentialRampToValueAtTime(0.001, now + 1.1)
  o2.connect(g2)
  g2.connect(master)
  o2.start(now + 0.12)
  o2.stop(now + 1.1)

  // Harmonic overtone sparkle
  const o3 = ctx.createOscillator()
  const g3 = ctx.createGain()
  o3.type = 'triangle'
  o3.frequency.setValueAtTime(2093.0, now + 0.12)
  g3.gain.setValueAtTime(0, now + 0.12)
  g3.gain.linearRampToValueAtTime(0.08, now + 0.14)
  g3.gain.exponentialRampToValueAtTime(0.001, now + 0.6)
  o3.connect(g3)
  g3.connect(master)
  o3.start(now + 0.12)
  o3.stop(now + 0.6)
}

function synthRadar(ctx, vol) {
  const now = ctx.currentTime
  const master = ctx.createGain()
  master.gain.setValueAtTime(vol * 0.9, now)
  master.connect(ctx.destination)

  // Chirp 1
  const o1 = ctx.createOscillator()
  const g1 = ctx.createGain()
  o1.type = 'sine'
  o1.frequency.setValueAtTime(700, now)
  o1.frequency.exponentialRampToValueAtTime(1400, now + 0.06)
  g1.gain.setValueAtTime(0, now)
  g1.gain.linearRampToValueAtTime(0.35, now + 0.01)
  g1.gain.exponentialRampToValueAtTime(0.001, now + 0.15)
  o1.connect(g1)
  g1.connect(master)
  o1.start(now)
  o1.stop(now + 0.15)

  // Chirp 2 (Higher)
  const o2 = ctx.createOscillator()
  const g2 = ctx.createGain()
  o2.type = 'sine'
  o2.frequency.setValueAtTime(950, now + 0.12)
  o2.frequency.exponentialRampToValueAtTime(1900, now + 0.18)
  g2.gain.setValueAtTime(0, now + 0.12)
  g2.gain.linearRampToValueAtTime(0.35, now + 0.13)
  g2.gain.exponentialRampToValueAtTime(0.001, now + 0.3)
  o2.connect(g2)
  g2.connect(master)
  o2.start(now + 0.12)
  o2.stop(now + 0.3)
}

function synthMarimba(ctx, vol) {
  const now = ctx.currentTime
  const master = ctx.createGain()
  master.gain.setValueAtTime(vol, now)
  master.connect(ctx.destination)

  const notes = [
    { freq: 523.25, time: 0 },      // C5
    { freq: 659.25, time: 0.07 },   // E5
    { freq: 783.99, time: 0.14 },   // G5
    { freq: 1046.50, time: 0.21 },  // C6
  ]

  notes.forEach((n) => {
    const o = ctx.createOscillator()
    const g = ctx.createGain()
    o.type = 'sine'
    o.frequency.setValueAtTime(n.freq, now + n.time)

    g.gain.setValueAtTime(0, now + n.time)
    g.gain.linearRampToValueAtTime(0.3, now + n.time + 0.015)
    g.gain.exponentialRampToValueAtTime(0.001, now + n.time + 0.3)

    o.connect(g)
    g.connect(master)
    o.start(now + n.time)
    o.stop(now + n.time + 0.3)
  })
}

function synthDigitalBeep(ctx, vol) {
  const now = ctx.currentTime
  const master = ctx.createGain()
  master.gain.setValueAtTime(vol * 0.75, now)
  master.connect(ctx.destination)

  // Beep 1
  const o1 = ctx.createOscillator()
  const g1 = ctx.createGain()
  o1.type = 'square'
  o1.frequency.setValueAtTime(1046.5, now)
  g1.gain.setValueAtTime(0.18, now)
  g1.gain.setValueAtTime(0.18, now + 0.06)
  g1.gain.linearRampToValueAtTime(0.001, now + 0.07)
  o1.connect(g1)
  g1.connect(master)
  o1.start(now)
  o1.stop(now + 0.07)

  // Beep 2
  const o2 = ctx.createOscillator()
  const g2 = ctx.createGain()
  o2.type = 'square'
  o2.frequency.setValueAtTime(1046.5, now + 0.1)
  g2.gain.setValueAtTime(0.22, now + 0.1)
  g2.gain.setValueAtTime(0.22, now + 0.18)
  g2.gain.linearRampToValueAtTime(0.001, now + 0.19)
  o2.connect(g2)
  g2.connect(master)
  o2.start(now + 0.1)
  o2.stop(now + 0.19)
}

function synthSubtlePing(ctx, vol) {
  const now = ctx.currentTime
  const master = ctx.createGain()
  master.gain.setValueAtTime(vol, now)
  master.connect(ctx.destination)

  const o = ctx.createOscillator()
  const g = ctx.createGain()
  o.type = 'sine'
  o.frequency.setValueAtTime(1318.51, now) // E6

  g.gain.setValueAtTime(0, now)
  g.gain.linearRampToValueAtTime(0.3, now + 0.01)
  g.gain.exponentialRampToValueAtTime(0.001, now + 0.4)

  o.connect(g)
  g.connect(master)
  o.start(now)
  o.stop(now + 0.4)
}

function synthElevator(ctx, vol) {
  const now = ctx.currentTime
  const master = ctx.createGain()
  master.gain.setValueAtTime(vol, now)
  master.connect(ctx.destination)

  // Warm brass chime (F#5 739.99 Hz)
  const o1 = ctx.createOscillator()
  const g1 = ctx.createGain()
  o1.type = 'sine'
  o1.frequency.setValueAtTime(739.99, now)
  g1.gain.setValueAtTime(0, now)
  g1.gain.linearRampToValueAtTime(0.4, now + 0.02)
  g1.gain.exponentialRampToValueAtTime(0.001, now + 1.2)
  o1.connect(g1)
  g1.connect(master)
  o1.start(now)
  o1.stop(now + 1.2)

  // Harmonic
  const o2 = ctx.createOscillator()
  const g2 = ctx.createGain()
  o2.type = 'triangle'
  o2.frequency.setValueAtTime(1479.98, now)
  g2.gain.setValueAtTime(0, now)
  g2.gain.linearRampToValueAtTime(0.12, now + 0.02)
  g2.gain.exponentialRampToValueAtTime(0.001, now + 0.8)
  o2.connect(g2)
  g2.connect(master)
  o2.start(now)
  o2.stop(now + 0.8)
}

function synthGentleHarp(ctx, vol) {
  const now = ctx.currentTime
  const master = ctx.createGain()
  master.gain.setValueAtTime(vol, now)
  master.connect(ctx.destination)

  const notes = [
    { freq: 880.00, time: 0 },      // A5
    { freq: 739.99, time: 0.08 },   // F#5
    { freq: 587.33, time: 0.16 },   // D5
  ]

  notes.forEach((n) => {
    const o = ctx.createOscillator()
    const g = ctx.createGain()
    o.type = 'triangle'
    o.frequency.setValueAtTime(n.freq, now + n.time)

    g.gain.setValueAtTime(0, now + n.time)
    g.gain.linearRampToValueAtTime(0.28, now + n.time + 0.02)
    g.gain.exponentialRampToValueAtTime(0.001, now + n.time + 0.5)

    o.connect(g)
    g.connect(master)
    o.start(now + n.time)
    o.stop(now + n.time + 0.5)
  })
}

function synthPop(ctx, vol) {
  const now = ctx.currentTime
  const master = ctx.createGain()
  master.gain.setValueAtTime(vol, now)
  master.connect(ctx.destination)

  const o = ctx.createOscillator()
  const g = ctx.createGain()
  o.type = 'sine'
  o.frequency.setValueAtTime(550, now)
  o.frequency.exponentialRampToValueAtTime(1100, now + 0.07)

  g.gain.setValueAtTime(0, now)
  g.gain.linearRampToValueAtTime(0.35, now + 0.015)
  g.gain.exponentialRampToValueAtTime(0.001, now + 0.22)

  o.connect(g)
  g.connect(master)
  o.start(now)
  o.stop(now + 0.22)
}

/**
 * Play a specific sound by key or audio file
 */
export function playSound(soundId, customVolume = null) {
  try {
    const settings = getSoundSettings()
    const volume = customVolume !== null ? Number(customVolume) : Number(settings.volume || 0.8)

    // Check if this sound is a custom MP3/audio file
    const custom = customSoundMap.get(soundId)
    const isCustomFile =
      custom ||
      (typeof soundId === 'string' &&
        (soundId.endsWith('.mp3') ||
          soundId.endsWith('.wav') ||
          soundId.endsWith('.ogg') ||
          soundId.endsWith('.m4a') ||
          soundId.startsWith('/') ||
          soundId.startsWith('http')))

    if (isCustomFile) {
      const url =
        custom?.url ||
        (typeof soundId === 'string' && (soundId.startsWith('/') || soundId.startsWith('http'))
          ? soundId
          : `/sounds/${soundId}`)
      playAudioFile(url, volume)
      return
    }

    const ctx = getAudioContext()
    if (!ctx) return

    switch (soundId) {
      case 'crystal_bell':
        synthCrystalBell(ctx, volume)
        break
      case 'radar':
        synthRadar(ctx, volume)
        break
      case 'marimba':
        synthMarimba(ctx, volume)
        break
      case 'digital_beep':
        synthDigitalBeep(ctx, volume)
        break
      case 'subtle_ping':
        synthSubtlePing(ctx, volume)
        break
      case 'elevator':
        synthElevator(ctx, volume)
        break
      case 'gentle_harp':
        synthGentleHarp(ctx, volume)
        break
      case 'pop':
        synthPop(ctx, volume)
        break
      case 'chime':
      default:
        synthChime(ctx, volume)
        break
    }
  } catch (err) {
    console.warn('Audio playback error:', err)
  }
}

/**
 * Preview sound regardless of mute state (used in settings dialog)
 */
export function previewSound(soundId, volume = null) {
  playSound(soundId, volume)
}

/**
 * Play sound for new ticket received
 */
export function playNewTicketChime(overrideSoundId = null) {
  const settings = getSoundSettings()
  if (!settings.enabled) return

  const soundKey = overrideSoundId || settings.ticket_sound || 'chime'
  playSound(soundKey, settings.volume)
}

/**
 * Play sound for new message received
 */
export function playNewMessageChime(overrideSoundId = null) {
  const settings = getSoundSettings()
  if (!settings.enabled) return

  const soundKey = overrideSoundId || settings.message_sound || 'pop'
  playSound(soundKey, settings.volume)
}
