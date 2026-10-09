import { useCallback, useEffect, useState } from 'react'
import { useLiveRefresh } from './useLiveRefresh'
import { brandName } from './useBranding'
import { RiderMoneyTable } from './RiderMoneyTable'
import { EmailRoutineToggle } from './EmailRoutineToggle'
import { RiderHoursEditor } from './RiderHoursEditor'
import { hoursLabel } from './riderHours'

// Seller Center → Local delivery: the seller's store, a step-by-step guide to
// hiring riders, the hiring switch, applications to accept or decline, and the
// riders who deliver for the store (experience and time with the store).

const API_URL = import.meta.env.VITE_API_URL ?? 'http://127.0.0.1:8000/api'
const VEHICLES = { bicycle: 'Bicycle', scooter: 'Scooter', motorbike: 'Motorbike', car: 'Car' }
const SHIFT = { clocked_in: 'On shift', on_break: 'On a break', paused: 'Paused', off: 'Off shift', off_roster: 'Not active' }
const STATUS = { on: 'On', off: 'Off', off_requested: 'Waiting to turn off', locked: `Off — only the store can turn it on` }

async function send(headers, path, method = 'GET', body) {
  const response = await fetch(`${API_URL}${path}`, { method, headers: { ...headers(), ...(body ? { 'Content-Type': 'application/json' } : {}) }, body: body ? JSON.stringify(body) : undefined })
  const text = await response.text()
  let data
  try { data = text ? JSON.parse(text) : {} } catch { data = {} }
  if (!response.ok) throw new Error(data.message ?? Object.values(data.errors ?? {})[0]?.[0] ?? 'Something went wrong.')
  return data
}

// "2 yrs 3 mos" from months; "since" dates as a length of time.
const months = (m) => (m == null ? '—' : m === 0 ? 'None' : [Math.floor(m / 12) && `${Math.floor(m / 12)} yr${Math.floor(m / 12) === 1 ? '' : 's'}`, m % 12 && `${m % 12} mo${m % 12 === 1 ? '' : 's'}`].filter(Boolean).join(' '))
const since = (date) => {
  if (!date) return '—'
  const d = new Date(date)
  const m = Math.max(0, (new Date().getFullYear() - d.getFullYear()) * 12 + new Date().getMonth() - d.getMonth())
  const days = Math.max(1, Math.round((Date.now() - d) / 86400000))
  return m < 1 ? `${days} day${days === 1 ? '' : 's'}` : months(m)
}

