import { Fragment, useCallback, useEffect, useRef, useState } from 'react'
import { currencySymbol, storeMoney } from './money'

// Admin → Secure access → Payouts: every seller with money to pay out (or a
// payout request) in one table — how they want to be paid, the withdrawal fee
// and what they receive — with Pay / Decline per row. Stripe payouts are sent
// by Stripe on Pay; bank and PayPal are sent by you first, then recorded here.

const API_URL = import.meta.env.VITE_API_URL ?? 'http://127.0.0.1:8000/api'
const money = (cents, currency) => storeMoney(cents ?? 0, currency)
const FILTERS = [['ready', 'Ready to pay'], ['requested', 'Requested'], ['all', 'All with a balance']]

export function SellerPayouts({ headers, onMessage, onError, onOpenSeller }) {
  const [rows, setRows] = useState(null)
  const [meta, setMeta] = useState({})
  const [filter, setFilter] = useState('ready')
  const [busy, setBusy] = useState(null)
  const onErrorRef = useRef(onError)
  useEffect(() => { onErrorRef.current = onError })

  const load = useCallback(async () => {
    try {
      const response = await fetch(`${API_URL}/admin/secure-access/payouts`, { headers: headers() })
      const data = await response.json().catch(() => ({}))
      if (!response.ok) throw new Error(data.message ?? 'Could not load payouts.')
      setRows(data.data ?? [])
      setMeta(data.meta ?? {})
    } catch (error) { onErrorRef.current(error) }
  }, [headers])

  useEffect(() => { Promise.resolve().then(load) }, [load])

  async function post(row, path, body) {
    setBusy(row.seller_id)
    try {
      const response = await fetch(`${API_URL}/admin/sellers/${row.seller_id}/${path}`, { method: 'POST', headers: headers(), body: JSON.stringify(body) })
      const data = await response.json().catch(() => ({}))
      if (!response.ok) throw new Error(data.message ?? Object.values(data.errors ?? {})[0]?.[0] ?? 'That did not work.')
      return true
    } catch (error) { onError(error); return false } finally { setBusy(null) }
  }

  async function pay(row) {
    const stripe = row.method === 'stripe'
    const amountStr = window.prompt(`Pay ${row.shop} (${row.currency.toUpperCase()}) — available ${money(row.available_cents, row.currency)}${row.max_cents > 0 ? `, max ${money(row.max_cents, row.currency)} per payout` : ''}:`, (row.amount_cents / 100).toFixed(2))
    if (!amountStr) return
    const amount_cents = Math.round(Number(String(amountStr).replace(/[^0-9.]/g, '')) * 100)
    if (!amount_cents || amount_cents <= 0) { onError(new Error('Enter a valid amount.')); return }
    const ask = stripe
      ? `Send ${money(amount_cents, row.currency)} (less the withdrawal fee) to ${row.shop}'s Stripe account now? The money leaves your Stripe balance.`
      : `Have you already sent the ${row.method_label.toLowerCase()} to ${row.shop}? This records ${money(amount_cents, row.currency)} as paid (less the withdrawal fee).`
    if (!window.confirm(ask)) return
    const note = stripe ? '' : (window.prompt('Note — e.g. the transfer reference (optional):', '') ?? '')
    if (await post(row, 'payout', { amount_cents, note: note.trim() || undefined })) {
      onMessage(stripe ? `Sent to ${row.shop} by Stripe.` : `Payout to ${row.shop} recorded.`)
      load()
    }
  }

  async function decline(row) {
    const note = window.prompt(`Why are you declining ${row.shop}'s payout request? The seller sees this.`, '')
    if (!note?.trim()) return
    if (await post(row, 'payout-request/reject', { note: note.trim() })) { onMessage('Payout request declined.'); load() }
  }

  if (rows === null) return <div className="admin-form"><p className="muted">Loading payouts…</p></div>
  const shown = rows.filter((r) => filter === 'all' || (filter === 'ready' ? r.status === 'ready' : r.request))
  const count = (key) => rows.filter((r) => key === 'all' || (key === 'ready' ? r.status === 'ready' : r.request)).length
  const totals = shown.filter((r) => r.status === 'ready').reduce((acc, r) => ({ ...acc, [r.currency]: (acc[r.currency] ?? 0) + r.net_cents }), {})

  return (
    <div className="admin-form admin-payouts">
      <h3>Seller payouts</h3>
      <p className="muted">Sellers with money past its return window, how they want to be paid and what they&rsquo;d receive after the withdrawal fee. <b>Stripe</b> sellers are paid by Stripe when you press Pay; for <b>bank</b> and <b>PayPal</b>, send the money first, then press Pay to record it. Switch country in the top bar.</p>
      <div className="admin-payouts-bar">
        {FILTERS.map(([key, label]) => <button key={key} type="button" className={filter === key ? 'act' : 'act ghost'} onClick={() => setFilter(key)}>{label} ({count(key)})</button>)}
        <button type="button" className="act ghost" onClick={load}>Refresh</button>
      </div>
      <p className="muted">
        {Object.entries(totals).map(([c, cents]) => <span key={c}>To pay: <b>{money(cents, c)}</b> · </span>)}
        {Object.entries(meta.daily_remaining ?? {}).filter(([, v]) => v != null).map(([m, v]) => <span key={m}>{m} left today: {money(v, rows.find((r) => r.market === m)?.currency)} · </span>)}
        {meta.stripe_balance && <span>Stripe balance: {Object.entries(meta.stripe_balance).map(([c, v]) => money(v, c)).join(', ') || '0'}</span>}
      </p>
      {shown.length === 0 ? <p className="muted">{filter === 'ready' ? 'No seller is ready to be paid right now.' : 'Nothing here.'}</p> : (
        <div className="admin-table-wrap">
          <table className="admin-table">
            <thead><tr><th>Seller</th><th>Paid by</th><th>Available</th><th>Requested</th><th>Pay now</th><th>Fee</th><th>They receive</th><th>Status</th><th /></tr></thead>
            <tbody>
              {shown.map((r) => (
                <tr key={r.seller_id}>
                  <td><button type="button" className="link" onClick={() => onOpenSeller?.(r.seller_id)}>{r.shop}</button><br /><small className="muted">{r.owner} · {r.market}</small></td>
                  <td>{r.method_label ?? <span className="muted">Not set</span>}{r.method_detail && <><br /><small className="muted">{r.method_detail}</small></>}</td>
                  <td>{money(r.available_cents, r.currency)}{r.pending_cents > 0 && <><br /><small className="muted">+{money(r.pending_cents, r.currency)} held</small></>}</td>
                  <td>{r.request ? <>{money(r.request.amount_cents, r.currency)}<br /><small className="muted">{new Date(r.request.created_at).toLocaleDateString()}</small></> : <span className="muted">—</span>}</td>
                  <td>{money(r.amount_cents, r.currency)}</td>
                  <td>{r.fee_cents > 0 ? `−${money(r.fee_cents, r.currency)}` : <span className="muted">Free</span>}</td>
                  <td><b>{money(r.net_cents, r.currency)}</b>{r.receive?.currency && r.receive.currency !== r.currency && <><br /><small className="muted">≈ {money(r.receive.cents, r.receive.currency)}</small></>}</td>
                  <td>{r.status === 'ready' ? <span className="pill pill-approved">Ready</span> : r.status === 'below_min' ? <span className="pill" title={`Minimum payout ${money(r.min_cents, r.currency)}`}>Below {money(r.min_cents, r.currency)}</span> : <small className="admin-payouts-blocked">{r.blocker}</small>}</td>
                  <td className="admin-payouts-actions">
                    <button type="button" className="act" disabled={busy === r.seller_id || r.status !== 'ready'} title={r.status === 'ready' ? '' : (r.blocker ?? 'Below the minimum payout')} onClick={() => pay(r)}>{r.method === 'stripe' ? 'Pay by Stripe' : 'Pay'}</button>
                    {r.request && <button type="button" className="act ghost" disabled={busy === r.seller_id} onClick={() => decline(r)}>Decline</button>}
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      )}
    </div>
  )
}

const METHOD_LABELS = { bank: 'Bank transfer', paypal: 'PayPal', stripe: 'Stripe' }
const METHOD_NOTES = {
  bank: 'You send the money from your bank, then record it.',
  paypal: 'You send it from your PayPal, then record it.',
  stripe: 'Sent automatically from your Stripe balance when you press Pay. Sellers set up a Stripe account on Stripe’s own pages first. Stripe’s own transfer cost is covered by the fee you set here.',
}

// Secure access → Withdrawal fees: per country, which payout methods sellers
// may choose and the fee taken from each payout (the seller pays it).
export function WithdrawalFees({ settings, market, marketName, currency, save, onMessage }) {
  const fees = settings.payout_fees?.[market] ?? {}
  const methods = Object.keys(METHOD_LABELS).filter((m) => fees[m])
  const [on, setOn] = useState(() => Object.fromEntries(methods.map((m) => [m, fees[m].enabled !== false])))
  const options = settings.payout_currency_options?.[market] ?? [currency]

  async function submit(event) {
    event.preventDefault()
    const fd = new FormData(event.currentTarget)
    const body = Object.fromEntries(methods.map((m) => [m, on[m]
      ? { fixed_cents: Math.round(Number(fd.get(`${m}_fixed`) || 0) * 100), min_cents: Math.round(Number(fd.get(`${m}_min`) || 0) * 100), bps: Math.round(Number(fd.get(`${m}_pct`) || 0) * 100), currency: fd.get(`${m}_currency`) || currency, currencies: m === 'stripe' ? [currency] : fd.getAll(`${m}_currencies`), enabled: true }
      : { ...fees[m], enabled: false }]))
    if (await save({ payout_fees: { [market]: body } })) onMessage('Withdrawal fees saved.')
  }

  return (
    <form className="admin-form" onSubmit={submit}>
      <h3>Withdrawal fees — {marketName} ({currency.toUpperCase()} {currencySymbol(currency)})</h3>
      <p className="muted">How sellers in {marketName} can be paid, and the fee taken from each payout — a fixed amount, a percentage, or both (0 = free). Sellers see it before they request a payout, and it shows in their ledger as &ldquo;Withdrawal fee&rdquo;. Switch country in the top bar.</p>
      {methods.map((m) => {
        const label = METHOD_LABELS[m]
        const blocked = m === 'stripe' && fees[m].unavailable && !on[m]
        return (
          <fieldset key={m} className="admin-fieldset">
            <label className={`admin-check${blocked ? ' is-off' : ''}`} title={blocked ? fees[m].unavailable : undefined}>
              <input type="checkbox" checked={on[m]} disabled={blocked} onChange={(e) => setOn({ ...on, [m]: e.target.checked })} /> <b>Offer {label} payouts to sellers in {marketName}</b>
            </label>
            {blocked ? <p className="muted">{fees[m].unavailable}</p> : <p className="muted">{METHOD_NOTES[m]}</p>}
            {on[m] && (
              <div className="admin-form-grid">
                {m === 'stripe'
                  ? <p className="muted wz-wide">Pays in {currency.toUpperCase()} — your Stripe account&rsquo;s currency.</p>
                  : <Fragment>
                    <label>{label} — pays sellers in<select name={`${m}_currency`} defaultValue={fees[m].currency ?? currency}>{[...new Set([currency, 'usd'])].map((c) => <option key={c} value={c}>{c.toUpperCase()} {currencySymbol(c)}</option>)}</select></label>
                    <div className="wz-wide"><span className="muted">Sellers can choose to be paid in:</span> {options.map((c) => <label key={c} className="admin-check" style={{ display: 'inline-flex', marginRight: 12 }}><input type="checkbox" name={`${m}_currencies`} value={c} defaultChecked={(fees[m].currencies ?? [fees[m].currency]).includes(c)} /> {c.toUpperCase()}</label>)}</div>
                  </Fragment>}
                <label>Fixed fee <small className="muted">in the currency it pays in</small><input name={`${m}_fixed`} type="number" min="0" step="0.01" defaultValue={(fees[m].fixed_cents / 100).toFixed(2)} /></label>
                <label>Percentage (%)<input name={`${m}_pct`} type="number" min="0" max="50" step="0.01" defaultValue={(fees[m].bps / 100).toFixed(2)} /></label>
                <label>Minimum payout ({currencySymbol(currency)}) <small className="muted">0 = the country&rsquo;s minimum; the higher one applies</small><input name={`${m}_min`} type="number" min="0" step="0.01" defaultValue={((fees[m].min_cents ?? 0) / 100).toFixed(2)} /></label>
              </div>
            )}
          </fieldset>
        )
      })}
      <div className="admin-form-actions"><button className="act" type="submit" disabled={!methods.some((m) => on[m])} title={methods.some((m) => on[m]) ? '' : 'Keep at least one payout method on'}>Save withdrawal fees</button></div>
    </form>
  )
}
