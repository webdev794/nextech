import { useState } from 'react'
import { brandName } from './useBranding'

// Admin → Support team: the names support replies are signed with in every chat
// ("Mak from {store} Support"), so admin is never shown taking a side.

export function AdminSupportTeam({ names, onSave }) {
  const [list, setList] = useState(() => (names?.length ? names : ['Mak', 'Terry', 'Priya', 'Sam', 'Alex']))
  const [added, setAdded] = useState('')
  const clean = (l) => [...new Set(l.map((n) => n.trim()).filter(Boolean))]

  return (
    <section className="admin-panel">
      <h3>Support team</h3>
      <p className="muted">Replies in buyer, seller and rider chats show as &ldquo;<b>{list[0] ?? 'Mak'} from {brandName()} Support</b>&rdquo; — never as admin. Each chat gets one of these names (you can pick one in the chat). Add or edit names here; later you can hire real support people.</p>
      <ul className="admin-support-names">
        {list.map((n, i) => (
          <li key={i}>
            <input value={n} maxLength={40} onChange={(e) => setList((l) => l.map((x, j) => (j === i ? e.target.value : x)))} />
            <button type="button" className="act ghost" disabled={list.length <= 1} onClick={() => setList((l) => l.filter((_, j) => j !== i))}>Remove</button>
          </li>
        ))}
      </ul>
      <div className="admin-form-actions">
        <input placeholder="New name" maxLength={40} value={added} onChange={(e) => setAdded(e.target.value)} />
        <button type="button" className="act ghost" disabled={!added.trim()} onClick={() => { setList((l) => clean([...l, added])); setAdded('') }}>Add</button>
        <button type="button" className="act" disabled={!clean(list).length} onClick={() => onSave(clean(list))}>Save names</button>
      </div>
    </section>
  )
}
