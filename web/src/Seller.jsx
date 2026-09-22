import { useCallback, useEffect, useMemo, useState } from 'react'
import { mediaUrl } from './mediaUrl'
import './Seller.css'

const API_URL = import.meta.env.VITE_API_URL ?? 'http://127.0.0.1:8000/api'
const STORE_URL = import.meta.env.BASE_URL || '/'

async function readJson(response) {
  const text = await response.text()
  try { return text ? JSON.parse(text) : {} } catch { return {} }
}

const money = (cents) => `$${((cents ?? 0) / 100).toFixed(2)}`
const LEDGER_TYPE_LABELS = { order_credit: 'Order credit', refund_debit: 'Refund', payout_debit: 'Payout' }

const STEPS = ['Business information', 'Seller information', 'Shop', 'Verification']

const BUSINESS_TYPE_FALLBACK = [
  { value: 'individual', label: 'Individual', description: 'An individual selling as themselves, no registered business.' },
  { value: 'proprietorship', label: 'Sole proprietorship', description: 'Unincorporated business owned by one person.' },
  { value: 'private_limited', label: 'Private company', description: 'Registered LLC or privately held company.' },
  { value: 'state_owned', label: 'State-owned enterprise', description: 'Government or state-owned enterprise.' },
  { value: 'public_listed', label: 'Public listed company', description: 'Company listed on a public stock exchange.' },
]

const EMPTY_FORM = {
  country: '',
  business_type: '',
  company_name: '',
  tax_id: '',
  registered_line1: '',
  registered_line2: '',
  registered_city: '',
  registered_state: '',
  registered_postal_code: '',
  registered_country: '',
  contact_name: '',
  id_type: '',
  id_number: '',
  date_of_birth: '',
  id_document_path: '',
  id_document_name: '',
  business_document_path: '',
  business_document_name: '',
  shop_name: '',
  shop_logo_url: '',
  shop_category_id: '',
  shop_description: '',
}

const STATUS_COPY = {
  pending: { title: 'Application submitted', body: 'Your seller application is in review. This usually takes a few business days — we’ll email you as soon as there’s a decision.' },
  rejected: { title: 'Application not approved', body: 'Your seller application was not approved.' },
  suspended: { title: 'Seller account suspended', body: 'Your seller account is currently suspended and your shop is hidden from customers.' },
}

const PRODUCT_STATUS_LABELS = { pending: 'Pending review', approved: 'Live', rejected: 'Rejected' }
const dollarsOrBlank = (cents) => (cents != null ? (cents / 100).toFixed(2) : '')
const EMPTY_SELLER_VARIANT = { label: '', sku: '', price: '', compare_at: '', stock: 0, image_url: '', is_active: true }
const EMPTY_SELLER_PRODUCT = { category_id: '', name: '', sku: '', price: '', compare_at: '', inventory_quantity: 0, description: '', suggested_category_name: '', images: [], variants: [] }
const variantRowsFrom = (product) => (product.variants ?? []).map((v) => ({
  id: v.id, label: v.label, sku: v.sku, price: (v.price_cents / 100).toFixed(2), compare_at: dollarsOrBlank(v.compare_at_price_cents),
  stock: v.inventory_quantity, image_url: v.image_url ?? '', is_active: v.is_active,
}))

