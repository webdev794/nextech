import { useCallback, useEffect, useRef, useState } from 'react'
import { BrandLogo } from './BrandLogo'
import { formatMoney } from './money'
import { TONES, loadAlertPrefs, saveAlertPrefs, getCustomTone, saveCustomTone, clearCustomTone, previewTone, startRiderAlarmLoop, stopRiderAlarmLoop } from './riderAlert'
import RiderEarnings from './RiderEarnings'
import { RiderChatDock } from './SurfaceChats'
import { openChat } from './chatDockUtils'

const API_URL = import.meta.env.VITE_API_URL ?? 'http://127.0.0.1:8000/api'
const STORE_URL = import.meta.env.BASE_URL || '/'

// Order amounts in the order's own currency (India: ₹).
const money = (c, currency) => formatMoney(c ?? 0, currency || 'usd')
const STATUS_LABEL = {
  confirmed: 'Confirmed', packing: 'Being packed', ready_for_delivery: 'Ready for pickup',
  out_for_delivery: 'Out for delivery', completed: 'Delivered', cancelled: 'Cancelled',
}
const addressText = (a) => [a?.name, a?.line1, a?.line2, [a?.city, a?.state, a?.postal_code].filter(Boolean).join(' ')].filter(Boolean).join(', ')

async function readJson(res) {
  const text = await res.text()
  try { return text ? JSON.parse(text) : {} } catch { return {} }
}

