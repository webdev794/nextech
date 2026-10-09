import { useEffect, useRef } from 'react'

// Live pages, without reloading them: one small check now and then reads
// the server's "something changed" marker (live.json — a tiny static file the
// web server hands out directly, no PHP or database work). Only when
// the counter moves do the open views fetch their data again — so a change
// made anywhere (admin allows cash on delivery, a seller asks to turn off
// deliveries, a rider takes an order) shows on every open page within seconds.
// Text being typed is never lost: only the data is fetched, nothing reloads.

const API_URL = import.meta.env.VITE_API_URL ?? 'http://127.0.0.1:8000/api'
// live.json sits next to the API, in the backend's public folder (in dev, Vite passes it through).
const LIVE_URL = import.meta.env.DEV ? '/live.json' : `${API_URL.replace(/\/api\/?$/, '')}/live.json`
// Light on the server: 10 s after a change or while someone is using the page,
// stretching to every 30 s when nothing happens; nothing while the tab is hidden.
const FAST_MS = 10000
const SLOW_MS = 30000
let wait = FAST_MS

const listeners = new Set()
let version = null
let timer = null

// Other tabs of this browser (e.g. admin in one, Seller Center in another) hear about a
// change at once: after any successful save / change request, this tab tells the others.
const channel = typeof BroadcastChannel !== 'undefined' ? new BroadcastChannel('nextech-live') : null
if (channel) {
  channel.onmessage = () => listeners.forEach((fn) => fn())
  if (!window.__nextechLiveFetch) {
    window.__nextechLiveFetch = true
    const original = window.fetch.bind(window)
    window.fetch = async (input, init) => {
      const res = await original(input, init)
      // Never let this side job break the page's own request.
      try {
        const method = String(init?.method || (typeof input === 'object' && input?.method) || 'GET').toUpperCase()
        if (method !== 'GET' && res.ok && String(typeof input === 'string' ? input : input?.url ?? '').startsWith(API_URL)) {
          setTimeout(() => channel.postMessage('changed'), 300) // after the server has finished writing
        }
      } catch { /* ignore */ }
      return res
    }
  }
}

async function check() {
  if (document.visibilityState !== 'visible') return
  try {
    const res = await fetch(`${LIVE_URL}?t=${Date.now()}`, { cache: 'no-store' })
    if (!res.ok) return
    const { v } = await res.json()
    if (version !== null && v !== version) { listeners.forEach((fn) => fn()); wait = FAST_MS } else wait = Math.min(SLOW_MS, Math.round(wait * 1.5))
    version = v
  } catch { wait = SLOW_MS /* offline: try again later */ }
}

function loop() {
  timer = setTimeout(async () => { await check(); if (timer) loop() }, wait)
}

// Someone clicking or typing: check soon again (without hammering — at most back to 10 s).
function busy() { if (wait > FAST_MS) { wait = FAST_MS; if (timer) { clearTimeout(timer); loop() } } }

function subscribe(fn) {
  listeners.add(fn)
  if (!timer) {
    check()
    loop()
    window.addEventListener('pointerdown', busy)
    window.addEventListener('keydown', busy)
  }
  return () => {
    listeners.delete(fn)
    if (!listeners.size && timer) {
      clearTimeout(timer); timer = null; version = null
      window.removeEventListener('pointerdown', busy)
      window.removeEventListener('keydown', busy)
    }
  }
}

// For code that isn't a component hook (e.g. a poller inside an effect): run `fn`
// whenever something changed. Returns the function that stops it.
export function onLiveChange(fn) {
  return subscribe(fn)
}

// `refresh` is the view's own load function. It runs when something changed,
// and when the user comes back to the tab.
export function useLiveRefresh(refresh) {
  const fn = useRef(refresh)
  useEffect(() => { fn.current = refresh }, [refresh])
  useEffect(() => {
    const run = () => fn.current?.()
    const unsubscribe = subscribe(run)
    const onBack = () => { if (document.visibilityState === 'visible') run() }
    document.addEventListener('visibilitychange', onBack)
    return () => { unsubscribe(); document.removeEventListener('visibilitychange', onBack) }
  }, [])
}
