import { useCallback, useEffect, useMemo, useRef, useState } from 'react'
import { formatMoney } from './money'
import { ChatDock, ChatWindow } from './ChatDock'
import { API_URL, chatAdapter, readJson } from './chatDockUtils'
import { popOutSellerChat, supportChat } from './sellerChatEvents'
import './Admin.css'
import { brandName } from './useBranding'

// The admin console's chat windows (ChatDock.jsx): chats with sellers
// (openSellerChat) and customer / rider support conversations
// (openSupportChat), several side by side. The "Messages" tab lists the open
// support conversations and flags ones waiting on a reply. "⋯" on a support
// chat opens its full card (status, order, refunds, adding the seller).

const sellerAdapter = (id, authHeaders) => chatAdapter({ url: `${API_URL}/admin/sellers/${id}/chat`, headers: authHeaders })

export function AdminChatDock({ authHeaders, issueLabels, onSupportDetails, onOpenSeller, onOpenPerson }) {
  // Chat setups are cached per window, so reach the latest handler through a ref.
  const details = useRef(onSupportDetails)
  useEffect(() => { details.current = onSupportDetails })
  const openSeller = useRef(onOpenSeller)
  useEffect(() => { openSeller.current = onOpenSeller })
  const openPerson = useRef(onOpenPerson)
  useEffect(() => { openPerson.current = onOpenPerson })
  const adapterFor = useCallback((chat) => (chat.kind === 'seller'
    ? { adapter: sellerAdapter(chat.id, authHeaders), labels: { seller: chat.name, nextech: `${brandName()}` }, onTitle: () => openSeller.current?.(chat.id) }
    : {
        adapter: chatAdapter({ url: `${API_URL}/admin/support/threads/${chat.id}/chat`, postUrl: `${API_URL}/admin/support/threads/${chat.id}/messages`, headers: authHeaders }),
        labels: { customer: 'Customer', seller: 'Seller', rider: 'Rider', nextech: `${brandName()}` },
        onDetails: () => details.current(chat.id),
        // The name opens who it is, behind the chat: a seller's page, a rider's panel, or the buyer's details.
        // Looked up from the chat itself, so it also works for chat windows opened before this existed.
        onTitle: async () => {
          let t = { seller_id: chat.sellerId, user_id: chat.userId, issue_type: chat.issueType }
          if (!t.issue_type || !t.user_id) {
            const d = await readJson(await fetch(`${API_URL}/admin/support/threads/${chat.id}`, { headers: authHeaders() }))
            if (d?.data) t = d.data
          }
          if (String(t.issue_type ?? '').startsWith('seller_') && t.seller_id) openSeller.current?.(t.seller_id)
          else if (t.user_id) openPerson.current?.(['rider_support', 'rider_seller'].includes(t.issue_type) ? 'rider' : 'customer', t.user_id)
        },
      }), [authHeaders])
  const load = useCallback(async () => {
    const d = await readJson(await fetch(`${API_URL}/admin/support/threads?status=open`, { headers: authHeaders() }))
    return (d.data ?? []).map((t) => supportChat(t, issueLabels))
  }, [authHeaders, issueLabels])
  const launcher = useMemo(() => ({ label: 'Messages', load }), [load])
  return <ChatDock storeKey="nextech_admin_chats" adapterFor={adapterFor} launcher={launcher}
    onPopOutFallback={(chat) => chat.kind === 'seller' && popOutSellerChat(chat.id, chat.name)} />
}

// The seller chat in its own browser window (#/admin-chat/<seller id>), the
// pop-out where the browser has no always-on-top window. Uses the admin's
// sign-in from this browser.
export function SellerChatPopup() {
  const match = window.location.hash.match(/^#\/admin-chat\/(\d+)(?:\?name=([^&]*))?/)
  const id = Number(match?.[1])
  const name = match?.[2] ? decodeURIComponent(match[2]) : `Seller #${id}`
  const token = (() => { try { return localStorage.getItem('gdp_token') || '' } catch { return '' } })()
  const authHeaders = useCallback(() => ({ Accept: 'application/json', Authorization: `Bearer ${token}` }), [token])
  const adapter = useMemo(() => sellerAdapter(id, authHeaders), [id, authHeaders])
  const [unread, setUnread] = useState(0)
  useEffect(() => { document.title = unread ? `(${unread}) ${name} — ${brandName()} chat` : `${name} — ${brandName()} chat` }, [unread, name])
  const onUnread = useCallback((_, n) => setUnread(n), [])
  if (!id || !token) return <p style={{ padding: 20, font: '14px system-ui' }}>Sign in to the admin console in this browser first.</p>
  return (
    <div className="chatdock-popup">
      <ChatWindow popup chat={{ key: `seller-${id}`, name, minimized: false, max: true, unread }} adapter={adapter} labels={{ seller: name, nextech: `${brandName()}` }}
        readKey={`nextech_admin_chats:read:seller-${id}`} onUnread={onUnread} onClose={() => window.close()} />
    </div>
  )
}

// The seller's payout ledger, a page at a time.
export function SellerLedgerTable({ sellerId, authHeaders, labels }) {
  const [page, setPage] = useState(1)
  const [data, setData] = useState(null)
  useEffect(() => {
    fetch(`${API_URL}/admin/sellers/${sellerId}/ledger?page=${page}`, { headers: authHeaders() }).then(readJson).then(setData).catch(() => setData({ data: [], meta: { last_page: 1, total: 0 } }))
  }, [sellerId, page, authHeaders])
  if (!data) return <p className="muted">Loading ledger…</p>
  if (!data.data?.length) return <p className="muted">No ledger entries yet.</p>
  const last = data.meta?.last_page ?? 1
  return (
    <>
      <table className="admin-table">
        <thead><tr><th>Date</th><th>Entry</th><th>Order</th><th>Amount</th></tr></thead>
        <tbody>{data.data.map((entry) => (
          <tr key={entry.id}>
            <td>{new Date(entry.created_at).toLocaleDateString()}</td>
            <td>{labels[entry.type] ?? entry.type}{entry.note ? <span className="admin-note">{entry.note}</span> : null}</td>
            <td>{entry.order_id ? `#${entry.order_id}` : '—'}</td>
            <td><b>{entry.amount_cents >= 0 ? '+' : '−'}{formatMoney(Math.abs(entry.amount_cents), data.currency)}</b></td>
          </tr>
        ))}</tbody>
      </table>
      {last > 1 && (
        <div className="admin-form-actions">
          <button className="act ghost" type="button" disabled={page <= 1} onClick={() => setPage(page - 1)}>Previous</button>
          <span className="muted">Page {page} of {last} · {data.meta.total} entries</span>
          <button className="act ghost" type="button" disabled={page >= last} onClick={() => setPage(page + 1)}>Next</button>
        </div>
      )}
    </>
  )
}