function DeliveryCard({ order, pool, headers, onDone, onChat }) {
  const a = order.delivery_address || {}
  const canStart = order.status === 'ready_for_delivery'
  const canDeliver = order.status === 'out_for_delivery'
  const preparing = order.status === 'confirmed' || order.status === 'packing'

  const [stage, setStage] = useState(null)   // null | 'code' | 'override' | 'refused'
  const [sentTo, setSentTo] = useState('')
  const [code, setCode] = useState('')
  const [note, setNote] = useState('')
  const [working, setWorking] = useState(false)
  const [err, setErr] = useState('')

  const post = async (path, body) => {
    setWorking(true); setErr('')
    try {
      const res = await fetch(`${API_URL}/rider/orders/${order.id}/${path}`, {
        method: 'POST', headers: headers(true), body: body ? JSON.stringify(body) : undefined,
      })
      const data = await readJson(res)
      if (!res.ok) throw new Error(data.message ?? Object.values(data.errors ?? {})[0]?.[0] ?? 'That failed.')
      return data
    } catch (e) { setErr(e.message); throw e } finally { setWorking(false) }
  }

  const simpleAct = async (path, body) => { try { await post(path, body); onDone() } catch { /* err shown */ } }

  const sendCode = async () => {
    try {
      const { data } = await post('delivery-otp')
      setSentTo(data?.to || 'the customer')
    } catch { /* err shown */ }
  }
  const confirmWithCode = async () => { try { await post('deliver', { code: code.trim() }); onDone() } catch { /* err shown */ } }
  const confirmOverride = async () => { try { await post('deliver', { override: true, note: note.trim() }); onDone() } catch { /* err shown */ } }
  const confirmRefused = async () => { try { await post('payment-refused', { note: note.trim() }); onDone() } catch { /* err shown */ } }

  return (
    <article className="rider-card">
      <div className="rider-card-top">
        <strong>Order #{order.id}</strong>
        <span className={`rider-pill s-${order.status}`}>{STATUS_LABEL[order.status] ?? order.status}</span>
      </div>
      <div className="rider-card-cust">
        <span>{order.customer_name || 'Customer'}</span>
        {order.customer_phone && <a href={`tel:${order.customer_phone}`}>📞 {order.customer_phone}</a>}
      </div>
      <p className="rider-card-addr">{addressText(a)}</p>
      {order.delivery_instructions && <p className="rider-card-note">“{order.delivery_instructions}”</p>}
      <ul className="rider-card-items">
        {order.items?.map((it, i) => <li key={i}>{it.quantity} × {it.name}</li>)}
      </ul>
      {order.cod_due > 0 && <p className="rider-card-cod">Collect cash: <b>{money(order.cod_due, order.currency)}</b></p>}

      <div className="rider-card-actions">
        <a className="rider-btn ghost" href={`https://www.google.com/maps/search/?api=1&query=${encodeURIComponent(addressText(a))}`} target="_blank" rel="noreferrer">Directions</a>
        <button className="rider-btn ghost" type="button" onClick={() => onChat(order.id)}>Message customer</button>
        {pool && <button className="rider-btn" type="button" disabled={working} onClick={() => simpleAct('claim')}>Pick up</button>}
        {!pool && preparing && <span className="rider-wait">Waiting for the store to pack it…</span>}
        {!pool && canStart && <button className="rider-btn" type="button" disabled={working} onClick={() => simpleAct('status', { status: 'out_for_delivery' })}>Picked up — start delivery</button>}
        {!pool && order.cod_due > 0 && (canStart || canDeliver) && <button className="rider-btn" type="button" disabled={working} onClick={() => simpleAct('cash-collected')}>Cash collected</button>}
        {!pool && canDeliver && order.cod_due > 0 && stage === null && <button className="rider-btn ghost" type="button" onClick={() => setStage('refused')}>Customer refused to pay</button>}
        {!pool && canDeliver && order.cod_due > 0 && <span className="rider-wait">Collect the cash, then you can mark it delivered.</span>}
        {!pool && canDeliver && stage === null && !(order.cod_due > 0) && <button className="rider-btn primary" type="button" onClick={() => setStage('code')}>Deliver</button>}
      </div>

      {stage === 'code' && (
        <div className="rider-deliver">
          <p className="rider-deliver-h">Confirm handover with a code</p>
          {!sentTo
            ? <button className="rider-btn" type="button" disabled={working} onClick={sendCode}>Send code to customer</button>
            : <>
                <p className="rider-deliver-sent">Code sent to {sentTo}. Ask them to read it out.</p>
                <div className="rider-deliver-row">
                  <input inputMode="numeric" maxLength={6} placeholder="6-digit code" value={code} onChange={(e) => setCode(e.target.value.replace(/[^0-9]/g, ''))} />
                  <button className="rider-btn primary" type="button" disabled={working || code.length < 4} onClick={confirmWithCode}>Confirm delivery</button>
                </div>
                <button className="rider-link-btn" type="button" onClick={sendCode} disabled={working}>Resend</button>
              </>}
          {err && <p className="rider-deliver-err">{err}</p>}
          <button className="rider-link-btn danger" type="button" onClick={() => { setStage('override'); setErr('') }}>Can’t verify? Mark delivered without a code</button>
          <button className="rider-link-btn" type="button" onClick={() => { setStage(null); setErr(''); setCode('') }}>Cancel</button>
        </div>
      )}

      {stage === 'override' && (
        <div className="rider-deliver">
          <p className="rider-deliver-h">Mark delivered without a code</p>
          <p className="rider-deliver-sent">This is recorded for the store. Say what happened (e.g. code not arriving, handed to customer in person, left with neighbour).</p>
          <textarea rows={3} placeholder="What happened at handover" value={note} onChange={(e) => setNote(e.target.value)} />
          {err && <p className="rider-deliver-err">{err}</p>}
          <div className="rider-deliver-row">
            <button className="rider-btn primary" type="button" disabled={working || note.trim().length < 5} onClick={confirmOverride}>Mark delivered</button>
            <button className="rider-link-btn" type="button" onClick={() => { setStage('code'); setErr('') }}>Back to code</button>
          </div>
        </div>
      )}

      {stage === 'refused' && (
        <div className="rider-deliver">
          <p className="rider-deliver-h">Customer refused to pay</p>
          <p className="rider-deliver-sent">This cancels the order on the spot and notifies the store. Say what happened.</p>
          <textarea rows={3} placeholder="What the customer said / why they refused" value={note} onChange={(e) => setNote(e.target.value)} />
          {err && <p className="rider-deliver-err">{err}</p>}
          <div className="rider-deliver-row">
            <button className="rider-btn reject" type="button" disabled={working || note.trim().length < 5} onClick={confirmRefused}>Cancel order</button>
            <button className="rider-link-btn" type="button" onClick={() => { setStage(null); setErr(''); setNote('') }}>Back</button>
          </div>
        </div>
      )}
    </article>
  )
}

function Delta({ now, prev }) {
  const d = (now ?? 0) - (prev ?? 0)
  if (d === 0) return <em className="rider-stat-delta flat">no change vs last week</em>
  return <em className={`rider-stat-delta ${d > 0 ? 'up' : 'down'}`}>{d > 0 ? '▲' : '▼'} {Math.abs(d)} vs last week</em>
}

