import { useState } from 'react'

// Rider app: days off — how many the store allows a month, how many are used,
// and asking for one (at least a day ahead; the store is told).

const API_URL = import.meta.env.VITE_API_URL ?? 'http://127.0.0.1:8000/api'
const day = (d) => new Date(`${String(d).slice(0, 10)}T00:00:00`).toLocaleDateString([], { weekday: 'short', day: 'numeric', month: 'short' })

export function RiderLeavePanel({ leave, headers, onChange }) {
  const [form, setForm] = useState(null)
  const [move, setMove] = useState(null) // { id, date, reason }
  const [msg, setMsg] = useState('')
  if (!leave) return null
  const today = new Date().toISOString().slice(0, 10)
  const tomorrowDate = new Date(`${today}T00:00:00Z`)
  tomorrowDate.setUTCDate(tomorrowDate.getUTCDate() + 1)
  const tomorrow = tomorrowDate.toISOString().slice(0, 10)

  async function call(path, method, body) {
    setMsg('')
    try {
      const res = await fetch(`${API_URL}${path}`, { method, headers: headers(true), body: body ? JSON.stringify(body) : undefined })
      const data = await res.json().catch(() => ({}))
      if (!res.ok) throw new Error(data.message ?? 'That failed.')
      setMsg(data.message ?? ''); setForm(null); onChange()
    } catch (e) { setMsg(e.message) }
  }

  return (
    <section className="rider-section rider-leave">
      <h3>Days off</h3>
      <p className="rider-card-note">
        {leave.allowed !== null ? <>This month: <b>{leave.used} of {leave.allowed}</b> days off used. </> : null}
        Ask at least <b>one day ahead</b> — your store accepts or declines it, and you&rsquo;re told. Once accepted, you can ask to change the date (your store confirms). Missing a working day without asking may affect that day&rsquo;s pay or your work agreement.
      </p>
      {(leave.list ?? []).length > 0 && <ul>{leave.list.map((l) => (
        <li key={l.id}>{day(l.date)} — {l.kind === 'absent' ? 'missed without asking' : l.status === 'requested' ? 'day off — waiting for the store' : 'day off — accepted'}{l.reason ? ` · ${l.reason}` : ''}
          {l.new_date && <> · <b>asked to move it to {day(l.new_date)}</b> — waiting for the store</>}
          {/* Not answered yet: take it back. Accepted and still ahead: ask to move it (the store confirms). */}
          {l.kind === 'leave' && l.status === 'requested' && <button type="button" className="rider-btn ghost" onClick={() => call(`/rider/leave/${l.id}`, 'DELETE')}>Take back</button>}
          {l.kind === 'leave' && l.status === 'approved' && !l.new_date && String(l.date).slice(0, 10) > today && move?.id !== l.id && <button type="button" className="rider-btn ghost" onClick={() => setMove({ id: l.id, date: '', reason: '' })}>Change date</button>}
          {move?.id === l.id && <div className="rider-leave-form">
            <label>New date <input type="date" min={tomorrow} value={move.date} onChange={(e) => setMove({ ...move, date: e.target.value })} /></label>
            <label>Reason <input maxLength={300} value={move.reason} onChange={(e) => setMove({ ...move, reason: e.target.value })} /></label>
            <button type="button" className="rider-btn primary" disabled={!move.date} onClick={async () => { await call(`/rider/leave/${l.id}/move`, 'POST', { date: move.date, reason: move.reason.trim() || null }); setMove(null) }}>Ask to move it</button>
            <button type="button" className="rider-btn ghost" onClick={() => setMove(null)}>Cancel</button>
          </div>}</li>
      ))}</ul>}
      {form ? (
        <div className="rider-leave-form">
          <label>Date <input type="date" min={today} value={form.date} onChange={(e) => setForm({ ...form, date: e.target.value })} /></label>
          <label>Reason <input maxLength={300} value={form.reason} onChange={(e) => setForm({ ...form, reason: e.target.value })} /></label>
          {form.date === today && <p className="rider-error">That&rsquo;s today — days off should be asked for a day ahead; this may affect today&rsquo;s pay.</p>}
          <button type="button" className="rider-btn primary" disabled={!form.date} onClick={() => call('/rider/leave', 'POST', { date: form.date, reason: form.reason.trim() || null })}>Ask for this day off</button>
          <button type="button" className="rider-btn ghost" onClick={() => setForm(null)}>Cancel</button>
        </div>
      ) : <button type="button" className="rider-btn" onClick={() => setForm({ date: '', reason: '' })}>Ask for a day off</button>}
      {msg && <p className="rider-card-note">{msg}</p>}
    </section>
  )
}
