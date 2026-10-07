import { useState } from 'react'

// Lightning deal for one product (seller's own, or any product for admin):
// a % off the regular price while it runs — fixed, or picked each round from a
// range — a start time and the units on offer. It runs for admin's set hours
// or until sold out, then the price goes back; with auto-restart a new round
// begins at a slightly different % from the range.

const API_URL = import.meta.env.VITE_API_URL ?? 'http://127.0.0.1:8000/api'

export default function LightningDeal({ product, path, headers, rules, onSaved }) {
  const running = product.lightning_ends_at && new Date(product.lightning_ends_at) > new Date()
  const minPct = rules?.lightning_min_pct ?? 20
  const [form, setForm] = useState({ starts_at: '', quantity: Math.max(1, Math.min(50, Number(product.inventory_quantity) || 10)), pct_min: product.lightning_pct_min ?? minPct, pct_max: product.lightning_pct_max ?? '', repeat: !!product.lightning_repeat })
  const [msg, setMsg] = useState('')
  const [busy, setBusy] = useState(false)

  async function send(method) {
    setBusy(true); setMsg('')
    try {
      const body = method === 'POST' ? JSON.stringify({ starts_at: form.starts_at || null, quantity: Number(form.quantity), pct_min: Number(form.pct_min), pct_max: form.pct_max === '' ? null : Number(form.pct_max), repeat: form.repeat }) : undefined
      const response = await fetch(`${API_URL}${path}`, { method, headers: { ...headers(), 'Content-Type': 'application/json' }, body })
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
        <p>{product.lightning_pct}% off {new Date(product.lightning_starts_at) > new Date() ? `from ${new Date(product.lightning_starts_at).toLocaleString()} ` : ''}until {new Date(product.lightning_ends_at).toLocaleString()} · {product.lightning_qty} units on offer{product.lightning_repeat ? ` · restarts automatically at ${product.lightning_pct_min}${product.lightning_pct_max > product.lightning_pct_min ? `–${product.lightning_pct_max}` : ''}% off` : ''}. <button type="button" className="link" disabled={busy} onClick={() => send('DELETE')}>End it now</button></p>
      ) : <>
        <p className="lightning-hint">Lightning deals show first, with a countdown and &ldquo;% claimed&rdquo;, for {rules?.lightning_hours ?? 12} hours or until the units sell out. The price drops by the % below while it runs, then goes back. At least {minPct}% off; up to {rules?.lightning_max_share ?? 40}% of your products at once.</p>
        <div className="lightning-row">
          <label>% off<input type="number" min={minPct} max="90" value={form.pct_min} onChange={(e) => setForm({ ...form, pct_min: e.target.value })} /></label>
          <label>up to % <small>optional range</small><input type="number" min={form.pct_min || minPct} max="90" value={form.pct_max} placeholder="—" onChange={(e) => setForm({ ...form, pct_max: e.target.value })} /></label>
          <label>Units on offer<input type="number" min="1" max="100000" value={form.quantity} onChange={(e) => setForm({ ...form, quantity: e.target.value })} /></label>
          <label>Start <small>blank = now</small><input type="datetime-local" value={form.starts_at} onChange={(e) => setForm({ ...form, starts_at: e.target.value })} /></label>
        </div>
        <label className="lightning-check"><input type="checkbox" checked={form.repeat} onChange={(e) => setForm({ ...form, repeat: e.target.checked })} /> Restart automatically when it ends or sells out{form.pct_max !== '' && Number(form.pct_max) > Number(form.pct_min) ? ` — each round at a % between ${form.pct_min} and ${form.pct_max}` : ''}</label>
        <div><button type="button" disabled={busy || Number(form.pct_min) < minPct} onClick={() => send('POST')}>Start lightning deal</button></div>
      </>}
      {msg && <p className="lightning-msg">{msg}</p>}
    </div>
  )
}
