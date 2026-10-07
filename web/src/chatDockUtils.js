import { useCallback, useEffect, useRef, useState } from 'react'

// Shared by the docked chat windows (ChatDock.jsx) on every surface.

export const API_URL = import.meta.env.VITE_API_URL ?? 'http://127.0.0.1:8000/api'
export async function readJson(response) {
  const text = await response.text()
  try { return text ? JSON.parse(text) : {} } catch { return {} }
}

// Opens (or brings up) a chat window in this page's ChatDock.
// detail: { key, kind, id, name, subtitle? }.
export const OPEN_CHAT = 'nextech-open-chat'
export function openChat(detail) {
  window.dispatchEvent(new CustomEvent(OPEN_CHAT, { detail }))
}

// How a chat window talks to the API. `url` returns a page of messages
// (App\Support\ChatPage: { messages, has_more }, `before` for older ones);
// `postUrl` takes { body, attachments } (photo URLs from POST
// /support/attachments). Its reply is used when it is a page too,
// otherwise the window reloads.
export function chatAdapter({ url, postUrl = url, headers }) {
  const get = async (before) => {
    const response = await fetch(before ? `${url}?before=${before}` : url, { headers: headers() })
    const d = await readJson(response)
    if (!response.ok || !d.data) throw new Error(d.message ?? 'Could not load the conversation.')
    return d.data
  }
  return {
    load: get,
    // The sign-in token, for uploading photos.
    token: () => (headers().Authorization ?? '').replace(/^Bearer /, ''),
    async send(body, attachments = []) {
      const response = await fetch(postUrl, { method: 'POST', headers: { ...headers(), 'Content-Type': 'application/json' }, body: JSON.stringify({ body, ...(attachments.length ? { attachments } : {}) }) })
      const d = await readJson(response)
      if (!response.ok) throw new Error(d.message ?? 'Could not send the message.')
      return d.data?.has_more !== undefined ? d.data : get()
    },
  }
}

// Chat sounds on/off — one setting for every chat window, remembered.
const SOUND_KEY = 'nextech_chat_sound'
const SOUND_EVENT = 'nextech-chat-sound'
export function soundOn() {
  return readStored(SOUND_KEY, true) !== false
}
export function useChatSound() {
  const [on, setOn] = useState(soundOn)
  useEffect(() => {
    const sync = () => setOn(soundOn())
    window.addEventListener(SOUND_EVENT, sync)
    window.addEventListener('storage', sync)
    return () => { window.removeEventListener(SOUND_EVENT, sync); window.removeEventListener('storage', sync) }
  }, [])
  const toggle = useCallback(() => { writeStored(SOUND_KEY, !soundOn()); window.dispatchEvent(new Event(SOUND_EVENT)) }, [])
  return [on, toggle]
}

// A light two-note chime for an incoming message (unless chat sounds are off).
export function chime() {
  if (!soundOn()) return
  try {
    const Ctx = window.AudioContext || window.webkitAudioContext
    if (!Ctx) return
    const ctx = new Ctx()
    ;[[660, 0], [880, 0.15]].forEach(([freq, at]) => {
      const osc = ctx.createOscillator()
      const gain = ctx.createGain()
      osc.connect(gain); gain.connect(ctx.destination)
      osc.frequency.value = freq
      gain.gain.setValueAtTime(0.0001, ctx.currentTime + at)
      gain.gain.exponentialRampToValueAtTime(0.2, ctx.currentTime + at + 0.02)
      gain.gain.exponentialRampToValueAtTime(0.0001, ctx.currentTime + at + 0.25)
      osc.start(ctx.currentTime + at)
      osc.stop(ctx.currentTime + at + 0.3)
    })
    setTimeout(() => ctx.close(), 800)
  } catch { /* audio blocked */ }
}

export function readStored(key, fallback) {
  try { return JSON.parse(localStorage.getItem(key) || 'null') ?? fallback } catch { return fallback }
}
export function writeStored(key, value) {
  try { localStorage.setItem(key, JSON.stringify(value)) } catch { /* ignore */ }
}

// Where a chat dock sits: docked along the bottom edge — right corner by
// default, or the left one (setSide, or drag a title bar sideways and let go:
// it snaps to the nearer side) — or floating, when its title bar drags it
// anywhere on the page. Remembered per key. Returns { style, side, setSide,
// floating, setFloating, onPointerDown, wasDragged }.
export function useDraggable(storeKey) {
  const [pos, setPos] = useState(() => ({ x: 0, y: 0, floating: false, side: 'right', ...readStored(storeKey, {}) }))
  const current = useRef(pos)
  const moved = useRef(false)
  useEffect(() => { current.current = pos; writeStored(storeKey, pos) }, [storeKey, pos])
  const onPointerDown = useCallback((event) => {
    if (event.button !== 0 || event.target.closest('button')) return
    const start = { x: event.clientX, y: event.clientY }
    const from = current.current
    moved.current = false
    const move = (e) => {
      const dx = e.clientX - start.x
      const dy = e.clientY - start.y
      if (Math.abs(dx) + Math.abs(dy) > 4) moved.current = true
      if (from.floating) setPos({ ...from, x: Math.min(0, Math.max(120 - window.innerWidth, from.x + dx)), y: Math.min(0, Math.max(60 - window.innerHeight, from.y + dy)) })
    }
    const up = (e) => {
      window.removeEventListener('pointermove', move)
      window.removeEventListener('pointerup', up)
      // Docked: a sideways drag snaps the dock to the nearer bottom corner.
      if (!from.floating && Math.abs(e.clientX - start.x) > 30) setPos({ ...from, side: e.clientX < window.innerWidth / 2 ? 'left' : 'right' })
    }
    window.addEventListener('pointermove', move)
    window.addEventListener('pointerup', up)
  }, [])
  // Floating lifts it a little off the corner so the change is visible; docking snaps back.
  const setFloating = useCallback((floating) => setPos((p) => (floating ? { ...p, floating: true, side: 'right', x: -24, y: -80 } : { ...p, floating: false, x: 0, y: 0 })), [])
  const setSide = useCallback((side) => setPos((p) => ({ ...p, floating: false, x: 0, y: 0, side })), [])
  const style = pos.floating ? { transform: `translate(${pos.x}px, ${pos.y}px)` } : undefined
  return { style, side: pos.floating ? 'right' : pos.side, setSide, floating: pos.floating, setFloating, onPointerDown, wasDragged: () => moved.current }
}