export default function Seller({ token, onSignOut }) {
  const [countries, setCountries] = useState([])
  const [categories, setCategories] = useState([])
  const [me, setMe] = useState(undefined) // undefined = loading, null = no application yet
  const [loadError, setLoadError] = useState('')
  const [step, setStep] = useState(1)
  const [maxStepSeen, setMaxStepSeen] = useState(1)
  const [form, setForm] = useState(EMPTY_FORM)
  const [stepError, setStepError] = useState('')
  const [submitting, setSubmitting] = useState(false)
  const [uploading, setUploading] = useState('') // '' | 'id' | 'business' | 'logo'
  const [dashboardForm, setDashboardForm] = useState(null)
  const [dashboardMsg, setDashboardMsg] = useState('')
  const [products, setProducts] = useState([])
  const [productForm, setProductForm] = useState(null)
  const [productMsg, setProductMsg] = useState('')
  const [productImgBusy, setProductImgBusy] = useState(false)
  const [orderSummary, setOrderSummary] = useState(null)
  const [orders, setOrders] = useState([])
  const [orderMsg, setOrderMsg] = useState('')

  const authHeaders = useCallback(() => ({ Accept: 'application/json', Authorization: `Bearer ${token}` }), [token])

  useEffect(() => {
    let cancelled = false
    fetch(`${API_URL}/config`, { headers: { Accept: 'application/json' } }).then(readJson)
      .then((res) => {
        if (cancelled) return
        const list = res?.data?.active_countries ?? []
        setCountries(list)
        setForm((f) => (f.country ? f : { ...f, country: list[0]?.code ?? '', registered_country: list[0]?.code ?? '' }))
      })
      .catch(() => {})
    fetch(`${API_URL}/categories`, { headers: { Accept: 'application/json' } }).then(readJson)
      .then((res) => { if (!cancelled) setCategories(res?.data ?? []) })
      .catch(() => {})
    return () => { cancelled = true }
  }, [])

  useEffect(() => {
    let cancelled = false
    fetch(`${API_URL}/seller/me`, { headers: authHeaders() }).then(readJson)
      .then((res) => { if (!cancelled) setMe(res?.data ?? null) })
      .catch(() => { if (!cancelled) setLoadError('Could not reach the API. Start Laravel on port 8000 and reload.') })
    return () => { cancelled = true }
  }, [authHeaders])

  const countryMap = useMemo(() => Object.fromEntries(countries.map((c) => [c.code, c])), [countries])
  const country = countryMap[form.country]
  const registeredCountry = countryMap[form.registered_country] ?? country
  const businessTypes = country?.business_types ?? BUSINESS_TYPE_FALLBACK
  const taxId = country?.tax_id ?? { label: 'Business number', placeholder: '', regex: '', help_url: '' }
  const idTypes = country?.id_types ?? []
  const address = registeredCountry?.address ?? { state_label: 'State', postal_label: 'Postal code', postal_regex: '' }

  function setCountry(code) {
    // Business country and registered-address country are the same jurisdiction
    // in the overwhelming common case, so they move together; the registered
    // address still has its own dropdown for the rare case they diverge. If the
    // selected id_type isn't offered by the newly chosen country, clear it
    // rather than silently submitting a value the country doesn't recognise.
    const nextIdTypes = countryMap[code]?.id_types ?? []
    setForm((f) => ({ ...f, country: code, registered_country: code, id_type: nextIdTypes.some((t) => t.value === f.id_type) ? f.id_type : '' }))
  }

  async function uploadKyc(kind, file) {
    if (!file) return
    setUploading(kind === 'id_document' ? 'id' : 'business')
    setStepError('')
    try {
      const body = new FormData()
      body.append('file', file)
      body.append('kind', kind)
      const response = await fetch(`${API_URL}/seller/kyc-document`, { method: 'POST', headers: authHeaders(), body })
      const data = await readJson(response)
      if (!response.ok) throw new Error(data.message ?? Object.values(data.errors ?? {})[0]?.[0] ?? 'Could not upload the document.')
      if (kind === 'id_document') setForm((f) => ({ ...f, id_document_path: data.data.path, id_document_name: file.name }))
      else setForm((f) => ({ ...f, business_document_path: data.data.path, business_document_name: file.name }))
    } catch (error) {
      setStepError(error.message)
    } finally {
      setUploading('')
    }
  }

  async function uploadLogo(file) {
    if (!file) return
    setUploading('logo')
    setStepError('')
    try {
      const body = new FormData()
      body.append('file', file)
      body.append('folder', 'shops')
      const response = await fetch(`${API_URL}/seller/media`, { method: 'POST', headers: authHeaders(), body })
      const data = await readJson(response)
      if (!response.ok) throw new Error(data.message ?? 'Could not upload the logo.')
      setForm((f) => ({ ...f, shop_logo_url: data.data.url }))
    } catch (error) {
      setStepError(error.message)
    } finally {
      setUploading('')
    }
  }

  function validateStep(n) {
    if (n === 1) {
      if (!form.country) return 'Choose a country.'
      if (!form.business_type) return 'Choose a business type.'
      if (!form.company_name.trim()) return 'Enter your company / registered name.'
      if (!form.tax_id.trim()) return `Enter your ${taxId.label}.`
      if (taxId.regex && !new RegExp(taxId.regex).test(form.tax_id.trim())) return `That doesn't look like a valid ${taxId.label}.`
      if (!form.registered_line1.trim()) return 'Enter your registered street address.'
      if (!form.registered_city.trim()) return 'Enter your city.'
      if (!form.registered_state.trim()) return `Enter your ${address.state_label.toLowerCase()}.`
      if (!form.registered_postal_code.trim()) return `Enter your ${address.postal_label.toLowerCase()}.`
      return ''
    }
    if (n === 2) {
      if (!form.contact_name.trim()) return 'Enter the contact / legal name.'
      if (!form.id_type) return 'Choose an ID type.'
      if (!form.id_number.trim()) return 'Enter the ID number.'
      if (!form.date_of_birth) return 'Enter a date of birth.'
      if (!form.id_document_path) return 'Upload a copy of the ID document.'
      return ''
    }
    if (n === 3) {
      if (!form.shop_name.trim()) return 'Enter a shop name.'
      return ''
    }
    if (n === 4) {
      if (!form.business_document_path) return 'Upload a business document.'
      return ''
    }
    return ''
  }

  function goNext() {
    const err = validateStep(step)
    if (err) { setStepError(err); return }
    setStepError('')
    setStep((s) => { const next = Math.min(4, s + 1); setMaxStepSeen((m) => Math.max(m, next)); return next })
  }
  function goBack() { setStepError(''); setStep((s) => Math.max(1, s - 1)) }
  function goToStep(n) { if (n <= maxStepSeen) { setStepError(''); setStep(n) } }

  async function submitApplication() {
    const err = validateStep(4)
    if (err) { setStepError(err); return }
    setSubmitting(true)
    setStepError('')
    try {
      const payload = {
        country: form.country,
        business_type: form.business_type,
        company_name: form.company_name.trim(),
        tax_id: form.tax_id.trim(),
        registered_line1: form.registered_line1.trim(),
        registered_line2: form.registered_line2.trim() || null,
        registered_city: form.registered_city.trim(),
        registered_state: form.registered_state.trim(),
        registered_postal_code: form.registered_postal_code.trim(),
        registered_country: form.registered_country,
        contact_name: form.contact_name.trim(),
        id_type: form.id_type,
        id_number: form.id_number.trim(),
        date_of_birth: form.date_of_birth,
        id_document_path: form.id_document_path,
        business_document_path: form.business_document_path,
        shop_name: form.shop_name.trim(),
        shop_logo_url: form.shop_logo_url || null,
        shop_category_id: form.shop_category_id ? Number(form.shop_category_id) : null,
        shop_description: form.shop_description.trim() || null,
      }
      const response = await fetch(`${API_URL}/seller/apply`, { method: 'POST', headers: { ...authHeaders(), 'Content-Type': 'application/json' }, body: JSON.stringify(payload) })
      const data = await readJson(response)
      if (!response.ok) throw new Error(data.message ?? Object.values(data.errors ?? {})[0]?.[0] ?? 'Could not submit the application.')
      setMe(data.data)
    } catch (error) {
      setStepError(error.message)
    } finally {
      setSubmitting(false)
    }
  }

  async function saveShop(event) {
    event.preventDefault()
    setDashboardMsg('')
    try {
      const payload = { name: dashboardForm.name.trim(), logo_url: dashboardForm.logo_url || null, category_id: dashboardForm.category_id ? Number(dashboardForm.category_id) : null, description: dashboardForm.description.trim() || null }
      const response = await fetch(`${API_URL}/seller/shop`, { method: 'PATCH', headers: { ...authHeaders(), 'Content-Type': 'application/json' }, body: JSON.stringify(payload) })
      const data = await readJson(response)
      if (!response.ok) throw new Error(data.message ?? 'Could not save your shop.')
      setMe((m) => ({ ...m, shop: data.data }))
      setDashboardMsg('Saved.')
    } catch (error) {
      setDashboardMsg(error.message)
    }
  }

  const loadProducts = useCallback(() => {
    fetch(`${API_URL}/seller/products`, { headers: authHeaders() }).then(readJson)
      .then((res) => setProducts(res?.data ?? []))
      .catch(() => setProductMsg('Could not load your products.'))
  }, [authHeaders])

  const loadOrders = useCallback(() => {
    fetch(`${API_URL}/seller/orders`, { headers: authHeaders() }).then(readJson)
      .then((res) => { setOrderSummary(res?.data?.summary ?? null); setOrders(res?.data?.orders ?? []) })
      .catch(() => setOrderMsg('Could not load your orders.'))
  }, [authHeaders])

  useEffect(() => {
    if (me?.status === 'approved') { loadProducts(); loadOrders() }
  }, [me?.status, loadProducts, loadOrders])

  async function uploadProductImage(file, apply) {
    if (!file) return
    setProductImgBusy(true)
    setProductMsg('')
    try {
      const body = new FormData()
      body.append('file', file)
      const response = await fetch(`${API_URL}/seller/product-media`, { method: 'POST', headers: authHeaders(), body })
      const data = await readJson(response)
      if (!response.ok) throw new Error(data.message ?? 'Could not upload the image.')
      apply(data.data.url)
    } catch (error) {
      setProductMsg(error.message)
    } finally {
      setProductImgBusy(false)
    }
  }

  function editProduct(product) {
    setProductMsg('')
    setProductForm({
      id: product.id,
      category_id: product.category_id ?? '',
      name: product.name,
      sku: product.sku,
      price: (product.price_cents / 100).toFixed(2),
      compare_at: dollarsOrBlank(product.compare_at_price_cents),
      inventory_quantity: product.inventory_quantity,
      description: product.description ?? '',
      suggested_category_name: product.suggested_category_name ?? '',
      images: (product.images ?? []).map((i) => i.url),
      variants: variantRowsFrom(product),
    })
  }

  async function saveProduct(event) {
    event.preventDefault()
    setProductMsg('')
    const { id, price, compare_at: compareAt, variants, images, ...rest } = productForm
    const payload = {
      ...rest,
      category_id: Number(rest.category_id),
      inventory_quantity: Number(rest.inventory_quantity),
      price_cents: Math.round(Number(price) * 100),
      compare_at_price_cents: String(compareAt).trim() ? Math.round(Number(compareAt) * 100) : null,
      suggested_category_name: rest.suggested_category_name?.trim() || null,
      images: (images ?? []).filter(Boolean),
    }
    const rows = (variants ?? []).filter((row) => row.id || !row._delete)
    if (id || rows.length) {
      payload.variants = rows.map((row) => ({
        ...(row.id ? { id: row.id } : {}),
        ...(row._delete ? { _delete: true } : {}),
        label: (row.label || '').trim(),
        sku: (row.sku || '').trim(),
        price_cents: Math.round(Number(row.price || 0) * 100),
        compare_at_price_cents: String(row.compare_at ?? '').trim() ? Math.round(Number(row.compare_at) * 100) : null,
        inventory_quantity: Number(row.stock) || 0,
        image_url: row.image_url?.trim() || null,
        is_active: !!row.is_active,
      }))
    }
    try {
      const response = await fetch(`${API_URL}/seller/products${id ? `/${id}` : ''}`, { method: id ? 'PATCH' : 'POST', headers: { ...authHeaders(), 'Content-Type': 'application/json' }, body: JSON.stringify(payload) })
      const data = await readJson(response)
      if (!response.ok) throw new Error(data.message ?? Object.values(data.errors ?? {})[0]?.[0] ?? 'Could not save the product.')
      setProductForm(null)
      loadProducts()
    } catch (error) {
      setProductMsg(error.message)
    }
  }

  async function removeProduct(product) {
    if (!window.confirm(`Delete ${product.name}?`)) return
    setProductMsg('')
    try {
      const response = await fetch(`${API_URL}/seller/products/${product.id}`, { method: 'DELETE', headers: authHeaders() })
      if (!response.ok && response.status !== 204) throw new Error((await readJson(response)).message ?? 'Could not delete the product.')
      loadProducts()
    } catch (error) {
      setProductMsg(error.message)
    }
  }

  function signOut() {
    fetch(`${API_URL}/auth/logout`, { method: 'POST', headers: authHeaders() }).catch(() => {})
    onSignOut()
  }

  const breadcrumb = me === null ? STEPS[step - 1] : null

  return (
    <div className="seller-shell">
      <header className="seller-bar">
        <a className="seller-brand" href={STORE_URL}>NexTech <span>Seller Center</span></a>
        {breadcrumb && <span className="seller-bar-step">Step {step} of 4 — {breadcrumb}</span>}
        <button type="button" className="seller-signout" onClick={signOut}>Sign out</button>
      </header>

      <main className="seller-main">
        {me === undefined && !loadError && <p className="seller-loading">Loading&hellip;</p>}
        {loadError && <p className="seller-error">{loadError}</p>}

        {me === null && (
          <div className="seller-wizard">
            <ol className="seller-stepper">
              {STEPS.map((label, i) => {
                const n = i + 1
                return (
                  <li key={label} className={n === step ? 'active' : n < step ? 'done' : ''}>
                    <button type="button" disabled={n > maxStepSeen} onClick={() => goToStep(n)}>
                      <span className="seller-step-circle">{n < step ? '✓' : n}</span>
                      <span className="seller-step-label">{label}</span>
                    </button>
                  </li>
                )
              })}
            </ol>

            <div className="seller-wizard-card">
              {step === 1 && (
                <section>
                  <h2>Business information</h2>
                  <label>Country
                    <select value={form.country} onChange={(event) => setCountry(event.target.value)}>
                      {countries.map((c) => <option key={c.code} value={c.code}>{c.name}</option>)}
                    </select>
                  </label>

                  <p className="seller-field-label">Business type</p>
                  <div className="seller-type-cards">
                    {businessTypes.map((bt) => (
                      <label key={bt.value} className={`seller-type-card${form.business_type === bt.value ? ' selected' : ''}`}>
                        <input type="radio" name="business_type" value={bt.value} checked={form.business_type === bt.value} onChange={() => setForm((f) => ({ ...f, business_type: bt.value }))} />
                        <span className="seller-type-title">{bt.label}</span>
                        <span className="seller-type-desc">{bt.description}</span>
                      </label>
                    ))}
                  </div>

                  <label>Company / registered name
                    <input value={form.company_name} onChange={(event) => setForm({ ...form, company_name: event.target.value })} placeholder="e.g. Acme Electronics LLC" />
                  </label>

                  <label>{taxId.label}
                    <input value={form.tax_id} onChange={(event) => setForm({ ...form, tax_id: event.target.value })} placeholder={taxId.placeholder} />
                  </label>
                  {taxId.regex && form.tax_id.trim() && !new RegExp(taxId.regex).test(form.tax_id.trim()) && (
                    <p className="seller-inline-error">That doesn&rsquo;t look like a valid {taxId.label}.{taxId.help_url && <> <a href={taxId.help_url} target="_blank" rel="noreferrer">Learn more ↗</a></>}</p>
                  )}

                  <fieldset className="seller-address">
                    <legend>Registered address</legend>
                    <p className="seller-banner-amber">This address must match your official business documents.</p>
                    <label>Country / region
                      <select value={form.registered_country} onChange={(event) => setForm({ ...form, registered_country: event.target.value })}>
                        {countries.map((c) => <option key={c.code} value={c.code}>{c.name}</option>)}
                      </select>
                    </label>
                    <label>Street address
                      <input value={form.registered_line1} onChange={(event) => setForm({ ...form, registered_line1: event.target.value })} />
                    </label>
                    <label>Apt / suite (optional)
                      <input value={form.registered_line2} onChange={(event) => setForm({ ...form, registered_line2: event.target.value })} />
                    </label>
                    <div className="seller-row-3">
                      <label>{address.postal_label}
                        <input value={form.registered_postal_code} onChange={(event) => setForm({ ...form, registered_postal_code: event.target.value })} />
                      </label>
                      <label>{address.state_label}
                        <input value={form.registered_state} onChange={(event) => setForm({ ...form, registered_state: event.target.value })} />
                      </label>
                      <label>City
                        <input value={form.registered_city} onChange={(event) => setForm({ ...form, registered_city: event.target.value })} />
                      </label>
                    </div>
                  </fieldset>
                </section>
              )}

              {step === 2 && (
                <section>
                  <h2>Seller information</h2>
                  <label>Contact / legal name
                    <input value={form.contact_name} onChange={(event) => setForm({ ...form, contact_name: event.target.value })} />
                  </label>
                  <label>ID type
                    <select value={form.id_type} onChange={(event) => setForm({ ...form, id_type: event.target.value })}>
                      <option value="" disabled>Choose…</option>
                      {idTypes.map((t) => <option key={t.value} value={t.value}>{t.label}</option>)}
                    </select>
                  </label>
                  <label>ID number
                    <input value={form.id_number} onChange={(event) => setForm({ ...form, id_number: event.target.value })} />
                  </label>
                  <label>Date of birth
                    <input type="date" value={form.date_of_birth} onChange={(event) => setForm({ ...form, date_of_birth: event.target.value })} />
                  </label>
                  <label>ID document (photo or PDF)
                    <input type="file" accept="image/jpeg,image/png,application/pdf" disabled={uploading === 'id'} onChange={(event) => uploadKyc('id_document', event.target.files?.[0])} />
                  </label>
                  {uploading === 'id' && <p className="seller-uploading">Uploading&hellip;</p>}
                  {form.id_document_name && <p className="seller-uploaded">✓ {form.id_document_name}</p>}
                </section>
              )}

              {step === 3 && (
                <section>
                  <h2>Shop</h2>
                  <label>Shop name
                    <input value={form.shop_name} onChange={(event) => setForm({ ...form, shop_name: event.target.value })} placeholder="What customers will see" />
                  </label>
                  <label>Shop logo (optional)
                    <div className="seller-image-field">
                      {form.shop_logo_url && <img src={mediaUrl(form.shop_logo_url)} alt="" className="seller-logo-preview" />}
                      <input type="file" accept="image/*" disabled={uploading === 'logo'} onChange={(event) => uploadLogo(event.target.files?.[0])} />
                    </div>
                  </label>
                  {uploading === 'logo' && <p className="seller-uploading">Uploading&hellip;</p>}
                  <label>Primary category
                    <select value={form.shop_category_id} onChange={(event) => setForm({ ...form, shop_category_id: event.target.value })}>
                      <option value="">— none —</option>
                      {categories.map((c) => <option key={c.id} value={c.id}>{c.name}</option>)}
                    </select>
                  </label>
                  <label>Shop description (optional)
                    <textarea rows="3" value={form.shop_description} onChange={(event) => setForm({ ...form, shop_description: event.target.value })} />
                  </label>
                </section>
              )}

              {step === 4 && (
                <section>
                  <h2>Verification</h2>
                  <div className="seller-summary">
                    <h4>Business information</h4>
                    <p>{country?.name} · {businessTypes.find((b) => b.value === form.business_type)?.label} · {form.company_name}</p>
                    <p>{taxId.label}: {form.tax_id}</p>
                    <p>{[form.registered_line1, form.registered_line2, form.registered_city, form.registered_state, form.registered_postal_code].filter(Boolean).join(', ')}</p>

                    <h4>Seller information</h4>
                    <p>{form.contact_name} · {idTypes.find((t) => t.value === form.id_type)?.label ?? form.id_type} {form.id_number}</p>
                    <p>Date of birth: {form.date_of_birth}</p>
                    <p>ID document: {form.id_document_name || '—'}</p>

                    <h4>Shop</h4>
                    <p>{form.shop_name}</p>
                    <p>{categories.find((c) => String(c.id) === String(form.shop_category_id))?.name ?? 'No category chosen'}</p>
                  </div>

                  <label>Business document (registration certificate, license, etc.)
                    <input type="file" accept="image/jpeg,image/png,application/pdf" disabled={uploading === 'business'} onChange={(event) => uploadKyc('business_document', event.target.files?.[0])} />
                  </label>
                  {uploading === 'business' && <p className="seller-uploading">Uploading&hellip;</p>}
                  {form.business_document_name && <p className="seller-uploaded">✓ {form.business_document_name}</p>}
                </section>
              )}

              {stepError && <p className="seller-inline-error seller-step-error">{stepError}</p>}

              <div className="seller-wizard-actions">
                {step > 1 && <button type="button" className="seller-btn ghost" onClick={goBack}>Back</button>}
                {step < 4
                  ? <button type="button" className="seller-btn" onClick={goNext}>Next</button>
                  : <button type="button" className="seller-btn" disabled={submitting} onClick={submitApplication}>{submitting ? 'Submitting…' : 'Submit application'}</button>}
              </div>
            </div>
          </div>
        )}

        {me && me.status && me.status !== 'approved' && (
          <div className="seller-status-page">
            <h2>{STATUS_COPY[me.status]?.title ?? me.status}</h2>
            <p>{STATUS_COPY[me.status]?.body}</p>
            {me.status === 'rejected' && me.rejection_reason && <p className="seller-reason">Reason: {me.rejection_reason}</p>}
            {me.status === 'suspended' && me.rejection_reason && <p className="seller-reason">Reason: {me.rejection_reason}</p>}
            <p className="seller-shopname">Shop: {me.shop?.name}</p>
          </div>
        )}

        {me && me.status === 'approved' && (
          <div className="seller-dashboard">
            <h2>{me.shop?.name}</h2>
            <p className="seller-status-pill approved">Approved{me.shop?.is_active ? ' · live' : ' · hidden'}</p>

            <div className="seller-earnings">
              <h3>Earnings</h3>
              <p className="seller-earnings-balance">Balance: <strong>{money(me.balance_cents ?? 0)}</strong></p>
              <p className="seller-earnings-note">Payouts are settled by NexTech outside the app (bank transfer); this reflects what you&rsquo;re owed.</p>
              <ul className="seller-earnings-list">
                {(me.ledger_entries ?? []).map((entry) => (
                  <li key={entry.id}>
                    <span>{LEDGER_TYPE_LABELS[entry.type] ?? entry.type}{entry.order_id ? ` — order #${entry.order_id}` : ''}{entry.note ? ` — ${entry.note}` : ''}</span>
                    <span className={entry.amount_cents >= 0 ? 'positive' : 'negative'}>{entry.amount_cents >= 0 ? '+' : '−'}{money(Math.abs(entry.amount_cents))}</span>
                  </li>
                ))}
                {(me.ledger_entries ?? []).length === 0 && <li className="seller-earnings-empty">No activity yet.</li>}
              </ul>
            </div>

            <div className="seller-orders">
              <h3>My orders</h3>
              <div className="seller-orders-stats">
                <div className="seller-orders-stat"><strong>{orderSummary?.active ?? 0}</strong><span>Active</span></div>
                <div className="seller-orders-stat"><strong>{orderSummary?.completed ?? 0}</strong><span>Completed</span></div>
                <div className="seller-orders-stat"><strong>{orderSummary?.cancelled ?? 0}</strong><span>Cancelled</span></div>
              </div>
              <ul className="seller-earnings-list">
                {orders.map((order) => {
                  const itemCount = (order.items ?? []).reduce((sum, item) => sum + item.quantity, 0)
                  const subtotal = (order.items ?? []).reduce((sum, item) => sum + item.line_total_cents, 0)
                  return (
                    <li key={order.id}>
                      <span>Order #{order.id} — {new Date(order.created_at).toLocaleDateString()} — {itemCount} item{itemCount === 1 ? '' : 's'}</span>
                      <span>{order.status} · {money(subtotal)}</span>
                    </li>
                  )
                })}
                {orders.length === 0 && <li className="seller-earnings-empty">No orders yet.</li>}
              </ul>
              {orderMsg && <p className="seller-inline-error">{orderMsg}</p>}
            </div>

            {!dashboardForm
              ? <button type="button" className="seller-btn" onClick={() => setDashboardForm({ name: me.shop?.name ?? '', logo_url: me.shop?.logo_url ?? '', category_id: me.shop?.category_id ?? '', description: me.shop?.description ?? '' })}>Edit shop</button>
              : (
                <form className="seller-shop-form" onSubmit={saveShop}>
                  <label>Shop name
                    <input value={dashboardForm.name} onChange={(event) => setDashboardForm({ ...dashboardForm, name: event.target.value })} />
                  </label>
                  <label>Logo
                    <div className="seller-image-field">
                      {dashboardForm.logo_url && <img src={mediaUrl(dashboardForm.logo_url)} alt="" className="seller-logo-preview" />}
                      <input type="file" accept="image/*" onChange={async (event) => {
                        const file = event.target.files?.[0]
                        if (!file) return
                        const body = new FormData(); body.append('file', file); body.append('folder', 'shops')
                        const response = await fetch(`${API_URL}/seller/media`, { method: 'POST', headers: authHeaders(), body })
                        const data = await readJson(response)
                        if (response.ok) setDashboardForm((f) => ({ ...f, logo_url: data.data.url }))
                      }} />
                    </div>
                  </label>
                  <label>Description
                    <textarea rows="3" value={dashboardForm.description} onChange={(event) => setDashboardForm({ ...dashboardForm, description: event.target.value })} />
                  </label>
                  <div className="seller-wizard-actions">
                    <button type="submit" className="seller-btn">Save</button>
                    <button type="button" className="seller-btn ghost" onClick={() => setDashboardForm(null)}>Cancel</button>
                  </div>
                  {dashboardMsg && <p className="seller-inline-error">{dashboardMsg}</p>}
                </form>
              )}

            <div className="seller-products">
              <h3>My products</h3>
              {!productForm && <button type="button" className="seller-btn" onClick={() => setProductForm({ ...EMPTY_SELLER_PRODUCT, category_id: categories[0]?.id ?? '' })}>Add product</button>}
              {productMsg && !productForm && <p className="seller-inline-error">{productMsg}</p>}

              <div className="seller-product-list">
                {products.map((product) => {
                  const thumb = product.images?.[0]?.url || product.image_url
                  return (
                    <div className="seller-product-row" key={product.id}>
                      {thumb ? <img className="seller-product-thumb" src={mediaUrl(thumb)} alt="" /> : <div className="seller-product-thumb placeholder" />}
                      <div className="seller-product-info">
                        <strong>{product.name}</strong>
                        <span className={`seller-product-pill ${product.status}`}>{PRODUCT_STATUS_LABELS[product.status] ?? product.status}</span>
                        {product.status === 'rejected' && product.rejection_reason && <p className="seller-reason">Reason: {product.rejection_reason}</p>}
                      </div>
                      <div className="seller-product-actions">
                        <button type="button" className="seller-btn ghost" onClick={() => editProduct(product)}>Edit</button>
                        <button type="button" className="seller-btn ghost" onClick={() => removeProduct(product)}>Delete</button>
                      </div>
                    </div>
                  )
                })}
                {products.length === 0 && <p className="seller-earnings-empty">No products yet.</p>}
              </div>

              {productForm && (
                <form className="seller-product-form" onSubmit={saveProduct}>
                  <h4>{productForm.id ? `Edit ${productForm.name}` : 'New product'}</h4>
                  <div className="seller-product-grid">
                    <label>Category
                      <select required value={productForm.category_id} onChange={(event) => setProductForm({ ...productForm, category_id: event.target.value })}>
                        <option value="" disabled>Choose…</option>
                        {categories.map((c) => <option key={c.id} value={c.id}>{c.name}</option>)}
                      </select>
                    </label>
                    <label>Name<input required value={productForm.name} onChange={(event) => setProductForm({ ...productForm, name: event.target.value })} /></label>
                    <label>SKU<input required value={productForm.sku} onChange={(event) => setProductForm({ ...productForm, sku: event.target.value })} /></label>
                    <label>Price ($)<input required type="number" min="0" step="0.01" value={productForm.price} onChange={(event) => setProductForm({ ...productForm, price: event.target.value })} /></label>
                    <label>Regular price ($)<input type="number" min="0" step="0.01" placeholder="blank = not on sale" value={productForm.compare_at} onChange={(event) => setProductForm({ ...productForm, compare_at: event.target.value })} /></label>
                    <label>Inventory<input type="number" min="0" value={productForm.inventory_quantity} onChange={(event) => setProductForm({ ...productForm, inventory_quantity: event.target.value })} /></label>
                  </div>

                  <label>Description<textarea rows="3" value={productForm.description} onChange={(event) => setProductForm({ ...productForm, description: event.target.value })} /></label>

                  <label>Suggest a category <span className="seller-field-hint">(nothing above fits? tell us and we&rsquo;ll review it — this doesn&rsquo;t create a category on its own)</span>
                    <input value={productForm.suggested_category_name} onChange={(event) => setProductForm({ ...productForm, suggested_category_name: event.target.value })} placeholder="e.g. Drone accessories" />
                  </label>

                  <p className="seller-field-label">Photos</p>
                  <div className="seller-gallery">
                    {(productForm.images ?? []).map((url, index) => (
                      <div className="seller-gallery-item" key={index}>
                        <img src={mediaUrl(url)} alt="" />
                        <button type="button" onClick={() => setProductForm({ ...productForm, images: productForm.images.filter((_, i) => i !== index) })}>&times;</button>
                      </div>
                    ))}
                    <label className="seller-gallery-add">
                      {productImgBusy ? '…' : '+ Add'}
                      <input type="file" accept="image/*" disabled={productImgBusy} onChange={(event) => uploadProductImage(event.target.files?.[0], (url) => setProductForm((form) => ({ ...form, images: [...(form.images ?? []), url] })))} />
                    </label>
                  </div>

                  <fieldset className="seller-fieldset">
                    <legend>Options / variants</legend>
                    <p className="seller-field-hint">Leave empty for a single-price product. Add a row per variant — colour, size, storage, etc.</p>
                    {(productForm.variants ?? []).map((row, index) => row._delete ? null : (
                      <div className="seller-variant-row" key={row.id ?? `new-${index}`}>
                        <input placeholder="Label" value={row.label} onChange={(event) => setProductForm({ ...productForm, variants: productForm.variants.map((r, i) => i === index ? { ...r, label: event.target.value } : r) })} />
                        <input placeholder="SKU" value={row.sku} onChange={(event) => setProductForm({ ...productForm, variants: productForm.variants.map((r, i) => i === index ? { ...r, sku: event.target.value } : r) })} />
                        <input type="number" min="0" step="0.01" placeholder="Price $" value={row.price} onChange={(event) => setProductForm({ ...productForm, variants: productForm.variants.map((r, i) => i === index ? { ...r, price: event.target.value } : r) })} />
                        <input type="number" min="0" step="0.01" placeholder="Reg. $" value={row.compare_at ?? ''} onChange={(event) => setProductForm({ ...productForm, variants: productForm.variants.map((r, i) => i === index ? { ...r, compare_at: event.target.value } : r) })} />
                        <input type="number" min="0" placeholder="Stock" value={row.stock} onChange={(event) => setProductForm({ ...productForm, variants: productForm.variants.map((r, i) => i === index ? { ...r, stock: event.target.value } : r) })} />
                        <span className="seller-variant-img">
                          <input placeholder="Image URL" value={row.image_url} onChange={(event) => setProductForm({ ...productForm, variants: productForm.variants.map((r, i) => i === index ? { ...r, image_url: event.target.value } : r) })} />
                          <input type="file" accept="image/*" disabled={productImgBusy} onChange={(event) => uploadProductImage(event.target.files?.[0], (url) => setProductForm((form) => ({ ...form, variants: form.variants.map((r, i) => i === index ? { ...r, image_url: url } : r) })))} />
                        </span>
                        <button type="button" className="seller-btn ghost" onClick={() => setProductForm({ ...productForm, variants: row.id
                          ? productForm.variants.map((r, i) => i === index ? { ...r, _delete: true } : r)
                          : productForm.variants.filter((_, i) => i !== index) })}>Remove</button>
                      </div>
                    ))}
                    <button type="button" className="seller-btn ghost" onClick={() => setProductForm({ ...productForm, variants: [...(productForm.variants ?? []), { ...EMPTY_SELLER_VARIANT }] })}>Add variant</button>
                  </fieldset>

                  <div className="seller-wizard-actions">
                    <button type="submit" className="seller-btn">Save</button>
                    <button type="button" className="seller-btn ghost" onClick={() => setProductForm(null)}>Cancel</button>
                  </div>
                  {productMsg && <p className="seller-inline-error">{productMsg}</p>}
                </form>
              )}
            </div>
          </div>
        )}
      </main>
    </div>
  )
}
