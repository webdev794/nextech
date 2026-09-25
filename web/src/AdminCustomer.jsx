import { useState } from 'react'
import { renderMarkdown } from './markdown'

// Admin → Customers → View: a CRM view of one customer — timeline of
// everything they did, orders, every email we sent them, support chats,
// credit and ratings, addresses — plus sending or scheduling an email.

const API_URL = import.meta.env.VITE_API_URL ?? 'http://127.0.0.1:8000/api'

async function readJson(response) {
  try { return await response.json() } catch { return {} }
}

const TABS = [['overview', 'Overview'], ['orders', 'Orders'], ['emails', 'Emails'], ['support', 'Support'], ['credit', 'Credit & ratings'], ['addresses', 'Addresses']]
const EVENT_ICONS = { joined: '👋', order: '🧾', delivered: '📦', refund: '↩️', email: '✉️', support: '💬', credit: '🎁', rating: '⭐', feedback: '🗳️' }
const KIND_LABELS = {
  otp: 'Login code', order_confirmed: 'Order confirmed', order_shipped: 'Order shipped', delivery_code: 'Delivery code',
  rider_message: 'Rider message', order_delivered: 'Delivered + bill', custom: 'Custom email', campaign: 'Campaign', rider_assigned: 'Rider assignment',
}
const when = (d) => (d ? new Date(d).toLocaleString([], { dateStyle: 'medium', timeStyle: 'short' }) : '—')

function EmailComposer({ customer, authHeaders, onDone, onCancel }) {
  const [form, setForm] = useState({ subject: '', body: '', schedule: false, send_at: '', repeat: 'none' })
  const [busy, setBusy] = useState(false)
  const [error, setError] = useState('')

  async function submit(event) {
    event.preventDefault()
    setBusy(true)
    setError('')
    try {
      const body = { subject: form.subject, body: form.body, ...(form.schedule ? { send_at: new Date(form.send_at).toISOString(), repeat: form.repeat } : {}) }
      const response = await fetch(`${API_URL}/admin/customers/${customer.id}/email`, { method: 'POST', headers: { ...authHeaders(), 'Content-Type': 'application/json' }, body: JSON.stringify(body) })
      const data = await readJson(response)
      if (!response.ok) throw new Error(data.message ?? Object.values(data.errors ?? {})[0]?.[0] ?? 'Could not send the email.')
      onDone(form.schedule ? 'Email scheduled.' : 'Email sent.')
    } catch (e) { setError(e.message) } finally { setBusy(false) }
  }

  return (
    <form className="crm-composer" onSubmit={submit}>
      <h4>Email {customer.name}</h4>
      <label>Subject<input required maxLength="200" value={form.subject} onChange={(e) => setForm({ ...form, subject: e.target.value })} placeholder="e.g. Your order, {first_name}" /></label>
      <label>Message <small className="muted">(markdown — **bold**, lists, links; {'{name}'} / {'{first_name}'} are filled in)</small><textarea required rows="7" value={form.body} onChange={(e) => setForm({ ...form, body: e.target.value })} /></label>
      {form.body.trim() && <div className="crm-preview" dangerouslySetInnerHTML={{ __html: renderMarkdown(form.body.replaceAll('{first_name}', (customer.name || '').split(' ')[0]).replaceAll('{name}', customer.name || '')) }} />}
      <label className="admin-check"><input type="checkbox" checked={form.schedule} onChange={(e) => setForm({ ...form, schedule: e.target.checked })} /> Schedule for later</label>
      {form.schedule && (
        <div className="crm-row">
          <label>Send at<input required type="datetime-local" value={form.send_at} onChange={(e) => setForm({ ...form, send_at: e.target.value })} /></label>
          <label>Repeat<select value={form.repeat} onChange={(e) => setForm({ ...form, repeat: e.target.value })}><option value="none">Once</option><option value="daily">Daily</option><option value="weekly">Weekly</option><option value="monthly">Monthly</option></select></label>
        </div>
      )}
      <div className="crm-actions"><button className="act" type="submit" disabled={busy}>{busy ? 'Sending…' : form.schedule ? 'Schedule email' : 'Send now'}</button><button type="button" className="link" onClick={onCancel}>Cancel</button></div>
      {error && <p className="auth-message">{error}</p>}
    </form>
  )
}

