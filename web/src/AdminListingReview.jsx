import { useCallback, useEffect, useState } from 'react'
import { mediaUrl } from './mediaUrl'
import { formatMoney } from './money'
import { DecorationView } from './StoreDecorationView'

// Admin review of seller listings: everything a seller entered in the Add
// product flow (details, variations, compliance documents, price links),
// sales boost offers for "Low traffic" products, and the trademarks sellers
// register under Account health.

const API_URL = import.meta.env.VITE_API_URL ?? 'http://127.0.0.1:8000/api'

async function readJson(response) {
  const text = await response.text()
  try { return text ? JSON.parse(text) : {} } catch { return {} }
}

const labelOf = (key) => key.replace(/_/g, ' ').replace(/^./, (c) => c.toUpperCase())

export function ListingReview({ product, currency, authHeaders, jsonHeaders, viewDocument, onClose, onAction, busy, fail }) {
  const [offers, setOffers] = useState([])
  const [prices, setPrices] = useState({})
  const money = (cents) => formatMoney(cents, currency)
  const loadOffers = useCallback(() => {
    fetch(`${API_URL}/admin/products/${product.id}/sales-boost`, { headers: authHeaders() }).then(readJson).then((d) => setOffers(d.data ?? [])).catch(() => {})
  }, [product.id, authHeaders])
  useEffect(() => { Promise.resolve().then(loadOffers) }, [loadOffers])

  const variants = (product.variants ?? []).filter((v) => v.is_active)
  const targets = variants.length ? variants.map((v) => ({ key: v.id, label: v.label, price: v.price_cents })) : [{ key: '', label: 'Whole product', price: product.price_cents }]

  async function sendOffers() {
    const rows = targets.filter((t) => String(prices[t.key] ?? '').trim() !== '').map((t) => ({ variant_id: t.key || null, recommended_price_cents: Math.round(Number(prices[t.key]) * 100) }))
    if (!rows.length) return
    try {
      const response = await fetch(`${API_URL}/admin/products/${product.id}/sales-boost`, { method: 'POST', headers: jsonHeaders(), body: JSON.stringify({ offers: rows }) })
      const data = await readJson(response)
      if (!response.ok) throw new Error(data.message ?? Object.values(data.errors ?? {})[0]?.[0] ?? 'Could not send the offer.')
      setOffers(data.data)
      setPrices({})
    } catch (error) { fail(error) }
  }

  const docs = product.compliance?.documents ?? []
  return (
    <div className="admin-drawer" role="presentation" onClick={onClose}>
      <aside onClick={(event) => event.stopPropagation()}>
        <button className="admin-close" type="button" onClick={onClose}>Close</button>
        <h3>{product.name}</h3>
        <p className="muted">{product.shop?.name} · {product.category?.name ?? 'No category'} · <span className={`pill pill-${product.status}`}>{product.status}</span>{product.trademark?.name ? ` · Brand: ${product.trademark.name}` : ''}</p>

        {(product.missing_compliance ?? []).length > 0 && <p className="admin-cash-holding overdue">Compliance documents missing: {product.missing_compliance.join(', ')} — it can’t be approved until the seller uploads them.</p>}
        {product.shop_id && product.status === 'pending' && (
          <div className="admin-form-actions">
            <button className="act" type="button" disabled={busy || (product.missing_compliance ?? []).length > 0} onClick={() => onAction('approve')}>Approve after price assessment</button>
            <button className="act danger" type="button" disabled={busy} onClick={() => onAction('reject')}>Reject</button>
          </div>
        )}

        <h4>Description</h4>
        {(product.bullet_points ?? []).length > 0 && <ul className="admin-bullets">{product.bullet_points.map((b) => <li key={b}>{b}</li>)}</ul>}
        <p className="muted">{product.description || '—'}</p>
        <div className="admin-thumbs">{(product.images ?? []).map((i) => <img key={i.id ?? i.url} src={mediaUrl(i.url)} alt="" />)}</div>
        {product.video_url && <p className="muted">Product video: <a href={mediaUrl(product.video_url)} target="_blank" rel="noreferrer">open</a></p>}
        {product.detail_video_url && <p className="muted">Detail video: <a href={mediaUrl(product.detail_video_url)} target="_blank" rel="noreferrer">open</a></p>}
        {(product.detail_images ?? []).length > 0 && <div className="admin-thumbs">{product.detail_images.map((u) => <img key={u} src={mediaUrl(u)} alt="" />)}</div>}

        <h4>Product details</h4>
        <dl className="admin-dl">
          {Object.entries(product.product_details ?? {}).filter(([, v]) => v !== '' && v != null && !(Array.isArray(v) && !v.length)).map(([k, v]) => <div key={k}><dt>{labelOf(k)}</dt><dd>{[].concat(v).join(', ')}</dd></div>)}
          <div><dt>Country of origin</dt><dd>{product.country_of_origin || '—'}</dd></div>
          {product.handling_days && <div><dt>Handling time</dt><dd>{product.handling_days} day(s)</dd></div>}
        </dl>

        <h4>Pricing {product.variation_theme?.length ? `· varies by ${product.variation_theme.join(' × ')}` : ''}</h4>
        <table className="admin-table">
          <thead><tr><th>SKU</th><th>Price</th><th>Stock</th><th>Weight / size</th></tr></thead>
          <tbody>
            {variants.length ? variants.map((v) => <tr key={v.id}><td>{v.label}<span className="admin-note">{v.sku}{v.seller_code ? ` · ${v.seller_code}` : ''}</span></td><td>{money(v.price_cents)}</td><td>{v.inventory_quantity}</td><td>{[v.weight_grams && `${v.weight_grams} g`, v.length_mm && `${v.length_mm}×${v.width_mm ?? '?'}×${v.height_mm ?? '?'} mm`].filter(Boolean).join(' · ') || '—'}</td></tr>)
              : <tr><td>{product.sku}</td><td>{money(product.price_cents)}</td><td>{product.inventory_quantity}</td><td>—</td></tr>}
          </tbody>
        </table>
        {(product.price_references ?? []).length > 0 && <p className="muted">Same product elsewhere: {product.price_references.map((u) => <a key={u} href={u} target="_blank" rel="noreferrer noopener">{u}</a>).reduce((acc, el) => (acc.length ? [...acc, ' · ', el] : [el]), [])}</p>}

        <h4>Compliance documents</h4>
        {docs.length ? <div className="admin-form-actions">{docs.map((d) => <button key={d.path} className="act ghost" type="button" onClick={() => viewDocument(d.path)}>{labelOf(d.type)}{d.name ? ` — ${d.name}` : ''}</button>)}</div> : <p className="muted">None uploaded.</p>}

        {product.shop_id && (
          <>
            <h4>Sales boost {offers.some((o) => o.status === 'pending') && <span className="pill pill-rejected">Low traffic</span>}</h4>
            <p className="muted">Offer the seller a lower recommended price when this product’s pricing gives it little traffic. It ranks lower until the seller accepts or rejects (rejecting closes that variation).</p>
            <table className="admin-table">
              <thead><tr><th>Variation</th><th>Current</th><th>Recommended</th></tr></thead>
              <tbody>{targets.map((t) => <tr key={t.key}><td>{t.label}</td><td>{money(t.price)}</td><td><input type="number" min="0" step="0.01" placeholder="leave empty to skip" value={prices[t.key] ?? ''} onChange={(e) => setPrices({ ...prices, [t.key]: e.target.value })} /></td></tr>)}</tbody>
            </table>
            <div className="admin-form-actions"><button className="act" type="button" onClick={sendOffers}>Send sales boost offer</button></div>
            {offers.length > 0 && <div className="admin-gift-issued">{offers.map((o) => <p key={o.id}>{(variants.find((v) => v.id === o.product_variant_id)?.label) ?? 'Whole product'}: {money(o.current_price_cents)} → <b>{money(o.recommended_price_cents)}</b> · {o.status} · {new Date(o.created_at).toLocaleDateString()}</p>)}</div>}
          </>
        )}
      </aside>
    </div>
  )
}

