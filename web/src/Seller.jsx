import { useCallback, useEffect, useMemo, useRef, useState } from 'react'
import { mediaUrl } from './mediaUrl'
import { ChatPhotoPicker, ChatPhotos } from './ChatPhotos'
import { checkProductImage } from './productImageCheck'
import { renderMarkdown } from './markdown'
import { PageSection } from './PageSections'
import { LineChart, PieChart } from './Charts'
import './Seller.css'

const API_URL = import.meta.env.VITE_API_URL ?? 'http://127.0.0.1:8000/api'
const STORE_URL = import.meta.env.BASE_URL || '/'

async function readJson(response) {
  const text = await response.text()
  try { return text ? JSON.parse(text) : {} } catch { return {} }
}

// A short two-note chime for a new admin message — plain WebAudio, no sound
// file to ship. Mirrors Admin.jsx's playChime() so the "new message" tone is
// consistent on both sides of the same conversation.
function playMessageChime() {
  try {
    const Ctx = window.AudioContext || window.webkitAudioContext
    if (!Ctx) return
    const ctx = new Ctx()
    const blip = (freq, at, dur = 0.2) => {
      const osc = ctx.createOscillator()
      const gain = ctx.createGain()
      osc.connect(gain); gain.connect(ctx.destination)
      osc.type = 'sine'
      osc.frequency.value = freq
      gain.gain.setValueAtTime(0.0001, ctx.currentTime + at)
      gain.gain.exponentialRampToValueAtTime(0.22, ctx.currentTime + at + 0.02)
      gain.gain.exponentialRampToValueAtTime(0.0001, ctx.currentTime + at + dur)
      osc.start(ctx.currentTime + at)
      osc.stop(ctx.currentTime + at + dur + 0.02)
    }
    blip(740, 0)
    blip(988, 0.16, 0.3)
    setTimeout(() => ctx.close(), 900)
  } catch { /* audio blocked — nothing else to fall back to */ }
}

const money = (cents) => `$${((cents ?? 0) / 100).toFixed(2)}`

// Performance chart lines. Order counts share one axis; amount earned (after
// refunds) gets its own dollar axis.
const dollarTick = (cents) => `$${Math.round(cents / 100).toLocaleString()}`
const SELLER_CHART_LINES = [
  { key: 'orders', label: 'Orders', color: '#1f5fae', axis: 'count', format: (v) => v },
  { key: 'completed', label: 'Delivered', color: '#2e7d32', axis: 'count', format: (v) => v },
  { key: 'cancelled', label: 'Cancelled', color: '#a23b28', axis: 'count', format: (v) => v },
  { key: 'earnings_cents', label: 'Earned', color: '#e69138', axis: 'usd', format: money, tickFormat: dollarTick },
]
const STATS_PERIOD = { day: 'last 14 days', week: 'last 12 weeks', month: 'last 12 months' }
const LEDGER_TYPE_LABELS = { order_credit: 'Order credit', refund_debit: 'Refund (item returned)', payout_debit: 'Payout', return_pickup_fee: 'Return pickup fee', delivery_fee_charge: 'Delivery fee (refunded order)' }

// A commission-only estimate, shown while a seller is pricing a product —
// not a quote: the real payout is computed server-side at order time.
function estimateSellerFees(priceDollars, config) {
  const priceCents = Math.round(Number(priceDollars) * 100)
  if (!config || !priceCents || Number.isNaN(priceCents) || priceCents <= 0) return null

  const commissionBps = config.commission_rate_bps ?? 0
  const taxBps = config.tax_rate_bps ?? 0
  const freeThreshold = config.free_delivery_threshold_cents ?? 0
  const deliveryFee = config.delivery_fee_cents ?? config.delivery_far_fee_cents ?? 0

  const commissionCents = Math.round((priceCents * commissionBps) / 10000)
  const netCents = priceCents - commissionCents
  const taxCents = Math.round((priceCents * taxBps) / 10000)
  const belowFreeThreshold = priceCents < freeThreshold
  const customerTotalCents = priceCents + taxCents + (belowFreeThreshold ? deliveryFee : 0)

  return { priceCents, commissionBps, commissionCents, netCents, taxCents, belowFreeThreshold, customerTotalCents }
}

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
  pickup_same_as_registered: true,
  pickup_phone: '',
  pickup_line1: '',
  pickup_line2: '',
  pickup_city: '',
  pickup_state: '',
  pickup_postal_code: '',
  pickup_country: '',
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
  needs_changes: { title: 'Changes requested', body: 'We need a change before we can approve your application — see the message below, then edit and resubmit.' },
  rejected: { title: 'Application not approved', body: 'Your seller application was not approved.' },
  suspended: { title: 'Seller account suspended', body: 'Your seller account is currently suspended and your shop is hidden from customers.' },
}

const formFromSeller = (seller) => ({
  country: seller.country ?? '',
  business_type: seller.business_type ?? '',
  company_name: seller.company_name ?? '',
  tax_id: seller.tax_id ?? '',
  registered_line1: seller.registered_line1 ?? '',
  registered_line2: seller.registered_line2 ?? '',
  registered_city: seller.registered_city ?? '',
  registered_state: seller.registered_state ?? '',
  registered_postal_code: seller.registered_postal_code ?? '',
  registered_country: seller.registered_country ?? '',
  pickup_same_as_registered: seller.pickup_same_as_registered ?? true,
  pickup_phone: seller.pickup_phone ?? '',
  pickup_line1: seller.pickup_line1 ?? '',
  pickup_line2: seller.pickup_line2 ?? '',
  pickup_city: seller.pickup_city ?? '',
  pickup_state: seller.pickup_state ?? '',
  pickup_postal_code: seller.pickup_postal_code ?? '',
  pickup_country: seller.pickup_country ?? '',
  contact_name: seller.contact_name ?? '',
  id_type: seller.id_type ?? '',
  id_number: seller.id_number ?? '',
  date_of_birth: seller.date_of_birth ?? '',
  id_document_path: seller.id_document_path ?? '',
  id_document_name: seller.id_document_path ? seller.id_document_path.split('/').pop() : '',
  business_document_path: seller.business_document_path ?? '',
  business_document_name: seller.business_document_path ? seller.business_document_path.split('/').pop() : '',
  shop_name: seller.shop?.name ?? '',
  shop_logo_url: seller.shop?.logo_url ?? '',
  shop_category_id: seller.shop?.category_id ?? '',
  shop_description: seller.shop?.description ?? '',
})

const PRODUCT_STATUS_LABELS = { pending: 'Pending review', approved: 'Live', rejected: 'Rejected' }
const SUPPORT_ISSUE_LABELS = {
  item_missing: 'Item missing', item_damaged: 'Item damaged', wrong_item: 'Wrong item',
  not_delivered: 'Not delivered', payment_issue: 'Payment issue', other: 'Other', delivery: 'Delivery message',
  seller_product_issue: 'Product issue', seller_other: 'Other',
}
const EMPTY_PAYOUT_FORM = { payout_method: 'bank', holder_name: '', account_number: '', routing_number: '', bank_name: '', email: '' }
const dollarsOrBlank = (cents) => (cents != null ? (cents / 100).toFixed(2) : '')
// Variant SKUs end in a single digit (-V1…-V9), so a product holds at most 9.
const MAX_VARIANTS = 9
const EMPTY_SELLER_VARIANT = { label: '', sku: '', price: '', compare_at: '', stock: 0, image_url: '', is_active: true }
const EMPTY_SELLER_PRODUCT = { category_id: '', name: '', sku: '', price: '', compare_at: '', inventory_quantity: 0, description: '', suggested_category_name: '', images: [], variants: [] }
const variantRowsFrom = (product) => (product.variants ?? []).map((v) => ({
  id: v.id, label: v.label, sku: v.sku, price: (v.price_cents / 100).toFixed(2), compare_at: dollarsOrBlank(v.compare_at_price_cents),
  stock: v.inventory_quantity, image_url: v.image_url ?? '', is_active: v.is_active,
}))