export function CustomerCrm({ detail, authHeaders, money, statusLabels, onClose, onToggleRider, onReload, onMessage }) {
  const [tab, setTab] = useState('overview')
  const [composing, setComposing] = useState(false)
  const [viewEmail, setViewEmail] = useState(null) // { subject, html } | 'loading'

  async function openEmail(id) {
    setViewEmail('loading')
    const data = await readJson(await fetch(`${API_URL}/admin/customer-emails/${id}`, { headers: authHeaders() }))
    setViewEmail(data.data ?? null)
  }

  async function cancelScheduled(id) {
    if (!window.confirm('Cancel this scheduled email?')) return
    await fetch(`${API_URL}/admin/email-campaigns/${id}`, { method: 'DELETE', headers: authHeaders() })
    onReload()
  }

  const d = detail
  const s = d.stats ?? {}

  return (
    <div className="admin-drawer" role="presentation" onClick={onClose}>
      <aside className="crm-drawer" onClick={(event) => event.stopPropagation()}>
        <button className="admin-close" type="button" onClick={onClose}>Close</button>
        {d.loading ? <p className="muted">Loading…</p> : <>
          <div className="crm-head">
            <div>
              <h3>{d.display_name ?? d.name}</h3>
              <p className="muted">{d.email} · {d.phone || 'no phone'} · joined {new Date(d.joined_at).toLocaleDateString()}{d.marketing_opt_out ? ' · unsubscribed from promotions' : ''}</p>
            </div>
            <button type="button" className="act" onClick={() => setComposing(true)}>✉ Send / schedule email</button>
          </div>
          <div className="crm-stats">
            <div><b>{s.orders ?? 0}</b><span>orders</span></div>
            <div><b>{(s.spend ?? []).join(' + ') || '—'}</b><span>spent</span></div>
            <div><b>{s.refunds ?? 0}</b><span>refunds</span></div>
            <div><b>{s.emails ?? 0}</b><span>emails</span></div>
            <div><b>{s.support_threads ?? 0}</b><span>chats</span></div>
            <div><b>{s.last_seen_at ? new Date(s.last_seen_at).toLocaleDateString() : '—'}</b><span>last active</span></div>
          </div>
          <label className="admin-check">
            <input type="checkbox" checked={!!d.is_rider} onChange={(event) => onToggleRider(d.id, event.target.checked)} />
            Delivery rider (can log into the rider app and deliver orders)
          </label>

          {composing && <EmailComposer customer={d} authHeaders={authHeaders} onCancel={() => setComposing(false)} onDone={(msg) => { setComposing(false); onMessage(msg); onReload() }} />}

          {(d.scheduled_emails ?? []).length > 0 && (
            <div className="crm-scheduled">
              <h4>Scheduled emails</h4>
              {d.scheduled_emails.map((c) => <p key={c.id}>⏰ {when(c.send_at)}{c.repeat !== 'none' ? ` · repeats ${c.repeat}` : ''} — <b>{c.subject}</b> <button type="button" className="link" onClick={() => cancelScheduled(c.id)}>Cancel</button></p>)}
            </div>
          )}

          <div className="crm-tabs" role="tablist">
            {TABS.map(([key, label]) => <button key={key} type="button" role="tab" aria-selected={tab === key} className={tab === key ? 'active' : ''} onClick={() => setTab(key)}>{label}</button>)}
          </div>

          {tab === 'overview' && (
            <ul className="crm-timeline">
              {(d.timeline ?? []).map((ev, i) => (
                <li key={i}>
                  <span className="crm-ico" aria-hidden>{EVENT_ICONS[ev.type] ?? '•'}</span>
                  <div>
                    <b>{ev.email_id ? <button type="button" className="link" onClick={() => openEmail(ev.email_id)}>{ev.title}</button> : ev.title}</b>
                    {ev.detail && <span className="muted"> · {ev.detail}</span>}
                    <small>{when(ev.at)}</small>
                  </div>
                </li>
              ))}
            </ul>
          )}

          {tab === 'orders' && (
            <ul className="admin-order-list">
              {(d.orders ?? []).map((order) => (
                <li key={order.id}>
                  <strong>#{order.id}</strong> {money(order.total_cents, order.currency)} · {order.payment_status} · {statusLabels[order.status] ?? order.status}
                  <span>{order.items?.length ?? 0} items · {new Date(order.created_at).toLocaleDateString()}</span>
                </li>
              ))}
              {(d.orders ?? []).length === 0 && <li className="muted">No orders.</li>}
            </ul>
          )}

          {tab === 'emails' && (
            <table className="admin-table crm-emails">
              <thead><tr><th>Sent</th><th>Type</th><th>Subject</th><th>Status</th></tr></thead>
              <tbody>
                {(d.emails ?? []).map((e) => (
                  <tr key={e.id}>
                    <td>{when(e.sent_at ?? e.created_at)}</td>
                    <td>{KIND_LABELS[e.kind] ?? e.kind}</td>
                    <td>{e.status === 'sent' ? <button type="button" className="link" onClick={() => openEmail(e.id)}>{e.subject ?? '(no subject)'}</button> : (e.subject ?? '—')}</td>
                    <td>{e.status === 'failed' ? <span className="pill pill-cancelled" title={e.error ?? ''}>failed</span> : <span className="pill pill-completed">sent</span>}</td>
                  </tr>
                ))}
                {(d.emails ?? []).length === 0 && <tr><td colSpan="4" className="muted">No emails yet.</td></tr>}
              </tbody>
            </table>
          )}

          {tab === 'support' && (
            <ul className="admin-order-list">
              {(d.support_threads ?? []).map((t) => (
                <li key={t.id}>
                  <strong>Chat #{t.id}</strong> {String(t.issue_type ?? '').replaceAll('_', ' ')} · {t.status}{t.rating ? ` · ${t.rating}★` : ''}
                  <span>{t.messages_count} messages · {new Date(t.created_at).toLocaleDateString()}{t.order_id ? ` · order #${t.order_id}` : ''}</span>
                </li>
              ))}
              {(d.support_threads ?? []).length === 0 && <li className="muted">No support chats.</li>}
            </ul>
          )}

          {tab === 'credit' && <>
            <h4>Store credit</h4>
            <ul className="admin-order-list">
              {(d.gift_cards ?? []).map((g) => <li key={g.id}><strong>{g.code}</strong> {money(g.balance_cents)} left of {money(g.initial_cents)}<span>{g.order_id ? `order #${g.order_id} · ` : ''}{new Date(g.created_at).toLocaleDateString()}</span></li>)}
              {(d.gift_cards ?? []).length === 0 && <li className="muted">None.</li>}
            </ul>
            <h4>Delivery ratings given</h4>
            <ul className="admin-order-list">
              {(d.rider_reviews ?? []).map((r) => <li key={r.id}><strong>{'★'.repeat(r.rating)}</strong> order #{r.order_id}<span>{r.comment || 'no comment'} · {new Date(r.created_at).toLocaleDateString()}</span></li>)}
              {(d.rider_reviews ?? []).length === 0 && <li className="muted">None.</li>}
            </ul>
          </>}

          {tab === 'addresses' && (
            <ul className="admin-order-list">
              {(d.addresses ?? []).map((a) => <li key={a.id}><strong>{a.label || 'Address'}{a.is_default ? ' (default)' : ''}</strong><span>{[a.line1, a.line2, a.city, a.state, a.postal_code].filter(Boolean).join(', ')}</span></li>)}
              {(d.addresses ?? []).length === 0 && <li className="muted">No saved addresses.</li>}
            </ul>
          )}
        </>}
      </aside>

      {viewEmail && (
        <div className="crm-email-view" role="presentation" onClick={(e) => { e.stopPropagation(); setViewEmail(null) }}>
          <div onClick={(e) => e.stopPropagation()}>
            <button className="admin-close" type="button" onClick={() => setViewEmail(null)}>Close</button>
            {viewEmail === 'loading' ? <p className="muted">Loading…</p> : <>
              <h4>{viewEmail.subject}</h4>
              {viewEmail.html ? <iframe title="Email" sandbox="" srcDoc={viewEmail.html} /> : <p className="muted">No copy of this email was stored.</p>}
            </>}
          </div>
        </div>
      )}
    </div>
  )
}