function RiderStats({ stats }) {
  const stars = stats.rating_avg != null ? stats.rating_avg.toFixed(1) : '—'
  const verified = stats.verified_rate != null ? `${Math.round(stats.verified_rate * 100)}%` : '—'
  const acceptance = stats.acceptance_rate != null ? `${Math.round(stats.acceptance_rate * 100)}%` : '—'
  const weekHrs = stats.shift?.week_worked_minutes
  return (
    <section className="rider-stats">
      <div className="rider-stat">
        <span className="rider-stat-n">{stats.deliveries_total}</span>
        <span className="rider-stat-l">Deliveries all-time</span>
      </div>
      <div className="rider-stat">
        <span className="rider-stat-n">{stats.deliveries_week}</span>
        <span className="rider-stat-l">This week</span>
        <Delta now={stats.deliveries_week} prev={stats.deliveries_week_prev} />
      </div>
      <div className="rider-stat">
        <span className="rider-stat-n">★ {stars}</span>
        <span className="rider-stat-l">{stats.rating_count} rating{stats.rating_count === 1 ? '' : 's'}</span>
      </div>
      <div className="rider-stat">
        <span className="rider-stat-n">{acceptance}</span>
        <span className="rider-stat-l">Offers accepted{stats.offers_total ? ` · ${stats.offers_total} offered` : ''}</span>
      </div>
      <div className="rider-stat">
        <span className="rider-stat-n">{stats.declined_total ?? 0} / {stats.missed_total ?? 0}</span>
        <span className="rider-stat-l">Rejected / missed</span>
      </div>
      {weekHrs != null && (
        <div className="rider-stat">
          <span className="rider-stat-n">{fmtDur(weekHrs)}</span>
          <span className="rider-stat-l">Hours this week</span>
        </div>
      )}
      <div className="rider-stat">
        <span className="rider-stat-n">{verified}</span>
        <span className="rider-stat-l">Code-verified</span>
      </div>
      {stats.cod_collected_cents > 0 && (
        <div className="rider-stat">
          <span className="rider-stat-n">{money(stats.cod_collected_cents)}</span>
          <span className="rider-stat-l">Cash collected</span>
        </div>
      )}
      {stats.cod_holding_cents > 0 && (
        <div className="rider-stat rider-stat-warn">
          <span className="rider-stat-n">{money(stats.cod_holding_cents)}</span>
          <span className="rider-stat-l">Cash to return to store</span>
        </div>
      )}
    </section>
  )
}

function AlertSettings({ open, onClose }) {
  const [prefs, setPrefs] = useState(loadAlertPrefs)
  const [custom, setCustom] = useState(getCustomTone)
  const [err, setErr] = useState('')
  const fileRef = useRef(null)

  if (!open) return null

  const update = (patch) => setPrefs(saveAlertPrefs(patch))

  const onUpload = async (e) => {
    const file = e.target.files?.[0]
    e.target.value = ''
    setErr('')
    try {
      const name = await saveCustomTone(file)
      setCustom(getCustomTone())
      update({ toneId: 'custom' })
      setErr(`Saved “${name}”.`)
    } catch (ex) { setErr(ex.message) }
  }

  const removeCustom = () => {
    clearCustomTone()
    setCustom(null)
    if (prefs.toneId === 'custom') update({ toneId: 'alarm' })
  }

  return (
    <div className="rider-alert-pop" role="dialog" aria-label="Alert sound">
      <div className="rider-alert-row">
        <label className="rider-alert-check">
          <input type="checkbox" checked={!prefs.muted} onChange={(e) => update({ muted: !e.target.checked })} />
          Sound on new delivery
        </label>
      </div>
      <div className="rider-alert-row">
        <label htmlFor="rider-tone">Tone</label>
        <select id="rider-tone" value={prefs.toneId} disabled={prefs.muted}
          onChange={(e) => update({ toneId: e.target.value })}>
          {TONES.map((t) => <option key={t.id} value={t.id}>{t.label}</option>)}
          {custom && <option value="custom">My clip — {custom.name}</option>}
        </select>
        <button type="button" className="rider-link-btn" onClick={() => previewTone(prefs.toneId)}>Test</button>
      </div>
      <div className="rider-alert-row">
        <button type="button" className="rider-btn ghost" onClick={() => fileRef.current?.click()}>Upload your own…</button>
        {custom && <button type="button" className="rider-link-btn danger" onClick={removeCustom}>Remove clip</button>}
        <input ref={fileRef} type="file" accept="audio/*" hidden onChange={onUpload} />
      </div>
      {err && <p className="rider-alert-note">{err}</p>}
      <p className="rider-alert-note">A 2–3 second clip works best. It’s stored only in this browser.</p>
      <button type="button" className="rider-link-btn" onClick={onClose}>Close</button>
    </div>
  )
}

