import { useCallback, useEffect, useState } from 'react'

const API_URL = import.meta.env.VITE_API_URL ?? 'http://127.0.0.1:8000/api'
const money = (cents) => `$${((cents ?? 0) / 100).toFixed(2)}`
const EMPTY_METHOD = { payout_method: 'bank', holder_name: '', bank_name: '', account_number: '', routing_number: '', email: '' }

async function readJson(response) {
  const text = await response.text()
  try { return text ? JSON.parse(text) : {} } catch { return {} }
}

/**
 * The rider's pay: what they've earned (base + per-mile per delivery), what
 * they're owed after any COD cash they still hold, payout history, their
 * payout method, and a "Request payout" button once the minimum is reached.
 */
export default function RiderEarnings({ headers, refreshKey }) {
  const [pay, setPay] = useState(null)
  const [open, setOpen] = useState(false)
  const [methodForm, setMethodForm] = useState(null)
  const [busy, setBusy] = useState(false)
  const [message, setMessage] = useState('')

  const load = useCallback(async () => {
    try {
      const res = await fetch(`${API_URL}/rider/earnings`, { headers: headers() })
      const body = await readJson(res)
      if (res.ok) setPay(body.data)
    } catch { /* keep last */ }
  }, [headers])

  useEffect(() => { Promise.resolve().then(load) }, [load, refreshKey])

  async function post(path, method, payload) {
    setBusy(true)
    setMessage('')
    try {
      const res = await fetch(`${API_URL}/rider/${path}`, { method, headers: headers(true), body: payload ? JSON.stringify(payload) : undefined })
      const body = await readJson(res)
      if (!res.ok) throw new Error(body.message ?? Object.values(body.errors ?? {})[0]?.[0] ?? 'Something went wrong.')
      setPay(body.data)
      return true
    } catch (error) { setMessage(error.message); return false } finally { setBusy(false) }
  }

  async function saveMethod(event) {
    event.preventDefault()
    const f = methodForm
    const payload = f.payout_method === 'bank'
      ? { payout_method: 'bank', holder_name: f.holder_name.trim(), bank_name: f.bank_name.trim(), account_number: f.account_number.trim(), routing_number: f.routing_number.trim() }
      : { payout_method: 'paypal', email: f.email.trim() }
    if (await post('payout-method', 'PATCH', payload)) setMethodForm(null)
  }

  if (!pay) return null

  const req = pay.last_payout_request
  const canRequest = pay.owed_cents >= pay.min_payout_cents && pay.owed_cents > 0 && req?.status !== 'pending'

  return (
    <section className="rider-section rider-earnings">
      <button type="button" className="rider-earnings-head" aria-expanded={open} onClick={() => setOpen((v) => !v)}>
        <span>
          <small>You&rsquo;re owed</small>
          <strong>{money(pay.owed_cents)}</strong>
        </span>
        <span>
          <small>This week</small>
          <strong>{money(pay.earned_week_cents)}</strong>
        </span>
        <em>{open ? 'Hide' : 'Earnings'} ▾</em>
      </button>

      {open && (
        <div className="rider-earnings-body">
          <p className="rider-earnings-note">You earn {money(pay.rates.base_cents)} per delivery + {money(pay.rates.per_mile_cents)} per mile from the store to the customer.</p>
          <ul className="rider-earnings-sums">
            <li><span>Total earned, not yet paid</span><b>{money(pay.balance_cents)}</b></li>
            {pay.cash_holding_cents > 0 && <li className="neg"><span>Cash you&rsquo;re holding (return to store)</span><b>−{money(pay.cash_holding_cents)}</b></li>}
            <li className="total"><span>Owed to you</span><b>{money(pay.owed_cents)}</b></li>
          </ul>

          {req?.status === 'pending' && <p className="rider-earnings-status pending">Payout of <b>{money(req.amount_cents)}</b> requested {new Date(req.created_at).toLocaleDateString()} — it&rsquo;ll be sent to your payout method.</p>}
          {req?.status === 'rejected' && <p className="rider-earnings-status rejected">Your last payout request wasn&rsquo;t approved{req.admin_note ? `: ${req.admin_note}` : '.'}</p>}

          {req?.status !== 'pending' && (
            canRequest
              ? <button type="button" className="rider-earnings-btn" disabled={busy || !pay.payout_method} onClick={() => post('payout-requests', 'POST')}>Request payout of {money(Math.min(pay.owed_cents, pay.max_payout_cents || pay.owed_cents))}</button>
              : <p className="rider-earnings-note">You can request a payout once you&rsquo;re owed {money(pay.min_payout_cents)}.{pay.cash_holding_cents > 0 ? ' Returning cash to your store counts towards it.' : ''}</p>
          )}
          {!pay.payout_method && <p className="rider-earnings-note">Add your payout method below to get paid.</p>}

          {methodForm ? (
            <form className="rider-earnings-method" onSubmit={saveMethod}>
              <div className="rider-earnings-radios">
                <label><input type="radio" checked={methodForm.payout_method === 'bank'} onChange={() => setMethodForm({ ...methodForm, payout_method: 'bank' })} /> Bank account</label>
                <label><input type="radio" checked={methodForm.payout_method === 'paypal'} onChange={() => setMethodForm({ ...methodForm, payout_method: 'paypal' })} /> PayPal</label>
              </div>
              {methodForm.payout_method === 'bank' ? (
                <>
                  <input required placeholder="Account holder name" value={methodForm.holder_name} onChange={(e) => setMethodForm({ ...methodForm, holder_name: e.target.value })} />
                  <input required placeholder="Bank name" value={methodForm.bank_name} onChange={(e) => setMethodForm({ ...methodForm, bank_name: e.target.value })} />
                  <input required placeholder="Account number" value={methodForm.account_number} onChange={(e) => setMethodForm({ ...methodForm, account_number: e.target.value })} />
                  <input required placeholder="Routing number" value={methodForm.routing_number} onChange={(e) => setMethodForm({ ...methodForm, routing_number: e.target.value })} />
                </>
              ) : (
                <input required type="email" placeholder="PayPal email" value={methodForm.email} onChange={(e) => setMethodForm({ ...methodForm, email: e.target.value })} />
              )}
              <div className="rider-earnings-radios">
                <button type="submit" className="rider-earnings-btn" disabled={busy}>Save</button>
                <button type="button" className="rider-earnings-btn ghost" onClick={() => setMethodForm(null)}>Cancel</button>
              </div>
            </form>
          ) : (
            <p className="rider-earnings-note">
              Payout method: {pay.payout_method === 'bank' ? `Bank — ${pay.payout_details?.bank_name ?? ''} ····${String(pay.payout_details?.account_number ?? '').slice(-4)}` : pay.payout_method === 'paypal' ? `PayPal — ${pay.payout_details?.email}` : 'none yet'}
              {' '}<button type="button" className="rider-link-inline" onClick={() => setMethodForm({ ...EMPTY_METHOD, ...(pay.payout_details ?? {}), payout_method: pay.payout_method ?? 'bank' })}>{pay.payout_method ? 'Change' : 'Add'}</button>
            </p>
          )}

          {message && <p className="rider-error">{message}</p>}

          <h3>History</h3>
          {pay.entries.length === 0 ? <p className="rider-empty">No earnings yet — complete a delivery to get started.</p> : (
            <ul className="rider-earnings-list">
              {pay.entries.map((e) => (
                <li key={e.id}>
                  <span>
                    {e.type === 'payout_debit' ? 'Payout' : `Delivery #${e.order_id ?? '—'}`}
                    {e.distance_miles != null ? ` · ${e.distance_miles} mi` : ''}
                    {e.note ? ` — ${e.note}` : ''}
                    <small>{new Date(e.created_at).toLocaleDateString()}</small>
                  </span>
                  <b className={e.amount_cents >= 0 ? 'pos' : 'neg'}>{e.amount_cents >= 0 ? '+' : '−'}{money(Math.abs(e.amount_cents))}</b>
                </li>
              ))}
            </ul>
          )}
        </div>
      )}
    </section>
  )
}
