import { useEffect, useState } from 'react'
import { mediaUrl } from './mediaUrl'
import { checkProductImage } from './productImageCheck'
import { currencySymbol } from './money'

// Seller Center -> Add products, step by step like Temu's Add product flow:
// Getting started (name + category), 01 Product description, 02 Product
// details, 03 Variations & SKUs, 04 Fulfillment, 05 Safety & compliance.
// A listing can be saved as a draft at any step (Manage products ->
// Incomplete); Submit sends it to NexTech for review.

const API_URL = import.meta.env.VITE_API_URL ?? 'http://127.0.0.1:8000/api'

async function readJson(response) {
  const text = await response.text()
  try { return text ? JSON.parse(text) : {} } catch { return {} }
}

async function uploadFile(headers, path, file, extra = {}) {
  const body = new FormData()
  body.append('file', file)
  Object.entries(extra).forEach(([k, v]) => body.append(k, v))
  const response = await fetch(`${API_URL}${path}`, { method: 'POST', headers: headers(), body })
  const data = await readJson(response)
  if (!response.ok) throw new Error(data.message ?? Object.values(data.errors ?? {})[0]?.[0] ?? 'Upload failed.')
  return data.data
}

// Temu's video rules: up to 100 MB, 3 minutes, at least 720p.
function checkVideo(file) {
  return new Promise((resolve) => {
    if (!['video/mp4', 'video/webm', 'video/quicktime'].includes(file.type)) { resolve('Videos must be MP4, WebM or MOV.'); return }
    if (file.size > 100 * 1048576) { resolve('Videos must be 100 MB or smaller.'); return }
    const url = URL.createObjectURL(file)
    const video = document.createElement('video')
    video.preload = 'metadata'
    video.onloadedmetadata = () => {
      URL.revokeObjectURL(url)
      if (video.duration > 180) resolve(`Videos can be at most 3 minutes long (this one is ${Math.round(video.duration)} s).`)
      else if (Math.min(video.videoWidth, video.videoHeight) < 720) resolve(`Videos need at least 720p resolution (this one is ${video.videoWidth}×${video.videoHeight}).`)
      else resolve(null)
    }
    video.onerror = () => { URL.revokeObjectURL(url); resolve(null) } // can't read it here — the server still checks type and size
    video.src = url
  })
}

const STEPS = ['Getting started', 'Product description', 'Product details', 'Variations & SKUs', 'Fulfillment', 'Safety & compliance']
const cents = (v) => (String(v ?? '').trim() === '' ? null : Math.round(Number(v) * 100))
const units = (c) => (c == null ? '' : (c / 100).toFixed(2))
const intOrNull = (v) => (String(v ?? '').trim() === '' ? null : Math.max(0, Math.round(Number(v))))
const comboKey = (options, theme) => theme.map((t) => String(options?.[t] ?? '').trim().toLowerCase()).join('|')

// Every combination of the chosen variation values (at most two levels).
function combos(theme, values) {
  if (!theme.length) return []
  const [a, b] = theme
  const first = (values[a] ?? []).filter(Boolean)
  if (!b) return first.map((v) => ({ [a]: v }))
  const second = (values[b] ?? []).filter(Boolean)
  return first.flatMap((v) => second.map((w) => ({ [a]: v, [b]: w })))
}

// Is a conditional field (or document) in play, given the details so far?
const applies = (field, details) => Object.entries(field.when ?? {}).every(([key, vals]) => [].concat(details?.[key] ?? []).some((v) => vals.map(String).includes(String(v))))

function formFrom(product, config) {
  const p = product ?? {}
  const theme = p.variation_theme ?? []
  const variants = (p.variants ?? []).map((v) => ({
    id: v.id, options: v.options ?? (theme[0] ? { [theme[0]]: v.label } : {}), label: v.label, price: units(v.price_cents), compare_at: units(v.compare_at_price_cents),
    stock: v.inventory_quantity ?? 0, image_url: v.image_url ?? '', seller_code: v.seller_code ?? '', weight: v.weight_grams ?? '', length: v.length_mm ?? '', width: v.width_mm ?? '', height: v.height_mm ?? '', is_active: v.is_active !== false,
  }))
  const values = Object.fromEntries(theme.map((t) => [t, [...new Set(variants.map((v) => v.options?.[t]).filter(Boolean))]]))
  return {
    id: p.id ?? null,
    status: p.status ?? null,
    name: p.name ?? '',
    category_id: p.category_id ? String(p.category_id) : '',
    suggested_category_name: p.suggested_category_name ?? '',
    seller_code: p.seller_code ?? '',
    description: p.description ?? '',
    bullet_points: [...(p.bullet_points ?? []), '', '', ''].slice(0, Math.max(3, (p.bullet_points ?? []).length)),
    images: (p.images ?? []).map((i) => i.url ?? i).filter(Boolean),
    video_url: p.video_url ?? '',
    detail_video_url: p.detail_video_url ?? '',
    detail_images: p.detail_images ?? [],
    trademark_id: p.trademark_id ? String(p.trademark_id) : '',
    product_details: p.product_details ?? {},
    has_variations: variants.length > 0,
    variation_theme: theme,
    variation_values: values,
    variants,
    removed_ids: [],
    price: units(p.price_cents || null),
    compare_at: units(p.compare_at_price_cents),
    stock: p.inventory_quantity ?? 0,
    price_references: [...(p.price_references ?? []), ''].slice(0, Math.max(1, (p.price_references ?? []).length)),
    size_chart: p.size_chart ?? { size_family: Object.keys(config?.size_families ?? {})[0] ?? '', sub_size_family: config?.sub_size_families?.[0] ?? '', rows: [] },
    handling_days: p.handling_days ? String(p.handling_days) : '',
    shipping_template_id: p.shipping_template_id ? String(p.shipping_template_id) : '',
    return_days: p.return_days ?? '',
    country_of_origin: p.country_of_origin ?? '',
    hsn_code: p.hsn_code ?? '',
    gst_rate_bps: p.gst_rate_bps ?? '',
    manufacturer_info: p.manufacturer_info ?? '',
    documents: p.compliance?.documents ?? [],
  }
}

