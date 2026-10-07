import { useCallback, useEffect, useRef, useState } from 'react'

// Admin → Secure access: sellers kept on an older commission rate (the admin
// changed the rate without applying it to existing sellers). Tick the ones to
// move onto their country's current rate.

const API_URL = import.meta.env.VITE_API_URL ?? 'http://127.0.0.1:8000/api'
const pct = (bps) => `${(bps / 100).toFixed(2)}%`

export function KeptRates({ headers, onMessage, onError }) {
  const [rows, setRows] = useState(null)
  const [picked, setPicked] = useState([])
  const [query, setQuery] = useState('')
  const onErrorRef = useRef(onError)
  useEffect(() => { onErrorRef.current = onError })

  const load = useCallback(async () => {
    try {
      const response = await fetch(`${API_URL}/admin/secure-access/kept-rates`, { headers: headers() })
      const data = await response.json().catch(() => ({}))
      if (!response.ok) throw new Error(data.message ?? 'Could not load sellers.')
      setRows(data.data ?? [])
      setPicked([])
    } catch (error) { onErrorRef.current(error) }
  }, [headers])

  useEffect(() => { Promise.resolve().then(load) }, [load])

  async function release(ids) {
    if (!ids.length || !window.confirm(`Move ${ids.length} seller${ids.length === 1 ? '' : 's'} to the current commission rate? It applies to their new orders.`)) return
    try {
      const response = await fetch(`${API_URL}/admin/secure-access/kept-rates/release`, { method: 'POST', headers: headers(), body: JSON.stringify({ shop_ids: ids }) })
      const data = await response.json().catch(() => ({}))
      if (!response.ok) throw new Error(data.message ?? 'Could not update sellers.')
      onMessage(`${data.data.moved} seller${data.data.moved === 1 ? '' : 's'} moved to the current commission rate.`)
      load()
    } catch (error) { onError(error) }
  }

  if (rows === null) return <p className="muted">Loading sellers on an older rate…</p>
  const q = query.trim().toLowerCase()
  const shown = rows.filter((r) => !q || r.name.toLowerCase().includes(q))
  const allShown = shown.length > 0 && shown.every((r) => picked.includes(r.id))
  return (
    <div className="admin-form">
      <h3>Sellers on an older commission rate</h3>
      {rows.length === 0 ? <p className="muted">Every seller pays the current rate. When you change the commission without applying it to existing sellers, they appear here so you can move chosen ones later.</p> : <>
        <p className="muted">These sellers kept the rate they had when you changed the commission. Tick the ones to move to the current rate.</p>
        <div className="admin-form-actions">
          <input type="search" placeholder="Search shop name" value={query} onChange={(event) => setQuery(event.target.value)} />
          <button className="act ghost" type="button" disabled={!shown.length} onClick={() => setPicked(allShown ? picked.filter((id) => !shown.some((r) => r.id === id)) : [...new Set([...picked, ...shown.map((r) => r.id)])])}>{allShown ? 'Clear selection' : `Select all${q ? ' shown' : ''} (${shown.length})`}</button>
        </div>
        <table className="admin-table">
          <thead><tr><th><input type="checkbox" aria-label="Select all" checked={allShown} onChange={() => setPicked(allShown ? picked.filter((id) => !shown.some((r) => r.id === id)) : [...new Set([...picked, ...shown.map((r) => r.id)])])} /></th><th>Shop</th><th>Country</th><th>Kept rate</th><th>Current rate</th></tr></thead>
          <tbody>
            {shown.map((r) => (
              <tr key={r.id}>
                <td><input type="checkbox" aria-label={`Select ${r.name}`} checked={picked.includes(r.id)} onChange={() => setPicked(picked.includes(r.id) ? picked.filter((id) => id !== r.id) : [...picked, r.id])} /></td>
                <td>{r.name}</td><td>{r.market}</td><td>{pct(r.kept_rate_bps)}</td><td>{pct(r.current_rate_bps)}</td>
              </tr>
            ))}
          </tbody>
        </table>
        <div className="admin-form-actions"><button className="act" type="button" disabled={!picked.length} onClick={() => release(picked)}>Move {picked.length || ''} selected to current rate</button></div>
      </>}
    </div>
  )
}
