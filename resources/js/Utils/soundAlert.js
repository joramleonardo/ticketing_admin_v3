/**
 * Web Audio API Notification Sound Synthesizer
 *
 * Provides crisp, zero-latency notification chimes (Messenger / Intercom / Slack style)
 * without requiring external audio files, downloads, or asset paths.
 */

let audioCtx = null

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
 * Check if notification audio is enabled
 */
export function isSoundEnabled() {
  if (typeof localStorage === 'undefined') return true
  return localStorage.getItem('tickets_dispatch_sound') !== 'false'
}

/**
 * Enable or disable notification audio
 */
export function setSoundEnabled(enabled) {
  if (typeof localStorage !== 'undefined') {
    localStorage.setItem('tickets_dispatch_sound', enabled ? 'true' : 'false')
  }
  if (typeof window !== 'undefined') {
    window.dispatchEvent(new CustomEvent('tickets:sound-toggled', { detail: { enabled } }))
  }
}

/**
 * Play a bright, friendly dual-tone chime (Messenger / Slack style) for new tickets.
 */
export function playNewTicketChime() {
  if (!isSoundEnabled()) return

  try {
    const ctx = getAudioContext()
    if (!ctx) return

    const now = ctx.currentTime

    // Tone 1: 587.33 Hz (D5) - Soft gentle attack
    const osc1 = ctx.createOscillator()
    const gain1 = ctx.createGain()
    osc1.type = 'sine'
    osc1.frequency.setValueAtTime(587.33, now)

    gain1.gain.setValueAtTime(0, now)
    gain1.gain.linearRampToValueAtTime(0.2, now + 0.02)
    gain1.gain.exponentialRampToValueAtTime(0.001, now + 0.3)

    osc1.connect(gain1)
    gain1.connect(ctx.destination)
    osc1.start(now)
    osc1.stop(now + 0.3)

    // Tone 2: 880 Hz (A5) - Followed quickly with a warm shimmer
    const osc2 = ctx.createOscillator()
    const gain2 = ctx.createGain()
    osc2.type = 'sine'
    osc2.frequency.setValueAtTime(880, now + 0.07)

    gain2.gain.setValueAtTime(0, now + 0.07)
    gain2.gain.linearRampToValueAtTime(0.25, now + 0.09)
    gain2.gain.exponentialRampToValueAtTime(0.001, now + 0.5)

    osc2.connect(gain2)
    gain2.connect(ctx.destination)
    osc2.start(now + 0.07)
    osc2.stop(now + 0.5)

    // Tone 3: 1174.66 Hz (D6) - Gentle harmonic sparkle
    const osc3 = ctx.createOscillator()
    const gain3 = ctx.createGain()
    osc3.type = 'triangle'
    osc3.frequency.setValueAtTime(1174.66, now + 0.12)

    gain3.gain.setValueAtTime(0, now + 0.12)
    gain3.gain.linearRampToValueAtTime(0.12, now + 0.14)
    gain3.gain.exponentialRampToValueAtTime(0.001, now + 0.45)

    osc3.connect(gain3)
    gain3.connect(ctx.destination)
    osc3.start(now + 0.12)
    osc3.stop(now + 0.45)
  } catch (err) {
    console.warn('Audio alert could not be played:', err)
  }
}

/**
 * Play a soft water-drop / bubble tone for incoming messages.
 */
export function playNewMessageChime() {
  if (!isSoundEnabled()) return

  try {
    const ctx = getAudioContext()
    if (!ctx) return

    const now = ctx.currentTime

    const osc = ctx.createOscillator()
    const gain = ctx.createGain()
    osc.type = 'sine'

    // Pitch bends slightly upward for a playful "pop"
    osc.frequency.setValueAtTime(600, now)
    osc.frequency.exponentialRampToValueAtTime(950, now + 0.08)

    gain.gain.setValueAtTime(0, now)
    gain.gain.linearRampToValueAtTime(0.25, now + 0.02)
    gain.gain.exponentialRampToValueAtTime(0.001, now + 0.25)

    osc.connect(gain)
    gain.connect(ctx.destination)
    osc.start(now)
    osc.stop(now + 0.25)
  } catch (err) {
    console.warn('Message sound alert could not be played:', err)
  }
}

