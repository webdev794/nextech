import { useState } from 'react'
import { brandName } from './useBranding'

// Rider app → Documents: a short joining letter for each store I work for —
// joined on this date, at this pay. (The full terms are shown once, when joining.)

const API_URL = import.meta.env.VITE_API_URL ?? 'http://127.0.0.1:8000/api'
const day = (d) => new Date(`${d}T00:00:00`).toLocaleDateString([], { day: 'numeric', month: 'long', year: 'numeric' })

export function RiderLetters({ headers }) {
  const [letters, setLetters] = useState(null)

  async function open() {
    try {
      const res = await fetch(`${API_URL}/rider/letters`, { headers: headers() })
      const body = await res.json().catch(() => ({}))
      setLetters(body.data ?? [])
    } catch { setLetters([]) }
  }

  if (letters === null) return <button type="button" className="rider-btn ghost" onClick={open}>Documents — joining letter</button>
  return (
    <section className="rider-section">
      <h3>Documents</h3>
      {letters.length === 0 ? <p className="rider-card-note">No joining letter yet.</p> : letters.map((l) => (
        <article key={l.store} className="rider-card rider-letter">
          <strong>Joining letter — {l.store}</strong>
          <p>This confirms that <b>{l.name}</b> joined <b>{l.store}</b> as a delivery rider on <b>{day(l.joined_on)}</b>.</p>
          <p>Pay: <b>{l.pay}</b>, paid through {brandName()}.{l.hours ? <> Working hours: {l.hours}.</> : null}</p>
        </article>
      ))}
      <button type="button" className="rider-btn ghost" onClick={() => setLetters(null)}>Close</button>
    </section>
  )
}
