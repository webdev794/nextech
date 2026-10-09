import { useEffect, useState } from 'react'

// "Email me routine reminders" — riders, sellers and admins can turn off the
// frequent reminder emails (they're shown in the app anyway). Important emails
// (pay, cash, orders, decisions about you) always go.

const API_URL = import.meta.env.VITE_API_URL ?? 'http://127.0.0.1:8000/api'

export function EmailRoutineToggle({ headers, className = '' }) {
  const [on, setOn] = useState(null)
  useEffect(() => {
    let alive = true
    fetch(`${API_URL}/user`, { headers: headers() }).then((r) => r.json()).then((u) => { if (alive) setOn(u?.email_routine !== false) }).catch(() => {})
    return () => { alive = false }
  }, [headers])
  if (on === null) return null
  return (
    <label className={`email-routine ${className}`} title="Clock-in and break reminders, stores closed tomorrow, each cash collection, new orders to take, days off… Important emails (pay, cash to hand over, decisions about you) always go.">
      <input type="checkbox" checked={on} onChange={async (e) => {
        const next = e.target.checked
        setOn(next)
        try { await fetch(`${API_URL}/me/email-routine`, { method: 'PATCH', headers: { ...headers(), 'Content-Type': 'application/json' }, body: JSON.stringify({ on: next }) }) } catch { setOn(!next) }
      }} /> Email me routine reminders <small>(important emails always come)</small>
    </label>
  )
}
