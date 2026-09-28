import { useState } from 'react'
import './TrackingTimeline.css'

// Live courier tracking for a seller-shipped package (backend LiveTracking /
// AfterShip): the latest status, where it is, the delivery estimate, and the
// courier's scans on demand. Renders nothing until the package is tracked.
export function TrackingTimeline({ pkg }) {
  const [open, setOpen] = useState(false)
  if (!pkg?.tracking_label && !(pkg?.tracking_events ?? []).length) return null
  const events = pkg.tracking_events ?? []
  const latest = events[0]
  const when = (at) => (at ? new Date(at).toLocaleString([], { dateStyle: 'medium', timeStyle: 'short' }) : '')
  const problem = ['Exception', 'AttemptFail', 'Expired'].includes(pkg.tracking_tag)
  return (
    <div className={`trk${problem ? ' problem' : ''}`}>
      <p className="trk-now">
        <b>{pkg.tracking_label ?? 'Tracking'}</b>
        {latest?.location ? ` · ${latest.location}` : ''}
        {latest?.at ? <span className="trk-time"> · {when(latest.at)}</span> : null}
      </p>
      {pkg.tracking_detail && pkg.tracking_detail !== pkg.tracking_label && <p className="trk-detail">{pkg.tracking_detail}</p>}
      {pkg.tracking_eta && pkg.status !== 'delivered' && <p className="trk-detail">Expected by {new Date(pkg.tracking_eta).toLocaleDateString([], { weekday: 'short', month: 'short', day: 'numeric' })}</p>}
      {events.length > 1 && (
        <>
          <button type="button" className="trk-toggle" onClick={() => setOpen((v) => !v)}>{open ? 'Hide courier updates' : `Show all ${events.length} courier updates`}</button>
          {open && (
            <ol className="trk-list">
              {events.map((e, i) => (
                <li key={`${e.at}-${i}`}>
                  <span>{e.message || e.tag}</span>
                  <small>{[e.location, when(e.at)].filter(Boolean).join(' · ')}</small>
                </li>
              ))}
            </ol>
          )}
        </>
      )}
    </div>
  )
}
