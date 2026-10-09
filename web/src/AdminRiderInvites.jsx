import { useCallback, useEffect, useState } from 'react'
import { useLiveRefresh } from './useLiveRefresh'

// Admin → Riders: riders with no store right now who live near one of NexTech's
// own stores that started deliveries. Invite the ones you'd like; they join only
// if they accept in the Rider app (they're emailed).

const API_URL = import.meta.env.VITE_API_URL ?? 'http://127.0.0.1:8000/api'

export function AdminRiderInvites({ headers, onMessage }) {
  const [rows, setRows] = useState([])
  const load = useCallback(() => fetch(`${API_URL}/admin/rider-invites`, { headers: headers() }).then((r) => r.json()).then((d) => setRows(d.data ?? [])).catch(() => {}), [headers])
  useEffect(() => { Promise.resolve().then(load) }, [load])
  useLiveRefresh(load)

  async function decide(iv, invite) {
    const res = await fetch(`${API_URL}/admin/rider-invites/${iv.id}`, { method: 'POST', headers: { ...headers(), 'Content-Type': 'application/json' }, body: JSON.stringify({ invite }) })
    const d = await res.json().catch(() => ({}))
    if (res.ok) { setRows(d.data ?? []); onMessage?.(invite ? `${iv.name} is invited — they're emailed.` : 'Done.') } else onMessage?.(d.message ?? 'That failed.')
  }

  if (!rows.length) return null
  return (
    <div className="admin-form">
      <h3 className="admin-subhead">Riders near your stores who delivered before ({rows.length})</h3>
      <p className="muted">They have no store right now. Invite the ones you&rsquo;d like — they join only if they accept.</p>
      <table className="admin-table"><tbody>
        {rows.map((iv) => (
          <tr key={iv.id}>
            <td>{iv.name}{iv.phone ? <><br /><span className="muted">{iv.phone}</span></> : null}</td>
            <td>{iv.store}</td>
            <td>{iv.status === 'invited' ? <span className="muted">Invited — waiting for their answer</span> : <>
              <button type="button" className="act" onClick={() => decide(iv, true)}>Invite</button>{' '}
              <button type="button" className="act ghost" onClick={() => decide(iv, false)}>No thanks</button></>}</td>
          </tr>
        ))}
      </tbody></table>
    </div>
  )
}