const fmtMMSS = (secs) => `${String(Math.floor(secs / 60)).padStart(2, '0')}:${String(secs % 60).padStart(2, '0')}`
const fmtDur = (mins) => { const m = Math.max(0, Math.round(mins)); return m >= 60 ? `${Math.floor(m / 60)}h ${m % 60}m` : `${m}m` }
const clockTime = (iso) => iso ? new Date(iso).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' }) : ''

/**
 * Attendance clock in the rider's top bar: check in, take / end a lunch break,
 * check out. Drives `rider_available`, so being off the clock or on a break
 * means the system won't offer this rider a delivery.
 */
function ShiftBar({ shift, headers, onChange }) {
  const [busy, setBusy] = useState(false)
  const [now, setNow] = useState(() => Date.now())

  useEffect(() => {
    if (!shift?.on_break) return undefined
    const tick = () => setNow(Date.now())
    tick()
    const t = setInterval(tick, 30000)
    return () => clearInterval(t)
  }, [shift?.on_break])

  if (!shift || shift.status === 'off_roster') return null

  async function act(action, reason) {
    if (busy) return
    if (action === 'clock_out' && !window.confirm('Clock out and stop receiving deliveries?')) return
    setBusy(true)
    try {
      const res = await fetch(`${API_URL}/rider/shift`, {
        method: 'POST', headers: headers(true), body: JSON.stringify({ action, reason }),
      })
      const body = await readJson(res)
      if (res.ok) onChange(body.data)
      else window.alert(body.message || 'That did not work.')
    } catch { window.alert('Cannot reach the server.') }
    finally { setBusy(false) }
  }

  const breakMins = shift.break_since ? (now - new Date(shift.break_since).getTime()) / 60000 : 0

  let label
  let actions
  if (shift.status === 'on_break') {
    label = <>☕ On break {fmtDur(breakMins)}{shift.break_reason ? ` — ${shift.break_reason}` : ''}</>
    actions = <button type="button" className="rider-link" disabled={busy} onClick={() => act('break_end')}>End break</button>
  } else if (shift.status === 'paused') {
    label = <>⏸ Paused{shift.unavailable_reason ? ` — ${shift.unavailable_reason}` : ' by the store'}</>
    actions = <button type="button" className="rider-link" disabled={busy} onClick={() => act('clock_out')}>Clock out</button>
  } else if (shift.status === 'clocked_in') {
    label = <>🟢 On since {clockTime(shift.clock_in_at)} · {fmtDur(shift.today_worked_minutes)} today</>
    actions = <>
      <button type="button" className="rider-link" disabled={busy} onClick={() => act('break_start', 'Lunch')}>Lunch break</button>
      <button type="button" className="rider-link" disabled={busy} onClick={() => act('clock_out')}>Clock out</button>
    </>
  } else {
    label = <>⚪ Off the clock{shift.week_worked_minutes ? ` · ${fmtDur(shift.week_worked_minutes)} this week` : ''}</>
    actions = <button type="button" className="rider-btn" disabled={busy} onClick={() => act('clock_in')}>Clock in</button>
  }

  return (
    <div className="rider-shiftbar">
      <span className="rider-shiftbar-label">{label}</span>
      <span className="rider-shiftbar-actions">{actions}</span>
    </div>
  )
}

/**
 * Blocking prompt for pending delivery offers: a live countdown plus Accept /
 * Reject. Rejecting (or letting it lapse) re-offers the order to another rider.
 */
