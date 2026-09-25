import { useCallback, useEffect, useRef, useState } from 'react'
import { CustomerCrm } from './AdminCustomer'
import { EmailsPanel } from './AdminEmails'
import { LabelRequestsPanel, LabelTemplates, OrderLabelRequests } from './AdminLabels'
import { MarketSettings } from './AdminMarkets'
import { currencySymbol, setStoreCurrency, storeMoney } from './money'
import MapPicker from './MapPicker'
import { ChatPhotoPicker, ChatPhotos } from './ChatPhotos'
import { Delta, Heatmap, LineChart, PieChart } from './Charts'
import { renderMarkdown } from './markdown'
import { SECTION_TYPES, blankSection } from './pageSectionTypes'
import { mediaUrl } from './mediaUrl'
import { checkProductImage } from './productImageCheck'
import './Admin.css'

const API_URL = import.meta.env.VITE_API_URL ?? 'http://127.0.0.1:8000/api'

// So the dashboard's day/week/hour buckets line up with the admin's own
// clock instead of the server's (which runs in UTC) — e.g. "orders today"
// means today where the admin is sitting, not today in UTC.
const ADMIN_TZ = (() => {
  try { return Intl.DateTimeFormat().resolvedOptions().timeZone || 'UTC' } catch { return 'UTC' }
})()

// Amounts are in the market's currency — pass it for anything tied to an
// order, product or seller (India = INR); default is the home market's USD.
const money = (cents, currency) => storeMoney(cents ?? 0, currency) // defaults to the admin's selected currency
const MARKET_CURRENCY = { US: 'usd', IN: 'inr' }

// Cap each notification-bell section so a busy week (dozens of refunds, say)
// doesn't turn the dropdown into a wall of rows — the rest is a "+N more" line.
const BELL_ITEM_CAP = 5

// The three selectable lines on the Orders trend chart, in the fixed order
// they're always drawn (independent of toggle click order).
// Revenue and refunds share one dollar axis so their heights compare directly;
// orders (a count) get their own axis.
const dollarTick = (cents) => `${currencySymbol()}${Math.round(cents / 100).toLocaleString()}`
const CHART_LINES = [
  { key: 'revenue_cents', label: 'Revenue', color: '#1f5fae', axis: 'usd', format: money, tickFormat: dollarTick },
  { key: 'refunded_cents', label: 'Refunds', color: '#a23b28', axis: 'usd', format: money, tickFormat: dollarTick },
  { key: 'orders', label: 'Orders', color: '#3f7d43', axis: 'count', format: (v) => v },
]

// A rider still holding cash collected on a day other than today (not returned
// same-day) — the Riders table flags this in red.
function cashHoldingOverdue(sinceIso) {
  if (!sinceIso) return false
  const since = new Date(sinceIso)
  const now = new Date()
  return since.getFullYear() !== now.getFullYear() || since.getMonth() !== now.getMonth() || since.getDate() !== now.getDate()
}

// Worst customer rating tied to an order — the rider/delivery review and any
// chat (support thread) rating — so the row can flag it for the admin to check.
function orderFeedbackTone(order) {
  const ratings = [order.rider_review?.rating, ...(order.support_threads ?? []).map((t) => t.rating)].filter((r) => r != null)
  if (!ratings.length) return null
  const worst = Math.min(...ratings)
  return worst <= 2 ? 'negative' : worst === 3 ? 'medium' : 'positive'
}

const STATUS_LABELS = {
  pending_payment: 'Awaiting payment',
  confirmed: 'Confirmed',
  packing: 'Packing',
  ready_for_delivery: 'Ready for delivery',
  out_for_delivery: 'Out for delivery',
  completed: 'Delivered',
  cancelled: 'Cancelled',
}

const NEXT_ACTIONS = {
  pending_payment: [['cancelled', 'Cancel']],
  confirmed: [['packing', 'Start packing'], ['cancelled', 'Cancel']],
  packing: [['ready_for_delivery', 'Mark ready'], ['cancelled', 'Cancel']],
  ready_for_delivery: [['out_for_delivery', 'Send out'], ['cancelled', 'Cancel']],
  out_for_delivery: [['completed', 'Mark delivered']],
  completed: [],
  cancelled: [],
}

const STATUS_FILTERS = ['all', 'confirmed', 'packing', 'ready_for_delivery', 'out_for_delivery', 'completed', 'cancelled']
const PAGE_SIZES = [5, 10, 20, 50, 100, 500, 1000]

// Rows-per-page + page nav shown under a list. `total`/`pageCount` come from the
// server for big lists, or from the array length for small ones paged client-side.
function Pager({ page, pageCount, total, onPage, pageSize, onPageSize }) {
  return (
    <div className="admin-pager">
      <label>Show
        <select value={pageSize} onChange={(event) => onPageSize(Number(event.target.value))}>
          {PAGE_SIZES.map((n) => <option key={n} value={n}>{n}</option>)}
        </select>
        per page
      </label>
      {pageCount > 1 && (
        <span className="admin-pager-nav">
          <button type="button" className="act ghost" disabled={page <= 1} onClick={() => onPage(page - 1)}>‹ Prev</button>
          <span>Page {page} of {pageCount}</span>
          <button type="button" className="act ghost" disabled={page >= pageCount} onClick={() => onPage(page + 1)}>Next ›</button>
        </span>
      )}
      <span className="muted">{total} total</span>
    </div>
  )
}

// A rotating ring of dots + label, for every "Loading…" placeholder.
// A dropdown that opens *above* its button with its own scrollbar — for pickers
// near the bottom of a drawer, where a native <select> list would drop off
// the screen. options: [{ value, label }]
function UpwardPicker({ placeholder, options, onPick }) {
  const [open, setOpen] = useState(false)
  const wrapRef = useRef(null)

  useEffect(() => {
    if (!open) return undefined
    const onDown = (event) => { if (!wrapRef.current?.contains(event.target)) setOpen(false) }
    const onKey = (event) => { if (event.key === 'Escape') setOpen(false) }
    document.addEventListener('mousedown', onDown)
    document.addEventListener('keydown', onKey)
    return () => { document.removeEventListener('mousedown', onDown); document.removeEventListener('keydown', onKey) }
  }, [open])

  return (
    <div className="admin-up-picker" ref={wrapRef}>
      <button type="button" className="admin-order-picker admin-up-picker-btn" aria-haspopup="listbox" aria-expanded={open} onClick={() => setOpen((v) => !v)}>
        <span>{placeholder}</span><span aria-hidden>{open ? '▾' : '▴'}</span>
      </button>
      {open && (
        <ul className="admin-up-picker-list" role="listbox">
          {options.map((o) => (
            <li key={o.value}>
              <button type="button" role="option" aria-selected="false" onClick={() => { setOpen(false); onPick(o.value) }}>{o.label}</button>
            </li>
          ))}
          {options.length === 0 && <li className="muted">Nothing to choose.</li>}
        </ul>
      )}
    </div>
  )
}

function Loading({ children }) {
  return (
    <p className="admin-empty admin-loading" role="status">
      <span className="admin-spinner" aria-hidden="true">
        {Array.from({ length: 8 }, (_, i) => <span key={i} style={{ '--i': i }} />)}
      </span>
      {children}
    </p>
  )
}
// Left sidebar vs top-right. Support/Settings stay top-right (used less often,
// and Support carries the live badge next to the notification bell).
const PRIMARY_TABS = ['dashboard', 'orders', 'products', 'categories', 'customers', 'emails', 'riders', 'sellers', 'stores', 'branding', 'secure']
const TOP_TABS = ['support', 'settings']
const TAB_LABELS = {
  dashboard: 'Dashboard', orders: 'Orders', products: 'Products', categories: 'Categories',
  customers: 'Customers', emails: 'Emails', riders: 'Riders', sellers: 'Sellers', stores: 'Stores', branding: 'Store settings', secure: 'Secure access',
  support: 'Support', settings: 'Settings',
}
const TAB_ICONS = {
  dashboard: '\u{1F4CA}', orders: '\u{1F9FE}', products: '\u{1F4E6}', categories: '\u{1F5C2}️',
  customers: '\u{1F465}', emails: '\u{2709}\u{FE0F}', riders: '\u{1F6F5}', sellers: '\u{1F4BC}', stores: '\u{1F3EC}', branding: '\u{1F3A8}', secure: '\u{1F510}',
}
const SELLER_STATUS_FILTERS = ['pending', 'needs_changes', 'approved', 'rejected', 'suspended']
const SELLER_STATUS_LABELS = { pending: 'Pending', needs_changes: 'Changes requested', approved: 'Approved', rejected: 'Rejected', suspended: 'Suspended' }
const PRODUCT_STATUS_FILTERS = ['pending', 'approved', 'rejected']
const PRODUCT_STATUS_LABELS = { pending: 'Pending', approved: 'Approved', rejected: 'Rejected' }
const SELLER_ID_TYPE_LABELS = { aadhaar: 'Aadhaar', pan: 'PAN', passport: 'Passport', ssn: 'SSN', drivers_license: "Driver's License" }
const LEDGER_TYPE_LABELS = { order_credit: 'Order credit', refund_debit: 'Refund', payout_debit: 'Payout', return_pickup_fee: 'Return pickup fee', delivery_fee_charge: 'Delivery fee (refunded order)', shipping_label: 'Shipping label (NexTech)', tcs_gst: 'TCS withheld (GST sec. 52)', tds_194o: 'TDS withheld (sec. 194-O)' }
const EMPTY_BRANDING = { store_name: '', tagline: '', logo_url: '', favicon_url: '', theme: 'light', layout_width: 'boxed', color_brand: '#1f7a3d', color_accent: '#ffd23f', color_heading: '#18211c' }
const SOCIAL_PLATFORMS = [['facebook', 'Facebook'], ['x', 'X / Twitter'], ['instagram', 'Instagram'], ['linkedin', 'LinkedIn'], ['youtube', 'YouTube']]
const EMPTY_FOOTER = { copyright: '© {year} NexTech', app_store_url: '', play_store_url: '', socials: { facebook: '', x: '', instagram: '', linkedin: '', youtube: '' }, links: [], bg_color: '#f3f5f2', text_color: '#18211c' }

const ISSUE_LABELS = {
  item_missing: 'Item missing', item_damaged: 'Item damaged', wrong_item: 'Wrong item',
  not_delivered: 'Not delivered', payment_issue: 'Payment issue', other: 'Other',
  delivery: 'Delivery message',
  seller_product_issue: 'Seller: product issue', seller_other: 'Seller: other',
}
const SELLER_ISSUE_TYPES = ['seller_product_issue', 'seller_other']

const EMPTY_PRODUCT = { category_id: '', shop_id: '', name: '', sku: '', price: '', compare_at: '', inventory_quantity: 0, description: '', image_url: '', video_url: '', images: [], is_active: true, per_store_stock: false, store_stock: {}, variants: [], deal_type: '', is_exclusive_offer: false }

// Build the per-store stock grid ({ [storeId]: { is_stocked, base, variants: { [variantIndex]: qty } } })
// from a product's store_inventory rows.
// A store's row in the Store stock grid. Anything not yet set for that store
// falls back to the product's own Inventory / each variant's Stock, so the
// grid starts pre-filled and saving an untouched store keeps those numbers.
const storeStockRow = (form, storeId) => {
  const saved = form.store_stock?.[storeId] ?? {}
  const variants = {}
  ;(form.variants ?? []).forEach((v, i) => { variants[i] = saved.variants?.[i] ?? String(v.stock ?? '') })
  return { is_stocked: saved.is_stocked ?? true, base: saved.base ?? String(form.inventory_quantity ?? ''), variants }
}

const storeAddress = (store) => [store.line1, store.city, [store.state, store.postal_code].filter(Boolean).join(' ')].filter(Boolean).join(', ')

const storeStockFrom = (product) => {
  const idxById = new Map((product.variants ?? []).map((v, i) => [v.id, i]))
  const map = {}
  for (const row of (product.store_inventory ?? [])) {
    const s = (map[row.store_id] = map[row.store_id] ?? { is_stocked: true, base: '', variants: {} })
    if (row.product_variant_id == null) {
      s.base = String(row.quantity)
      s.is_stocked = !!row.is_stocked
    } else if (idxById.has(row.product_variant_id)) {
      s.variants[idxById.get(row.product_variant_id)] = String(row.quantity)
    }
  }
  return map
}
// Variant SKUs end in a single digit (-V1…-V9), so a product holds at most 9.
const MAX_VARIANTS = 9
// Mirrors App\Support\ProductImages::MAX_IMAGES.
const MAX_IMAGES = 8
const EMPTY_VARIANT = { label: '', sku: '', price: '', compare_at: '', stock: 0, image_url: '', is_active: true }
const dollarsOrBlank = (cents) => (cents != null ? (cents / 100).toFixed(2) : '')

const variantRowsFrom = (product) => (product.variants ?? []).map((v) => ({
  id: v.id, label: v.label, sku: v.sku, price: (v.price_cents / 100).toFixed(2), compare_at: dollarsOrBlank(v.compare_at_price_cents),
  stock: v.inventory_quantity, image_url: v.image_url ?? '', is_active: v.is_active,
}))
const EMPTY_CATEGORY = { name: '', slug: '', image_url: '', sort_order: 0, is_active: true, show_on_home: true }
const EMPTY_STORE = { name: '', line1: '', line2: '', city: '', state: '', postal_code: '', country: '', latitude: '', longitude: '', delivery_radius_km: 5, is_active: true }
const riderFormFrom = (rider) => ({
  id: rider.id,
  name: rider.name,
  phone: rider.phone ?? '',
  rider_is_active: !!rider.rider_is_active,
  rider_base_address: rider.rider_base_address ?? '',
  rider_base_lat: rider.rider_base_lat ?? '',
  rider_base_lng: rider.rider_base_lng ?? '',
  daily_target_hours: rider.daily_target_minutes ? String(rider.daily_target_minutes / 60) : '',
  store_ids: (rider.stores ?? []).map((s) => s.id),
})

const RIDER_STATUS = {
  clocked_in: { label: '🟢 On shift', color: '#2f6d34' },
  on_break: { label: '☕ Break', color: '#8a6d2f' },
  paused: { label: '⏸ Paused', color: '#a23b28' },
  off: { label: '⚪ Off the clock', color: '#7c857a' },
  off_roster: { label: '— off roster', color: '#7c857a' },
}
const fmtWorked = (m) => { const n = Math.max(0, Math.round(m || 0)); return n >= 60 ? `${Math.floor(n / 60)}h ${n % 60}m` : `${n}m` }
const DAY_STATUS = {
  full: { label: 'Full', color: '#2f6d34' },
  short: { label: 'Short', color: '#8a6d2f' },
  off: { label: 'Off', color: '#a0a7a0' },
  today: { label: 'Today', color: '#1f5fae' },
  pre: { label: '—', color: '#c0c6c0' },
}

function riderStatusChip(rider) {
  const a = rider.attendance || {}
  const s = RIDER_STATUS[a.status] || RIDER_STATUS.off
  const missed = (rider.declined_count ?? 0) + (rider.missed_count ?? 0)
  return (
    <>
      <span style={{ color: s.color, fontWeight: 600 }}>{s.label}</span>
      {a.today_worked_minutes ? <span className="admin-note">{fmtWorked(a.today_worked_minutes)} today · {fmtWorked(a.week_worked_minutes)} this week</span>
        : a.week_worked_minutes ? <span className="admin-note">{fmtWorked(a.week_worked_minutes)} this week</span> : null}
      {rider.online
        ? <span className="admin-note" style={{ color: '#2f6d34' }}>online now</span>
        : rider.last_seen_at && <span className="admin-note" title={`last seen ${new Date(rider.last_seen_at).toLocaleString()}`}>seen {new Date(rider.last_seen_at).toLocaleDateString()}</span>}
      {a.unavailable_reason && <span className="admin-note" title={a.unavailable_reason}>&ldquo;{a.unavailable_reason}&rdquo;</span>}
      {rider.offers_count > 0 && (
        <span className="admin-note" style={missed > 0 ? { color: '#a23b28' } : undefined} title={`${rider.offers_count} offers · ${rider.declined_count ?? 0} rejected · ${rider.missed_count ?? 0} missed`}>
          {rider.acceptance_rate != null ? `${Math.round(rider.acceptance_rate * 100)}% accepted` : ''}{missed > 0 ? ` · ✗${missed}` : ''}
        </span>
      )}
    </>
  )
}
const EMPTY_PAGE = { title: '', slug: '', banner_image: '', content: '', sections: [], footer_group: 'company', menu_placements: ['main_footer'], show_in_footer: true, is_published: true, sort_order: 0 }
const FOOTER_GROUP_LABELS = { company: 'Company info', legal: 'Customer service', help: 'Help', bottom: 'Lower footer', blog: 'Blog (not shown in footer columns)' }
const FOOTER_COLUMNS = ['company', 'legal', 'help', 'bottom']
// Where a page is actually linked from on the live site — purely for admin
// tracking/organization; show_in_footer is still what gates the real render.
const MENU_PLACEMENT_OPTIONS = ['main_menu', 'main_footer', 'seller_footer', 'blog']
const MENU_PLACEMENT_LABELS = { main_menu: 'Main menu (storefront Help menu)', main_footer: 'Main footer', seller_footer: 'Seller Center footer', blog: 'Blog' }
const sectionLabel = (type) => (SECTION_TYPES.find(([value]) => value === type) ?? [type, type])[1]

// Reference rows for the "Formatting guide" tab. Each `code` is fed through the
// real renderMarkdown() so the preview always matches the storefront.
const MD_GUIDE = [
  ['Headings', '# Biggest heading\n## Section heading\n### Sub-heading', 'Use # to #### — one # is the biggest.'],
  ['Bold', 'Prices are **final** at checkout.', 'Tip: a paragraph that is nothing but a bold phrase becomes a lead-in heading on the page.'],
  ['Italic', 'Delivery is *usually* under 15 minutes.', ''],
  ['Inline code', 'Enter the code `SAVE20` at checkout.', ''],
  ['Link', 'Read our [returns policy](https://example.com/returns).', 'The address must start with https://, / or mailto: — anything else shows as plain text.'],
  ['Bullet list', '- Fresh produce\n- Dairy & eggs\n- Pantry staples', 'Start each line with - or *.'],
  ['Numbered list', '1. Add items to your cart\n2. Check out\n3. Track your rider', ''],
  ['Divider', 'Above the line.\n\n---\n\nBelow the line.', 'Three or more dashes on their own line.'],
  ['Paragraphs', 'First paragraph.\n\nSecond paragraph — leave a blank line between them.', ''],
]

const dollars = (cents) => ((cents ?? 0) / 100).toFixed(2)
const toCents = (value) => Math.max(0, Math.round(Number(value || 0) * 100))

// Alert for a new customer support message. Web Audio => no asset/CSP.
// A two-run DESCENDING tone — clearly audible, but distinct from the ASCENDING
// new-order alert so the admin can tell them apart by ear.
function playChime() {
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
      gain.gain.exponentialRampToValueAtTime(0.24, ctx.currentTime + at + 0.02)
      gain.gain.exponentialRampToValueAtTime(0.0001, ctx.currentTime + at + dur)
      osc.start(ctx.currentTime + at)
      osc.stop(ctx.currentTime + at + dur + 0.02)
    }
    for (const base of [0, 0.85]) {
      blip(988, base)
      blip(740, base + 0.16)
      blip(587, base + 0.32, 0.3)
    }
    setTimeout(() => ctx.close(), 1600)
  } catch { /* audio blocked — the toast still shows */ }
}

// A more insistent alert for a NEW ORDER to pack — two rising three-note runs.
function playOrderAlert() {
  try {
    const Ctx = window.AudioContext || window.webkitAudioContext
    if (!Ctx) return
    const ctx = new Ctx()
    const beep = (freq, at, dur = 0.18) => {
      const osc = ctx.createOscillator()
      const gain = ctx.createGain()
      osc.connect(gain); gain.connect(ctx.destination)
      osc.type = 'triangle'
      osc.frequency.value = freq
      gain.gain.setValueAtTime(0.0001, ctx.currentTime + at)
      gain.gain.exponentialRampToValueAtTime(0.25, ctx.currentTime + at + 0.02)
      gain.gain.exponentialRampToValueAtTime(0.0001, ctx.currentTime + at + dur)
      osc.start(ctx.currentTime + at)
      osc.stop(ctx.currentTime + at + dur + 0.02)
    }
    for (const base of [0, 0.9]) {
      beep(587, base)
      beep(784, base + 0.16)
      beep(1047, base + 0.32, 0.3)
    }
    setTimeout(() => ctx.close(), 1700)
  } catch { /* audio blocked — the toast still shows */ }
}

// Fee settings <-> a dollar/percent form the admin edits.
function feesToForm(s) {
  return {
    delivery_mode: s.delivery_mode ?? 'fixed',
    delivery_fee: dollars(s.delivery_fee_cents),
    delivery_near_fee: dollars(s.delivery_near_fee_cents),
    delivery_far_fee: dollars(s.delivery_far_fee_cents),
    free_delivery_threshold: dollars(s.free_delivery_threshold_cents),
    handling_fee: dollars(s.handling_fee_cents),
    small_cart_fee: dollars(s.small_cart_fee_cents),
    small_cart_min: dollars(s.small_cart_min_cents),
    tax_rate_pct: ((s.tax_rate_bps ?? 0) / 100).toFixed(2),
    commission_rate_pct: ((s.commission_rate_bps ?? 0) / 100).toFixed(2),
    min_payout: dollars(s.min_payout_cents),
    max_payout: dollars(s.max_payout_cents),
    daily_payout_cap: dollars(s.daily_payout_cap_cents),
    return_window_days: String(s.return_window_days ?? 30),
    max_return_days: String(s.max_return_days ?? 90),
    return_pickup_fee: dollars(s.return_pickup_fee_cents),
    label_postage: dollars(s.label_postage_cents),
    rider_base_pay: dollars(s.rider_base_pay_cents),
    rider_per_mile: dollars(s.rider_per_mile_cents),
    rider_min_payout: dollars(s.rider_min_payout_cents),
    rider_max_payout: dollars(s.rider_max_payout_cents),
  }
}

function formToFees(f) {
  return {
    delivery_mode: f.delivery_mode,
    delivery_fee_cents: toCents(f.delivery_fee),
    delivery_near_fee_cents: toCents(f.delivery_near_fee),
    delivery_far_fee_cents: toCents(f.delivery_far_fee),
    free_delivery_threshold_cents: toCents(f.free_delivery_threshold),
    handling_fee_cents: toCents(f.handling_fee),
    small_cart_fee_cents: toCents(f.small_cart_fee),
    small_cart_min_cents: toCents(f.small_cart_min),
    tax_rate_bps: Math.max(0, Math.min(10000, Math.round(Number(f.tax_rate_pct || 0) * 100))),
    commission_rate_bps: Math.max(0, Math.min(10000, Math.round(Number(f.commission_rate_pct || 0) * 100))),
    min_payout_cents: toCents(f.min_payout),
    max_payout_cents: toCents(f.max_payout),
    daily_payout_cap_cents: toCents(f.daily_payout_cap),
    return_window_days: Math.max(0, Math.min(365, Math.round(Number(f.return_window_days || 0)))),
    max_return_days: Math.max(0, Math.min(365, Math.round(Number(f.max_return_days || 0)))),
    return_pickup_fee_cents: toCents(f.return_pickup_fee),
    label_postage_cents: toCents(f.label_postage),
    rider_base_pay_cents: toCents(f.rider_base_pay),
    rider_per_mile_cents: toCents(f.rider_per_mile),
    rider_min_payout_cents: toCents(f.rider_min_payout),
    rider_max_payout_cents: toCents(f.rider_max_payout),
  }
}

async function readJson(response) {
  const text = await response.text()
  if (!text) return {}
  const start = Math.min(...['{', '['].map((token) => {
    const index = text.indexOf(token)
    return index === -1 ? text.length : index
  }))
  return JSON.parse(text.slice(start))
}

// Like readJson, but rejects on a non-2xx response instead of silently
// resolving with an error body — so a failed request shows up in .catch()
// instead of leaving a "Loading…" placeholder up forever.
async function fetchJson(url, options) {
  const response = await fetch(url, options)
  const data = await readJson(response)
  if (!response.ok) throw new Error(data.message ?? 'Request failed.')

  return data
}

