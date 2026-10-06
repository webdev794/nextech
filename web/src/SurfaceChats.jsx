import { useCallback, useEffect, useMemo, useRef } from 'react'
import { ChatDock } from './ChatDock'
import { API_URL, chatAdapter, readJson } from './chatDockUtils'
import { brandName } from './useBranding'

// The chat docks for Seller Center, the storefront and the rider console —
// the same windows as the admin console (ChatDock.jsx), each with its own
// kinds of chat. `onDetails` handlers open that surface's full view of a
// conversation (photos, rating, ending the chat, …).

const NEXTECH = { key: 'nextech', kind: 'nextech', id: 0, name: `${brandName()} support`, minimized: true }

// Chat setups are cached per window, so reach the latest handler through a ref.
function useLatest(fn) {
  const ref = useRef(fn)
  useEffect(() => { ref.current = fn })
  return ref
}

// Seller Center: NexTech support (always there, folded) and chats with buyers.
// onRead(chat, page): a chat was just read in its window (clears the page's unread counts).
export function SellerChatDock({ authHeaders, onCustomerDetails, onRead }) {
  const details = useLatest(onCustomerDetails)
  const adapterFor = useCallback((chat) => (chat.kind === 'nextech'
    ? { adapter: chatAdapter({ url: `${API_URL}/seller/chat`, headers: authHeaders }), labels: { nextech: `${brandName()}` } }
    : {
        adapter: chatAdapter({ url: `${API_URL}/seller/customer-chats/${chat.id}/chat`, postUrl: `${API_URL}/seller/customer-chats/${chat.id}/messages`, headers: authHeaders }),
        labels: { customer: 'Buyer', nextech: `${brandName()}`, rider: 'Delivery partner' },
        onDetails: () => details.current?.({ id: chat.id }),
      }), [authHeaders, details])
  const load = useCallback(async () => {
    const d = await readJson(await fetch(`${API_URL}/seller/customer-chats`, { headers: authHeaders() }))
    return [{ ...NEXTECH, subtitle: 'Questions about your shop' }, ...(d.data ?? []).map((t) => ({
      key: `customer-${t.id}`, kind: 'customer', id: t.id, name: t.customer_name || 'Buyer',
      subtitle: [t.issue_type?.replaceAll('_', ' '), t.order_id ? `order #${t.order_id}` : null].filter(Boolean).join(' · '),
      stamp: t.needs_seller_reply ? t.last_message_at : null,
    }))]
  }, [authHeaders])
  const launcher = useMemo(() => ({ label: 'Messages', load }), [load])
  return <ChatDock storeKey="nextech_seller_chats" adapterFor={adapterFor} launcher={launcher} initial={[NEXTECH]} onRead={onRead} />
}

// Storefront: the customer's support conversations (NexTech, and the seller
// or delivery partner when they're in the chat).
export function CustomerChatDock({ authHeaders, issueLabel, onDetails, onRead }) {
  const details = useLatest(onDetails)
  const adapterFor = useCallback((chat) => ({
    adapter: chatAdapter({ url: `${API_URL}/support/threads/${chat.id}/chat`, postUrl: `${API_URL}/support/threads/${chat.id}/messages`, headers: authHeaders }),
    labels: { nextech: `${brandName()}`, seller: 'Seller', rider: 'Delivery partner' },
    onDetails: () => details.current?.(chat.id),
  }), [authHeaders, details])
  const load = useCallback(async () => {
    const d = await readJson(await fetch(`${API_URL}/support/threads`, { headers: authHeaders() }))
    return (d.data ?? []).map((t) => customerChat(t, issueLabel))
  }, [authHeaders, issueLabel])
  const launcher = useMemo(() => ({ label: 'Messages', load }), [load])
  return <ChatDock storeKey="nextech_customer_chats" adapterFor={adapterFor} launcher={launcher} onRead={onRead} />
}

function customerChat(t, issueLabel) {
  return {
    key: `support-${t.id}`, kind: 'support', id: t.id,
    name: issueLabel(t.issue_type), subtitle: ['Started by you', t.order_id ? `order #${t.order_id}` : null, t.status === 'resolved' ? 'resolved' : null].filter(Boolean).join(' · '),
    stamp: t.last_staff_message_at && !(t.last_message_at && new Date(t.last_staff_message_at) < new Date(t.last_message_at)) ? t.last_staff_message_at : null,
  }
}

// Rider console: NexTech support (always there, folded) and a chat with the
// customer of each delivery — two side by side on a wide screen.
export function RiderChatDock({ headers, orders }) {
  const adapterFor = useCallback((chat) => (chat.kind === 'nextech'
    ? { adapter: chatAdapter({ url: `${API_URL}/rider/support-chat`, headers }), labels: { nextech: `${brandName()}` } }
    : { adapter: chatAdapter({ url: `${API_URL}/rider/orders/${chat.id}/chat`, postUrl: `${API_URL}/rider/orders/${chat.id}/messages`, headers }), labels: { customer: 'Customer', nextech: `${brandName()}`, seller: 'Seller' } }), [headers])
  const items = useMemo(() => [{ ...NEXTECH, subtitle: 'Help with a delivery' }, ...orders.map((o) => ({ key: `order-${o.id}`, kind: 'order', id: o.id, name: `Order #${o.id}`, subtitle: 'Chat with the customer' }))], [orders])
  const launcher = useMemo(() => ({ label: 'Messages', items }), [items])
  return <ChatDock storeKey="nextech_rider_chats" adapterFor={adapterFor} launcher={launcher} initial={[NEXTECH]} />
}
