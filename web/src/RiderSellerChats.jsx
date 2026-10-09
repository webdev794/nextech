import { useCallback, useEffect, useState } from 'react'
import { brandName } from './useBranding'
import { onLiveChange } from './useLiveRefresh'

// Rider app: chat with each seller you deliver for. If you can't settle something,
// open a support ticket: support reads the whole chat and replies within 1–2 working days.

const API_URL = import.meta.env.VITE_API_URL ?? 'http://127.0.0.1:8000/api'

export function RiderSellerChats({ headers }) {
  const [chats, setChats] = useState(null)
  const [open, setOpen] = useState(null)
  const [text, setText] = useState('')
  const load = useCallback(() => fetch(`${API_URL}/rider/seller-chats`, { headers: headers() }).then((r) => r.json()).then((d) => setChats(d.data ?? [])).catch(() => {}), [headers])
  useEffect(() => {
    if (open === null) return undefined
    const off = onLiveChange(load)
    const t = setInterval(load, 10000) // an open chat stays quick
    return () => { clearInterval(t); off() }
  }, [open, load])

  if (chats === null) return <button type="button" className="rider-btn ghost" onClick={load}>Chat with your sellers</button>
  if (!chats.length) return null
  const chat = chats.find((c) => c.store_id === open)

  async function send() {
    if (!text.trim()) return
    const r = await fetch(`${API_URL}/rider/seller-chats/${chat.store_id}`, { method: 'POST', headers: headers(true), body: JSON.stringify({ body: text.trim() }) })
    const d = await r.json().catch(() => ({}))
    if (r.ok) { setChats(d.data ?? []); setText('') } else window.alert(d.message ?? 'Could not send.')
  }

  async function ticket(action) {
    if (action === 'open' && !window.confirm('Open a support ticket? Our support team will read the whole chat and reply within 1–2 working days.')) return
    const r = await fetch(`${API_URL}/rider/seller-chats/thread/${chat.thread_id}/ticket`, { method: 'POST', headers: headers(true), body: JSON.stringify({ action }) })
    const d = await r.json().catch(() => ({}))
    if (r.ok) setChats(d.data ?? []); else window.alert(d.message ?? 'That failed.')
  }

  return (
    <section className="rider-section rider-seller-chats">
      <h3>Chat with your sellers</h3>
      {!chat ? chats.map((c) => (
        <button key={c.store_id} type="button" className="rider-btn ghost" onClick={() => setOpen(c.store_id)}>{c.shop}{c.messages.length ? ` (${c.messages.length})` : ''}</button>
      )) : (
        <div className="rider-chat">
          <button type="button" className="rider-btn ghost" onClick={() => setOpen(null)}>&larr; All sellers</button>
          <strong>{chat.shop}</strong>
          <ul className="rider-chat-list">{chat.messages.map((m) => <li key={m.id} className={`from-${m.from}`}>{m.from === 'system' ? <i>{m.body}</i> : <><b>{m.from === 'me' ? 'You' : m.from === 'seller' ? chat.shop : `${m.agent ? `${m.agent} from ` : ''}${brandName()} Support`}</b>: {m.body}</>}</li>)}</ul>
          <textarea rows="2" value={text} placeholder={`Message ${chat.shop}…`} onChange={(e) => setText(e.target.value)} />
          <button type="button" className="rider-btn primary" onClick={send}>Send</button>
          {chat.thread_id && (chat.ticket_status === 'open'
            ? <p className="rider-card-note">Support ticket open — support will reply within 1–2 working days.{chat.ticket_by === 'rider' && <> <button type="button" className="rider-btn ghost" onClick={() => ticket('close')}>Close ticket</button></>}</p>
            : <button type="button" className="rider-btn ghost" onClick={() => ticket('open')}>Open a support ticket</button>)}
        </div>
      )}
    </section>
  )
}
