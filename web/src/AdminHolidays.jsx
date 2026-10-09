import { useCallback, useEffect, useState } from 'react'
import { useLiveRefresh } from './useLiveRefresh'

// Shipping → Holidays: the country's built-in holidays, ones admin adds (for every
// seller in the country, or one store's day off), and sellers' day-off requests.

const API_URL = import.meta.env.VITE_API_URL ?? 'http://127.0.0.1:8000/api'

const day = (d) => new Date(`${d}T00:00:00`).toLocaleDateString([], { weekday: 'short', day: 'numeric', month: 'short', year: 'numeric' })

export function AdminHolidays({ headers, markets, defaultMarket, onMessage }) {
  const [market, setMarket] = useState(defaultMarket && defaultMarket !== 'ALL' ? defaultMarket : (markets[0]?.code ?? 'US'))
  const [data, setData] = useState(null)
  const [form, setForm] = useState({ date: '', name: '', shop_id: '' })
  const [error, setError] = useState('')

  const call = useCallback(async (path, method = 'GET', body) => {
    setError('')
    try {
      const res = await fetch(`${API_URL}${path}`, { method, headers: headers(), body: body ? JSON.stringify(body) : undefined })
      const json = await res.json().catch(() => ({}))
      if (!res.ok) throw new Error(json.message ?? 'That failed.')
      setData(json.data)
      return true
    } catch (e) { setError(e.message); return false }
  }, [headers])

  useEffect(() => { Promise.resolve().then(() => call(`/admin/holidays?market=${market}`)) }, [call, market])
  useLiveRefresh(useCallback(() => call(`/admin/holidays?market=${market}`), [call, market])) // new requests appear by themselves

  async function add(event) {
    event.preventDefault()
    if (await call('/admin/holidays', 'POST', { market, date: form.date, name: form.name.trim(), shop_id: form.shop_id ? Number(form.shop_id) : null })) {
      setForm({ date: '', name: '', shop_id: '' })
      onMessage?.('Holiday added.')
    }
  }

  async function decide(r, decision) {
    let note = null
    if (decision === 'decline') {
      note = window.prompt(`Why not? ${r.shop ?? 'The seller'} is told.`, '')
      if (note === null) return
    }
    if (await call(`/admin/holiday-requests/${r.id}`, 'POST', { decision, note })) onMessage?.(decision === 'decline' ? 'Declined — the seller is told.' : 'Added — the seller is told.')
  }

  const stores = [...new Map((data?.requests ?? []).concat(data?.extras ?? []).filter((x) => x.shop_id).map((x) => [x.shop_id, x.shop])).entries()]

  return (
    <div className="admin-form">
      <h3>Holidays</h3>
      <p className="muted">Holidays count in sellers&rsquo; delivery dates. A holiday for the <b>whole country</b> shows in every seller&rsquo;s Holiday settings (off unless they tick that they work). A <b>store day off</b> is always off for that store — delivery dates skip it, buyers are told, and its riders see it in their timetable.</p>
      {error && <p className="admin-error">{error}</p>}
      {markets.length > 1 && (
        <label>Country
          <select value={market} onChange={(e) => setMarket(e.target.value)}>{markets.map((m) => <option key={m.code} value={m.code}>{m.name}</option>)}</select>
        </label>
      )}

      {(data?.requests ?? []).length > 0 && <>
        <h4>Day-off requests from sellers</h4>
        <table className="admin-table"><tbody>
          {data.requests.map((r) => (
            <tr key={r.id}>
              <td><b>{r.shop ?? `Shop #${r.shop_id}`}</b><br /><small className="muted">asked {new Date(r.at).toLocaleDateString()}</small></td>
              <td>{day(r.date)} — {r.name}{r.reason ? <><br /><small className="muted">&ldquo;{r.reason}&rdquo;</small></> : null}</td>
              <td>
                <button type="button" className="act" onClick={() => decide(r, 'store')}>Add for this store</button>{' '}
                <button type="button" className="act ghost" onClick={() => decide(r, 'country')}>Add for the whole country</button>{' '}
                <button type="button" className="act ghost" onClick={() => decide(r, 'decline')}>Decline</button>
              </td>
            </tr>
          ))}
        </tbody></table>
      </>}

      <h4>Added holidays</h4>
      {(data?.extras ?? []).length === 0 ? <p className="muted">None yet.</p> : (
        <table className="admin-table"><tbody>
          {data.extras.map((h) => (
            <tr key={h.id}>
              <td>{day(h.date)}</td><td>{h.name}</td><td>{h.shop_id ? `Store day off — ${h.shop ?? `shop #${h.shop_id}`}` : 'Whole country'}</td>
              <td><button type="button" className="act ghost" onClick={() => window.confirm(`Remove ${h.name}?`) && call(`/admin/holidays/${h.id}?market=${market}`, 'DELETE')}>Remove</button></td>
            </tr>
          ))}
        </tbody></table>
      )}

      <form onSubmit={add} className="admin-inline-form">
        <label>Date<input type="date" required value={form.date} onChange={(e) => setForm({ ...form, date: e.target.value })} /></label>
        <label>Name<input required maxLength={60} placeholder="e.g. Bhai Dooj" value={form.name} onChange={(e) => setForm({ ...form, name: e.target.value })} /></label>
        <label>For
          <select value={form.shop_id} onChange={(e) => setForm({ ...form, shop_id: e.target.value })}>
            <option value="">The whole country</option>
            {stores.map(([id, name]) => <option key={id} value={id}>Store: {name ?? `#${id}`}</option>)}
          </select>
        </label>
        <button type="submit" className="act">Add holiday</button>
      </form>

      <h4>Built-in holidays</h4>
      <p className="muted">{(data?.built_in ?? []).map((h) => `${h.name} (${day(h.date)})`).join(' · ') || '—'}</p>
    </div>
  )
}