export default function Admin({ token, onClose }) {
  const [tab, setTab] = useState('dashboard')
  const [navOpen, setNavOpen] = useState(() => {
    try { return localStorage.getItem('gdp_admin_nav') !== '0' } catch { return true }
  })
  const [metrics, setMetrics] = useState(null)
  const [chart, setChart] = useState(null)
  const [chartBucket, setChartBucket] = useState('day')
  const [chartMetrics, setChartMetrics] = useState(['orders']) // any non-empty subset of CHART_LINES keys
  const [compare, setCompare] = useState(null)
  const [comparePreset, setComparePreset] = useState('day')
  const [compareDays, setCompareDays] = useState(7)
  const [compareDaysDraft, setCompareDaysDraft] = useState('7')
  const [compareMetric, setCompareMetric] = useState('orders') // 'orders' | 'revenue_cents'
  const [insights, setInsights] = useState(null)
  const [orders, setOrders] = useState([])
  const [products, setProducts] = useState([])
  const [categories, setCategories] = useState([])
  const [customers, setCustomers] = useState([])
  const [customerDetail, setCustomerDetail] = useState(null)
  const [customerQuery, setCustomerQuery] = useState('') // Customers search
  const [orderDetail, setOrderDetail] = useState(null)
  const [statusFilter, setStatusFilter] = useState('all')
  const [productSearch, setProductSearch] = useState('')
  const [productSort, setProductSort] = useState('newest')
  const [productStore, setProductStore] = useState('')
  const [productCategory, setProductCategory] = useState('')
  // Default 'all' — admin's own products (always approved) stay visible by
  // default; a pending seller submission is flagged by its status pill rather
  // than requiring the admin to switch filters to notice it exists.
  const [productStatus, setProductStatus] = useState('all')
  // Rows per page — shared across every list, remembered per browser.
  const [pageSize, setPageSizeRaw] = useState(() => {
    const n = Number(localStorage.getItem('gdp_admin_page_size'))
    return PAGE_SIZES.includes(n) ? n : 10
  })
  // Server-paginated lists: current page + the server's meta.
  const [ordersPage, setOrdersPage] = useState(1)
  const [ordersMeta, setOrdersMeta] = useState(null)
  const [productsPage, setProductsPage] = useState(1)
  const [productsMeta, setProductsMeta] = useState(null)
  const [customersPage, setCustomersPage] = useState(1)
  const [customersMeta, setCustomersMeta] = useState(null)
  // Small lists paged client-side.
  const [categoriesPage, setCategoriesPage] = useState(1)
  const [ridersPage, setRidersPage] = useState(1)
  const [storesPage, setStoresPage] = useState(1)
  const [sellersPage, setSellersPage] = useState(1)
  const setPageSize = useCallback((n) => {
    setPageSizeRaw(n)
    try { localStorage.setItem('gdp_admin_page_size', String(n)) } catch { /* private mode */ }
    setOrdersPage(1); setProductsPage(1); setCustomersPage(1)
    setCategoriesPage(1); setRidersPage(1); setStoresPage(1); setSellersPage(1)
  }, [])
  const pageSlice = (list, page) => list.slice((page - 1) * pageSize, page * pageSize)
  const [productForm, setProductForm] = useState(null)
  const [categoryForm, setCategoryForm] = useState(null)
  const [stores, setStores] = useState([])
  const [storeForm, setStoreForm] = useState(null)
  const [pages, setPages] = useState([])
  const [pageForm, setPageForm] = useState(null)
  const [pagePreview, setPagePreview] = useState(false)
  const [pagesExpanded, setPagesExpanded] = useState(false)
  const [expandedPageGroups, setExpandedPageGroups] = useState({})
  const [pageGroupFilter, setPageGroupFilter] = useState(null)
  const [pageSettingsOpen, setPageSettingsOpen] = useState(false)

  // Clicking a group's own header should actually show that group — not just
  // expand/collapse the sidebar while the main panel keeps showing whatever
  // page (or none) was open before. WordPress-style: shows the group's page
  // list (scopes the table to it) rather than jumping into an editor — the
  // admin picks a page from the list and hits Edit there. A second click
  // (already open) just collapses it — including its sub-sub-groups, e.g.
  // Main footer's columns — without touching whatever's shown on the right.
  function selectPageGroup(key) {
    const willOpen = !expandedPageGroups[key]
    setExpandedPageGroups((cur) => ({ ...cur, [key]: willOpen }))
    if (!willOpen) return
    setPageGroupFilter(key)
    goTab('pages')
    setPageForm(null)
  }

  function selectPageInGroup(page, key) {
    setPageGroupFilter(key)
    goTab('pages')
    editPage(page)
  }
  const [courierDraft, setCourierDraft] = useState({})
  const [riders, setRiders] = useState([])
  const [riderForm, setRiderForm] = useState(null)
  const [riderEmail, setRiderEmail] = useState('')
  const [riderHireStoreId, setRiderHireStoreId] = useState('')
  const [riderDetail, setRiderDetail] = useState(null)
  const [riderApps, setRiderApps] = useState([])
  const [sellers, setSellers] = useState([])
  const [sellerStatus, setSellerStatus] = useState('all')
  const [sellerDetail, setSellerDetail] = useState(null)
  const [shops, setShops] = useState([])
  const [riderMonth, setRiderMonth] = useState(() => { const d = new Date(); return new Date(d.getFullYear(), d.getMonth(), 1) })
  const [riderReport, setRiderReport] = useState(null)
  const [settings, setSettings] = useState(null)
  // The admin's currency / country switch (top bar): Dashboard, Orders,
  // Products, Sellers and Settings → charges show only that market's entries.
  const [adminMarket, setAdminMarket] = useState(() => { try { return localStorage.getItem('nextech_admin_market') || '' } catch { return '' } })
  const [feesForm, setFeesForm] = useState(null)
  const [brandingForm, setBrandingForm] = useState(null)
  const [footerForm, setFooterForm] = useState(null)
  const [paymentsForm, setPaymentsForm] = useState(null)
  const [courierForm, setCourierForm] = useState(null)
  const [accountForm, setAccountForm] = useState({ name: '', email: '', phone: '' })
  const [secureGate, setSecureGate] = useState('locked') // locked | code | unlocked
  const [secureSecret, setSecureSecret] = useState('') // password or OTP code
  const [secureToken, setSecureToken] = useState('')
  const [secureMsg, setSecureMsg] = useState('')
  const [imgBusy, setImgBusy] = useState(false)
  const [threads, setThreads] = useState([])
  const [threadStatus, setThreadStatus] = useState('open')
  const [thread, setThread] = useState(null)
  const [threadReply, setThreadReply] = useState('')
  const [threadPhotos, setThreadPhotos] = useState([]) // photo URLs for the next admin reply
  const chatLogRef = useRef(null)
  const [refundForm, setRefundForm] = useState({ items: [], amount: '', reason: '', charge_pickup: true, charge_delivery: true })
  const [giftIssued, setGiftIssued] = useState(null)
  const [supportBadge, setSupportBadge] = useState(0)
  const [pendingThreads, setPendingThreads] = useState([])
  const [supportToasts, setSupportToasts] = useState([])
  const [orderToasts, setOrderToasts] = useState([])
  const [ratingToasts, setRatingToasts] = useState([])
  const [speakerOpen, setSpeakerOpen] = useState(false)
  const [notifications, setNotifications] = useState({ awaiting_packing: [], refused_cod: [], cash_overdue: [], negative_feedback: [], negative_feedback_total: 0, financial_activity: [], financial_activity_total: 0, recent_ratings: [] })
  const [bellOpen, setBellOpen] = useState(false)
  const [dismissedNotifs, setDismissedNotifs] = useState(() => {
    try { return new Set(JSON.parse(localStorage.getItem('gdp_dismissed_notifs') ?? '[]')) } catch { return new Set() }
  })
  const dismissNotif = (key) => setDismissedNotifs((prev) => {
    const next = new Set(prev)
    next.add(key)
    try { localStorage.setItem('gdp_dismissed_notifs', JSON.stringify([...next])) } catch { /* private mode */ }
    return next
  })
  const [soundMuted, setSoundMuted] = useState(() => { try { return localStorage.getItem('gdp_support_muted') === '1' } catch { return false } })
  const seenRef = useRef(null)
  const orderSeenRef = useRef(null)
  const refusedSeenRef = useRef(null)
  const notifSeenRef = useRef(null)
  const ratingSeenRef = useRef(null)
  const [bellShaking, setBellShaking] = useState(false)
  const [busyId, setBusyId] = useState(null)
  const [message, setMessage] = useState('')
  // Per-list "fetch in flight" flags so a slow API shows "Loading…" instead of
  // an empty-result message.
  const [listBusy, setListBusy] = useState({})
  // Flip the flag on a microtask so this isn't a synchronous setState when a
  // loader is called straight from an effect; clear it when the fetch settles.
  const track = useCallback((key, promise) => {
    Promise.resolve().then(() => setListBusy((b) => ({ ...b, [key]: true })))
    return promise.finally(() => setListBusy((b) => ({ ...b, [key]: false })))
  }, [])

  const authHeaders = useCallback(() => ({ Accept: 'application/json', Authorization: `Bearer ${token}`, ...(adminMarket ? { 'X-Market': adminMarket } : {}) }), [token, adminMarket])
  const jsonHeaders = useCallback(() => ({ ...authHeaders(), 'Content-Type': 'application/json' }), [authHeaders])

  const fail = (error) => setMessage(error?.message ?? 'Something went wrong.')

  const loadMetrics = useCallback(() => {
    fetch(`${API_URL}/admin/metrics`, { headers: authHeaders() }).then(readJson)
      .then((data) => setMetrics(data.data)).catch(() => setMessage('Could not load dashboard metrics.'))
  }, [authHeaders])

  const loadChart = useCallback(() => {
    fetchJson(`${API_URL}/admin/metrics/timeseries?bucket=${chartBucket}&tz=${encodeURIComponent(ADMIN_TZ)}`, { headers: authHeaders() })
      .then((data) => setChart(data.data)).catch(() => setMessage('Could not load the orders chart.'))
  }, [authHeaders, chartBucket])

  const loadCompare = useCallback(() => {
    const query = comparePreset === 'custom' ? `preset=custom&days=${compareDays}` : `preset=${comparePreset}`
    fetchJson(`${API_URL}/admin/metrics/compare?${query}&tz=${encodeURIComponent(ADMIN_TZ)}`, { headers: authHeaders() })
      .then((data) => setCompare(data.data)).catch(() => setMessage('Could not load period comparisons.'))
  }, [authHeaders, comparePreset, compareDays])

  const loadInsights = useCallback(() => {
    fetchJson(`${API_URL}/admin/metrics/insights?tz=${encodeURIComponent(ADMIN_TZ)}`, { headers: authHeaders() })
      .then((data) => setInsights(data.data)).catch(() => setMessage('Could not load dashboard insights.'))
  }, [authHeaders])

  const loadOrders = useCallback(() => {
    const qs = new URLSearchParams({ page: ordersPage, per_page: pageSize })
    if (statusFilter !== 'all') qs.set('status', statusFilter)
    track('orders', fetch(`${API_URL}/admin/orders?${qs}`, { headers: authHeaders() }).then(readJson)
      .then((data) => { setOrders(data.data ?? []); setOrdersMeta(data.meta ?? null) }).catch(() => setMessage('Could not load orders.')))
    fetch(`${API_URL}/admin/riders`, { headers: authHeaders() }).then(readJson)
      .then((data) => setRiders(data.data ?? [])).catch(() => {})
  }, [authHeaders, statusFilter, ordersPage, pageSize, track])

  const loadProducts = useCallback(() => {
    const qs = new URLSearchParams({ page: productsPage, per_page: pageSize, sort: productSort })
    if (productSearch.trim()) qs.set('search', productSearch.trim())
    if (productStore) qs.set('store_id', productStore)
    if (productCategory) qs.set('category_id', productCategory)
    if (productStatus !== 'all') qs.set('status', productStatus)
    track('products', fetch(`${API_URL}/admin/products?${qs}`, { headers: authHeaders() }).then(readJson)
      .then((data) => { setProducts(data.data ?? []); setProductsMeta(data.meta ?? null) }).catch(() => setMessage('Could not load products.')))
  }, [authHeaders, productSearch, productSort, productStore, productCategory, productStatus, productsPage, pageSize, track])

  const loadCategories = useCallback(() => {
    track('categories', fetch(`${API_URL}/admin/categories`, { headers: authHeaders() }).then(readJson)
      .then((data) => setCategories(data.data ?? [])).catch(() => setMessage('Could not load categories.')))
  }, [authHeaders, track])

  const loadCustomers = useCallback(() => {
    const qs = new URLSearchParams({ page: customersPage, per_page: pageSize, ...(customerQuery.trim().length >= 2 ? { search: customerQuery.trim() } : {}) })
    track('customers', fetch(`${API_URL}/admin/customers?${qs}`, { headers: authHeaders() }).then(readJson)
      .then((data) => { setCustomers(data.data ?? []); setCustomersMeta(data.meta ?? null) }).catch(() => setMessage('Could not load customers.')))
  }, [authHeaders, customersPage, pageSize, track, customerQuery])

  const loadRiders = useCallback(() => {
    track('riders', fetch(`${API_URL}/admin/riders`, { headers: authHeaders() }).then(readJson)
      .then((data) => setRiders(data.data ?? [])).catch(() => setMessage('Could not load riders.')))
  }, [authHeaders, track])

  const loadSellers = useCallback(() => {
    const qs = new URLSearchParams(sellerStatus === 'all' ? {} : { status: sellerStatus })
    track('sellers', fetch(`${API_URL}/admin/sellers?${qs}`, { headers: authHeaders() }).then(readJson)
      .then((data) => setSellers(data.data ?? [])).catch(() => setMessage('Could not load seller applications.')))
  }, [authHeaders, sellerStatus, track])

  // Approved shops, for the Products form's Shop picker — kept separate from
  // loadSellers() since it's needed on the Products tab too, not just Sellers.
  const loadShops = useCallback(() => {
    fetch(`${API_URL}/admin/sellers/shops`, { headers: authHeaders() }).then(readJson)
      .then((data) => setShops(data.data ?? [])).catch(() => {})
  }, [authHeaders])

  const loadSettings = useCallback(() => {
    fetch(`${API_URL}/admin/settings`, { headers: authHeaders() }).then(readJson)
      .then((data) => {
        setSettings(data.data)
        setFeesForm(feesToForm(data.data))
        setBrandingForm({ ...EMPTY_BRANDING, ...(data.data.branding ?? {}) })
        setFooterForm({ ...EMPTY_FOOTER, ...(data.data.footer ?? {}), socials: { ...EMPTY_FOOTER.socials, ...(data.data.footer?.socials ?? {}) }, links: (data.data.footer?.links ?? []).map((l) => ({ ...l })) })
        setPaymentsForm({ stripe_key: data.data.payments?.stripe_key ?? '', stripe_secret: '', stripe_webhook_secret: '' })
        setCourierForm({
          courier_provider: data.data.courier?.provider ?? 'mock',
          courier_base_url: data.data.courier?.base_url ?? '',
          courier_account_code: data.data.courier?.account_code ?? '',
          courier_api_key: '',
          courier_api_secret: '',
        })
      })
      .catch(() => setMessage('Could not load settings.'))
  }, [authHeaders])

  const loadStores = useCallback(() => {
    track('stores', fetch(`${API_URL}/admin/stores`, { headers: authHeaders() }).then(readJson)
      .then((data) => setStores(data.data ?? [])).catch(() => setMessage('Could not load stores.')))
  }, [authHeaders, track])

  const loadPages = useCallback(() => {
    fetch(`${API_URL}/admin/pages`, { headers: authHeaders() }).then(readJson)
      .then((data) => setPages(data.data ?? [])).catch(() => setMessage('Could not load pages.'))
  }, [authHeaders])

  const loadThreads = useCallback(() => {
    // "sellers" isn't a status — it swaps the filter dimension to issue_type,
    // sent as a comma-separated list (AdminSupportController::index() accepts
    // either a single value or several this way).
    const query = threadStatus === 'sellers' ? `?issue_type=${SELLER_ISSUE_TYPES.join(',')}`
      : threadStatus === 'all' ? '' : `?status=${threadStatus}`
    fetch(`${API_URL}/admin/support/threads${query}`, { headers: authHeaders() }).then(readJson)
      .then((data) => setThreads(data.data ?? [])).catch(() => setMessage('Could not load support threads.'))
  }, [authHeaders, threadStatus])

  useEffect(() => { if (tab === 'dashboard') loadMetrics() }, [tab, loadMetrics])
  useEffect(() => { loadPages() }, [loadPages])
  useEffect(() => { if (tab === 'dashboard') loadChart() }, [tab, loadChart])
  useEffect(() => { if (tab === 'dashboard') loadCompare() }, [tab, loadCompare])
  useEffect(() => { if (tab === 'dashboard') loadInsights() }, [tab, loadInsights])
  useEffect(() => { if (tab === 'orders') loadOrders() }, [tab, loadOrders])
  // Keep the Orders board current so rider accept / reject / timeout shows within
  // seconds (and each poll drives the server-side offer-timeout sweep).
  useEffect(() => {
    if (tab !== 'orders') return undefined
    const t = setInterval(loadOrders, 15000)
    return () => clearInterval(t)
  }, [tab, loadOrders])
  useEffect(() => { if (tab === 'products') { loadProducts(); loadCategories(); loadStores(); loadShops() } }, [tab, loadProducts, loadCategories, loadStores, loadShops])
  useEffect(() => { if (tab === 'categories') loadCategories() }, [tab, loadCategories])
  useEffect(() => { if (tab === 'customers') loadCustomers() }, [tab, loadCustomers])
  const loadRiderApps = useCallback(() => {
    fetch(`${API_URL}/admin/rider-applications`, { headers: authHeaders() })
      .then(readJson)
      .then((data) => setRiderApps(data?.data ?? []))
      .catch(() => {})
  }, [authHeaders])

  useEffect(() => { if (tab === 'riders') { loadRiders(); loadStores(); loadRiderApps() } }, [tab, loadRiders, loadStores, loadRiderApps])
  useEffect(() => { if (tab === 'sellers') loadSellers() }, [tab, loadSellers])
  useEffect(() => { if (tab === 'stores') loadStores() }, [tab, loadStores])
  useEffect(() => { if (tab === 'support') loadThreads() }, [tab, loadThreads])
  const threadId = thread?.id ?? null
  useEffect(() => {
    if (!threadId) return
    const timer = setInterval(() => {
      fetch(`${API_URL}/admin/support/threads/${threadId}`, { headers: authHeaders() }).then(readJson)
        .then((data) => setThread((cur) => (cur && cur.id === data.data.id ? data.data : cur))).catch(() => {})
    }, 5000)
    return () => clearInterval(timer)
  }, [threadId, authHeaders])
  // Keep the chat pinned to the newest message — on open, after sending, and
  // when the 5s poll above brings in a customer's reply.
  const threadMessageCount = thread?.messages?.length ?? 0
  const lastThreadMessageId = thread?.messages?.[threadMessageCount - 1]?.id ?? null
  useEffect(() => {
    if (!chatLogRef.current) return
    chatLogRef.current.scrollTop = chatLogRef.current.scrollHeight
  }, [threadId, threadMessageCount, lastThreadMessageId])
  useEffect(() => { if (tab === 'settings') { loadSettings(); loadStores() } }, [tab, loadSettings, loadStores])
  useEffect(() => { if (tab === 'branding' || tab === 'secure' || tab === 'footer') loadSettings() }, [tab, loadSettings])

  // Background notification poll — runs on every admin tab so a new customer
  // message chimes and toasts even while working elsewhere.
  useEffect(() => {
    let stopped = false
    const check = async () => {
      try {
        const data = await readJson(await fetch(`${API_URL}/admin/support/threads?status=open`, { headers: authHeaders() }))
        if (stopped) return
        const pending = (data.data ?? []).filter((t) => t.needs_reply)
        setSupportBadge(pending.length)
        setPendingThreads(pending)
        const map = Object.fromEntries(pending.map((t) => [t.id, t.last_message_at]))
        if (seenRef.current === null) { seenRef.current = map; return } // seed, don't chime on first load
        const fresh = pending.filter((t) => seenRef.current[t.id] !== t.last_message_at)
        seenRef.current = map
        if (fresh.length) {
          if (!soundMuted) playChime()
          setSupportToasts((cur) => [
            ...fresh.map((t) => ({ id: t.id, text: `New message${t.order_id ? ` · order #${t.order_id}` : ''} — ${t.user?.email ?? 'customer'}` })),
            ...cur,
          ].slice(0, 4))
        }
      } catch { /* keep last */ }

      // A new order that's paid/confirmed and waiting to be packed — alert the
      // admin the same way (louder tone + a toast) even from another tab.
      try {
        const od = await readJson(await fetch(`${API_URL}/admin/orders?per_page=8`, { headers: authHeaders() }))
        if (stopped) return
        const toPack = (od.data ?? []).filter((o) => o.status === 'confirmed')
        if (orderSeenRef.current === null) {
          orderSeenRef.current = new Set(toPack.map((o) => o.id)) // seed, don't alert for existing
        } else {
          const fresh = toPack.filter((o) => !orderSeenRef.current.has(o.id))
          fresh.forEach((o) => orderSeenRef.current.add(o.id))
          if (fresh.length) {
            if (!soundMuted) playOrderAlert()
            setOrderToasts((cur) => [
              ...fresh.map((o) => ({ id: o.id, text: `New order #${o.id} — ${money(o.total_cents, o.currency)} · ${o.user?.email ?? 'customer'} — start packing` })),
              ...cur,
            ].slice(0, 4))
          }
        }

        // A rider reported the customer refused to pay for a COD delivery —
        // the order is auto-cancelled; alert the admin to follow up.
        const refused = (od.data ?? []).filter((o) => o.status === 'cancelled' && o.cancelled_by === 'rider')
        if (refusedSeenRef.current === null) {
          refusedSeenRef.current = new Set(refused.map((o) => o.id)) // seed, don't alert for existing
        } else {
          const freshRefused = refused.filter((o) => !refusedSeenRef.current.has(o.id))
          freshRefused.forEach((o) => refusedSeenRef.current.add(o.id))
          if (freshRefused.length) {
            if (!soundMuted) playOrderAlert()
            setOrderToasts((cur) => [
              ...freshRefused.map((o) => ({ id: `refused-${o.id}`, text: `Order #${o.id} cancelled — customer refused to pay${o.cancel_reason ? `: ${o.cancel_reason}` : ''}` })),
              ...cur,
            ].slice(0, 4))
          }
        }
      } catch { /* keep last */ }

      // Standing issues for the notification bell: new orders to pack,
      // refused C.O.D., overdue rider cash, negative feedback, refunds/gift cards.
      try {
        const nd = await readJson(await fetch(`${API_URL}/admin/notifications`, { headers: authHeaders() }))
        if (stopped) return
        const data = nd.data ?? { awaiting_packing: [], refused_cod: [], cash_overdue: [], negative_feedback: [], negative_feedback_total: 0, financial_activity: [], financial_activity_total: 0, recent_ratings: [] }
        setNotifications(data)

        // Every new rating — good or bad — gets a brief toast that fades on
        // its own; it's "here's what just happened," not something to track.
        const ratingKey = (r) => `rate-${r.source}-${r.order_id}-${r.at}`
        const ratingKeys = new Set((data.recent_ratings ?? []).map(ratingKey))
        if (ratingSeenRef.current === null) {
          ratingSeenRef.current = ratingKeys // seed, don't toast for what's already there
        } else {
          const freshRatings = (data.recent_ratings ?? []).filter((r) => !ratingSeenRef.current.has(ratingKey(r)))
          ratingSeenRef.current = ratingKeys
          freshRatings.forEach((r) => {
            const id = ratingKey(r)
            const tone = r.rating <= 2 ? 'bad' : r.rating === 3 ? 'mid' : 'good'
            const stars = '★'.repeat(r.rating) + '☆'.repeat(5 - r.rating)
            const who = r.source === 'chat' ? 'Chat rating' : r.source === 'site' ? 'Site feedback' : 'Delivery rating'
            setRatingToasts((cur) => [
              ...cur,
              { id, tone, text: `${stars} ${who}${r.order_id ? ` — order #${r.order_id}` : ''}${r.comment ? ` — “${r.comment}”` : ''}`, orderId: r.order_id },
            ].slice(-4))
            setTimeout(() => setRatingToasts((cur) => cur.filter((t) => t.id !== id)), 6000)
          })
        }

        // One-shot ring when a genuinely new item shows up — not a permanent
        // loop for as long as anything is outstanding.
        const keys = new Set([
          ...(data.refused_cod ?? []).map((o) => `refused-${o.order_id}`),
          ...(data.cash_overdue ?? []).map((c) => `cash-${c.rider_id}`),
          ...(data.negative_feedback ?? []).map((f) => `fb-${f.source}-${f.order_id}-${f.at}`),
          ...(data.financial_activity ?? []).map((a) => `fin-${a.type}-${a.order_id}-${a.at}`),
        ])
        if (notifSeenRef.current === null) {
          notifSeenRef.current = keys // seed, don't ring for what's already there
        } else {
          const isNew = [...keys].some((k) => !notifSeenRef.current.has(k))
          notifSeenRef.current = keys
          if (isNew) {
            setBellShaking(true)
            setTimeout(() => setBellShaking(false), 1000)
          }
        }
      } catch { /* keep last */ }
    }
    // Delay the first poll so it doesn't compete with the tab's own requests
    // on a single-threaded dev server.
    const kick = setTimeout(check, 2500)
    const timer = setInterval(check, 10000)
    return () => { stopped = true; clearTimeout(kick); clearInterval(timer) }
  }, [authHeaders, soundMuted])

  async function saveStore(event) {
    event.preventDefault()
    setMessage('')
    const { id, latitude, longitude, ...rest } = storeForm
    const payload = { ...rest, country: rest.country || activeMarket, delivery_radius_km: Number(rest.delivery_radius_km) }
    if (String(latitude).trim() !== '' && String(longitude).trim() !== '') {
      payload.latitude = Number(latitude)
      payload.longitude = Number(longitude)
    }
    try {
      const response = await fetch(`${API_URL}/admin/stores${id ? `/${id}` : ''}`, { method: id ? 'PATCH' : 'POST', headers: jsonHeaders(), body: JSON.stringify(payload) })
      const data = await readJson(response)
      if (!response.ok) throw new Error(data.message ?? Object.values(data.errors ?? {})[0]?.[0] ?? 'Could not save the store.')
      setStoreForm(null)
      loadStores()
    } catch (error) { fail(error) }
  }

  async function removeStore(store) {
    if (!window.confirm(`Delete ${store.name}?`)) return
    setMessage('')
    try {
      const response = await fetch(`${API_URL}/admin/stores/${store.id}`, { method: 'DELETE', headers: authHeaders() })
      if (!response.ok && response.status !== 204) throw new Error((await readJson(response)).message ?? 'Could not delete the store.')
      loadStores()
    } catch (error) { fail(error) }
  }

  async function addRider(event) {
    event.preventDefault()
    setMessage('')
    const email = riderEmail.trim()
    if (!email || !riderHireStoreId) return
    try {
      const response = await fetch(`${API_URL}/admin/riders`, { method: 'POST', headers: jsonHeaders(), body: JSON.stringify({ email, store_ids: [Number(riderHireStoreId)] }) })
      const data = await readJson(response)
      if (!response.ok) throw new Error(data.message ?? Object.values(data.errors ?? {})[0]?.[0] ?? 'Could not add the rider.')
      setRiderEmail('')
      setRiderHireStoreId('')
      loadRiders()
      setRiderForm(riderFormFrom(data.data))
    } catch (error) { fail(error) }
  }

  async function patchRider(rider, body) {
    setMessage('')
    try {
      const response = await fetch(`${API_URL}/admin/riders/${rider.id}`, { method: 'PATCH', headers: jsonHeaders(), body: JSON.stringify(body) })
      const data = await readJson(response)
      if (!response.ok) throw new Error(data.message ?? 'Could not update the rider.')
      setRiders((cur) => cur.map((r) => (r.id === rider.id ? data.data : r)))
    } catch (error) { fail(error) }
  }

  async function saveRider(event) {
    event.preventDefault()
    setMessage('')
    const f = riderForm
    const payload = {
      phone: f.phone.trim() || null,
      rider_is_active: f.rider_is_active,
      rider_base_address: f.rider_base_address.trim() || null,
      rider_base_lat: String(f.rider_base_lat).trim() === '' ? null : Number(f.rider_base_lat),
      rider_base_lng: String(f.rider_base_lng).trim() === '' ? null : Number(f.rider_base_lng),
      rider_daily_target_minutes: String(f.daily_target_hours).trim() === '' ? null : Math.round(Number(f.daily_target_hours) * 60),
      store_ids: f.store_ids.map(Number),
    }
    try {
      const response = await fetch(`${API_URL}/admin/riders/${f.id}`, { method: 'PATCH', headers: jsonHeaders(), body: JSON.stringify(payload) })
      const data = await readJson(response)
      if (!response.ok) throw new Error(data.message ?? Object.values(data.errors ?? {})[0]?.[0] ?? 'Could not save the rider.')
      setRiderForm(null)
      loadRiders()
    } catch (error) { fail(error) }
  }

  async function removeRider(rider) {
    if (!window.confirm(`Remove the rider role from ${rider.name}? Their account stays.`)) return
    setMessage('')
    try {
      const response = await fetch(`${API_URL}/admin/riders/${rider.id}`, { method: 'DELETE', headers: authHeaders() })
      if (!response.ok && response.status !== 204) throw new Error((await readJson(response)).message ?? 'Could not remove the rider.')
      if (riderForm?.id === rider.id) setRiderForm(null)
      loadRiders()
    } catch (error) { fail(error) }
  }

  const scrollAdminTop = () => {
    requestAnimationFrame(() => {
      document.querySelector('.admin-main')?.scrollTo({ top: 0, behavior: 'smooth' })
      window.scrollTo({ top: 0, behavior: 'smooth' })
    })
  }

  // Bring a just-opened edit form into view (it renders above its list).
  const scrollFormIntoView = (id) => {
    requestAnimationFrame(() => {
      const el = document.getElementById(id)
      if (el) el.scrollIntoView({ behavior: 'smooth', block: 'start' })
      else document.querySelector('.admin-main')?.scrollTo({ top: 0, behavior: 'smooth' })
    })
  }

  function editPage(page) {
    setPagePreview(false)
    setPageSettingsOpen(false)
    setPageForm({
      id: page.id, title: page.title ?? '', slug: page.slug ?? '', parent_slug: page.parent_slug ?? '', banner_image: page.banner_image ?? '',
      content: page.content ?? '',
      sections: Array.isArray(page.sections) ? page.sections : [],
      footer_group: page.footer_group ?? 'useful_links',
      menu_placements: Array.isArray(page.menu_placements) ? page.menu_placements : [],
      show_in_footer: page.show_in_footer,
      is_published: page.is_published, sort_order: page.sort_order ?? 0,
    })
    scrollAdminTop()
  }

  function newPage(extra = {}) {
    setPagePreview(false)
    setPageSettingsOpen(false)
    setPageForm({ ...EMPTY_PAGE, ...extra })
    scrollAdminTop()
  }

  async function savePage(event) {
    event.preventDefault()
    setMessage('')
    const { id, ...rest } = pageForm
    const payload = { ...rest, slug: rest.slug.trim(), parent_slug: (rest.parent_slug ?? '').trim() || null, sort_order: Number(rest.sort_order) || 0 }
    try {
      const response = await fetch(`${API_URL}/admin/pages${id ? `/${id}` : ''}`, { method: id ? 'PATCH' : 'POST', headers: jsonHeaders(), body: JSON.stringify(payload) })
      const data = await readJson(response)
      if (!response.ok) throw new Error(data.message ?? Object.values(data.errors ?? {})[0]?.[0] ?? 'Could not save the page.')
      setPageForm(null)
      loadPages()
    } catch (error) { fail(error) }
  }

  const patchSection = (i, patch) => setPageForm((f) => ({ ...f, sections: f.sections.map((s, idx) => (idx === i ? { ...s, ...patch } : s)) }))
  const addSection = (type) => setPageForm((f) => ({ ...f, sections: [...(f.sections ?? []), blankSection(type)] }))
  const removeSection = (i) => setPageForm((f) => ({ ...f, sections: f.sections.filter((_, idx) => idx !== i) }))
  const moveSection = (i, dir) => setPageForm((f) => {
    const next = [...f.sections]
    const j = i + dir
    if (j < 0 || j >= next.length) return f
    ;[next[i], next[j]] = [next[j], next[i]]
    return { ...f, sections: next }
  })
  const patchItem = (si, ii, patch) => setPageForm((f) => ({ ...f, sections: f.sections.map((s, idx) => (idx === si ? { ...s, items: (s.items ?? []).map((it, k) => (k === ii ? { ...it, ...patch } : it)) } : s)) }))
  const addItem = (si) => setPageForm((f) => ({ ...f, sections: f.sections.map((s, idx) => (idx === si ? { ...s, items: [...(s.items ?? []), { image_url: '', title: '', text: '', link_url: '' }] } : s)) }))
  const removeItem = (si, ii) => setPageForm((f) => ({ ...f, sections: f.sections.map((s, idx) => (idx === si ? { ...s, items: (s.items ?? []).filter((_, k) => k !== ii) } : s)) }))

  async function removePage(page) {
    if (!window.confirm(`Delete the “${page.title}” page?`)) return
    setMessage('')
    try {
      const response = await fetch(`${API_URL}/admin/pages/${page.id}`, { method: 'DELETE', headers: authHeaders() })
      if (!response.ok && response.status !== 204) throw new Error((await readJson(response)).message ?? 'Could not delete the page.')
      if (pageForm?.id === page.id) setPageForm(null)
      loadPages()
    } catch (error) { fail(error) }
  }

  async function saveSetting(patch, extraHeaders = {}) {
    setMessage('')
    try {
      const response = await fetch(`${API_URL}/admin/settings`, { method: 'PATCH', headers: { ...jsonHeaders(), ...extraHeaders }, body: JSON.stringify(patch) })
      const data = await readJson(response)
      if (!response.ok) throw new Error(data.message ?? 'Could not save the setting.')
      setSettings(data.data)
      return data.data
    } catch (error) { fail(error) }
  }

  async function saveFees(event) {
    event.preventDefault()
    const saved = await saveSetting(formToFees(feesForm))
    if (saved) { setFeesForm(feesToForm(saved)); setMessage('Charges saved.') }
  }

  async function saveBranding(event) {
    event.preventDefault()
    const saved = await saveSetting({
      store_name: brandingForm.store_name.trim() || 'NexTech',
      tagline: brandingForm.tagline.trim(),
      logo_url: brandingForm.logo_url.trim(),
      favicon_url: brandingForm.favicon_url.trim(),
      theme: brandingForm.theme,
      layout_width: brandingForm.layout_width,
      color_brand: brandingForm.color_brand,
      color_accent: brandingForm.color_accent,
      color_heading: brandingForm.color_heading,
    })
    if (saved) { setBrandingForm({ ...EMPTY_BRANDING, ...(saved.branding ?? {}) }); setMessage('Store settings saved — refresh the storefront to see them.') }
  }

  async function saveFooter(event) {
    event.preventDefault()
    const payload = {
      copyright: footerForm.copyright.trim(),
      app_store_url: footerForm.app_store_url.trim(),
      play_store_url: footerForm.play_store_url.trim(),
      socials: footerForm.socials,
      links: footerForm.links.filter((l) => l.label.trim() && l.url.trim()),
      bg_color: footerForm.bg_color,
      text_color: footerForm.text_color,
    }
    const saved = await saveSetting({ footer: payload })
    if (saved) {
      setFooterForm({ ...EMPTY_FOOTER, ...(saved.footer ?? {}), socials: { ...EMPTY_FOOTER.socials, ...(saved.footer?.socials ?? {}) }, links: (saved.footer?.links ?? []).map((l) => ({ ...l })) })
      setMessage('Footer saved — refresh the storefront to see it.')
    }
  }

  const secureMethod = settings?.secure_access?.method ?? 'password'

  async function challengeSecure() {
    setSecureMsg('Sending a code to your admin email…')
    try {
      const response = await fetch(`${API_URL}/admin/secure-access/challenge`, { method: 'POST', headers: authHeaders() })
      const data = await readJson(response)
      if (!response.ok) throw new Error(data.message ?? 'Could not send the code.')
      setSecureMsg(data.data?.message ?? 'Check your admin email for the code.')
    } catch (error) { setSecureMsg(error.message) }
  }

  async function unlockSecure() {
    setSecureMsg('')
    const body = secureMethod === 'otp' ? { code: secureSecret.trim() } : { password: secureSecret }
    try {
      const response = await fetch(`${API_URL}/admin/secure-access/unlock`, { method: 'POST', headers: jsonHeaders(), body: JSON.stringify(body) })
      const data = await readJson(response)
      if (!response.ok) throw new Error(data.message ?? 'That did not work.')
      setSecureToken(data.data.token)
      setSecureSecret('')
      setSecureGate('unlocked')
      if (data.data.account) setAccountForm({ name: data.data.account.name ?? '', email: data.data.account.email ?? '', phone: data.data.account.phone ?? '' })
      if (data.data.payments) setSettings((s) => (s ? { ...s, payments: data.data.payments } : s))
      if (data.data.courier) setSettings((s) => (s ? { ...s, courier: data.data.courier } : s))
    } catch (error) { setSecureMsg(error.message) }
  }

  async function savePayments(event) {
    event.preventDefault()
    const patch = { stripe_key: paymentsForm.stripe_key.trim() }
    if (paymentsForm.stripe_secret.trim()) patch.stripe_secret = paymentsForm.stripe_secret.trim()
    if (paymentsForm.stripe_webhook_secret.trim()) patch.stripe_webhook_secret = paymentsForm.stripe_webhook_secret.trim()
    const saved = await saveSetting(patch, { 'X-Secure-Access': secureToken })
    if (saved) {
      setPaymentsForm({ stripe_key: saved.payments?.stripe_key ?? '', stripe_secret: '', stripe_webhook_secret: '' })
      setMessage('Payment settings saved — they take effect immediately.')
    } else {
      setSecureGate('locked'); setSecureToken('')
    }
  }

  async function saveCourier(event) {
    event.preventDefault()
    const patch = {
      courier_provider: courierForm.courier_provider,
      courier_base_url: courierForm.courier_base_url.trim(),
      courier_account_code: courierForm.courier_account_code.trim(),
    }
    if (courierForm.courier_api_key.trim()) patch.courier_api_key = courierForm.courier_api_key.trim()
    if (courierForm.courier_api_secret.trim()) patch.courier_api_secret = courierForm.courier_api_secret.trim()
    const saved = await saveSetting(patch, { 'X-Secure-Access': secureToken })
    if (saved) {
      setCourierForm({
        courier_provider: saved.courier?.provider ?? 'mock',
        courier_base_url: saved.courier?.base_url ?? '',
        courier_account_code: saved.courier?.account_code ?? '',
        courier_api_key: '',
        courier_api_secret: '',
      })
      setMessage('Courier settings saved — they take effect immediately.')
    } else {
      setSecureGate('locked'); setSecureToken('')
    }
  }

  async function saveAccount(event) {
    event.preventDefault()
    setMessage('')
    try {
      const response = await fetch(`${API_URL}/admin/secure-access/account`, {
        method: 'PATCH', headers: { ...jsonHeaders(), 'X-Secure-Access': secureToken },
        body: JSON.stringify({ name: accountForm.name.trim(), email: accountForm.email.trim(), phone: accountForm.phone.trim() }),
      })
      const data = await readJson(response)
      if (!response.ok) throw new Error(data.message ?? Object.values(data.errors ?? {})[0]?.[0] ?? 'Could not update the account.')
      setAccountForm({ name: data.data.name ?? '', email: data.data.email ?? '', phone: data.data.phone ?? '' })
      setMessage('Admin account updated.')
    } catch (error) {
      fail(error)
      if (error.message === 'Unlock the Secure access section first.') { setSecureGate('locked'); setSecureToken('') }
    }
  }

  async function patchOrder(order, body) {
    setBusyId(order.id)
    setMessage('')
    try {
      const response = await fetch(`${API_URL}/admin/orders/${order.id}`, { method: 'PATCH', headers: jsonHeaders(), body: JSON.stringify(body) })
      const data = await readJson(response)
      if (!response.ok) throw new Error(data.message ?? 'That change was not allowed.')
      setOrders((current) => current.map((row) => row.id === order.id ? { ...row, ...data.data } : row))
      loadMetrics()
    } catch (error) { fail(error) } finally { setBusyId(null) }
  }

  async function refundOrder(order) {
    if (!window.confirm(`Refund ${money(order.total_cents, order.currency)} to the customer via Stripe?`)) return
    setBusyId(order.id)
    setMessage('')
    try {
      const response = await fetch(`${API_URL}/admin/orders/${order.id}/refund`, { method: 'POST', headers: authHeaders() })
      const data = await readJson(response)
      if (!response.ok) throw new Error(data.message ?? 'Refund failed.')
      setOrders((current) => current.map((row) => row.id === order.id ? { ...row, ...data.data } : row))
      setMessage('Refund issued.')
      loadMetrics()
    } catch (error) { fail(error) } finally { setBusyId(null) }
  }

  // Pulls the courier's current tracking status; a `delivered` result
  // auto-completes the order (handled server-side).
  // Admin override on a seller's package: fix carrier/tracking or set its status.
  async function patchPackage(order, pkg, body) {
    setBusyId(order.id)
    setMessage('')
    try {
      const response = await fetch(`${API_URL}/admin/packages/${pkg.id}`, { method: 'PATCH', headers: jsonHeaders(), body: JSON.stringify(body) })
      const data = await readJson(response)
      if (!response.ok) throw new Error(data.message ?? Object.values(data.errors ?? {})[0]?.[0] ?? 'Could not update the package.')
      setOrders((current) => current.map((row) => row.id === order.id ? { ...row, ...data.data } : row))
    } catch (error) { fail(error) } finally { setBusyId(null) }
  }

  async function downloadPackageLabel(pkg) {
    try {
      const response = await fetch(`${API_URL}/admin/packages/${pkg.id}/label`, { headers: authHeaders() })
      if (!response.ok) throw new Error('Could not download the label.')
      const url = URL.createObjectURL(await response.blob())
      window.open(url, '_blank', 'noopener')
      setTimeout(() => URL.revokeObjectURL(url), 60000)
    } catch (error) { fail(error) }
  }

  function editPackageTracking(order, pkg) {
    const tracking = window.prompt(`Tracking number for ${pkg.carrier} package on order #${order.id}:`, pkg.tracking_number)
    if (tracking && tracking.trim() !== pkg.tracking_number) patchPackage(order, pkg, { tracking_number: tracking.trim() })
  }

  async function syncTracking(order) {
    setBusyId(order.id)
    setMessage('')
    try {
      const response = await fetch(`${API_URL}/admin/orders/${order.id}/sync-tracking`, { method: 'POST', headers: authHeaders() })
      const data = await readJson(response)
      if (!response.ok) throw new Error(data.message ?? 'Could not pull tracking.')
      setOrders((current) => current.map((row) => row.id === order.id ? { ...row, ...data.data } : row))
      loadMetrics()
    } catch (error) { fail(error) } finally { setBusyId(null) }
  }

  // Manual escalation for an own-rider order stuck unassigned in the pool —
  // hands it to the online courier instead, immediately booking a shipment.
  async function escalateToCourier(order) {
    if (!window.confirm('Send this order via online courier instead of waiting for a rider?')) return
    setBusyId(order.id)
    setMessage('')
    try {
      const response = await fetch(`${API_URL}/admin/orders/${order.id}/escalate-to-courier`, { method: 'POST', headers: authHeaders() })
      const data = await readJson(response)
      if (!response.ok) throw new Error(data.message ?? 'Could not escalate to courier.')
      setOrders((current) => current.map((row) => row.id === order.id ? { ...row, ...data.data } : row))
      loadMetrics()
    } catch (error) { fail(error) } finally { setBusyId(null) }
  }

  // Context-sensitive buttons that move an order along the pipeline (one step at
  // a time) plus any cash/refund step. Shared by the Orders table and the
  // order-summary drawer. Returns null when there's nothing to do.
  function orderActions(order) {
    const codCollect = order.payment_method === 'cod' && order.payment_status !== 'paid' && order.status !== 'cancelled'
    const needsRefund = order.payment_status === 'refund_pending' || (order.payment_status === 'paid' && order.status === 'cancelled')
    const refundedLink = order.payment_status === 'refunded' && order.stripe_dashboard_url
    // A refund that's settled but has nowhere to click through to (manual / COD
    // "Mark refunded", or a Stripe partial refund) — still worth a line so the
    // Actions column isn't blank for an order that was in fact refunded.
    const refundedNote = !refundedLink && !needsRefund
      && (order.payment_status === 'refunded' || order.payment_status === 'partially_refunded' || (order.refunded_amount_cents ?? 0) > 0)
    const giftCards = order.gift_cards ?? []
    const needsItemReturn = order.cancelled_by === 'rider' && !order.items_returned_at
    const itemsReturned = order.cancelled_by === 'rider' && !!order.items_returned_at
    const steps = (NEXT_ACTIONS[order.status] ?? []).filter(([status]) => order.delivery_method !== 'seller' || status === 'cancelled')
    if (!codCollect && !needsRefund && !refundedLink && !refundedNote && !giftCards.length && !needsItemReturn && !itemsReturned && steps.length === 0) return null
    return (
      <>
        {codCollect && (
          <button type="button" disabled={busyId === order.id} className="act" onClick={() => patchOrder(order, { cash_collected: true })}>Cash collected</button>
        )}
        {needsRefund && (
          order.stripe_payment_intent_id
            ? <button type="button" disabled={busyId === order.id} className="act" onClick={() => refundOrder(order)}>Refund via Stripe</button>
            : <button type="button" disabled={busyId === order.id} className="act" onClick={() => patchOrder(order, { refunded: true })}>Mark refunded</button>
        )}
        {refundedLink && (
          <a className="act ghost" href={order.stripe_dashboard_url} target="_blank" rel="noreferrer">View in Stripe ↗</a>
        )}
        {refundedNote && (
          <span className="admin-note" style={{ color: '#2f5a8a' }} title={(order.refunds ?? []).length
            ? order.refunds.map((r) => `${money(r.amount_cents, order.currency)}${r.reason ? ` — ${r.reason}` : ''}${r.creator?.name ? ` — by ${r.creator.name}` : ''}`).join('\n')
            : `Refunded ${money(order.refunded_amount_cents ?? 0, order.currency)}`}>↩ refunded{order.payment_status === 'partially_refunded' ? ' (partial)' : ''}</span>
        )}
        {giftCards.length > 0 && (
          <span className="admin-note" style={{ color: '#6b4f12' }} title={giftCards.map((g) => `${g.code} — ${money(g.initial_cents)}${g.reason ? ` (${g.reason})` : ''}${g.issued_by?.name ? ` — by ${g.issued_by.name}` : ''}`).join('\n')}>🎁 gift card issued{giftCards.length > 1 ? ` ×${giftCards.length}` : ''}</span>
        )}
        {needsItemReturn && (
          <button type="button" disabled={busyId === order.id} className="act warn" title={order.cancel_reason || ''} onClick={() => { if (window.confirm('Confirm the rider has brought these items back to the store?')) patchOrder(order, { items_returned: true }) }}>Items returned? Click to confirm</button>
        )}
        {itemsReturned && (
          <span className="admin-note" style={{ color: '#2f6d34' }} title={`Confirmed ${new Date(order.items_returned_at).toLocaleString()}`}>✓ items returned</span>
        )}
        {steps.map(([status, label]) => (
          <button key={status} type="button" disabled={busyId === order.id} className={status === 'cancelled' ? 'act danger' : 'act'} onClick={() => patchOrder(order, { status })}>{label}</button>
        ))}
      </>
    )
  }

  // Opens an order's drawer straight from the notification bell, even when
  // that order isn't on the currently loaded Orders page.
  async function openOrderById(id) {
    setBellOpen(false)
    setMessage('')
    try {
      const data = await readJson(await fetch(`${API_URL}/admin/orders/${id}`, { headers: authHeaders() }))
      setOrderDetail(data.data)
    } catch (error) { fail(error) }
  }

  // A new order is a fleeting "go handle this," not something to inspect in
  // place — send the admin to the Orders list (filtered to unpacked) rather
  // than popping the edit drawer.
  const goToOrder = () => {
    setSpeakerOpen(false)
    setStatusFilter('confirmed')
    setOrdersPage(1)
    goTab('orders')
  }

  async function openThread(id) {
    setMessage('')
    setRefundForm({ items: [], amount: '', reason: '', charge_pickup: true, charge_delivery: true })
    setSupportToasts((cur) => cur.filter((t) => t.id !== id))
    try {
      const data = await readJson(await fetch(`${API_URL}/admin/support/threads/${id}`, { headers: authHeaders() }))
      setThread(data.data)
      if (seenRef.current && data.data.last_message_at) seenRef.current[id] = data.data.last_message_at
    } catch (error) { fail(error) }
  }

  async function replyThread() {
    const body = threadReply.trim()
    if ((!body && !threadPhotos.length) || !thread) return
    try {
      const response = await fetch(`${API_URL}/admin/support/threads/${thread.id}/messages`, { method: 'POST', headers: jsonHeaders(), body: JSON.stringify({ body, ...(threadPhotos.length ? { attachments: threadPhotos } : {}) }) })
      const data = await readJson(response)
      if (!response.ok) throw new Error(data.message ?? 'Message not sent.')
      setThreadReply('')
      setThreadPhotos([])
      setThread(data.data)
    } catch (error) { fail(error) }
  }

  async function setThreadResolved(status) {
    if (!thread) return
    try {
      const response = await fetch(`${API_URL}/admin/support/threads/${thread.id}`, { method: 'PATCH', headers: jsonHeaders(), body: JSON.stringify({ status }) })
      const data = await readJson(response)
      if (!response.ok) throw new Error(data.message ?? 'Could not update.')
      setThread(data.data)
      loadThreads()
    } catch (error) { fail(error) }
  }

  // A thread opened without picking a specific order (general support) still
  // needs one attached before it can carry a refund / gift card — lets the
  // admin pick from that customer's own recent orders.
  async function linkThreadOrder(orderId) {
    if (!thread || !orderId) return
    try {
      const response = await fetch(`${API_URL}/admin/support/threads/${thread.id}`, { method: 'PATCH', headers: jsonHeaders(), body: JSON.stringify({ order_id: Number(orderId) }) })
      const data = await readJson(response)
      if (!response.ok) throw new Error(data.message ?? 'Could not attach that order.')
      setThread(data.data)
      loadThreads()
    } catch (error) { fail(error) }
  }

  // Pull the fresh order (refunds, gift cards, …) into whichever list/drawer is
  // showing it, so an action taken from the Support tab shows up on Orders too.
  async function refreshOrderInList(orderId) {
    try {
      const response = await fetch(`${API_URL}/admin/orders/${orderId}`, { headers: authHeaders() })
      const data = await readJson(response)
      if (response.ok) setOrders((current) => current.map((o) => (o.id === orderId ? { ...o, ...data.data } : o)))
    } catch { /* the next Orders poll will catch up */ }
  }

  // order/threadId: threadId is set when issuing from a Support conversation
  // (the code + password are posted into it); null when issuing straight from
  // the Orders tab's order-summary drawer.
  // Return costs charged to the seller(s) on this refund — only sent when the
  // order actually has seller items.
  const sellerChargeFlags = (order) => ((order.items ?? []).some((i) => i.shop_id)
    ? { charge_seller_pickup: !!refundForm.charge_pickup, charge_seller_delivery: !!refundForm.charge_delivery }
    : {})

  async function issueRefund(order, threadId) {
    if (!order) return
    const body = { reason: refundForm.reason.trim() || undefined, ...sellerChargeFlags(order) }
    if (threadId) body.support_thread_id = threadId
    if (refundForm.items.length) body.item_ids = refundForm.items
    else if (refundForm.amount) body.amount_cents = Math.round(Number(refundForm.amount) * 100)
    const allItemsPicked = refundForm.items.length > 0 && refundForm.items.length === (order.items ?? []).length
    const label = refundForm.items.length
      ? money(allItemsPicked ? order.total_cents - (order.refunded_amount_cents ?? 0) : order.items.filter((i) => refundForm.items.includes(i.id)).reduce((s, i) => s + i.line_total_cents, 0), order.currency)
      : (refundForm.amount ? money(Math.round(Number(refundForm.amount) * 100), order.currency) : money(order.total_cents - (order.refunded_amount_cents ?? 0), order.currency))
    if (!window.confirm(`Refund ${label} to the customer via Stripe?`)) return
    setBusyId(threadId ?? order.id)
    try {
      const response = await fetch(`${API_URL}/admin/orders/${order.id}/refund`, { method: 'POST', headers: jsonHeaders(), body: JSON.stringify(body) })
      const data = await readJson(response)
      if (!response.ok) throw new Error(data.message ?? 'Refund failed.')
      setRefundForm({ items: [], amount: '', reason: '', charge_pickup: true, charge_delivery: true })
      if (threadId) openThread(threadId)
      refreshOrderInList(order.id)
      loadMetrics()
      setMessage('Refund issued.')
    } catch (error) { fail(error) } finally { setBusyId(null) }
  }

  // Store credit for a paid order (e.g. missing items on a cash delivery). The
  // code + password go into the thread's chat when issued from Support; from
  // the Orders drawer they're shown here for the admin to relay themselves.
  async function issueGiftCard(order, threadId) {
    if (!order) return
    const body = { reason: refundForm.reason.trim() || undefined, ...sellerChargeFlags(order) }
    if (threadId) body.support_thread_id = threadId
    if (refundForm.items.length) body.item_ids = refundForm.items
    else if (refundForm.amount) body.amount_cents = Math.round(Number(refundForm.amount) * 100)
    const allItemsPicked = refundForm.items.length > 0 && refundForm.items.length === (order.items ?? []).length
    const room = order.total_cents - (order.refunded_amount_cents ?? 0) - (order.gift_cards ?? []).reduce((s, g) => s + g.initial_cents, 0)
    const label = refundForm.items.length
      ? money(allItemsPicked ? room : order.items.filter((i) => refundForm.items.includes(i.id)).reduce((s, i) => s + i.line_total_cents, 0), order.currency)
      : (refundForm.amount ? money(Math.round(Number(refundForm.amount) * 100), order.currency) : '')
    if (!window.confirm(`Issue a ${label || 'store-credit'} gift card to the customer?`)) return
    setBusyId(threadId ?? order.id)
    try {
      const response = await fetch(`${API_URL}/admin/orders/${order.id}/gift-card`, { method: 'POST', headers: jsonHeaders(), body: JSON.stringify(body) })
      const data = await readJson(response)
      if (!response.ok) throw new Error(data.message ?? 'Could not issue the gift card.')
      setGiftIssued(data.data)
      setRefundForm({ items: [], amount: '', reason: '', charge_pickup: true, charge_delivery: true })
      if (threadId) openThread(threadId)
      refreshOrderInList(order.id)
      setMessage(`Gift card ${data.data.code} for ${money(data.data.amount_cents, order.currency)} issued.`)
    } catch (error) { fail(error) } finally { setBusyId(null) }
  }

  // Applies a gift card the customer already has (from an earlier order) to a
  // different order that's still unpaid — for when they ask in chat instead
  // of entering the code themselves at checkout.
  async function applyGiftCardToOrder(card, order, threadId) {
    const label = money(Math.min(card.balance_cents, order.total_cents), order.currency)
    if (!window.confirm(`Apply ${label} from gift card ${card.code} to order #${order.id}?`)) return
    setBusyId(threadId ?? order.id)
    try {
      const response = await fetch(`${API_URL}/admin/orders/${order.id}/apply-gift-card`, {
        method: 'POST', headers: jsonHeaders(), body: JSON.stringify({ gift_card_code: card.code, support_thread_id: threadId ?? undefined }),
      })
      const data = await readJson(response)
      if (!response.ok) throw new Error(data.message ?? 'Could not apply the gift card.')
      if (threadId) openThread(threadId)
      refreshOrderInList(order.id)
      setMessage(`Applied ${money(data.data.applied_cents)} from ${card.code} to order #${order.id}.`)
    } catch (error) { fail(error) } finally { setBusyId(null) }
  }

  // Item-picker + Refund/Issue-store-credit form, shared by the Support thread
  // drawer (threadId set) and the order-summary drawer (threadId null).
  function renderRefundPanel(o, threadId) {
    const remaining = Math.max(0, o.total_cents - (o.refunded_amount_cents ?? 0))
    const stateOk = ['paid', 'partially_refunded', 'refund_pending'].includes(o.payment_status)
    const canRefund = !!o.stripe_payment_intent_id && stateOk && remaining > 0
    const blockReason = !canRefund && (
      remaining <= 0 || o.payment_status === 'refunded'
        ? 'This order is fully refunded.'
        : !o.stripe_payment_intent_id
          ? 'No online payment to refund (cash on delivery) — issue store credit instead.'
          : 'This order is not in a refundable state.'
    )
    const selectedSum = (o.items ?? []).filter((i) => refundForm.items.includes(i.id)).reduce((s, i) => s + i.line_total_cents, 0)
    const allItemsSelected = (o.items ?? []).length > 0 && refundForm.items.length === (o.items ?? []).length
    const bare = refundForm.items.length === 0 && !String(refundForm.amount).trim()
    const amountCents = refundForm.items.length ? (allItemsSelected ? remaining : selectedSum) : Math.round(Number(refundForm.amount || 0) * 100)
    const amountOk = bare ? remaining > 0 : (amountCents > 0 && amountCents <= remaining)
    const giftLast = giftIssued && giftIssued.order_id === o.id ? giftIssued : null
    const alreadyIssuedCard = (o.gift_cards ?? [])[0]
    const alreadyIssuedTitle = alreadyIssuedCard ? `${alreadyIssuedCard.code} — ${money(alreadyIssuedCard.initial_cents, o.currency)}` : undefined
    const busyKey = threadId ?? o.id
    return (
      <div className="admin-form" style={{ marginTop: 16 }}>
        <h4>Refund</h4>
        <p className="muted">Paid {money(o.total_cents, o.currency)} · refunded {money(o.refunded_amount_cents ?? 0, o.currency)} · remaining {money(remaining, o.currency)}</p>
        {stateOk && remaining > 0 ? (
          <>
            {(o.items ?? []).map((item) => (
              <label key={item.id} className="admin-check">
                <input type="checkbox" checked={refundForm.items.includes(item.id)} onChange={(event) => setRefundForm((f) => ({ ...f, items: event.target.checked ? [...f.items, item.id] : f.items.filter((x) => x !== item.id) }))} />
                {item.product_name}{item.variant_label ? ` · ${item.variant_label}` : ''} × {item.quantity} — {money(item.line_total_cents, o.currency)}
              </label>
            ))}
            <div className="admin-form-grid" style={{ marginTop: 10 }}>
              <label>Or amount ({currencySymbol(o.currency)})<input type="number" min="0" step="0.01" disabled={refundForm.items.length > 0} value={refundForm.amount} onChange={(event) => setRefundForm({ ...refundForm, amount: event.target.value })} /></label>
              <label>Reason<input value={refundForm.reason} onChange={(event) => setRefundForm({ ...refundForm, reason: event.target.value })} /></label>
            </div>
            {(o.items ?? []).some((i) => i.shop_id) && (
              <div className="admin-seller-charges">
                <strong>Charge the seller</strong>
                <label className="admin-check"><input type="checkbox" checked={!!refundForm.charge_pickup} onChange={(event) => setRefundForm({ ...refundForm, charge_pickup: event.target.checked })} /> Return pickup fee ({money(settings?.return_pickup_fee_cents ?? 499, o.currency)} per seller)</label>
                <label className="admin-check"><input type="checkbox" checked={!!refundForm.charge_delivery} onChange={(event) => setRefundForm({ ...refundForm, charge_delivery: event.target.checked })} /> Their share of the delivery fee ({money(o.delivery_fee_cents ?? 0, o.currency)} on this order, charged once)</label>
                <span className="muted">The refunded item value (less the commission they paid) is always taken back from the seller. Untick these for a NexTech fault, e.g. delivery damage.</span>
              </div>
            )}
            {refundForm.items.length > 0 && (
              <p className="muted">
                {allItemsSelected
                  ? `Every item selected — full refund of ${money(remaining, o.currency)} (includes tax and fees).`
                  : `Selected items: ${money(selectedSum, o.currency)}${selectedSum > remaining ? ' — more than the remaining balance' : ' (tax and fees are refunded separately)'}.`}
              </p>
            )}
            <div className="admin-form-actions">
              {canRefund
                ? <button className="act" type="button" disabled={busyId === busyKey || !amountOk} onClick={() => issueRefund(o, threadId)}>Refund via Stripe</button>
                : <span className="muted" title={blockReason}>No card to refund — issue store credit instead.</span>}
              {alreadyIssuedCard
                ? <span className="muted" title={alreadyIssuedTitle}>Store credit already issued for this order.</span>
                : <button className="act" type="button" disabled={busyId === busyKey || !amountOk} onClick={() => issueGiftCard(o, threadId)}>Issue store credit (gift card)</button>}
              {o.stripe_dashboard_url && <a className="act ghost" href={o.stripe_dashboard_url} target="_blank" rel="noreferrer">View in Stripe ↗</a>}
            </div>
          </>
        ) : (
          <>
            <p className="muted">{blockReason}</p>
            {o.stripe_dashboard_url && <div className="admin-form-actions"><a className="act ghost" href={o.stripe_dashboard_url} target="_blank" rel="noreferrer">View in Stripe ↗</a></div>}
          </>
        )}
        {giftLast && (
          <p className="admin-gift-issued">Gift card <b>{giftLast.code}</b> · password <b>{giftLast.pin}</b> · {money(giftLast.amount_cents, o.currency)}{threadId ? ' — sent to the customer in this chat.' : ' — share this with the customer.'}</p>
        )}
      </div>
    )
  }

  async function saveProduct(event) {
    event.preventDefault()
    setMessage('')
    const { id, price, compare_at: compareAt, variants, per_store_stock: perStore, store_stock: storeStockMap, ...rest } = productForm
    rest.sku = rest.sku?.trim() || null
    const payload = { ...rest, market: rest.shop_id ? undefined : (rest.market || activeMarket), category_id: Number(rest.category_id), shop_id: rest.shop_id ? Number(rest.shop_id) : null, inventory_quantity: Number(rest.inventory_quantity), price_cents: Math.round(Number(price) * 100), compare_at_price_cents: String(compareAt).trim() ? Math.round(Number(compareAt) * 100) : null, return_days: String(rest.return_days ?? '').trim() === '' ? null : Number(rest.return_days), image_url: rest.image_url?.trim() || null, images: (rest.images ?? []).filter(Boolean), video_url: rest.video_url?.trim() || null }

    // Per-store stock: a full grid of (store, option) rows. Off = single stock,
    // sent as [] so the backend drops any rows. `rows` below (sent as
    // payload.variants) is what the backend indexes `variant_index` against,
    // so store-stock rows must reference positions in `rows`, not `variants`.
    const rows = (variants ?? []).filter((row) => row.id || !row._delete)
    const liveVariants = rows.filter((v) => !v._delete && (v.label || '').trim())
    payload.store_stock = perStore
      ? (stores.length ? stores.map((store) => String(store.id)) : Object.keys(storeStockMap ?? {})).flatMap((sid) => {
          const row = storeStockRow(productForm, sid)
          const stocked = row.is_stocked !== false
          const base = { store_id: Number(sid), variant_index: null, is_stocked: stocked, quantity: Number(row.base || 0) }
          const vRows = liveVariants.map((v) => {
            const localIdx = variants.indexOf(v)
            const payloadIdx = rows.indexOf(v)
            return { store_id: Number(sid), variant_index: payloadIdx, is_stocked: stocked, quantity: Number(row.variants?.[localIdx] || 0) }
          })
          return [base, ...vRows]
        })
      : []
    if (id || rows.length) {
      payload.variants = rows.map((row) => ({
        ...(row.id ? { id: row.id } : {}),
        ...(row._delete ? { _delete: true } : {}),
        label: (row.label || '').trim(),
        sku: row.sku?.trim() || null,
        price_cents: Math.round(Number(row.price || 0) * 100),
        compare_at_price_cents: String(row.compare_at ?? '').trim() ? Math.round(Number(row.compare_at) * 100) : null,
        inventory_quantity: Number(row.stock) || 0,
        image_url: row.image_url?.trim() || null,
        is_active: !!row.is_active,
      }))
    }
    try {
      const response = await fetch(`${API_URL}/admin/products${id ? `/${id}` : ''}`, { method: id ? 'PATCH' : 'POST', headers: jsonHeaders(), body: JSON.stringify(payload) })
      const data = await readJson(response)
      if (!response.ok) throw new Error(data.message ?? Object.values(data.errors ?? {})[0]?.[0] ?? 'Could not save the product.')
      setProductForm(null)
      loadProducts()
      loadMetrics()
    } catch (error) { fail(error) }
  }

  // Upload an image file to /api/admin/media and hand the stored URL to `apply`.
  // `folder` defaults to 'products' — product/variant photos, which the
  // server holds to a stricter bar (square, JPEG/PNG, 800KB) than category,
  // banner, page, or branding images, so only that folder gets the client-side
  // pre-check too.
  async function uploadImage(file, apply, folder = 'products') {
    if (!file) return
    setMessage('')
    if (folder === 'products') {
      const problem = await checkProductImage(file)
      if (problem) { fail(new Error(problem)); return }
    }
    setImgBusy(true)
    try {
      const body = new FormData()
      body.append('file', file)
      if (folder !== 'products') body.append('folder', folder)
      const response = await fetch(`${API_URL}/admin/media`, { method: 'POST', headers: authHeaders(), body })
      const data = await readJson(response)
      if (!response.ok) throw new Error(data.message ?? Object.values(data.errors ?? {})[0]?.[0] ?? 'Upload failed.')
      apply(data.data.url)
    } catch (error) { fail(error) } finally { setImgBusy(false) }
  }

  async function removeProduct(product) {
    if (!window.confirm(`Delete ${product.name}?`)) return
    setMessage('')
    try {
      const response = await fetch(`${API_URL}/admin/products/${product.id}`, { method: 'DELETE', headers: authHeaders() })
      if (!response.ok && response.status !== 204) throw new Error((await readJson(response)).message ?? 'Could not delete the product.')
      loadProducts()
      loadMetrics()
    } catch (error) { fail(error) }
  }

  async function saveCategory(event) {
    event.preventDefault()
    setMessage('')
    const { id, ...rest } = categoryForm
    const payload = { ...rest, sort_order: Number(rest.sort_order) }
    if (!payload.slug) delete payload.slug
    try {
      const response = await fetch(`${API_URL}/admin/categories${id ? `/${id}` : ''}`, { method: id ? 'PATCH' : 'POST', headers: jsonHeaders(), body: JSON.stringify(payload) })
      const data = await readJson(response)
      if (!response.ok) throw new Error(data.message ?? Object.values(data.errors ?? {})[0]?.[0] ?? 'Could not save the category.')
      setCategoryForm(null)
      loadCategories()
    } catch (error) { fail(error) }
  }

  async function removeCategory(category) {
    if (!window.confirm(`Delete ${category.name}?`)) return
    setMessage('')
    try {
      const response = await fetch(`${API_URL}/admin/categories/${category.id}`, { method: 'DELETE', headers: authHeaders() })
      if (!response.ok && response.status !== 204) throw new Error((await readJson(response)).message ?? 'Could not delete the category.')
      loadCategories()
    } catch (error) { fail(error) }
  }

  async function openCustomer(id) {
    setMessage('')
    setCustomerDetail({ loading: true })
    try {
      const response = await fetch(`${API_URL}/admin/customers/${id}`, { headers: authHeaders() })
      const data = await readJson(response)
      if (!response.ok) throw new Error(data.message ?? 'Could not load the customer.')
      setCustomerDetail(data.data)
    } catch (error) { setCustomerDetail(null); fail(error) }
  }

  async function openSellerDetail(id) {
    setMessage('')
    setSellerDetail({ loading: true })
    try {
      const response = await fetch(`${API_URL}/admin/sellers/${id}`, { headers: authHeaders() })
      const data = await readJson(response)
      if (!response.ok) throw new Error(data.message ?? 'Could not load the application.')
      setSellerDetail(data.data)
    } catch (error) { setSellerDetail(null); fail(error) }
  }

  async function sellerAction(seller, action, body) {
    setBusyId(seller.id)
    setMessage('')
    try {
      const response = await fetch(`${API_URL}/admin/sellers/${seller.id}/${action}`, { method: 'POST', headers: jsonHeaders(), body: body ? JSON.stringify(body) : undefined })
      const data = await readJson(response)
      if (!response.ok) throw new Error(data.message ?? Object.values(data.errors ?? {})[0]?.[0] ?? 'Could not update the application.')
      setSellers((cur) => cur.map((s) => (s.id === seller.id ? data.data : s)))
      setSellerDetail((cur) => (cur?.id === seller.id ? { ...cur, ...data.data } : cur))
      if (action === 'approve') loadShops()
      if (action === 'payout' || action === 'payout-request/reject') {
        setNotifications((cur) => ({ ...cur, payout_requests: (cur.payout_requests ?? []).filter((r) => r.seller_id !== seller.id) }))
      }
    } catch (error) { fail(error) } finally { setBusyId(null) }
  }

  // Seller Center onboarding tasks (tax information, compliance information, bank account).
  function reviewOnboarding(seller, task, decision) {
    const note = decision === 'reject' ? window.prompt('What does the seller need to fix? They see this on the task and get it as a message.', '') : null
    if (decision === 'reject' && !note) return
    sellerAction(seller, `onboarding/${task}`, { decision, note })
  }

  function rejectSeller(seller) {
    const reason = window.prompt('Reason for rejecting this application:', '')
    if (reason) sellerAction(seller, 'reject', { reason })
  }

  async function productAction(product, action, body) {
    setBusyId(product.id)
    setMessage('')
    try {
      const response = await fetch(`${API_URL}/admin/products/${product.id}/${action}`, { method: 'POST', headers: jsonHeaders(), body: body ? JSON.stringify(body) : undefined })
      const data = await readJson(response)
      if (!response.ok) throw new Error(data.message ?? Object.values(data.errors ?? {})[0]?.[0] ?? 'Could not update the product.')
      setProducts((cur) => cur.map((p) => (p.id === product.id ? data.data : p)))
    } catch (error) { fail(error) } finally { setBusyId(null) }
  }

  function rejectProduct(product) {
    const reason = window.prompt(`Reason for rejecting "${product.name}":`, '')
    if (reason) productAction(product, 'reject', { reason })
  }
  function suspendSeller(seller) {
    const reason = window.prompt(`Reason for suspending ${seller.shop?.name ?? 'this seller'}:`, '')
    if (reason) sellerAction(seller, 'suspend', { reason })
  }

  function recordSellerPayout(seller) {
    const balance = Math.max(0, seller.available_cents ?? seller.balance_cents ?? 0)
    const max = seller.max_payout_cents ?? 0
    const suggested = seller.pending_payout_request?.amount_cents ?? (max > 0 ? Math.min(balance, max) : balance)
    const limits = [max > 0 ? `max ${money(max, seller.currency)} per payout` : null, seller.daily_payout_remaining_cents != null ? `${money(seller.daily_payout_remaining_cents, seller.currency)} left today` : null].filter(Boolean).join(', ')
    const amountStr = window.prompt(`Payout amount for ${seller.shop?.name ?? 'this seller'} (${currencySymbol(seller.currency)}) — available ${money(balance, seller.currency)}${limits ? ` (${limits})` : ''}:`, (suggested / 100).toFixed(2))
    if (!amountStr) return
    const amount_cents = toCents(amountStr)
    if (!amount_cents || amount_cents <= 0) { setMessage('Enter a valid payout amount.'); return }
    const note = window.prompt('Note (optional):', '') ?? ''
    sellerAction(seller, 'payout', { amount_cents, note: note.trim() || undefined })
  }

  function rejectPayoutRequest(seller) {
    const note = window.prompt('Reason for declining this payout request (the seller sees it):', '')
    if (note?.trim()) sellerAction(seller, 'payout-request/reject', { note: note.trim() })
  }

  // Deliberately not routed through sellerAction() — that helper expects the
  // response's `data` to be a Seller row it can merge into sellers/sellerDetail
  // state, but this endpoint returns the SupportThread instead. Re-opens the
  // detail drawer afterward so the "Last message" line reflects what was sent.
  async function messageSeller(seller) {
    const body = window.prompt(`Message to ${seller.shop?.name ?? 'this seller'}:`, '')
    if (!body || !body.trim()) return
    setBusyId(seller.id)
    setMessage('')
    try {
      const response = await fetch(`${API_URL}/admin/sellers/${seller.id}/message`, { method: 'POST', headers: jsonHeaders(), body: JSON.stringify({ body: body.trim() }) })
      const data = await readJson(response)
      if (!response.ok) throw new Error(data.message ?? Object.values(data.errors ?? {})[0]?.[0] ?? 'Could not send the message.')
      setMessage('Message sent.')
      openSellerDetail(seller.id)
    } catch (error) { fail(error) } finally { setBusyId(null) }
  }

  function requestSellerChanges(seller) {
    const reason = window.prompt(`What does ${seller.shop?.name ?? 'this seller'} need to change? (sent to them as a message, and reopens the application for editing)`, '')
    if (reason) sellerAction(seller, 'request-changes', { reason })
  }

  // KYC documents live on the private disk, gated by auth — not a plain <a
  // href>, since the browser needs the bearer token to fetch them.
  async function viewKycDocument(path) {
    if (!path) return
    try {
      const response = await fetch(`${API_URL}/seller/kyc-document/${path}`, { headers: authHeaders() })
      if (!response.ok) throw new Error('Could not load the document.')
      const blob = await response.blob()
      window.open(URL.createObjectURL(blob), '_blank')
    } catch (error) { fail(error) }
  }

  async function riderAppAction(app, action, body) {
    setBusyId(`app-${app.id}`)
    setMessage('')
    try {
      const response = await fetch(`${API_URL}/admin/rider-applications/${app.id}/${action}`, { method: 'POST', headers: jsonHeaders(), body: body ? JSON.stringify(body) : undefined })
      const data = await readJson(response)
      if (!response.ok) throw new Error(data.message ?? Object.values(data.errors ?? {})[0]?.[0] ?? 'Could not update the application.')
      setRiderApps((cur) => cur.map((a) => (a.id === app.id ? data.data : a)))
      setNotifications((cur) => ({ ...cur, rider_applications: (cur.rider_applications ?? []).filter((a) => a.id !== app.id) }))
      if (action === 'approve') { loadRiders(); setMessage(`${app.user?.name ?? 'Rider'} hired at ${data.data.store?.name ?? 'their store'}.`) }
    } catch (error) { fail(error) } finally { setBusyId(null) }
  }

  // Bring the order's seller into this customer chat (one-way — they stay in
  // it until it's resolved).
  async function bringInSeller(shopId) {
    if (!thread) return
    try {
      const response = await fetch(`${API_URL}/admin/support/threads/${thread.id}/seller`, { method: 'POST', headers: jsonHeaders(), body: JSON.stringify({ shop_id: shopId }) })
      const data = await readJson(response)
      if (!response.ok) throw new Error(data.message ?? 'Could not update the chat.')
      setThread(data.data)
    } catch (error) { fail(error) }
  }

  function rejectRiderApp(app) {
    const reason = window.prompt(`Reason for rejecting ${app.user?.name ?? 'this applicant'} (they see it):`, '')
    if (reason?.trim()) riderAppAction(app, 'reject', { reason: reason.trim() })
  }

  async function riderPayAction(rider, path, body) {
    setBusyId(rider.id)
    setMessage('')
    try {
      const response = await fetch(`${API_URL}/admin/riders/${rider.id}/${path}`, { method: 'POST', headers: jsonHeaders(), body: JSON.stringify(body) })
      const data = await readJson(response)
      if (!response.ok) throw new Error(data.message ?? Object.values(data.errors ?? {})[0]?.[0] ?? 'Could not update rider pay.')
      setRiderDetail((cur) => (cur?.rider?.id === rider.id ? { ...cur, pay: data.data } : cur))
      setRiders((cur) => cur.map((r) => (r.id === rider.id ? { ...r, earnings_balance_cents: data.data.balance_cents, payout_requested_cents: data.data.pending_payout_request?.amount_cents ?? null } : r)))
      setNotifications((cur) => ({ ...cur, rider_payout_requests: (cur.rider_payout_requests ?? []).filter((r) => r.rider_id !== rider.id) }))
    } catch (error) { fail(error) } finally { setBusyId(null) }
  }

  function recordRiderPayout(rider, pay) {
    const max = pay.max_payout_cents ?? 0
    const suggested = pay.pending_payout_request?.amount_cents ?? (max > 0 ? Math.min(pay.owed_cents, max) : pay.owed_cents)
    const amountStr = window.prompt(`Payout to ${rider.name} ($) — owed ${money(pay.owed_cents)}${max > 0 ? `, max ${money(max)} per payout` : ''}:`, (suggested / 100).toFixed(2))
    if (!amountStr) return
    const amount_cents = toCents(amountStr)
    if (!amount_cents || amount_cents <= 0) { setMessage('Enter a valid payout amount.'); return }
    const note = window.prompt('Note (optional, e.g. transfer reference):', '') ?? ''
    riderPayAction(rider, 'payout', { amount_cents, note: note.trim() || undefined })
  }

  function declineRiderPayout(rider) {
    const note = window.prompt('Reason for declining this payout request (the rider sees it):', '')
    if (note?.trim()) riderPayAction(rider, 'payout-request/reject', { note: note.trim() })
  }

  async function openRiderDetail(id, view = 'full') {
    setMessage('')
    setRiderDetail({ loading: true, view })
    setRiderReport(null)
    setRiderMonth(() => { const d = new Date(); return new Date(d.getFullYear(), d.getMonth(), 1) })
    try {
      const response = await fetch(`${API_URL}/admin/riders/${id}`, { headers: authHeaders() })
      const data = await readJson(response)
      if (!response.ok) throw new Error(data.message ?? 'Could not load the rider.')
      setRiderDetail({ ...data.data, view })
    } catch (error) { setRiderDetail(null); fail(error) }
  }

  // Admin confirms the rider has handed back the cash they've collected on
  // COD orders — clears their holding balance immediately, everywhere it shows.
  async function settleRiderCash(rider) {
    if (!window.confirm(`Confirm ${rider.name} has returned ${money(rider.cash_holding_cents)} in cash?`)) return
    setBusyId(rider.id)
    try {
      const response = await fetch(`${API_URL}/admin/riders/${rider.id}/cash-settle`, { method: 'POST', headers: authHeaders() })
      const data = await readJson(response)
      if (!response.ok) throw new Error(data.message ?? 'Could not confirm the cash returned.')
      setRiderDetail((current) => (current?.rider?.id === rider.id ? { ...current, rider: { ...current.rider, cash_holding_cents: 0, cash_holding_since: null } } : current))
      setRiders((current) => current.map((r) => (r.id === rider.id ? { ...r, cash_holding_cents: 0, cash_holding_since: null } : r)))
      setMessage(`${rider.name} — ${money(data.data.settled_cents)} confirmed returned.`)
    } catch (error) { fail(error) } finally { setBusyId(null) }
  }

  const loadRiderReport = useCallback((riderId, month) => {
    const from = `${month.getFullYear()}-${String(month.getMonth() + 1).padStart(2, '0')}-01`
    const end = new Date(month.getFullYear(), month.getMonth() + 1, 0)
    const to = `${end.getFullYear()}-${String(end.getMonth() + 1).padStart(2, '0')}-${String(end.getDate()).padStart(2, '0')}`
    Promise.resolve().then(() => setRiderReport({ loading: true }))
    return fetch(`${API_URL}/admin/riders/${riderId}/attendance?from=${from}&to=${to}`, { headers: authHeaders() })
      .then(readJson)
      .then((data) => setRiderReport(data?.data ?? null))
      .catch(() => setRiderReport(null))
  }, [authHeaders])

  useEffect(() => {
    const id = riderDetail?.rider?.id
    if (id && riderDetail?.view !== 'reviews') loadRiderReport(id, riderMonth)
  }, [riderDetail?.rider?.id, riderDetail?.view, riderMonth, loadRiderReport])

  async function toggleRider(id, isRider) {
    try {
      const response = await fetch(`${API_URL}/admin/customers/${id}`, { method: 'PATCH', headers: jsonHeaders(), body: JSON.stringify({ is_rider: isRider }) })
      const data = await readJson(response)
      if (!response.ok) throw new Error(data.message ?? 'Could not update.')
      setCustomerDetail((c) => (c && c.id === id ? { ...c, is_rider: data.data.is_rider } : c))
      setCustomers((list) => list.map((c) => (c.id === id ? { ...c, is_rider: data.data.is_rider } : c)))
    } catch (error) { fail(error) }
  }

  // Stable per-item keys so a manually-dismissed notification (the × button)
  // stays hidden across polls/reloads until the underlying item genuinely
  // changes (e.g. a new refusal on the same order gets a new "at" stamp).
  const packKey = (o) => `pack-${o.order_id}`
  const refusedKey = (o) => `refused-${o.order_id}`
  const cashKey = (c) => `cash-${c.rider_id}`
  const fbKey = (f) => `fb-${f.source}-${f.order_id}-${f.at}`
  const finKey = (a) => `fin-${a.type}-${a.order_id}-${a.at}`

  const visibleAwaitingPacking = (notifications.awaiting_packing ?? []).filter((o) => !dismissedNotifs.has(packKey(o)))
  const visibleRefusedCod = (notifications.refused_cod ?? []).filter((o) => !dismissedNotifs.has(refusedKey(o)))
  const visibleCashOverdue = (notifications.cash_overdue ?? []).filter((c) => !dismissedNotifs.has(cashKey(c)))
  const visibleNegativeFeedback = (notifications.negative_feedback ?? []).filter((f) => !dismissedNotifs.has(fbKey(f)))
  const visibleFinancialActivity = (notifications.financial_activity ?? []).filter((a) => !dismissedNotifs.has(finKey(a)))

  // A busy week can produce more negative-feedback/financial-activity events
  // than the fetched sample (10) — anything beyond that sample was never
  // fetched, so it can't be individually dismissed. Counting it in the badge
  // anyway meant the badge (and this panel) could never reach zero once that
  // backlog built up, which looked exactly like a dismissed item "coming
  // back." The badge only ever counts what's actually fetched and dismissable;
  // "+N more this week" (rendered separately, below) covers the rest.
  const negativeFeedbackHidden = (notifications.negative_feedback ?? []).length - visibleNegativeFeedback.length
  const financialActivityHidden = (notifications.financial_activity ?? []).length - visibleFinancialActivity.length

  const payoutRequests = notifications.payout_requests ?? []
  const labelRequests = notifications.label_requests ?? []
  const riderPayoutRequests = notifications.rider_payout_requests ?? []
  const riderApplications = notifications.rider_applications ?? []
  const sellerApplications = notifications.seller_applications ?? []
  const notificationCount = payoutRequests.length
    + labelRequests.length
    + sellerApplications.length
    + riderPayoutRequests.length
    + riderApplications.length
    + visibleRefusedCod.length
    + visibleCashOverdue.length
    + visibleNegativeFeedback.length
    + visibleFinancialActivity.length

  const homeMarket = settings?.home_market ?? 'US'
  const marketOptions = settings?.all_markets?.length ? settings.all_markets : [{ code: homeMarket, name: settings?.home_market_name ?? 'United States', currency: settings?.home_currency ?? 'usd' }]
  const activeMarket = marketOptions.some((m) => m.code === adminMarket) ? adminMarket : homeMarket
  const activeCurrency = marketOptions.find((m) => m.code === activeMarket)?.currency ?? 'usd'
  setStoreCurrency(activeCurrency)
  const chargesMarket = activeMarket === 'US' ? 'home' : activeMarket // the US uses the original charge forms
  const switchAdminMarket = (code) => {
    setAdminMarket(code)
    try { localStorage.setItem('nextech_admin_market', code) } catch { /* ignore */ }
    setOrderDetail(null)
    setProductForm(null)
  }

  const toggleNav = () => setNavOpen((open) => {
    const next = !open
    try { localStorage.setItem('gdp_admin_nav', next ? '1' : '0') } catch { /* ignore */ }
    return next
  })

  const goTab = (name) => {
    // Leaving Secure access re-locks it; entering it starts the challenge fresh.
    if (tab === 'secure' && name !== 'secure') {
      setSecureGate('locked'); setSecureSecret(''); setSecureToken(''); setSecureMsg('')
    }
    if (name === 'secure' && tab !== 'secure') {
      setSecureGate('code'); setSecureSecret(''); setSecureToken(''); setSecureMsg('')
      if ((settings?.secure_access?.method ?? 'password') === 'otp') challengeSecure()
    }
    setTab(name)
    setMessage('')
    // Any open edit form belongs to the tab you're leaving — close them all.
    setProductForm(null); setCategoryForm(null); setStoreForm(null); setRiderForm(null)
    setPageForm(null)
    // On a narrow screen the sidebar overlays the content — close it after a pick.
    if (typeof window !== 'undefined' && window.matchMedia('(max-width: 860px)').matches) {
      setNavOpen(false)
    }
  }

  return (
    <div className={`admin-shell${navOpen ? '' : ' nav-collapsed'}`}>
      <header className="admin-bar">
        <button className="admin-menu-toggle" type="button" aria-label={navOpen ? 'Hide menu' : 'Show menu'} aria-expanded={navOpen} onClick={toggleNav}>☰</button>
        <div className="admin-brand"><span>g</span> Admin console</div>
        <div className="admin-bar-right">
          {marketOptions.length > 1 && (
            <select className="admin-market-select" aria-label="Currency" title="Show entries for this currency / country" value={activeMarket} onChange={(event) => switchAdminMarket(event.target.value)}>
              {marketOptions.map((m) => <option key={m.code} value={m.code}>{currencySymbol(m.currency)} {m.currency.toUpperCase()} · {m.name}</option>)}
            </select>
          )}
          {TOP_TABS.map((name) => (
            <button key={name} type="button" className={`admin-top-tab${tab === name ? ' active' : ''}`} onClick={() => goTab(name)}>
              {TAB_LABELS[name]}{name === 'support' && supportBadge > 0 && <span className="tab-badge">{supportBadge}</span>}
            </button>
          ))}
          <div className="admin-bell-wrap">
            <button className={`admin-close${bellShaking ? ' shaking' : ''}`} type="button" title="Notifications" aria-expanded={bellOpen} onClick={() => setBellOpen((v) => !v)}>
              🔔{notificationCount > 0 && <span className="admin-bell-badge">{notificationCount > 99 ? '99+' : notificationCount}</span>}
            </button>
            {bellOpen && (
              <div className="admin-bell-pop" role="menu">
                <h4>Needs attention</h4>
                {notificationCount === 0 ? <p className="muted">Nothing outstanding.</p> : (
                  <>
                    {labelRequests.length > 0 && (
                      <section>
                        <h5>Shipping labels to upload</h5>
                        {labelRequests.slice(0, BELL_ITEM_CAP).map((r) => (
                          <div className="admin-bell-row" key={`label-${r.id}`}>
                            <button type="button" className="admin-bell-item warn" onClick={() => { setBellOpen(false); goTab('orders') }}>
                              🏷️ {r.shop_name ?? 'Seller'} — order #{r.order_id} · {new Date(r.at).toLocaleDateString()}
                            </button>
                          </div>
                        ))}
                        {labelRequests.length > BELL_ITEM_CAP && (
                          <button type="button" className="admin-bell-more" onClick={() => { setBellOpen(false); goTab('orders') }}>+{labelRequests.length - BELL_ITEM_CAP} more — see Orders</button>
                        )}
                      </section>
                    )}
                    {payoutRequests.length > 0 && (
                      <section>
                        <h5>Seller payout requests</h5>
                        {payoutRequests.slice(0, BELL_ITEM_CAP).map((r) => (
                          <div className="admin-bell-row" key={`payout-${r.id}`}>
                            <button type="button" className="admin-bell-item warn" onClick={() => { setBellOpen(false); goTab('sellers'); if (r.seller_id) openSellerDetail(r.seller_id) }}>
                              💸 {r.shop_name ?? 'Seller'} — {money(r.amount_cents)} · {new Date(r.at).toLocaleDateString()}
                            </button>
                          </div>
                        ))}
                        {payoutRequests.length > BELL_ITEM_CAP && (
                          <button type="button" className="admin-bell-more" onClick={() => { setBellOpen(false); goTab('sellers') }}>+{payoutRequests.length - BELL_ITEM_CAP} more — see Sellers</button>
                        )}
                      </section>
                    )}
                    {riderPayoutRequests.length > 0 && (
                      <section>
                        <h5>Rider payout requests</h5>
                        {riderPayoutRequests.slice(0, BELL_ITEM_CAP).map((r) => (
                          <div className="admin-bell-row" key={`rpay-${r.id}`}>
                            <button type="button" className="admin-bell-item warn" onClick={() => { setBellOpen(false); goTab('riders'); openRiderDetail(r.rider_id) }}>
                              🛵 {r.rider_name ?? 'Rider'} — {money(r.amount_cents)} · {new Date(r.at).toLocaleDateString()}
                            </button>
                          </div>
                        ))}
                        {riderPayoutRequests.length > BELL_ITEM_CAP && (
                          <button type="button" className="admin-bell-more" onClick={() => { setBellOpen(false); goTab('riders') }}>+{riderPayoutRequests.length - BELL_ITEM_CAP} more — see Riders</button>
                        )}
                      </section>
                    )}
                    {sellerApplications.length > 0 && (
                      <section>
                        <h5>New seller applications</h5>
                        {sellerApplications.slice(0, BELL_ITEM_CAP).map((a) => (
                          <div className="admin-bell-row" key={`sapp-${a.id}`}>
                            <button type="button" className="admin-bell-item" onClick={() => { setBellOpen(false); goTab('sellers'); openSellerDetail(a.id) }}>
                              🏪 {a.name ?? 'New seller'} · {new Date(a.at).toLocaleDateString()}
                            </button>
                          </div>
                        ))}
                        {sellerApplications.length > BELL_ITEM_CAP && (
                          <button type="button" className="admin-bell-more" onClick={() => { setBellOpen(false); goTab('sellers') }}>+{sellerApplications.length - BELL_ITEM_CAP} more — see Sellers</button>
                        )}
                      </section>
                    )}
                    {riderApplications.length > 0 && (
                      <section>
                        <h5>Rider applications</h5>
                        {riderApplications.slice(0, BELL_ITEM_CAP).map((a) => (
                          <div className="admin-bell-row" key={`rapp-${a.id}`}>
                            <button type="button" className="admin-bell-item" onClick={() => { setBellOpen(false); goTab('riders') }}>
                              🙋 {a.name ?? 'Applicant'}{a.store_name ? ` — ${a.store_name}` : ''} · {new Date(a.at).toLocaleDateString()}
                            </button>
                          </div>
                        ))}
                        {riderApplications.length > BELL_ITEM_CAP && (
                          <button type="button" className="admin-bell-more" onClick={() => { setBellOpen(false); goTab('riders') }}>+{riderApplications.length - BELL_ITEM_CAP} more — see Riders</button>
                        )}
                      </section>
                    )}
                    {visibleRefusedCod.length > 0 && (
                      <section>
                        <h5>Customer refused C.O.D.</h5>
                        {visibleRefusedCod.slice(0, BELL_ITEM_CAP).map((o) => (
                          <div className="admin-bell-row" key={refusedKey(o)}>
                            <button type="button" className="admin-bell-item danger" onClick={() => openOrderById(o.order_id)}>
                              🚫 Order #{o.order_id}{o.reason ? ` — ${o.reason}` : ''}
                            </button>
                            <button type="button" className="admin-bell-x" title="Dismiss" onClick={(event) => { event.stopPropagation(); dismissNotif(refusedKey(o)) }}>×</button>
                          </div>
                        ))}
                        {visibleRefusedCod.length > BELL_ITEM_CAP && (
                          <button type="button" className="admin-bell-more" onClick={() => { setBellOpen(false); goTab('orders') }}>+{visibleRefusedCod.length - BELL_ITEM_CAP} more — see Orders</button>
                        )}
                      </section>
                    )}
                    {visibleCashOverdue.length > 0 && (
                      <section>
                        <h5>Cash not returned</h5>
                        {visibleCashOverdue.slice(0, BELL_ITEM_CAP).map((c) => (
                          <div className="admin-bell-row" key={cashKey(c)}>
                            <button type="button" className="admin-bell-item warn" onClick={() => { setBellOpen(false); goTab('riders'); openRiderDetail(c.rider_id) }}>
                              💰 {c.rider_name} — {money(c.holding_cents)} since {new Date(c.since).toLocaleDateString()}
                            </button>
                            <button type="button" className="admin-bell-x" title="Dismiss" onClick={(event) => { event.stopPropagation(); dismissNotif(cashKey(c)) }}>×</button>
                          </div>
                        ))}
                        {visibleCashOverdue.length > BELL_ITEM_CAP && (
                          <button type="button" className="admin-bell-more" onClick={() => { setBellOpen(false); goTab('riders') }}>+{visibleCashOverdue.length - BELL_ITEM_CAP} more — see Riders</button>
                        )}
                      </section>
                    )}
                    {visibleNegativeFeedback.length > 0 && (
                      <section>
                        <h5>Negative feedback (7 days)</h5>
                        {visibleNegativeFeedback.slice(0, BELL_ITEM_CAP).map((f) => (
                          <div className="admin-bell-row" key={fbKey(f)}>
                            <button type="button" className="admin-bell-item danger" onClick={() => f.order_id && openOrderById(f.order_id)}>
                              {'★'.repeat(f.rating)}{'☆'.repeat(5 - f.rating)} {f.source === 'site' ? `Site feedback${f.rider_name ? ` — ${f.rider_name}` : ''}` : `Order #${f.order_id}${f.source === 'chat' ? ' · chat' : ' · delivery'}`}{f.comment ? ` — “${f.comment}”` : ''}
                            </button>
                            <button type="button" className="admin-bell-x" title="Dismiss" onClick={(event) => { event.stopPropagation(); dismissNotif(fbKey(f)) }}>×</button>
                          </div>
                        ))}
                        {notifications.negative_feedback_total - negativeFeedbackHidden > BELL_ITEM_CAP && (
                          <span className="admin-bell-more">+{notifications.negative_feedback_total - negativeFeedbackHidden - BELL_ITEM_CAP} more this week</span>
                        )}
                      </section>
                    )}
                    {visibleFinancialActivity.length > 0 && (
                      <section>
                        <h5>Recent refunds &amp; gift cards (7 days)</h5>
                        {visibleFinancialActivity.slice(0, BELL_ITEM_CAP).map((a) => (
                          <div className="admin-bell-row" key={finKey(a)}>
                            <button type="button" className="admin-bell-item" onClick={() => openOrderById(a.order_id)}>
                              {a.type === 'gift_card' ? '🎁' : '↩'} Order #{a.order_id} — {money(a.amount_cents)}{a.reason ? ` — ${a.reason}` : ''}{a.issued_by_name ? ` (by ${a.issued_by_name})` : ''}
                            </button>
                            <button type="button" className="admin-bell-x" title="Dismiss" onClick={(event) => { event.stopPropagation(); dismissNotif(finKey(a)) }}>×</button>
                          </div>
                        ))}
                        {notifications.financial_activity_total - financialActivityHidden > BELL_ITEM_CAP && (
                          <button type="button" className="admin-bell-more" onClick={() => { setBellOpen(false); goTab('orders') }}>+{notifications.financial_activity_total - financialActivityHidden - BELL_ITEM_CAP} more this week — see Orders</button>
                        )}
                      </section>
                    )}
                  </>
                )}
              </div>
            )}
          </div>
          <div className="admin-bell-wrap">
            <button className="admin-close soundtoggle" type="button" title="New orders &amp; chat messages" aria-expanded={speakerOpen} onClick={() => setSpeakerOpen((v) => !v)}>
              {soundMuted ? '🔇' : '🔊'}
              {(visibleAwaitingPacking.length + pendingThreads.length) > 0 && (
                <span className="admin-bell-badge">{(visibleAwaitingPacking.length + pendingThreads.length) > 99 ? '99+' : visibleAwaitingPacking.length + pendingThreads.length}</span>
              )}
            </button>
            {speakerOpen && (
              <div className="admin-bell-pop" role="menu">
                <h4>New orders &amp; chats</h4>
                <button type="button" className="admin-bell-mute" onClick={() => setSoundMuted((m) => { const next = !m; try { localStorage.setItem('gdp_support_muted', next ? '1' : '0') } catch { /* ignore */ } return next })}>
                  {soundMuted ? '🔇 Sound is muted — tap to unmute' : '🔊 Sound is on — tap to mute'}
                </button>
                {visibleAwaitingPacking.length === 0 && pendingThreads.length === 0 ? <p className="muted">Nothing new.</p> : (
                  <>
                    {visibleAwaitingPacking.length > 0 && (
                      <section>
                        <h5>New orders to pack</h5>
                        {visibleAwaitingPacking.slice(0, BELL_ITEM_CAP).map((o) => (
                          <button key={packKey(o)} type="button" className="admin-bell-item" onClick={goToOrder}>
                            🆕 Order #{o.order_id} — {money(o.total_cents, o.currency)}{o.customer ? ` — ${o.customer}` : ''}
                          </button>
                        ))}
                        {visibleAwaitingPacking.length > BELL_ITEM_CAP && (
                          <button type="button" className="admin-bell-more" onClick={goToOrder}>+{visibleAwaitingPacking.length - BELL_ITEM_CAP} more — see Orders</button>
                        )}
                      </section>
                    )}
                    {pendingThreads.length > 0 && (
                      <section>
                        <h5>New chat messages</h5>
                        {pendingThreads.slice(0, BELL_ITEM_CAP).map((t) => (
                          <button key={t.id} type="button" className="admin-bell-item" onClick={() => { setSpeakerOpen(false); goTab('support'); openThread(t.id) }}>
                            💬 {t.user?.email ?? 'Customer'}{t.order_id ? ` · order #${t.order_id}` : ''}
                          </button>
                        ))}
                        {pendingThreads.length > BELL_ITEM_CAP && (
                          <button type="button" className="admin-bell-more" onClick={() => { setSpeakerOpen(false); goTab('support') }}>+{pendingThreads.length - BELL_ITEM_CAP} more — see Support</button>
                        )}
                      </section>
                    )}
                  </>
                )}
              </div>
            )}
          </div>
          <button className="admin-close" type="button" onClick={onClose}>Back to store</button>
        </div>
      </header>

      {(supportToasts.length > 0 || orderToasts.length > 0 || ratingToasts.length > 0) && (
        <div className="admin-toasts">
          {orderToasts.map((t) => (
            <div key={`o-${t.id}`} className="admin-toast admin-toast-order">
              <button type="button" className="admin-toast-body" onClick={() => { setOrderToasts((cur) => cur.filter((x) => x.id !== t.id)); goTab('orders') }}>
                🛒 {t.text} <span>Open →</span>
              </button>
              <button type="button" className="admin-bell-x" title="Dismiss" onClick={() => setOrderToasts((cur) => cur.filter((x) => x.id !== t.id))}>×</button>
            </div>
          ))}
          {supportToasts.map((t) => (
            <div key={`${t.id}-${t.text}`} className="admin-toast">
              <button type="button" className="admin-toast-body" onClick={() => { goTab('support'); openThread(t.id) }}>
                💬 {t.text} <span>Open →</span>
              </button>
              <button type="button" className="admin-bell-x" title="Dismiss" onClick={() => setSupportToasts((cur) => cur.filter((x) => x.id !== t.id))}>×</button>
            </div>
          ))}
          {ratingToasts.map((t) => (
            <div key={t.id} className={`admin-toast admin-toast-rating ${t.tone}`}>
              <button type="button" className="admin-toast-body" onClick={() => { setRatingToasts((cur) => cur.filter((x) => x.id !== t.id)); if (t.orderId) openOrderById(t.orderId) }}>
                {t.text}
              </button>
              <button type="button" className="admin-bell-x" title="Dismiss" onClick={() => setRatingToasts((cur) => cur.filter((x) => x.id !== t.id))}>×</button>
            </div>
          ))}
        </div>
      )}

      <div className="admin-body">
        <nav className="admin-sidebar" aria-label="Admin sections">
          {PRIMARY_TABS.map((name) => (
            <button key={name} type="button" className={tab === name ? 'active' : ''} onClick={() => goTab(name)}>
              <span className="nav-ico" aria-hidden>{TAB_ICONS[name]}</span>
              <span className="nav-label">{TAB_LABELS[name]}</span>
            </button>
          ))}

          {(() => { const inGroup = tab === 'pages' || tab === 'footer' || tab === 'formatting'; const open = pagesExpanded; return <>
          <button type="button" className={`nav-group-toggle${inGroup ? ' active' : ''}`} aria-expanded={open} onClick={() => setPagesExpanded((v) => !v)}>
            <span className="nav-ico" aria-hidden>{'\u{1F4C4}'}</span>
            <span className="nav-label">Pages</span>
            <span className="nav-caret" aria-hidden>{open ? '▾' : '▸'}</span>
          </button>
          {open && (
            <div className="admin-nav-sub">
              <button type="button" className={tab === 'footer' ? 'active' : ''} onClick={() => goTab('footer')}>Footer</button>
              <button type="button" className={tab === 'pages' && !pageForm && !pageGroupFilter ? 'active' : ''} onClick={() => { goTab('pages'); setPageForm(null); setPageGroupFilter(null) }}>All pages</button>

              {MENU_PLACEMENT_OPTIONS.map((key) => {
                const groupPages = pages.filter((p) => Array.isArray(p.menu_placements) && p.menu_placements.includes(key))
                const isOpen = !!expandedPageGroups[key]
                return (
                  <div key={key}>
                    <button type="button" className={`nav-subgroup-toggle${pageGroupFilter === key ? ' active' : ''}`} aria-expanded={isOpen} onClick={() => selectPageGroup(key)}>
                      {MENU_PLACEMENT_LABELS[key]}<span className="nav-caret" aria-hidden>{isOpen ? '▾' : '▸'}</span>
                    </button>
                    {isOpen && (
                      <div className="admin-nav-sub">
                        {key === 'main_footer' ? FOOTER_COLUMNS.map((fg) => {
                          const colPages = groupPages.filter((p) => (p.footer_group || 'company') === fg)
                          return (
                            <div key={fg}>
                              <span className="nav-sub-label">{FOOTER_GROUP_LABELS[fg]}</span>
                              {colPages.length === 0 && <button type="button" disabled className="nav-sub-empty">No pages</button>}
                              {colPages.map((p) => (
                                <button key={p.id} type="button" className={tab === 'pages' && pageForm?.id === p.id ? 'active' : ''} onClick={() => selectPageInGroup(p, key)}>{p.title}</button>
                              ))}
                            </div>
                          )
                        }) : <>
                          {groupPages.length === 0 && <button type="button" disabled className="nav-sub-empty">No pages</button>}
                          {groupPages.map((p) => (
                            <button key={p.id} type="button" className={tab === 'pages' && pageForm?.id === p.id ? 'active' : ''} onClick={() => selectPageInGroup(p, key)}>{p.title}</button>
                          ))}
                        </>}
                        <button type="button" className="nav-sub-add" onClick={() => { goTab('pages'); setPageGroupFilter(key); newPage({ menu_placements: [key], ...(key === 'blog' ? { show_in_footer: false } : {}) }) }}>+ New page</button>
                      </div>
                    )}
                  </div>
                )
              })}

              {(() => {
                const unassigned = pages.filter((p) => !Array.isArray(p.menu_placements) || p.menu_placements.length === 0)
                if (unassigned.length === 0) return null
                const isOpen = !!expandedPageGroups.unassigned
                return (
                  <div>
                    <button type="button" className={`nav-subgroup-toggle${pageGroupFilter === 'unassigned' ? ' active' : ''}`} aria-expanded={isOpen} onClick={() => selectPageGroup('unassigned')}>
                      Unassigned<span className="nav-caret" aria-hidden>{isOpen ? '▾' : '▸'}</span>
                    </button>
                    {isOpen && (
                      <div className="admin-nav-sub">
                        {unassigned.map((p) => (
                          <button key={p.id} type="button" className={tab === 'pages' && pageForm?.id === p.id ? 'active' : ''} onClick={() => selectPageInGroup(p, 'unassigned')}>{p.title}</button>
                        ))}
                      </div>
                    )}
                  </div>
                )
              })()}

              <button type="button" className="nav-sub-add" onClick={() => { goTab('pages'); setPageGroupFilter(null); newPage() }}>+ New page</button>
              <button type="button" className={tab === 'formatting' ? 'active' : ''} onClick={() => goTab('formatting')}>Formatting guide</button>
            </div>
          )}
          </> })()}
        </nav>
        <div className="admin-nav-scrim" role="presentation" onClick={() => setNavOpen(false)} />

        <main className="admin-main">
      {message && <p className="admin-message">{message}</p>}

      {tab === 'dashboard' && (
        <>
          <section className="admin-grid">
            {!metrics ? <Loading>Loading metrics…</Loading> : (
              <>
                <article className="metric accent" title="Orders currently marked paid. An order that's since been fully or partially refunded moves out of this figure — see the Payments vs refunds chart below for the net, all-time picture."><span>Paid revenue</span><strong>{money(metrics.revenue_cents)}</strong></article>
                <article className="metric"><span>Orders</span><strong>{metrics.orders_total}</strong></article>
                <article className="metric"><span>Avg order value</span><strong>{money(metrics.avg_order_cents ?? 0)}</strong></article>
                <article className="metric"><span>Awaiting fulfilment</span><strong>{metrics.awaiting_fulfilment}</strong></article>
                <article className="metric"><span>COD orders</span><strong>{metrics.cod_orders ?? 0}</strong></article>
                <article className="metric"><span>Discounts given</span><strong>{money(metrics.discount_cents ?? 0)}</strong></article>
                <article className="metric" title="Stripe refunds plus store-credit gift cards issued, all-time — across every order regardless of its current status."><span>Refunded</span><strong>{money(metrics.refunded_cents ?? 0)}</strong></article>
                <article className="metric"><span>Customers</span><strong>{metrics.customers}</strong></article>
                <article className="metric"><span>Products</span><strong>{metrics.products}</strong></article>
                <article className="metric"><span>Low stock (&le;5)</span><strong>{metrics.low_stock}</strong></article>
              </>
            )}
          </section>

          <section className="admin-panel">
            <h3 className="admin-subhead">Orders trend</h3>
            <div className="chart-toolbar">
              <div className="seg" role="group" aria-label="Time basis">
                {[['day', 'Daily'], ['week', 'Weekly'], ['month', 'Monthly']].map(([value, label]) => (
                  <button key={value} type="button" className={chartBucket === value ? 'active' : ''} onClick={() => { setChart(null); setChartBucket(value) }}>{label}</button>
                ))}
              </div>
              <div className="seg" role="group" aria-label="Measure (pick any combination)">
                {CHART_LINES.map(({ key, label }) => (
                  <button
                    key={key}
                    type="button"
                    className={chartMetrics.includes(key) ? 'active' : ''}
                    onClick={() => setChartMetrics((cur) => (cur.includes(key)
                      ? (cur.length > 1 ? cur.filter((k) => k !== key) : cur) // keep at least one on
                      : [...cur, key]))}
                  >{label}</button>
                ))}
              </div>
              {chart && <span className="muted chart-range">{chart.from} → {chart.to} · {chart.totals.orders} orders · {chart.totals.paid_orders} paid · {money(chart.totals.revenue_cents)} · {money(chart.totals.refunded_cents ?? 0)} refunded</span>}
            </div>

            {!chart ? <Loading>Loading chart…</Loading> : (
              <>
                <LineChart
                  lines={CHART_LINES.filter((line) => chartMetrics.includes(line.key)).map((line) => ({
                    ...line,
                    points: chart.series.map((row) => ({ label: row.label, value: row[line.key] ?? 0 })),
                  }))}
                />
                <div className="chart-pies">
                  <div>
                    <h4 className="admin-subhead">Orders by status</h4>
                    <PieChart data={Object.entries(chart.by_status).map(([status, count]) => ({ label: STATUS_LABELS[status] ?? status, value: count }))} />
                  </div>
                  <div>
                    <h4 className="admin-subhead">Orders by payment</h4>
                    <PieChart data={Object.entries(chart.by_payment_method).map(([method, count]) => ({ label: method === 'cod' ? 'Cash on delivery' : 'Card', value: count }))} />
                  </div>
                </div>
              </>
            )}
          </section>

          <section className="admin-panel">
            <h3 className="admin-subhead">When orders come in</h3>
            {!insights ? <Loading>Loading…</Loading> : (
              <>
                <Heatmap
                  rows={insights.activity?.rows ?? []}
                  matrix={insights.activity?.matrix ?? []}
                  peak={insights.activity?.peak ?? 0}
                  cols={['00:00', '23:00']}
                  cellTitle={(r, c, v) => `${(insights.activity?.rows ?? [])[r]} ${String(c).padStart(2, '0')}:00 — ${v} order${v === 1 ? '' : 's'}`}
                />
                <p className="muted chart-range">Orders by weekday and hour &middot; since {insights.activity?.since ?? ''}</p>
              </>
            )}
          </section>

          <section className="admin-panel admin-panel-compare">
           <div className="dash-split">
            <div className="dash-split-main">
            <h3 className="admin-subhead">Compared to the previous period</h3>
            <div className="chart-toolbar">
              <select className="admin-select" value={comparePreset} onChange={(event) => { setCompare(null); setComparePreset(event.target.value) }} aria-label="Comparison period">
                <option value="day">Today vs yesterday</option>
                <option value="two_day">Last 2 days vs previous 2 days</option>
                <option value="week">This week vs last week</option>
                <option value="month">This month vs last month</option>
                <option value="six_month">Last 6 months vs previous 6 months</option>
                <option value="year">This year vs last year</option>
                <option value="custom">Custom days…</option>
              </select>
              {comparePreset === 'custom' && (
                <form className="compare-custom" onSubmit={(event) => { event.preventDefault(); const n = Math.max(1, Math.min(730, Number(compareDaysDraft) || 0)); if (n) { setCompare(null); setCompareDays(n) } }}>
                  <input type="number" min="1" max="730" value={compareDaysDraft} onChange={(event) => setCompareDaysDraft(event.target.value)} aria-label="Number of days" />
                  <span className="muted">days each side</span>
                  <button className="act" type="submit">Apply</button>
                </form>
              )}
              <div className="seg" role="group" aria-label="Measure">
                {[['orders', 'Orders'], ['revenue_cents', 'Revenue']].map(([value, label]) => (
                  <button key={value} type="button" className={compareMetric === value ? 'active' : ''} onClick={() => setCompareMetric(value)}>{label}</button>
                ))}
              </div>
            </div>

            {!compare ? <Loading>Loading comparison…</Loading> : (() => {
              const fmt = compareMetric === 'orders' ? ((v) => v) : money
              const cur = compare.current[compareMetric]
              const prev = compare.previous[compareMetric]
              return (
                <>
                  <div className="compare-head">
                    <div><span className="compare-measure">{compare.current.label}{compare.partial ? ' (so far)' : ''}</span><strong>{fmt(cur)}</strong></div>
                    <Delta current={cur} previous={prev} />
                    <div className="compare-vs"><span className="compare-measure">{compare.previous.label} (full)</span><strong>{fmt(prev)}</strong></div>
                  </div>
                  <PieChart
                    format={fmt}
                    data={[
                      { label: `${compare.current.label}${compare.partial ? ' (so far)' : ''}`, value: cur },
                      { label: `${compare.previous.label} (full)`, value: prev },
                    ]}
                  />
                </>
              )
            })()}
            </div>
            <div className="dash-split-side">
              <h3 className="admin-subhead">Payments vs refunds</h3>
              <p className="muted" style={{ margin: '-4px 0 10px', fontSize: 11 }}>All-time net: every order ever collected, minus every refund and gift card issued — not just currently-paid orders, so this won&rsquo;t match &ldquo;Paid revenue&rdquo; above.</p>
              {!metrics ? <Loading>Loading…</Loading> : (
                <PieChart
                  format={money}
                  data={[
                    { label: 'Payments', value: Math.max(0, (metrics.gross_collected_cents ?? 0) - (metrics.refunded_cents ?? 0)) },
                    { label: 'Refunds', value: metrics.refunded_cents ?? 0 },
                  ]}
                />
              )}
            </div>
           </div>
          </section>
        </>
      )}

      {tab === 'orders' && (
        <section className="admin-panel">
          <LabelRequestsPanel authHeaders={authHeaders} onMessage={setMessage} onOpenOrder={openOrderById} refreshKey={labelRequests.length} />
          <div className="admin-filters">
            {STATUS_FILTERS.map((value) => (
              <button key={value} type="button" className={statusFilter === value ? 'chip active' : 'chip'} onClick={() => { setStatusFilter(value); setOrdersPage(1) }}>
                {value === 'all' ? 'All' : STATUS_LABELS[value]}
              </button>
            ))}
          </div>
          {listBusy.orders && orders.length === 0 ? <Loading>Loading orders…</Loading> : orders.length === 0 ? <p className="admin-empty">No orders for this filter.</p> : (
            <div className="admin-table-wrap">
            <table className="admin-table">
              <thead><tr><th>#</th><th>Customer</th><th>Placed</th><th>Total</th><th>Payment</th><th>Delivery</th><th>Courier</th><th>Actions</th></tr></thead>
              <tbody>
                {orders.map((order) => { const feedback = orderFeedbackTone(order); return (
                  <tr key={order.id}>
                    <td><button type="button" className="link" title="View order summary" onClick={() => setOrderDetail(order)}>#{order.id}</button></td>
                    <td>{order.user?.display_name ?? '—'}</td>
                    <td>{new Date(order.created_at).toLocaleDateString()}</td>
                    <td>{money(order.total_cents, order.currency)}<span className="admin-note">{order.items?.length ?? 0} item{order.items?.length === 1 ? '' : 's'}</span></td>
                    <td><span className={`pill pill-${order.payment_status}`}>{order.payment_status}</span><span className="admin-note">{order.payment_method === 'cod' ? 'C.O.D.' : 'Card'}</span>{order.cancelled_by === 'rider' && <span className="admin-note" style={{ color: '#a23b28' }} title={order.cancel_reason || 'Customer refused to pay on delivery'}>Customer refused to pay</span>}</td>
                    <td className={feedback ? `admin-td-fb-${feedback}` : undefined} title={feedback ? `${feedback} feedback on this order — open it to see why` : undefined}>{STATUS_LABELS[order.status] ?? order.status}{order.store && <span className="admin-note" title={`Fulfilled by ${order.store.name}${order.store.city ? `, ${order.store.city}` : ''}`}>🏬 {order.store.name}</span>}{order.status === 'completed' && order.delivery_verified === true && <span className="admin-note" style={{ color: '#2f6d34' }} title={order.delivered_at ? `Confirmed ${new Date(order.delivered_at).toLocaleString()}` : ''}>✓ code verified</span>}{!order.rider_accepted_at && order.rider_offer_expires_at && <span className="admin-note" style={{ color: '#7a5c14' }} title={`Offered${order.delivery_partner?.name ? ` to ${order.delivery_partner.name}` : ''}, expires ${new Date(order.rider_offer_expires_at).toLocaleString()}`}>⏳ offer sent</span>}{order.rider_offer_decline_count > 0 && order.status !== 'completed' && <span className="admin-note" style={{ color: '#a23b28' }} title="Riders who declined or missed this offer">↩ declined ×{order.rider_offer_decline_count}</span>}</td>
                    <td className="admin-courier">
                      {order.delivery_method === 'seller' ? (
                        <>
                          <span className="pill pill-seller">Seller ships</span>
                          {(order.packages ?? []).map((pk) => <span key={pk.id} className="admin-note">{pk.carrier} · {pk.tracking_number} · {pk.status.replace('_', ' ')}</span>)}
                          {(order.packages ?? []).length === 0 && order.status !== 'cancelled' && <span className="admin-note">awaiting seller shipment</span>}
                        </>
                      ) : order.delivery_method === 'online_courier' ? (
                        order.shipment ? (
                          <>
                            <span className="admin-note">{order.shipment.carrier} · {order.shipment.tracking_number}</span>
                            <span className={`pill pill-${order.shipment.status}`}>{order.shipment.status.replace('_', ' ')}</span>
                            {order.status !== 'completed' && order.status !== 'cancelled' && order.shipment.status !== 'delivered' && (
                              <button type="button" disabled={busyId === order.id} onClick={() => syncTracking(order)}>Sync tracking</button>
                            )}
                          </>
                        ) : <span className="muted">booking…</span>
                      ) : order.status === 'completed' || order.status === 'cancelled' ? (
                        order.courier_name || <span className="muted">—</span>
                      ) : riders.length > 0 ? (
                        <>
                          <select value={order.delivery_partner_id ?? ''} disabled={busyId === order.id}
                            onChange={(event) => patchOrder(order, { delivery_partner_id: event.target.value ? Number(event.target.value) : null })}>
                            <option value="">— rider —</option>
                            {riders.map((r) => <option key={r.id} value={r.id}>{r.name}</option>)}
                          </select>
                          {order.courier_name && !order.delivery_partner_id && (
                            <span className="admin-note">manual: {order.courier_name}
                              <button type="button" className="link" disabled={busyId === order.id} onClick={() => patchOrder(order, { courier_name: null })}> clear</button>
                            </span>
                          )}
                          {order.status === 'ready_for_delivery' && !order.delivery_partner_id && (
                            <button type="button" disabled={busyId === order.id} onClick={() => escalateToCourier(order)}>Send via online courier instead</button>
                          )}
                        </>
                      ) : (
                        // No riders configured yet — fall back to a free-text courier name.
                        <>
                          <input value={courierDraft[order.id] ?? (order.courier_name ?? '')} placeholder="courier name"
                            onChange={(event) => setCourierDraft((current) => ({ ...current, [order.id]: event.target.value }))} />
                          <button type="button" disabled={busyId === order.id || courierDraft[order.id] === undefined} onClick={() => patchOrder(order, { courier_name: (courierDraft[order.id] ?? '').trim() || null })}>Save</button>
                          {order.status === 'ready_for_delivery' && (
                            <button type="button" disabled={busyId === order.id} onClick={() => escalateToCourier(order)}>Send via online courier instead</button>
                          )}
                        </>
                      )}
                    </td>
                    <td className="admin-actions">
                      {orderActions(order) ?? (
                        <span className="muted" title={order.status === 'pending_payment' || (order.payment_status !== 'paid' && order.status !== 'completed' && order.status !== 'cancelled') ? "Nothing to do until the customer's payment goes through" : 'This order is finished'}>—</span>
                      )}
                    </td>
                  </tr>
                )})}
              </tbody>
            </table>
            </div>
          )}
          <Pager page={ordersMeta?.current_page ?? ordersPage} pageCount={ordersMeta?.last_page ?? 1} total={ordersMeta?.total ?? orders.length} onPage={setOrdersPage} pageSize={pageSize} onPageSize={setPageSize} />
        </section>
      )}

      {tab === 'products' && (
        <section className="admin-panel">
          <div className="admin-toolbar">
            <input className="admin-search" value={productSearch} placeholder="Search name or SKU" onChange={(event) => { setProductSearch(event.target.value); setProductsPage(1); setProductForm(null) }} />
            <label>Sort
              <select value={productSort} onChange={(event) => { setProductSort(event.target.value); setProductsPage(1); setProductForm(null) }}>
                <option value="newest">Newest first</option>
                <option value="oldest">Oldest first</option>
                <option value="name">Name (A–Z)</option>
                <option value="stock_low">Stock: low to high</option>
                <option value="stock_high">Stock: high to low</option>
              </select>
            </label>
            {categories.length > 0 && (
              <label>Category
                <select value={productCategory} onChange={(event) => { setProductCategory(event.target.value); setProductsPage(1); setProductForm(null) }}>
                  <option value="">All categories</option>
                  {categories.map((c) => <option key={c.id} value={c.id}>{c.name}</option>)}
                </select>
              </label>
            )}
            {stores.length > 0 && (
              <label>Store
                <select value={productStore} onChange={(event) => { setProductStore(event.target.value); setProductsPage(1); setProductForm(null) }}>
                  <option value="">All stores</option>
                  {stores.map((s) => <option key={s.id} value={s.id}>{s.name}{s.city ? ` — ${s.city}` : ''}</option>)}
                </select>
              </label>
            )}
            <label>Status
              <select value={productStatus} onChange={(event) => { setProductStatus(event.target.value); setProductsPage(1) }}>
                <option value="all">All</option>
                {PRODUCT_STATUS_FILTERS.map((s) => <option key={s} value={s}>{PRODUCT_STATUS_LABELS[s]}</option>)}
              </select>
            </label>
            <button className="act" type="button" onClick={() => { if (!stores.length) loadStores(); setProductForm({ ...EMPTY_PRODUCT, category_id: categories[0]?.id ?? '' }); scrollFormIntoView('admin-product-form') }}>New product</button>
          </div>

          {productForm && (
            <form id="admin-product-form" className="admin-form" onSubmit={saveProduct}>
              <h3>{productForm.id ? `Edit product #${productForm.id}` : 'New product'}</h3>
              <div className="admin-form-grid">
                <label>Category
                  <select required value={productForm.category_id} onChange={(event) => setProductForm({ ...productForm, category_id: event.target.value })}>
                    <option value="" disabled>Choose…</option>
                    {categories.map((category) => <option key={category.id} value={category.id}>{category.name}</option>)}
                  </select>
                  {productForm.suggested_category_name && <p className="admin-note">Seller suggested a new category: &ldquo;{productForm.suggested_category_name}&rdquo;</p>}
                </label>
                <label>Shop
                  <select value={productForm.shop_id ?? ''} onChange={(event) => setProductForm({ ...productForm, shop_id: event.target.value })}>
                    <option value="">Sold directly by NexTech</option>
                    {shops.map((shop) => <option key={shop.id} value={shop.id}>{shop.name}</option>)}
                  </select>
                </label>
                {!productForm.shop_id ? (
                  <label>Sold in (country store)
                    <select value={productForm.market || activeMarket} onChange={(event) => setProductForm({ ...productForm, market: event.target.value })}>
                      {marketOptions.map((m) => <option key={m.code} value={m.code}>{m.name} ({currencySymbol(m.currency)} {m.currency.toUpperCase()})</option>)}
                    </select>
                  </label>
                ) : <p className="admin-note">Sold in the seller&rsquo;s country store.</p>}
                <label>Name<input required value={productForm.name} onChange={(event) => setProductForm({ ...productForm, name: event.target.value })} /></label>
                <label>SKU<input value={productForm.sku ?? ''} placeholder="Auto-generated if left blank" onChange={(event) => setProductForm({ ...productForm, sku: event.target.value })} /></label>
                <label>Regular price ({currencySymbol()})<input type="number" min="0" step="0.01" placeholder="blank = not on sale" value={productForm.compare_at} onChange={(event) => setProductForm({ ...productForm, compare_at: event.target.value })} /></label>
                <label>Sale price ({currencySymbol()})<input required type="number" min="0" step="0.01" value={productForm.price} onChange={(event) => setProductForm({ ...productForm, price: event.target.value })} /></label>
                <label>Return window (days, max {feesForm?.max_return_days ?? 90})<input type="number" min="0" max={feesForm?.max_return_days ?? 90} placeholder={`default ${feesForm?.return_window_days ?? 30} · 0 = non-returnable`} value={productForm.return_days ?? ''} onChange={(event) => setProductForm({ ...productForm, return_days: event.target.value })} /></label>
                {productForm.per_store_stock
                  ? <label>Inventory<input type="text" value="Per store — see below" disabled title="This product tracks stock per store; the counts are in the Store stock section." /></label>
                  : <label>Inventory<input type="number" min="0" value={productForm.inventory_quantity} onChange={(event) => setProductForm({ ...productForm, inventory_quantity: event.target.value })} /></label>}
                <label className={productForm.is_active ? 'admin-check admin-check-live on' : 'admin-check admin-check-live'}><input type="checkbox" checked={productForm.is_active} onChange={(event) => setProductForm({ ...productForm, is_active: event.target.checked })} /> Active (visible in store)</label>
                <label>Deal type
                  <select value={productForm.deal_type ?? ''} onChange={(event) => setProductForm({ ...productForm, deal_type: event.target.value })}>
                    <option value="">— none —</option>
                    <option value="lightning">Lightning deals</option>
                    <option value="unbeatable">Unbeatable deals</option>
                  </select>
                </label>
                <label className="admin-check"><input type="checkbox" checked={!!productForm.is_exclusive_offer} onChange={(event) => setProductForm({ ...productForm, is_exclusive_offer: event.target.checked })} /> Exclusive Offer</label>
              </div>
              <label>Image
                <div className="admin-image-field">
                  {productForm.image_url && <img src={mediaUrl(productForm.image_url)} alt="" className="admin-image-preview" onError={(event) => { event.currentTarget.style.display = 'none' }} />}
                  <input placeholder="Image URL, or upload →" value={productForm.image_url ?? ''} onChange={(event) => setProductForm({ ...productForm, image_url: event.target.value })} />
                  <input type="file" accept="image/*" disabled={imgBusy} onChange={(event) => uploadImage(event.target.files?.[0], (url) => setProductForm((form) => ({ ...form, image_url: url })))} />
                  {productForm.image_url && <button type="button" className="act ghost" onClick={() => setProductForm({ ...productForm, image_url: '' })}>Clear</button>}
                </div>
              </label>
              <div className="admin-gallery-field">
                <span>More photos <span className="muted">({(productForm.images ?? []).length}/{MAX_IMAGES}) — click a photo to make it the main image</span></span>
                <div className="admin-gallery">
                  {(productForm.images ?? []).map((url, index) => (
                    <div key={url} className={url === productForm.image_url ? 'admin-gallery-item active' : 'admin-gallery-item'}>
                      <button type="button" title="Use as main image" onClick={() => setProductForm({ ...productForm, image_url: url })}>
                        <img src={mediaUrl(url)} alt="" onError={(event) => { event.currentTarget.style.display = 'none' }} />
                      </button>
                      <button type="button" className="admin-gallery-remove" title="Remove photo" onClick={() => setProductForm((form) => ({ ...form, images: form.images.filter((_, i) => i !== index), image_url: form.image_url === url ? (form.images.find((u) => u !== url) ?? '') : form.image_url }))}>&times;</button>
                    </div>
                  ))}
                  {(productForm.images ?? []).length < MAX_IMAGES && (
                    <label className="admin-gallery-add">+ Add
                      <input type="file" accept="image/*" multiple disabled={imgBusy} onChange={async (event) => {
                        const files = [...(event.target.files ?? [])]
                        event.target.value = ''
                        for (const file of files) {
                          await uploadImage(file, (url) => setProductForm((form) => (form.images ?? []).length >= MAX_IMAGES ? form : { ...form, images: [...(form.images ?? []), url], image_url: form.image_url || url }))
                        }
                      }} />
                    </label>
                  )}
                </div>
              </div>
              <label>Video <span className="muted">(optional — plays on hover over the product image)</span>
                <div className="admin-image-field">
                  {productForm.video_url && <video src={mediaUrl(productForm.video_url)} className="admin-image-preview" muted loop onError={(event) => { event.currentTarget.style.display = 'none' }} />}
                  <input placeholder="Video URL (mp4)" value={productForm.video_url ?? ''} onChange={(event) => setProductForm({ ...productForm, video_url: event.target.value })} />
                  {productForm.video_url && <button type="button" className="act ghost" onClick={() => setProductForm({ ...productForm, video_url: '' })}>Clear</button>}
                </div>
              </label>
              <label>Description<textarea rows="2" value={productForm.description ?? ''} onChange={(event) => setProductForm({ ...productForm, description: event.target.value })} /></label>

              <fieldset className="admin-fieldset">
                <legend>Options / variants</legend>
                <p className="muted">Leave empty for a single-price product. Add a row per variant &mdash; pack size, weight, colour, flavour, or a mix (e.g. &ldquo;1 kg&rdquo;, &ldquo;Red / Large&rdquo;). Each has its own price, compare-at price, stock and image; its SKU is generated automatically if left blank.</p>
                {(productForm.variants ?? []).map((row, index) => row._delete ? null : (
                  <div className="admin-variant-row" key={row.id ?? `new-${index}`}>
                    <input placeholder="Label (1 kg, Red / Large…)" value={row.label} onChange={(event) => setProductForm({ ...productForm, variants: productForm.variants.map((r, i) => i === index ? { ...r, label: event.target.value } : r) })} />
                    <input placeholder="SKU (auto if blank)" value={row.sku ?? ''} onChange={(event) => setProductForm({ ...productForm, variants: productForm.variants.map((r, i) => i === index ? { ...r, sku: event.target.value } : r) })} />
                    <input type="number" min="0" step="0.01" placeholder="Regular $" value={row.compare_at ?? ''} onChange={(event) => setProductForm({ ...productForm, variants: productForm.variants.map((r, i) => i === index ? { ...r, compare_at: event.target.value } : r) })} />
                    <input type="number" min="0" step="0.01" placeholder="Sale $" value={row.price} onChange={(event) => setProductForm({ ...productForm, variants: productForm.variants.map((r, i) => i === index ? { ...r, price: event.target.value } : r) })} />
                    <input type="number" min="0" placeholder="Stock" value={row.stock} onChange={(event) => setProductForm({ ...productForm, variants: productForm.variants.map((r, i) => i === index ? { ...r, stock: event.target.value } : r) })} />
                    <span className="admin-variant-img">
                      <input placeholder="Image URL" value={row.image_url} onChange={(event) => setProductForm({ ...productForm, variants: productForm.variants.map((r, i) => i === index ? { ...r, image_url: event.target.value } : r) })} />
                      <input type="file" accept="image/*" disabled={imgBusy} onChange={(event) => uploadImage(event.target.files?.[0], (url) => setProductForm((form) => ({ ...form, variants: form.variants.map((r, i) => i === index ? { ...r, image_url: url } : r) })))} />
                    </span>
                    <label className="admin-check"><input type="checkbox" checked={row.is_active} onChange={(event) => setProductForm({ ...productForm, variants: productForm.variants.map((r, i) => i === index ? { ...r, is_active: event.target.checked } : r) })} /> On</label>
                    <button type="button" className="act danger" onClick={() => setProductForm({ ...productForm, variants: row.id
                      ? productForm.variants.map((r, i) => i === index ? { ...r, _delete: true } : r)
                      : productForm.variants.filter((_, i) => i !== index) })}>Remove</button>
                  </div>
                ))}
                <button type="button" className="act" disabled={(productForm.variants ?? []).filter((v) => !v._delete).length >= MAX_VARIANTS} onClick={() => setProductForm({ ...productForm, variants: [...(productForm.variants ?? []), { ...EMPTY_VARIANT }] })}>Add variant{(productForm.variants ?? []).filter((v) => !v._delete).length >= MAX_VARIANTS ? ` (max ${MAX_VARIANTS})` : ''}</button>
              </fieldset>

              <fieldset className="admin-fieldset">
                <legend>Store stock</legend>
                <label className="admin-check">
                  <input type="checkbox" checked={!!productForm.per_store_stock} onChange={(event) => setProductForm({ ...productForm, per_store_stock: event.target.checked })} />
                  Track stock per store
                </label>
                {!productForm.per_store_stock
                  ? (stores.length >= 2
                    ? <p className="muted">You have <strong>{stores.length} stores</strong> — they can&rsquo;t share one inventory number. <button type="button" className="act" onClick={() => setProductForm({ ...productForm, per_store_stock: true })}>Give each store its own count</button></p>
                    : <p className="muted">Off — the single <strong>Inventory</strong> / variant <strong>Stock</strong> above applies at every store. Turn on for a multi-store shop so each store has its own count and out-of-stock state.</p>)
                  : stores.length === 0
                    ? <p className="muted">No stores yet — add them under <strong>Stores</strong> first.</p>
                    : <>
                        <p className="muted">Untick <em>Carried</em> for a store that doesn&rsquo;t sell this at all (it disappears there). Quantity 0 keeps it listed as &ldquo;out of stock&rdquo;.</p>
                        <div className="admin-scroll-x">
                          <table className="admin-stock-grid">
                            <thead><tr><th>Store</th><th>Carried</th><th>Qty</th>
                              {(productForm.variants ?? []).filter((v) => !v._delete).map((v, i) => <th key={i}>{v.label || v.sku || `Variant ${i + 1}`}</th>)}
                            </tr></thead>
                            <tbody>
                              {stores.map((store) => {
                                const row = storeStockRow(productForm, store.id)
                                const setRow = (patch) => setProductForm((form) => ({ ...form, store_stock: { ...form.store_stock, [store.id]: { ...row, ...patch } } }))
                                return (
                                  <tr key={store.id}>
                                    <td><strong>{store.name || `#${store.id}`}</strong>{storeAddress(store) && <small className="muted admin-store-addr">{storeAddress(store)}</small>}</td>
                                    <td><input type="checkbox" checked={row.is_stocked !== false} onChange={(event) => setRow({ is_stocked: event.target.checked })} /></td>
                                    <td><input type="number" min="0" value={row.base ?? ''} disabled={row.is_stocked === false} onChange={(event) => setRow({ base: event.target.value })} /></td>
                                    {(productForm.variants ?? []).map((v, i) => v._delete ? null : (
                                      <td key={i}><input type="number" min="0" value={row.variants?.[i] ?? ''} disabled={row.is_stocked === false} onChange={(event) => setRow({ variants: { ...row.variants, [i]: event.target.value } })} /></td>
                                    ))}
                                  </tr>
                                )
                              })}
                            </tbody>
                          </table>
                        </div>
                        <p className="muted">New stores show up here automatically. <button type="button" className="act ghost" onClick={() => setTab('stores')}>Add a store</button></p>
                      </>}
              </fieldset>

              <div className="admin-form-actions">
                <button className="act" type="submit">Save</button>
                <button className="act ghost" type="button" onClick={() => setProductForm(null)}>Cancel</button>
              </div>
            </form>
          )}

          {listBusy.products && products.length === 0 ? <Loading>Loading products…</Loading> : products.length === 0 ? <p className="admin-empty">No products.</p> : (
            <table className="admin-table">
              <thead><tr><th>Name</th><th>SKU</th><th>Category</th><th>Shop</th><th>Status</th><th>Price</th><th>Stock</th><th>Variants</th><th>Active</th><th></th></tr></thead>
              <tbody>
                {products.map((product) => {
                  const packs = (product.variants ?? []).filter((v) => v.is_active).length
                  return (
                  <tr key={product.id}>
                    <td>{product.name}</td>
                    <td>{product.sku}</td>
                    <td>{product.category?.name ?? '—'}</td>
                    <td>{product.shop?.name ?? <span className="muted">NexTech</span>}</td>
                    <td><span className={`pill pill-${product.status}`}>{PRODUCT_STATUS_LABELS[product.status] ?? product.status}</span>{product.status === 'rejected' && product.rejection_reason && <p className="admin-note">{product.rejection_reason}</p>}</td>
                    <td>{packs ? `${money(Math.min(...product.variants.filter((v) => v.is_active).map((v) => v.price_cents)), MARKET_CURRENCY[product.market])}+` : <>{money(product.price_cents, MARKET_CURRENCY[product.market])}{product.compare_at_price_cents > product.price_cents && <s className="muted" style={{ marginLeft: 5 }}>{money(product.compare_at_price_cents, MARKET_CURRENCY[product.market])}</s>}</>}</td>
                    <td className={(product.effective_stock ?? product.inventory_quantity) <= 5 ? 'low' : ''}>{packs ? '—' : (product.effective_stock ?? product.inventory_quantity)}{productStore && !packs ? <span className="admin-note">at {stores.find((s) => String(s.id) === String(productStore))?.name ?? 'store'}</span> : null}</td>
                    <td>{packs || '—'}</td>
                    <td>{product.is_active ? 'Yes' : 'No'}</td>
                    <td className="admin-actions">
                      <button className="act" type="button" onClick={() => { if (!stores.length) loadStores(); setProductForm({ id: product.id, category_id: product.category_id, shop_id: product.shop_id ?? '', market: product.market ?? '', name: product.name, sku: product.sku, price: (product.price_cents / 100).toFixed(2), compare_at: dollarsOrBlank(product.compare_at_price_cents), return_days: product.return_days ?? '', inventory_quantity: product.inventory_quantity, description: product.description ?? '', image_url: product.image_url ?? '', video_url: product.video_url ?? '', is_active: product.is_active, per_store_stock: (product.store_inventory ?? []).length > 0, store_stock: storeStockFrom(product), variants: variantRowsFrom(product), deal_type: product.deal_type ?? '', is_exclusive_offer: !!product.is_exclusive_offer, suggested_category_name: product.suggested_category_name ?? '', images: (product.images ?? []).map((i) => i.url) }); scrollFormIntoView('admin-product-form') }}>Edit</button>
                      {product.shop_id && product.status === 'pending' && <>
                        <button className="act" type="button" disabled={busyId === product.id} onClick={() => productAction(product, 'approve')}>Approve</button>
                        <button className="act danger" type="button" disabled={busyId === product.id} onClick={() => rejectProduct(product)}>Reject</button>
                      </>}
                      {product.shop_id && product.status === 'approved' && <button className="act danger" type="button" disabled={busyId === product.id} onClick={() => rejectProduct(product)}>Reject</button>}
                      {product.shop_id && product.status === 'rejected' && <button className="act" type="button" disabled={busyId === product.id} onClick={() => productAction(product, 'approve')}>Approve</button>}
                      <button className="act danger" type="button" onClick={() => removeProduct(product)}>Delete</button>
                    </td>
                  </tr>
                )})}
              </tbody>
            </table>
          )}
          <Pager page={productsMeta?.current_page ?? productsPage} pageCount={productsMeta?.last_page ?? 1} total={productsMeta?.total ?? products.length} onPage={setProductsPage} pageSize={pageSize} onPageSize={setPageSize} />
        </section>
      )}

      {tab === 'categories' && (
        <section className="admin-panel">
          <div className="admin-toolbar">
            <button className="act" type="button" onClick={() => { setCategoryForm({ ...EMPTY_CATEGORY }); scrollFormIntoView('admin-category-form') }}>New category</button>
          </div>

          {categoryForm && (
            <form id="admin-category-form" className="admin-form" onSubmit={saveCategory}>
              <h3>{categoryForm.id ? `Edit category #${categoryForm.id}` : 'New category'}</h3>
              <div className="admin-image-field">
                {categoryForm.image_url
                  ? <img className="admin-banner-thumb" src={mediaUrl(categoryForm.image_url)} alt="" />
                  : <div className="admin-banner-thumb placeholder">category image</div>}
                <div>
                  <label>Image URL<input value={categoryForm.image_url} onChange={(event) => setCategoryForm({ ...categoryForm, image_url: event.target.value })} /></label>
                  <input type="file" accept="image/*" disabled={imgBusy} onChange={(event) => uploadImage(event.target.files?.[0], (url) => setCategoryForm((form) => ({ ...form, image_url: url })), 'categories')} />
                  {imgBusy && <span className="muted"> uploading…</span>}
                </div>
              </div>
              <div className="admin-form-grid">
                <label>Name<input required value={categoryForm.name} onChange={(event) => setCategoryForm({ ...categoryForm, name: event.target.value })} /></label>
                <label>Slug (optional)<input value={categoryForm.slug ?? ''} onChange={(event) => setCategoryForm({ ...categoryForm, slug: event.target.value })} /></label>
                <label>Sort order<input type="number" min="0" value={categoryForm.sort_order} onChange={(event) => setCategoryForm({ ...categoryForm, sort_order: event.target.value })} /></label>
                <label className="admin-check"><input type="checkbox" checked={categoryForm.is_active} onChange={(event) => setCategoryForm({ ...categoryForm, is_active: event.target.checked })} /> Active</label>
                <label className="admin-check"><input type="checkbox" checked={categoryForm.show_on_home !== false} onChange={(event) => setCategoryForm({ ...categoryForm, show_on_home: event.target.checked })} /> Show on homepage</label>
              </div>
              <p className="muted">Homepage category tiles use this category&rsquo;s name, image and sort order.</p>
              <div className="admin-form-actions">
                <button className="act" type="submit">Save</button>
                <button className="act ghost" type="button" onClick={() => setCategoryForm(null)}>Cancel</button>
              </div>
            </form>
          )}

          {listBusy.categories && categories.length === 0 ? <Loading>Loading categories…</Loading> : categories.length === 0 ? <p className="admin-empty">No categories.</p> : (
            <table className="admin-table">
              <thead><tr><th>Image</th><th>Name</th><th>Slug</th><th>Products</th><th>Sort</th><th>Active</th><th>Homepage</th><th></th></tr></thead>
              <tbody>
                {pageSlice(categories, categoriesPage).map((category) => (
                  <tr key={category.id}>
                    <td>{category.image_url ? <img className="admin-banner-thumb" src={mediaUrl(category.image_url)} alt="" /> : <span className="muted">—</span>}</td>
                    <td>{category.name}</td>
                    <td>{category.slug}</td>
                    <td>{category.products_count ?? 0}</td>
                    <td>{category.sort_order}</td>
                    <td>{category.is_active ? 'Yes' : 'No'}</td>
                    <td>{category.show_on_home !== false ? 'Yes' : 'No'}</td>
                    <td className="admin-actions">
                      <button className="act" type="button" onClick={() => { setCategoryForm({ id: category.id, name: category.name, slug: category.slug, image_url: category.image_url ?? '', sort_order: category.sort_order, is_active: category.is_active, show_on_home: category.show_on_home !== false }); scrollFormIntoView('admin-category-form') }}>Edit</button>
                      <button className="act danger" type="button" onClick={() => removeCategory(category)}>Delete</button>
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          )}
          <Pager page={categoriesPage} pageCount={Math.max(1, Math.ceil(categories.length / pageSize))} total={categories.length} onPage={setCategoriesPage} pageSize={pageSize} onPageSize={setPageSize} />
        </section>
      )}

      {tab === 'emails' && <EmailsPanel authHeaders={authHeaders} defaultMarket={activeMarket} onMessage={setMessage} />}

      {tab === 'customers' && (
        <section className="admin-panel">
          <div className="admin-filters"><input className="admin-search" type="search" placeholder="Search name, email or phone" value={customerQuery} onChange={(event) => { setCustomerQuery(event.target.value); setCustomersPage(1) }} /></div>
          {listBusy.customers && customers.length === 0 ? <Loading>Loading customers…</Loading> : customers.length === 0 ? <p className="admin-empty">No customers yet.</p> : (
            <table className="admin-table">
              <thead><tr><th>Name</th><th>Email</th><th>Orders</th><th>Paid spend</th><th>Last order</th><th>Emails</th><th>Joined</th><th></th></tr></thead>
              <tbody>
                {customers.map((customer) => (
                  <tr key={customer.id}>
                    <td>{customer.display_name ?? customer.name}</td>
                    <td>{customer.email}</td>
                    <td>{customer.orders_count}</td>
                    <td>{money(customer.spent_cents)}</td>
                    <td>{customer.last_order_at ? new Date(customer.last_order_at).toLocaleDateString() : '—'}</td>
                    <td>{customer.emails_count ?? 0}</td>
                    <td>{new Date(customer.joined_at).toLocaleDateString()}</td>
                    <td className="admin-actions"><button className="act" type="button" onClick={() => openCustomer(customer.id)}>View</button></td>
                  </tr>
                ))}
              </tbody>
            </table>
          )}
          <Pager page={customersMeta?.current_page ?? customersPage} pageCount={customersMeta?.last_page ?? 1} total={customersMeta?.total ?? customers.length} onPage={setCustomersPage} pageSize={pageSize} onPageSize={setPageSize} />
        </section>
      )}

      {tab === 'riders' && (
        <section className="admin-panel">
          <form className="admin-toolbar" onSubmit={addRider}>
            <input type="email" placeholder="rider@example.com" value={riderEmail} onChange={(event) => setRiderEmail(event.target.value)} />
            <select required value={riderHireStoreId} onChange={(event) => setRiderHireStoreId(event.target.value)}>
              <option value="" disabled>Assign to store…</option>
              {stores.map((store) => <option key={store.id} value={store.id}>{store.name || `Store #${store.id}`}{store.city ? ` — ${store.city}` : ''}</option>)}
            </select>
            <button className="act" type="submit" disabled={stores.length === 0}>Add rider</button>
            <span className="muted">{stores.length === 0
              ? <>Add a store under <strong>Stores</strong> first — every rider needs one, so it&rsquo;s clear where their COD cash gets returned.</>
              : <>Turns an existing customer account into a delivery rider. A store is required, so it&rsquo;s always clear where the rider returns COD cash. Auto-assign picks the nearest on-shift rider linked to the order&rsquo;s store; unassigned orders fall back to the pickup pool.</>}</span>
          </form>

          {riderForm && (
            <form id="admin-rider-form" className="admin-form" onSubmit={saveRider}>
              <h3>{riderForm.name}</h3>
              <div className="admin-form-grid">
                <label>Phone<input value={riderForm.phone} placeholder="e.g. +1 555 987 6543" onChange={(event) => setRiderForm({ ...riderForm, phone: event.target.value })} /></label>
                <label>Full day (hours)<input type="number" step="0.5" min="0.5" max="24" value={riderForm.daily_target_hours} placeholder="8" onChange={(event) => setRiderForm({ ...riderForm, daily_target_hours: event.target.value })} /></label>
                <label className="admin-check"><input type="checkbox" checked={riderForm.rider_is_active} onChange={(event) => setRiderForm({ ...riderForm, rider_is_active: event.target.checked })} /> On shift (available for auto-assign)</label>
              </div>

              <fieldset className="admin-fieldset">
                <legend>Stores served</legend>
                <p className="muted">A rider only gets orders (auto-assigned or from the pool) for the stores ticked here. Tick more than one for nearby cities.</p>
                {stores.length === 0
                  ? <p className="muted">No stores yet — add them under <strong>Stores</strong> first.</p>
                  : <div className="admin-check-list">
                      {stores.map((store) => {
                        const picked = riderForm.store_ids.includes(store.id)
                        return (
                          <label className="admin-check" key={store.id}>
                            <input type="checkbox" checked={picked} onChange={(event) => setRiderForm((form) => ({
                              ...form,
                              store_ids: event.target.checked
                                ? [...form.store_ids, store.id]
                                : form.store_ids.filter((id) => id !== store.id),
                            }))} />
                            {store.name || `Store #${store.id}`}{store.city ? ` — ${store.city}` : ''}
                          </label>
                        )
                      })}
                    </div>}
              </fieldset>

              <fieldset className="admin-fieldset">
                <legend>Home base</legend>
                <p className="muted">Where auto-assign measures from when the rider app has no recent live location. Type an address (geocoded on save) or drag the pin.</p>
                <div className="admin-form-grid">
                  <label>Base address<input value={riderForm.rider_base_address} onChange={(event) => setRiderForm({ ...riderForm, rider_base_address: event.target.value })} /></label>
                  <label>Latitude<input type="number" step="any" value={riderForm.rider_base_lat} onChange={(event) => setRiderForm({ ...riderForm, rider_base_lat: event.target.value })} /></label>
                  <label>Longitude<input type="number" step="any" value={riderForm.rider_base_lng} onChange={(event) => setRiderForm({ ...riderForm, rider_base_lng: event.target.value })} /></label>
                </div>
                <MapPicker
                  lat={riderForm.rider_base_lat === '' ? NaN : Number(riderForm.rider_base_lat)}
                  lng={riderForm.rider_base_lng === '' ? NaN : Number(riderForm.rider_base_lng)}
                  onPick={(la, ln) => setRiderForm((form) => ({ ...form, rider_base_lat: la.toFixed(6), rider_base_lng: ln.toFixed(6) }))}
                />
              </fieldset>

              <div className="admin-form-actions">
                <button className="act" type="submit">Save</button>
                <button className="act ghost" type="button" onClick={() => setRiderForm(null)}>Cancel</button>
              </div>
            </form>
          )}

          {riderApps.length > 0 && (
            <>
              <h3 className="admin-subhead">Rider applications{riderApps.filter((a) => a.status === 'pending').length ? ` (${riderApps.filter((a) => a.status === 'pending').length} waiting)` : ''}</h3>
              <p className="muted">People apply at /rider, choosing a store near them. Approving makes them a rider for that store.</p>
              <table className="admin-table">
                <thead><tr><th>Applicant</th><th>Store</th><th>Vehicle</th><th>Home</th><th>Licence</th><th>Status</th><th>Applied</th><th></th></tr></thead>
                <tbody>
                  {riderApps.map((app) => (
                    <tr key={app.id}>
                      <td>{app.user?.name}<br /><span className="muted">{app.user?.email} · {app.phone}</span></td>
                      <td>{app.store?.name ?? <span className="muted">—</span>}</td>
                      <td>{app.vehicle_type}</td>
                      <td>{app.home_address}</td>
                      <td>{app.license_number || <span className="muted">—</span>}{app.license_document_path && <> <button type="button" className="act ghost" onClick={() => viewKycDocument(app.license_document_path)}>View</button></>}</td>
                      <td>{app.status === 'pending' ? <span className="admin-status-chip pending">Pending</span> : app.status === 'approved' ? <span className="admin-status-chip ok">Approved</span> : <span className="admin-status-chip bad" title={app.rejection_reason ?? ''}>Rejected</span>}</td>
                      <td>{new Date(app.created_at).toLocaleDateString()}</td>
                      <td className="admin-actions">
                        {app.status === 'pending' && (
                          <>
                            <button className="act" type="button" disabled={busyId === `app-${app.id}`} onClick={() => riderAppAction(app, 'approve')}>Approve</button>
                            <button className="act danger" type="button" disabled={busyId === `app-${app.id}`} onClick={() => rejectRiderApp(app)}>Reject</button>
                          </>
                        )}
                      </td>
                    </tr>
                  ))}
                </tbody>
              </table>
              <h3 className="admin-subhead">Riders</h3>
            </>
          )}

          {listBusy.riders && riders.length === 0 ? <Loading>Loading riders…</Loading> : riders.length === 0 ? <p className="admin-empty">No riders yet. Add one by email above.</p> : (
            <table className="admin-table">
              <thead><tr><th>Name</th><th>Phone</th><th>Status</th><th>Stores</th><th>Location</th><th>Rating</th><th>Active jobs</th><th>Earnings</th><th>On shift</th><th></th></tr></thead>
              <tbody>
                {pageSlice(riders, ridersPage).map((rider) => (
                  <tr key={rider.id}>
                    <td>{rider.name}</td>
                    <td>{rider.phone || <span className="muted">—</span>}</td>
                    <td>{riderStatusChip(rider)}</td>
                    <td className={rider.cash_holding_cents > 0 ? (cashHoldingOverdue(rider.cash_holding_since) ? 'admin-td-cash-overdue' : 'admin-td-cash-today') : undefined}>{(rider.stores ?? []).length
                      ? (rider.stores).map((s) => s.name).join(', ')
                      : <span className="muted">none — can&rsquo;t be auto-assigned</span>}
                      {rider.cash_holding_cents > 0 && (
                        <span className="admin-note admin-cash-note" title={rider.cash_holding_since ? `holding since ${new Date(rider.cash_holding_since).toLocaleDateString()}` : ''}>
                          💰 {money(rider.cash_holding_cents)} to return
                        </span>
                      )}</td>
                    <td>{rider.located
                      ? <span title={rider.located.last_ping_at ? `pinged ${new Date(rider.located.last_ping_at).toLocaleString()}` : ''}>{rider.located.source === 'live' ? '🟢 live' : '📍 base'}</span>
                      : <span className="muted">no base set</span>}</td>
                    <td><button className="act ghost" type="button" onClick={() => openRiderDetail(rider.id, 'reviews')}>{rider.rating_count ? `★ ${(rider.rating_avg ?? 0).toFixed(1)} (${rider.rating_count})` : 'Reviews'}</button></td>
                    <td className={rider.active_deliveries > 0 ? 'low' : ''}>{rider.active_deliveries}</td>
                    <td>{money(rider.earnings_balance_cents ?? 0)}{rider.payout_requested_cents != null && <span className="admin-note admin-cash-note">💸 {money(rider.payout_requested_cents)} requested</span>}</td>
                    <td>{rider.rider_is_active ? 'Yes' : 'No'}</td>
                    <td className="admin-actions">
                      <button className="act" type="button" onClick={() => openRiderDetail(rider.id)}>Attendance &amp; stats</button>
                      <button className="act" type="button" onClick={() => { setRiderForm(riderFormFrom(rider)); scrollFormIntoView('admin-rider-form') }}>Edit</button>
                      {rider.attendance?.available
                        ? <button className="act" type="button" onClick={() => { const why = window.prompt('Reason for taking this rider offline (optional):', ''); if (why !== null) patchRider(rider, { rider_available: false, rider_unavailable_reason: why || null }) }}>Set offline</button>
                        : rider.attendance?.clocked_in && <button className="act" type="button" onClick={() => patchRider(rider, { rider_available: true })}>Bring online</button>}
                      <button className="act danger" type="button" onClick={() => removeRider(rider)}>Remove</button>
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          )}
          <Pager page={ridersPage} pageCount={Math.max(1, Math.ceil(riders.length / pageSize))} total={riders.length} onPage={setRidersPage} pageSize={pageSize} onPageSize={setPageSize} />
        </section>
      )}

      {tab === 'sellers' && (
        <section className="admin-panel">
          <div className="admin-toolbar">
            <label>Status
              <select value={sellerStatus} onChange={(event) => { setSellerStatus(event.target.value); setSellersPage(1) }}>
                {SELLER_STATUS_FILTERS.map((s) => <option key={s} value={s}>{SELLER_STATUS_LABELS[s]}</option>)}
                <option value="all">All</option>
              </select>
            </label>
            <span className="muted">Applications sellers submit at /seller. Approving flips the shop live on the storefront.</span>
          </div>

          {listBusy.sellers && sellers.length === 0 ? <Loading>Loading applications…</Loading> : sellers.length === 0 ? <p className="admin-empty">No {sellerStatus === 'all' ? '' : SELLER_STATUS_LABELS[sellerStatus].toLowerCase() + ' '}applications.</p> : (
            <table className="admin-table">
              <thead><tr><th>Shop</th><th>Contact</th><th>Country</th><th>Business type</th><th>Status</th><th>Last message</th><th>Submitted</th><th></th></tr></thead>
              <tbody>
                {pageSlice(sellers, sellersPage).map((seller) => (
                  <tr key={seller.id}>
                    <td>{seller.shop?.name ?? '—'}</td>
                    <td>{seller.user?.name}<br /><span className="muted">{seller.user?.email}</span></td>
                    <td>{seller.country}</td>
                    <td>{seller.business_type}</td>
                    <td><span className={`pill pill-${seller.status}`}>{SELLER_STATUS_LABELS[seller.status] ?? seller.status}</span>{seller.reviews_pending > 0 && <span className="pill pill-pending" title="Onboarding tasks (tax, compliance, bank) waiting for review">{seller.reviews_pending} to review</span>}</td>
                    <td>{seller.last_message ? <span className="muted">{seller.last_message.is_staff ? 'You: ' : ''}{seller.last_message.body.length > 60 ? `${seller.last_message.body.slice(0, 60)}…` : seller.last_message.body}</span> : <span className="muted">—</span>}</td>
                    <td>{seller.submitted_at ? new Date(seller.submitted_at).toLocaleDateString() : '—'}</td>
                    <td className="admin-actions">
                      <button className="act" type="button" onClick={() => openSellerDetail(seller.id)}>Review</button>
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          )}
          <Pager page={sellersPage} pageCount={Math.max(1, Math.ceil(sellers.length / pageSize))} total={sellers.length} onPage={setSellersPage} pageSize={pageSize} onPageSize={setPageSize} />
        </section>
      )}

      {tab === 'stores' && (
        <section className="admin-panel">
          <div className="admin-toolbar">
            <button className="act" type="button" onClick={() => { setStoreForm({ ...EMPTY_STORE }); scrollFormIntoView('admin-store-form') }}>New store</button>
            <span className="muted">Customers outside every active store&rsquo;s radius can browse but can&rsquo;t check out.</span>
          </div>

          {storeForm && (
            <form id="admin-store-form" className="admin-form" onSubmit={saveStore}>
              <h3>{storeForm.id ? `Edit store #${storeForm.id}` : 'New store'}</h3>
              <div className="admin-form-grid">
                <label>Name<input value={storeForm.name} placeholder="Main Store" onChange={(event) => setStoreForm({ ...storeForm, name: event.target.value })} /></label>
                <label>Street<input required value={storeForm.line1} onChange={(event) => setStoreForm({ ...storeForm, line1: event.target.value })} /></label>
                <label>Line 2<input value={storeForm.line2 ?? ''} onChange={(event) => setStoreForm({ ...storeForm, line2: event.target.value })} /></label>
                <label>City<input required value={storeForm.city} onChange={(event) => setStoreForm({ ...storeForm, city: event.target.value })} /></label>
                <label>State<input required maxLength="60" value={storeForm.state} onChange={(event) => setStoreForm({ ...storeForm, state: event.target.value })} /></label>
                <label>Postal code<input required maxLength="12" value={storeForm.postal_code} onChange={(event) => setStoreForm({ ...storeForm, postal_code: event.target.value })} /></label>
                <label>Country<select value={storeForm.country || activeMarket} onChange={(event) => setStoreForm({ ...storeForm, country: event.target.value })}>{marketOptions.map((m) => <option key={m.code} value={m.code}>{m.name}</option>)}</select></label>
                <label>Delivery radius (km)<input required type="number" min="1" max="200" value={storeForm.delivery_radius_km} onChange={(event) => setStoreForm({ ...storeForm, delivery_radius_km: event.target.value })} /></label>
                <label>Latitude (optional)<input type="number" step="any" value={storeForm.latitude ?? ''} onChange={(event) => setStoreForm({ ...storeForm, latitude: event.target.value })} /></label>
                <label>Longitude (optional)<input type="number" step="any" value={storeForm.longitude ?? ''} onChange={(event) => setStoreForm({ ...storeForm, longitude: event.target.value })} /></label>
                <label className="admin-check"><input type="checkbox" checked={storeForm.is_active} onChange={(event) => setStoreForm({ ...storeForm, is_active: event.target.checked })} /> Active</label>
              </div>
              <p className="muted">Type an address (geocoded on save) or drag the pin to set the exact spot. The pin fills latitude/longitude.</p>
              <MapPicker
                lat={storeForm.latitude === '' ? NaN : Number(storeForm.latitude)}
                lng={storeForm.longitude === '' ? NaN : Number(storeForm.longitude)}
                onPick={(la, ln) => setStoreForm((form) => ({ ...form, latitude: la.toFixed(6), longitude: ln.toFixed(6) }))}
              />
              <p className="muted">{storeForm.latitude !== '' && Number.isFinite(Number(storeForm.latitude)) ? `Pin: ${Number(storeForm.latitude).toFixed(5)}, ${Number(storeForm.longitude).toFixed(5)}` : 'No pin set yet — drag the marker or save with an address to locate it.'}</p>
              <div className="admin-form-actions">
                <button className="act" type="submit">Save</button>
                <button className="act ghost" type="button" onClick={() => setStoreForm(null)}>Cancel</button>
              </div>
            </form>
          )}

          {listBusy.stores && stores.length === 0 ? <Loading>Loading stores…</Loading> : stores.length === 0 ? <p className="admin-empty">No stores yet. Add one to switch on delivery-area checks.</p> : (
            <table className="admin-table">
              <thead><tr><th>Name</th><th>Address</th><th>Radius</th><th>Location</th><th>Active</th><th></th></tr></thead>
              <tbody>
                {pageSlice(stores, storesPage).map((store) => (
                  <tr key={store.id}>
                    <td>{store.name}</td>
                    <td>{[store.line1, store.city, store.state, store.postal_code].filter(Boolean).join(', ')}</td>
                    <td>{store.delivery_radius_km} km</td>
                    <td>{store.latitude != null && store.longitude != null
                      ? `${Number(store.latitude).toFixed(4)}, ${Number(store.longitude).toFixed(4)}`
                      : <span className="muted">not located — add coordinates</span>}</td>
                    <td>{store.is_active ? 'Yes' : 'No'}</td>
                    <td className="admin-actions">
                      <button className="act" type="button" onClick={() => { setStoreForm({ id: store.id, name: store.name ?? '', line1: store.line1 ?? '', line2: store.line2 ?? '', city: store.city ?? '', state: store.state ?? '', postal_code: store.postal_code ?? '', country: store.country ?? '', latitude: store.latitude ?? '', longitude: store.longitude ?? '', delivery_radius_km: store.delivery_radius_km ?? 5, is_active: store.is_active }); scrollFormIntoView('admin-store-form') }}>Edit</button>
                      <button className="act danger" type="button" onClick={() => removeStore(store)}>Delete</button>
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          )}
          <Pager page={storesPage} pageCount={Math.max(1, Math.ceil(stores.length / pageSize))} total={stores.length} onPage={setStoresPage} pageSize={pageSize} onPageSize={setPageSize} />
        </section>
      )}

      {tab === 'pages' && (() => {
        const visiblePages = pageGroupFilter
          ? pages.filter((p) => (pageGroupFilter === 'unassigned'
            ? !Array.isArray(p.menu_placements) || p.menu_placements.length === 0
            : Array.isArray(p.menu_placements) && p.menu_placements.includes(pageGroupFilter)))
          : pages
        const filterLabel = pageGroupFilter === 'unassigned' ? 'Unassigned' : MENU_PLACEMENT_LABELS[pageGroupFilter]
        return (
        <section className="admin-panel">
          {!pageForm && (
            <>
              <div className="admin-toolbar">
                <button className="act" type="button" onClick={() => { setPageGroupFilter(null); newPage() }}>New page</button>
                <span className="muted">Content pages linked from the storefront footer. Content is Markdown (## heading, **bold**, - list, [text](url)).</span>
              </div>

              {pageGroupFilter && (
                <p className="admin-filter-note">Showing <strong>{filterLabel}</strong> pages only — <button type="button" className="act ghost" onClick={() => setPageGroupFilter(null)}>Show all pages</button></p>
              )}
            </>
          )}

          {pageForm && (
            <form className="admin-form" onSubmit={savePage}>
              <div className="admin-form-topbar">
                <h3>{pageForm.id ? `Edit “${pageForm.title || 'page'}”` : 'New page'}</h3>
                <div className="admin-form-actions admin-form-actions-top">
                  <button className="act" type="submit">Save</button>
                  <button className="act ghost" type="button" onClick={() => setPageForm(null)}>&larr; Back to pages</button>
                  {pageForm.id && <button className="act danger" type="button" onClick={() => removePage(pageForm)}>Delete</button>}
                </div>
              </div>
              <label className="admin-page-title-field">Title
                <input required maxLength="160" value={pageForm.title} onChange={(event) => setPageForm({ ...pageForm, title: event.target.value })} placeholder="Page title" />
              </label>

              <div className="admin-fieldset admin-collapsible-box">
                <div className="admin-collapsible-header">
                  <span>Page settings</span>
                  <button type="button" className="admin-collapsible-arrow" aria-expanded={pageSettingsOpen} aria-label={pageSettingsOpen ? 'Collapse page settings' : 'Expand page settings'} onClick={() => setPageSettingsOpen((v) => !v)}>
                    {pageSettingsOpen ? '▾' : '▸'}
                  </button>
                </div>
                {pageSettingsOpen && (
                  <div className="admin-collapsible-body">
                    <div className="admin-form-grid">
                      <label>Slug (optional)<input value={pageForm.slug} placeholder="auto from title" onChange={(event) => setPageForm({ ...pageForm, slug: event.target.value })} /></label>
                      <label>Parent page (optional)
                        <input list="admin-page-slugs" value={pageForm.parent_slug ?? ''} placeholder="e.g. seller-services-agreement" onChange={(event) => setPageForm({ ...pageForm, parent_slug: event.target.value })} />
                        <datalist id="admin-page-slugs">{pages.filter((p) => p.slug !== pageForm.slug).map((p) => <option key={p.slug} value={p.slug}>{p.title}</option>)}</datalist>
                      </label>
                      <label>Sort order<input type="number" min="0" max="9999" value={pageForm.sort_order} onChange={(event) => setPageForm({ ...pageForm, sort_order: event.target.value })} /></label>
                      <label className="admin-check"><input type="checkbox" checked={pageForm.show_in_footer} onChange={(event) => setPageForm({ ...pageForm, show_in_footer: event.target.checked })} /> Show in footer</label>
                      <label className="admin-check"><input type="checkbox" checked={pageForm.is_published} onChange={(event) => setPageForm({ ...pageForm, is_published: event.target.checked })} /> Published</label>
                    </div>

                    <div className="admin-subhead" style={{ marginTop: 4 }}>Placement</div>
                    <p className="muted">Where this page is tracked as belonging, for the sidebar/list here — tick every menu it's actually linked from on the live site (a page can be in more than one).</p>
                    <div className="admin-check-list">
                      {MENU_PLACEMENT_OPTIONS.map((opt) => (
                        <label className="admin-check" key={opt}>
                          <input type="checkbox" checked={pageForm.menu_placements.includes(opt)}
                            onChange={(event) => setPageForm((f) => ({
                              ...f,
                              menu_placements: event.target.checked ? [...f.menu_placements, opt] : f.menu_placements.filter((v) => v !== opt),
                            }))} /> {MENU_PLACEMENT_LABELS[opt]}
                        </label>
                      ))}
                    </div>
                    {pageForm.menu_placements.includes('main_footer') && (
                      <label>Footer column
                        <select value={pageForm.footer_group} onChange={(event) => setPageForm({ ...pageForm, footer_group: event.target.value })}>
                          <option value="company">Company info</option>
                          <option value="legal">Customer service</option>
                          <option value="help">Help</option>
                          <option value="bottom">Lower footer (legal links bar)</option>
                        </select>
                      </label>
                    )}
                  </div>
                )}
              </div>

              <fieldset className="admin-fieldset admin-fieldset-content">
                <legend>Content</legend>
                <label>Banner image — shown above the title
                  <div className="admin-image-field">
                    {pageForm.banner_image && <img src={mediaUrl(pageForm.banner_image)} alt="" className="admin-image-preview" onError={(event) => { event.currentTarget.style.display = 'none' }} />}
                    <input value={pageForm.banner_image ?? ''} placeholder="/img/… or https://…, or upload →" onChange={(event) => setPageForm({ ...pageForm, banner_image: event.target.value })} />
                    <input type="file" accept="image/*" disabled={imgBusy} onChange={(event) => uploadImage(event.target.files?.[0], (url) => setPageForm((form) => ({ ...form, banner_image: url })), 'pages')} />
                    {pageForm.banner_image && <button type="button" className="act ghost" onClick={() => setPageForm({ ...pageForm, banner_image: '' })}>Clear</button>}
                  </div>
                </label>
                <div className="admin-sections">
                  <div className="admin-subhead" style={{ marginTop: 4 }}>Sections</div>
                {(pageForm.sections ?? []).length === 0
                  ? <p className="muted">No sections yet — the page shows the body text below. Add sections for a richer layout; preview on the storefront at <code>/#/p/{pageForm.slug || 'slug'}</code>.</p>
                  : null}
                {(pageForm.sections ?? []).map((s, i) => (
                  <div className="admin-section-card" key={i}>
                    <div className="admin-section-head">
                      <strong>{i + 1}. {sectionLabel(s.type)}</strong>
                      <div className="admin-section-tools">
                        <button type="button" className="act ghost" disabled={i === 0} onClick={() => moveSection(i, -1)}>&uarr;</button>
                        <button type="button" className="act ghost" disabled={i === pageForm.sections.length - 1} onClick={() => moveSection(i, 1)}>&darr;</button>
                        <button type="button" className="act danger" onClick={() => removeSection(i)}>Remove</button>
                      </div>
                    </div>
                    {s.type === 'rich_text' && (
                      <label>Markdown<textarea rows="6" value={s.markdown ?? ''} onChange={(event) => patchSection(i, { markdown: event.target.value })} /></label>
                    )}
                    {(s.type === 'hero' || s.type === 'cta') && (
                      <>
                        {s.type === 'hero' && (
                          <label>Image
                            <div className="admin-image-field">
                              {s.image_url && <img src={mediaUrl(s.image_url)} alt="" className="admin-image-preview" onError={(event) => { event.currentTarget.style.display = 'none' }} />}
                              <input value={s.image_url ?? ''} placeholder="/img/… or https://…, or upload →" onChange={(event) => patchSection(i, { image_url: event.target.value })} />
                              <input type="file" accept="image/*" disabled={imgBusy} onChange={(event) => uploadImage(event.target.files?.[0], (url) => patchSection(i, { image_url: url }), 'pages')} />
                              {s.image_url && <button type="button" className="act ghost" onClick={() => patchSection(i, { image_url: '' })}>Clear</button>}
                            </div>
                          </label>
                        )}
                        <label>Heading<input value={s.heading ?? ''} onChange={(event) => patchSection(i, { heading: event.target.value })} /></label>
                        <label>Text<textarea rows="2" value={s.text ?? ''} onChange={(event) => patchSection(i, { text: event.target.value })} /></label>
                        <div className="admin-form-grid">
                          <label>Button label<input value={s.button_label ?? ''} onChange={(event) => patchSection(i, { button_label: event.target.value })} /></label>
                          <label>Button URL<input value={s.button_url ?? ''} onChange={(event) => patchSection(i, { button_url: event.target.value })} placeholder="https://… or /#/p/…" /></label>
                        </div>
                      </>
                    )}
                    {s.type === 'media_text' && (
                      <>
                        <label>Image
                          <div className="admin-image-field">
                            {s.image_url && <img src={mediaUrl(s.image_url)} alt="" className="admin-image-preview" onError={(event) => { event.currentTarget.style.display = 'none' }} />}
                            <input value={s.image_url ?? ''} placeholder="/img/… or https://…, or upload →" onChange={(event) => patchSection(i, { image_url: event.target.value })} />
                            <input type="file" accept="image/*" disabled={imgBusy} onChange={(event) => uploadImage(event.target.files?.[0], (url) => patchSection(i, { image_url: url }), 'pages')} />
                            {s.image_url && <button type="button" className="act ghost" onClick={() => patchSection(i, { image_url: '' })}>Clear</button>}
                          </div>
                        </label>
                        <label>Image side<select value={s.image_side ?? 'left'} onChange={(event) => patchSection(i, { image_side: event.target.value })}><option value="left">Left</option><option value="right">Right</option></select></label>
                        <label>Heading<input value={s.heading ?? ''} onChange={(event) => patchSection(i, { heading: event.target.value })} /></label>
                        <label>Text (Markdown)<textarea rows="5" value={s.markdown ?? ''} onChange={(event) => patchSection(i, { markdown: event.target.value })} /></label>
                      </>
                    )}
                    {s.type === 'feature_grid' && (
                      <>
                        <label>Heading<input value={s.heading ?? ''} onChange={(event) => patchSection(i, { heading: event.target.value })} /></label>
                        {(s.items ?? []).map((it, ii) => (
                          <div className="admin-feature-row admin-feature-row--quad" key={ii}>
                            <span className="admin-variant-img">
                              <input placeholder="Icon / image URL" value={it.image_url ?? ''} onChange={(event) => patchItem(i, ii, { image_url: event.target.value })} />
                              <input type="file" accept="image/*" disabled={imgBusy} onChange={(event) => uploadImage(event.target.files?.[0], (url) => patchItem(i, ii, { image_url: url }), 'pages')} />
                            </span>
                            <input placeholder="Title" value={it.title ?? ''} onChange={(event) => patchItem(i, ii, { title: event.target.value })} />
                            <input placeholder="Text" value={it.text ?? ''} onChange={(event) => patchItem(i, ii, { text: event.target.value })} />
                            <input placeholder="Link URL (optional)" value={it.link_url ?? ''} onChange={(event) => patchItem(i, ii, { link_url: event.target.value })} />
                            <button type="button" className="act danger" onClick={() => removeItem(i, ii)}>&times;</button>
                          </div>
                        ))}
                        <button type="button" className="act ghost" onClick={() => addItem(i)}>+ Card</button>
                      </>
                    )}
                    {(s.type === 'stats' || s.type === 'steps') && (
                      <>
                        <label>Heading<input value={s.heading ?? ''} onChange={(event) => patchSection(i, { heading: event.target.value })} /></label>
                        {(s.items ?? []).map((it, ii) => (
                          <div className="admin-feature-row admin-feature-row--pair" key={ii}>
                            <input placeholder={s.type === 'stats' ? 'Value (e.g. 10 min)' : 'Step title'} value={it.title ?? ''} onChange={(event) => patchItem(i, ii, { title: event.target.value })} />
                            <input placeholder={s.type === 'stats' ? 'Caption' : 'Step description'} value={it.text ?? ''} onChange={(event) => patchItem(i, ii, { text: event.target.value })} />
                            <button type="button" className="act danger" onClick={() => removeItem(i, ii)}>&times;</button>
                          </div>
                        ))}
                        <button type="button" className="act ghost" onClick={() => addItem(i)}>+ {s.type === 'stats' ? 'Stat' : 'Step'}</button>
                      </>
                    )}
                    {s.type === 'faq' && (
                      <>
                        <label>Heading<input value={s.heading ?? ''} onChange={(event) => patchSection(i, { heading: event.target.value })} /></label>
                        {(s.items ?? []).map((it, ii) => (
                          <div className="admin-faq-row" key={ii}>
                            <input placeholder="Question" value={it.title ?? ''} onChange={(event) => patchItem(i, ii, { title: event.target.value })} />
                            <textarea rows="2" placeholder="Answer (Markdown allowed)" value={it.text ?? ''} onChange={(event) => patchItem(i, ii, { text: event.target.value })} />
                            <button type="button" className="act danger" onClick={() => removeItem(i, ii)}>Remove</button>
                          </div>
                        ))}
                        <button type="button" className="act ghost" onClick={() => addItem(i)}>+ Question</button>
                      </>
                    )}
                    {s.type === 'quote' && (
                      <>
                        <label>Quote<textarea rows="3" value={s.text ?? ''} onChange={(event) => patchSection(i, { text: event.target.value })} /></label>
                        <label>Attribution<input value={s.author ?? ''} onChange={(event) => patchSection(i, { author: event.target.value })} placeholder="Name, role" /></label>
                      </>
                    )}
                  </div>
                ))}
                <div className="admin-section-add">
                  {SECTION_TYPES.map(([type, label]) => (
                    <button key={type} type="button" className="act" onClick={() => addSection(type)}>+ {label}</button>
                  ))}
                </div>
              </div>

                <label>Page body (Markdown) — shown when the page has no sections
                  <div className="admin-page-editor">
                    <div className="admin-page-tabs">
                      <button type="button" className={!pagePreview ? 'active' : ''} onClick={() => setPagePreview(false)}>Write</button>
                      <button type="button" className={pagePreview ? 'active' : ''} onClick={() => setPagePreview(true)}>Preview</button>
                    </div>
                    {pagePreview
                      ? <div className="admin-page-preview page-content" dangerouslySetInnerHTML={{ __html: renderMarkdown(pageForm.content) }} />
                      : <textarea rows="16" value={pageForm.content} onChange={(event) => setPageForm({ ...pageForm, content: event.target.value })} />}
                  </div>
                </label>
              </fieldset>
              {pageForm.id && pageForm.is_published && <p className="muted">Storefront link: <code>/#/p/{pageForm.slug}</code></p>}
            </form>
          )}

          {!pageForm && (visiblePages.length === 0 ? <p className="admin-empty">{pageGroupFilter ? `No ${filterLabel.toLowerCase()} pages yet.` : 'No pages yet.'}</p> : (
            <table className="admin-table">
              <thead><tr><th>Title</th><th>Slug</th><th>Placement</th><th>In footer</th><th>Published</th><th></th></tr></thead>
              <tbody>
                {visiblePages.map((page) => (
                  <tr key={page.id}>
                    <td>{page.title}</td>
                    <td><code>{page.slug}</code></td>
                    <td>{Array.isArray(page.menu_placements) && page.menu_placements.length > 0
                      ? page.menu_placements.map((p) => MENU_PLACEMENT_LABELS[p]?.split(' (')[0] ?? p).join(', ')
                        + (page.menu_placements.includes('main_footer') ? ` — ${FOOTER_GROUP_LABELS[page.footer_group] ?? page.footer_group}` : '')
                      : <span className="muted">Unassigned</span>}</td>
                    <td>{page.show_in_footer ? 'Yes' : 'No'}</td>
                    <td>{page.is_published ? 'Yes' : <span className="muted">Draft</span>}</td>
                    <td className="admin-actions">
                      <button className="act" type="button" onClick={() => editPage(page)}>Edit</button>
                      <button className="act danger" type="button" onClick={() => removePage(page)}>Delete</button>
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          ))}
        </section>
        )
      })()}

      {tab === 'support' && (
        <section className="admin-panel">
          <div className="admin-filters">
            {['open', 'resolved', 'all', 'sellers'].map((value) => (
              <button key={value} type="button" className={threadStatus === value ? 'chip active' : 'chip'} onClick={() => setThreadStatus(value)}>{value}</button>
            ))}
          </div>
          {threads.length === 0 ? <p className="admin-empty">No conversations.</p> : (
            <table className="admin-table">
              <thead><tr><th>Customer</th><th>Order</th><th>Issue</th><th>Status</th><th>Rating</th><th>Last activity</th><th></th></tr></thead>
              <tbody>
                {threads.map((t) => (
                  <tr key={t.id}>
                    <td>{t.user?.email ?? '—'}</td>
                    <td>{t.order_id ? `#${t.order_id}` : '—'}</td>
                    <td>{ISSUE_LABELS[t.issue_type] ?? t.issue_type}</td>
                    <td><span className={`pill pill-${t.status === 'open' ? 'failed' : 'paid'}`}>{t.status}</span></td>
                    <td>{t.rating != null ? <span className="admin-review-stars" title={t.rating_comment || ''}>{'★'.repeat(t.rating)}<span className="dim">{'★'.repeat(5 - t.rating)}</span></span> : <span className="muted">—</span>}</td>
                    <td>{t.last_message_at ? new Date(t.last_message_at).toLocaleString() : '—'}</td>
                    <td className="admin-actions"><button className="act" type="button" onClick={() => openThread(t.id)}>Open</button></td>
                  </tr>
                ))}
              </tbody>
            </table>
          )}
        </section>
      )}

      {tab === 'footer' && (
        <section className="admin-panel">
          {!footerForm ? <Loading>Loading…</Loading> : (
            <form className="admin-form" onSubmit={saveFooter}>
              <h3>Footer</h3>
              <p className="muted">The storefront footer. &ldquo;Useful Links&rdquo; also lists your published content pages; the links below are added after them.</p>
              <div className="admin-form-grid">
                <label>Copyright line<input maxLength="160" value={footerForm.copyright} onChange={(event) => setFooterForm({ ...footerForm, copyright: event.target.value })} placeholder="© {year} NexTech" /></label>
                <label>App Store URL<input value={footerForm.app_store_url} onChange={(event) => setFooterForm({ ...footerForm, app_store_url: event.target.value })} placeholder="https://apps.apple.com/…" /></label>
                <label>Google Play URL<input value={footerForm.play_store_url} onChange={(event) => setFooterForm({ ...footerForm, play_store_url: event.target.value })} placeholder="https://play.google.com/…" /></label>
              </div>
              <p className="muted"><code>{'{year}'}</code> in the copyright line is replaced with the current year.</p>

              <fieldset className="admin-fieldset">
                <legend>Colours</legend>
                <div className="admin-form-grid admin-color-grid">
                  {[
                    ['bg_color', 'Background', 'The footer’s own background — set a dark colour for a dark footer'],
                    ['text_color', 'Text', 'Headings, links and copy throughout the footer'],
                  ].map(([key, label, hint]) => (
                    <label key={key} className="admin-color">{label}
                      <span>
                        <input type="color" value={footerForm[key]} aria-label={`${label} colour`} onChange={(event) => setFooterForm({ ...footerForm, [key]: event.target.value })} />
                        <input value={footerForm[key]} maxLength="7" spellCheck="false" aria-label={`${label} hex`} onChange={(event) => setFooterForm({ ...footerForm, [key]: event.target.value })} />
                      </span>
                      <em className="admin-color-hint">{hint}</em>
                    </label>
                  ))}
                </div>
              </fieldset>

              <fieldset className="admin-fieldset">
                <legend>Social links</legend>
                <div className="admin-form-grid">
                  {SOCIAL_PLATFORMS.map(([key, label]) => (
                    <label key={key}>{label}
                      <input value={footerForm.socials[key] ?? ''} placeholder="https://… (blank = hidden)"
                        onChange={(event) => setFooterForm({ ...footerForm, socials: { ...footerForm.socials, [key]: event.target.value } })} />
                    </label>
                  ))}
                </div>
              </fieldset>

              <fieldset className="admin-fieldset">
                <legend>Extra footer links</legend>
                {footerForm.links.map((link, index) => (
                  <div className="admin-variant-row" key={index}>
                    <input placeholder="Label" value={link.label} onChange={(event) => setFooterForm({ ...footerForm, links: footerForm.links.map((l, i) => i === index ? { ...l, label: event.target.value } : l) })} />
                    <input placeholder="https://…" value={link.url} onChange={(event) => setFooterForm({ ...footerForm, links: footerForm.links.map((l, i) => i === index ? { ...l, url: event.target.value } : l) })} />
                    <button type="button" className="act danger" onClick={() => setFooterForm({ ...footerForm, links: footerForm.links.filter((_, i) => i !== index) })}>Remove</button>
                  </div>
                ))}
                {footerForm.links.length < 12 && <button type="button" className="act" onClick={() => setFooterForm({ ...footerForm, links: [...footerForm.links, { label: '', url: '' }] })}>Add link</button>}
              </fieldset>

              <div className="admin-form-actions"><button className="act" type="submit">Save footer</button></div>
            </form>
          )}
        </section>
      )}

      {tab === 'formatting' && (
        <section className="admin-panel">
          <h3 className="admin-subhead">Formatting guide</h3>
          <p className="muted">Page &amp; blog <strong>body text</strong> is written in Markdown. Type the code on the left; the storefront renders what you see on the right. Anything not listed here shows as plain text.</p>
          <table className="admin-table admin-md-guide">
            <thead><tr><th>Element</th><th>What you type</th><th>What you get</th></tr></thead>
            <tbody>
              {MD_GUIDE.map(([label, code, note]) => (
                <tr key={label}>
                  <td className="admin-md-guide-name">{label}</td>
                  <td><pre>{code}</pre>{note && <span className="admin-note">{note}</span>}</td>
                  <td><div className="admin-page-preview page-content" dangerouslySetInnerHTML={{ __html: renderMarkdown(code) }} /></td>
                </tr>
              ))}
            </tbody>
          </table>
          <p className="muted">For a richer layout — hero image, media + text, FAQ, stats, steps — add <strong>Sections</strong> in the page editor instead of writing them in the body.</p>
        </section>
      )}

      {tab === 'branding' && (
        <section className="admin-panel">
          {!brandingForm ? <Loading>Loading…</Loading> : (
            <form className="admin-form" onSubmit={saveBranding}>
              <h3>Store settings</h3>
              <p className="muted">Branding for the storefront. Current values are pre-filled; changes apply after the shopper reloads.</p>
              <div className="admin-form-grid">
                <label>Store name<input required maxLength="60" value={brandingForm.store_name} onChange={(event) => setBrandingForm({ ...brandingForm, store_name: event.target.value })} /></label>
                <label>Tagline<input maxLength="120" value={brandingForm.tagline} onChange={(event) => setBrandingForm({ ...brandingForm, tagline: event.target.value })} /></label>
                <label>Theme
                  <select value={brandingForm.theme} onChange={(event) => setBrandingForm({ ...brandingForm, theme: event.target.value })}>
                    <option value="light">Light</option>
                    <option value="dark">Dark</option>
                  </select>
                </label>
                <label>Layout width
                  <select value={brandingForm.layout_width} onChange={(event) => setBrandingForm({ ...brandingForm, layout_width: event.target.value })}>
                    <option value="boxed">Boxed &mdash; 1280px, centred (Blinkit-style)</option>
                    <option value="full">Full width</option>
                  </select>
                </label>
              </div>

              <label>Logo
                <div className="admin-image-field">
                  {brandingForm.logo_url && <img className="admin-image-preview" src={mediaUrl(brandingForm.logo_url)} alt="" onError={(event) => { event.currentTarget.style.display = 'none' }} />}
                  <input placeholder="Logo image URL, or upload →" value={brandingForm.logo_url} onChange={(event) => setBrandingForm({ ...brandingForm, logo_url: event.target.value })} />
                  <input type="file" accept="image/*" disabled={imgBusy} onChange={(event) => uploadImage(event.target.files?.[0], (url) => setBrandingForm((form) => ({ ...form, logo_url: url })), 'branding')} />
                  {brandingForm.logo_url && <button type="button" className="act ghost" onClick={() => setBrandingForm({ ...brandingForm, logo_url: '' })}>Clear</button>}
                </div>
              </label>
              <label>Favicon
                <div className="admin-image-field">
                  {brandingForm.favicon_url && <img className="admin-image-preview" src={mediaUrl(brandingForm.favicon_url)} alt="" onError={(event) => { event.currentTarget.style.display = 'none' }} />}
                  <input placeholder="Favicon URL (.png / .ico / .svg), or upload →" value={brandingForm.favicon_url} onChange={(event) => setBrandingForm({ ...brandingForm, favicon_url: event.target.value })} />
                  <input type="file" accept="image/*" disabled={imgBusy} onChange={(event) => uploadImage(event.target.files?.[0], (url) => setBrandingForm((form) => ({ ...form, favicon_url: url })), 'branding')} />
                  {brandingForm.favicon_url && <button type="button" className="act ghost" onClick={() => setBrandingForm({ ...brandingForm, favicon_url: '' })}>Clear</button>}
                </div>
              </label>

              <fieldset className="admin-fieldset">
                <legend>Colours</legend>
                <div className="admin-form-grid admin-color-grid">
                  {[
                    ['color_brand', 'Brand / buttons', 'Primary buttons, links and highlights'],
                    ['color_accent', 'Accent', 'Badges, sale tags and small highlights'],
                    ['color_heading', 'Headings & major text', 'Page titles and section headings'],
                  ].map(([key, label, hint]) => (
                    <label key={key} className="admin-color">{label}
                      <span>
                        <input type="color" value={brandingForm[key]} aria-label={`${label} colour`} onChange={(event) => setBrandingForm({ ...brandingForm, [key]: event.target.value })} />
                        <input value={brandingForm[key]} maxLength="7" spellCheck="false" aria-label={`${label} hex`} onChange={(event) => setBrandingForm({ ...brandingForm, [key]: event.target.value })} />
                      </span>
                      <em className="admin-color-hint">{hint}</em>
                    </label>
                  ))}
                </div>
                <p className="muted">The heading colour follows the theme unless you change it here.</p>
              </fieldset>

              <div className="admin-form-actions"><button className="act" type="submit">Save store settings</button></div>
            </form>
          )}
        </section>
      )}

      {tab === 'secure' && (
        <section className="admin-panel">
          {secureGate !== 'unlocked' ? (
            <div className="admin-form payments-gate">
              <h3>Secure access</h3>
              <p className="muted">
                {secureMethod === 'otp'
                  ? 'Enter the one-time code emailed to your admin address to continue.'
                  : 'Re-enter your admin password to change payment keys or your admin email / phone.'}
              </p>
              {secureMsg && <p className="muted">{secureMsg}</p>}
              <label>{secureMethod === 'otp' ? 'Email code' : 'Admin password'}
                <input type={secureMethod === 'otp' ? 'text' : 'password'}
                  inputMode={secureMethod === 'otp' ? 'numeric' : undefined}
                  autoComplete={secureMethod === 'otp' ? 'one-time-code' : 'current-password'}
                  maxLength={secureMethod === 'otp' ? 8 : undefined}
                  value={secureSecret}
                  onChange={(event) => setSecureSecret(secureMethod === 'otp' ? event.target.value.replace(/[^0-9]/g, '') : event.target.value)}
                  onKeyDown={(event) => { if (event.key === 'Enter') unlockSecure() }} />
              </label>
              <div className="admin-form-actions">
                <button className="act" type="button" disabled={!secureSecret.trim()} onClick={unlockSecure}>Unlock</button>
                {secureMethod === 'otp' && <button className="act ghost" type="button" onClick={challengeSecure}>Resend code</button>}
              </div>
            </div>
          ) : (
            <>
              <p className="muted">Unlocked for ~15 minutes — re-locks when you leave this section.</p>

              <form className="admin-form" onSubmit={saveAccount}>
                <h3>Admin account</h3>
                <div className="admin-form-grid">
                  <label>Name<input value={accountForm.name} onChange={(event) => setAccountForm({ ...accountForm, name: event.target.value })} /></label>
                  <label>Email<input type="email" value={accountForm.email} onChange={(event) => setAccountForm({ ...accountForm, email: event.target.value })} /></label>
                  <label>Phone<input value={accountForm.phone} onChange={(event) => setAccountForm({ ...accountForm, phone: event.target.value })} placeholder="+1…" /></label>
                </div>
                <p className="muted">Changing the email also changes the address a future one-time code is sent to.</p>
                <div className="admin-form-actions"><button className="act" type="submit">Save account</button></div>
              </form>

              {paymentsForm && settings && (
                <form className="admin-form" onSubmit={savePayments}>
                  <h3>Payments — Stripe</h3>
                  <p className="muted">
                    These override the server&rsquo;s <code>STRIPE_*</code> environment values and take effect immediately.
                    Current mode: <strong>{settings.payments?.stripe_mode ?? 'test'}</strong>.
                  </p>
                  <label>Publishable key
                    <input value={paymentsForm.stripe_key} placeholder="pk_test_… / pk_live_…" onChange={(event) => setPaymentsForm({ ...paymentsForm, stripe_key: event.target.value })} />
                  </label>
                  <label>Secret key
                    <input type="password" autoComplete="off" value={paymentsForm.stripe_secret}
                      placeholder={settings.payments?.stripe_secret_set ? `current: ${settings.payments.stripe_secret_hint} — leave blank to keep` : 'sk_test_… / sk_live_…'}
                      onChange={(event) => setPaymentsForm({ ...paymentsForm, stripe_secret: event.target.value })} />
                  </label>
                  <label>Webhook signing secret
                    <input type="password" autoComplete="off" value={paymentsForm.stripe_webhook_secret}
                      placeholder={settings.payments?.stripe_webhook_secret_set ? `current: ${settings.payments.stripe_webhook_secret_hint} — leave blank to keep` : 'whsec_…'}
                      onChange={(event) => setPaymentsForm({ ...paymentsForm, stripe_webhook_secret: event.target.value })} />
                  </label>
                  <p className="muted">Secrets are stored in the database and shown afterwards only as a hint. Use live keys only on an HTTPS store.</p>
                  <div className="admin-form-actions"><button className="act" type="submit">Save payment settings</button></div>
                </form>
              )}

              {courierForm && settings && (
                <form className="admin-form" onSubmit={saveCourier}>
                  <h3>Courier — real provider</h3>
                  <p className="muted">
                    Deliveries fall back to the built-in mock courier whenever the real provider isn&rsquo;t configured or a call fails, so switching this on is always safe.
                    The real provider is a generic REST template — adjust its endpoint/field names once you have a specific carrier&rsquo;s API docs.
                  </p>
                  <label>Provider
                    <select value={courierForm.courier_provider} onChange={(event) => setCourierForm({ ...courierForm, courier_provider: event.target.value })}>
                      <option value="mock">Mock (default)</option>
                      <option value="real">Real</option>
                    </select>
                  </label>
                  <label>Base URL
                    <input value={courierForm.courier_base_url} placeholder="https://api.example-courier.com"
                      onChange={(event) => setCourierForm({ ...courierForm, courier_base_url: event.target.value })} />
                  </label>
                  <label>Account code
                    <input value={courierForm.courier_account_code} onChange={(event) => setCourierForm({ ...courierForm, courier_account_code: event.target.value })} />
                  </label>
                  <label>API key
                    <input type="password" autoComplete="off" value={courierForm.courier_api_key}
                      placeholder={settings.courier?.api_key_set ? `current: ${settings.courier.api_key_hint} — leave blank to keep` : 'API key'}
                      onChange={(event) => setCourierForm({ ...courierForm, courier_api_key: event.target.value })} />
                  </label>
                  <label>API secret
                    <input type="password" autoComplete="off" value={courierForm.courier_api_secret}
                      placeholder={settings.courier?.api_secret_set ? `current: ${settings.courier.api_secret_hint} — leave blank to keep` : 'API secret'}
                      onChange={(event) => setCourierForm({ ...courierForm, courier_api_secret: event.target.value })} />
                  </label>
                  <p className="muted">Secrets are stored in the database and shown afterwards only as a hint.</p>
                  <div className="admin-form-actions"><button className="act" type="submit">Save courier settings</button></div>
                </form>
              )}
            </>
          )}
        </section>
      )}

      {tab === 'settings' && (
        <section className="admin-panel">
          {!settings || !feesForm ? <Loading>Loading settings…</Loading> : (
            <>
              <div className="admin-form">
                <h3>Payment</h3>
                <label className="admin-check">
                  <input type="checkbox" checked={!!settings.cod_enabled} onChange={(event) => saveSetting({ cod_enabled: event.target.checked })} />
                  Accept cash on delivery
                </label>
                <p className="muted">When on, customers can choose to pay with cash at checkout. Cash-on-delivery orders are confirmed immediately; mark them paid from the Orders tab once the courier collects the cash.</p>
              </div>

              <div className="admin-form">
                <h3>Seller shipping</h3>
                <label>&ldquo;NexTech collects &amp; delivers&rdquo; option for sellers
                  <select value={settings.nextech_pickup ?? 'available'} onChange={(event) => saveSetting({ nextech_pickup: event.target.value })}>
                    <option value="available">Available — sellers can choose it</option>
                    <option value="disabled">Shown but unselectable</option>
                    <option value="hidden">Hidden</option>
                  </select>
                </label>
                <label>NexTech shipping labels (&ldquo;I ship, NexTech label&rdquo;)
                  <select value={settings.nextech_label_mode ?? 'manual'} onChange={(event) => saveSetting({ nextech_label_mode: event.target.value })}>
                    <option value="manual">Built-in — label PDF generated instantly from your templates (you can replace any)</option>
                    <option value="auto">Courier API — paid carrier label bought through the courier connection</option>
                  </select>
                </label>
                <p className="muted">Built-in: the seller gets a printable address label straight away (templates below). Courier API needs a real courier account connected in Secure access.</p>
                <p className="muted">Turn it off to have sellers ship their own orders (own courier or a NexTech-bought label), taking pickups off NexTech. Sellers already using it keep it for existing products, see a notice to switch, and can&rsquo;t add new products until they set up their own shipping.</p>
              </div>

              <LabelTemplates authHeaders={authHeaders} onMessage={setMessage} />

              <div className="admin-form">
                <h3>Countries</h3>
                <p className="muted">Countries enabled here appear as options in seller registration (business type, tax-ID format, and address labels all follow whichever country a seller picks). Enabling just one keeps the platform single-country; enabling several turns on multi-country selection everywhere that depends on it.</p>
                {(settings.all_countries ?? []).map((country) => {
                  const activeCodes = (settings.active_countries ?? []).map((c) => c.code)
                  const checked = activeCodes.includes(country.code)
                  return <label className="admin-check" key={country.code}>
                    <input type="checkbox" checked={checked} onChange={(event) => {
                      const next = event.target.checked ? [...activeCodes, country.code] : activeCodes.filter((code) => code !== country.code)
                      saveSetting({ active_countries: next })
                    }} />
                    {country.name} ({country.code})
                  </label>
                })}
                <label style={{ marginTop: 10 }}>Default country (shoppers and this console start here)
                  <select value={settings.home_market ?? 'US'} onChange={(event) => saveSetting({ home_market: event.target.value })}>
                    {marketOptions.map((m) => <option key={m.code} value={m.code}>{m.name} ({m.currency.toUpperCase()})</option>)}
                  </select>
                </label>
                <p className="muted">Every country can have its own NexTech stores, riders and products — pick the country in the top bar before adding them. Running only in India? Make India the default and untick the United States.</p>
              </div>

              <p className="muted admin-currency-note">Showing charges &amp; payouts for <b>{marketOptions.find((m) => m.code === activeMarket)?.name} ({activeCurrency.toUpperCase()} {currencySymbol(activeCurrency)})</b> — switch currency in the top bar.</p>
              {chargesMarket !== 'home' && (settings.markets ?? []).some((m) => m.code === chargesMarket)
                ? <MarketSettings key={chargesMarket} settings={settings} only={chargesMarket} save={saveSetting} onSaved={setMessage} />
                : <>
              <form className="admin-form" onSubmit={saveFees}>
                <h3>Marketplace commission &amp; seller payouts ({(settings.home_currency ?? 'usd').toUpperCase()} {currencySymbol(settings.home_currency)})</h3>
                <p className="muted">The platform's cut of every order line sold through a seller's shop, credited to the seller's ledger balance net of this commission. Doesn&rsquo;t apply to NexTech&rsquo;s own catalog.</p>
                <div className="admin-form-grid">
                  <label>Commission rate (%)<input type="number" min="0" step="0.01" value={feesForm.commission_rate_pct} onChange={(event) => setFeesForm({ ...feesForm, commission_rate_pct: event.target.value })} /></label>
                  <label>Minimum payout ($)<input type="number" min="0" step="0.01" value={feesForm.min_payout} onChange={(event) => setFeesForm({ ...feesForm, min_payout: event.target.value })} /></label>
                  <label>Maximum per payout ($)<input type="number" min="0" step="0.01" value={feesForm.max_payout} onChange={(event) => setFeesForm({ ...feesForm, max_payout: event.target.value })} /></label>
                  <label>Daily payout cap, all sellers ($)<input type="number" min="0" step="0.01" placeholder="0 = no cap" value={feesForm.daily_payout_cap} onChange={(event) => setFeesForm({ ...feesForm, daily_payout_cap: event.target.value })} /></label>
                  <label>Default return window (days)<input type="number" min="0" max={feesForm.max_return_days || 365} value={feesForm.return_window_days} onChange={(event) => setFeesForm({ ...feesForm, return_window_days: event.target.value })} /></label>
                  <label>Maximum return window (days)<input type="number" min="0" max="365" value={feesForm.max_return_days} onChange={(event) => setFeesForm({ ...feesForm, max_return_days: event.target.value })} /></label>
                  <label>Return pickup fee charged to seller ($)<input type="number" min="0" step="0.01" value={feesForm.return_pickup_fee} onChange={(event) => setFeesForm({ ...feesForm, return_pickup_fee: event.target.value })} /></label>
                  <label>NexTech label postage charged to seller ($)<input type="number" min="0" step="0.01" value={feesForm.label_postage} onChange={(event) => setFeesForm({ ...feesForm, label_postage: event.target.value })} /></label>
                </div>
                <p className="muted">A seller's balance must reach the minimum before a payout can be recorded — batches small amounts into one transfer instead of paying out per order (the norm across marketplaces). The maximum caps a single transfer (banks limit these too) — a bigger balance is paid over several. The daily cap limits the total paid to all sellers in one day, to stay inside your own account's transfer limit; 0 = no cap. Label postage is deducted per NexTech-bought label while the built-in test courier is used — a connected real courier charges its own rate.</p>

                <h3>Rider pay</h3>
                <div className="admin-form-grid">
                  <label>Base pay per delivery ($)<input type="number" min="0" step="0.01" value={feesForm.rider_base_pay} onChange={(event) => setFeesForm({ ...feesForm, rider_base_pay: event.target.value })} /></label>
                  <label>Per mile ($)<input type="number" min="0" step="0.01" value={feesForm.rider_per_mile} onChange={(event) => setFeesForm({ ...feesForm, rider_per_mile: event.target.value })} /></label>
                  <label>Minimum rider payout ($)<input type="number" min="0" step="0.01" value={feesForm.rider_min_payout} onChange={(event) => setFeesForm({ ...feesForm, rider_min_payout: event.target.value })} /></label>
                  <label>Maximum per rider payout ($)<input type="number" min="0" step="0.01" value={feesForm.rider_max_payout} onChange={(event) => setFeesForm({ ...feesForm, rider_max_payout: event.target.value })} /></label>
                </div>
                <p className="muted">Each completed delivery credits the rider the base pay plus the per-mile rate for the straight-line distance from the store to the customer. Riders can request a payout once they&rsquo;re owed the minimum — COD cash they still hold is deducted first.</p>
                <div className="admin-form-actions"><button className="act" type="submit">Save charges</button></div>
              </form>

              <div className="admin-form">
                <h3>Delivery</h3>
                <label className="admin-check">
                  <input type="checkbox" checked={settings.rider_auto_assign !== false} onChange={(event) => saveSetting({ rider_auto_assign: event.target.checked })} />
                  Auto-assign riders to orders
                </label>
                <p className="muted">When an order becomes ready for delivery, the nearest on-shift rider linked to its store is assigned automatically (preferring riders with fewer active jobs). If none is eligible the order waits in the pickup pool. Manage riders and their stores under <strong>Riders</strong>.</p>
              </div>

              <form className="admin-form" onSubmit={saveFees}>
                <h3>Delivery &amp; checkout charges ({(settings.home_currency ?? 'usd').toUpperCase()} {currencySymbol(settings.home_currency)})</h3>

                <fieldset className="admin-fieldset">
                  <legend>Delivery fee</legend>
                  <label className="admin-radio-row">Charge model
                    <span>
                      <label><input type="radio" name="delivery_mode" checked={feesForm.delivery_mode === 'fixed'} onChange={() => setFeesForm({ ...feesForm, delivery_mode: 'fixed' })} /> Fixed</label>
                      <label><input type="radio" name="delivery_mode" checked={feesForm.delivery_mode === 'distance'} onChange={() => setFeesForm({ ...feesForm, delivery_mode: 'distance' })} /> By distance</label>
                    </span>
                  </label>
                  {feesForm.delivery_mode === 'fixed' ? (
                    <div className="admin-form-grid">
                      <label>Delivery fee ($)<input type="number" min="0" step="0.01" value={feesForm.delivery_fee} onChange={(event) => setFeesForm({ ...feesForm, delivery_fee: event.target.value })} /></label>
                    </div>
                  ) : (
                    <>
                      <div className="admin-pair">
                        <label>Fee near store ($)<input type="number" min="0" step="0.01" value={feesForm.delivery_near_fee} onChange={(event) => setFeesForm({ ...feesForm, delivery_near_fee: event.target.value })} /></label>
                        <label>Fee at edge of radius ($)<input type="number" min="0" step="0.01" value={feesForm.delivery_far_fee} onChange={(event) => setFeesForm({ ...feesForm, delivery_far_fee: event.target.value })} /></label>
                      </div>
                      <p className="muted">Linear from 0 km (near fee) to each store&rsquo;s delivery radius (far fee).{stores[0]?.delivery_radius_km ? ` e.g. ${money(toCents(feesForm.delivery_near_fee))} at the store, ${money(toCents(feesForm.delivery_far_fee))} at ${stores[0].delivery_radius_km} km.` : ''}</p>
                    </>
                  )}
                  <div className="admin-form-grid">
                    <label>Free delivery above ($)<input type="number" min="0" step="0.01" value={feesForm.free_delivery_threshold} onChange={(event) => setFeesForm({ ...feesForm, free_delivery_threshold: event.target.value })} /></label>
                  </div>
                </fieldset>

                <fieldset className="admin-fieldset">
                  <legend>Other charges</legend>
                  <div className="admin-form-grid">
                    <label>Handling fee ($)<input type="number" min="0" step="0.01" value={feesForm.handling_fee} onChange={(event) => setFeesForm({ ...feesForm, handling_fee: event.target.value })} /></label>
                    <label>Small-cart fee ($)<input type="number" min="0" step="0.01" value={feesForm.small_cart_fee} onChange={(event) => setFeesForm({ ...feesForm, small_cart_fee: event.target.value })} /></label>
                    <label>…applied below ($)<input type="number" min="0" step="0.01" value={feesForm.small_cart_min} onChange={(event) => setFeesForm({ ...feesForm, small_cart_min: event.target.value })} /></label>
                    <label>Tax rate (%)<input type="number" min="0" step="0.01" value={feesForm.tax_rate_pct} onChange={(event) => setFeesForm({ ...feesForm, tax_rate_pct: event.target.value })} /></label>
                  </div>
                </fieldset>

                <div className="admin-form-actions"><button className="act" type="submit">Save charges</button></div>
              </form>
                </>}
            </>
          )}
        </section>
      )}
        </main>
      </div>

      {thread && (
        <div className="admin-drawer" role="presentation" onClick={() => setThread(null)}>
          <aside onClick={(event) => event.stopPropagation()}>
            <button className="admin-close" type="button" onClick={() => setThread(null)}>Close</button>
            <h3>{ISSUE_LABELS[thread.issue_type] ?? thread.issue_type}{thread.order_id ? ` · Order #${thread.order_id}` : ''}</h3>
            <p className="muted">{thread.user?.email} · {thread.status}
              {thread.status === 'open'
                ? <button className="act" type="button" style={{ marginLeft: 8 }} onClick={() => setThreadResolved('resolved')}>Mark resolved</button>
                : <button className="act ghost" type="button" style={{ marginLeft: 8 }} onClick={() => setThreadResolved('open')}>Re-open</button>}
            </p>
            {thread.rating != null && (
              <p className="admin-chat-rating">
                <span className="admin-review-stars">{'★'.repeat(thread.rating)}<span className="dim">{'★'.repeat(5 - thread.rating)}</span></span>
                <span className="muted"> customer rated this chat{thread.rated_at ? ` · ${new Date(thread.rated_at).toLocaleDateString()}` : ''}</span>
                {thread.rating_comment && <span className="admin-chat-rating-c">“{thread.rating_comment}”</span>}
              </p>
            )}
            <div className="chat-log" ref={chatLogRef}>{(thread.messages ?? []).map((m) => (
              <div key={m.id} className={`chat-msg ${m.internal ? 'internal' : m.from_seller ? 'seller' : m.is_staff && m.user_id ? 'staff' : m.user_id ? 'customer' : 'system'}`}>
                {(m.internal || m.body) && <span>{m.internal && '🔒 '}{m.body}</span>}
                <ChatPhotos urls={m.attachments} />
                <em>{m.internal ? 'Internal note — not visible to customer · ' : ''}{m.from_seller ? `${thread.seller_shop?.name ?? 'Seller'} (seller) · ` : ''}{new Date(m.created_at).toLocaleString()}</em>
              </div>
            ))}</div>
            {thread.order_id && !String(thread.issue_type).startsWith('seller_') && (
              thread.seller_shop
                ? <p className="muted">Seller in this chat: <b>{thread.seller_shop.name}</b></p>
                : (thread.seller_options ?? []).length > 0 && (
                  <p className="muted">Needs the seller?{' '}
                    {thread.seller_options.map((shop) => <button key={shop.id} className="act ghost" type="button" style={{ marginLeft: 6 }} onClick={() => bringInSeller(shop.id)}>Bring in {shop.name}</button>)}
                  </p>
                )
            )}
            <ChatPhotoPicker photos={threadPhotos} onChange={setThreadPhotos} token={token} onError={setMessage} />
            <div className="chat-send">
              <input placeholder="Reply to the customer" value={threadReply} onChange={(event) => setThreadReply(event.target.value)} onKeyDown={(event) => { if (event.key === 'Enter') replyThread() }} />
              <button type="button" disabled={!threadReply.trim() && !threadPhotos.length} onClick={replyThread}>Send</button>
            </div>

            {thread.order && thread.order.payment_status === 'pending' && (thread.user?.gift_cards ?? []).length > 0 && (
              <div className="admin-form" style={{ marginTop: 16 }}>
                <h4>Apply an existing gift card</h4>
                <p className="muted">Order #{thread.order.id} is still open — total {money(thread.order.total_cents, thread.order.currency)}.</p>
                {thread.user.gift_cards.map((card) => (
                  <button key={card.id} type="button" className="act" disabled={busyId === thread.id} onClick={() => applyGiftCardToOrder(card, thread.order, thread.id)}>
                    Apply {card.code} — {money(card.balance_cents)} balance
                  </button>
                ))}
              </div>
            )}

            {!thread.order && (thread.user?.orders ?? []).length > 0 && (
              <div className="admin-form" style={{ marginTop: 16 }}>
                <h4>Attach an order</h4>
                <p className="muted">This chat wasn&rsquo;t opened against a specific order — pick one of {thread.user?.email ?? 'this customer'}&rsquo;s orders to unlock refunds &amp; gift cards.</p>
                <UpwardPicker
                  placeholder="Choose an order…"
                  options={thread.user.orders.map((o) => ({ value: o.id, label: `#${o.id} — ${money(o.total_cents, o.currency)} — ${STATUS_LABELS[o.status] ?? o.status} — ${new Date(o.created_at).toLocaleDateString()}` }))}
                  onPick={(id) => linkThreadOrder(id)}
                />
              </div>
            )}

            {thread.order && renderRefundPanel(thread.order, thread.id)}
          </aside>
        </div>
      )}

      {customerDetail && (
        <CustomerCrm
          detail={customerDetail}
          authHeaders={authHeaders}
          money={money}
          statusLabels={STATUS_LABELS}
          onClose={() => setCustomerDetail(null)}
          onToggleRider={toggleRider}
          onReload={() => openCustomer(customerDetail.id)}
          onMessage={setMessage}
        />
      )}

      {sellerDetail && (
        <div className="admin-drawer" role="presentation" onClick={() => setSellerDetail(null)}>
          <aside onClick={(event) => event.stopPropagation()}>
            <button className="admin-close" type="button" onClick={() => setSellerDetail(null)}>Close</button>
            {sellerDetail.loading ? <Loading>Loading…</Loading> : (
              <>
                <h3>{sellerDetail.shop?.name ?? sellerDetail.company_name}</h3>
                <p className="muted"><span className={`pill pill-${sellerDetail.status}`}>{SELLER_STATUS_LABELS[sellerDetail.status] ?? sellerDetail.status}</span> · submitted {sellerDetail.submitted_at ? new Date(sellerDetail.submitted_at).toLocaleString() : '—'}</p>
                {sellerDetail.rejection_reason && <p className="admin-cash-holding overdue">{sellerDetail.status === 'needs_changes' ? 'Changes requested: ' : 'Reason: '}{sellerDetail.rejection_reason}</p>}
                {sellerDetail.last_message && (
                  <p className="muted">Last message ({sellerDetail.last_message.is_staff ? 'you' : 'seller'}, {new Date(sellerDetail.last_message.created_at).toLocaleString()}): &ldquo;{sellerDetail.last_message.body}&rdquo;</p>
                )}

                <h4>Business</h4>
                <p className="muted">{sellerDetail.company_name} · {sellerDetail.business_type} · {sellerDetail.country}</p>
                <p className="muted">Tax ID: {sellerDetail.tax_id}</p>
                <p className="muted">{[sellerDetail.registered_line1, sellerDetail.registered_line2, sellerDetail.registered_city, sellerDetail.registered_state, sellerDetail.registered_postal_code, sellerDetail.registered_country].filter(Boolean).join(', ')}</p>

                <h4>Pickup address</h4>
                <p className="muted">{sellerDetail.pickup_phone}</p>
                <p className="muted">{sellerDetail.pickup_same_as_registered
                  ? 'Same as registered address (above)'
                  : [sellerDetail.pickup_line1, sellerDetail.pickup_line2, sellerDetail.pickup_city, sellerDetail.pickup_state, sellerDetail.pickup_postal_code, sellerDetail.pickup_country].filter(Boolean).join(', ')}</p>

                <h4>Seller / contact</h4>
                <p className="muted">{sellerDetail.contact_name} · {sellerDetail.user?.email}</p>
                <p className="muted">{SELLER_ID_TYPE_LABELS[sellerDetail.id_type] ?? sellerDetail.id_type}: {sellerDetail.id_number} · DOB {sellerDetail.date_of_birth}</p>

                <h4>Documents</h4>
                <div className="admin-form-actions">
                  <button className="act ghost" type="button" onClick={() => viewKycDocument(sellerDetail.id_document_path)}>View ID document</button>
                  <button className="act ghost" type="button" onClick={() => viewKycDocument(sellerDetail.business_document_path)}>View business document</button>
                </div>

                {sellerDetail.status === 'approved' && (() => {
                  const d = sellerDetail
                  const STATUS_TEXT = { pending: 'Waiting for review', approved: 'Approved', rejected: 'Sent back', processing: 'Waiting for verification', linked: 'Linked', failed: 'Verification failed' }
                  const reviewButtons = (task, status, waiting) => status && (
                    <div className="admin-form-actions">
                      {status !== (task === 'bank' ? 'linked' : 'approved') && <button className="act" type="button" disabled={busyId === d.id} onClick={() => reviewOnboarding(d, task, 'approve')}>{task === 'bank' ? 'Verify & link' : 'Approve'}</button>}
                      {status !== (task === 'bank' ? 'failed' : 'rejected') && <button className="act ghost" type="button" disabled={busyId === d.id} onClick={() => reviewOnboarding(d, task, 'reject')}>{waiting ? 'Send back' : 'Revoke'}</button>}
                    </div>
                  )
                  const people = d.compliance?.people ?? []
                  const roleNames = { ubo: 'beneficial owner', director: 'director', executive: 'executive' }
                  return (
                    <>
                      <h4>Onboarding tasks</h4>
                      <p className="muted"><b>1. Tax information</b> — {d.tax_status ? STATUS_TEXT[d.tax_status] : d.tax_info?.tax_number ? 'Step 2 not done' : 'Not started'}{d.tax_submitted_at ? ` · submitted ${new Date(d.tax_submitted_at).toLocaleDateString()}` : ''}{d.tax_note ? ` · “${d.tax_note}”` : ''}</p>
                      {d.tax_info?.tax_number && <p className="muted">Tax number {d.tax_info.tax_number} (registered: {d.tax_id}){d.tax_info.tax_code ? ` · default item tax code: ${d.tax_codes?.[d.tax_info.tax_code] ?? d.tax_info.tax_code}` : ''}{d.tax_info.certificate_path && <> · <button className="link" type="button" onClick={() => viewKycDocument(d.tax_info.certificate_path)}>View certificate</button></>}</p>}
                      {reviewButtons('tax', d.tax_status, d.tax_status === 'pending')}

                      <p className="muted"><b>2. Compliance information</b> — {d.compliance_status ? STATUS_TEXT[d.compliance_status] : 'Not started'}{d.compliance_submitted_at ? ` · submitted ${new Date(d.compliance_submitted_at).toLocaleDateString()}` : ''}{d.compliance_note ? ` · “${d.compliance_note}”` : ''}</p>
                      {people.map((p) => (
                        <p className="muted" key={p.id}>{p.legal_name}{p.is_primary ? ' (primary contact)' : ''} — {p.roles.map((r) => roleNames[r]).join(', ')}{p.ownership_pct != null ? ` · ${p.ownership_pct}% owned` : ''} · born {p.date_of_birth} in {p.place_of_birth} · citizen of {p.citizenship} · {p.id_type} {p.id_number} ({p.id_country}, expires {p.id_expiry}) · {[p.address?.line1, p.address?.line2, p.address?.city, p.address?.state, p.address?.postal_code, p.address?.country].filter(Boolean).join(', ')}</p>
                      ))}
                      {(d.compliance?.documents ?? []).length > 0 && (
                        <div className="admin-form-actions">
                          {d.compliance.documents.map((doc) => <button key={doc.path} className="act ghost" type="button" onClick={() => viewKycDocument(doc.path)}>{d.corporate_document_types?.[doc.type] ?? doc.type}</button>)}
                        </div>
                      )}
                      {reviewButtons('compliance', d.compliance_status, d.compliance_status === 'pending')}

                      <p className="muted"><b>3. Bank account</b> — {d.bank_status ? STATUS_TEXT[d.bank_status] : 'Not started'}{d.bank_submitted_at ? ` · submitted ${new Date(d.bank_submitted_at).toLocaleDateString()}` : ''}{d.bank_note ? ` · “${d.bank_note}”` : ''}</p>
                      {d.payout_details?.document_path && <p className="muted">Check the bank document matches: holder, {d.payout_details.bank_code_label ?? 'routing number'} and account number, issued {d.payout_details.document_issued_on} (must be within 180 days). <button className="link" type="button" onClick={() => viewKycDocument(d.payout_details.document_path)}>View bank document</button></p>}
                      {reviewButtons('bank', d.bank_status, d.bank_status === 'processing')}
                    </>
                  )
                })()}

                <h4>Shop</h4>
                <p className="muted">{sellerDetail.shop?.name} {sellerDetail.shop?.is_active ? '(live)' : '(hidden)'}</p>

                {sellerDetail.shop && (
                  <>
                    <h4>Shipping</h4>
                    <p className="muted">{{ nextech: 'NexTech collects & delivers this seller’s orders', self: 'Ships orders with their own courier (tracking entered in Seller Center)', label: 'Ships orders on NexTech-bought labels (postage deducted from earnings)' }[sellerDetail.shop?.fulfillment_mode ?? 'nextech']}</p>
                    <h4>Payouts</h4>
                    <p className="muted">Available: <strong>{money(Math.max(0, sellerDetail.available_cents ?? 0), sellerDetail.currency)}</strong> · Held for returns: <strong>{money(sellerDetail.pending_cents ?? 0, sellerDetail.currency)}</strong> · Total balance: {money(sellerDetail.balance_cents ?? 0, sellerDetail.currency)}</p>
                    {(sellerDetail.pending_orders ?? []).length > 0 && (
                      <p className="muted">Held: {sellerDetail.pending_orders.map((p) => `#${p.order_id} ${money(p.amount_cents, sellerDetail.currency)} ${p.releases_at ? `→ ${new Date(p.releases_at).toLocaleDateString()}` : '(not delivered)'}`).join(' · ')}</p>
                    )}
                    <p className="muted"> · Minimum payout: {money(sellerDetail.min_payout_cents ?? 0, sellerDetail.currency)}{sellerDetail.max_payout_cents > 0 ? ` · Max per payout: ${money(sellerDetail.max_payout_cents, sellerDetail.currency)}` : ''}{sellerDetail.daily_payout_remaining_cents != null ? ` · ${money(sellerDetail.daily_payout_remaining_cents, sellerDetail.currency)} left today (all sellers)` : ''}</p>
                    {sellerDetail.pending_payout_request && (
                      <p className="admin-payout-request">💸 Seller requested <strong>{money(sellerDetail.pending_payout_request.amount_cents, sellerDetail.currency)}</strong> on {new Date(sellerDetail.pending_payout_request.created_at).toLocaleDateString()}. Send it, then record it below.</p>
                    )}
                    {sellerDetail.payout_method ? (
                      <p className="muted">
                        {sellerDetail.payout_method === 'bank' ? <>Bank transfer — {sellerDetail.payout_details?.holder_name}, {sellerDetail.payout_details?.bank_name}, acct {sellerDetail.payout_details?.account_number} · routing {sellerDetail.payout_details?.routing_number}</>
                          : <>PayPal — {sellerDetail.payout_details?.email}</>}
                      </p>
                    ) : <p className="muted">No payout method on file yet.</p>}
                    {(sellerDetail.ledger_entries ?? []).length > 0 && (
                      <div className="admin-gift-issued">
                        {sellerDetail.ledger_entries.map((entry) => (
                          <p key={entry.id}>{LEDGER_TYPE_LABELS[entry.type] ?? entry.type} <b>{entry.amount_cents >= 0 ? '+' : '−'}{money(Math.abs(entry.amount_cents), sellerDetail.currency)}</b>{entry.order_id ? ` — order #${entry.order_id}` : ''}{entry.note ? ` — ${entry.note}` : ''} · {new Date(entry.created_at).toLocaleDateString()}</p>
                        ))}
                      </div>
                    )}
                    {sellerDetail.status === 'approved' && (
                      <div className="admin-form-actions">
                        {/* Only offered once the cleared (past-return-window) balance reaches the minimum. */}
                        {(sellerDetail.available_cents ?? 0) >= (sellerDetail.min_payout_cents ?? 0) && (sellerDetail.available_cents ?? 0) > 0 && (
                          <button className="act" type="button" disabled={busyId === sellerDetail.id} onClick={() => recordSellerPayout(sellerDetail)}>Record payout</button>
                        )}
                        {sellerDetail.pending_payout_request && <button className="act ghost" type="button" disabled={busyId === sellerDetail.id} onClick={() => rejectPayoutRequest(sellerDetail)}>Decline request</button>}
                        {(sellerDetail.available_cents ?? 0) < (sellerDetail.min_payout_cents ?? 0) && <p className="muted">Available balance is below the {money(sellerDetail.min_payout_cents ?? 0, sellerDetail.currency)} minimum — earnings still inside their return window can&rsquo;t be paid out yet.</p>}
                      </div>
                    )}
                  </>
                )}

                <h4>Actions</h4>
                <div className="admin-form-actions">
                  <button className="act ghost" type="button" disabled={busyId === sellerDetail.id} onClick={() => messageSeller(sellerDetail)}>Message seller</button>
                  {(sellerDetail.status === 'pending' || sellerDetail.status === 'rejected') && (
                    <button className="act ghost" type="button" disabled={busyId === sellerDetail.id} onClick={() => requestSellerChanges(sellerDetail)}>Request changes</button>
                  )}
                  {sellerDetail.status === 'pending' && <>
                    <button className="act" type="button" disabled={busyId === sellerDetail.id} onClick={() => sellerAction(sellerDetail, 'approve')}>Approve</button>
                    <button className="act danger" type="button" disabled={busyId === sellerDetail.id} onClick={() => rejectSeller(sellerDetail)}>Reject</button>
                  </>}
                  {sellerDetail.status === 'approved' && (
                    <button className="act danger" type="button" disabled={busyId === sellerDetail.id} onClick={() => suspendSeller(sellerDetail)}>Suspend</button>
                  )}
                  {sellerDetail.status === 'suspended' && (
                    <button className="act" type="button" disabled={busyId === sellerDetail.id} onClick={() => sellerAction(sellerDetail, 'reinstate')}>Reinstate</button>
                  )}
                  {sellerDetail.status === 'needs_changes' && <span className="muted">Waiting on the seller to edit and resubmit.</span>}
                  {sellerDetail.status === 'rejected' && <span className="muted">No further action.</span>}
                </div>
                {sellerDetail.reviewer && <p className="muted">Reviewed by {sellerDetail.reviewer.name}{sellerDetail.reviewed_at ? ` on ${new Date(sellerDetail.reviewed_at).toLocaleDateString()}` : ''}</p>}
              </>
            )}
          </aside>
        </div>
      )}

      {orderDetail && (() => {
        const o = orders.find((x) => x.id === orderDetail.id) ?? orderDetail
        const addr = o.delivery_address ?? {}
        const addrLine = [addr.name, addr.line1, addr.line2, [addr.city, addr.state, addr.postal_code].filter(Boolean).join(', ')].filter(Boolean).join(' · ')
        return (
          <div className="admin-drawer" role="presentation" onClick={() => setOrderDetail(null)}>
            <aside onClick={(event) => event.stopPropagation()}>
              <button className="admin-close" type="button" onClick={() => setOrderDetail(null)}>Close</button>
              <h3>Order #{o.id}</h3>
              <p className="muted">{new Date(o.created_at).toLocaleString()} · <span className={`pill pill-${o.payment_status}`}>{o.payment_status}</span> · {STATUS_LABELS[o.status] ?? o.status}</p>
              <p className="muted">{o.user?.display_name ? `${o.user.display_name} · ` : ''}{o.user?.email ?? '—'}{(addr.phone || o.user?.phone) ? ` · ☎ ${addr.phone || o.user.phone}` : ''}</p>

              <h4>Items ({o.items?.length ?? 0})</h4>
              <table className="admin-table admin-order-items">
                <thead><tr><th>Item</th><th>Qty</th><th>Unit</th><th>Total</th></tr></thead>
                <tbody>
                  {(o.items ?? []).map((it) => (
                    <tr key={it.id}>
                      <td>{it.product_name}{it.variant_label && <span className="admin-note">{it.variant_label}</span>}</td>
                      <td>{it.quantity}</td>
                      <td>{money(it.unit_price_cents, o.currency)}</td>
                      <td>{money(it.line_total_cents, o.currency)}</td>
                    </tr>
                  ))}
                  {(o.items ?? []).length === 0 && <tr><td colSpan="4" className="muted">No items recorded.</td></tr>}
                </tbody>
              </table>

              <dl className="admin-order-totals">
                <div><dt>Subtotal</dt><dd>{money(o.subtotal_cents, o.currency)}</dd></div>
                {o.tax_cents > 0 && <div><dt>Tax</dt><dd>{money(o.tax_cents, o.currency)}</dd></div>}
                {o.delivery_fee_cents > 0 && <div><dt>Delivery</dt><dd>{money(o.delivery_fee_cents, o.currency)}</dd></div>}
                {o.handling_fee_cents > 0 && <div><dt>Handling</dt><dd>{money(o.handling_fee_cents, o.currency)}</dd></div>}
                {o.small_cart_fee_cents > 0 && <div><dt>Small-cart fee</dt><dd>{money(o.small_cart_fee_cents, o.currency)}</dd></div>}
                {o.gift_card_discount_cents > 0 && <div><dt>Gift card</dt><dd>−{money(o.gift_card_discount_cents, o.currency)}</dd></div>}
                {o.refunded_amount_cents > 0 && <div><dt>Refunded</dt><dd>−{money(o.refunded_amount_cents, o.currency)}</dd></div>}
                <div className="admin-order-grand"><dt>Total</dt><dd>{money(o.total_cents, o.currency)}</dd></div>
              </dl>

              {(o.gift_cards ?? []).length > 0 && (
                <div className="admin-gift-issued">
                  {o.gift_cards.map((g) => (
                    <p key={g.id}>🎁 Gift card <b>{g.code}</b> — {money(g.initial_cents, o.currency)} issued{g.balance_cents !== g.initial_cents ? `, ${money(g.balance_cents, o.currency)} left` : ''}{g.reason ? ` — ${g.reason}` : ''}{g.issued_by?.name ? ` (by ${g.issued_by.name})` : ''}</p>
                  ))}
                </div>
              )}

              {(o.refunds ?? []).length > 0 && (
                <div className="admin-gift-issued">
                  {o.refunds.map((r) => (
                    <p key={r.id}>↩ Refund <b>{money(r.amount_cents, o.currency)}</b>{r.reason ? ` — ${r.reason}` : ''}{r.creator?.name ? ` (by ${r.creator.name})` : ''} · {new Date(r.created_at).toLocaleDateString()}</p>
                  ))}
                </div>
              )}

              <h4>Delivery</h4>
              <p className="muted">{addrLine || 'No address on file'}</p>
              {o.delivery_instructions && <p className="muted">Note: &ldquo;{o.delivery_instructions}&rdquo;</p>}
              <p className="muted">{o.payment_method === 'cod' ? 'Cash on delivery (C.O.D.)' : 'Card'}{(o.delivery_partner?.name || o.courier_name) ? ` · Courier: ${o.delivery_partner?.name || o.courier_name}` : ''}{o.store ? ` · Fulfilled by ${o.store.name}` : ''}</p>
              <OrderLabelRequests order={o} authHeaders={authHeaders} onChanged={(msg) => { setMessage(msg); openOrderById(o.id) }} />
              {(o.shop_shipping ?? []).length > 0 && (
                <div className="admin-seller-ship">
                  {o.shop_shipping.map((ss) => {
                    const pks = (o.packages ?? []).filter((pk) => pk.shop_id === ss.shop_id)
                    return (
                      <div key={ss.id}>
                        <p><b>Shipped by {ss.shop?.name ?? `shop #${ss.shop_id}`}</b> · {ss.mode === 'label' ? 'NexTech label' : 'own courier'} · shipping {ss.free_shipping ? 'free (seller covers)' : money(ss.fee_cents, o.currency)} · ship by {new Date(ss.ship_by).toLocaleDateString()} · arrives {new Date(ss.deliver_from).toLocaleDateString()}–{new Date(ss.deliver_by).toLocaleDateString()}</p>
                        {pks.length === 0 && <p className="muted">Not shipped yet{new Date(ss.ship_by) < new Date() && o.status !== 'cancelled' ? ' — overdue' : ''}.</p>}
                        {pks.map((pk) => (
                          <p key={pk.id} className="muted">
                            📦 {pk.carrier} {pk.tracking_url ? <a href={pk.tracking_url} target="_blank" rel="noreferrer">{pk.tracking_number}</a> : pk.tracking_number}
                            {' · '}<span className={`pill pill-${pk.status}`}>{pk.status.replace('_', ' ')}</span>
                            {' · '}{(pk.items ?? []).reduce((n, it) => n + it.quantity, 0)} item(s) · shipped {new Date(pk.shipped_at).toLocaleDateString()}{pk.edit_count ? ` · tracking edited ${pk.edit_count}×` : ''}
                            {pk.has_label_file && <>{' '}<button type="button" className="link" onClick={() => downloadPackageLabel(pk)}>Label file</button></>}
                            {' '}<button type="button" className="link" disabled={busyId === o.id} onClick={() => editPackageTracking(o, pk)}>Edit tracking</button>
                            {pk.status !== 'delivered' && <button type="button" className="link" disabled={busyId === o.id} onClick={() => patchPackage(o, pk, { status: 'delivered' })}> Mark delivered</button>}
                            {!['lost', 'delivered'].includes(pk.status) && <button type="button" className="link" disabled={busyId === o.id} onClick={() => { if (window.confirm('Mark this package as lost?')) patchPackage(o, pk, { status: 'lost' }) }}> Lost</button>}
                          </p>
                        ))}
                      </div>
                    )
                  })}
                </div>
              )}
              {o.delivery_method === 'online_courier' && (
                o.shipment ? (
                  <p className="muted">Tracking {o.shipment.tracking_number} · <span className={`pill pill-${o.shipment.status}`}>{o.shipment.status.replace('_', ' ')}</span>
                    {o.status !== 'completed' && o.status !== 'cancelled' && o.shipment.status !== 'delivered' && (
                      <button type="button" className="link" disabled={busyId === o.id} onClick={() => syncTracking(o)}> Sync tracking</button>
                    )}
                  </p>
                ) : <p className="muted">Online courier — awaiting booking.</p>
              )}
              {o.rider_accepted_at && <p className="muted">Accepted{o.delivery_partner?.name ? ` by ${o.delivery_partner.name}` : ''} · {new Date(o.rider_accepted_at).toLocaleString()}</p>}
              {!o.rider_accepted_at && o.rider_offer_expires_at && <p className="muted">Offered{o.delivery_partner?.name ? ` to ${o.delivery_partner.name}` : ''}, expires {new Date(o.rider_offer_expires_at).toLocaleString()}</p>}
              {o.rider_offer_decline_count > 0 && <p className="muted">Declined or missed by {o.rider_offer_decline_count} rider{o.rider_offer_decline_count === 1 ? '' : 's'} before this assignment.</p>}
              {o.delivered_at && <p className="muted">Delivered {new Date(o.delivered_at).toLocaleString()}{o.delivery_verified === false ? ` · without code${o.delivery_note ? ` — ${o.delivery_note}` : ''}` : o.delivery_verified ? ' · code verified' : ''}</p>}
              {o.status === 'cancelled' && o.cancelled_by && (
                <p className="muted" style={o.cancelled_by === 'rider' ? { color: '#a23b28', fontWeight: 600 } : undefined}>
                  Cancelled by {o.cancelled_by}{o.cancelled_by === 'rider' ? ' — customer refused to pay' : ''}{o.cancel_reason ? `: “${o.cancel_reason}”` : ''}
                </p>
              )}
              {(() => {
                const reviews = [
                  o.rider_review && { key: 'delivery', label: 'Delivery rating', rating: o.rider_review.rating, comment: o.rider_review.comment },
                  ...(o.support_threads ?? []).filter((t) => t.rating != null).map((t) => ({ key: `chat-${t.id}`, label: 'Chat rating', rating: t.rating, comment: t.rating_comment })),
                ].filter(Boolean)
                if (!reviews.length) return null
                const worst = Math.min(...reviews.map((r) => r.rating))
                const tone = worst <= 2 ? 'negative' : worst === 3 ? 'medium' : 'positive'
                return (
                  <div className={`admin-feedback-box admin-feedback-${tone}`}>
                    <h4>Feedback{tone === 'negative' ? ' — check this' : ''}</h4>
                    {reviews.map((r) => (
                      <p key={r.key}>{'★'.repeat(r.rating)}{'☆'.repeat(5 - r.rating)} {r.label}{r.comment ? ` — “${r.comment}”` : ''}</p>
                    ))}
                  </div>
                )
              })()}

              <h4>Move this order</h4>
              <div className="admin-actions admin-order-actions">
                {orderActions(o) ?? (
                  <span className="muted">
                    {o.status === 'completed' ? 'Delivered — nothing more to do.'
                      : o.status === 'cancelled' ? 'This order was cancelled.'
                        : "Waiting on the customer's payment before it can be packed."}
                  </span>
                )}
              </div>
              <p className="muted">Orders move one step at a time: confirmed → packing → ready for delivery → out for delivery → delivered. The rider marks the final “delivered” step from their app.</p>

              {renderRefundPanel(o, null)}
            </aside>
          </div>
        )
      })()}

      {riderDetail && (
        <div className="admin-drawer" role="presentation" onClick={() => setRiderDetail(null)}>
          <aside onClick={(event) => event.stopPropagation()}>
            <button className="admin-close" type="button" onClick={() => setRiderDetail(null)}>Close</button>
            {riderDetail.loading ? <Loading>Loading…</Loading> : (
              <>
                <h3>{riderDetail.rider?.name}{riderDetail.view === 'reviews' ? ' — reviews' : ''}</h3>
                <p className="muted">{riderDetail.rider?.email} · {riderDetail.rider?.phone || 'no phone'}</p>
                <p className="muted">{riderDetail.rider?.located?.source === 'live'
                  ? `Current location: ${riderDetail.rider.located.lat.toFixed(4)}, ${riderDetail.rider.located.lng.toFixed(4)} (live)`
                  : riderDetail.rider?.rider_base_address
                    ? `Home base: ${riderDetail.rider.rider_base_address}`
                    : 'No address on file'}</p>

                {riderDetail.view === 'reviews' ? (
                  <div className="admin-review-overview">
                    <div className="admin-review-score">
                      <strong>{riderDetail.rider?.rating_avg != null ? riderDetail.rider.rating_avg.toFixed(1) : '—'}</strong>
                      <span>
                        <span className="admin-review-stars">{'★'.repeat(Math.round(riderDetail.rider?.rating_avg ?? 0))}<span className="dim">{'★'.repeat(5 - Math.round(riderDetail.rider?.rating_avg ?? 0))}</span></span>
                        <span className="muted"> {riderDetail.rider?.rating_count ?? 0} review{riderDetail.rider?.rating_count === 1 ? '' : 's'}</span>
                      </span>
                    </div>
                    <div className="admin-review-bars">
                      {[5, 4, 3, 2, 1].map((n) => {
                        const total = riderDetail.reviews?.length ?? 0
                        const c = (riderDetail.reviews ?? []).filter((r) => r.rating === n).length
                        return (
                          <div key={n} className="admin-review-bar">
                            <span>{n}★</span>
                            <span className="admin-review-bar-track"><span style={{ width: `${total ? (c / total) * 100 : 0}%` }} /></span>
                            <span>{c}</span>
                          </div>
                        )
                      })}
                    </div>
                  </div>
                ) : (
                  <>
                    <div className="admin-rider-stats">
                      <span><strong>{riderDetail.rider?.completed_deliveries ?? 0}</strong> delivered</span>
                      <span><strong>{riderDetail.rider?.active_deliveries ?? 0}</strong> active now</span>
                      <span><strong>{riderDetail.rider?.rating_avg != null ? `★ ${riderDetail.rider.rating_avg.toFixed(1)}` : '—'}</strong> {riderDetail.rider?.rating_count ?? 0} rating{riderDetail.rider?.rating_count === 1 ? '' : 's'}</span>
                      <span><strong>{riderDetail.rider?.acceptance_rate != null ? `${Math.round(riderDetail.rider.acceptance_rate * 100)}%` : '—'}</strong> offers accepted{riderDetail.rider?.offers_count ? ` (${riderDetail.rider.offers_count})` : ''}</span>
                      <span><strong>{riderDetail.rider?.declined_count ?? 0} / {riderDetail.rider?.missed_count ?? 0}</strong> rejected / missed</span>
                    </div>

                    {riderDetail.rider?.cash_holding_cents > 0 && (
                      <p className={`admin-cash-holding${cashHoldingOverdue(riderDetail.rider.cash_holding_since) ? ' overdue' : ''}`}>
                        Holding <b>{money(riderDetail.rider.cash_holding_cents)}</b> in cash{riderDetail.rider.cash_holding_since ? ` from ${new Date(riderDetail.rider.cash_holding_since).toLocaleDateString()}` : ''} on COD deliveries.
                        <button type="button" className="act" disabled={busyId === riderDetail.rider.id} onClick={() => settleRiderCash(riderDetail.rider)}>Confirm cash returned</button>
                      </p>
                    )}

                    {riderDetail.pay && (
                      <>
                        <h4>Pay</h4>
                        <div className="admin-rider-stats">
                          <span><strong>{money(riderDetail.pay.balance_cents)}</strong> earned, unpaid</span>
                          <span><strong>{money(riderDetail.pay.cash_holding_cents)}</strong> cash held</span>
                          <span><strong>{money(riderDetail.pay.owed_cents)}</strong> owed</span>
                          <span><strong>{money(riderDetail.pay.paid_total_cents)}</strong> paid to date</span>
                        </div>
                        <p className="muted">{riderDetail.pay.payout_method === 'bank'
                          ? `Bank — ${riderDetail.pay.payout_details?.holder_name}, ${riderDetail.pay.payout_details?.bank_name}, acct ${riderDetail.pay.payout_details?.account_number} · routing ${riderDetail.pay.payout_details?.routing_number}`
                          : riderDetail.pay.payout_method === 'paypal' ? `PayPal — ${riderDetail.pay.payout_details?.email}` : 'No payout method on file yet.'}</p>
                        {riderDetail.pay.pending_payout_request && (
                          <p className="admin-payout-request">🛵 Rider requested <strong>{money(riderDetail.pay.pending_payout_request.amount_cents)}</strong> on {new Date(riderDetail.pay.pending_payout_request.created_at).toLocaleDateString()}. Send it, then record it below.</p>
                        )}
                        <div className="admin-form-actions">
                          <button className="act" type="button" disabled={busyId === riderDetail.rider.id || riderDetail.pay.owed_cents <= 0} onClick={() => recordRiderPayout(riderDetail.rider, riderDetail.pay)}>Record payout</button>
                          {riderDetail.pay.pending_payout_request && <button className="act ghost" type="button" disabled={busyId === riderDetail.rider.id} onClick={() => declineRiderPayout(riderDetail.rider)}>Decline request</button>}
                        </div>
                        {(riderDetail.pay.entries ?? []).length > 0 && (
                          <div className="admin-gift-issued">
                            {riderDetail.pay.entries.map((e) => (
                              <p key={e.id}>{e.type === 'payout_debit' ? 'Payout' : `Delivery #${e.order_id ?? '—'}`} <b>{e.amount_cents >= 0 ? '+' : '−'}{money(Math.abs(e.amount_cents))}</b>{e.distance_miles != null ? ` · ${e.distance_miles} mi` : ''}{e.note ? ` — ${e.note}` : ''} · {new Date(e.created_at).toLocaleDateString()}</p>
                            ))}
                          </div>
                        )}
                      </>
                    )}

                    <h4>Attendance</h4>
                    <p className="muted">{riderStatusChip(riderDetail.rider ?? {})}</p>
                    <div className="admin-month-nav">
                      <button type="button" className="act ghost" onClick={() => setRiderMonth((m) => new Date(m.getFullYear(), m.getMonth() - 1, 1))}>&lsaquo; Prev</button>
                      <strong>{riderMonth.toLocaleDateString([], { month: 'long', year: 'numeric' })}</strong>
                      <button type="button" className="act ghost" disabled={riderMonth.getFullYear() === new Date().getFullYear() && riderMonth.getMonth() === new Date().getMonth()} onClick={() => setRiderMonth((m) => new Date(m.getFullYear(), m.getMonth() + 1, 1))}>Next &rsaquo;</button>
                    </div>
                    {!riderReport || riderReport.loading ? <Loading>Loading…</Loading> : (
                      <>
                        <p className="muted">Completed days only — counting from {new Date(riderReport.active_from).toLocaleDateString()}{riderReport.today?.on_the_clock ? ` · on the clock now, ${fmtWorked(riderReport.today.worked_minutes)} today` : ''}</p>
                        <div className="admin-rider-stats">
                          <span><strong>{riderReport.summary.days_full}</strong> full days (&ge; {fmtWorked(riderReport.target_minutes)})</span>
                          <span><strong>{riderReport.summary.days_short}</strong> short days</span>
                          <span><strong>{riderReport.summary.days_off}</strong> days off</span>
                          <span><strong>{fmtWorked(riderReport.summary.total_worked_minutes)}</strong> total worked</span>
                          <span><strong>{fmtWorked(riderReport.summary.avg_worked_minutes)}</strong> avg / completed day</span>
                        </div>
                        <table className="admin-table">
                          <thead><tr><th>Date</th><th></th><th>In</th><th>Out</th><th>Worked</th><th>Breaks</th></tr></thead>
                          <tbody>
                            {riderReport.days.map((d) => (
                              <tr key={d.date} className={(d.status === 'off' || d.status === 'pre') ? 'admin-day-off' : ''}>
                                <td>{new Date(d.date).toLocaleDateString([], { weekday: 'short', day: 'numeric', month: 'short' })}</td>
                                <td><span style={{ color: DAY_STATUS[d.status].color, fontWeight: 600 }}>{DAY_STATUS[d.status].label}</span></td>
                                <td>{d.first_in ? new Date(d.first_in).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' }) : '—'}</td>
                                <td>{d.shifts === 0 ? '—' : d.last_out ? new Date(d.last_out).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' }) : <span className="muted">open</span>}</td>
                                <td>{d.worked_minutes ? fmtWorked(d.worked_minutes) : '—'}</td>
                                <td>{d.break_minutes ? fmtWorked(d.break_minutes) : '—'}</td>
                              </tr>
                            ))}
                            {riderReport.days.length === 0 && <tr><td colSpan={6} className="muted">No days in range.</td></tr>}
                          </tbody>
                        </table>
                      </>
                    )}
                  </>
                )}

                <h4>{riderDetail.view === 'reviews' ? 'Recent reviews' : 'Customer reviews'} ({riderDetail.reviews?.length ?? 0})</h4>
                <p className="muted">Comments are for admins only — the rider never sees them.</p>
                <ul className="admin-review-list">
                  {(riderDetail.reviews ?? []).map((rv) => (
                    <li key={rv.id}>
                      <div className="admin-review-head">
                        <span className="admin-review-stars">{'★'.repeat(rv.rating)}<span className="dim">{'★'.repeat(5 - rv.rating)}</span></span>
                        <span className="muted">Order #{rv.order_id} · {rv.source === 'chat' ? 'from chat' : 'delivery'} · {new Date(rv.at).toLocaleDateString()}</span>
                      </div>
                      {rv.comment ? <p className="admin-review-comment">{rv.comment}</p> : <p className="muted">No comment.</p>}
                    </li>
                  ))}
                  {(riderDetail.reviews ?? []).length === 0 && <li className="muted">No reviews yet.</li>}
                </ul>
              </>
            )}
          </aside>
        </div>
      )}
    </div>
  )
}
