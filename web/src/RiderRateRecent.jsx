import { useState } from 'react'
import { brandName } from './useBranding'

// Rider app: rate the store and the buyer of recent deliveries — private, only
// the store's (NexTech's) team sees it; never the seller or the buyer.

const API_URL = import.meta.env.VITE_API_URL ?? 'http://127.0.0.1:8000/api'

function Stars({ value, onChange, label }) {
  return (
    <span className="rider-stars" aria-label={label}>
      {label}: {[1, 2, 3, 4, 5].map((n) => <button key={n} type="button" className={n <= (value ?? 0) ? 'on' : ''} onClick={() => onChange(n)}>★</button>)}
    </span>
  )
}

export function RiderRateRecent({ items = [], headers, onDone }) {
  const [form, setForm] = useState({})
  if (!items.length) return null
  const set = (id, patch) => setForm((f) => ({ ...f, [id]: { ...(f[id] ?? {}), ...patch } }))

  async function send(item) {
    const f = form[item.order_id] ?? {}
    try {
      const res = await fetch(`${API_URL}/rider/feedback`, { method: 'POST', headers: headers(true), body: JSON.stringify({ order_id: item.order_id, store_rating: f.store ?? null, buyer_rating: f.buyer ?? null, note: f.note?.trim() || null }) })
      const body = await res.json().catch(() => ({}))
      if (!res.ok) throw new Error(body.message ?? 'Could not send.')
      onDone()
    } catch (e) { window.alert(e.message) }
  }

  return (
    <section className="rider-section rider-rate">
      <h3>Rate recent deliveries</h3>
      <p className="rider-card-note">Private — only {brandName()}&rsquo;s team sees it, never the seller or the buyer. It helps fix problems at stores or with buyers.</p>
      {items.map((it) => {
        const f = form[it.order_id] ?? {}
        return (
          <article key={it.order_id} className="rider-card">
            <strong>Order #{it.order_id}</strong> · {it.store}{it.buyer ? ` → ${it.buyer}` : ''}
            <div><Stars label="Store" value={f.store} onChange={(n) => set(it.order_id, { store: n })} /></div>
            <div><Stars label="Buyer" value={f.buyer} onChange={(n) => set(it.order_id, { buyer: n })} /></div>
            <input maxLength={300} placeholder="Anything we should know? (optional)" value={f.note ?? ''} onChange={(e) => set(it.order_id, { note: e.target.value })} />
            <button type="button" className="rider-btn" disabled={!f.store && !f.buyer} onClick={() => send(it)}>Send</button>
          </article>
        )
      })}
    </section>
  )
}
