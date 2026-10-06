import { useEffect, useState } from 'react'

// The store's branding (name, logo, favicon) from GET /api/config, shared by
// every surface — storefront, admin, Seller Center, rider. Remembered in the
// browser so the logo shows instantly, then refreshed from the server (so a
// new logo saved in admin replaces the old one everywhere).

const API_URL = import.meta.env.VITE_API_URL ?? 'http://127.0.0.1:8000/api'
const KEY = 'nextech_branding'
const listeners = new Set()
let current = (() => { try { return JSON.parse(localStorage.getItem(KEY) ?? 'null') } catch { return null } })()
let fetched = false

export function setBranding(branding) {
  current = branding ?? {}
  try { localStorage.setItem(KEY, JSON.stringify(current)) } catch { /* private mode */ }
  listeners.forEach((fn) => fn(current))
}

// The store's name from Store settings — use it wherever the store names itself,
// so renaming the store in admin renames it everywhere. ("NexTech" until set.)
export function brandName() {
  return (current?.store_name ?? '').trim() || 'NexTech'
}

export function useBranding() {
  const [branding, setState] = useState(current)
  useEffect(() => {
    listeners.add(setState)
    if (!fetched) {
      fetched = true
      fetch(`${API_URL}/config`, { headers: { Accept: 'application/json' } })
        .then((r) => r.json())
        .then((d) => setBranding(d?.data?.branding ?? {}))
        .catch(() => { fetched = false })
    }
    return () => { listeners.delete(setState) }
  }, [])
  return branding
}