function OfferPrompt({ offers, headers, onResolved }) {
  const [now, setNow] = useState(() => Date.now())
  const [working, setWorking] = useState(false)

  useEffect(() => {
    const tick = () => setNow(Date.now())
    tick()
    const t = setInterval(tick, 1000)
    return () => clearInterval(t)
  }, [])

  // When any offer's countdown hits zero, pull a fresh board once rather than
  // waiting up to 15s for the next poll — the server sweep has (or soon will)
  // re-offer it.
  const anyExpired = offers.some((o) => new Date(o.offer_expires_at).getTime() - now <= 0)
  useEffect(() => {
    if (anyExpired) onResolved()
  }, [anyExpired, onResolved])

  async function respond(id, accept) {
    if (working) return
    setWorking(true)
    try {
      const res = await fetch(`${API_URL}/rider/orders/${id}/respond`, {
        method: 'POST', headers: headers(true), body: JSON.stringify({ accept }),
      })
      const body = await readJson(res)
      onResolved(res.ok ? body.data : undefined)
    } catch {
      onResolved()
    } finally {
      setWorking(false)
    }
  }

  return (
    <div className="rider-offer-overlay" role="alertdialog" aria-modal="true" aria-label="New delivery offer">
      <div className="rider-offer-wrap">
        {offers.map((o) => {
          const secs = Math.max(0, Math.ceil((new Date(o.offer_expires_at).getTime() - now) / 1000))
          return (
            <article className="rider-offer-card" key={o.id}>
              <h3 className="rider-offer-h">New delivery — Order #{o.id}</h3>
              <p className="rider-offer-sub">{o.customer_name || 'Customer'}<br />{addressText(o.delivery_address)}</p>
              <p className="rider-offer-sub">{o.items?.reduce((n, i) => n + (i.quantity || 0), 0) ?? 0} item(s){o.delivery_instructions ? ` · “${o.delivery_instructions}”` : ''}</p>
              {o.cod_due > 0 && <p className="rider-offer-cod">Collect cash {money(o.cod_due, o.currency)}</p>}
              <div className={`rider-offer-count${secs > 10 ? ' calm' : ''}`}>{fmtMMSS(secs)}</div>
              {secs === 0
                ? <p className="rider-offer-wait">Re-offering to another rider…</p>
                : (
                  <div className="rider-offer-actions">
                    <button type="button" className="rider-btn primary" disabled={working} onClick={() => respond(o.id, true)}>Accept</button>
                    <button type="button" className="rider-btn reject" disabled={working} onClick={() => respond(o.id, false)}>Reject</button>
                  </div>
                )}
              <button type="button" className="rider-offer-replay" onClick={() => previewTone(loadAlertPrefs().toneId)}>▶ Replay tone</button>
            </article>
          )
        })}
      </div>
    </div>
  )
}

