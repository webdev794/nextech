// Rider "new delivery" alert: a chosen alarm tone, played loud when an order
// lands in the rider's queue. Presets are synthesised with Web Audio (no asset,
// no CSP headache); the rider can also upload a short clip of their own, kept in
// this browser's localStorage as a data URL.

const PREFS_KEY = 'gdp_rider_alert'
const CUSTOM_KEY = 'gdp_rider_alert_custom'      // { name, dataUrl }
const CUSTOM_MAX_BYTES = 700 * 1024

export const TONES = [
  { id: 'alarm', label: 'Urgent alarm' },
  { id: 'chime', label: 'Chime' },
  { id: 'bell', label: 'Bell' },
  { id: 'siren', label: 'Siren' },
]

export function loadAlertPrefs() {
  try {
    const raw = JSON.parse(localStorage.getItem(PREFS_KEY) || '{}')
    return { muted: !!raw.muted, toneId: raw.toneId || 'alarm' }
  } catch {
    return { muted: false, toneId: 'alarm' }
  }
}

export function saveAlertPrefs(patch) {
  const next = { ...loadAlertPrefs(), ...patch }
  try { localStorage.setItem(PREFS_KEY, JSON.stringify(next)) } catch { /* private mode */ }
  return next
}

export function getCustomTone() {
  try { return JSON.parse(localStorage.getItem(CUSTOM_KEY) || 'null') } catch { return null }
}

export function clearCustomTone() {
  try { localStorage.removeItem(CUSTOM_KEY) } catch { /* ignore */ }
}

/** Reads an uploaded audio file into localStorage. Resolves with its name. */
export function saveCustomTone(file) {
  return new Promise((resolve, reject) => {
    if (!file) return reject(new Error('No file chosen.'))
    if (!file.type.startsWith('audio/')) return reject(new Error('That is not an audio file.'))
    if (file.size > CUSTOM_MAX_BYTES) return reject(new Error('Keep it under 700 KB — a 2–3 second clip.'))
    const reader = new FileReader()
    reader.onerror = () => reject(new Error('Could not read that file.'))
    reader.onload = () => {
      try {
        localStorage.setItem(CUSTOM_KEY, JSON.stringify({ name: file.name, dataUrl: reader.result }))
        resolve(file.name)
      } catch {
        reject(new Error('This browser has no room to store it. Try a shorter clip.'))
      }
    }
    reader.readAsDataURL(file)
  })
}

// ---- playback -------------------------------------------------------------

let audioCtx = null
function ctx() {
  const Ctx = window.AudioContext || window.webkitAudioContext
  if (!Ctx) return null
  if (!audioCtx) audioCtx = new Ctx()
  if (audioCtx.state === 'suspended') audioCtx.resume().catch(() => {})
  return audioCtx
}

function blip(c, { freq = 880, start = 0, dur = 0.18, type = 'square', gain = 0.5 }) {
  const osc = c.createOscillator()
  const g = c.createGain()
  osc.connect(g); g.connect(c.destination)
  osc.type = type
  const t0 = c.currentTime + start
  if (typeof freq === 'function') freq(osc, t0)
  else osc.frequency.setValueAtTime(freq, t0)
  g.gain.setValueAtTime(0.0001, t0)
  g.gain.exponentialRampToValueAtTime(gain, t0 + 0.015)
  g.gain.exponentialRampToValueAtTime(0.0001, t0 + dur)
  osc.start(t0)
  osc.stop(t0 + dur + 0.02)
}

const PRESET_PLAYERS = {
  chime(c) {
    blip(c, { freq: 660, start: 0, dur: 0.22, type: 'sine', gain: 0.4 })
    blip(c, { freq: 990, start: 0.16, dur: 0.28, type: 'sine', gain: 0.4 })
  },
  alarm(c) {
    // three fast urgent pairs, loud
    for (let i = 0; i < 3; i++) {
      const b = i * 0.42
      blip(c, { freq: 1180, start: b, dur: 0.16, type: 'square', gain: 0.6 })
      blip(c, { freq: 1480, start: b + 0.18, dur: 0.16, type: 'square', gain: 0.6 })
    }
  },
  bell(c) {
    blip(c, { freq: 1046, start: 0, dur: 1.1, type: 'triangle', gain: 0.5 })
    blip(c, { freq: 1568, start: 0.01, dur: 0.9, type: 'sine', gain: 0.25 })
    blip(c, { freq: 2093, start: 0.02, dur: 0.5, type: 'sine', gain: 0.12 })
  },
  siren(c) {
    const sweep = (lo, hi) => (osc, t0) => {
      osc.frequency.setValueAtTime(lo, t0)
      osc.frequency.linearRampToValueAtTime(hi, t0 + 0.45)
      osc.frequency.linearRampToValueAtTime(lo, t0 + 0.9)
    }
    blip(c, { freq: sweep(600, 1300), start: 0, dur: 0.9, type: 'sawtooth', gain: 0.45 })
    blip(c, { freq: sweep(600, 1300), start: 0.9, dur: 0.9, type: 'sawtooth', gain: 0.45 })
  },
}

function playPreset(id) {
  const c = ctx()
  if (!c) return
  ;(PRESET_PLAYERS[id] || PRESET_PLAYERS.alarm)(c)
}

function playCustom() {
  const saved = getCustomTone()
  if (!saved?.dataUrl) return playPreset('alarm')
  try {
    const audio = new Audio(saved.dataUrl)
    audio.volume = 1
    audio.play().catch(() => playPreset('alarm'))
  } catch {
    playPreset('alarm')
  }
}

/** Play the tone the rider picked. Silent when muted. */
export function playRiderAlert() {
  const { muted, toneId } = loadAlertPrefs()
  if (muted) return
  if (toneId === 'custom') playCustom()
  else playPreset(toneId)
}

/** Play a tone regardless of mute — for the "Test" button. */
export function previewTone(toneId) {
  if (toneId === 'custom') playCustom()
  else playPreset(toneId)
}

// ---- repeating alarm (pending delivery offer) ---------------------------------

let alarmTimer = null   // setInterval id while an offer is pending
let alarmAudio = null   // natively-looping <audio> for a custom clip, if any

/**
 * Repeat the chosen tone until stopped — used while a delivery offer is waiting
 * for the rider to Accept/Reject. Honours the mute pref (a muted loop ticks
 * silently; unmuting sounds within one tick). Idempotent.
 */
export function startRiderAlarmLoop() {
  if (alarmTimer) return

  playRiderAlert()

  const { muted, toneId } = loadAlertPrefs()
  const custom = getCustomTone()
  if (!muted && toneId === 'custom' && custom?.dataUrl) {
    try {
      alarmAudio = new Audio(custom.dataUrl)
      alarmAudio.loop = true
      alarmAudio.volume = 1
      alarmAudio.play().catch(() => {})
    } catch { alarmAudio = null }
  }

  alarmTimer = setInterval(() => {
    if (alarmAudio && !alarmAudio.paused) return   // native loop is carrying it
    playRiderAlert()
  }, 3000)
}

export function stopRiderAlarmLoop() {
  if (alarmTimer) { clearInterval(alarmTimer); alarmTimer = null }
  if (alarmAudio) {
    try { alarmAudio.pause(); alarmAudio.currentTime = 0 } catch { /* ignore */ }
    alarmAudio = null
  }
}
