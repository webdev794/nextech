import { useCallback, useEffect, useState } from 'react'

// Riders' money for a month: deliveries, delivery fees buyers paid, what the
// rider earned (and the difference), cash collected and still held. Admin sees
// every rider (with their balance); a seller sees their own store's figures.

const API_URL = import.meta.env.VITE_API_URL ?? 'http://127.0.0.1:8000/api'

export function RiderMoneyTable({ headers, path, currency, admin = false, className = 'admin-table', csvPath = null }) {
  const [month, setMonth] = useState(() => new Date().toISOString().slice(0, 7))
  const [rows, setRows] = useState(null)
  const [error, setError] = useState('')
  const load = useCallback(() => {
    fetch(`${API_URL}${path}?month=${month}`, { headers: headers() })
      .then(async (r) => { const d = await r.json().catch(() => ({})); if (!r.ok) throw new Error(d.message ?? 'Could not load.'); setRows(d.data ?? []); setError('') })
      .catch((e) => setError(e.message))
  }, [headers, path, month])
  useEffect(() => { Promise.resolve().then(load) }, [load])
  const money = (c) => new Intl.NumberFormat(undefined, { style: 'currency', currency: String(currency ?? 'usd').toUpperCase() }).format((c ?? 0) / 100)

  return (
    <div className="rider-money">
      <label className="rider-money-month">Month <input type="month" value={month} max={new Date().toISOString().slice(0, 7)} onChange={(e) => setMonth(e.target.value || month)} /></label>
      {csvPath && <button type="button" className="sc-link" onClick={async () => {
        try {
          const res = await fetch(`${API_URL}${csvPath}?month=${month}`, { headers: headers() })
          if (!res.ok) throw new Error('Could not download.')
          const url = URL.createObjectURL(await res.blob())
          const a = document.createElement('a')
          a.href = url; a.download = `rider-deliveries-${month}.csv`; a.click()
          setTimeout(() => URL.revokeObjectURL(url), 1000)
        } catch (e) { setError(e.message) }
      }}>Download deliveries (CSV)</button>}
      {error && <p className="muted">{error}</p>}
      {rows === null ? <p className="muted">Loading…</p> : rows.length === 0 ? <p className="muted">No riders yet.</p> : (
        <div className="admin-table-wrap"><table className={className}>
          <thead><tr><th>Rider</th><th>Deliveries</th><th>Fees buyers paid</th><th>Rider earned</th><th title="Fees minus pay: positive = deliveries paid for themselves">Difference</th><th>Cash collected</th><th>Cash held now</th>{admin && <th>Balance owed</th>}</tr></thead>
          <tbody>{rows.map((r) => (
            <tr key={r.rider_id}>
              <td>{r.name}{!r.active && <small> (not active)</small>}{r.leaving_on && <><br /><small>leaving {new Date(r.leaving_on).toLocaleDateString()}</small></>}</td>
              <td>{r.deliveries}</td>
              <td>{money(r.fees_from_buyers_cents)}</td>
              <td>{money(r.earned_cents)}</td>
              <td className={r.margin_cents < 0 ? 'money-neg' : ''}>{money(r.margin_cents)}</td>
              <td>{money(r.cash_collected_cents)}</td>
              <td className={r.cash_held_cents > 0 ? 'money-neg' : ''}>{money(r.cash_held_cents)}</td>
              {admin && <td>{money(r.balance_cents)}</td>}
            </tr>
          ))}</tbody>
        </table></div>
      )}
      <p className="muted">&ldquo;Difference&rdquo; shows whether a rider&rsquo;s deliveries paid for themselves (delivery fees buyers paid minus the rider&rsquo;s pay) — useful for deciding between pay per delivery and a fixed pay. Cash still held at month end is taken from the rider&rsquo;s earnings and given to the seller.</p>
    </div>
  )
}