// Sellers' own deliveries given to this rider: pick up at the seller's address,
// deliver, and confirm with the code the buyer reads out (and the cash, if any).
function SellerDeliveries({ headers, refreshKey }) {
  const [list, setList] = useState([])
  const [cashBy, setCashBy] = useState([]) // cash I hold per seller store, and where I'm paused
  const [code, setCode] = useState({})
  const [cash, setCash] = useState({})
  const [busy, setBusy] = useState(null)
  const [msg, setMsg] = useState('')
  const load = useCallback(async () => {
    try {
      const res = await fetch(`${API_URL}/rider/packages`, { headers: headers() })
      const body = await readJson(res)
      if (res.ok) { setList(body.data ?? []); setCashBy(body.cash ?? []) }
    } catch { /* keep last */ }
  }, [headers])
  useEffect(() => {
    Promise.resolve().then(load)
    const t = setInterval(load, 30000)
    return () => clearInterval(t)
  }, [load, refreshKey])

  async function deliver(p) {
    setBusy(p.id); setMsg('')
    try {
      const res = await fetch(`${API_URL}/rider/packages/${p.id}/deliver`, { method: 'POST', headers: headers(true), body: JSON.stringify({ delivery_code: (code[p.id] ?? '').trim(), cash_collected: !!cash[p.id] }) })
      const body = await readJson(res)
      if (!res.ok) throw new Error(body.message ?? Object.values(body.errors ?? {})[0]?.[0] ?? 'Could not mark it delivered.')
      setList(body.data ?? []); setCashBy(body.cash ?? [])
      setMsg(`Order #${p.order_id} delivered.`)
    } catch (e) { setMsg(e.message) } finally { setBusy(null) }
  }

  if (!list.length && !cashBy.length) return null
  const fmt = (cents, cur) => new Intl.NumberFormat(undefined, { style: 'currency', currency: String(cur).toUpperCase() }).format(cents / 100)
  return (
    <section className="rider-section">
      <h2>Store deliveries ({list.length})</h2>
      {msg && <p className="rider-error">{msg}</p>}
      {cashBy.map((c) => (
        <div key={c.store} className={c.paused || c.disputed ? 'rider-error' : 'rider-cash'}>
          {c.held_cents > 0 && <p>{c.paused ? `Paused for ${c.store}: visit the store and hand over the ${fmt(c.held_cents, c.currency)} you hold before more deliveries.` : `You hold ${fmt(c.held_cents, c.currency)} of ${c.store}’s cash (limit ${fmt(c.limit_cents, c.currency)}) — hand it over at the store today.`}</p>}
          {c.held_cents > 0 && <button type="button" disabled={busy === `cash-${c.store_id}`} onClick={async () => {
            if (!window.confirm(`Did you hand ${fmt(c.held_cents, c.currency)} to ${c.store}? They confirm it when they have it.`)) return
            setBusy(`cash-${c.store_id}`)
            try {
              const res = await fetch(`${API_URL}/rider/stores/${c.store_id}/cash-handed`, { method: 'POST', headers: headers(true) })
              const body = await readJson(res)
              if (!res.ok) throw new Error(body.message ?? 'Could not send.')
              setList(body.data ?? []); setCashBy(body.cash ?? []); setMsg(`${c.store} is asked to confirm.`)
            } catch (e) { setMsg(e.message) } finally { setBusy(null) }
          }}>I handed over the cash</button>}
          {c.claimed && <p>You said you handed cash to {c.store} — waiting for them to confirm.</p>}
        </div>
      ))}
      {list.map((p) => (
        <div key={p.id} className="rider-card">
          <strong>Order #{p.order_id} — {p.shop}</strong>
          {p.pickup && <p>Pick up: {p.pickup}</p>}
          <p>Deliver to: <b>{p.buyer}</b>{p.phone && <> · <a href={`tel:${p.phone}`}>{p.phone}</a></>}<br />{p.address}</p>
          {p.instructions && <p>Note: {p.instructions}</p>}
          <p>{p.items.join(', ')}</p>
          {p.cash_cents > 0 && <p className="rider-cash">Cash on delivery: collect <b>{new Intl.NumberFormat(undefined, { style: 'currency', currency: p.currency.toUpperCase() }).format(p.cash_cents / 100)}</b></p>}
          <div className="rider-deliver-row">
            <input inputMode="numeric" maxLength={8} placeholder="Buyer's delivery code" value={code[p.id] ?? ''} onChange={(e) => setCode({ ...code, [p.id]: e.target.value.replace(/[^0-9]/g, '') })} />
            {p.cash_cents > 0 && <label><input type="checkbox" checked={!!cash[p.id]} onChange={(e) => setCash({ ...cash, [p.id]: e.target.checked })} /> Cash collected</label>}
            <button type="button" disabled={busy === p.id || !(code[p.id] ?? '').trim() || (p.cash_cents > 0 && !cash[p.id])} onClick={() => deliver(p)}>Delivered</button>
          </div>
        </div>
      ))}
    </section>
  )
}

