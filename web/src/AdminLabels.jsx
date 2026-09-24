import { useCallback, useEffect, useState } from 'react'
import { currencySymbol } from './money'

// Manual NexTech labels: sellers on "I ship, NexTech label" request a label
// and the admin uploads the label PDF here for them to download and print.
// Tracking and postage are optional — without tracking, the seller adds it
// when they hand the package over.

const API_URL = import.meta.env.VITE_API_URL ?? 'http://127.0.0.1:8000/api'

async function readJson(response) {
  try { return await response.json() } catch { return {} }
}

const addressLine = (a) => [a?.name, a?.line1, a?.line2, [a?.city, a?.state, a?.postal_code].filter(Boolean).join(' ')].filter(Boolean).join(', ')

async function openFile(url, authHeaders) {
  const response = await fetch(url, { headers: authHeaders() })
  if (!response.ok) return
  const blobUrl = URL.createObjectURL(await response.blob())
  window.open(blobUrl, '_blank', 'noopener')
  setTimeout(() => URL.revokeObjectURL(blobUrl), 60000)
}

// Replace an issued label with the admin's own file.
async function replaceLabel(request, authHeaders, file) {
  const body = new FormData()
  body.append('label', file)
  const response = await fetch(`${API_URL}/admin/label-requests/${request.id}/replace`, { method: 'POST', headers: authHeaders(), body })
  const data = await readJson(response)
  if (!response.ok) throw new Error(data.message ?? Object.values(data.errors ?? {})[0]?.[0] ?? 'Could not replace the label.')
  return data.data
}

// Upload form for one waiting request.
function FulfilForm({ request, authHeaders, onDone }) {
  const [form, setForm] = useState({ carrier: request.carriers?.[0]?.value ?? 'Other', tracking: '', cost: '', file: null })
  const [busy, setBusy] = useState(false)
  const [error, setError] = useState('')

  async function submit(event) {
    event.preventDefault()
    if (!form.file) { setError('Choose the label PDF.'); return }
    setBusy(true)
    setError('')
    const body = new FormData()
    body.append('label', form.file)
    if (form.tracking.trim()) {
      body.append('carrier', form.carrier)
      body.append('tracking_number', form.tracking.trim())
    }
    if (Number(form.cost) > 0) body.append('cost_cents', String(Math.round(Number(form.cost) * 100)))
    try {
      const response = await fetch(`${API_URL}/admin/label-requests/${request.id}/fulfil`, { method: 'POST', headers: authHeaders(), body })
      const data = await readJson(response)
      if (!response.ok) throw new Error(data.message ?? Object.values(data.errors ?? {})[0]?.[0] ?? 'Could not upload the label.')
      onDone(data.data, form.tracking.trim() ? 'Label uploaded and the package marked shipped.' : 'Label uploaded — the seller can download it now.')
    } catch (e) { setError(e.message) } finally { setBusy(false) }
  }

  async function decline() {
    const reason = window.prompt('Why can’t this label be issued? The seller sees this.', '')
    if (!reason?.trim()) return
    setBusy(true)
    try {
      const response = await fetch(`${API_URL}/admin/label-requests/${request.id}/cancel`, { method: 'POST', headers: { ...authHeaders(), 'Content-Type': 'application/json' }, body: JSON.stringify({ reason: reason.trim() }) })
      const data = await readJson(response)
      if (!response.ok) throw new Error(data.message ?? 'Could not decline the request.')
      onDone(data.data, 'Label request declined.')
    } catch (e) { setError(e.message) } finally { setBusy(false) }
  }

  return (
    <form className="admin-label-form" onSubmit={submit}>
      <label>Label PDF (max 5 MB)<input required type="file" accept="application/pdf,image/png,image/jpeg" onChange={(e) => setForm({ ...form, file: e.target.files?.[0] ?? null })} /></label>
      <details className="admin-label-optional">
        <summary>Optional: tracking &amp; postage</summary>
        <label>Carrier<select value={form.carrier} onChange={(e) => setForm({ ...form, carrier: e.target.value })}>{(request.carriers ?? []).map((c) => <option key={c.value} value={c.value}>{c.label}</option>)}</select></label>
        <label>Tracking number <small className="muted">(blank = seller adds it when shipping)</small><input minLength="6" value={form.tracking} onChange={(e) => setForm({ ...form, tracking: e.target.value })} /></label>
        <label>Postage to deduct from seller ({currencySymbol(request.currency)}) <small className="muted">(blank = none)</small><input type="number" min="0" step="0.01" value={form.cost} onChange={(e) => setForm({ ...form, cost: e.target.value })} /></label>
      </details>
      <div className="admin-label-actions">
        <button className="act" type="submit" disabled={busy}>{busy ? 'Uploading…' : 'Upload label'}</button>
        <button className="link" type="button" disabled={busy} onClick={decline}>Decline</button>
      </div>
      {error && <p className="auth-message">{error}</p>}
    </form>
  )
}

