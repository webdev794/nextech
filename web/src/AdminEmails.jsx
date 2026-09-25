import { useCallback, useEffect, useState } from 'react'
import { renderMarkdown } from './markdown'

// Admin → Emails: campaigns to a group of customers (now, scheduled, or
// repeating), the order-email switch, and a log of every email sent.

const API_URL = import.meta.env.VITE_API_URL ?? 'http://127.0.0.1:8000/api'

async function readJson(response) {
  try { return await response.json() } catch { return {} }
}

const STATUS_PILL = { draft: 'pending', scheduled: 'confirmed', sending: 'packing', sent: 'completed', cancelled: 'cancelled' }
const KIND_LABELS = {
  otp: 'Login code', order_confirmed: 'Order confirmed', order_shipped: 'Order shipped', delivery_code: 'Delivery code',
  rider_message: 'Rider message', order_delivered: 'Delivered + bill', custom: 'Custom email', campaign: 'Campaign', rider_assigned: 'Rider assignment',
}
const when = (d) => (d ? new Date(d).toLocaleString([], { dateStyle: 'medium', timeStyle: 'short' }) : '—')
const toLocalInput = (d) => { if (!d) return ''; const x = new Date(d); x.setMinutes(x.getMinutes() - x.getTimezoneOffset()); return x.toISOString().slice(0, 16) }
const EMPTY = { name: '', subject: '', body: '', market: '', has_ordered: '', no_order_in_days: '', promotional: true, send_at: '', repeat: 'none' }

function audienceText(a = {}) {
  if (a.user_id) return `Customer #${a.user_id}`
  const parts = []
  if (a.market) parts.push(`ordered in ${a.market}`)
  if (a.has_ordered === true) parts.push('has ordered')
  if (a.has_ordered === false) parts.push('never ordered')
  if (a.no_order_in_days) parts.push(`no order in ${a.no_order_in_days} days`)
  return parts.length ? parts.join(', ') : 'All customers'
}