export default function Seller({ token, onSignOut }) {
  const [countries, setCountries] = useState([])
  const [siteConfig, setSiteConfig] = useState(null)
  const [categories, setCategories] = useState([])
  const [me, setMe] = useState(undefined) // undefined = loading, null = no application yet
  const [loadError, setLoadError] = useState('')
  const [step, setStep] = useState(1)
  const [maxStepSeen, setMaxStepSeen] = useState(1)
  const [editingApplication, setEditingApplication] = useState(false)
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
  const [payoutForm, setPayoutForm] = useState(null)
  const [payoutMsg, setPayoutMsg] = useState('')
  const [payoutReqMsg, setPayoutReqMsg] = useState('')
  const [shopLinkMsg, setShopLinkMsg] = useState('')
  const [payoutReqBusy, setPayoutReqBusy] = useState(false)
  const [supportThreads, setSupportThreads] = useState([])
  const [supportThread, setSupportThread] = useState(null)
  const [supportReply, setSupportReply] = useState('')
  const [supportMsg, setSupportMsg] = useState('')
  const [newThreadForm, setNewThreadForm] = useState(null)
  // Seller Center console (approved sellers): which page of the left menu is open.
  const [section, setSection] = useState('home')
  const [productTab, setProductTab] = useState('all')
  const [productSearch, setProductSearch] = useState('')
  const [orderTab, setOrderTab] = useState('all')
  const [openGroups, setOpenGroups] = useState({ products: true, orders: true, account: false })
  // Customer <-> seller chats (buyer messages, or an order chat NexTech brought the seller into).
  const [messagesTab, setMessagesTab] = useState('customers')
  const [sellerPages, setSellerPages] = useState([]) // policy pages placed in the seller footer
  const [customerChats, setCustomerChats] = useState([])
  const [customerChat, setCustomerChat] = useState(null)
  const [customerReply, setCustomerReply] = useState('')
  const [customerPhotos, setCustomerPhotos] = useState([])
  const [customerChatMsg, setCustomerChatMsg] = useState('')
  // "Unread" = the other side wrote something the seller hasn't opened yet.
  // Keyed s-<id> (NexTech threads) / c-<id> (customer chats) -> the message
  // timestamp last seen.
  const [seenMsgs, setSeenMsgs] = useState(() => { try { return JSON.parse(localStorage.getItem('nextech_seller_seen') || '{}') } catch { return {} } })
  const markSeen = useCallback((key, stamp) => {
    if (!stamp) return
    setSeenMsgs((cur) => {
      if (cur[key] === stamp) return cur
      const next = { ...cur, [key]: stamp }
      try { localStorage.setItem('nextech_seller_seen', JSON.stringify(next)) } catch { /* private mode */ }
      return next
    })
  }, [])
  const [pageView, setPageView] = useState(null) // { slug, title, content } | 'loading' | null
  const [soundMuted, setSoundMuted] = useState(() => { try { return localStorage.getItem('nextech_seller_sound_muted') === '1' } catch { return false } })
  const seenMessagesRef = useRef(null)
  const supportThreadRef = useRef(null)

  const authHeaders = useCallback(() => ({ Accept: 'application/json', Authorization: `Bearer ${token}` }), [token])

  useEffect(() => {
    let cancelled = false
    fetch(`${API_URL}/config`, { headers: { Accept: 'application/json' } }).then(readJson)
      .then((res) => {
        if (cancelled) return
        const list = res?.data?.active_countries ?? []
        setCountries(list)
        setSiteConfig(res?.data ?? null)
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

  // Seller policy pages (and any other page) via the same #/p/<slug>
  // hash mechanism Storefront's openPage() uses — reused here as its own small
  // page view rather than switching to the storefront SPA route.
  useEffect(() => {
    const sync = () => {
      const match = window.location.hash.match(/^#\/p\/([a-z0-9-]+)$/)
      if (!match) { setPageView(null); return }
      const slug = match[1]
      setPageView((current) => (current && current !== 'loading' && current.slug === slug ? current : 'loading'))
      fetch(`${API_URL}/pages/${slug}`, { headers: { Accept: 'application/json' } })
        .then(readJson)
        .then((res) => setPageView(res?.data ?? { slug, title: 'Page not found', content: 'That page does not exist.' }))
        .catch(() => setPageView({ slug, title: 'Page not found', content: 'That page does not exist.' }))
    }
    sync()
    window.addEventListener('hashchange', sync)
    return () => window.removeEventListener('hashchange', sync)
  }, [])

  function openPage(slug) {
    window.location.assign(`#/p/${slug}`)
    window.scrollTo({ top: 0 })
  }
  function closePage() {
    if (window.location.hash) window.location.hash = ''
    else setPageView(null)
  }

  async function requestPayout() {
    setPayoutReqMsg('')
    setPayoutReqBusy(true)
    try {
      const response = await fetch(`${API_URL}/seller/payout-requests`, { method: 'POST', headers: authHeaders() })
      const data = await readJson(response)
      if (!response.ok) throw new Error(data.message ?? 'Could not send your payout request.')
      setMe((m) => ({ ...m, last_payout_request: data.data }))
    } catch (error) {
      setPayoutReqMsg(error.message)
    } finally {
      setPayoutReqBusy(false)
    }
  }

  async function savePayoutMethod(event) {
    event.preventDefault()
    setPayoutMsg('')
    try {
      const payload = { payout_method: payoutForm.payout_method }
      if (payoutForm.payout_method === 'bank') {
        payload.holder_name = payoutForm.holder_name.trim()
        payload.account_number = payoutForm.account_number.trim()
        payload.routing_number = payoutForm.routing_number.trim()
        payload.bank_name = payoutForm.bank_name.trim()
      } else {
        payload.email = payoutForm.email.trim()
      }
      const response = await fetch(`${API_URL}/seller/payout-method`, { method: 'PATCH', headers: { ...authHeaders(), 'Content-Type': 'application/json' }, body: JSON.stringify(payload) })
      const data = await readJson(response)
      if (!response.ok) throw new Error(data.message ?? Object.values(data.errors ?? {})[0]?.[0] ?? 'Could not save your payout details.')
      setMe((m) => ({ ...m, payout_method: data.data.payout_method, payout_details: data.data.payout_details }))
      setPayoutMsg('Saved.')
    } catch (error) {
      setPayoutMsg(error.message)
    }
  }

  // The seller wraps up a conversation with NexTech (recorded as a system note).
  async function endSupportThread() {
    if (!supportThread || !window.confirm('End this chat with NexTech?')) return
    setSupportMsg('')
    try {
      const response = await fetch(`${API_URL}/support/threads/${supportThread.id}/end?kind=seller`, { method: 'POST', headers: authHeaders() })
      const data = await readJson(response)
      if (!response.ok) throw new Error(data.message ?? 'Could not end the chat.')
      setSupportThread(null)
      loadSupportThreads()
    } catch (error) {
      setSupportMsg(error.message)
    }
  }

  const loadSupportThreads = useCallback(() => {
    fetch(`${API_URL}/support/threads?kind=seller`, { headers: authHeaders() }).then(readJson)
      .then((res) => setSupportThreads(res?.data ?? []))
      .catch(() => setSupportMsg('Could not load your messages.'))
  }, [authHeaders])

  async function openSupportThread(id) {
    setSupportMsg('')
    try {
      const response = await fetch(`${API_URL}/support/threads/${id}?kind=seller`, { headers: authHeaders() })
      const data = await readJson(response)
      if (!response.ok) throw new Error(data.message ?? 'Could not load that conversation.')
      setSupportThread(data.data)
      markSeen(`s-${data.data.id}`, data.data.last_staff_message_at)
    } catch (error) {
      setSupportMsg(error.message)
    }
  }

  async function replySupportThread(event) {
    event.preventDefault()
    const body = supportReply.trim()
    if (!body || !supportThread) return
    try {
      const response = await fetch(`${API_URL}/support/threads/${supportThread.id}/messages?kind=seller`, { method: 'POST', headers: { ...authHeaders(), 'Content-Type': 'application/json' }, body: JSON.stringify({ body }) })
      const data = await readJson(response)
      if (!response.ok) throw new Error(data.message ?? 'Message not sent.')
      setSupportReply('')
      setSupportThread(data.data)
      loadSupportThreads()
    } catch (error) {
      setSupportMsg(error.message)
    }
  }

  async function startSupportThread(event) {
    event.preventDefault()
    const message = newThreadForm.message.trim()
    if (!message) return
    try {
      const response = await fetch(`${API_URL}/support/threads?kind=seller`, { method: 'POST', headers: { ...authHeaders(), 'Content-Type': 'application/json' }, body: JSON.stringify({ issue_type: newThreadForm.issue_type, message }) })
      const data = await readJson(response)
      if (!response.ok) throw new Error(data.message ?? Object.values(data.errors ?? {})[0]?.[0] ?? 'Could not send your message.')
      setNewThreadForm(null)
      setSupportThread(data.data)
      loadSupportThreads()
    } catch (error) {
      setSupportMsg(error.message)
    }
  }

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
      if (!form.pickup_phone.trim()) return 'Enter a phone number for pickup.'
      if (!form.pickup_same_as_registered) {
        if (!form.pickup_line1.trim()) return 'Enter the pickup street address.'
        if (!form.pickup_city.trim()) return 'Enter the pickup city.'
        if (!form.pickup_state.trim()) return `Enter the pickup ${address.state_label.toLowerCase()}.`
        if (!form.pickup_postal_code.trim()) return `Enter the pickup ${address.postal_label.toLowerCase()}.`
      }
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
        pickup_same_as_registered: form.pickup_same_as_registered,
        pickup_phone: form.pickup_phone.trim(),
        pickup_line1: form.pickup_same_as_registered ? null : form.pickup_line1.trim(),
        pickup_line2: form.pickup_same_as_registered ? null : (form.pickup_line2.trim() || null),
        pickup_city: form.pickup_same_as_registered ? null : form.pickup_city.trim(),
        pickup_state: form.pickup_same_as_registered ? null : form.pickup_state.trim(),
        pickup_postal_code: form.pickup_same_as_registered ? null : form.pickup_postal_code.trim(),
        pickup_country: form.pickup_same_as_registered ? null : form.pickup_country,
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
      setEditingApplication(false)
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

  const [stats, setStats] = useState(null)
  const [statsBucket, setStatsBucket] = useState('day')
  const [statsLines, setStatsLines] = useState(['orders', 'completed', 'cancelled'])

  useEffect(() => {
    if (me?.status !== 'approved') return
    let cancelled = false
    const tz = Intl.DateTimeFormat().resolvedOptions().timeZone
    fetch(`${API_URL}/seller/stats?bucket=${statsBucket}&tz=${encodeURIComponent(tz)}`, { headers: authHeaders() }).then(readJson)
      .then((res) => { if (!cancelled) setStats(res?.data ?? null) })
      .catch(() => { if (!cancelled) setStats({ series: [], top_products: [] }) })
    return () => { cancelled = true }
  }, [me?.status, statsBucket, authHeaders])

  const loadOrders = useCallback(() => {
    fetch(`${API_URL}/seller/orders`, { headers: authHeaders() }).then(readJson)
      .then((res) => { setOrderSummary(res?.data?.summary ?? null); setOrders(res?.data?.orders ?? []) })
      .catch(() => setOrderMsg('Could not load your orders.'))
  }, [authHeaders])

  useEffect(() => {
    if (me?.status === 'approved') { loadProducts(); loadOrders() }
    // Seller<->admin messages (e.g. "message seller" from the application
    // review) matter before approval too — load for any existing application.
    if (me?.status) loadSupportThreads()
  }, [me?.status, loadProducts, loadOrders, loadSupportThreads])

  // On the (non-approved) status page, jump straight into the seller<->admin
  // conversation instead of making the seller pick it out of a thread list.
  useEffect(() => {
    if (!me?.status || me.status === 'approved' || supportThread) return
    const thread = supportThreads.find((t) => t.issue_type === 'seller_product_issue' || t.issue_type === 'seller_other')
    if (!thread) return
    let cancelled = false
    fetch(`${API_URL}/support/threads/${thread.id}?kind=seller`, { headers: authHeaders() }).then(readJson)
      .then((res) => { if (!cancelled && res?.data) setSupportThread(res.data) })
      .catch(() => {})
    return () => { cancelled = true }
  }, [me?.status, supportThreads, supportThread, authHeaders])

  useEffect(() => { supportThreadRef.current = supportThread }, [supportThread])

  // Chat wasn't dynamic before — a new admin message only ever showed up
  // once the seller did something that happened to refetch (e.g. hitting
  // Send). Poll instead, mirroring Admin.jsx's own notification poll: chime
  // + refresh the open thread the moment a new *staff* message lands,
  // without the seller having to do anything.
  useEffect(() => {
    if (!me?.status) return undefined
    let stopped = false
    const check = async () => {
      try {
        const response = await fetch(`${API_URL}/support/threads?kind=seller`, { headers: authHeaders() })
        const data = await readJson(response)
        if (stopped) return
        const threads = data?.data ?? []
        setSupportThreads(threads)

        const relevant = threads.filter((t) => t.issue_type === 'seller_product_issue' || t.issue_type === 'seller_other')
        const seenMap = Object.fromEntries(relevant.map((t) => [t.id, t.last_message_at]))

        if (seenMessagesRef.current === null) {
          seenMessagesRef.current = seenMap // seed — don't chime for history already there
          return
        }

        const fresh = relevant.filter((t) => seenMessagesRef.current[t.id] !== t.last_message_at
          && t.last_staff_message_at && t.last_staff_message_at === t.last_message_at)
        seenMessagesRef.current = seenMap

        if (fresh.length === 0) return
        if (!soundMuted) playMessageChime()

        const openThread = supportThreadRef.current
        if (openThread && fresh.some((t) => t.id === openThread.id)) {
          const detailResponse = await fetch(`${API_URL}/support/threads/${openThread.id}?kind=seller`, { headers: authHeaders() })
          const detail = await readJson(detailResponse)
          if (!stopped && detail?.data) { setSupportThread(detail.data); markSeen(`s-${detail.data.id}`, detail.data.last_staff_message_at) }
        }
      } catch { /* keep last */ }
    }
    const timer = setInterval(check, 8000)
    return () => { stopped = true; clearInterval(timer) }
  }, [me?.status, authHeaders, soundMuted, markSeen])

  // Customer chats: poll the inbox, chime on a new customer/NexTech message,
  // and keep an open conversation live.
  const customerChatRef = useRef(null)
  const seenCustomerRef = useRef(null)
  useEffect(() => { customerChatRef.current = customerChat }, [customerChat])
  useEffect(() => {
    if (me?.status !== 'approved') return undefined
    let stopped = false
    const check = async () => {
      try {
        const data = await readJson(await fetch(`${API_URL}/seller/customer-chats`, { headers: authHeaders() }))
        if (stopped) return
        const list = data?.data ?? []
        setCustomerChats(list)
        const map = Object.fromEntries(list.map((t) => [t.id, t.last_message_at]))
        const fresh = seenCustomerRef.current === null ? [] : list.filter((t) => t.needs_seller_reply && seenCustomerRef.current[t.id] !== t.last_message_at)
        seenCustomerRef.current = map
        if (fresh.length && !soundMuted) playMessageChime()
        const open = customerChatRef.current
        if (open && list.some((t) => t.id === open.id && t.last_message_at !== open.last_message_at)) {
          const detail = await readJson(await fetch(`${API_URL}/seller/customer-chats/${open.id}`, { headers: authHeaders() }))
          const stamp = list.find((t) => t.id === open.id)?.last_message_at
          if (!stopped && detail?.data) { setCustomerChat({ ...detail.data, last_message_at: stamp }); markSeen(`c-${open.id}`, stamp) }
        }
      } catch { /* keep last */ }
    }
    check()
    const timer = setInterval(check, 10000)
    return () => { stopped = true; clearInterval(timer) }
  }, [me?.status, authHeaders, soundMuted, markSeen])

  // Seller policy pages (Pages with the "seller_footer" placement), listed under
  // My account > Policies & rules.
  useEffect(() => {
    fetch(`${API_URL}/pages`, { headers: { Accept: 'application/json' } }).then(readJson)
      .then((data) => setSellerPages((data?.data ?? []).filter((p) => (p.menu_placements ?? []).includes('seller_footer'))))
      .catch(() => {})
  }, [])

  async function openCustomerChat(row) {
    setCustomerChatMsg('')
    try {
      const data = await readJson(await fetch(`${API_URL}/seller/customer-chats/${row.id}`, { headers: authHeaders() }))
      if (data?.data) { setCustomerChat({ ...data.data, last_message_at: row.last_message_at }); markSeen(`c-${row.id}`, row.last_message_at) }
    } catch { setCustomerChatMsg('Could not open that chat.') }
  }

  async function replyCustomerChat(event) {
    event.preventDefault()
    const body = customerReply.trim()
    if ((!body && !customerPhotos.length) || !customerChat) return
    setCustomerChatMsg('')
    try {
      const response = await fetch(`${API_URL}/seller/customer-chats/${customerChat.id}/messages`, { method: 'POST', headers: { ...authHeaders(), 'Content-Type': 'application/json' }, body: JSON.stringify({ body, ...(customerPhotos.length ? { attachments: customerPhotos } : {}) }) })
      const data = await readJson(response)
      if (!response.ok) throw new Error(data.message ?? 'Message not sent.')
      setCustomerReply('')
      setCustomerPhotos([])
      setCustomerChat(data.data)
      setCustomerChats((list) => list.map((t) => (t.id === data.data.id ? { ...t, needs_seller_reply: false } : t)))
    } catch (error) { setCustomerChatMsg(error.message) }
  }

  function editApplication() {
    setForm(formFromSeller(me))
    setStep(1)
    setMaxStepSeen(4)
    setStepError('')
    setEditingApplication(true)
  }

  async function uploadProductImage(file, apply) {
    if (!file) return
    setProductMsg('')
    const problem = await checkProductImage(file)
    if (problem) { setProductMsg(problem); return }
    setProductImgBusy(true)
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
      return_days: product.return_days ?? '',
      inventory_quantity: product.inventory_quantity,
      description: product.description ?? '',
      suggested_category_name: product.suggested_category_name ?? '',
      images: (product.images ?? []).map((i) => i.url),
      variants: variantRowsFrom(product),
      rejection_reason: product.status === 'rejected' ? product.rejection_reason : null,
    })
    setSection('add-product')
    window.scrollTo({ top: 0 })
  }

  async function saveProduct(event) {
    event.preventDefault()
    setProductMsg('')
    const { id, price, compare_at: compareAt, variants, images, ...rest } = productForm
    delete rest.sku
    delete rest.rejection_reason
    const payload = {
      ...rest,
      category_id: Number(rest.category_id),
      inventory_quantity: Number(rest.inventory_quantity),
      price_cents: Math.round(Number(price) * 100),
      compare_at_price_cents: String(compareAt).trim() ? Math.round(Number(compareAt) * 100) : null,
      return_days: String(rest.return_days ?? '').trim() === '' ? null : Number(rest.return_days),
      suggested_category_name: rest.suggested_category_name?.trim() || null,
      images: (images ?? []).filter(Boolean),
    }
    const rows = (variants ?? []).filter((row) => row.id || !row._delete)
    if (id || rows.length) {
      payload.variants = rows.map((row) => ({
        ...(row.id ? { id: row.id } : {}),
        ...(row._delete ? { _delete: true } : {}),
        label: (row.label || '').trim(),
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
      setSection('products')
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

  const showWizard = me === null || editingApplication
  const breadcrumb = showWizard ? STEPS[step - 1] : null

  // ---------------------------------------------------------------------------
  // Approved sellers get the full console: dark top bar, left menu, one page at
  // a time (layout modelled on Temu's Seller Center).
  // ---------------------------------------------------------------------------
  if (me && me.status === 'approved' && !editingApplication) {
    const productStatus = (product) => (!product.is_active && product.status === 'approved' ? 'hidden' : product.status)
    const PRODUCT_TABS = [['all', 'All'], ['approved', 'Live'], ['pending', 'Pending review'], ['rejected', 'Rejected'], ['hidden', 'Hidden']]
    const tabCount = (tab) => (tab === 'all' ? products.length : products.filter((p) => productStatus(p) === tab).length)
    const needle = productSearch.trim().toLowerCase()
    const visibleProducts = products
      .filter((p) => productTab === 'all' || productStatus(p) === productTab)
      .filter((p) => !needle || p.name.toLowerCase().includes(needle) || (p.sku ?? '').toLowerCase().includes(needle) || (p.variants ?? []).some((v) => (v.sku ?? '').toLowerCase().includes(needle)))
    const rejectedCount = tabCount('rejected')
    const pendingCount = tabCount('pending')
    const nextechUnread = (t) => !!t.last_staff_message_at
      && (!t.last_message_at || new Date(t.last_staff_message_at) >= new Date(t.last_message_at))
      && seenMsgs[`s-${t.id}`] !== t.last_staff_message_at
    const customerUnread = (t) => t.needs_seller_reply && seenMsgs[`c-${t.id}`] !== t.last_message_at
    const unreadCustomerChats = customerChats.filter(customerUnread).length
    const unreadNextech = supportThreads.filter(nextechUnread).length
    const unreadThreads = unreadNextech + unreadCustomerChats
    const ORDER_STATUS = { confirmed: 'Confirmed', packing: 'Packing', ready_for_delivery: 'Ready to ship', out_for_delivery: 'Out for delivery', completed: 'Delivered', cancelled: 'Cancelled' }
    const ACTIVE_ORDER = ['confirmed', 'packing', 'ready_for_delivery', 'out_for_delivery']
    const visibleOrders = orders.filter((o) => orderTab === 'all'
      || (orderTab === 'active' && ACTIVE_ORDER.includes(o.status))
      || (orderTab === 'completed' && o.status === 'completed')
      || (orderTab === 'cancelled' && o.status === 'cancelled'))
    const balance = Math.max(0, me.available_cents ?? me.balance_cents ?? 0)
    const payoutReady = balance >= (me.min_payout_cents ?? 0) && balance > 0 && me.last_payout_request?.status !== 'pending'
    const shopUrl = me.shop?.slug ? `${window.location.origin}${import.meta.env.BASE_URL || '/'}#/shop/${me.shop.slug}` : null

    const go = (next) => { setSection(next); setProductForm(null); if (window.location.hash) closePage(); window.scrollTo({ top: 0 }) }
    const newProduct = () => { setProductMsg(''); setProductForm({ ...EMPTY_SELLER_PRODUCT, category_id: categories[0]?.id ?? '' }); setSection('add-product'); window.scrollTo({ top: 0 }) }
    const priceText = (p) => {
      const prices = [p.price_cents, ...(p.variants ?? []).map((v) => v.price_cents)].filter((c) => c != null)
      const lo = Math.min(...prices); const hi = Math.max(...prices)
      return lo === hi ? money(lo) : `${money(lo)} – ${money(hi)}`
    }
    const qtyOf = (p) => ((p.variants ?? []).length ? p.variants.reduce((sum, v) => sum + (v.inventory_quantity ?? 0), 0) : (p.inventory_quantity ?? 0))

    const NAV = [
      { key: 'home', label: 'Homepage', icon: '⌂' },
      { key: 'products', label: 'Products', icon: '▣', children: [['products', 'Manage products'], ['add-product', 'Add products']] },
      { key: 'orders', label: 'Orders', icon: '☰', children: [['orders', 'Manage orders']] },
      { key: 'finances', label: 'Finances', icon: '$' },
      { key: 'analytics', label: 'Analytics', icon: '◔' },
      { key: 'messages', label: 'Messages', icon: '✉', badge: unreadThreads },
      { key: 'account', label: 'My account', icon: '◉', children: [['shop', 'Shop profile'], ['policies', 'Policies & rules']] },
    ]
    const activePage = pageView ? 'page' : section

    return (
      <div className="sc-shell">
        <header className="sc-top">
          <a className="sc-brand" href={STORE_URL}><span className="sc-brand-mark">N</span><span>NexTech<small>Seller Center</small></span></a>
          <form className="sc-search" onSubmit={(event) => { event.preventDefault(); go('products'); setProductTab('all') }}>
            <input placeholder="Search your products by name or SKU" value={productSearch} onChange={(event) => setProductSearch(event.target.value)} />
            <button type="submit" aria-label="Search">⌕</button>
          </form>
          <div className="sc-top-right">
            <button type="button" className="sc-top-btn" onClick={() => { go('policies'); setOpenGroups((g) => ({ ...g, account: true })) }}>Help</button>
            <button type="button" className="sc-top-btn" onClick={() => go('messages')}>Messages{unreadThreads > 0 && <span className="sc-badge">{unreadThreads}</span>}</button>
            <button type="button" className="sc-top-btn" title={soundMuted ? 'Message sound is off — click to turn on' : 'Message sound is on — click to mute'}
              onClick={() => setSoundMuted((m) => { const next = !m; try { localStorage.setItem('nextech_seller_sound_muted', next ? '1' : '0') } catch { /* ignore */ } return next })}>
              {soundMuted ? '🔇' : '🔊'}
            </button>
            <span className="sc-user">
              {me.shop?.logo_url ? <img src={mediaUrl(me.shop.logo_url)} alt="" /> : <i>{(me.shop?.name ?? 'S').slice(0, 1).toUpperCase()}</i>}
              {me.shop?.name}
            </span>
            <button type="button" className="sc-top-btn" onClick={signOut}>Sign out</button>
          </div>
        </header>

        <div className="sc-body">
          <nav className="sc-nav" aria-label="Seller Center">
            {NAV.map((item) => item.children ? (
              <div key={item.key} className="sc-nav-group">
                <button type="button" className={`sc-nav-item${item.children.some(([k]) => k === activePage) ? ' in' : ''}`} aria-expanded={!!openGroups[item.key]} onClick={() => setOpenGroups((g) => ({ ...g, [item.key]: !g[item.key] }))}>
                  <span className="sc-nav-ico" aria-hidden>{item.icon}</span>{item.label}<span className="sc-caret" aria-hidden>{openGroups[item.key] ? '▴' : '▾'}</span>
                </button>
                {openGroups[item.key] && item.children.map(([key, label]) => (
                  <button type="button" key={key} className={`sc-nav-sub${activePage === key ? ' active' : ''}`} onClick={() => (key === 'add-product' ? newProduct() : go(key))}>{label}</button>
                ))}
              </div>
            ) : (
              <button type="button" key={item.key} className={`sc-nav-item${activePage === item.key ? ' active' : ''}`} onClick={() => go(item.key)}>
                <span className="sc-nav-ico" aria-hidden>{item.icon}</span>{item.label}{item.badge > 0 && <span className="sc-badge">{item.badge}</span>}
              </button>
            ))}
          </nav>

          <main className="sc-main">
            {pageView ? (
              <article className="sc-card seller-page-view">
                {pageView === 'loading' ? <p className="seller-loading">Loading&hellip;</p> : <>
                  <nav className="sc-crumbs" aria-label="Breadcrumb">
                    <button type="button" className="sc-link" onClick={() => { closePage(); setSection('policies') }}>Policies</button>
                    {pageView.parent && <> <span aria-hidden>&rsaquo;</span> {pageView.parent.slug
                      ? <button type="button" className="sc-link" onClick={() => openPage(pageView.parent.slug)}>{pageView.parent.title}</button>
                      : <span>{pageView.parent.title}</span>}</>}
                    {' '}<span aria-hidden>&rsaquo;</span> <span>{pageView.title}</span>
                  </nav>
                  <h1 className="sc-title">{pageView.title}</h1>
                  {Array.isArray(pageView.sections) && pageView.sections.length > 0
                    ? <div className="page-sections">{pageView.sections.map((section, index) => <PageSection key={index} section={section} />)}</div>
                    : <div className="page-content" dangerouslySetInnerHTML={{ __html: renderMarkdown(pageView.content ?? '') }} />}
                </>}
              </article>
            ) : section === 'home' ? (
              <>
                <h1 className="sc-title">Welcome back{me.contact_name ? `, ${me.contact_name.split(' ')[0]}` : ''}</h1>
                {!me.shop?.is_active && <div className="sc-alert warn">Your shop is hidden from customers right now. Contact NexTech via Messages if you think this is a mistake.</div>}
                <div className="sc-card">
                  <h2 className="sc-h2">Action needed</h2>
                  <div className="sc-action-grid">
                    <button type="button" className={rejectedCount ? 'sc-action hot' : 'sc-action'} onClick={() => { go('products'); setProductTab('rejected') }}><span>Rejected products</span><strong>{rejectedCount}</strong></button>
                    <button type="button" className="sc-action" onClick={() => { go('products'); setProductTab('pending') }}><span>Pending review</span><strong>{pendingCount}</strong></button>
                    <button type="button" className={orderSummary?.active ? 'sc-action hot' : 'sc-action'} onClick={() => { go('orders'); setOrderTab('active') }}><span>Orders in progress</span><strong>{orderSummary?.active ?? 0}</strong></button>
                    <button type="button" className={unreadThreads ? 'sc-action hot' : 'sc-action'} onClick={() => go('messages')}><span>Unread messages</span><strong>{unreadThreads}</strong></button>
                    <button type="button" className={payoutReady ? 'sc-action hot' : 'sc-action'} onClick={() => go('finances')}><span>Available to pay out</span><strong>{money(balance)}</strong></button>
                  </div>
                </div>
                {shopUrl && (
                  <div className="sc-card sc-shop-link">
                    <div><h2 className="sc-h2">Your shop page</h2><a href={shopUrl} target="_blank" rel="noreferrer">{shopUrl}</a></div>
                    <button type="button" className="seller-btn ghost" onClick={() => navigator.clipboard?.writeText(shopUrl).then(() => setShopLinkMsg('Copied!')).catch(() => {})}>{shopLinkMsg || 'Copy link'}</button>
                  </div>
                )}
                {stats?.totals && (
                  <div className="sc-card">
                    <h2 className="sc-h2">Last {STATS_PERIOD[statsBucket].replace('last ', '')} <button type="button" className="sc-link" onClick={() => go('analytics')}>View analytics &rsaquo;</button></h2>
                    <div className="seller-stat-tiles">
                      <div><span>Total orders</span><strong>{stats.totals.orders}</strong></div>
                      <div><span>Delivered</span><strong>{stats.totals.completed}</strong></div>
                      <div><span>Cancelled</span><strong>{stats.totals.cancelled}</strong></div>
                      <div><span>Amount earned</span><strong>{money(stats.totals.earnings_cents)}</strong></div>
                    </div>
                  </div>
                )}
              </>
            ) : section === 'products' ? (
              <>
                <div className="sc-head"><h1 className="sc-title">Manage products</h1><button type="button" className="sc-primary" onClick={newProduct}>+ Add new product</button></div>
                {rejectedCount > 0 && productTab !== 'rejected' && (
                  <div className="sc-alert danger"><span><b>{rejectedCount} product{rejectedCount === 1 ? ' was' : 's were'} rejected</b> — fix and resubmit to get {rejectedCount === 1 ? 'it' : 'them'} listed.</span><button type="button" onClick={() => setProductTab('rejected')}>Take action</button></div>
                )}
                {productMsg && <p className="seller-inline-error">{productMsg}</p>}
                <div className="sc-tabs">
                  {PRODUCT_TABS.map(([key, label]) => <button type="button" key={key} className={productTab === key ? 'active' : ''} onClick={() => setProductTab(key)}>{label} <small>{tabCount(key)}</small></button>)}
                </div>
                <div className="sc-card sc-table-card">
                  <div className="sc-filter"><input placeholder="Search name or SKU" value={productSearch} onChange={(event) => setProductSearch(event.target.value)} />{productSearch && <button type="button" onClick={() => setProductSearch('')}>Clear</button>}</div>
                  <div className="sc-table-wrap">
                    <table className="sc-table">
                      <thead><tr><th>Status</th><th>Product</th><th>SKU</th><th>Quantity</th><th>Price</th><th>Actions</th></tr></thead>
                      <tbody>
                        {visibleProducts.map((product) => {
                          const thumb = product.images?.[0]?.url || product.image_url
                          const st = productStatus(product)
                          return (
                            <tr key={product.id}>
                              <td>
                                <span className={`sc-pill ${st}`}>{st === 'hidden' ? 'Hidden' : (PRODUCT_STATUS_LABELS[product.status] ?? product.status)}</span>
                                {product.status === 'rejected' && product.rejection_reason && <span className="sc-reason" title={product.rejection_reason}>ⓘ {product.rejection_reason}</span>}
                                <small className="sc-muted">{new Date(product.updated_at ?? product.created_at).toLocaleDateString()}</small>
                              </td>
                              <td>
                                <div className="sc-prod">
                                  {thumb ? <img src={mediaUrl(thumb)} alt="" /> : <span className="sc-prod-ph" />}
                                  <span><b>{product.name}</b><small className="sc-muted">{product.category?.name ?? '—'}{(product.variants ?? []).length ? ` · ${product.variants.length} variant${product.variants.length === 1 ? '' : 's'}` : ''}</small></span>
                                </div>
                              </td>
                              <td><code>{product.sku}</code>{(product.variants ?? []).length > 0 && <small className="sc-muted">{product.variants.map((v) => v.sku).join(', ')}</small>}</td>
                              <td className={qtyOf(product) <= 0 ? 'sc-low' : ''}>{qtyOf(product)}</td>
                              <td>{priceText(product)}{product.compare_at_price_cents > product.price_cents && <s className="sc-muted"> {money(product.compare_at_price_cents)}</s>}</td>
                              <td className="sc-actions">
                                <button type="button" onClick={() => editProduct(product)}>{product.status === 'rejected' ? 'Fix & resubmit' : 'Edit'}</button>
                                {st === 'approved' && shopUrl && <a href={`${import.meta.env.BASE_URL || '/'}#/product/${product.slug}`} target="_blank" rel="noreferrer">View</a>}
                                <button type="button" className="danger" onClick={() => removeProduct(product)}>Delete</button>
                              </td>
                            </tr>
                          )
                        })}
                        {visibleProducts.length === 0 && <tr><td colSpan="6" className="sc-empty">{products.length === 0 ? <>No products yet. <button type="button" className="sc-link" onClick={newProduct}>Add your first product</button></> : 'No products match.'}</td></tr>}
                      </tbody>
                    </table>
                  </div>
                  <p className="sc-muted sc-foot">{visibleProducts.length} product{visibleProducts.length === 1 ? '' : 's'} shown</p>
                </div>
              </>
            ) : section === 'add-product' ? (
              <>
                <div className="sc-head"><h1 className="sc-title">{productForm?.id ? 'Edit product' : 'Add new product'}</h1><button type="button" className="sc-link" onClick={() => go('products')}>&larr; Back to products</button></div>
                {productForm?.id && productForm.rejection_reason && <div className="sc-alert danger">Rejected: {productForm.rejection_reason}</div>}
                <div className="sc-card">
                  {productForm ? (
                    <form className="seller-product-form" onSubmit={saveProduct}>
                                                        <div className="seller-product-grid">
                                        <label>Category
                                          <select required value={productForm.category_id} onChange={(event) => setProductForm({ ...productForm, category_id: event.target.value })}>
                                            <option value="" disabled>Choose…</option>
                                            {categories.map((c) => <option key={c.id} value={c.id}>{c.name}</option>)}
                                          </select>
                                        </label>
                                        <label>Name<input required value={productForm.name} onChange={(event) => setProductForm({ ...productForm, name: event.target.value })} /></label>
                                        <label>SKU<input disabled value={productForm.sku || 'Generated automatically on save'} /></label>
                                        <label>Regular price ($)<input type="number" min="0" step="0.01" placeholder="blank = not on sale" value={productForm.compare_at} onChange={(event) => setProductForm({ ...productForm, compare_at: event.target.value })} /></label>
                                        <label>Sale price ($)<input required type="number" min="0" step="0.01" value={productForm.price} onChange={(event) => setProductForm({ ...productForm, price: event.target.value })} /></label>
                                        <label>Return window (days, max {siteConfig?.max_return_days ?? 90})<input type="number" min="0" max={siteConfig?.max_return_days ?? 90} placeholder={`default ${siteConfig?.return_window_days ?? 30} · 0 = non-returnable`} value={productForm.return_days ?? ''} onChange={(event) => setProductForm({ ...productForm, return_days: event.target.value })} /></label>
                                        <label>Inventory<input type="number" min="0" value={productForm.inventory_quantity} onChange={(event) => setProductForm({ ...productForm, inventory_quantity: event.target.value })} /></label>
                                      </div>

                                      {(() => {
                                        const est = estimateSellerFees(productForm.price, siteConfig)
                                        if (!est) return null
                                        const lowMargin = est.netCents > 0 && est.netCents < 300
                                        return (
                                          <div className="seller-fee-estimate">
                                            <p className="seller-fee-row"><span>Platform commission ({(est.commissionBps / 100).toFixed(1)}%)</span><span>&minus;{money(est.commissionCents)}</span></p>
                                            <p className="seller-fee-row total"><span>You receive per sale</span><span>{money(est.netCents)}</span></p>
                                            <p className="seller-fee-row muted"><span>Customer pays (approx., with tax{est.belowFreeThreshold ? ' + delivery' : ''})</span><span>~{money(est.customerTotalCents)}</span></p>
                                            <p className="seller-field-hint">Commission also covers NexTech&rsquo;s delivery/logistics cost — nothing else is deducted from your payout.</p>
                                            {lowMargin && <p className="seller-inline-error">Only {money(est.netCents)} per sale at this price — card processing and packaging eat into margins this thin. Consider pricing this item a bit higher.</p>}
                                          </div>
                                        )
                                      })()}

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
                                            <input disabled placeholder="SKU" value={row.sku || 'Auto on save'} />
                                            <input type="number" min="0" step="0.01" placeholder="Regular $" value={row.compare_at ?? ''} onChange={(event) => setProductForm({ ...productForm, variants: productForm.variants.map((r, i) => i === index ? { ...r, compare_at: event.target.value } : r) })} />
                                            <input type="number" min="0" step="0.01" placeholder="Sale $" value={row.price} onChange={(event) => setProductForm({ ...productForm, variants: productForm.variants.map((r, i) => i === index ? { ...r, price: event.target.value } : r) })} />
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
                                        <button type="button" className="seller-btn ghost" disabled={(productForm.variants ?? []).filter((v) => !v._delete).length >= MAX_VARIANTS} onClick={() => setProductForm({ ...productForm, variants: [...(productForm.variants ?? []), { ...EMPTY_SELLER_VARIANT }] })}>Add variant{(productForm.variants ?? []).filter((v) => !v._delete).length >= MAX_VARIANTS ? ` (max ${MAX_VARIANTS})` : ''}</button>
                                      </fieldset>

                                      <div className="seller-wizard-actions">
                                        <button type="submit" className="seller-btn">Save</button>
                                        <button type="button" className="seller-btn ghost" onClick={() => setProductForm(null)}>Cancel</button>
                                      </div>
                                      {productMsg && <p className="seller-inline-error">{productMsg}</p>}
                                    </form>
                  ) : <p className="sc-muted">Nothing open. <button type="button" className="sc-link" onClick={newProduct}>Add a product</button></p>}
                </div>
              </>
            ) : section === 'orders' ? (
              <>
                <h1 className="sc-title">Manage orders</h1>
                <div className="sc-card">
                  <h2 className="sc-h2">Overview</h2>
                  <div className="sc-action-grid">
                    <button type="button" className={orderTab === 'active' ? 'sc-action sel' : 'sc-action'} onClick={() => setOrderTab('active')}><span>In progress</span><strong>{orderSummary?.active ?? 0}</strong></button>
                    <button type="button" className={orderTab === 'completed' ? 'sc-action sel' : 'sc-action'} onClick={() => setOrderTab('completed')}><span>Delivered</span><strong>{orderSummary?.completed ?? 0}</strong></button>
                    <button type="button" className={orderTab === 'cancelled' ? 'sc-action sel' : 'sc-action'} onClick={() => setOrderTab('cancelled')}><span>Cancelled</span><strong>{orderSummary?.cancelled ?? 0}</strong></button>
                  </div>
                </div>
                <div className="sc-tabs">
                  {[['all', 'All'], ['active', 'In progress'], ['completed', 'Delivered'], ['cancelled', 'Cancelled']].map(([key, label]) => <button type="button" key={key} className={orderTab === key ? 'active' : ''} onClick={() => setOrderTab(key)}>{label}</button>)}
                </div>
                <div className="sc-card sc-table-card">
                  <div className="sc-table-wrap">
                    <table className="sc-table">
                      <thead><tr><th>Order</th><th>Date</th><th>Your items</th><th>Qty</th><th>Items total</th><th>Status</th></tr></thead>
                      <tbody>
                        {visibleOrders.map((order) => {
                          const items = order.items ?? []
                          return (
                            <tr key={order.id}>
                              <td><b>#{order.id}</b></td>
                              <td>{new Date(order.created_at).toLocaleString([], { dateStyle: 'medium', timeStyle: 'short' })}</td>
                              <td>{items.map((i) => <div key={i.id}>{i.product_name} <span className="sc-muted">× {i.quantity}</span></div>)}</td>
                              <td>{items.reduce((sum, i) => sum + i.quantity, 0)}</td>
                              <td>{money(items.reduce((sum, i) => sum + i.line_total_cents, 0))}</td>
                              <td><span className={`sc-pill order-${order.status}`}>{ORDER_STATUS[order.status] ?? order.status}</span></td>
                            </tr>
                          )
                        })}
                        {visibleOrders.length === 0 && <tr><td colSpan="6" className="sc-empty">No orders here yet.</td></tr>}
                      </tbody>
                    </table>
                  </div>
                  <p className="sc-muted sc-foot">Showing your 20 most recent orders. NexTech packs and delivers them — this shows only your items in each order.</p>
                  {orderMsg && <p className="seller-inline-error">{orderMsg}</p>}
                </div>
              </>
            ) : section === 'finances' ? (
              <>
                <h1 className="sc-title">Finances</h1>
                <div className="sc-card">
                  <div className="seller-earnings">

                    <div className="seller-stat-tiles">
                      <div className="tile-available"><span>Available to pay out</span><strong>{money(Math.max(0, me.available_cents ?? 0))}</strong></div>
                      <div className="tile-held"><span>Held for returns</span><strong>{money(me.pending_cents ?? 0)}</strong></div>
                      <div><span>Total balance</span><strong>{money(me.balance_cents ?? 0)}</strong></div>
                    </div>
                    <p className="seller-payout-status info">Because of the return policy, payment for each order is held — it can&rsquo;t be paid out until the order has been delivered and the return window you set on its products has passed (products without their own window use {me.return_window_days ?? 30} days). This way a later return never has to be taken back from money already paid to you.{(me.available_cents ?? 0) < 0 ? ' Your available balance is below zero because an earlier payout covered sales still inside their return window — it evens out as they clear.' : ''}</p>
                    {(me.pending_orders ?? []).length > 0 && (
                      <ul className="seller-earnings-list seller-held-list">
                        {me.pending_orders.map((p) => (
                          <li key={p.order_id}>
                            <span>Order #{p.order_id} — {p.return_days != null ? `${p.return_days}-day return window · ` : ''}{p.releases_at ? `payable from ${new Date(p.releases_at).toLocaleDateString()}` : 'payable after delivery + return window'}</span>
                            <span className={p.amount_cents >= 0 ? 'positive' : 'negative'}>{p.amount_cents >= 0 ? '+' : '−'}{money(Math.abs(p.amount_cents))}</span>
                          </li>
                        ))}
                      </ul>
                    )}
                    <p className="seller-earnings-note">Payouts are settled by NexTech outside the app (bank transfer/PayPal); this reflects what you&rsquo;re owed. Payouts are batched — your balance needs to reach {money(me.min_payout_cents ?? 0)} before one can be sent.{(me.balance_cents ?? 0) < (me.min_payout_cents ?? 0) && me.balance_cents > 0 ? ` You're ${money((me.min_payout_cents ?? 0) - me.balance_cents)} away.` : ''}</p>
                    {(() => {
                      const req = me.last_payout_request
                      const balance = Math.max(0, me.available_cents ?? 0)
                      const max = me.max_payout_cents ?? 0
                      const requestable = max > 0 ? Math.min(balance, max) : balance
                      if (req?.status === 'pending') {
                        return <p className="seller-payout-status pending">Payout of <strong>{money(req.amount_cents)}</strong> requested on {new Date(req.created_at).toLocaleDateString()} — NexTech will send it to your payout method shortly.</p>
                      }
                      return (
                        <>
                          {req?.status === 'rejected' && <p className="seller-payout-status rejected">Your last payout request wasn&rsquo;t approved{req.admin_note ? `: ${req.admin_note}` : '.'}</p>}
                          {balance >= (me.min_payout_cents ?? 0) && balance > 0 && (
                            <div className="seller-payout-request">
                              <button type="button" className="seller-btn" disabled={payoutReqBusy || !me.payout_method} onClick={requestPayout}>Request payout of {money(requestable)}</button>
                              {!me.payout_method && <span className="seller-earnings-note">Add your payout method below first.</span>}
                              {max > 0 && balance > max && <span className="seller-earnings-note">Single payouts are capped at {money(max)} — the rest can be requested after this one is paid.</span>}
                            </div>
                          )}
                          {payoutReqMsg && <p className="seller-inline-error">{payoutReqMsg}</p>}
                        </>
                      )
                    })()}
                    <ul className="seller-earnings-list">
                      {(me.ledger_entries ?? []).map((entry) => (
                        <li key={entry.id}>
                          <span>{LEDGER_TYPE_LABELS[entry.type] ?? entry.type}{entry.order_id ? ` — order #${entry.order_id}` : ''}{entry.note ? ` — ${entry.note}` : ''}</span>
                          <span className={entry.amount_cents >= 0 ? 'positive' : 'negative'}>{entry.amount_cents >= 0 ? '+' : '−'}{money(Math.abs(entry.amount_cents))}</span>
                        </li>
                      ))}
                      {(me.ledger_entries ?? []).length === 0 && <li className="seller-earnings-empty">No activity yet.</li>}
                    </ul>

                    {me.has_sales ? (
                      payoutForm ? (
                        <form className="seller-shop-form seller-payout-form" onSubmit={savePayoutMethod}>
                          <p className="seller-field-label">Payout method</p>
                          <div className="seller-payout-radios">
                            <label><input type="radio" name="payout_method" value="bank" checked={payoutForm.payout_method === 'bank'} onChange={() => setPayoutForm({ ...payoutForm, payout_method: 'bank' })} /> Bank account</label>
                            <label><input type="radio" name="payout_method" value="paypal" checked={payoutForm.payout_method === 'paypal'} onChange={() => setPayoutForm({ ...payoutForm, payout_method: 'paypal' })} /> PayPal</label>
                          </div>
                          {payoutForm.payout_method === 'bank' ? (
                            <>
                              <label>Account holder name<input required value={payoutForm.holder_name} onChange={(event) => setPayoutForm({ ...payoutForm, holder_name: event.target.value })} /></label>
                              <label>Bank name<input required value={payoutForm.bank_name} onChange={(event) => setPayoutForm({ ...payoutForm, bank_name: event.target.value })} /></label>
                              <label>Account number<input required value={payoutForm.account_number} onChange={(event) => setPayoutForm({ ...payoutForm, account_number: event.target.value })} /></label>
                              <label>Routing number<input required value={payoutForm.routing_number} onChange={(event) => setPayoutForm({ ...payoutForm, routing_number: event.target.value })} /></label>
                            </>
                          ) : (
                            <label>PayPal email<input required type="email" value={payoutForm.email} onChange={(event) => setPayoutForm({ ...payoutForm, email: event.target.value })} /></label>
                          )}
                          <div className="seller-wizard-actions">
                            <button type="submit" className="seller-btn">Save payout details</button>
                            <button type="button" className="seller-btn ghost" onClick={() => setPayoutForm(null)}>Cancel</button>
                          </div>
                          {payoutMsg && <p className="seller-inline-error">{payoutMsg}</p>}
                        </form>
                      ) : (
                        <div className="seller-payout-form">
                          {me.payout_method && (
                            <p className="seller-earnings-note">
                              On file: {me.payout_method === 'bank'
                                ? <>Bank transfer — {me.payout_details?.bank_name}, acct ending {String(me.payout_details?.account_number ?? '').slice(-4)}</>
                                : <>PayPal — {me.payout_details?.email}</>}
                            </p>
                          )}
                          <button type="button" className="seller-btn ghost" onClick={() => setPayoutForm({ ...EMPTY_PAYOUT_FORM, payout_method: me.payout_method || 'bank', ...(me.payout_details ?? {}) })}>{me.payout_method ? 'Edit payout details' : 'Add payout details'}</button>
                          {payoutMsg && <p className="seller-inline-error">{payoutMsg}</p>}
                        </div>
                      )
                    ) : (
                      <p className="seller-earnings-note">Add your payout details once you&rsquo;ve made your first sale — you&rsquo;ll see this unlock here.</p>
                    )}
                  </div>
                </div>
              </>
            ) : section === 'analytics' ? (
              <>
                <h1 className="sc-title">Analytics</h1>
                <div className="sc-card">
                  <div className="seller-earnings seller-performance">

                    <div className="seller-seg-row">
                      <div className="seller-seg" role="group" aria-label="Time basis">
                        {[['day', 'Daily'], ['week', 'Weekly'], ['month', 'Monthly']].map(([value, label]) => (
                          <button key={value} type="button" className={statsBucket === value ? 'active' : ''} onClick={() => { setStats(null); setStatsBucket(value) }}>{label}</button>
                        ))}
                      </div>
                      <div className="seller-seg" role="group" aria-label="Measures (pick any combination)">
                        {SELLER_CHART_LINES.map(({ key, label }) => (
                          <button key={key} type="button" className={statsLines.includes(key) ? 'active' : ''} onClick={() => setStatsLines((cur) => (cur.includes(key) ? (cur.length > 1 ? cur.filter((k) => k !== key) : cur) : [...cur, key]))}>{label}</button>
                        ))}
                      </div>
                    </div>
                    {!stats ? <p className="seller-earnings-note">Loading chart…</p> : (
                      <>
                        {stats.totals && (
                          <div className="seller-stat-tiles">
                            <div><span>Total orders</span><strong>{stats.totals.orders}</strong></div>
                            <div><span>Delivered</span><strong>{stats.totals.completed}</strong></div>
                            <div><span>In progress</span><strong>{stats.totals.active}</strong></div>
                            <div><span>Cancelled</span><strong>{stats.totals.cancelled}</strong></div>
                            <div><span>Items sold</span><strong>{stats.totals.units}</strong></div>
                            <div><span>Amount earned</span><strong>{money(stats.totals.earnings_cents)}</strong></div>
                          </div>
                        )}
                        <p className="seller-earnings-note">Figures for the {STATS_PERIOD[statsBucket]}.</p>
                        <LineChart
                          lines={SELLER_CHART_LINES.filter((line) => statsLines.includes(line.key)).map((line) => ({
                            ...line,
                            points: (stats.series ?? []).map((row) => ({ label: row.label, value: row[line.key] ?? 0 })),
                          }))}
                        />
                        {(stats.top_products ?? []).length > 0 && (
                          <div className="seller-top-products">
                            <h4>Top products</h4>
                            <PieChart data={stats.top_products.map((p) => ({ label: p.label, value: p.quantity }))} size={140} format={(v) => `${v} sold`} />
                          </div>
                        )}
                      </>
                    )}
                  </div>
                </div>
              </>
            ) : section === 'messages' ? (
              <>
                <h1 className="sc-title">Messages</h1>
                <div className="sc-tabs">
                  <button type="button" className={messagesTab === 'customers' ? 'active' : ''} onClick={() => setMessagesTab('customers')}>Customer chats {unreadCustomerChats > 0 && <span className="sc-badge">{unreadCustomerChats}</span>}</button>
                  <button type="button" className={messagesTab === 'nextech' ? 'active' : ''} onClick={() => setMessagesTab('nextech')}>NexTech support {unreadNextech > 0 && <span className="sc-badge">{unreadNextech}</span>}</button>
                </div>
                {messagesTab === 'customers' ? (
                  <div className="sc-card">
                    {customerChat ? (
                      <div className="sc-chat">
                        <button type="button" className="sc-link" onClick={() => setCustomerChat(null)}>&larr; All customer chats</button>
                        <h2 className="sc-h2">{customerChat.customer_first_name}{customerChat.order ? ` · Order #${customerChat.order.id}` : ''}</h2>
                        {customerChat.order && (
                          <p className="sc-muted">Your items: {(customerChat.order.items ?? []).map((i) => `${i.product_name} × ${i.quantity}`).join(', ') || '—'} · order {customerChat.order.status}</p>
                        )}
                        <p className="sc-alert warn">NexTech brought you into this chat to help resolve an order issue. NexTech can see and reply here too.</p>
                        <div className="sc-chat-log">
                          {(customerChat.messages ?? []).map((m) => (
                            <div key={m.id} className={`sc-msg ${m.from}`}>
                              <b>{m.from === 'you' ? 'You' : m.from === 'nextech' ? 'NexTech' : m.from === 'customer' ? customerChat.customer_first_name : ''}</b>
                              {m.body && <span>{m.body}</span>}
                              <ChatPhotos urls={m.attachments} />
                              <small>{new Date(m.created_at).toLocaleString()}</small>
                            </div>
                          ))}
                        </div>
                        <ChatPhotoPicker photos={customerPhotos} onChange={setCustomerPhotos} token={token} onError={setCustomerChatMsg} />
                        <form className="sc-chat-send" onSubmit={replyCustomerChat}>
                          <textarea rows="2" placeholder={`Reply to ${customerChat.customer_first_name}…`} value={customerReply} onChange={(event) => setCustomerReply(event.target.value)} />
                          <button type="submit" className="sc-primary" disabled={!customerReply.trim() && !customerPhotos.length}>Send</button>
                        </form>
                        <p className="sc-muted">Keep it on NexTech — don&rsquo;t share or ask for emails, phone numbers or payment outside the platform.</p>
                      </div>
                    ) : customerChats.length === 0 ? (
                      <p className="sc-muted">No customer chats yet. When a customer has an issue with one of your items, NexTech support will bring you into their chat here.</p>
                    ) : (
                      <table className="sc-table">
                        <thead><tr><th>Customer</th><th>Order</th><th>Type</th><th>Last message</th><th>Status</th><th></th></tr></thead>
                        <tbody>
                          {customerChats.map((t) => (
                            <tr key={t.id}>
                              <td><b>{t.customer_first_name}</b></td>
                              <td>{t.order_id ? `#${t.order_id}` : '—'}</td>
                              <td>Order issue (with NexTech)</td>
                              <td>{t.last_message_at ? new Date(t.last_message_at).toLocaleString() : '—'}</td>
                              <td>{customerUnread(t) ? <span className="sc-pill rejected">Unread</span> : t.needs_seller_reply ? <span className="sc-pill pending">Awaiting your reply</span> : <span className="sc-pill">{t.status}</span>}</td>
                              <td className="sc-actions"><button type="button" onClick={() => openCustomerChat(t)}>Open</button></td>
                            </tr>
                          ))}
                        </tbody>
                      </table>
                    )}
                    {customerChatMsg && <p className="seller-inline-error">{customerChatMsg}</p>}
                  </div>
                ) : (
                <div className="sc-card">
                  <div className="seller-support">

                    {!supportThread && !newThreadForm && (
                      <button type="button" className="seller-btn ghost" onClick={() => setNewThreadForm({ issue_type: 'seller_product_issue', message: '' })}>New message</button>
                    )}

                    {newThreadForm && (
                      <form className="seller-shop-form" onSubmit={startSupportThread}>
                        <label>What&rsquo;s this about?
                          <select value={newThreadForm.issue_type} onChange={(event) => setNewThreadForm({ ...newThreadForm, issue_type: event.target.value })}>
                            <option value="seller_product_issue">Product issue</option>
                            <option value="seller_other">Other</option>
                          </select>
                        </label>
                        <label>Message
                          <textarea rows="4" required value={newThreadForm.message} onChange={(event) => setNewThreadForm({ ...newThreadForm, message: event.target.value })} />
                        </label>
                        <div className="seller-wizard-actions">
                          <button type="submit" className="seller-btn">Send</button>
                          <button type="button" className="seller-btn ghost" onClick={() => setNewThreadForm(null)}>Cancel</button>
                        </div>
                      </form>
                    )}

                    {!supportThread && !newThreadForm && (
                      <ul className="seller-earnings-list seller-thread-list">
                        {supportThreads.map((t) => (
                          <li key={t.id}>
                            <button type="button" className="seller-thread-row" onClick={() => openSupportThread(t.id)}>
                              <span>{SUPPORT_ISSUE_LABELS[t.issue_type] ?? t.issue_type}{nextechUnread(t) && <span className="seller-thread-dot" aria-label="Unread" />}</span>
                              <span>{t.status}{t.last_message_at ? ` · ${new Date(t.last_message_at).toLocaleDateString()}` : ''}</span>
                            </button>
                          </li>
                        ))}
                        {supportThreads.length === 0 && <li className="seller-earnings-empty">No messages yet.</li>}
                      </ul>
                    )}

                    {supportThread && (
                      <div className="seller-thread-detail">
                        <div className="seller-thread-top">
                          <button type="button" className="seller-btn ghost" onClick={() => setSupportThread(null)}>&larr; Back to messages</button>
                          <button type="button" className="seller-btn ghost" onClick={endSupportThread}>End chat</button>
                        </div>
                        <h4>{SUPPORT_ISSUE_LABELS[supportThread.issue_type] ?? supportThread.issue_type}</h4>
                        <div className="seller-thread-messages">
                          {(supportThread.messages ?? []).map((msg) => (
                            <p key={msg.id} className={msg.is_staff ? 'seller-thread-msg staff' : 'seller-thread-msg'}>
                              <strong>{msg.is_staff ? 'NexTech' : 'You'}:</strong> {msg.body}
                            </p>
                          ))}
                        </div>
                        <form className="seller-thread-reply" onSubmit={replySupportThread}>
                          <textarea rows="2" placeholder="Reply…" value={supportReply} onChange={(event) => setSupportReply(event.target.value)} />
                          <button type="submit" className="seller-btn">Send</button>
                        </form>
                      </div>
                    )}
                    {supportMsg && <p className="seller-inline-error">{supportMsg}</p>}
                  </div>
                </div>
                )}
              </>
            ) : section === 'policies' ? (
              <>
                <h1 className="sc-title">Policies &amp; rules</h1>
                <div className="sc-card">
                  <p className="sc-muted">The rules for selling on NexTech. Please read them before listing products — listings that break them are removed.</p>
                  {(() => {
                    const bySlug = Object.fromEntries(sellerPages.map((pg) => [pg.slug, pg]))
                    const children = (slug) => sellerPages.filter((pg) => pg.parent_slug === slug)
                    const heading = (slug) => slug.split('-').map((w) => w.charAt(0).toUpperCase() + w.slice(1)).join(' ')
                    // Top level: pages with no (or an unknown) parent, plus placeholder
                    // groups for parents that aren't published yet.
                    const orphanParents = [...new Set(sellerPages.filter((pg) => pg.parent_slug && !bySlug[pg.parent_slug]).map((pg) => pg.parent_slug))]
                    const top = [
                      ...sellerPages.filter((pg) => !pg.parent_slug || !bySlug[pg.parent_slug]).filter((pg) => !pg.parent_slug),
                      ...orphanParents.map((slug) => ({ slug, title: heading(slug), placeholder: true })),
                    ]
                    if (sellerPages.length === 0) return <p className="sc-muted">No policies published yet.</p>
                    return (
                      <ul className="sc-policy-list">
                        {top.map((pg) => (
                          <li key={pg.slug}>
                            {pg.placeholder
                              ? <span className="sc-policy-parent">{pg.title}</span>
                              : <button type="button" onClick={() => openPage(pg.slug)}>{pg.title}<span aria-hidden>&rsaquo;</span></button>}
                            {children(pg.slug).length > 0 && (
                              <ul className="sc-policy-sub">
                                {children(pg.slug).map((c) => <li key={c.slug}><button type="button" onClick={() => openPage(c.slug)}>{c.title}<span aria-hidden>&rsaquo;</span></button></li>)}
                              </ul>
                            )}
                          </li>
                        ))}
                      </ul>
                    )
                  })()}
                  <p className="sc-muted">Questions? <button type="button" className="sc-link" onClick={() => go('messages')}>Message NexTech</button></p>
                </div>
              </>
            ) : section === 'shop' ? (
              <>
                <h1 className="sc-title">Shop profile</h1>
                <div className="sc-card sc-shop-profile">
                  <div className="sc-prod">
                    {me.shop?.logo_url ? <img src={mediaUrl(me.shop.logo_url)} alt="" /> : <span className="sc-prod-ph" />}
                    <span><b>{me.shop?.name}</b><small className="sc-muted">{me.shop?.is_active ? 'Live on NexTech' : 'Hidden'} · SKU prefix SLR{me.shop?.shop_code}</small></span>
                  </div>
                  {me.shop?.description && <p>{me.shop.description}</p>}
                  {shopUrl && <p>Shop page: <a href={shopUrl} target="_blank" rel="noreferrer">{shopUrl}</a></p>}
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
                </div>
                <p className="sc-muted"><button type="button" className="sc-link" onClick={() => openPage('seller-services-agreement')}>NexTech Seller Services Agreement</button></p>
              </>
            ) : null}
          </main>
        </div>
      </div>
    )
  }


  return (
    <div className="seller-shell">
      <header className="seller-bar">
        <a className="seller-brand" href={STORE_URL}>NexTech <span>Seller Center</span></a>
        {breadcrumb && <span className="seller-bar-step">Step {step} of 4 — {breadcrumb}</span>}
        {me?.status && (
          <button type="button" className="seller-sound-toggle" title={soundMuted ? 'Message sound is off — click to turn on' : 'Message sound is on — click to mute'}
            onClick={() => setSoundMuted((m) => { const next = !m; try { localStorage.setItem('nextech_seller_sound_muted', next ? '1' : '0') } catch { /* ignore */ } return next })}>
            {soundMuted ? '🔇' : '🔊'}
          </button>
        )}
        <button type="button" className="seller-signout" onClick={signOut}>Sign out</button>
      </header>

      <main className="seller-main">
        {pageView ? (
          <article className="seller-page-view">
            <button type="button" className="seller-btn ghost" onClick={closePage}>&larr; Back</button>
            {pageView === 'loading'
              ? <p className="seller-loading">Loading&hellip;</p>
              : <>
                  <h2>{pageView.title}</h2>
                  {Array.isArray(pageView.sections) && pageView.sections.length > 0
                    ? <div className="page-sections">{pageView.sections.map((section, index) => <PageSection key={index} section={section} />)}</div>
                    : <div className="page-content" dangerouslySetInnerHTML={{ __html: renderMarkdown(pageView.content) }} />}
                </>}
          </article>
        ) : <>
        {me === undefined && !loadError && <p className="seller-loading">Loading&hellip;</p>}
        {loadError && <p className="seller-error">{loadError}</p>}

        {showWizard && (
          <div className="seller-wizard">
            {editingApplication && (
              <button type="button" className="seller-btn ghost" onClick={() => setEditingApplication(false)}>&larr; Back without resubmitting</button>
            )}
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

                  <fieldset className="seller-address">
                    <legend>Pickup address</legend>
                    <p className="seller-field-hint">Where a courier collects your orders from — this can differ from your registered business address above.</p>
                    <label className="seller-checkbox-row">
                      <input type="checkbox" checked={form.pickup_same_as_registered}
                        onChange={(event) => setForm((f) => ({
                          ...f,
                          pickup_same_as_registered: event.target.checked,
                          pickup_country: event.target.checked ? f.pickup_country : (f.pickup_country || f.registered_country),
                        }))} />
                      Same as registered address
                    </label>
                    <label>Pickup contact phone
                      <input value={form.pickup_phone} onChange={(event) => setForm({ ...form, pickup_phone: event.target.value })} placeholder="For the courier to call on arrival" />
                    </label>
                    {!form.pickup_same_as_registered && (
                      <>
                        <label>Country / region
                          <select value={form.pickup_country} onChange={(event) => setForm({ ...form, pickup_country: event.target.value })}>
                            {countries.map((c) => <option key={c.code} value={c.code}>{c.name}</option>)}
                          </select>
                        </label>
                        <label>Street address
                          <input value={form.pickup_line1} onChange={(event) => setForm({ ...form, pickup_line1: event.target.value })} />
                        </label>
                        <label>Apt / suite (optional)
                          <input value={form.pickup_line2} onChange={(event) => setForm({ ...form, pickup_line2: event.target.value })} />
                        </label>
                        <div className="seller-row-3">
                          <label>{address.postal_label}
                            <input value={form.pickup_postal_code} onChange={(event) => setForm({ ...form, pickup_postal_code: event.target.value })} />
                          </label>
                          <label>{address.state_label}
                            <input value={form.pickup_state} onChange={(event) => setForm({ ...form, pickup_state: event.target.value })} />
                          </label>
                          <label>City
                            <input value={form.pickup_city} onChange={(event) => setForm({ ...form, pickup_city: event.target.value })} />
                          </label>
                        </div>
                      </>
                    )}
                  </fieldset>
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

                    <h4>Pickup address</h4>
                    <p>{form.pickup_phone}</p>
                    <p>{form.pickup_same_as_registered
                      ? 'Same as registered address'
                      : [form.pickup_line1, form.pickup_line2, form.pickup_city, form.pickup_state, form.pickup_postal_code].filter(Boolean).join(', ')}</p>
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

        {me && me.status && me.status !== 'approved' && !editingApplication && (
          <div className="seller-status-page">
            <h2>{STATUS_COPY[me.status]?.title ?? me.status}</h2>
            <p>{STATUS_COPY[me.status]?.body}</p>
            {me.status === 'rejected' && me.rejection_reason && <p className="seller-reason">Reason: {me.rejection_reason}</p>}
            {me.status === 'suspended' && me.rejection_reason && <p className="seller-reason">Reason: {me.rejection_reason}</p>}
            {me.status === 'needs_changes' && me.rejection_reason && <p className="seller-reason">What to change: {me.rejection_reason}</p>}
            <p className="seller-shopname">Shop: {me.shop?.name}</p>

            {me.status === 'needs_changes' && (
              <button type="button" className="seller-btn" onClick={editApplication}>Edit &amp; resubmit application</button>
            )}

            {supportThread && (
              <div className="seller-support seller-status-thread">
                <h3>Messages</h3>
                <div className="seller-thread-detail">
                  <div className="seller-thread-messages">
                    {(supportThread.messages ?? []).map((msg) => (
                      <p key={msg.id} className={msg.is_staff ? 'seller-thread-msg staff' : 'seller-thread-msg'}>
                        <strong>{msg.is_staff ? 'NexTech' : 'You'}:</strong> {msg.body}
                      </p>
                    ))}
                    {(supportThread.messages ?? []).length === 0 && <p className="seller-earnings-empty">No messages yet.</p>}
                  </div>
                  <form className="seller-thread-reply" onSubmit={replySupportThread}>
                    <textarea rows="2" placeholder="Reply…" value={supportReply} onChange={(event) => setSupportReply(event.target.value)} />
                    <button type="submit" className="seller-btn">Send</button>
                  </form>
                </div>
                {supportMsg && <p className="seller-inline-error">{supportMsg}</p>}
              </div>
            )}
          </div>
        )}

        </>}
      </main>
    </div>
  )
}
