import { useState } from 'react'

// Rider app: days off — how many the store allows a month, how many are used,
// and asking for one (at least a day ahead; the store is told).

const API_URL = import.meta.env.VITE_API_URL ?? 'http://127.0.0.1:8000/api'
const day = (d) => new Date(`${String(d).slice(0, 10)}T00:00:00`).toLocaleDateString([], { weekday: 'short', day: 'numeric', month: 'short' })

export function RiderLeavePanel({ leave, headers, onChange }) {
  const [form, setForm] = useState(null)
  const [msg, setMsg] = useState('')
  if (!leave) return null
  const today = new Date().toISOString().slice(0, 10)

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
        Ask at least <b>one day ahead</b> — your store is told. Missing a working day without asking may affect that day&rsquo;s pay or your work agreement.
      </p>
      {(leave.list ?? []).length > 0 && <ul>{leave.list.map((l) => (
        <li key={l.id}>{day(l.date)} — {l.kind === 'absent' ? 'missed without asking' : l.told_ahead ? 'day off' : 'day off (asked on the day)'}{l.reason ? ` · ${l.reason}` : ''}
          {l.kind === 'leave' && String(l.date).slice(0, 10) > today && <button type="button" className="rider-btn ghost" onClick={() => call(`/rider/leave/${l.id}`, 'DELETE')}>Take back</button>}</li>
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