export function EmailsPanel({ authHeaders, defaultMarket, onMessage }) {
  const [data, setData] = useState(null)
  const [form, setForm] = useState(null)
  const [preview, setPreview] = useState(null) // { count, html }
  const [busy, setBusy] = useState(false)
  const [error, setError] = useState('')

  const load = useCallback(() => {
    fetch(`${API_URL}/admin/email-campaigns`, { headers: authHeaders() }).then(readJson).then((d) => setData(d.data ?? null)).catch(() => {})
  }, [authHeaders])
  useEffect(() => { Promise.resolve().then(load) }, [load])

  const json = () => ({ ...authHeaders(), 'Content-Type': 'application/json' })
  const audienceOf = (f) => ({
    ...(f.market ? { market: f.market } : {}),
    ...(f.has_ordered !== '' ? { has_ordered: f.has_ordered === 'yes' } : {}),
    ...(f.no_order_in_days ? { no_order_in_days: Number(f.no_order_in_days) } : {}),
  })

  async function runPreview() {
    setError('')
    const response = await fetch(`${API_URL}/admin/email-campaigns/preview`, { method: 'POST', headers: json(), body: JSON.stringify({ subject: form.subject || '(subject)', body: form.body || ' ', audience: audienceOf(form), promotional: form.promotional }) })
    const d = await readJson(response)
    if (!response.ok) { setError(d.message ?? 'Preview failed.'); return }
    setPreview(d.data)
  }

  async function save(action) {
    setBusy(true)
    setError('')
    try {
      const body = { name: form.name || form.subject, subject: form.subject, body: form.body, audience: audienceOf(form), promotional: form.promotional, repeat: form.repeat, send_at: form.send_at ? new Date(form.send_at).toISOString() : null, action }
      if (action === 'send_now' && !window.confirm(`Send "${form.subject}" now to ${preview?.count ?? 'the selected'} customer(s)?`)) { setBusy(false); return }
      const response = await fetch(`${API_URL}/admin/email-campaigns${form.id ? `/${form.id}` : ''}`, { method: form.id ? 'PUT' : 'POST', headers: json(), body: JSON.stringify(body) })
      const d = await readJson(response)
      if (!response.ok) throw new Error(d.message ?? Object.values(d.errors ?? {})[0]?.[0] ?? 'Could not save.')
      onMessage(action === 'send_now' ? `Sent to ${d.data.sent_count} customer(s).` : action === 'schedule' ? 'Email scheduled.' : 'Draft saved.')
      setForm(null); setPreview(null); load()
    } catch (e) { setError(e.message) } finally { setBusy(false) }
  }

  async function cancel(c) {
    if (!window.confirm(c.status === 'draft' ? 'Delete this draft?' : 'Cancel this email?')) return
    await fetch(`${API_URL}/admin/email-campaigns/${c.id}`, { method: 'DELETE', headers: authHeaders() })
    load()
  }

  async function sendNow(c) {
    if (!window.confirm(`Send "${c.subject}" now?`)) return
    const response = await fetch(`${API_URL}/admin/email-campaigns/${c.id}/send-now`, { method: 'POST', headers: authHeaders() })
    const d = await readJson(response)
    onMessage(response.ok ? `Sent to ${d.sent} customer(s).` : (d.message ?? 'Could not send.'))
    load()
  }

  async function toggleOrderEmails(enabled) {
    await fetch(`${API_URL}/admin/order-emails`, { method: 'POST', headers: json(), body: JSON.stringify({ enabled }) })
    load()
  }

  function edit(c) {
    const a = c.audience ?? {}
    setPreview(null)
    setForm({ id: c.id, name: c.name, subject: c.subject, body: c.body, market: a.market ?? '', has_ordered: a.has_ordered === true ? 'yes' : a.has_ordered === false ? 'no' : '', no_order_in_days: a.no_order_in_days ?? '', promotional: !!c.promotional, send_at: toLocalInput(c.send_at), repeat: c.repeat })
  }

  if (!data) return <section className="admin-panel"><p className="muted">Loading…</p></section>

  return (
    <section className="admin-panel">
      <div className="admin-form">
        <h3>Order emails</h3>
        <label className="admin-check"><input type="checkbox" checked={!!data.order_emails} onChange={(e) => toggleOrderEmails(e.target.checked)} /> Email customers when an order is confirmed and when it ships (with tracking)</label>
        <p className="muted">The delivery code, rider messages and the bill on delivery are always sent. Every email is logged below and on each customer&rsquo;s page (Customers → View).</p>
      </div>

      <div className="admin-form">
        <div className="crm-head"><h3>Campaigns &amp; scheduled emails</h3>{!form && <button type="button" className="act" onClick={() => { setPreview(null); setForm({ ...EMPTY, market: defaultMarket ?? '' }) }}>New email</button>}</div>

        {form && (
          <div className="crm-composer">
            <div className="crm-row">
              <label>Name (internal)<input maxLength="120" value={form.name} onChange={(e) => setForm({ ...form, name: e.target.value })} placeholder="e.g. Diwali sale" /></label>
              <label>Subject<input required maxLength="200" value={form.subject} onChange={(e) => setForm({ ...form, subject: e.target.value })} placeholder="e.g. {first_name}, 20% off headphones" /></label>
            </div>
            <label>Message <small className="muted">(markdown; {'{name}'} / {'{first_name}'} are filled in per customer)</small><textarea rows="8" value={form.body} onChange={(e) => setForm({ ...form, body: e.target.value })} /></label>
            {form.body.trim() && <div className="crm-preview" dangerouslySetInnerHTML={{ __html: renderMarkdown(form.body.replaceAll('{first_name}', 'Alex').replaceAll('{name}', 'Alex Customer')) }} />}
            <h4>Who gets it</h4>
            <div className="crm-row">
              <label>Country<select value={form.market} onChange={(e) => setForm({ ...form, market: e.target.value })}><option value="">Any</option><option value="US">Ordered in the US</option><option value="IN">Ordered in India</option></select></label>
              <label>Orders<select value={form.has_ordered} onChange={(e) => setForm({ ...form, has_ordered: e.target.value })}><option value="">Any</option><option value="yes">Has ordered</option><option value="no">Never ordered</option></select></label>
              <label>No order in the last … days<input type="number" min="1" value={form.no_order_in_days} onChange={(e) => setForm({ ...form, no_order_in_days: e.target.value })} placeholder="e.g. 30" /></label>
            </div>
            <label className="admin-check"><input type="checkbox" checked={form.promotional} onChange={(e) => setForm({ ...form, promotional: e.target.checked })} /> Promotional (adds an unsubscribe link and skips customers who unsubscribed)</label>
            <h4>When</h4>
            <div className="crm-row">
              <label>Send at<input type="datetime-local" value={form.send_at} onChange={(e) => setForm({ ...form, send_at: e.target.value })} /></label>
              <label>Repeat<select value={form.repeat} onChange={(e) => setForm({ ...form, repeat: e.target.value })}><option value="none">Once</option><option value="daily">Daily</option><option value="weekly">Weekly</option><option value="monthly">Monthly</option></select></label>
            </div>
            {preview && <p className="crm-reach">Reaches <b>{preview.count}</b> customer{preview.count === 1 ? '' : 's'}.</p>}
            {preview?.html && <iframe className="crm-iframe" title="Preview" sandbox="" srcDoc={preview.html} />}
            <div className="crm-actions">
              <button type="button" className="link" onClick={runPreview}>Preview &amp; count</button>
              <button type="button" className="act" disabled={busy || !form.subject || !form.body} onClick={() => save('send_now')}>Send now</button>
              <button type="button" className="act" disabled={busy || !form.subject || !form.body || !form.send_at} onClick={() => save('schedule')}>Schedule</button>
              <button type="button" className="link" disabled={busy} onClick={() => save('draft')}>Save draft</button>
              <button type="button" className="link" onClick={() => { setForm(null); setPreview(null) }}>Cancel</button>
            </div>
            {error && <p className="auth-message">{error}</p>}
          </div>
        )}

        <table className="admin-table">
          <thead><tr><th>Email</th><th>Audience</th><th>When</th><th>Status</th><th>Sent</th><th></th></tr></thead>
          <tbody>
            {data.campaigns.map((c) => (
              <tr key={c.id}>
                <td><b>{c.subject}</b><span className="admin-note">{c.name}</span></td>
                <td>{audienceText(c.audience)}</td>
                <td>{when(c.send_at)}{c.repeat !== 'none' && <span className="admin-note">repeats {c.repeat}</span>}</td>
                <td><span className={`pill pill-${STATUS_PILL[c.status] ?? 'pending'}`}>{c.status}</span></td>
                <td>{c.sent_count}</td>
                <td className="admin-row-actions">
                  {['draft', 'scheduled'].includes(c.status) && <button type="button" className="link" onClick={() => edit(c)}>Edit</button>}
                  {['draft', 'scheduled'].includes(c.status) && <button type="button" className="link" onClick={() => sendNow(c)}>Send now</button>}
                  {['draft', 'scheduled'].includes(c.status) && <button type="button" className="link" onClick={() => cancel(c)}>{c.status === 'draft' ? 'Delete' : 'Cancel'}</button>}
                </td>
              </tr>
            ))}
            {data.campaigns.length === 0 && <tr><td colSpan="6" className="muted">No campaigns yet.</td></tr>}
          </tbody>
        </table>
        <p className="muted">Scheduled emails go out within a minute of their time — on the live server this needs the cron job <code>php artisan schedule:run</code> every minute.</p>
      </div>

      <div className="admin-form">
        <h3>Recently sent</h3>
        <table className="admin-table">
          <thead><tr><th>Sent</th><th>To</th><th>Type</th><th>Subject</th><th>Status</th></tr></thead>
          <tbody>
            {data.recent.map((e) => (
              <tr key={e.id}>
                <td>{when(e.sent_at ?? e.created_at)}</td>
                <td>{e.user?.name ?? e.to_email}<span className="admin-note">{e.to_email}</span></td>
                <td>{KIND_LABELS[e.kind] ?? e.kind}</td>
                <td>{e.subject ?? '—'}</td>
                <td>{e.status === 'failed' ? <span className="pill pill-cancelled" title={e.error ?? ''}>failed</span> : <span className="pill pill-completed">sent</span>}</td>
              </tr>
            ))}
            {data.recent.length === 0 && <tr><td colSpan="5" className="muted">Nothing sent yet.</td></tr>}
          </tbody>
        </table>
      </div>
    </section>
  )
}
