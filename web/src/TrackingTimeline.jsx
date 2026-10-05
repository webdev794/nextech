import { useState } from 'react'
import './TrackingTimeline.css'

// The steps of a seller-shipped package as the seller (or live courier
// tracking) updates them: packed, picked up by courier, in transit, out for
// delivery, delivered — plus "cash collected" for cash on delivery.
const PROGRESS_STEPS = [['packed', 'Packed'], ['shipped', 'Picked up'], ['in_transit', 'In transit'], ['out_for_delivery', 'Out for delivery'], ['delivered', 'Delivered']]
export function PackageProgress({ pkg, packedAt = null, cod = false }) {
  const history = pkg?.status_history ?? []
  const at = (status) => history.find((h) => h.status === status)?.at ?? null
  const order = PROGRESS_STEPS.map(([key]) => key)
  const reached = pkg ? Math.max(order.indexOf(pkg.status), 1) : (packedAt ? 0 : -1)
  const steps = [...PROGRESS_STEPS, ...(cod ? [['cash_collected', 'Cash collected']] : [])]
  if (pkg && ['returned', 'lost'].includes(pkg.status)) return <p className="pp-off">{pkg.status === 'lost' ? 'Package lost' : 'Package returned'}</p>
  const when = (d) => (d ? new Date(d).toLocaleDateString([], { month: 'short', day: 'numeric' }) + ' ' + new Date(d).toLocaleTimeString([], { hour: 'numeric', minute: '2-digit' }) : '')
  return (
    <ol className="pp" aria-label="Delivery progress">
      {steps.map(([key, label], i) => {
        const done = key === 'cash_collected' ? !!pkg?.cash_collected_at : i <= reached
        const time = key === 'packed' ? packedAt : key === 'cash_collected' ? pkg?.cash_collected_at : key === 'shipped' ? (at('shipped') ?? pkg?.shipped_at) : key === 'delivered' ? (at('delivered') ?? pkg?.delivered_at) : at(key)
        return <li key={key} className={done ? 'done' : ''}><span className="pp-dot" aria-hidden /><b>{label}</b>{done && time && <small>{when(time)}</small>}</li>
      })}
    </ol>
  )
}

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
