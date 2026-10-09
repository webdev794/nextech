import { useEffect, useRef, useState } from 'react'

// A small card about a seller (from Stores / hubs and other lists): contact,
// business type, application and shop status, and their last message.

const API_URL = import.meta.env.VITE_API_URL ?? 'http://127.0.0.1:8000/api'
const sellingFor = (date) => {
  const m = Math.max(0, (new Date().getFullYear() - new Date(date).getFullYear()) * 12 + new Date().getMonth() - new Date(date).getMonth())
  return m < 1 ? 'under a month' : m < 12 ? `${m} month${m === 1 ? '' : 's'}` : `${Math.floor(m / 12)} yr${Math.floor(m / 12) === 1 ? '' : 's'}${m % 12 ? ` ${m % 12} mo` : ''}`
}
const STATUS = { approved: 'Approved', pending: 'Waiting for review', rejected: 'Rejected', suspended: 'Paused', changes_requested: 'Changes requested', draft: 'Not submitted' }

export function SellerCard({ sellerId, headers, onClose, onOpen }) {
  const [seller, setSeller] = useState(null)
  const [error, setError] = useState('')
  const box = useRef(null)
  useEffect(() => {
    fetch(`${API_URL}/admin/sellers/${sellerId}`, { headers: headers() })
      .then(async (r) => { const d = await r.json().catch(() => ({})); if (!r.ok) throw new Error(d.message ?? 'Could not load the seller.'); setSeller(d.data) })
      .catch((e) => setError(e.message))
  }, [sellerId, headers])
  useEffect(() => {
    const away = (e) => { if (box.current && !box.current.contains(e.target)) onClose() }
    const esc = (e) => { if (e.key === 'Escape') onClose() }
    document.addEventListener('mousedown', away)
    document.addEventListener('keydown', esc)
    return () => { document.removeEventListener('mousedown', away); document.removeEventListener('keydown', esc) }
  }, [onClose])

  const phone = seller?.pickup_phone ?? seller?.user?.phone
  return (
    <div className="seller-card-pop" ref={box} role="dialog" aria-label="Seller details">
      <button type="button" className="seller-card-x" aria-label="Close" onClick={onClose}>×</button>
      {!seller ? <p className="muted">{error || 'Loading…'}</p> : <>
        <h4>{seller.shop?.name ?? seller.company_name}</h4>
        <dl>
          <dt>Owner</dt><dd>{seller.user?.name ?? seller.contact_name ?? '—'}</dd>
          <dt>Email</dt><dd>{seller.user?.email ? <a href={`mailto:${seller.user.email}`}>{seller.user.email}</a> : '—'}</dd>
          <dt>Phone</dt><dd>{phone ? <a href={`tel:${phone}`}>{phone}</a> : '—'}</dd>
          <dt>Business</dt><dd>{seller.business_type ? seller.business_type.replace(/_/g, ' ') : '—'}{seller.company_name ? ` · ${seller.company_name}` : ''}</dd>
          <dt>Status</dt><dd>{STATUS[seller.status] ?? seller.status}{seller.shop ? ` · shop ${seller.shop.is_active ? 'open' : 'paused'}` : ''}</dd>
          {/* Admin only: how long they've sold here (from approval). */}
          <dt>Selling since</dt><dd>{seller.status === 'approved' && seller.reviewed_at ? `${new Date(seller.reviewed_at).toLocaleDateString()} (${sellingFor(seller.reviewed_at)})` : '—'}</dd>
          {seller.rider_feedback?.count > 0 && <><dt>Riders say</dt><dd>
            ★{seller.rider_feedback.avg ?? '—'} from {seller.rider_feedback.count} deliveries <small className="muted">(private — the seller doesn&rsquo;t see this)</small>
            <ul className="seller-card-notes">{seller.rider_feedback.recent.filter((f) => f.note || f.rating <= 2).map((f) => <li key={`${f.order_id}-${f.at}`}>{f.rating ? `★${f.rating} ` : ''}{f.note ?? ''} <small className="muted">— {f.rider}, order #{f.order_id}</small></li>)}</ul>
          </dd></>}
          <dt>Last message</dt><dd>{seller.last_message ? <>{seller.last_message.is_staff ? 'You: ' : ''}{String(seller.last_message.body).slice(0, 140)}{String(seller.last_message.body).length > 140 ? '…' : ''} <small className="muted">· {new Date(seller.last_message.created_at).toLocaleDateString()}</small></> : 'No messages yet'}</dd>
        </dl>
        <div className="admin-form-actions"><button type="button" className="act" onClick={() => onOpen(seller.id)}>Open seller page</button></div>
      </>}
    </div>
  )
}