export default function RiderConsole({ token, onSignOut }) {
  const headers = useCallback((json) => ({
    Accept: 'application/json',
    Authorization: `Bearer ${token}`,
    ...(json ? { 'Content-Type': 'application/json' } : {}),
  }), [token])

  const [data, setData] = useState({ assigned: [], pool: [], pending_returns: [] })
  const [stats, setStats] = useState(null)
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState('')
  const [alertOpen, setAlertOpen] = useState(false)
  const [payKey, setPayKey] = useState(0)

  const load = useCallback(async () => {
    try {
      const res = await fetch(`${API_URL}/rider/orders`, { headers: headers() })
      const body = await readJson(res)
      if (res.ok) {
        setData(body.data ?? { assigned: [], pool: [], pending_returns: [] })
        setError('')
      } else setError(body.message ?? 'Could not load your deliveries.')
    } catch { setError('Cannot reach the server.') }
  }, [headers])

  // Orders assigned to me that I haven't accepted yet — drive the blocking
  // prompt + the repeating alarm.
  const pendingOffers = data.assigned.filter((o) => o.offer_pending)

  const applyBoard = useCallback((board) => {
    if (board) setData(board)
    load()
  }, [load])

  useEffect(() => {
    if (pendingOffers.length > 0) startRiderAlarmLoop()
    else stopRiderAlarmLoop()
    return () => stopRiderAlarmLoop()
  }, [pendingOffers.length])

  const loadStats = useCallback(async () => {
    try {
      const res = await fetch(`${API_URL}/rider/stats`, { headers: headers() })
      const body = await readJson(res)
      if (res.ok) setStats(body.data ?? null)
    } catch { /* keep last */ }
  }, [headers])

  useEffect(() => {
    let stop = false
    ;(async () => { await load(); if (!stop) setLoading(false) })()
    const t = setInterval(load, 15000)
    return () => { stop = true; clearInterval(t) }
  }, [load])

  useEffect(() => {
    Promise.resolve().then(loadStats)
    const t = setInterval(loadStats, 60000)
    return () => clearInterval(t)
  }, [loadStats])

  const refresh = () => { load(); loadStats(); setPayKey((k) => k + 1) }
  // Customer chats open bottom right (RiderChatDock), beside the NexTech one.
  const chatWithCustomer = (orderId) => openChat({ key: `order-${orderId}`, kind: 'order', id: orderId, name: `Order #${orderId}`, subtitle: 'Chat with the customer' })

  if (loading) return <div className="rider-shell"><div className="rider-loading">Loading your deliveries…</div></div>

  return (
    <div className="rider-shell">
      <header className="rider-bar">
        <strong className="rider-brand"><BrandLogo onDark /> Deliveries</strong>
        <div>
          <div className="rider-alert-wrap">
            <button type="button" className="rider-link" aria-expanded={alertOpen} onClick={() => setAlertOpen((v) => !v)}>Alert sound</button>
            <AlertSettings open={alertOpen} onClose={() => setAlertOpen(false)} />
          </div>
          <button type="button" className="rider-link" onClick={refresh}>Refresh</button>
          <a className="rider-link" href={STORE_URL}>Store</a>
          <button type="button" className="rider-link" onClick={onSignOut}>Sign out</button>
        </div>
      </header>

      <ShiftBar shift={data.shift} headers={headers} onChange={(s) => setData((d) => ({ ...d, shift: s }))} />

      {pendingOffers.length > 0 && (
        <OfferPrompt offers={pendingOffers} headers={headers} onResolved={applyBoard} />
      )}

      {error && <p className="rider-error">{error}</p>}
      {data.cash_block && <p className="rider-error"><b>You&rsquo;re paused in every store</b> — {data.cash_block}. Visit the store to hand it over; you can take deliveries again once it&rsquo;s marked received.</p>}

      {stats && <RiderStats stats={stats} />}

      <RiderEarnings headers={headers} refreshKey={payKey} />

      {(data.pending_returns ?? []).length > 0 && (
        <div className="rider-returns">
          <strong>Return items to the store</strong>
          <ul>
            {data.pending_returns.map((r) => (
              <li key={r.id}>Order #{r.id}{r.reason ? ` — ${r.reason}` : ''}</li>
            ))}
          </ul>
          <p>The customer refused to pay — bring these items back to the store. This clears once the store confirms they're back.</p>
        </div>
      )}

      <SellerDeliveries headers={headers} refreshKey={payKey} />

      <section className="rider-section">
        <h2>My deliveries ({data.assigned.length})</h2>
        {data.assigned.length === 0
          ? <p className="rider-empty">Nothing assigned to you right now. New assignments appear here automatically.</p>
          : data.assigned.map((o) => <DeliveryCard key={o.id} order={o} headers={headers} onDone={refresh} onChat={chatWithCustomer} />)}
      </section>

      {data.pool.length > 0 && (
        <section className="rider-section">
          <h2>Available to pick up ({data.pool.length})</h2>
          {data.pool.map((o) => <DeliveryCard key={o.id} order={o} pool headers={headers} onDone={refresh} onChat={chatWithCustomer} />)}
        </section>
      )}

      <RiderChatDock headers={headers} orders={data.assigned} />

    </div>
  )
}