export function LocalDelivery({ headers, go }) {
  const [data, setData] = useState(null)
  const [msg, setMsg] = useState('')
  const [busy, setBusy] = useState(false)

  const load = useCallback(() => send(headers, '/seller/local-delivery').then((d) => setData(d.data)).catch((e) => setMsg(e.message)), [headers])
  useEffect(() => { Promise.resolve().then(load) }, [load])
  useLiveRefresh(useCallback(() => send(headers, '/seller/local-delivery').then((d) => setData(d.data)).catch(() => {}), [headers]))

  async function act(path, method, body, ok) {
    setBusy(true); setMsg('')
    try { const d = await send(headers, path, method, body); setData(d.data); if (ok) setMsg(ok) } catch (e) { setMsg(e.message) } finally { setBusy(false) }
  }

  // Documents are private: fetched with the seller's sign-in, then opened.
  async function viewDoc(path) {
    try {
      const response = await fetch(`${API_URL}/seller/kyc-document/${path}`, { headers: headers() })
      if (!response.ok) throw new Error('Could not open that document.')
      window.open(URL.createObjectURL(await response.blob()), '_blank', 'noopener')
    } catch (e) { setMsg(e.message) }
  }

  if (!data) return <div className="sc-card"><p className="sc-muted">{msg || 'Loading…'}</p></div>
  const money = (cents) => new Intl.NumberFormat(undefined, { style: 'currency', currency: String(data.currency ?? 'usd').toUpperCase() }).format((cents ?? 0) / 100)
  const { store } = data
  const on = store.status === 'on'
  const pending = data.applications.filter((a) => a.status === 'pending')
  const STATUS_TEXT = { pending: 'Waiting for you', seller_accepted: `Accepted by you — waiting for ${brandName()} to approve`, approved: 'Approved — your rider' }

  return (
    <>
      <h1 className="sc-title">Local delivery</h1>
      {msg && <div className="sc-alert warn"><span>{msg}</span><button type="button" onClick={() => setMsg('')}>OK</button></div>}

      <div className="sc-card">
        <h2 className="sc-h2">Your store</h2>
        <p>Local delivery: <b>{STATUS[store.status]}</b>{store.address ? ` · ${store.address}` : ''}{on ? ` · within ${store.radius_km} km` : ''}</p>
        <p className="sc-muted">Turn it on, and set the address, area and fee, in <button type="button" className="sc-link" onClick={() => go('shipping')}>Shipping settings → Local delivery</button>.</p>
      </div>

      <div className="sc-card ld-guide">
        <h2 className="sc-h2">Hire your own riders — step by step</h2>
        <ol>
          <li className={on ? 'done' : ''}><b>Turn on local delivery</b> in Shipping settings. Until riders join, you deliver these orders yourself — or send any order by courier.</li>
          <li className={store.hiring_open ? 'done' : ''}><b>Open hiring</b> below. The <b>&ldquo;Work with us — deliver&rdquo;</b> link then goes live in the {brandName()} footer for signed-in buyers near your store.</li>
          <li><b>Find people:</b> post the job on sites like Quikr or OLX, put a poster outside your shop, or ask people you know and trust who are looking for work. They need <b>their own vehicle</b> (and pay its fuel and running costs) and must be at least <b>{data.min_age}</b>.</li>
          <li><b>They apply:</b> sign up as a buyer on {brandName()}, then open the footer link or this address: <code>{data.apply_url}</code> — with their ID proof, licence (for motor vehicles), experience and contact details.</li>
          <li><b>You choose</b> under Applications below: check their documents and age, then accept or decline. {brandName()} checks the documents of riders you accept and gives the final approval.</li>
          <li><b>They start:</b> riders sign in to the Rider app and start shifts. You manage and pay them yourself. <b>Cash on delivery</b> your riders collect is yours to collect from them by the end of each day — {brandName()} isn&rsquo;t responsible for it.</li>
        </ol>
        <label className="sc-check">
          <input type="checkbox" checked={store.hiring_open} disabled={busy || (!on && !store.hiring_open)} onChange={(e) => act('/seller/local-delivery/hiring', 'PATCH', { open: e.target.checked }, e.target.checked ? 'Hiring is open — the Apply link is now live for buyers near your store.' : 'Hiring closed.')} />
          <b>Hiring riders</b> {store.hiring_open ? '— the Apply link is live near your store' : on ? '' : '(turn on local delivery first)'}
        </label>
      </div>

      <div className="sc-card">
        <h2 className="sc-h2">Applications{pending.length ? ` (${pending.length} waiting)` : ''}</h2>
        {data.applications.length === 0 ? <p className="sc-muted">No applications yet.</p> : (
          <div className="sc-table-wrap"><table className="sc-table">
            <thead><tr><th>Applicant</th><th>Contact</th><th>Age</th><th>Vehicle</th><th>Experience &amp; education</th><th>Health</th><th>Documents</th><th>Status</th><th /></tr></thead>
            <tbody>{data.applications.map((a) => (
              <tr key={a.id}>
                <td><b>{a.name}</b><br /><small className="sc-muted">{a.home_address}</small><br /><small className="sc-muted">{a.priority === 1 ? 'Your store is their first choice' : `Your store is choice ${a.priority}`}</small></td>
                <td>{a.phone}<br /><small className="sc-muted">{a.email}</small></td>
                <td>{a.age ?? '—'}{a.age != null && a.age < data.min_age && <small className="ld-red"> under {data.min_age}</small>}</td>
                <td>{VEHICLES[a.vehicle_type] ?? a.vehicle_type}{a.license_number ? <><br /><small className="sc-muted">Licence {a.license_number}</small></> : null}</td>
                <td>{months(a.experience_months)}{a.education && <><br /><small className="sc-muted">{a.education}</small></>}{a.work_history && <><br /><small className="sc-muted">{a.work_history}</small></>}</td>
                <td>{a.health}</td>
                <td>{Object.entries(a.documents).map(([label, path]) => <div key={label}><button type="button" className="sc-link" onClick={() => viewDoc(path)}>{label}</button></div>)}</td>
                <td>{STATUS_TEXT[a.status] ?? `Declined${a.rejection_reason ? `: ${a.rejection_reason}` : ''}`}</td>
                <td>{a.status === 'pending' && a.unmet?.length > 0 && <small className="ld-red">Can&rsquo;t accept: needs {a.unmet.join(', ')}.</small>}{a.status === 'pending' && <div className="ld-actions">
                  <button type="button" className="sc-primary" disabled={busy || a.unmet?.length > 0} onClick={() => { if (window.confirm(`Accept ${a.name} as your rider? Check their ID shows they're at least ${data.min_age}. ${brandName()} checks their documents and gives the final approval.`)) act(`/seller/rider-applications/${a.id}/approve`, 'POST', {}, `Sent to ${brandName()} for final approval — you'll get a message when it's decided.`) }}>Accept</button>
                  <button type="button" className="sc-link" disabled={busy} onClick={() => { const reason = window.prompt(`Why decline ${a.name}? They see this.`, ''); if (reason?.trim()) act(`/seller/rider-applications/${a.id}/reject`, 'POST', { reason: reason.trim() }, 'Application declined.') }}>Decline</button>
                </div>}</td>
              </tr>
            ))}</tbody>
          </table></div>
        )}
      </div>

      {data.riders.length > 0 && <div className="sc-card">
        <h2 className="sc-h2">Rider money</h2>
        <RiderMoneyTable headers={headers} path="/seller/local-delivery/money" csvPath="/seller/local-delivery/deliveries.csv" currency={data.currency} className="sc-table" />
      </div>}

      <div className="sc-card">
        <h2 className="sc-h2">Your riders ({data.riders.length})</h2>
        <EmailRoutineToggle headers={headers} />
        <p className="sc-muted">Cash on delivery your riders collect is <b>yours</b>: collect it whenever they come back and press <b>Cash received</b>. A rider holding more than your limit, or still holding cash the next morning, is paused for your store until you do. {brandName()} isn&rsquo;t responsible for cash your riders keep or products they damage; the only cover is their unpaid earnings for the month, which go to you instead.</p>
        <label className="ld-limit">You pay per delivery
          <input type="number" min="0" step="0.01" defaultValue={((data.rider_pay_cents ?? 0) / 100).toFixed(2)} onBlur={(e) => { const v = Math.round(Number(e.target.value || 0) * 100); if (v !== data.rider_pay_cents) act('/seller/local-delivery/rider-pay', 'PATCH', { pay_cents: v }, `Riders are paid ${money(v)} per delivery.`) }} />
        </label>
        <p className="sc-muted">{brandName()} pays your riders for each delivery they make for you and takes it from your earnings (shown as &ldquo;Rider pay&rdquo; in Finances). Deliveries you make yourself cost nothing.</p>
        <h3 className="ss-sub">Riders&rsquo; working hours</h3>
        <p className="sc-muted">The days and hours your riders must be on duty. A rider who hasn&rsquo;t clocked in when they start gets a reminder; 30 minutes in, you&rsquo;re told who is missing, and if someone clocks out early. Your days off don&rsquo;t count.{data.rider_hours ? <> Now: <b>{hoursLabel(data.rider_hours)}</b>.</> : ' No hours set yet.'}</p>
        {(data.invites ?? []).length > 0 && <div className="ld-leaves">
          <b>Riders near you who delivered before</b>
          <p className="sc-muted">They have no store right now. Invite the ones you&rsquo;d like — they join only if they accept.</p>
          <ul>{data.invites.map((iv) => <li key={iv.id}>{iv.name}{iv.phone ? ` · ${iv.phone}` : ''} — {iv.status === 'invited' ? <span className="sc-muted">invited, waiting for their answer</span> : <>
            <button type="button" className="sc-link" disabled={busy} onClick={() => act(`/seller/rider-invites/${iv.id}`, 'POST', { invite: true }, `${iv.name} is invited.`)}>Invite</button>{' · '}
            <button type="button" className="sc-link" disabled={busy} onClick={() => act(`/seller/rider-invites/${iv.id}`, 'POST', { invite: false }, 'Done.')}>No thanks</button></>}</li>)}</ul>
        </div>}
        {(data.leaves ?? []).length > 0 && <div className="ld-leaves">
          <b>Riders&rsquo; days off this month</b>
          <ul>{data.leaves.map((l) => <li key={l.id} className={l.kind === 'absent' || !l.told_ahead ? 'ld-red' : ''}>{new Date(`${l.date}T00:00:00`).toLocaleDateString([], { weekday: 'short', day: 'numeric', month: 'short' })} — {l.rider}: {l.kind === 'absent' ? 'missed without asking' : l.told_ahead ? 'asked ahead' : 'asked on the day'}{l.reason ? ` (“${l.reason}”)` : ''}</li>)}</ul>
        </div>}
        <RiderHoursEditor key={JSON.stringify(data.rider_hours ?? null)} value={data.rider_hours} onSave={(hours) => act('/seller/local-delivery/rider-hours', 'PATCH', { hours }, hours ? `Riders' hours: ${hoursLabel(hours)}.` : 'No set hours for riders.')} />
        <label className="ld-limit">Hours riders get to take an order you offer them
          <input type="number" min="1" max="72" step="1" defaultValue={data.rider_pickup_hours ?? 5} onBlur={(e) => { const v = Number(e.target.value || 0); if (v >= 1 && v <= 72 && v !== data.rider_pickup_hours) act('/seller/local-delivery/pickup-hours', 'PATCH', { hours: v }, `Riders get ${v} hour${v === 1 ? '' : 's'} to take an order you offer them.`) }} />
        </label>
        <p className="sc-muted">When you send a local order out you can <b>offer it to all your riders</b>: everyone on your store sees it with the same deadline (riders who come online later too), and the first to take it gets it. If nobody has by then, you&rsquo;re told — deliver it yourself, give it to a rider, or send it by courier. Riders on a break, logged out or on a day off aren&rsquo;t alerted.</p>
        <label className="ld-limit" title="Also the largest order you accept cash on delivery for">Most cash one rider may hold (and largest cash-on-delivery order)
          <input type="number" min="0" step="1" defaultValue={Math.round((data.cash_limit_cents ?? 0) / 100)} onBlur={(e) => { const v = Math.round(Number(e.target.value || 0) * 100); if (v !== data.cash_limit_cents) act('/seller/local-delivery/cash-limit', 'PATCH', { limit_cents: v }, `Limit set to ${money(v)}.`) }} />
        </label>
        {data.riders.length === 0 ? <p className="sc-muted">No riders yet — open hiring above.</p> : (
          <div className="sc-table-wrap"><table className="sc-table">
            <thead><tr><th>Rider</th><th>Vehicle</th><th>Experience</th><th>With you</th><th>Rider for</th><th>Now</th><th>Open orders</th><th>Delivered today</th><th>Your cash they hold</th><th /><th /></tr></thead>
            <tbody>{data.riders.map((r) => (
              <tr key={r.id}>
                <td><b>{r.name}</b><br /><small className="sc-muted">{r.phone}</small>{r.other_stores > 0 && <><br /><small className="sc-muted">Also delivers for {r.other_stores} other store{r.other_stores === 1 ? '' : 's'}</small></>}</td>
                <td>{VEHICLES[r.vehicle] ?? r.vehicle ?? '—'}</td>
                <td>{months(r.experience_months)}</td>
                <td>{since(r.with_store_since)}</td>
                <td>{since(r.rider_since)}</td>
                <td>{SHIFT[r.shift] ?? r.shift}</td>
                <td>{r.active_deliveries}</td>
                <td>{r.delivered_today}</td>
                <td>
                  <b className={r.cash_paused ? 'ld-red' : ''}>{money(r.cash_held_cents)}</b>
                  {r.cash_paused && <><br /><small className="ld-red">Paused until handed over</small></>}
                  {r.cash_later && <><br /><small className="sc-muted">Later — your own risk</small></>}
                  {r.cash_claimed_cents > 0 && <div className="ld-claim">
                    <small><b>{r.name} says they handed you {money(r.cash_claimed_cents)}.</b> Confirm once you have it — or mark it not received.</small>
                    <div className="ld-actions">
                      <button type="button" className="sc-primary" disabled={busy} onClick={() => act(`/seller/riders/${r.id}/cash-received`, 'POST', {}, `Cash from ${r.name} confirmed.`)}>Confirm</button>
                      <button type="button" className="sc-link" disabled={busy} onClick={() => { const reason = window.prompt(`Not received — what happened? ${r.name} is told, and paused until it's handed over (if it isn't, it comes from their earnings at month end — your only cover).`, ''); if (reason?.trim()) act(`/seller/riders/${r.id}/cash-not-received`, 'POST', { reason: reason.trim() }, `${r.name} is paused until the cash is handed over.`) }}>Not received</button>
                    </div>
                  </div>}
                  {(r.cash_held_cents > 0 || r.cash_paused) && <div className="ld-actions">
                    <button type="button" className="sc-primary" disabled={busy} onClick={() => { if (window.confirm(`Did ${r.name} hand you ${money(r.cash_held_cents)}? Confirm only once you have it.`)) act(`/seller/riders/${r.id}/cash-received`, 'POST', {}, `Cash from ${r.name} marked received.`) }}>Cash received</button>
                    {r.cash_paused && <button type="button" className="sc-link" disabled={busy} title={`At your own risk — ${brandName()} isn't responsible`} onClick={() => { if (window.confirm(`Let ${r.name} keep delivering while holding ${money(r.cash_held_cents)}?\n\nAt your own risk — ${brandName()} isn't responsible for cash your riders hold.`)) act(`/seller/riders/${r.id}/cash-later`, 'POST', {}, `${r.name} can keep delivering (your own risk).`) }}>Later</button>}
                  </div>}
                  {r.cash_paused && <small className="sc-muted">Later: at your own risk — {brandName()} isn&rsquo;t responsible.</small>}
                </td>
                <td>
                  <button type="button" className="sc-link" disabled={busy} title={`From your earnings (${money(data.balance_cents ?? 0)} available)`} onClick={() => {
                    const amount = window.prompt(`Bonus for ${r.name} (${data.currency?.toUpperCase()}) — paid from your earnings, ${money(data.balance_cents ?? 0)} available:`, '')
                    if (!amount || !(Number(amount) > 0)) return
                    const note = window.prompt('What for? (optional — the rider sees it)', 'Great work this month') ?? ''
                    act(`/seller/riders/${r.id}/bonus`, 'POST', { amount_cents: Math.round(Number(amount) * 100), note: note.trim() || null }, `${r.name} got a ${money(Math.round(Number(amount) * 100))} bonus.`)
                  }}>Give a bonus</button>
                  {r.signed && <details className="ld-terms"><summary>Signed terms</summary><p className="sc-muted">{r.signed.name}, {r.signed.place} · {new Date(r.signed.at).toLocaleDateString()}</p><ul>{r.signed.terms.map((t) => <li key={t}>{t}</li>)}</ul></details>}
                </td>
                <td><button type="button" className="sc-link" disabled={busy || r.active_deliveries > 0} title={r.active_deliveries > 0 ? 'Finish or reassign their open orders first' : ''} onClick={() => { if (!window.confirm(`Remove ${r.name} from your store?\n\nThey stop getting your orders. Pay already earned is still due to them. ${brandName()} is told, with your reason.`)) return; const reason = window.prompt(`Why are you removing ${r.name}? ${brandName()} gets this.`, ''); if (reason?.trim()) act(`/seller/riders/${r.id}/remove`, 'POST', { reason: reason.trim() }, `${r.name} removed from your store.`) }}>Remove</button></td>
              </tr>
            ))}</tbody>
          </table></div>
        )}
      </div>
    </>
  )
}
