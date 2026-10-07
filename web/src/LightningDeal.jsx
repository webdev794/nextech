import { useState } from 'react'

// Lightning deal for one product (seller's own, or any product for admin):
// a start time and the units on offer; it runs for admin's set hours or until
// sold out. Unbeatable deals and Exclusive offers are filled automatically.

const API_URL = import.meta.env.VITE_API_URL ?? 'http://127.0.0.1:8000/api'

export default function LightningDeal({ product, path, headers, rules, onSaved }) {
  const running = product.lightning_ends_at && new Date(product.lightning_ends_at) > new Date()
  const [form, setForm] = useState({ starts_at: '', quantity: Math.max(1, Math.min(50, Number(product.inventory_quantity) || 10)) })
  const [msg, setMsg] = useState('')
  const [busy, setBusy] = useState(false)
  const compare = Number(product.compare_at_price_cents) || 0
  const priceNow = Number(product.price_cents) || 0
  const pct = compare > priceNow && compare > 0 ? Math.floor(((compare - priceNow) * 100) / compare) : 0

  async function send(method) {
    setBusy(true); setMsg('')
    try {
      const response = await fetch(`${API_URL}${path}`, { method, headers: { ...headers(), 'Content-Type': 'application/json' }, body: method === 'POST' ? JSON.stringify({ starts_at: form.starts_at || null, quantity: Number(form.quantity) }) : undefined })
      const text = await response.text()
      const data = text ? JSON.parse(text) : {}
      if (!response.ok) throw new Error(data.message ?? Object.values(data.errors ?? {})[0]?.[0] ?? 'Could not save.')
      onSaved?.(data.data)
      setMsg(method === 'POST' ? 'Lightning deal set.' : 'Lightning deal ended.')
    } catch (e) { setMsg(e.message) } finally { setBusy(false) }
  }

  return (
    <div className="lightning-box">
      <b>⚡ Lightning deal</b>
      {running ? (
        <p>Running {new Date(product.lightning_starts_at) > new Date() ? `from ${new Date(product.lightning_starts_at).toLocaleString()} ` : ''}until {new Date(product.lightning_ends_at).toLocaleString()} · {product.lightning_qty} units on offer. <button type="button" className="link" disabled={busy} onClick={() => send('DELETE')}>End it now</button></p>
      ) : <>
        <p className="lightning-hint">Shown in Lightning deals with a countdown and &ldquo;% claimed&rdquo; for {rules?.lightning_hours ?? 12} hours, or until the units below sell out. Needs at least {rules?.lightning_min_pct ?? 20}% off (&ldquo;compare at&rdquo; vs price){pct ? ` — this product is ${pct}% off now` : ' — this product has no discount yet'}.</p>
        <div className="lightning-row">
          <label>Start <small>blank = now</small><input type="datetime-local" value={form.starts_at} onChange={(e) => setForm({ ...form, starts_at: e.target.value })} /></label>
          <label>Units on offer<input type="number" min="1" max="100000" value={form.quantity} onChange={(e) => setForm({ ...form, quantity: e.target.value })} /></label>
          <button type="button" disabled={busy || pct < (rules?.lightning_min_pct ?? 20)} onClick={() => send('POST')}>Start lightning deal</button>
        </div>
      </>}
      {msg && <p className="lightning-msg">{msg}</p>}
    </div>
  )
}