export function TrademarkReview({ authHeaders, jsonHeaders, viewDocument, fail, market }) {
  const [rows, setRows] = useState(null)
  const [status, setStatus] = useState('pending')
  const load = useCallback(() => {
    fetch(`${API_URL}/admin/trademarks?status=${status}`, { headers: { ...authHeaders(), ...(market ? { 'X-Market': market } : {}) } }).then(readJson).then((d) => setRows(d.data ?? [])).catch(() => setRows([]))
  }, [authHeaders, status, market])
  useEffect(() => { Promise.resolve().then(load) }, [load])

  async function decide(t, decision) {
    const note = decision === 'reject' ? window.prompt(`Why can’t "${t.name}" be approved? The seller sees this.`, '') : null
    if (decision === 'reject' && !note) return
    try {
      const response = await fetch(`${API_URL}/admin/trademarks/${t.id}/review`, { method: 'POST', headers: jsonHeaders(), body: JSON.stringify({ decision, note }) })
      const data = await readJson(response)
      if (!response.ok) throw new Error(data.message ?? 'Could not update the trademark.')
      load()
    } catch (error) { fail(error) }
  }

  if (!rows) return null
  if (!rows.length && status === 'pending') return null
  return (
    <div className="admin-form">
      <h3>Trademarks {status === 'pending' ? 'to review' : ''} <select value={status} onChange={(e) => setStatus(e.target.value)}><option value="pending">Pending</option><option value="approved">Approved</option><option value="rejected">Rejected</option><option value="all">All</option></select></h3>
      <table className="admin-table">
        <thead><tr><th>Trademark</th><th>Shop</th><th>Registration</th><th>Status</th><th></th></tr></thead>
        <tbody>{rows.map((t) => (
          <tr key={t.id}>
            <td>{t.logo_url && <img className="admin-tm-logo" src={mediaUrl(t.logo_url)} alt="" />}{t.name}</td>
            <td>{t.shop?.name}</td>
            <td>{t.registration_number} · {t.registration_country}</td>
            <td><span className={`pill pill-${t.status === 'approved' ? 'approved' : t.status === 'rejected' ? 'rejected' : 'pending'}`}>{t.status}</span>{t.note && <span className="admin-note">{t.note}</span>}</td>
            <td className="admin-actions">
              {t.certificate_path && <button className="act ghost" type="button" onClick={() => viewDocument(t.certificate_path)}>Certificate</button>}
              {t.status !== 'approved' && <button className="act" type="button" onClick={() => decide(t, 'approve')}>Approve</button>}
              {t.status !== 'rejected' && <button className="act danger" type="button" onClick={() => decide(t, 'reject')}>Reject</button>}
            </td>
          </tr>
        ))}</tbody>
      </table>
    </div>
  )
}

