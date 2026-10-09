import { useState } from 'react'

// The hours a store's riders must be on duty: days + start / end time.
// Used by the seller (Local delivery & riders) and admin (Stores, own stores).

import { DAYS } from './riderHours'

export function RiderHoursEditor({ value, onSave, saveClass = 'sc-primary', linkClass = 'sc-link' }) {
  const [h, setH] = useState(() => value ?? { days: [1, 2, 3, 4, 5, 6], start: '10:00', end: '19:00' })
  const toggle = (d) => setH((cur) => ({ ...cur, days: cur.days.includes(d) ? cur.days.filter((x) => x !== d) : [...cur.days, d].sort() }))
  return (
    <div className="rider-hours-edit">
      <div className="rider-hours-days">
        {DAYS.map(([d, label]) => <label key={d}><input type="checkbox" checked={h.days.includes(d)} onChange={() => toggle(d)} /> {label}</label>)}
      </div>
      <label>From <input type="time" value={h.start} onChange={(e) => setH({ ...h, start: e.target.value })} /></label>
      <label>To <input type="time" value={h.end} onChange={(e) => setH({ ...h, end: e.target.value })} /></label>
      <button type="button" className={saveClass} disabled={!h.days.length || !h.start || !h.end || h.end <= h.start} onClick={() => onSave(h)}>Save hours</button>
      {value && <button type="button" className={linkClass} onClick={() => onSave(null)}>No set hours</button>}
    </div>
  )
}