const COUNTRY_NAMES = (() => {
  try {
    const names = new Intl.DisplayNames(['en'], { type: 'region' })
    const out = []
    for (let a = 65; a <= 90; a += 1) for (let b = 65; b <= 90; b += 1) {
      const code = String.fromCharCode(a, b)
      const name = names.of(code)
      if (name && name !== code && !/^(X|Q)/.test(code) && !['EU', 'EZ', 'UN', 'ZZ'].includes(code)) out.push(name)
    }
    return [...new Set(out)].sort()
  } catch { return ['China', 'India', 'United States'] }
})()

export function ProductWizard({ headers, product, onSaved, onCancel, go, inclusive, gstRates, shipTemplates, maxReturnDays, defaultReturnDays }) {
  const [config, setConfig] = useState(null)
  const [form, setForm] = useState(null)
  const [step, setStep] = useState(product?.id ? 1 : 0)
  const [msg, setMsg] = useState('')
  const [errors, setErrors] = useState({})
  const [busy, setBusy] = useState('')
  const [search, setSearch] = useState('')
  const [valueDraft, setValueDraft] = useState({})
  const [done, setDone] = useState(null)
  const sym = currencySymbol()

  useEffect(() => {
    let cancelled = false
    fetch(`${API_URL}/seller/catalog-config`, { headers: headers() }).then(readJson)
      // A late (or repeated) response must never wipe what the seller has typed.
      .then((d) => { if (!cancelled) { setConfig(d.data); setForm((f) => f ?? formFrom(product, d.data)) } })
      .catch(() => { if (!cancelled) setMsg('Could not load the product form. Reload and try again.') })
    return () => { cancelled = true }
  }, [headers, product])

  if (!config || !form) return <div className="sc-card"><p className="sc-muted">{msg || 'Loading…'}</p></div>

  const category = config.categories.find((c) => String(c.id) === String(form.category_id)) ?? null
  const lowerName = form.name.trim().toLowerCase()
  // Recommended categories: ones whose keywords appear in the product name.
  const recommended = lowerName ? config.categories.filter((c) => c.keywords.some((k) => lowerName.includes(k))).slice(0, 5) : []

  const set = (patch) => setForm((f) => ({ ...f, ...patch }))
  const setDetail = (key, value) => setForm((f) => ({ ...f, product_details: { ...f.product_details, [key]: value } }))
  const apparel = !!category?.apparel
  const theme = apparel && form.has_variations ? ['Color', 'Size'] : form.variation_theme
  const liveVariants = form.variants
  const err = (key) => errors[key] && <small className="wz-err">{errors[key]}</small>

  async function upload(kind, file, apply) {
    if (!file) return
    setMsg('')
    try {
      if (kind === 'image') {
        const problem = await checkProductImage(file)
        if (problem) { setMsg(problem); return }
        setBusy('image')
        apply((await uploadFile(headers, '/seller/product-media', file)).url)
      } else if (kind === 'video') {
        const problem = await checkVideo(file)
        if (problem) { setMsg(problem); return }
        setBusy('video')
        apply((await uploadFile(headers, '/seller/product-video', file)).url)
      } else {
        setBusy('doc')
        const d = await uploadFile(headers, '/seller/kyc-document', file, { kind: 'product_document' })
        apply({ path: d.path, name: file.name })
      }
    } catch (e) { setMsg(e.message) } finally { setBusy('') }
  }

  // Rebuild the SKU list from the variation values, keeping what's already typed in.
  function regenerate(nextTheme, nextValues) {
    setForm((f) => {
      const byKey = new Map(f.variants.map((v) => [comboKey(v.options, nextTheme), v]))
      const blank = { price: f.price || '', compare_at: '', stock: 0, image_url: '', seller_code: '', weight: '', length: '', width: '', height: '', is_active: true }
      const next = combos(nextTheme, nextValues).map((options) => ({ ...(byKey.get(comboKey(options, nextTheme)) ?? blank), options }))
      const kept = new Set(next.map((v) => v.id).filter(Boolean))
      const removed = [...f.removed_ids, ...f.variants.filter((v) => v.id && !kept.has(v.id)).map((v) => v.id)]
      return { ...f, variation_theme: nextTheme, variation_values: nextValues, variants: next, removed_ids: [...new Set(removed)] }
    })
  }

  function addValue(type) {
    const value = (valueDraft[type] ?? '').trim()
    if (!value) return
    const current = form.variation_values[type] ?? []
    if (current.some((v) => v.toLowerCase() === value.toLowerCase())) return
    setValueDraft((d) => ({ ...d, [type]: '' }))
    regenerate(theme, { ...form.variation_values, [type]: [...current, value] })
  }

  function payload(submit) {
    const variants = form.has_variations
      ? [
          ...liveVariants.map((v, i) => ({
            ...(v.id ? { id: v.id } : {}),
            label: theme.map((t) => v.options?.[t]).filter(Boolean).join(' / ') || `Option ${i + 1}`,
            options: Object.fromEntries(theme.map((t) => [t, v.options?.[t] ?? ''])),
            price_cents: cents(v.price) ?? 0,
            compare_at_price_cents: cents(v.compare_at),
            inventory_quantity: Number(v.stock) || 0,
            image_url: v.image_url || null,
            seller_code: v.seller_code?.trim() || null,
            weight_grams: intOrNull(v.weight), length_mm: intOrNull(v.length), width_mm: intOrNull(v.width), height_mm: intOrNull(v.height),
            is_active: v.is_active !== false,
            sort_order: i,
          })),
          ...form.removed_ids.map((id) => ({ id, _delete: true, label: 'x', price_cents: 0 })),
        ]
      : form.variants.filter((v) => v.id).map((v) => ({ id: v.id, _delete: true, label: 'x', price_cents: 0 }))
    return {
      submit,
      name: form.name.trim(),
      category_id: form.category_id ? Number(form.category_id) : null,
      suggested_category_name: form.suggested_category_name.trim() || null,
      seller_code: form.seller_code.trim() || null,
      description: form.description,
      bullet_points: form.bullet_points,
      images: form.images,
      video_url: form.video_url || null,
      detail_video_url: form.detail_video_url || null,
      detail_images: form.detail_images,
      trademark_id: form.trademark_id ? Number(form.trademark_id) : null,
      product_details: form.product_details,
      variation_theme: form.has_variations ? theme : null,
      variants,
      price_cents: form.has_variations ? (Math.min(...liveVariants.map((v) => cents(v.price) ?? Infinity)) || 0) : (cents(form.price) ?? 0),
      compare_at_price_cents: form.has_variations ? null : cents(form.compare_at),
      inventory_quantity: form.has_variations ? 0 : Number(form.stock) || 0,
      price_references: form.price_references,
      size_chart: apparel ? form.size_chart : null,
      handling_days: form.handling_days ? Number(form.handling_days) : null,
      shipping_template_id: form.shipping_template_id ? Number(form.shipping_template_id) : null,
      return_days: String(form.return_days).trim() === '' ? null : Number(form.return_days),
      country_of_origin: form.country_of_origin || null,
      compliance: { documents: form.documents },
      ...(inclusive ? { hsn_code: form.hsn_code || null, gst_rate_bps: form.gst_rate_bps === '' ? null : Number(form.gst_rate_bps), manufacturer_info: form.manufacturer_info || null } : {}),
    }
  }

  async function save(submit) {
    if (!form.name.trim()) { setStep(0); setErrors({ name: 'Enter a product name.' }); return }
    setBusy('save')
    setMsg('')
    setErrors({})
    try {
      const response = await fetch(`${API_URL}/seller/products${form.id ? `/${form.id}` : ''}`, { method: form.id ? 'PATCH' : 'POST', headers: { ...headers(), 'Content-Type': 'application/json' }, body: JSON.stringify(payload(submit)) })
      const data = await readJson(response)
      if (!response.ok) {
        const fieldErrors = Object.fromEntries(Object.entries(data.errors ?? {}).map(([k, v]) => [k, v[0]]))
        setErrors(fieldErrors)
        // Jump to the first step that has a problem.
        const keys = Object.keys(fieldErrors)
        const stepOf = (k) => (['name', 'category_id'].includes(k) ? 0 : ['description', 'images', 'bullet_points', 'trademark_id'].some((p) => k.startsWith(p)) ? 1 : k.startsWith('product_details') ? 2 : ['variants', 'variation_theme', 'price_cents', 'size_chart', 'price_references'].some((p) => k.startsWith(p)) ? 3 : ['handling_days', 'shipping_template_id', 'return_days'].includes(k) ? 4 : 5)
        if (keys.length) setStep(Math.min(...keys.map(stepOf)))
        throw new Error(keys.length ? `Fix ${keys.length} thing${keys.length === 1 ? '' : 's'} before submitting — they’re marked in red.` : (data.message ?? 'Could not save the product.'))
      }
      setForm(formFrom(data.data, config))
      if (submit) setDone(data.data)
      else setMsg('Draft saved — find it under Manage products → Incomplete.')
      onSaved?.(data.data)
    } catch (e) { setMsg(e.message) } finally { setBusy('') }
  }

  if (done) {
    return (
      <div className="sc-card wz-done">
        <div className="wz-done-icon" aria-hidden>✓</div>
        <h2 className="sc-h2">New product submitted successfully!</h2>
        <p>Check its progress under <b>Manage products</b>. It goes on sale once NexTech has assessed the price and approved the listing.</p>
        {done.missing_compliance?.length > 0 && <p className="sc-alert warn">Compliance documents are still needed before it can be listed: {done.missing_compliance.join(', ')}. Upload them under Products → Product compliance.</p>}
        <div className="ss-actions"><button type="button" className="seller-btn ghost" onClick={() => onCancel()}>Manage products</button><button type="button" className="sc-primary" onClick={() => { setDone(null); setForm(formFrom(null, config)); setStep(0) }}>Add another product</button></div>
      </div>
    )
  }

  const attrs = category?.attributes ?? []
  const docs = (category?.compliance ?? []).filter((d) => applies(d, form.product_details))
  const sizes = form.variation_values.Size ?? []

  return (
    <>
      <ol className="wz-steps">
        {STEPS.map((label, i) => <li key={label} className={i === step ? 'on' : i < step ? 'past' : ''}><button type="button" disabled={i > 0 && !form.name.trim()} onClick={() => setStep(i)}><span>{i === 0 ? '★' : `0${i}`}</span>{label}</button></li>)}
      </ol>
      {msg && <div className="sc-alert warn"><span>{msg}</span><button type="button" onClick={() => setMsg('')}>OK</button></div>}
      {form.status === 'rejected' && product?.rejection_reason && <div className="sc-alert danger"><span>Rejected: {product.rejection_reason}</span></div>}

      {step === 0 && (
        <div className="sc-card wz-card">
          <h2 className="sc-h2">Getting started: name &amp; category</h2>
          <p className="sc-muted">Pick the most relevant, accurate category so buyers can find your product.</p>
          <label>Product name<input value={form.name} maxLength="160" placeholder="e.g. Wireless earbuds with charging case" onChange={(e) => set({ name: e.target.value })} />{err('name')}</label>
          {recommended.length > 0 && <div className="wz-chips"><span className="sc-muted">Recommended categories:</span>{recommended.map((c) => <button type="button" key={c.id} className={String(c.id) === form.category_id ? 'on' : ''} onClick={() => set({ category_id: String(c.id) })}>{c.name}</button>)}</div>}
          {config.recent_category_ids?.length > 0 && <div className="wz-chips"><span className="sc-muted">Previously used:</span>{config.recent_category_ids.map((id) => config.categories.find((c) => c.id === id)).filter(Boolean).map((c) => <button type="button" key={c.id} className={String(c.id) === form.category_id ? 'on' : ''} onClick={() => set({ category_id: String(c.id) })}>{c.name}</button>)}</div>}
          <label>Search categories<input value={search} placeholder="Type to filter" onChange={(e) => setSearch(e.target.value)} /></label>
          <div className="wz-cat-list">
            {config.categories.filter((c) => !search.trim() || c.name.toLowerCase().includes(search.trim().toLowerCase()) || c.keywords.some((k) => k.includes(search.trim().toLowerCase()))).map((c) => (
              <label key={c.id} className={`wz-cat${String(c.id) === form.category_id ? ' on' : ''}`}><input type="radio" name="wz-category" checked={String(c.id) === form.category_id} onChange={() => set({ category_id: String(c.id) })} />{c.name}</label>
            ))}
          </div>
          {err('category_id')}
          <label>Nothing fits? Suggest a category <span className="sc-muted">(NexTech reviews it — it doesn’t create a category)</span><input value={form.suggested_category_name} onChange={(e) => set({ suggested_category_name: e.target.value })} placeholder="e.g. Drone accessories" /></label>
        </div>
      )}

      {step === 1 && (
        <div className="sc-card wz-card">
          <h2 className="sc-h2">01 Product description</h2>
          <p className="sc-muted">A detailed description helps buyers understand the product and decide to buy — it also counts for search results.</p>
          <label>Product name<input value={form.name} maxLength="160" onChange={(e) => set({ name: e.target.value })} /></label>
          <label>Description<textarea rows="5" maxLength="5000" value={form.description} onChange={(e) => set({ description: e.target.value })} />{err('description')}</label>
          <div className="wz-field">
            <span className="wz-label">Bullet points <small className="sc-muted">key selling points, shown near the top of the page</small></span>
            {form.bullet_points.map((b, i) => <input key={i} value={b} maxLength="500" placeholder={`Bullet point ${i + 1}`} onChange={(e) => set({ bullet_points: form.bullet_points.map((x, j) => (j === i ? e.target.value : x)) })} />)}
            {form.bullet_points.length < 6 && <button type="button" className="sc-link" onClick={() => set({ bullet_points: [...form.bullet_points, ''] })}>+ Add bullet point</button>}
          </div>
          <div className="wz-field">
            <span className="wz-label">Product images <small className="sc-muted">up to {config.max_images} · square (1:1), JPEG/PNG, 3 MB max · the first is the main image</small></span>
            <div className="seller-gallery">
              {form.images.map((url, i) => <div className="seller-gallery-item" key={url + i}><img src={mediaUrl(url)} alt="" /><button type="button" onClick={() => set({ images: form.images.filter((_, j) => j !== i) })}>&times;</button></div>)}
              {form.images.length < config.max_images && <label className="seller-gallery-add">{busy === 'image' ? '…' : '+ Add'}<input type="file" accept="image/jpeg,image/png" disabled={!!busy} onChange={(e) => { const f = e.target.files?.[0]; e.target.value = ''; upload('image', f, (url) => setForm((x) => ({ ...x, images: [...x.images, url] }))) }} /></label>}
            </div>
            {err('images')}
          </div>
          <div className="wz-two">
            <div className="wz-field">
              <span className="wz-label">Product video <small className="sc-muted">top of the product page · MP4, ≤100 MB, ≤3 min, ≥720p</small></span>
              {form.video_url ? <div className="wz-video"><video src={mediaUrl(form.video_url)} controls preload="metadata" /><button type="button" className="sc-link" onClick={() => set({ video_url: '' })}>Remove</button></div>
                : <input type="file" accept="video/mp4,video/webm,video/quicktime" disabled={!!busy} onChange={(e) => { const f = e.target.files?.[0]; e.target.value = ''; upload('video', f, (url) => set({ video_url: url })) }} />}
            </div>
            <div className="wz-field">
              <span className="wz-label">Detail video <small className="sc-muted">in the product details section</small></span>
              {form.detail_video_url ? <div className="wz-video"><video src={mediaUrl(form.detail_video_url)} controls preload="metadata" /><button type="button" className="sc-link" onClick={() => set({ detail_video_url: '' })}>Remove</button></div>
                : <input type="file" accept="video/mp4,video/webm,video/quicktime" disabled={!!busy} onChange={(e) => { const f = e.target.files?.[0]; e.target.value = ''; upload('video', f, (url) => set({ detail_video_url: url })) }} />}
            </div>
          </div>
          {busy === 'video' && <p className="sc-muted">Uploading video…</p>}
          <p className="sc-muted">Products with videos generally get more exposure.</p>
          <div className="wz-field">
            <span className="wz-label">Detail images <small className="sc-muted">shown in the product details section, below the detail video</small></span>
            <div className="seller-gallery">
              {form.detail_images.map((url, i) => <div className="seller-gallery-item" key={url + i}><img src={mediaUrl(url)} alt="" /><button type="button" onClick={() => set({ detail_images: form.detail_images.filter((_, j) => j !== i) })}>&times;</button></div>)}
              {form.detail_images.length < 20 && <label className="seller-gallery-add">{busy === 'image' ? '…' : '+ Add'}<input type="file" accept="image/jpeg,image/png" disabled={!!busy} onChange={(e) => { const f = e.target.files?.[0]; e.target.value = ''; upload('image', f, (url) => setForm((x) => ({ ...x, detail_images: [...x.detail_images, url] }))) }} /></label>}
            </div>
          </div>
          <label>Product identity — trademark
            <select value={form.trademark_id} onChange={(e) => set({ trademark_id: e.target.value })}>
              <option value="">No brand / unbranded</option>
              {config.trademarks.map((t) => <option key={t.id} value={t.id}>{t.name}</option>)}
            </select>
            {err('trademark_id')}
          </label>
          <p className="sc-muted">Select a trademark if the product is made by a specific brand — it improves the price assessment and search matching. No trademark yet? <button type="button" className="sc-link" onClick={() => go('account-health')}>Register one under Account health</button> and wait for NexTech to review it.</p>
          <label>Your product code (Contribution Goods, optional)<input value={form.seller_code} maxLength="60" onChange={(e) => set({ seller_code: e.target.value })} /></label>
        </div>
      )}

      {step === 2 && (
        <div className="sc-card wz-card">
          <h2 className="sc-h2">02 Product details</h2>
          <p className="sc-muted">Key attributes such as material and specifications — used for accurate classification and price assessment, to answer buyers’ questions, and for search ranking.</p>
          {!category ? <p className="ob-missing">Choose a category first (Getting started).</p> : (
            <div className="wz-grid">
              {attrs.map((f) => {
                const on = applies(f, form.product_details)
                if (f.when && !on) return null
                const value = form.product_details[f.key]
                return (
                  <div key={f.key} className={`wz-attr${f.when ? ' conditional' : ''}`}>
                    <span className="wz-label">{f.label}{f.unit ? ` (${f.unit})` : ''}{f.required && on && <b className="wz-req"> *</b>}</span>
                    {f.type === 'select' ? (
                      <select value={value ?? ''} onChange={(e) => setDetail(f.key, e.target.value)}><option value="">Select…</option>{f.options.map((o) => <option key={o} value={o}>{o}</option>)}</select>
                    ) : f.type === 'multiselect' ? (
                      <div className="wz-multi">{f.options.map((o) => <label key={o} className="sc-check"><input type="checkbox" checked={[].concat(value ?? []).includes(o)} onChange={(e) => setDetail(f.key, e.target.checked ? [...[].concat(value ?? []), o] : [].concat(value ?? []).filter((x) => x !== o))} />{o}</label>)}</div>
                    ) : (
                      <input type={f.type === 'number' ? 'number' : 'text'} step="any" value={value ?? ''} onChange={(e) => setDetail(f.key, e.target.value)} />
                    )}
                    {err(`product_details.${f.key}`)}
                  </div>
                )
              })}
            </div>
          )}
          <p className="sc-muted">Fields with an orange edge appear because of an earlier answer — e.g. battery details once a battery power source is chosen.</p>
        </div>
      )}

      {step === 3 && (
        <div className="sc-card wz-card">
          <h2 className="sc-h2">03 Variations &amp; SKUs</h2>
          <p className="sc-muted">Variations are versions of the same product (e.g. black or blue, 128 GB or 256 GB). Each SKU — the smallest selling unit — gets its own stock, price and image.</p>
          <label className="sc-check"><input type="checkbox" checked={form.has_variations} onChange={(e) => set({ has_variations: e.target.checked })} /> This product has variations</label>
          {!form.has_variations ? (
            <div className="wz-grid">
              <label>{inclusive ? `Base price (${sym}, incl. GST)` : `Base price (${sym})`}<input type="number" min="0" step="0.01" value={form.price} onChange={(e) => set({ price: e.target.value })} />{err('price_cents')}</label>
              <label>{inclusive ? `MRP (${sym})` : `Regular price (${sym}, optional)`}<input type="number" min="0" step="0.01" value={form.compare_at} onChange={(e) => set({ compare_at: e.target.value })} /></label>
              <label>Quantity in stock<input type="number" min="0" value={form.stock} onChange={(e) => set({ stock: e.target.value })} /></label>
            </div>
          ) : (
            <>
              {apparel ? <p className="sc-muted">Clothing varies by <b>Color × Size</b> only, and needs a size chart.</p> : (
                <div className="wz-field">
                  <span className="wz-label">Varies by <small className="sc-muted">up to {config.max_variation_levels} types</small></span>
                  <div className="wz-chips">{config.variation_types.map((t) => {
                    const on = theme.includes(t)
                    return <button type="button" key={t} className={on ? 'on' : ''} disabled={!on && theme.length >= config.max_variation_levels} onClick={() => regenerate(on ? theme.filter((x) => x !== t) : [...theme, t], form.variation_values)}>{t}</button>
                  })}</div>
                  {err('variation_theme')}
                </div>
              )}
              {apparel && (
                <div className="wz-grid">
                  <label>Size family<select value={form.size_chart.size_family} onChange={(e) => set({ size_chart: { ...form.size_chart, size_family: e.target.value } })}>{Object.keys(config.size_families).map((k) => <option key={k}>{k}</option>)}</select></label>
                  <label>Sub-size family<select value={form.size_chart.sub_size_family} onChange={(e) => set({ size_chart: { ...form.size_chart, sub_size_family: e.target.value } })}>{config.sub_size_families.map((k) => <option key={k}>{k}</option>)}</select></label>
                </div>
              )}
              {theme.map((type) => (
                <div className="wz-field" key={type}>
                  <span className="wz-label">{type} values</span>
                  <div className="wz-chips">
                    {(form.variation_values[type] ?? []).map((v) => <span key={v} className="wz-value">{v}<button type="button" aria-label={`Remove ${v}`} onClick={() => regenerate(theme, { ...form.variation_values, [type]: form.variation_values[type].filter((x) => x !== v) })}>&times;</button></span>)}
                    {apparel && type === 'Size'
                      ? <select value="" onChange={(e) => { if (e.target.value) { regenerate(theme, { ...form.variation_values, Size: [...(form.variation_values.Size ?? []), e.target.value] }) } }}><option value="">+ Add size</option>{(config.size_families[form.size_chart.size_family] ?? []).filter((s) => !(form.variation_values.Size ?? []).includes(s)).map((s) => <option key={s}>{s}</option>)}</select>
                      : <span className="wz-add"><input value={valueDraft[type] ?? ''} placeholder={`Add a ${type.toLowerCase()}`} onChange={(e) => setValueDraft((d) => ({ ...d, [type]: e.target.value }))} onKeyDown={(e) => { if (e.key === 'Enter') { e.preventDefault(); addValue(type) } }} /><button type="button" className="seller-btn ghost" onClick={() => addValue(type)}>Add</button></span>}
                  </div>
                </div>
              ))}
              {liveVariants.length > config.max_skus && <p className="ob-missing">A product can have at most {config.max_skus} SKUs.</p>}
              {liveVariants.length > 0 && (
                <div className="sc-table-wrap">
                  <table className="sc-table wz-skus">
                    <thead><tr><th>SKU</th><th>Image</th><th>Stock</th><th>Base price ({sym})</th><th>{inclusive ? 'MRP' : 'Regular'} ({sym})</th><th>Weight (g)</th><th>L × W × H (mm)</th><th>Your SKU code</th></tr></thead>
                    <tbody>
                      {liveVariants.map((v, i) => {
                        const setV = (patch) => setForm((f) => ({ ...f, variants: f.variants.map((x, j) => (j === i ? { ...x, ...patch } : x)) }))
                        return (
                          <tr key={comboKey(v.options, theme) || i}>
                            <td><b>{theme.map((t) => v.options?.[t]).join(' / ')}</b>{err(`variants.${i}.options`)}</td>
                            <td>{v.image_url ? <span className="wz-sku-img"><img src={mediaUrl(v.image_url)} alt="" /><button type="button" onClick={() => setV({ image_url: '' })}>&times;</button></span> : <label className="seller-gallery-add small">+<input type="file" accept="image/jpeg,image/png" disabled={!!busy} onChange={(e) => { const f = e.target.files?.[0]; e.target.value = ''; upload('image', f, (url) => setV({ image_url: url })) }} /></label>}</td>
                            <td><input type="number" min="0" value={v.stock} onChange={(e) => setV({ stock: e.target.value })} /></td>
                            <td><input type="number" min="0" step="0.01" value={v.price} onChange={(e) => setV({ price: e.target.value })} />{err(`variants.${i}.price_cents`)}</td>
                            <td><input type="number" min="0" step="0.01" value={v.compare_at} onChange={(e) => setV({ compare_at: e.target.value })} /></td>
                            <td><input type="number" min="0" value={v.weight} onChange={(e) => setV({ weight: e.target.value })} /></td>
                            <td className="wz-dims"><input type="number" min="0" value={v.length} onChange={(e) => setV({ length: e.target.value })} />×<input type="number" min="0" value={v.width} onChange={(e) => setV({ width: e.target.value })} />×<input type="number" min="0" value={v.height} onChange={(e) => setV({ height: e.target.value })} /></td>
                            <td><input value={v.seller_code} maxLength="60" onChange={(e) => setV({ seller_code: e.target.value })} /></td>
                          </tr>
                        )
                      })}
                    </tbody>
                  </table>
                </div>
              )}
              {liveVariants.length > 1 && <button type="button" className="sc-link" onClick={() => setForm((f) => ({ ...f, variants: f.variants.map((v) => ({ ...v, price: f.variants[0].price, stock: f.variants[0].stock })) }))}>Copy the first SKU’s price and stock to all</button>}
              {apparel && (
                <div className="wz-field">
                  <span className="wz-label">Size chart <small className="sc-muted">product (…-Product) and body (…-Body) measurements, in cm · set the US label size for each size</small></span>
                  <div className="sc-table-wrap">
                    <table className="sc-table">
                      <thead><tr><th>Size</th><th>US size</th>{config.size_chart_measurements.map((m) => <th key={m}>{m}</th>)}</tr></thead>
                      <tbody>{sizes.map((size) => {
                        const row = form.size_chart.rows.find((r) => r.size === size) ?? { size, local_size: '', measurements: {} }
                        const setRow = (patch) => set({ size_chart: { ...form.size_chart, rows: [...form.size_chart.rows.filter((r) => r.size !== size), { ...row, ...patch }] } })
                        return <tr key={size}><td>{size}</td><td><input value={row.local_size ?? ''} onChange={(e) => setRow({ local_size: e.target.value })} /></td>{config.size_chart_measurements.map((m) => <td key={m}><input type="number" min="0" step="0.1" value={row.measurements?.[m] ?? ''} onChange={(e) => setRow({ measurements: { ...row.measurements, [m]: e.target.value } })} /></td>)}</tr>
                      })}</tbody>
                    </table>
                  </div>
                  {err('size_chart')}
                </div>
              )}
            </>
          )}
          <div className="wz-field">
            <span className="wz-label">Same product on other sites <small className="sc-muted">optional links that back up your price</small></span>
            {form.price_references.map((u, i) => <input key={i} type="url" value={u} placeholder="https://" onChange={(e) => set({ price_references: form.price_references.map((x, j) => (j === i ? e.target.value : x)) })} />)}
            {form.price_references.length < 5 && <button type="button" className="sc-link" onClick={() => set({ price_references: [...form.price_references, ''] })}>+ Add link</button>}
            {Object.keys(errors).filter((k) => k.startsWith('price_references')).map((k) => <small key={k} className="wz-err">{errors[k]}</small>)}
          </div>
        </div>
      )}

      {step === 4 && (
        <div className="sc-card wz-card">
          <h2 className="sc-h2">04 Fulfillment</h2>
          <p className="sc-muted">Your commitment from order to dispatch.</p>
          {config.ships_itself ? (
            <div className="wz-grid">
              <label>Handling time <small className="sc-muted">order placed → shipped</small>
                <select value={form.handling_days} onChange={(e) => set({ handling_days: e.target.value })}><option value="">Select…</option>{config.handling_days.map((d) => <option key={d} value={d}>{d} working day{d === 1 ? '' : 's'}</option>)}</select>{err('handling_days')}
              </label>
              <label>Shipping template <small className="sc-muted">regions, transit times and fees</small>
                <select value={form.shipping_template_id} onChange={(e) => set({ shipping_template_id: e.target.value })}><option value="">Default template</option>{shipTemplates.map((t) => <option key={t.id} value={t.id}>{t.name}{t.is_default ? ' (default)' : ''}</option>)}</select>
              </label>
            </div>
          ) : <p>NexTech collects and delivers this product — no handling time or shipping template needed.</p>}
          {config.ships_itself && <p className="sc-muted">No suitable template, or this product needs its own shipping? <button type="button" className="sc-link" onClick={() => go('shipping')}>Add shipping template</button></p>}
          <p className="sc-muted">Delivery method: {{ nextech: 'NexTech collects & delivers', self: 'You ship with your own courier', label: 'You ship on a NexTech label' }[config.fulfillment_mode] ?? config.fulfillment_mode}</p>
          <label>Return window (days, max {maxReturnDays})<input type="number" min="0" max={maxReturnDays} placeholder={`default ${defaultReturnDays} · 0 = non-returnable`} value={form.return_days} onChange={(e) => set({ return_days: e.target.value })} /></label>
        </div>
      )}

      {step === 5 && (
        <div className="sc-card wz-card">
          <h2 className="sc-h2">05 Safety &amp; compliance</h2>
          <p className="sc-alert warn">Make sure everything is accurate. Incorrect or false information can lead to penalties, compensation to buyers and legal consequences.</p>
          <label>Country/Region of origin<input list="wz-countries" value={form.country_of_origin} onChange={(e) => set({ country_of_origin: e.target.value })} />{err('country_of_origin')}</label>
          <datalist id="wz-countries">{COUNTRY_NAMES.map((c) => <option key={c} value={c} />)}</datalist>
          {inclusive && (
            <div className="wz-grid">
              <label>HSN code<input inputMode="numeric" value={form.hsn_code} onChange={(e) => set({ hsn_code: e.target.value.trim() })} />{err('hsn_code')}</label>
              <label>GST rate<select value={form.gst_rate_bps} onChange={(e) => set({ gst_rate_bps: e.target.value })}><option value="">Select…</option>{(gstRates ?? []).map((r) => <option key={r} value={r}>{r / 100}%</option>)}</select>{err('gst_rate_bps')}</label>
              <label>Manufacturer / packer / importer (name &amp; address)<input value={form.manufacturer_info} onChange={(e) => set({ manufacturer_info: e.target.value })} />{err('manufacturer_info')}</label>
            </div>
          )}
          <div className="wz-field">
            <span className="wz-label">Compliance documents <small className="sc-muted">what this category needs in your market</small></span>
            {!category && <p className="ob-missing">Choose a category to see the documents it needs.</p>}
            <ul className="ob-people">
              {docs.map((d) => {
                const have = form.documents.filter((x) => x.type === d.key)
                return (
                  <li key={d.key}>
                    <span><b>{d.label}</b>{d.required ? <small className="wz-req"> required</small> : <small className="sc-muted"> optional</small>}{have.map((h) => <small key={h.path} className="sc-muted"> · {h.name} <button type="button" className="sc-link" onClick={() => set({ documents: form.documents.filter((x) => x !== h) })}>remove</button></small>)}</span>
                    <label className="seller-btn ghost wz-upload">Upload<input type="file" accept=".pdf,.jpg,.jpeg,.png" disabled={!!busy} onChange={(e) => { const f = e.target.files?.[0]; e.target.value = ''; upload('doc', f, (doc) => setForm((x) => ({ ...x, documents: [...x.documents, { type: d.key, ...doc }] }))) }} /></label>
                  </li>
                )
              })}
            </ul>
            <p className="sc-muted">You can skip documents now, but they must be submitted before the product can be listed. Add them later under Products → Product compliance.</p>
          </div>
        </div>
      )}

      <div className="wz-foot">
        <button type="button" className="seller-btn ghost" onClick={onCancel}>Cancel</button>
        {(!form.id || form.status === 'draft') && <button type="button" className="seller-btn ghost" disabled={!!busy} onClick={() => save(false)}>Save draft</button>}
        {step > 0 && <button type="button" className="seller-btn ghost" onClick={() => setStep(step - 1)}>Back</button>}
        {step < STEPS.length - 1
          ? <button type="button" className="sc-primary" disabled={step === 0 && (!form.name.trim() || !form.category_id)} onClick={() => setStep(step + 1)}>Next</button>
          : <button type="button" className="sc-primary" disabled={!!busy} onClick={() => save(true)}>{busy === 'save' ? 'Submitting…' : 'Submit'}</button>}
      </div>
    </>
  )
}
