import { useCallback, useEffect, useState } from 'react'
import { storeMoney } from './money'

// Admin: a NexTech rider's pay — per delivery, or monthly pay (target, bonus, cap);
// and the top-up pools (extra above bonus caps) to top up riders who fell short.

const API_URL = import.meta.env.VITE_API_URL ?? 'http://127.0.0.1:8000/api'

export function RiderPayPlanEditor({ rider, headers, currency, onSaved }) {
  const p = rider.pay_plan
  const [on, setOn] = useState(!!p)
  const [f, setF] = useState({ monthly: p ? p.monthly_cents / 100 : '', target: p?.target ?? 100, bonus: p ? p.bonus_per_extra_cents / 100 : 0, cap: p ? p.bonus_cap_cents / 100 : 0 })
  const money = (c) => storeMoney(c, currency)
  async function save(plan) {
    const res = await fetch(`${API_URL}/admin/riders/${rider.id}/pay-plan`, { method: 'PATCH', headers: headers(), body: JSON.stringify({ plan }) })
    const d = await res.json().catch(() => ({}))
    if (res.ok) onSaved(d.data); else window.alert(d.message ?? 'Could not save.')
  }
  return (
    <div className="admin-alert rider-pay-plan">
      <b>Pay:</b>{' '}
      <label><input type="radio" checked={!on} onChange={() => { setOn(false); if (p) save(null) }} /> Per delivery</label>{' '}
      <label><input type="radio" checked={on} onChange={() => setOn(true)} /> Monthly pay</label>
      {on && <span className="rider-pay-fields">
        <label>Monthly pay <input type="number" min="1" value={f.monthly} onChange={(e) => setF({ ...f, monthly: e.target.value })} /></label>
        <label>at (deliveries) <input type="number" min="1" value={f.target} onChange={(e) => setF({ ...f, target: e.target.value })} /></label>
        <label>bonus per extra <input type="number" min="0" value={f.bonus} onChange={(e) => setF({ ...f, bonus: e.target.value })} /></label>
        <label>bonus up to <input type="number" min="0" value={f.cap} onChange={(e) => setF({ ...f, cap: e.target.value })} /></label>
        <button type="button" className="act" disabled={!(Number(f.monthly) > 0) || !(Number(f.target) > 0)} onClick={() => save({ monthly_cents: Math.round(Number(f.monthly) * 100), target: Number(f.target), bonus_per_extra_cents: Math.round(Number(f.bonus || 0) * 100), bonus_cap_cents: Math.round(Number(f.cap || 0) * 100) })}>Save</button>
        {p && rider.deliveries_this_month != null && <small className="muted"> This month: {rider.deliveries_this_month} of {p.target} · full pay {money(p.monthly_cents)}</small>}
      </span>}
    </div>
  )
}

export function RiderTopUpPools({ headers, onMessage }) {
  const [pools, setPools] = useState([])
  const load = useCallback(() => fetch(`${API_URL}/admin/rider-pools`, { headers: headers() }).then((r) => r.json()).then((d) => setPools(d.data ?? [])).catch(() => {}), [headers])
  useEffect(() => { Promise.resolve().then(load) }, [load])
  const open = pools.filter((p) => p.pool_cents > 0 || (p.short ?? []).some((s) => s.topped_cents < s.short_cents))
  if (!open.length) return null
  return (
    <div className="admin-form">
      <h3 className="admin-subhead">Top-up pools</h3>
      <p className="muted">What busy riders on monthly pay earned above their bonus cap. Use it to top up riders who fell short of their monthly pay.</p>
      {open.map((p) => (
        <div key={p.id}>
          <b>{p.month} · {p.market}</b> — pool {storeMoney(p.pool_cents, p.market === 'IN' ? 'inr' : 'usd')}
          <ul>{(p.short ?? []).map((s) => (
            <li key={s.rider_id}>{s.name}: short {storeMoney(s.short_cents, p.market === 'IN' ? 'inr' : 'usd')}{s.topped_cents ? ` · topped up ${storeMoney(s.topped_cents, p.market === 'IN' ? 'inr' : 'usd')}` : ''}{' '}
              {p.pool_cents > 0 && s.topped_cents < s.short_cents && <button type="button" className="act ghost" onClick={async () => {
                const max = Math.min(p.pool_cents, s.short_cents - s.topped_cents)
                const amt = window.prompt(`Top up ${s.name} by (up to ${(max / 100).toFixed(2)}):`, (max / 100).toFixed(2))
                if (!amt || !(Number(amt) > 0)) return
                const res = await fetch(`${API_URL}/admin/rider-pools/top-up`, { method: 'POST', headers: headers(), body: JSON.stringify({ pool_id: p.id, rider_id: s.rider_id, amount_cents: Math.round(Number(amt) * 100) }) })
                const d = await res.json().catch(() => ({}))
                if (res.ok) { setPools(d.data ?? []); onMessage?.(`${s.name} topped up.`) } else onMessage?.(d.message ?? 'Could not top up.')
              }}>Top up</button>}
            </li>
          ))}</ul>
        </div>
      ))}
    </div>
  )
}
