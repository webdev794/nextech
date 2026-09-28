import { openChat } from './chatDockUtils'

// Opening admin chats in the admin console's ChatDock (AdminSellerChat.jsx).
// popOutSellerChat() opens a seller chat in its own small browser window,
// which stays open even when the admin tab is closed.

const BASE = (import.meta.env.BASE_URL || '/').replace(/\/$/, '')

export function popupUrl(id, name) {
  return `${window.location.origin}${BASE}/#/admin-chat/${id}?name=${encodeURIComponent(name)}`
}

export function openSellerChat(seller) {
  const id = seller.id
  const name = seller.shop?.name ?? seller.name ?? seller.company_name ?? `Seller #${id}`
  openChat({ key: `seller-${id}`, kind: 'seller', id, name, subtitle: 'Seller' })
}

// Returns false when the browser blocked the pop-up.
export function popOutSellerChat(id, name) {
  const win = window.open(popupUrl(id, name), `nextech-seller-chat-${id}`, 'popup=yes,width=420,height=340,left=' + Math.max(0, window.screen.width - 480) + ',top=80')
  if (win) win.focus()
  return Boolean(win)
}

// A support thread row (admin list) as a chat for the dock.
export function supportChat(t, issueLabels = {}) {
  return {
    key: `support-${t.id}`, kind: 'support', id: t.id,
    name: t.user?.name || t.user?.email || `Conversation #${t.id}`,
    subtitle: [issueLabels[t.issue_type] ?? t.issue_type?.replaceAll('_', ' '), t.order_id ? `order #${t.order_id}` : null].filter(Boolean).join(' · '),
    stamp: t.needs_reply ? t.last_message_at : null,
  }
}

export function openSupportChat(thread, issueLabels) {
  openChat(supportChat(thread, issueLabels))
}