function RequestCard({ request, authHeaders, onDone, onOpenOrder }) {
  return (
    <div className="admin-label-card">
      <p>
        <b>{request.shop_name}</b> · order {onOpenOrder ? <button type="button" className="link" onClick={() => onOpenOrder(request.order_id)}>#{request.order_id}</button> : `#${request.order_id}`}
        {' · '}requested {new Date(request.created_at).toLocaleString()}
      </p>
      <p className="muted">Items: {request.items.map((i) => `${i.product_name}${i.variant_label ? ` · ${i.variant_label}` : ''} × ${i.quantity}`).join('; ')}</p>
      <p className="muted">From: {addressLine(request.ship_from)}{request.ship_from?.phone ? ` · ${request.ship_from.phone}` : ''}</p>
      <p className="muted">To: {addressLine(request.ship_to)}{request.ship_to?.phone ? ` · ${request.ship_to.phone}` : ''}</p>
      {request.note && <p className="muted">Seller note: {request.note}</p>}
      <FulfilForm request={request} authHeaders={authHeaders} onDone={onDone} />
    </div>
  )
}

// Every waiting request (top of the Orders tab); renders nothing when none.
export function LabelRequestsPanel({ authHeaders, onMessage, onOpenOrder, refreshKey }) {
  const [requests, setRequests] = useState([])

  const load = useCallback(() => {
    fetch(`${API_URL}/admin/label-requests`, { headers: authHeaders() })
      .then(readJson)
      .then((data) => setRequests(data.data ?? []))
      .catch(() => {})
  }, [authHeaders])
  useEffect(() => { Promise.resolve().then(load) }, [load, refreshKey])

  if (requests.length === 0) return null

  return (
    <div className="admin-form admin-label-panel">
      <h3>Shipping labels to upload ({requests.length})</h3>
      <p className="muted">Upload the label PDF for each request — the seller downloads and prints it. Adding a tracking number is optional (otherwise the seller enters it when shipping).</p>
      {requests.map((r) => (
        <RequestCard key={r.id} request={r} authHeaders={authHeaders} onOpenOrder={onOpenOrder} onDone={(_, msg) => { onMessage?.(msg); load() }} />
      ))}
    </div>
  )
}

// Inside an order's drawer: its label requests (waiting ones get the upload form).
export function OrderLabelRequests({ order, authHeaders, onChanged }) {
  const [full, setFull] = useState(null)
  const waiting = (order.label_requests ?? []).filter((r) => r.status === 'requested')
  const key = waiting.map((r) => r.id).join(',')

  useEffect(() => {
    if (!key) return undefined
    let cancelled = false
    fetch(`${API_URL}/admin/label-requests`, { headers: authHeaders() })
      .then(readJson)
      .then((data) => { if (!cancelled) setFull((data.data ?? []).filter((r) => r.order_id === order.id)) })
      .catch(() => {})
    return () => { cancelled = true }
  }, [key, order.id, authHeaders])

  if ((order.label_requests ?? []).length === 0) return null

  return (
    <div className="admin-seller-ship">
      <p><b>NexTech label requests</b></p>
      {(order.label_requests ?? []).filter((r) => r.status !== 'requested').map((r) => (
        <p key={r.id} className="muted">#{r.id} · {r.status === 'ready' ? `label uploaded ${r.handled_at ? new Date(r.handled_at).toLocaleDateString() : ''}${r.order_package_id ? ' · shipped' : ' · seller hasn’t shipped yet'}` : `cancelled — ${r.admin_note ?? ''}`}
          {r.has_label_file && <> · <button type="button" className="link" onClick={() => openFile(`${API_URL}/admin/label-requests/${r.id}/label`, authHeaders)}>View label</button></>}
          {r.status === 'ready' && <> · <label className="link admin-file-link">Replace with my PDF<input type="file" accept="application/pdf,image/png,image/jpeg" hidden onChange={async (e) => { const f = e.target.files?.[0]; e.target.value = ''; if (!f) return; try { await replaceLabel(r, authHeaders, f); onChanged?.('Label replaced — the seller downloads the new one.') } catch (err) { onChanged?.(err.message) } }} /></label></>}</p>
      ))}
      {(full ?? []).map((r) => <RequestCard key={r.id} request={r} authHeaders={authHeaders} onDone={(_, msg) => onChanged?.(msg)} />)}
    </div>
  )
}

const SIZE_LABELS = { '4x6': '4×6 in (thermal printer)', a6: 'A6', a4: 'A4 sheet' }
const EMPTY_TEMPLATE = { name: '', size: '4x6', header_text: 'NexTech Shipping', logo_url: '', footer_note: '', show_items: true, show_phone: false, is_default: false, is_active: true }

// Settings → Shipping label templates: what seller labels are generated from.
export function LabelTemplates({ authHeaders, onMessage }) {
  const [templates, setTemplates] = useState(null)
  const [form, setForm] = useState(null)

  const load = useCallback(() => {
    fetch(`${API_URL}/admin/label-templates`, { headers: authHeaders() })
      .then(readJson)
      .then((data) => setTemplates(data.data ?? []))
      .catch(() => setTemplates([]))
  }, [authHeaders])
  useEffect(() => { Promise.resolve().then(load) }, [load])

  async function save(event) {
    event.preventDefault()
    const { id, ...body } = form
    const response = await fetch(`${API_URL}/admin/label-templates${id ? `/${id}` : ''}`, { method: id ? 'PUT' : 'POST', headers: { ...authHeaders(), 'Content-Type': 'application/json' }, body: JSON.stringify({ ...body, logo_url: body.logo_url?.trim() || null }) })
    const data = await readJson(response)
    if (!response.ok) { onMessage?.(data.message ?? Object.values(data.errors ?? {})[0]?.[0] ?? 'Could not save the template.'); return }
    setForm(null)
    onMessage?.('Label template saved.')
    load()
  }

  async function remove(t) {
    if (!window.confirm(`Delete the "${t.name}" label template?`)) return
    const response = await fetch(`${API_URL}/admin/label-templates/${t.id}`, { method: 'DELETE', headers: authHeaders() })
    if (!response.ok) { onMessage?.((await readJson(response)).message ?? 'Could not delete the template.'); return }
    load()
  }

  return (
    <div className="admin-form">
      <h3>Shipping label templates</h3>
      <p className="muted">When a seller on &ldquo;I ship, NexTech label&rdquo; asks for a label, it&rsquo;s generated instantly from the default template with the order&rsquo;s addresses filled in. Sellers can switch template any time; you can still replace any label with your own PDF from the order. With no active template, requests wait for you to upload one.</p>
      {templates === null ? <p className="muted">Loading…</p> : (
        <table className="admin-table">
          <thead><tr><th>Template</th><th>Size</th><th>Status</th><th></th></tr></thead>
          <tbody>
            {templates.map((t) => (
              <tr key={t.id}>
                <td>{t.name}{t.is_default && <span className="pill pill-active" style={{ marginLeft: 6 }}>Default</span>}</td>
                <td>{SIZE_LABELS[t.size] ?? t.size}</td>
                <td>{t.is_active ? 'Active' : 'Off'}</td>
                <td className="admin-row-actions">
                  <button type="button" className="link" onClick={() => openFile(`${API_URL}/admin/label-templates/${t.id}/preview`, authHeaders)}>Preview</button>
                  <button type="button" className="link" onClick={() => setForm({ ...EMPTY_TEMPLATE, ...t, logo_url: t.logo_url ?? '', header_text: t.header_text ?? '', footer_note: t.footer_note ?? '' })}>Edit</button>
                  <button type="button" className="link" onClick={() => remove(t)}>Delete</button>
                </td>
              </tr>
            ))}
          </tbody>
        </table>
      )}
      {form ? (
        <form className="admin-form-grid" onSubmit={save} style={{ marginTop: 12 }}>
          <label>Name<input required maxLength="80" value={form.name} onChange={(e) => setForm({ ...form, name: e.target.value })} /></label>
          <label>Size<select value={form.size} onChange={(e) => setForm({ ...form, size: e.target.value })}>{Object.entries(SIZE_LABELS).map(([v, l]) => <option key={v} value={v}>{l}</option>)}</select></label>
          <label>Header text (shown when there&rsquo;s no logo)<input maxLength="80" value={form.header_text} onChange={(e) => setForm({ ...form, header_text: e.target.value })} /></label>
          <label>Logo URL (blank = store logo; PNG/JPG upload)<input maxLength="500" value={form.logo_url} onChange={(e) => setForm({ ...form, logo_url: e.target.value })} /></label>
          <label>Footer note<input maxLength="300" value={form.footer_note} onChange={(e) => setForm({ ...form, footer_note: e.target.value })} /></label>
          <label className="admin-check"><input type="checkbox" checked={form.show_items} onChange={(e) => setForm({ ...form, show_items: e.target.checked })} /> List package contents</label>
          <label className="admin-check"><input type="checkbox" checked={form.show_phone} onChange={(e) => setForm({ ...form, show_phone: e.target.checked })} /> Print customer phone</label>
          <label className="admin-check"><input type="checkbox" checked={form.is_active} onChange={(e) => setForm({ ...form, is_active: e.target.checked })} /> Active (sellers can pick it)</label>
          <label className="admin-check"><input type="checkbox" checked={form.is_default} onChange={(e) => setForm({ ...form, is_default: e.target.checked })} /> Default template</label>
          <div className="admin-label-actions"><button className="act" type="submit">Save template</button><button type="button" className="link" onClick={() => setForm(null)}>Cancel</button></div>
        </form>
      ) : <button type="button" className="act" style={{ marginTop: 10 }} onClick={() => setForm({ ...EMPTY_TEMPLATE })}>Add template</button>}
    </div>
  )
}