// Store decoration spot checks: versions picked for review, previewed as shoppers would see them.
export function DecorationReview({ authHeaders, jsonHeaders, fail }) {
  const [rows, setRows] = useState(null)
  const [status, setStatus] = useState('in_review')
  const [open, setOpen] = useState(null)
  const load = useCallback(() => {
    fetch(`${API_URL}/admin/decorations?status=${status}`, { headers: authHeaders() }).then(readJson).then((d) => setRows(d.data ?? [])).catch(() => setRows([]))
  }, [authHeaders, status])
  useEffect(() => { Promise.resolve().then(load) }, [load])

  async function decide(d, decision) {
    const note = decision === 'reject' ? window.prompt(`What must ${d.shop.name} change in “${d.name}”? They see this.`, '') : null
    if (decision === 'reject' && !note) return
    try {
      const response = await fetch(`${API_URL}/admin/decorations/${d.id}/review`, { method: 'POST', headers: jsonHeaders(), body: JSON.stringify({ decision, note }) })
      const data = await readJson(response)
      if (!response.ok) throw new Error(data.message ?? 'Could not update the store decoration.')
      setOpen(null)
      load()
    } catch (error) { fail(error) }
  }

  if (!rows || (!rows.length && status === 'in_review')) return null
  return (
    <div className="admin-form">
      <h3>Store decorations {status === 'in_review' ? 'to spot-check' : ''} <select value={status} onChange={(e) => setStatus(e.target.value)}><option value="in_review">In review</option><option value="live">Live</option><option value="rejected">Rejected</option><option value="all">All</option></select></h3>
      <table className="admin-table">
        <thead><tr><th>Shop</th><th>Version</th><th>Platform</th><th>Submitted</th><th></th></tr></thead>
        <tbody>{rows.map((d) => (
          <tr key={d.id}>
            <td>{d.shop.name}</td>
            <td>{d.name} {d.is_live && <span className="pill pill-approved">live</span>}{d.review_note && <span className="admin-note">{d.review_note}</span>}</td>
            <td>{d.platform}</td>
            <td>{d.submitted_at ? new Date(d.submitted_at).toLocaleString() : '—'}</td>
            <td className="admin-actions"><button className="act ghost" type="button" onClick={() => setOpen(d)}>Preview</button></td>
          </tr>
        ))}</tbody>
      </table>
      {open && (
        <div className="admin-drawer" role="presentation" onClick={() => setOpen(null)}>
          <aside className="admin-deco-drawer" onClick={(e) => e.stopPropagation()}>
            <button className="admin-close" type="button" onClick={() => setOpen(null)}>Close</button>
            <h3>{open.shop.name} — {open.name} ({open.platform})</h3>
            <p className="muted">Check that images, videos and text are lawful, the seller’s own, and follow the Seller Rules.</p>
            <div className="admin-form-actions">
              {open.status !== 'approved' && <button className="act" type="button" onClick={() => decide(open, 'approve')}>Approve</button>}
              <button className="act danger" type="button" onClick={() => decide(open, 'reject')}>{open.is_live ? 'Take down' : 'Reject'}</button>
            </div>
            <DecorationView design={open.preview} platform={open.platform} shopName={open.shop.name} />
          </aside>
        </div>
      )}
    </div>
  )
}
