import { useCallback, useEffect, useState } from 'react'
import { openChat } from './chatDockUtils'

// Rider app → My account: the sellers you deliver for. Chats open in the chat
// button at the bottom right; here you can open a support ticket if you can't
// settle something (support reads the whole chat, replies within 1–2 working days).

const API_URL = import.meta.env.VITE_API_URL ?? 'http://127.0.0.1:8000/api'

export function RiderSellerChats({ headers }) {
  const [chats, setChats] = useState(null)
  const load = useCallback(() => fetch(`${API_URL}/rider/seller-chats`, { headers: headers() }).then((r) => r.json()).then((d) => setChats(d.data ?? [])).catch(() => setChats([])), [headers])
  useEffect(() => { Promise.resolve().then(load) }, [load])

  async function ticket(c, action) {
    if (action === 'open' && !window.confirm('Open a support ticket? Our support team will read the whole chat and reply within 1–2 working days.')) return
    const r = await fetch(`${API_URL}/rider/seller-chats/thread/${c.thread_id}/ticket`, { method: 'POST', headers: headers(true), body: JSON.stringify({ action }) })
    const d = await r.json().catch(() => ({}))
    if (r.ok) setChats(d.data ?? []); else window.alert(d.message ?? 'That failed.')
  }

  if (!chats?.length) return null
  return (
    <section className="rider-section rider-seller-chats">
      <h3>Your sellers</h3>
      <p className="rider-card-note">Chats open in the chat button at the bottom right. Can&rsquo;t settle something? Open a support ticket — support reads the whole chat and replies within 1–2 working days.</p>
      {chats.map((c) => (
        <div key={c.store_id} className="rider-seller-row">
          <b>{c.shop}</b>{' '}
          <button type="button" className="rider-btn ghost" onClick={() => openChat({ key: `sellerchat-${c.store_id}`, kind: 'sellerchat', id: c.store_id, name: c.shop, subtitle: 'Chat with the seller' })}>Open chat</button>{' '}
          {c.thread_id && (c.ticket_status === 'open'
            ? <span className="rider-card-note">Ticket open — support replies within 1–2 working days.{c.ticket_by === 'rider' && <> <button type="button" className="rider-btn ghost" onClick={() => ticket(c, 'close')}>Close ticket</button></>}</span>
            : <button type="button" className="rider-btn ghost" onClick={() => ticket(c, 'open')}>Open a support ticket</button>)}
        </div>
      ))}
    </section>
  )
}
