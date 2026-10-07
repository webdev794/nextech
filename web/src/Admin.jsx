import { Fragment, useCallback, useEffect, useRef, useState } from 'react'
import { BrandLogo } from './BrandLogo'
import { setBranding as setSharedBranding, brandName } from './useBranding'
import { DecorationReview, ListingReview, TrademarkReview } from './AdminListingReview'
import { AdminChatDock, SellerLedgerTable } from './AdminSellerChat'
import { PackageProgress, TrackingTimeline } from './TrackingTimeline'
import { PersonalizationView } from './Personalization'
import { openSellerChat, openSupportChat } from './sellerChatEvents'
import { CustomerCrm } from './AdminCustomer'
import { EmailsPanel } from './AdminEmails'
import { AdminReviews } from './AdminReviews'
import { LabelRequestsPanel, LabelTemplates, OrderLabelRequests } from './AdminLabels'
import { BusinessDetails, MarketSettings } from './AdminMarkets'
import { CHANGE_ITEMS } from './sellerChangeItems'
import LightningDeal from './LightningDeal'
import { ReturnPolicyFields } from './returnPolicy'
import { AdminDigitalFiles } from './AdminDigitalFiles'
import { KeptRates } from './AdminKeptRates'
import { SellerPayouts, WithdrawalFees } from './AdminPayouts'
import { CategoryDetailsEditor } from './AdminCategoryDetails'
import { SalesTaxKey, SalesTaxSettings } from './AdminSalesTax'
import { currencySymbol, setStoreCurrency, storeMoney } from './money'
import MapPicker from './MapPicker'
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

// Pages: the storefront address a page lives at, and turning typed text into a URL slug.
const STORE_PAGE_BASE = `${window.location.origin}${import.meta.env.BASE_URL || '/'}#/p/`
const toSlug = (text, typing = false) => {
  const s = String(text ?? '').toLowerCase().normalize('NFKD').replace(/[\u0300-\u036f]/g, '').replace(/[^a-z0-9]+/g, '-').replace(/^-+/, '')
  return typing ? s.slice(0, 160) : s.replace(/-+$/, '').slice(0, 160)
}

// Why an admin cancels an order (the customer and the order record see it).
const CANCEL_REASONS = ['Item out of stock / missing', 'Item damaged before dispatch', 'Customer asked to cancel', 'Can’t deliver to this address', 'Payment problem / suspected fraud', 'Duplicate order', 'Other']

const STATUS_FILTERS = ['all', 'open', 'refund_due', 'confirmed', 'packing', 'ready_for_delivery', 'out_for_delivery', 'completed', 'cancelled']
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
const PRIMARY_TABS = ['dashboard', 'orders', 'categories', 'products', 'stores', 'sellers', 'customers', 'emails', 'reviews', 'riders', 'shipping', 'branding', 'secure']
const TOP_TABS = ['support', 'settings']
const TAB_LABELS = {
  dashboard: 'Dashboard', orders: 'Orders', products: 'Products', reviews: 'Reviews', categories: 'Categories',
  customers: 'Customers', emails: 'Emails', riders: 'Riders', sellers: 'Sellers', stores: 'Stores / hubs', shipping: 'Shipping', branding: 'Store settings', secure: 'Secure access',
  support: 'Support', settings: 'Settings',
}
const TAB_ICONS = {
  dashboard: '\u{1F4CA}', orders: '\u{1F9FE}', products: '\u{1F4E6}', reviews: '\u{2B50}', categories: '\u{1F5C2}️',
  customers: '\u{1F465}', emails: '\u{2709}\u{FE0F}', riders: '\u{1F6F5}', sellers: '\u{1F4BC}', stores: '\u{1F3EC}', shipping: '\u{1F69A}', branding: '\u{1F3A8}', secure: '\u{1F510}',
}
// Cross-border currency conversion: the market rate (fetched daily from two
// sources and cross-checked), an optional fixed rate, and the platform margin
// buyers pay on top. Sellers are always paid their own listed price.
function CurrencySettings({ fx, saveSetting, onSaved }) {
  const [margin, setMargin] = useState(() => String(fx.margin_bps / 100))
  const [manual, setManual] = useState(() => Object.fromEntries(fx.currencies.map((c) => [c.currency, c.manual_rate ?? ''])))
  const [busy, setBusy] = useState(false)
  const run = async (patch, msg) => { setBusy(true); const saved = await saveSetting(patch); setBusy(false); if (saved) onSaved(msg) }
  return (
    <form className="admin-form" onSubmit={(e) => { e.preventDefault(); run({ fx_margin_bps: Math.round(Number(margin || 0) * 100), fx_manual: Object.fromEntries(Object.entries(manual).map(([c, v]) => [c, v === '' ? null : Number(v)])) }, 'Currency settings saved.') }}>
      <h3>Currency conversion (cross-border orders)</h3>
      <p className="muted">When a buyer orders from a seller in another country, prices are converted into the buyer&rsquo;s currency at the rate below plus your margin. The seller is paid their own listed price; the margin stays with {brandName()} (it covers the card&rsquo;s currency-conversion fee). Each order keeps the rate it was placed at.</p>
      {fx.currencies.map((c) => (
        <div key={c.currency} className="admin-fx-row">
          <p><b>1 USD = {c.in_use} {c.currency.toUpperCase()}</b>{c.manual_rate ? ' (your fixed rate)' : ' (market rate)'} · buyers pay <b>{c.buyer_rate} {c.currency.toUpperCase()}</b> per USD with the margin
            <span className="admin-note admin-fx-note">Market rate: {c.market_rate ?? 'not fetched yet'}{fx.fetched_at ? ` · updated ${new Date(fx.fetched_at).toLocaleString()}` : ''}{fx.sources?.length ? ` · from ${fx.sources.join(' + ')}` : ''}</span></p>
          <label>Fixed rate (optional — leave blank to follow the market)
            <input type="number" min="0" step="0.0001" value={manual[c.currency] ?? ''} placeholder={String(c.market_rate ?? '')} onChange={(e) => setManual((m) => ({ ...m, [c.currency]: e.target.value }))} />
          </label>
        </div>
      ))}
      <label>Margin added for buyers (%)
        <input type="number" min="0" max="20" step="0.1" value={margin} onChange={(e) => setMargin(e.target.value)} />
      </label>
      <div className="admin-form-actions">
        <button className="act" type="submit" disabled={busy}>Save currency settings</button>
        <button type="button" className="act ghost" disabled={busy} onClick={() => run({ fx_refresh: true }, 'Market rate updated.')}>Update market rate now</button>
      </div>
    </form>
  )
}

// A seller-shipped package still on its way with no update for 2+ days (and
// no live courier tracking): how many days, else 0.
function staleSellerUpdate(pk) {
  if (!['shipped', 'in_transit', 'out_for_delivery'].includes(pk.status) || pk.tracking_ref) return 0
  const last = new Date(pk.progress_updated_at ?? pk.shipped_at).getTime()
  const days = Math.floor((Date.now() - last) / 86400000)
  return days >= 2 ? days : 0
}

// Who sells the items on an order and who ships them: each seller shop
// ("ships itself" or NexTech delivering), plus NexTech's own stock.
function orderSellers(order) {
  const shops = new Map()
  let nextechStock = false
  for (const item of order.items ?? []) {
    if (!item.shop_id) { nextechStock = true; continue }
    const cur = shops.get(item.shop_id) ?? { name: item.shop?.name ?? `Shop #${item.shop_id}`, ships: false }
    if (item.fulfilled_by === 'seller') cur.ships = true
    shops.set(item.shop_id, cur)
  }
  if (!shops.size) return <span className="muted">{brandName()}</span>
  return (
    <>
      {[...shops.values()].map((shop) => (
        <span key={shop.name} className="admin-order-seller"><b>{shop.name}</b><span className="admin-note">{shop.ships ? 'seller ships' : `${brandName()} delivers`}</span></span>
      ))}
      {nextechStock && <span className="admin-note">+ {brandName()} items</span>}
    </>
  )
}

const SELLER_STATUS_FILTERS = ['pending', 'needs_changes', 'approved', 'rejected', 'suspended', 'removed']
const SELLER_STATUS_LABELS = { pending: 'Pending', needs_changes: 'Changes requested', approved: 'Approved', rejected: 'Rejected', suspended: 'Deactivated', removed: 'Removed' }
const PRODUCT_STATUS_FILTERS = ['unapproved', 'pending', 'draft', 'rejected', 'followups', 'approved', 'deletion']
const PRODUCT_STATUS_LABELS = { unapproved: 'Not approved yet (all)', pending: 'Waiting for review', draft: 'Draft (seller not finished)', approved: 'Approved', rejected: 'Rejected', followups: 'Live — details missing', deletion: 'Removal requested' }
const SELLER_ID_TYPE_LABELS = { aadhaar: 'Aadhaar', pan: 'PAN', passport: 'Passport', ssn: 'SSN', drivers_license: "Driver's License" }
const LEDGER_TYPE_LABELS = { order_credit: 'Order credit', cod_cash_held: 'Cash on delivery kept by seller', refund_debit: 'Refund', payout_debit: 'Payout', payout_fee: 'Withdrawal fee', return_pickup_fee: 'Return pickup fee', delivery_fee_charge: 'Delivery fee (refunded order)', shipping_label: `Shipping label (${brandName()})`, tcs_gst: 'TCS withheld (GST sec. 52)', tds_194o: 'TDS withheld (sec. 194-O)' }
const EMPTY_BRANDING = { store_name: '', tagline: '', logo_url: '', favicon_url: '', theme: 'light', layout_width: 'boxed', color_brand: '#1f7a3d', color_accent: '#ffd23f', color_heading: '#18211c' }
const SOCIAL_PLATFORMS = [['facebook', 'Facebook'], ['x', 'X / Twitter'], ['instagram', 'Instagram'], ['linkedin', 'LinkedIn'], ['youtube', 'YouTube']]
const EMPTY_FOOTER = { copyright: '© {year} {store}', app_store_url: '', play_store_url: '', socials: { facebook: '', x: '', instagram: '', linkedin: '', youtube: '' }, links: [], bg_color: '#f3f5f2', text_color: '#18211c' }

const ISSUE_LABELS = {
  item_missing: 'Item missing', item_damaged: 'Item damaged', wrong_item: 'Wrong item',
  not_delivered: 'Not delivered', payment_issue: 'Payment issue', other: 'Other',
  delivery: 'Delivery message',
  seller_product_issue: 'Seller: product issue', seller_other: 'Seller: other',
}
const SELLER_ISSUE_TYPES = ['seller_product_issue', 'seller_other']

const EMPTY_PRODUCT = { category_id: '', shop_id: '', name: '', sku: '', price: '', compare_at: '', inventory_quantity: 0, description: '', image_url: '', video_url: '', images: [], is_active: true, per_store_stock: false, store_stock: {}, variants: [], condition: '', kind: 'live', affiliate_url: '', affiliate_merchant: '' }

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
// Variant SKUs are numbered -V1…-V30 (App\Support\Sku::MAX_VARIANTS).
const MAX_VARIANTS = 30
// Mirrors App\Support\ProductImages::MAX_IMAGES.
const MAX_IMAGES = 8
const EMPTY_VARIANT = { label: '', sku: '', price: '', compare_at: '', stock: 0, image_url: '', is_active: true }
const dollarsOrBlank = (cents) => (cents != null ? (cents / 100).toFixed(2) : '')

const variantRowsFrom = (product) => (product.variants ?? []).map((v) => ({
  id: v.id, label: v.label, sku: v.sku, price: (v.price_cents / 100).toFixed(2), compare_at: dollarsOrBlank(v.compare_at_price_cents),
  stock: v.inventory_quantity, image_url: v.image_url ?? '', is_active: v.is_active,
}))
const EMPTY_CATEGORY = { name: '', slug: '', image_url: '', sort_order: 0, is_active: true, show_on_home: true, parent_id: '', kind: 'physical' }
// A category's place in the tree, for pickers: "Downloadable › Games › Arcade".
const categoryLabel = (c) => c?.path ?? c?.name ?? ''
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
const EMPTY_PAGE = { title: '', slug: '', banner_image: '', content: '', sections: [], footer_group: 'company', menu_placements: [], show_in_footer: true, is_published: true, sort_order: 0 }
const FOOTER_GROUP_LABELS = { company: 'Company info', legal: 'Customer service', help: 'Help', bottom: 'Lower footer', blog: 'Blog (not shown in footer columns)' }
const FOOTER_COLUMNS = ['company', 'legal', 'help', 'bottom']
// Where a page is actually linked from on the live site — purely for admin
// tracking/organization; show_in_footer is still what gates the real render.
// Pages menu (left): its own order and names.
const NAV_PAGE_GROUPS = ['blog', 'main_menu', 'main_footer', 'seller_footer']

// Secure access stays unlocked only while you're in it: leaving it, or a minute
// without activity, locks it again (also on the server).
const SECURE_KEY = 'nextech_admin_secure'
const storedSecure = (login) => { try { const s = JSON.parse(localStorage.getItem(SECURE_KEY) ?? 'null'); return s && s.login === String(login).slice(-16) ? s.token : '' } catch { return '' } }
const SECURE_SECTIONS = [['fees', 'Withdrawal fees'], ['payouts', 'Payouts'], ['access', 'Keys & account']]
const NAV_PAGE_LABELS = { blog: 'All blogs', main_menu: 'Main help pages', main_footer: 'Main footer pages', seller_footer: 'Seller policies & rules' }
const MENU_PLACEMENT_LABELS = { main_menu: 'Main menu (storefront Help menu)', main_footer: 'Main footer', seller_footer: 'Seller Center → My account → Policies & rules', blog: 'Blog' }
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
// Secure access → new-seller commission (bps on the server, % in the form).
function sellerRateToForm(s) {
  return { new_pct: s.new_seller_commission_rate_bps == null ? '' : (s.new_seller_commission_rate_bps / 100).toFixed(2), days: String(s.new_seller_days ?? 90) }
}

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
    commission_apply_existing: !!f.commission_apply_existing,
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

// The house shop: the owner's own shop on the seller tools (shipping
// templates, couriers + tracking, international, own delivery, reminders),
// with no commission or product review. Managed in Seller Center from the
// admin account that created it.
function HouseShop({ headers, onMessage }) {
  const [data, setData] = useState(null)
  const [form, setForm] = useState(null)
  useEffect(() => { fetchJson(`${API_URL}/admin/house-shop`, { headers: headers() }).then((d) => setData(d.data)).catch((e) => onMessage(e.message)) }, [headers, onMessage])
  if (!data) return null
  const sellerCenter = `${import.meta.env.BASE_URL}#/seller`

  async function create(event) {
    event.preventDefault()
    if (!window.confirm('Create the house shop on this admin account? You’ll manage it in Seller Center, signed in as this account.')) return
    try {
      const d = await fetchJson(`${API_URL}/admin/house-shop`, { method: 'POST', headers: { ...headers(), 'Content-Type': 'application/json' }, body: JSON.stringify(form) })
      setData(d.data); setForm(null); onMessage('House shop created — open Seller Center to add products and set up shipping.')
    } catch (e) { onMessage(e.message) }
  }

  return (
    <div className="admin-form">
      <h4>House shop — sell as the store owner</h4>
      <p className="muted">Your own shop on the seller tools: shipping rates by state with delivery estimates, couriers with tracking numbers and links, international shipping with customs paperwork, own local delivery, labels, and reminders to pack and ship. No commission and no product review. {brandName()}&rsquo;s stores and riders keep working as they are.</p>
      {data.shop ? (
        <p><b>{data.shop.name}</b> · {data.shop.products} product(s) · managed by {data.shop.owner?.name} ({data.shop.owner?.email}) · <a href={sellerCenter} target="_blank" rel="noreferrer">Open Seller Center</a></p>
      ) : form ? (
        <form onSubmit={create} className="admin-form-grid">
          <label>Shop name<input required maxLength="120" value={form.name} onChange={(e) => setForm({ ...form, name: e.target.value })} /></label>
          <label>Ship-from address<input required maxLength="255" value={form.line1} onChange={(e) => setForm({ ...form, line1: e.target.value })} /></label>
          <label>Address line 2<input maxLength="255" value={form.line2} onChange={(e) => setForm({ ...form, line2: e.target.value })} /></label>
          <label>City<input required maxLength="100" value={form.city} onChange={(e) => setForm({ ...form, city: e.target.value })} /></label>
          <label>State<select required value={form.state} onChange={(e) => setForm({ ...form, state: e.target.value })}><option value="">Choose…</option>{Object.entries(data.states ?? {}).map(([code, name]) => <option key={code} value={code}>{name}</option>)}</select></label>
          <label>Postal code<input required maxLength="12" value={form.postal_code} onChange={(e) => setForm({ ...form, postal_code: e.target.value })} /></label>
          <label>Tax number <small className="muted">optional — defaults to your business details</small><input maxLength="60" value={form.tax_id} onChange={(e) => setForm({ ...form, tax_id: e.target.value })} /></label>
          <div className="admin-form-actions wz-wide"><button type="button" className="act ghost" onClick={() => setForm(null)}>Cancel</button><button className="act" type="submit">Create house shop</button></div>
        </form>
      ) : (
        <button type="button" className="act" onClick={() => setForm({ name: `${brandName()} Official Store`, line1: '', line2: '', city: '', state: '', postal_code: '', tax_id: '' })}>Create house shop</button>
      )}
    </div>
  )
}

// Shipped with a courier admin booked by hand: courier, tracking number and
// link (for "Other"), so the buyer gets a track link. Marks it out for delivery.
function ManualCourier({ order, carriers, headers, onDone }) {
  const [form, setForm] = useState(null)
  if (!form) return <button type="button" className="link" onClick={() => setForm({ carrier: order.shipment?.provider === 'manual' ? 'Other' : (carriers[0]?.value ?? 'Other'), tracking: order.shipment?.provider === 'manual' ? order.shipment.tracking_number : '', name: order.shipment?.provider === 'manual' ? order.shipment.carrier : '', site: order.shipment?.provider === 'manual' ? (order.shipment.tracking_url ?? '') : '' })}>{order.shipment?.provider === 'manual' ? 'Edit courier details' : 'Shipped by courier — enter courier details'}</button>

  async function save(event) {
    event.preventDefault()
    try {
      await fetchJson(`${API_URL}/admin/orders/${order.id}/manual-shipment`, { method: 'POST', headers: headers(), body: JSON.stringify({ carrier: form.carrier, tracking_number: form.tracking, carrier_name: form.name || null, tracking_site: form.site || null }) })
      setForm(null); onDone('Courier details saved — the buyer can track it.')
    } catch (e) { onDone(e.message) }
  }

  return (
    <form className="admin-form-grid" onSubmit={save}>
      <label>Courier<select value={form.carrier} onChange={(e) => setForm({ ...form, carrier: e.target.value })}>{(carriers.length ? carriers : [{ value: 'Other', label: 'Other carrier' }]).map((c) => <option key={c.value} value={c.value}>{c.label}</option>)}</select></label>
      <label>Tracking number<input required minLength="4" maxLength="60" value={form.tracking} onChange={(e) => setForm({ ...form, tracking: e.target.value })} /></label>
      {form.carrier === 'Other' && <>
        <label>Courier name<input required maxLength="60" value={form.name} onChange={(e) => setForm({ ...form, name: e.target.value })} /></label>
        <label>Courier tracking website<input required type="url" maxLength="500" placeholder="https://…" value={form.site} onChange={(e) => setForm({ ...form, site: e.target.value })} /></label>
      </>}
      <div className="admin-form-actions wz-wide"><button type="button" className="act ghost" onClick={() => setForm(null)}>Cancel</button><button className="act" type="submit">Save courier details</button></div>
    </form>
  )
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
  const [listingReview, setListingReview] = useState(null) // a seller product being reviewed
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
  const [productDemo, setProductDemo] = useState('all') // all | only | none — the Demo filter
  const [customersPage, setCustomersPage] = useState(1)
  const [customersMeta, setCustomersMeta] = useState(null)
  // Small lists paged client-side.
  const [categoriesPage, setCategoriesPage] = useState(1)
  // Categories list: subcategories stay folded under their parent until its ▸ is clicked.
  const [openCategories, setOpenCategories] = useState(() => new Set())
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
  const [sellersElsewhere, setSellersElsewhere] = useState({}) // other countries' seller counts, for the empty list
  const [sellerDetail, setSellerDetail] = useState(null)
  // "Request changes": { seller, picked: { itemKey: note }, reason }
  const [changeRequest, setChangeRequest] = useState(null)
  const [digitalFilesOf, setDigitalFilesOf] = useState(null) // Products → Files panel
  // Products / Categories submenus: clicking the open section again folds its submenu.
  const [subnavFolded, setSubnavFolded] = useState(false)
  // Shipping (left menu): which shipping form is open.
  const [shipSection, setShipSection] = useState('options')
  // Admin cancelling an order: { order, reason, note } — a reason is required.
  const [cancelling, setCancelling] = useState(null)
  const [shops, setShops] = useState([])
  const [riderMonth, setRiderMonth] = useState(() => { const d = new Date(); return new Date(d.getFullYear(), d.getMonth(), 1) })
  const [riderReport, setRiderReport] = useState(null)
  const [settings, setSettings] = useState(null)
  // The admin's currency / country switch (top bar): Dashboard, Orders,
  // Products, Sellers and Settings → charges show only that market's entries.
  const [adminMarket, setAdminMarket] = useState(() => { try { return localStorage.getItem('nextech_admin_market') || 'ALL' } catch { return 'ALL' } })
  const [feesForm, setFeesForm] = useState(null)
  const [brandingForm, setBrandingForm] = useState(null)
  const [footerForm, setFooterForm] = useState(null)
  const [paymentsForm, setPaymentsForm] = useState(null)
  const [sellerRateForm, setSellerRateForm] = useState(null)
  const [courierForm, setCourierForm] = useState(null)
  const [pendingReviews, setPendingReviews] = useState(0)
  const [openOrders, setOpenOrders] = useState(0)
  const [accountForm, setAccountForm] = useState({ name: '', email: '', phone: '' })
  const [secureGate, setSecureGate] = useState(() => (storedSecure(token) ? 'unlocked' : 'locked')) // locked | code | unlocked
  const [secureSection, setSecureSection] = useState('fees')
  const [commonDetailsOpen, setCommonDetailsOpen] = useState(false)
  const [secureSecret, setSecureSecret] = useState('') // password or OTP code
  const [secureToken, setSecureToken] = useState(() => storedSecure(token))
  const [secureMsg, setSecureMsg] = useState('')
  const [imgBusy, setImgBusy] = useState(false)
  const [threads, setThreads] = useState([])
  const [threadStatus, setThreadStatus] = useState('open')
  const [thread, setThread] = useState(null)
  const [noteDraft, setNoteDraft] = useState('')
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
  const [sellerBellOpen, setSellerBellOpen] = useState(false)
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
  const secureHeaders = useCallback(() => ({ ...jsonHeaders(), 'X-Secure-Access': secureToken }), [jsonHeaders, secureToken])

  const fail = (error) => setMessage(error?.message ?? 'Something went wrong.')

  // A category a seller asked for that won't be added: cleared for good (server-side) and the seller is told.
  async function declineCategorySuggestion(c) {
    if (!window.confirm(`Decline the category “${c.name}”? ${c.shop_name ?? 'The seller'} is told, and their product${c.products > 1 ? 's keep their' : ' keeps its'} current category.`)) return
    try {
      const response = await fetch(`${API_URL}/admin/category-suggestions/decline`, { method: 'POST', headers: jsonHeaders(), body: JSON.stringify({ name: c.name }) })
      const data = await readJson(response)
      if (!response.ok) throw new Error(data.message ?? 'Could not decline the category.')
      setNotifications((cur) => ({ ...cur, category_suggestions: (cur.category_suggestions ?? []).filter((x) => x.name !== c.name) }))
      setMessage(`Declined “${c.name}” — the seller has been told.`)
    } catch (error) { fail(error) }
  }

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
    if (productDemo === 'ad') qs.set('affiliate', 'only')
    else if (productDemo !== 'all') qs.set('demo', productDemo)
    track('products', fetch(`${API_URL}/admin/products?${qs}`, { headers: authHeaders() }).then(readJson)
      .then((data) => { setProducts(data.data ?? []); setProductsMeta(data.meta ?? null) }).catch(() => setMessage('Could not load products.')))
  }, [authHeaders, productSearch, productSort, productStore, productCategory, productStatus, productDemo, productsPage, pageSize, track])

  // Demo products: flag one, flag everything the filters match, or show / hide them all on the store.
  async function setProductDemoFlag(product, isDemo) {
    try {
      const response = await fetch(`${API_URL}/admin/products/${product.id}/demo`, { method: 'PATCH', headers: jsonHeaders(), body: JSON.stringify({ is_demo: isDemo }) })
      if (!response.ok) throw new Error((await readJson(response)).message ?? 'Could not update the product.')
      loadProducts()
    } catch (error) { fail(error) }
  }

  async function markAllDemo(isDemo) {
    const total = productsMeta?.total ?? products.length
    if (!window.confirm(`Mark all ${total} product${total === 1 ? '' : 's'} matching the current filters as ${isDemo ? 'demo' : 'not demo'}?`)) return
    try {
      const body = { is_demo: isDemo, search: productSearch.trim() || null, category_id: productCategory ? Number(productCategory) : null, status: productStatus !== 'all' ? productStatus : null }
      const response = await fetch(`${API_URL}/admin/products/demo`, { method: 'POST', headers: jsonHeaders(), body: JSON.stringify(body) })
      const data = await readJson(response)
      if (!response.ok) throw new Error(data.message ?? 'Could not update the products.')
      setMessage(`${data.data.updated} product${data.data.updated === 1 ? '' : 's'} marked as ${isDemo ? 'demo' : 'not demo'}.`)
      loadProducts()
    } catch (error) { fail(error) }
  }

  async function setDemosHidden(hidden) {
    try {
      const response = await fetch(`${API_URL}/admin/products/demo-visibility`, { method: 'POST', headers: jsonHeaders(), body: JSON.stringify({ hidden }) })
      if (!response.ok) throw new Error((await readJson(response)).message ?? 'Could not change demo products.')
      setMessage(hidden ? 'Demo products are now hidden from the store.' : 'Demo products are shown on the store again.')
      loadProducts()
    } catch (error) { fail(error) }
  }

  // Open a product in the edit form; `patch` overrides fields (e.g. kind: 'ad').
  function openProductEdit(product, patch = {}) {
    if (!stores.length) loadStores()
    setProductForm({ ...{ id: product.id, category_id: product.category_id, shop_id: product.shop_id ?? '', market: product.market ?? '', name: product.name, sku: product.sku, price: (product.price_cents / 100).toFixed(2), compare_at: dollarsOrBlank(product.compare_at_price_cents), return_days: product.return_days ?? '', return_policy: product.return_policy ?? null, inventory_quantity: product.inventory_quantity, description: product.description ?? '', image_url: product.image_url ?? '', video_url: product.video_url ?? '', is_active: product.is_active, per_store_stock: (product.store_inventory ?? []).length > 0, store_stock: storeStockFrom(product), variants: variantRowsFrom(product), lightning_starts_at: product.lightning_starts_at, lightning_ends_at: product.lightning_ends_at, lightning_qty: product.lightning_qty, lightning_pct: product.lightning_pct, lightning_pct_min: product.lightning_pct_min, lightning_pct_max: product.lightning_pct_max, lightning_repeat: product.lightning_repeat, price_cents: product.price_cents, compare_at_price_cents: product.compare_at_price_cents, condition: product.condition ?? '', kind: product.affiliate_url ? 'ad' : product.is_demo ? 'demo' : 'live', affiliate_url: product.affiliate_url ?? '', affiliate_merchant: product.affiliate_merchant ?? '', suggested_category_name: product.suggested_category_name ?? '', images: (product.images ?? []).map((i) => i.url) }, ...patch })
    scrollFormIntoView('admin-product-form')
  }

  // The row's Live / Demo / Ad choice. Ad needs a partner link, so it opens the form.
  async function setProductKind(product, kind) {
    if (kind === 'ad') { openProductEdit(product, { kind: 'ad', affiliate_url: product.affiliate_url || '' }); setMessage('Add the partner (affiliate) link, then save to make it an ad.'); return }
    if (product.affiliate_url) {
      try {
        const response = await fetch(`${API_URL}/admin/products/${product.id}`, { method: 'PATCH', headers: jsonHeaders(), body: JSON.stringify({ affiliate_url: null, affiliate_merchant: null, is_demo: kind === 'demo' }) })
        if (!response.ok) throw new Error((await readJson(response)).message ?? 'Could not change the product.')
        setMessage(`${product.name} is now ${kind === 'demo' ? 'a demo product' : 'a live product'}.`)
        loadProducts()
      } catch (error) { fail(error) }
      return
    }
    setProductDemoFlag(product, kind === 'demo')
  }

  async function setAffiliatesHidden(hidden) {
    try {
      const response = await fetch(`${API_URL}/admin/products/affiliate-visibility`, { method: 'POST', headers: jsonHeaders(), body: JSON.stringify({ hidden }) })
      if (!response.ok) throw new Error((await readJson(response)).message ?? 'Could not change affiliate products.')
      setMessage(hidden ? 'Affiliate products are now hidden from the store.' : 'Affiliate products are shown on the store again.')
      loadProducts()
    } catch (error) { fail(error) }
  }

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
      .then((data) => { setSellers(data.data ?? []); setSellersElsewhere(data.elsewhere ?? {}) }).catch(() => setMessage('Could not load seller applications.')))
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
        setSellerRateForm(sellerRateToForm(data.data))
        setCourierForm({
          courier_provider: data.data.courier?.provider ?? 'mock',
          courier_base_url: data.data.courier?.base_url ?? '',
          courier_account_code: data.data.courier?.account_code ?? '',
          courier_api_key: '',
          courier_api_secret: '',
          tracking_api_key: '',
          tracking_webhook_secret: '',
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
  // Orders not finished yet (awaiting payment through out for delivery), for the
  // count in the top bar; refreshed with the orders list and every minute.
  useEffect(() => {
    let stopped = false
    const check = () => fetch(`${API_URL}/admin/orders?status=open&per_page=1`, { headers: authHeaders() }).then(readJson)
      .then((d) => { if (!stopped && typeof d.meta?.total === 'number') setOpenOrders(d.meta.total) }).catch(() => {})
    check()
    const timer = setInterval(check, 60000)
    return () => { stopped = true; clearInterval(timer) }
  }, [authHeaders, orders])
  // Reviews waiting for approval, for the badge on the Reviews menu item.
  useEffect(() => {
    let stopped = false
    const check = () => fetch(`${API_URL}/admin/reviews?status=pending`, { headers: authHeaders() }).then(readJson)
      .then((d) => { if (!stopped && typeof d.pending === 'number') setPendingReviews(d.pending) }).catch(() => {})
    check()
    const timer = setInterval(check, 120000)
    return () => { stopped = true; clearInterval(timer) }
  }, [authHeaders])
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
  useEffect(() => { if (tab === 'settings' || tab === 'shipping') { loadSettings(); loadStores() } }, [tab, loadSettings, loadStores])
  // The country dropdown in the top bar is built from the settings — load them on open.
  useEffect(() => { if (!settings) loadSettings() }, [settings, loadSettings])
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
        // First load: seed without chiming — and carry on, so the bells load straight away too.
        const fresh = seenRef.current === null ? [] : pending.filter((t) => seenRef.current[t.id] !== t.last_message_at)
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
    const payload = { ...rest, country: rest.country || workMarket, delivery_radius_km: Number(rest.delivery_radius_km) }
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
      acceptance_for: page.acceptance_for ?? '',
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
    const { id, no_menu: noMenu, ...rest } = pageForm
    if (!id && rest.menu_placements.length === 0 && !noMenu) { setMessage('Choose where to add this page (or tick “Not in a menu yet”).'); return }
    const payload = { ...rest, slug: rest.slug.trim(), parent_slug: (rest.parent_slug ?? '').trim() || null, sort_order: Number(rest.sort_order) || 0, acceptance_for: rest.acceptance_for || null }
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
      store_name: brandingForm.store_name.trim() || `${brandName()}`,
      tagline: brandingForm.tagline.trim(),
      logo_url: brandingForm.logo_url.trim(),
      favicon_url: brandingForm.favicon_url.trim(),
      theme: brandingForm.theme,
      layout_width: brandingForm.layout_width,
      color_brand: brandingForm.color_brand,
      color_accent: brandingForm.color_accent,
      color_heading: brandingForm.color_heading,
    })
    // The new logo/name shows everywhere straight away (admin, Seller Center, rider, storefront).
    if (saved) { setBrandingForm({ ...EMPTY_BRANDING, ...(saved.branding ?? {}) }); setSharedBranding(saved.branding ?? {}); setMessage('Store settings saved — the new logo and name show everywhere.') }
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
  const restoredSecure = useRef(storedSecure(token))
  useEffect(() => {
    if (!restoredSecure.current) return
    fetch(`${API_URL}/admin/secure-access/state`, { headers: { ...authHeaders(), 'X-Secure-Access': restoredSecure.current } })
      .then((response) => (response.ok ? response.json() : Promise.reject(new Error('locked'))))
      .then((data) => { const a = data.data?.account; if (a) setAccountForm({ name: a.name ?? '', email: a.email ?? '', phone: a.phone ?? '' }) })
      .catch(() => { try { localStorage.removeItem(SECURE_KEY) } catch { /* ignore */ } setSecureToken(''); setSecureGate('locked') })
    restoredSecure.current = ''
  }, [authHeaders])

  async function challengeSecure() {
    setSecureMsg('Sending a code to your admin email…')
    try {
      const response = await fetch(`${API_URL}/admin/secure-access/challenge`, { method: 'POST', headers: authHeaders() })
      const data = await readJson(response)
      if (!response.ok) throw new Error(data.message ?? 'Could not send the code.')
      setSecureMsg(data.data?.message ?? 'Check your admin email for the code.')
    } catch (error) { setSecureMsg(error.message) }
  }

  // Lock again (the button, or the server said the unlock ended).
  function relockSecure() {
    try { localStorage.removeItem(SECURE_KEY) } catch { /* ignore */ }
    if (secureToken) fetch(`${API_URL}/admin/secure-access/lock`, { method: 'POST', headers: { ...authHeaders(), 'X-Secure-Access': secureToken } }).catch(() => {})
    setSecureToken(''); setSecureSecret('')
    setSecureGate('code')
  }

  // A minute with no mouse, keyboard or scrolling in Secure access locks it.
  const relockRef = useRef(relockSecure)
  useEffect(() => { relockRef.current = relockSecure })
  useEffect(() => {
    if (tab !== 'secure' || !secureToken) return undefined
    let timer = setTimeout(() => relockRef.current(), 60000)
    const active = () => { clearTimeout(timer); timer = setTimeout(() => relockRef.current(), 60000) }
    const events = ['mousemove', 'mousedown', 'keydown', 'scroll', 'touchstart']
    events.forEach((e) => window.addEventListener(e, active, { passive: true }))
    return () => { clearTimeout(timer); events.forEach((e) => window.removeEventListener(e, active)) }
  }, [tab, secureToken])

  async function unlockSecure() {
    setSecureMsg('')
    const body = secureMethod === 'otp' ? { code: secureSecret.trim() } : { password: secureSecret }
    try {
      const response = await fetch(`${API_URL}/admin/secure-access/unlock`, { method: 'POST', headers: jsonHeaders(), body: JSON.stringify(body) })
      const data = await readJson(response)
      if (!response.ok) throw new Error(data.message ?? 'That did not work.')
      setSecureToken(data.data.token)
      try { localStorage.setItem(SECURE_KEY, JSON.stringify({ login: String(token).slice(-16), token: data.data.token })) } catch { /* private mode */ }
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
      relockSecure()
    }
  }

  // Secure access → commission for new sellers (their first N days after approval).
  async function saveSellerRates(event) {
    event.preventDefault()
    const pct = sellerRateForm.new_pct.trim()
    const patch = {
      new_seller_commission_rate_bps: pct === '' ? null : Math.max(0, Math.min(10000, Math.round(Number(pct) * 100))),
      new_seller_days: Math.max(1, Math.min(3650, Math.round(Number(sellerRateForm.days) || 90))),
    }
    const saved = await saveSetting(patch, { 'X-Secure-Access': secureToken })
    if (saved) { setSellerRateForm(sellerRateToForm(saved)); setMessage('Seller commission saved — applies to new orders.') }
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
    if (courierForm.tracking_api_key.trim()) patch.tracking_api_key = courierForm.tracking_api_key.trim()
    if (courierForm.tracking_webhook_secret.trim()) patch.tracking_webhook_secret = courierForm.tracking_webhook_secret.trim()
    const saved = await saveSetting(patch, { 'X-Secure-Access': secureToken })
    if (saved) {
      setCourierForm({
        courier_provider: saved.courier?.provider ?? 'mock',
        courier_base_url: saved.courier?.base_url ?? '',
        courier_account_code: saved.courier?.account_code ?? '',
        courier_api_key: '',
        courier_api_secret: '',
          tracking_api_key: '',
          tracking_webhook_secret: '',
      })
      setMessage('Courier settings saved — they take effect immediately.')
    } else {
      relockSecure()
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
          <button key={status} type="button" disabled={busyId === order.id} className={status === 'cancelled' ? 'act danger' : 'act'} onClick={() => (status === 'cancelled' ? setCancelling({ order, reason: '', note: '' }) : patchOrder(order, { status }))}>{label}</button>
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
    setNoteDraft('')
    setRefundForm({ items: [], amount: '', reason: '', charge_pickup: true, charge_delivery: true })
    setSupportToasts((cur) => cur.filter((t) => t.id !== id))
    try {
      const data = await readJson(await fetch(`${API_URL}/admin/support/threads/${id}`, { headers: authHeaders() }))
      setThread(data.data)
      if (seenRef.current && data.data.last_message_at) seenRef.current[id] = data.data.last_message_at
    } catch (error) { fail(error) }
  }

  // Internal notes on a support thread (staff only, never in the chat).
  async function addThreadNote() {
    if (!thread || !noteDraft.trim()) return
    try {
      const response = await fetch(`${API_URL}/admin/support/threads/${thread.id}/notes`, { method: 'POST', headers: jsonHeaders(), body: JSON.stringify({ body: noteDraft.trim() }) })
      const data = await readJson(response)
      if (!response.ok) throw new Error(data.message ?? 'Note not saved.')
      setNoteDraft('')
      setThread(data.data)
    } catch (error) { fail(error) }
  }

  async function deleteThreadNote(noteId) {
    if (!thread || !window.confirm('Delete this note?')) return
    try {
      const response = await fetch(`${API_URL}/admin/support/threads/${thread.id}/notes/${noteId}`, { method: 'DELETE', headers: authHeaders() })
      const data = await readJson(response)
      if (!response.ok) throw new Error(data.message ?? 'Note not deleted.')
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
                <span className="muted">The refunded item value (less the commission they paid) is always taken back from the seller. Untick these for a {brandName()} fault, e.g. delivery damage.</span>
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
    if (!rest.image_url?.trim() && !(rest.images ?? []).filter(Boolean).length) { setMessage('Add a product image — a product can’t go live without one.'); return }
    const payload = { ...rest, market: rest.shop_id ? undefined : (rest.market || workMarket), category_id: Number(rest.category_id), shop_id: rest.shop_id ? Number(rest.shop_id) : null, inventory_quantity: Number(rest.inventory_quantity), price_cents: Math.round(Number(price) * 100), compare_at_price_cents: String(compareAt).trim() ? Math.round(Number(compareAt) * 100) : null, return_days: String(rest.return_days ?? '').trim() === '' ? null : Number(rest.return_days), image_url: rest.image_url?.trim() || null, images: (rest.images ?? []).filter(Boolean), video_url: rest.video_url?.trim() || null, condition: rest.condition || null, is_demo: rest.kind === 'demo', affiliate_url: rest.kind === 'ad' ? (rest.affiliate_url?.trim() || null) : null, affiliate_merchant: rest.kind === 'ad' ? (rest.affiliate_merchant?.trim() || null) : null, kind: undefined }

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
  // Product video: MP4 / WebM / MOV up to 100 MB (same as sellers').
  async function uploadVideo(file) {
    if (!file) return
    setMessage('')
    if (!['video/mp4', 'video/webm', 'video/quicktime'].includes(file.type)) { fail(new Error('Videos must be MP4, WebM or MOV.')); return }
    if (file.size > 100 * 1024 * 1024) { fail(new Error('Videos can be up to 100 MB.')); return }
    setImgBusy(true)
    try {
      const body = new FormData()
      body.append('file', file)
      const response = await fetch(`${API_URL}/admin/media/video`, { method: 'POST', headers: authHeaders(), body })
      const data = await readJson(response)
      if (!response.ok) throw new Error(data.message ?? Object.values(data.errors ?? {})[0]?.[0] ?? 'Upload failed.')
      setProductForm((form) => ({ ...form, video_url: data.data.url }))
    } catch (error) { fail(error) } finally { setImgBusy(false) }
  }

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

  // A seller asked to remove a product (it's already hidden). Remove = deleted,
  // or archived when it's on past orders; Keep = stays hidden, request cleared.
  async function decideDeletion(product, decision) {
    if (decision === 'remove' && !window.confirm(`Remove ${product.name}? It's taken out of the catalog and the seller's list. ${product.support_until ? `Past buyers are still under returns/warranty until ${new Date(product.support_until).toLocaleDateString()} — they keep a "no longer sold" page and the seller still owes them support.` : 'Past orders keep their record.'}`)) return
    setMessage('')
    setBusyId(product.id)
    try {
      const response = await fetch(`${API_URL}/admin/products/${product.id}/deletion`, { method: 'POST', headers: jsonHeaders(), body: JSON.stringify({ decision }) })
      if (!response.ok) throw new Error((await readJson(response)).message ?? 'Could not update the product.')
      setMessage(decision === 'remove' ? `${product.name} removed.` : `${product.name} kept (still hidden).`)
      loadProducts()
    } catch (error) { fail(error) } finally { setBusyId(null) }
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
    const payload = { ...rest, sort_order: Number(rest.sort_order), parent_id: rest.parent_id ? Number(rest.parent_id) : null }
    if (!payload.slug) delete payload.slug
    if (payload.parent_id) delete payload.kind // a subcategory takes its parent's kind
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
      return true
    } catch (error) { fail(error); return false } finally { setBusyId(null) }
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
      setProducts((cur) => cur.map((p) => (p.id === product.id ? { ...p, ...data.data } : p)))
      setListingReview((cur) => (cur?.id === product.id ? { ...cur, ...data.data } : cur))
    } catch (error) { fail(error) } finally { setBusyId(null) }
  }

  // Approve; when details are still missing (HSN / GST rate, compliance
  // documents, or the seller never submitted it), confirm "approve anyway" —
  // it goes live and the seller is emailed to add them soon.
  function approveProduct(product) {
    const f = product.followups ?? { blocking: [], later: [] }
    if (f.blocking?.length) { setMessage(`Can’t go live yet: ${f.blocking.join(' ')}`); return }
    const missing = f.later ?? []
    if (missing.length || product.status === 'draft') {
      const list = missing.length ? missing.map((m) => `• ${m}`).join('\n') : '• The seller hasn’t submitted it yet.'
      if (!window.confirm(`Approve "${product.name}" anyway? It goes live now and the seller is asked to add soon:\n\n${list}`)) return
      productAction(product, 'approve', { override: true })
      return
    }
    productAction(product, 'approve')
  }

  function rejectProduct(product) {
    const reason = window.prompt(`Reason for rejecting "${product.name}":`, '')
    if (reason) productAction(product, 'reject', { reason })
  }
  // Remove a seller for good: products never ordered are deleted, the rest switched off.
  async function removeSeller(seller) {
    const name = seller.shop?.name ?? seller.company_name ?? 'this seller'
    const reason = window.prompt(`Remove ${name} permanently? Their shop closes, products never ordered are deleted, and products with orders are switched off. They can't re-apply.

Reason:`, '')
    if (!reason?.trim()) return
    setBusyId(seller.id)
    try {
      const response = await fetch(`${API_URL}/admin/sellers/${seller.id}`, { method: 'DELETE', headers: jsonHeaders(), body: JSON.stringify({ reason: reason.trim() }) })
      const data = await readJson(response)
      if (!response.ok) throw new Error(data.message ?? 'Could not remove the seller.')
      setSellers((cur) => cur.map((s) => (s.id === seller.id ? data.data : s)))
      setSellerDetail((cur) => (cur?.id === seller.id ? { ...cur, ...data.data } : cur))
      setMessage(`${name} removed — ${data.meta.deleted} product${data.meta.deleted === 1 ? '' : 's'} deleted${data.meta.kept ? `, ${data.meta.kept} with orders switched off` : ''}.`)
    } catch (error) { fail(error) } finally { setBusyId(null) }
  }

  function suspendSeller(seller) {
    const reason = window.prompt(`Reason for suspending ${seller.shop?.name ?? 'this seller'}:`, '')
    if (reason) sellerAction(seller, 'suspend', { reason })
  }

  // Opens (or brings up) the chat window with this seller, bottom right.
  function messageSeller(seller) {
    openSellerChat(seller)
  }

  // Temu-style: tick the items the seller must fix (each with an optional note).
  function requestSellerChanges(seller) {
    setChangeRequest({ seller, picked: {}, reason: '' })
  }

  async function sendChangeRequest(event) {
    event.preventDefault()
    const { seller, picked, reason } = changeRequest
    const items = Object.entries(picked).map(([key, note]) => ({ key, note }))
    if (items.length === 0 && !reason.trim()) return
    if (await sellerAction(seller, 'request-changes', { items, reason: reason.trim() || null })) setChangeRequest(null)
  }

  // Stop a seller's international selling, or let them sell abroad again.
  async function intlAction(seller, decision) {
    const note = decision === 'revoke' ? window.prompt('Why are you stopping their international selling? (sent to the seller)') : null
    if (decision === 'revoke' && !note) return
    setBusyId(seller.id)
    try {
      const data = await fetchJson(`${API_URL}/admin/sellers/${seller.id}/international`, { method: 'POST', headers: jsonHeaders(), body: JSON.stringify({ decision, note }) })
      setSellerDetail((cur) => (cur?.id === seller.id ? { ...cur, intl_approval: data.data.intl_approval } : cur))
      setMessage(decision === 'revoke' ? 'International selling stopped.' : 'International selling restored.')
    } catch (error) { fail(error) } finally { setBusyId(null) }
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

  // Seller notifications: × hides one (remembered like the main bell's). Keys
  // include what changes — a new request, date or amount brings it back.
  const sellerKey = {
    cod: (c) => `scod-${c.order_id}-${c.seller_id}`,
    owe: (o) => `sowe-${o.seller_id}-${o.owed_cents}`,
    task: (t) => `stask-${t.seller_id}-${t.task}-${t.at}`,
    app: (a) => `sapp-${a.id}-${a.at}`,
    cat: (c) => `scat-${c.name}-${c.shop_name}-${c.at}`,
    pay: (r) => `spay-${r.id}`,
    label: (r) => `slabel-${r.id}`,
  }
  const notDismissed = (kind) => (item) => !dismissedNotifs.has(sellerKey[kind](item))
  const payoutRequests = (notifications.payout_requests ?? []).filter(notDismissed('pay'))
  const labelRequests = notifications.label_requests ?? []
  const sellerLabelRequests = labelRequests.filter(notDismissed('label'))
  const riderPayoutRequests = notifications.rider_payout_requests ?? []
  const riderApplications = notifications.rider_applications ?? []
  const sellerApplications = (notifications.seller_applications ?? []).filter(notDismissed('app'))
  const categorySuggestions = (notifications.category_suggestions ?? []).filter(notDismissed('cat'))
  const refundsDue = notifications.refunds_due ?? []
  const sellerTasks = (notifications.seller_tasks ?? []).filter(notDismissed('task'))
  const codKept = (notifications.cod_kept ?? []).filter(notDismissed('cod'))
  const sellersOwing = (notifications.sellers_owing ?? []).filter(notDismissed('owe'))
  // Counts, not items: × hides the line until the number changes.
  const productsWaiting = dismissedNotifs.has(`sprod-${notifications.products_waiting}`) ? 0 : (notifications.products_waiting ?? 0)
  const trademarkReviews = dismissedNotifs.has(`stm-${notifications.trademark_reviews}`) ? 0 : (notifications.trademark_reviews ?? 0)
  const removalRequests = dismissedNotifs.has(`sdel-${notifications.removal_requests}`) ? 0 : (notifications.removal_requests ?? 0)
  // Seller items live under the top-bar "Sellers" button, not the main bell.
  const sellerNotificationCount = sellerApplications.length + sellerTasks.length + payoutRequests.length + codKept.length + sellersOwing.length
    + sellerLabelRequests.length + categorySuggestions.length + (productsWaiting > 0 ? 1 : 0) + (removalRequests > 0 ? 1 : 0) + (trademarkReviews > 0 ? 1 : 0)
  const notificationCount = refundsDue.length
    + riderPayoutRequests.length
    + riderApplications.length
    + visibleRefusedCod.length
    + visibleCashOverdue.length
    + visibleNegativeFeedback.length
    + visibleFinancialActivity.length

  const homeMarket = settings?.home_market ?? 'US'
  const marketOptions = settings?.all_markets?.length ? settings.all_markets : [{ code: homeMarket, name: settings?.home_market_name ?? 'United States', currency: settings?.home_currency ?? 'usd' }]
  // 'ALL' = every country in the lists (each row carries a country badge); totals,
  // charts and charge settings can't add up different currencies, so they use
  // the default country then (workMarket).
  const activeMarket = adminMarket === 'ALL' || !marketOptions.some((m) => m.code === adminMarket) ? (marketOptions.length > 1 ? 'ALL' : homeMarket) : adminMarket
  const workMarket = activeMarket === 'ALL' ? homeMarket : activeMarket
  const activeCurrency = marketOptions.find((m) => m.code === workMarket)?.currency ?? 'usd'
  const countryBadge = (code) => (activeMarket === 'ALL' && code ? <span className="pill pill-country" title={marketOptions.find((m) => m.code === code)?.name ?? code}>{code}</span> : null)
  setStoreCurrency(activeCurrency)
  const chargesMarket = workMarket === 'US' ? 'home' : workMarket // the US uses the original charge forms
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

  const categoryById = new Map(categories.map((c) => [c.id, c]))
  const shownCategories = categories.filter((c) => {
    for (let p = c.parent_id, guard = 0; p && guard < 10; p = categoryById.get(p)?.parent_id, guard++) if (!openCategories.has(p)) return false
    return true
  })

  const goTab = (name) => {
    // Leaving Secure access locks it; entering it while locked starts the challenge.
    if (tab === 'secure' && name !== 'secure') relockSecure()
    if (name === 'secure' && tab !== 'secure' && !secureToken) {
      setSecureGate('code'); setSecureSecret(''); setSecureMsg('')
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
        <div className="admin-brand"><BrandLogo onDark /> Admin console</div>
        <div className="admin-bar-right">
          {marketOptions.length > 1 && (
            <select className="admin-market-select" aria-label="Currency" title="Show entries for this currency / country" value={activeMarket} onChange={(event) => switchAdminMarket(event.target.value)}>
              <option value="ALL">All countries</option>
              {marketOptions.map((m) => <option key={m.code} value={m.code}>{currencySymbol(m.currency)} {m.currency.toUpperCase()} · {m.name}</option>)}
            </select>
          )}
          {openOrders > 0 && (
            <button type="button" className={`admin-top-tab${tab === 'orders' && statusFilter === 'open' ? ' active' : ''}`} title="Orders not delivered or cancelled yet" onClick={() => { goTab('orders'); setStatusFilter('open'); setOrdersPage(1) }}>
              Open orders<span className="tab-badge">{openOrders > 99 ? '99+' : openOrders}</span>
            </button>
          )}
          {TOP_TABS.map((name) => (
            <button key={name} type="button" className={`admin-top-tab${tab === name ? ' active' : ''}`} onClick={() => goTab(name)}>
              {TAB_LABELS[name]}{name === 'support' && supportBadge > 0 && <span className="tab-badge">{supportBadge}</span>}
            </button>
          ))}
          {/* Seller notifications, kept apart from the main bell: applications, onboarding
              reviews, products, payouts, labels and category requests. */}
          <div className="admin-bell-wrap">
            <button className="admin-top-tab admin-seller-bell" type="button" title="Seller notifications" aria-expanded={sellerBellOpen} onClick={() => { setSellerBellOpen((v) => !v); setBellOpen(false) }}>
              Sellers{sellerNotificationCount > 0 && <span className="tab-badge">{sellerNotificationCount > 99 ? '99+' : sellerNotificationCount}</span>}
            </button>
            {sellerBellOpen && (
              <div className="admin-bell-pop" role="menu">
                <h4>Sellers — needs attention</h4>
                {sellerNotificationCount === 0 ? <p className="muted">Nothing outstanding.</p> : (
                  <>
                    {codKept.length > 0 && (
                      <section>
                        <h5>Cash kept by sellers (7 days)</h5>
                        {codKept.slice(0, BELL_ITEM_CAP).map((c) => (
                          <div className="admin-bell-row" key={`cod-${c.order_id}-${c.seller_id}`}>
                            <button type="button" className="admin-bell-item" onClick={() => { setSellerBellOpen(false); openOrderById(c.order_id) }}>
                              💵 {c.shop_name ?? 'Seller'} kept {money(c.amount_cents, c.currency)} — order #{c.order_id} · {new Date(c.at).toLocaleDateString()}
                            </button>
                            <button type="button" className="admin-bell-x" title="Dismiss" onClick={(event) => { event.stopPropagation(); dismissNotif(sellerKey.cod(c)) }}>×</button>
                          </div>
                        ))}
                      </section>
                    )}
                    {sellersOwing.length > 0 && (
                      <section>
                        <h5>Sellers owing {brandName()}</h5>
                        {sellersOwing.slice(0, BELL_ITEM_CAP).map((o) => (
                          <div className="admin-bell-row" key={`owe-${o.seller_id}`}>
                            <button type="button" className={o.over_limit ? 'admin-bell-item warn' : 'admin-bell-item'} onClick={() => { setSellerBellOpen(false); goTab('sellers'); if (o.seller_id) openSellerDetail(o.seller_id) }}>
                              ⚖️ {o.shop_name} owes {money(o.owed_cents, o.currency)}{o.over_limit ? ' — over the limit, cash on delivery paused' : ' — comes out of their next orders'}
                            </button>
                            <button type="button" className="admin-bell-x" title="Dismiss" onClick={(event) => { event.stopPropagation(); dismissNotif(sellerKey.owe(o)) }}>×</button>
                          </div>
                        ))}
                      </section>
                    )}
                    {sellerTasks.length > 0 && (
                      <section>
                        <h5>Onboarding to review</h5>
                        {sellerTasks.slice(0, BELL_ITEM_CAP).map((t) => (
                          <div className="admin-bell-row" key={`stask-${t.seller_id}-${t.task}`}>
                            <button type="button" className="admin-bell-item warn" onClick={() => { setSellerBellOpen(false); goTab('sellers'); openSellerDetail(t.seller_id) }}>
                              📝 {t.name ?? 'Seller'} — {t.label}{t.at ? ` · ${new Date(t.at).toLocaleDateString()}` : ''}
                            </button>
                            <button type="button" className="admin-bell-x" title="Dismiss" onClick={(event) => { event.stopPropagation(); dismissNotif(sellerKey.task(t)) }}>×</button>
                          </div>
                        ))}
                        {sellerTasks.length > BELL_ITEM_CAP && (
                          <button type="button" className="admin-bell-more" onClick={() => { setSellerBellOpen(false); goTab('sellers') }}>+{sellerTasks.length - BELL_ITEM_CAP} more — see Sellers</button>
                        )}
                      </section>
                    )}
                    {(productsWaiting > 0 || removalRequests > 0 || trademarkReviews > 0) && (
                      <section>
                        <h5>Seller products</h5>
                        {productsWaiting > 0 && <div className="admin-bell-row"><button type="button" className="admin-bell-item" onClick={() => { setSellerBellOpen(false); if (marketOptions.length > 1) switchAdminMarket('ALL'); goTab('products'); setProductStatus('pending'); setProductsPage(1); setProductForm(null) }}>📦 {productsWaiting} waiting for review</button><button type="button" className="admin-bell-x" title="Dismiss" onClick={(event) => { event.stopPropagation(); dismissNotif(`sprod-${productsWaiting}`) }}>×</button></div>}
                        {removalRequests > 0 && <div className="admin-bell-row"><button type="button" className="admin-bell-item" onClick={() => { setSellerBellOpen(false); if (marketOptions.length > 1) switchAdminMarket('ALL'); goTab('products'); setProductStatus('deletion'); setProductsPage(1); setProductForm(null) }}>🗑️ {removalRequests} removal request{removalRequests === 1 ? '' : 's'}</button><button type="button" className="admin-bell-x" title="Dismiss" onClick={(event) => { event.stopPropagation(); dismissNotif(`sdel-${removalRequests}`) }}>×</button></div>}
                        {trademarkReviews > 0 && <div className="admin-bell-row"><button type="button" className="admin-bell-item" onClick={() => { setSellerBellOpen(false); if (marketOptions.length > 1) switchAdminMarket('ALL'); goTab('sellers') }}>™️ {trademarkReviews} trademark{trademarkReviews === 1 ? '' : 's'} / change request{trademarkReviews === 1 ? '' : 's'} to review</button><button type="button" className="admin-bell-x" title="Dismiss" onClick={(event) => { event.stopPropagation(); dismissNotif(`stm-${trademarkReviews}`) }}>×</button></div>}
                      </section>
                    )}
                    {sellerApplications.length > 0 && (
                      <section>
                        <h5>New seller applications</h5>
                        {sellerApplications.slice(0, BELL_ITEM_CAP).map((a) => (
                          <div className="admin-bell-row" key={`sapp-${a.id}`}>
                            <button type="button" className="admin-bell-item" onClick={() => { setSellerBellOpen(false); goTab('sellers'); openSellerDetail(a.id) }}>
                              🏪 {a.name ?? 'New seller'} · {new Date(a.at).toLocaleDateString()}
                            </button>
                            <button type="button" className="admin-bell-x" title="Dismiss" onClick={(event) => { event.stopPropagation(); dismissNotif(sellerKey.app(a)) }}>×</button>
                          </div>
                        ))}
                        {sellerApplications.length > BELL_ITEM_CAP && (
                          <button type="button" className="admin-bell-more" onClick={() => { setSellerBellOpen(false); goTab('sellers') }}>+{sellerApplications.length - BELL_ITEM_CAP} more — see Sellers</button>
                        )}
                      </section>
                    )}
                    {categorySuggestions.length > 0 && (
                      <section>
                        <h5>New categories sellers asked for</h5>
                        {categorySuggestions.slice(0, BELL_ITEM_CAP).map((c) => (
                          <div className="admin-bell-row" key={`cat-${c.name}`}>
                            <button type="button" className="admin-bell-item" title="Create this category (it leaves this list once it exists), then set it on the seller's product" onClick={() => { setSellerBellOpen(false); goTab('categories'); setCategoryForm({ ...EMPTY_CATEGORY, name: c.name }); scrollFormIntoView('admin-category-form') }}>
                              🗂️ &ldquo;{c.name}&rdquo; — {c.shop_name ?? 'Seller'}, {c.products > 1 ? `${c.products} products` : c.product_name} · {new Date(c.at).toLocaleDateString()}
                            </button>
                            <button type="button" className="admin-bell-decline" title="Don't add this category — the seller is told and their product keeps its category" onClick={(event) => { event.stopPropagation(); declineCategorySuggestion(c) }}>Decline</button>
                          </div>
                        ))}
                        {categorySuggestions.length > BELL_ITEM_CAP && (
                          <button type="button" className="admin-bell-more" onClick={() => { setSellerBellOpen(false); goTab('products') }}>+{categorySuggestions.length - BELL_ITEM_CAP} more — see Products</button>
                        )}
                      </section>
                    )}
                    {payoutRequests.length > 0 && (
                      <section>
                        <h5>Seller payout requests</h5>
                        {payoutRequests.slice(0, BELL_ITEM_CAP).map((r) => (
                          <div className="admin-bell-row" key={`payout-${r.id}`}>
                            <button type="button" className="admin-bell-item warn" onClick={() => { setSellerBellOpen(false); setSecureSection('payouts'); goTab('secure') }}>
                              💸 {r.shop_name ?? 'Seller'} — {money(r.amount_cents)} · {new Date(r.at).toLocaleDateString()}
                            </button>
                            <button type="button" className="admin-bell-x" title="Dismiss" onClick={(event) => { event.stopPropagation(); dismissNotif(sellerKey.pay(r)) }}>×</button>
                          </div>
                        ))}
                        {payoutRequests.length > BELL_ITEM_CAP && (
                          <button type="button" className="admin-bell-more" onClick={() => { setSellerBellOpen(false); setSecureSection('payouts'); goTab('secure') }}>+{payoutRequests.length - BELL_ITEM_CAP} more — see Payouts</button>
                        )}
                      </section>
                    )}
                    {sellerLabelRequests.length > 0 && (
                      <section>
                        <h5>Shipping labels to upload</h5>
                        {sellerLabelRequests.slice(0, BELL_ITEM_CAP).map((r) => (
                          <div className="admin-bell-row" key={`label-${r.id}`}>
                            <button type="button" className="admin-bell-item warn" onClick={() => { setSellerBellOpen(false); goTab('orders') }}>
                              🏷️ {r.shop_name ?? 'Seller'} — order #{r.order_id} · {new Date(r.at).toLocaleDateString()}
                            </button>
                            <button type="button" className="admin-bell-x" title="Dismiss" onClick={(event) => { event.stopPropagation(); dismissNotif(sellerKey.label(r)) }}>×</button>
                          </div>
                        ))}
                        {sellerLabelRequests.length > BELL_ITEM_CAP && (
                          <button type="button" className="admin-bell-more" onClick={() => { setSellerBellOpen(false); goTab('orders') }}>+{sellerLabelRequests.length - BELL_ITEM_CAP} more — see Orders</button>
                        )}
                      </section>
                    )}
                  </>
                )}
              </div>
            )}
          </div>
          <div className="admin-bell-wrap">
            <button className={`admin-close${bellShaking ? ' shaking' : ''}`} type="button" title="Notifications" aria-expanded={bellOpen} onClick={() => { setBellOpen((v) => !v); setSellerBellOpen(false) }}>
              🔔{notificationCount > 0 && <span className="admin-bell-badge">{notificationCount > 99 ? '99+' : notificationCount}</span>}
            </button>
            {bellOpen && (
              <div className="admin-bell-pop" role="menu">
                <h4>Needs attention</h4>
                {notificationCount === 0 ? <p className="muted">Nothing outstanding.</p> : (
                  <>
                    {refundsDue.length > 0 && (
                      <section>
                        <h5>Refunds to issue</h5>
                        {refundsDue.slice(0, BELL_ITEM_CAP).map((r) => (
                          <div className="admin-bell-row" key={`refund-${r.id}`}>
                            <button type="button" className="admin-bell-item warn" title={r.reason ?? ''} onClick={() => openOrderById(r.id)}>
                              ↩ Order #{r.id} — {money(r.amount_cents, r.currency)}{r.customer ? ` · ${r.customer}` : ''} · cancelled{r.cancelled_by ? ` by ${r.cancelled_by === 'admin' ? `${brandName()}` : r.cancelled_by}` : ''}
                            </button>
                          </div>
                        ))}
                        {refundsDue.length > BELL_ITEM_CAP && (
                          <button type="button" className="admin-bell-more" onClick={() => { setBellOpen(false); goTab('orders'); setStatusFilter('refund_due'); setOrdersPage(1) }}>+{refundsDue.length - BELL_ITEM_CAP} more — see Orders</button>
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
                          <button key={t.id} type="button" className="admin-bell-item" onClick={() => { setSpeakerOpen(false); setSupportToasts((cur) => cur.filter((x) => x.id !== t.id)); openSupportChat(t, ISSUE_LABELS) }}>
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
              <button type="button" className="admin-toast-body" onClick={() => { setSupportToasts((cur) => cur.filter((x) => x.id !== t.id)); openSupportChat(t, ISSUE_LABELS) }}>
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
          {PRIMARY_TABS.map((name) => (<Fragment key={name}>
            <button type="button" className={tab === name ? 'active' : ''} aria-expanded={['products', 'categories', 'shipping'].includes(name) ? (tab === name && !subnavFolded) : undefined}
              onClick={() => { if (tab === name && ['products', 'categories', 'shipping'].includes(name)) { setSubnavFolded((f) => !f) } else { setSubnavFolded(false); goTab(name) } }}>
              <span className="nav-ico" aria-hidden>{TAB_ICONS[name]}</span>
              <span className="nav-label">{TAB_LABELS[name]}</span>
              {name === 'reviews' && pendingReviews > 0 && <span className="nav-badge">{pendingReviews}</span>}
              {['products', 'categories', 'shipping'].includes(name) && <span className="nav-caret" aria-hidden>{tab === name && !subnavFolded ? '▾' : '▸'}</span>}
            </button>
            {name === 'shipping' && tab === 'shipping' && !subnavFolded && (
              <div className="admin-nav-sub">
                <button type="button" className={shipSection === 'options' ? 'active' : ''} onClick={() => setShipSection('options')}>Seller shipping &amp; labels</button>
                <button type="button" className={shipSection === 'local' ? 'active' : ''} onClick={() => setShipSection('local')}>Sellers’ own delivery (local)</button>
                <button type="button" className={shipSection === 'abroad' ? 'active' : ''} onClick={() => setShipSection('abroad')}>Selling abroad</button>
                <button type="button" className={shipSection === 'updates' ? 'active' : ''} onClick={() => setShipSection('updates')}>Order update reminders</button>
                <button type="button" className={shipSection === 'cod' ? 'active' : ''} onClick={() => setShipSection('cod')}>Seller cash on delivery</button>
                <button type="button" className={shipSection === 'own' ? 'active' : ''} onClick={() => setShipSection('own')}>{brandName()} delivery</button>
              </div>
            )}
            {name === 'secure' && tab === 'secure' && secureGate === 'unlocked' && (
              <div className="admin-nav-sub">
                {SECURE_SECTIONS.map(([key, label]) => <button key={key} type="button" className={secureSection === key ? 'active' : ''} onClick={() => setSecureSection(key)}>{label}</button>)}
              </div>
            )}
            {name === 'categories' && tab === 'categories' && !subnavFolded && (
              <div className="admin-nav-sub">
                <button type="button" className={!categoryForm && !commonDetailsOpen ? 'active' : ''} onClick={() => { setCategoryForm(null); setCommonDetailsOpen(false) }}>All categories</button>
                <button type="button" className={!categoryForm && commonDetailsOpen ? 'active' : ''} onClick={() => { setCategoryForm(null); setCommonDetailsOpen(true); scrollAdminTop() }}>Common product details</button>
                <button type="button" className={categoryForm && !categoryForm.id ? 'active nav-sub-add' : 'nav-sub-add'} onClick={() => { setCategoryForm({ ...EMPTY_CATEGORY }); scrollAdminTop() }}>+ Add new category</button>
                {categoryForm?.id && <button type="button" className="active">Editing #{categoryForm.id}</button>}
              </div>
            )}
            {name === 'products' && tab === 'products' && !subnavFolded && (
              <div className="admin-nav-sub">
                <button type="button" className={!productForm ? 'active' : ''} onClick={() => setProductForm(null)}>All products</button>
                <button type="button" className={productForm && !productForm.id ? 'active nav-sub-add' : 'nav-sub-add'} onClick={() => { if (!stores.length) loadStores(); setProductForm({ ...EMPTY_PRODUCT, category_id: categories[0]?.id ?? '' }); scrollAdminTop() }}>+ Add new product</button>
                {productForm?.id && <button type="button" className="active">Editing #{productForm.id}</button>}
              </div>
            )}
          </Fragment>))}

          {(() => { const inGroup = tab === 'pages' || tab === 'formatting'; const open = pagesExpanded; return <>
          <button type="button" className={`nav-group-toggle${inGroup ? ' active' : ''}`} aria-expanded={open} onClick={() => setPagesExpanded((v) => !v)}>
            <span className="nav-ico" aria-hidden>{'\u{1F4C4}'}</span>
            <span className="nav-label">Pages</span>
            <span className="nav-caret" aria-hidden>{open ? '▾' : '▸'}</span>
          </button>
          {open && (
            <div className="admin-nav-sub">
              <button type="button" className={tab === 'pages' && !pageForm && !pageGroupFilter ? 'active' : ''} onClick={() => { goTab('pages'); setPageForm(null); setPageGroupFilter(null) }}>All pages</button>

              {NAV_PAGE_GROUPS.map((key) => {
                const groupPages = pages.filter((p) => Array.isArray(p.menu_placements) && p.menu_placements.includes(key))
                const isOpen = !!expandedPageGroups[key]
                return (
                  <div key={key}>
                    <button type="button" className={`nav-subgroup-toggle${pageGroupFilter === key ? ' active' : ''}`} aria-expanded={isOpen} onClick={() => selectPageGroup(key)}>
                      {NAV_PAGE_LABELS[key]}<span className="nav-caret" aria-hidden>{isOpen ? '▾' : '▸'}</span>
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

      {tab === 'dashboard' && activeMarket === 'ALL' && <p className="muted admin-currency-note">Totals and charts can&rsquo;t add up different currencies — showing <b>{marketOptions.find((m) => m.code === workMarket)?.name}</b>. Pick a country in the top bar to see another.</p>}
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
                {value === 'all' ? 'All' : value === 'open' ? `Open${openOrders > 0 ? ` (${openOrders})` : ''}` : value === 'refund_due' ? `Refund due${refundsDue.length > 0 ? ` (${refundsDue.length})` : ''}` : STATUS_LABELS[value]}
              </button>
            ))}
          </div>
          {listBusy.orders && orders.length === 0 ? <Loading>Loading orders…</Loading> : orders.length === 0 ? <p className="admin-empty">No orders for this filter.</p> : (
            <div className="admin-table-wrap">
            <table className="admin-table">
              <thead><tr><th>#</th><th>Customer</th><th>Seller</th><th>Placed</th><th>Total</th><th>Payment</th><th>Delivery</th><th>Courier</th><th>Actions</th></tr></thead>
              <tbody>
                {orders.map((order) => { const feedback = orderFeedbackTone(order); return (
                  <tr key={order.id}>
                    <td><button type="button" className="link" title="View order summary" onClick={() => setOrderDetail(order)}>#{order.id}</button>{countryBadge(order.market)}</td>
                    <td className="admin-td-name">{order.user?.display_name ?? '—'}</td>
                    <td className="admin-td-name">{orderSellers(order)}</td>
                    <td>{new Date(order.created_at).toLocaleDateString()}</td>
                    <td>{money(order.total_cents, order.currency)}<span className="admin-note">{order.items?.length ?? 0} item{order.items?.length === 1 ? '' : 's'}</span></td>
                    <td>{order.payment_status === 'refund_pending' || (order.status === 'cancelled' && order.payment_status === 'paid') ? <span className="pill pill-refund_due">refund due</span> : <span className={`pill pill-${order.payment_status}`}>{order.payment_status.replace('_', ' ')}</span>}<span className="admin-note">{order.payment_method === 'cod' ? 'C.O.D.' : 'Card'}</span>{order.cancelled_by === 'rider' && <span className="admin-note" style={{ color: '#a23b28' }} title={order.cancel_reason || 'Customer refused to pay on delivery'}>Customer refused to pay</span>}</td>
                    <td className={feedback ? `admin-td-fb-${feedback}` : undefined} title={feedback ? `${feedback} feedback on this order — open it to see why` : undefined}>{STATUS_LABELS[order.status] ?? order.status}{order.status === 'cancelled' && order.cancel_reason && <span className="admin-note" title={order.cancel_reason}>{order.cancelled_by === 'customer' ? 'By customer' : order.cancelled_by === 'rider' ? 'At the door' : `By ${brandName()}`}: {order.cancel_reason}</span>}{order.store && <span className="admin-note" title={`Fulfilled by ${order.store.name}${order.store.city ? `, ${order.store.city}` : ''}`}>🏬 {order.store.name}</span>}{order.status === 'completed' && order.delivery_verified === true && <span className="admin-note" style={{ color: '#2f6d34' }} title={order.delivered_at ? `Confirmed ${new Date(order.delivered_at).toLocaleString()}` : ''}>✓ code verified</span>}{!order.rider_accepted_at && order.rider_offer_expires_at && <span className="admin-note" style={{ color: '#7a5c14' }} title={`Offered${order.delivery_partner?.name ? ` to ${order.delivery_partner.name}` : ''}, expires ${new Date(order.rider_offer_expires_at).toLocaleString()}`}>⏳ offer sent</span>}{order.rider_offer_decline_count > 0 && order.status !== 'completed' && <span className="admin-note" style={{ color: '#a23b28' }} title="Riders who declined or missed this offer">↩ declined ×{order.rider_offer_decline_count}</span>}</td>
                    <td className="admin-courier">
                      {order.delivery_method === 'seller' ? (
                        <>
                          <span className="pill pill-seller">Seller ships</span>
                          {(order.packages ?? []).filter((pk) => staleSellerUpdate(pk)).slice(0, 1).map((pk) => <span key={`stale-${pk.id}`} className="pill pill-stale" title="The seller hasn't updated this package for 2+ days">No seller update {staleSellerUpdate(pk)}d</span>)}{(order.packages ?? []).map((pk) => <span key={pk.id} className="admin-note" title={pk.tracking_detail ?? undefined}>{pk.carrier} · {pk.tracking_number} · {pk.tracking_label ?? pk.status.replace('_', ' ')}{pk.tracking_events?.[0]?.location ? ` · ${pk.tracking_events[0].location}` : ''}</span>)}
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
          {/* While a product is open, just its form — back via Products → All products (WordPress-style). */}
          {!productForm && <>
          {/* Quick filters: what still needs work, with counts. */}
          <div className="admin-filters admin-prod-quick">
            {[['all', 'All products'], ['unapproved', 'Not approved yet'], ['pending', 'Waiting for review'], ['draft', 'Drafts'], ['rejected', 'Rejected'], ['followups', 'Live — details missing'], ['deletion', 'Removal requested']].map(([value, label]) => {
              const n = value === 'all' ? null : productsMeta?.status_counts?.[value]
              return (
                <button key={value} type="button" className={productStatus === value ? 'chip active' : 'chip'} onClick={() => { setProductStatus(value); setProductsPage(1); setProductForm(null) }}>
                  {label}{n != null && <span className={`chip-count${n > 0 && value !== 'all' ? ' hot' : ''}`}>{n}</span>}
                </button>
              )
            })}
          </div>
          {/* Products are sold in their seller's country: say when the top-bar country hides some waiting for review. */}
          {productsMeta?.waiting_elsewhere > 0 && <p className="admin-elsewhere">{productsMeta.waiting_elsewhere} more {productsMeta.waiting_elsewhere === 1 ? 'product is' : 'products are'} waiting for review in other countries&rsquo; stores. <button type="button" className="link" onClick={() => switchAdminMarket('ALL')}>Show all countries</button></p>}
          <div className="admin-toolbar">
            <input className="admin-search" value={productSearch} placeholder="Search name, SKU or seller" onChange={(event) => { setProductSearch(event.target.value); setProductsPage(1); setProductForm(null) }} />
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
                  {categories.map((c) => <option key={c.id} value={c.id}>{categoryLabel(c)}</option>)}
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
            <label>Demo
              <select value={productDemo} onChange={(event) => { setProductDemo(event.target.value); setProductsPage(1) }}>
                <option value="all">All products</option>
                <option value="only">Demo only</option>
                <option value="none">Not demo</option>
                <option value="ad">Ads only</option>
              </select>
            </label>

            <button className="act" type="button" onClick={() => { if (!stores.length) loadStores(); setProductForm({ ...EMPTY_PRODUCT, category_id: categories[0]?.id ?? '' }); scrollFormIntoView('admin-product-form') }}>New product</button>
          </div>
          </>}

          {productForm && (
            <form id="admin-product-form" className="admin-form" onSubmit={saveProduct}>
              <button type="button" className="act ghost admin-back" onClick={() => setProductForm(null)}>&larr; All products</button>
              <h3>{productForm.id ? `Edit product #${productForm.id}` : 'New product'}</h3>
              <section className="admin-form-section"><h4>Product details</h4>
              <div className="admin-form-grid">
                <label>Category<b className="admin-req" title="Required"> *</b>
                  <select required value={productForm.category_id} onChange={(event) => setProductForm({ ...productForm, category_id: event.target.value })}>
                    <option value="" disabled>Choose…</option>
                    {categories.map((category) => <option key={category.id} value={category.id}>{categoryLabel(category)}</option>)}
                  </select>
                  {productForm.suggested_category_name && <p className="admin-note">Seller suggested a new category: &ldquo;{productForm.suggested_category_name}&rdquo;</p>}
                </label>
                <label>Shop
                  <select value={productForm.shop_id ?? ''} onChange={(event) => setProductForm({ ...productForm, shop_id: event.target.value })}>
                    <option value="">Sold directly by {brandName()}</option>
                    {shops.map((shop) => <option key={shop.id} value={shop.id}>{shop.name}</option>)}
                  </select>
                </label>
                {!productForm.shop_id ? (
                  <label>Sold in (country store)
                    <select value={productForm.market || workMarket} onChange={(event) => setProductForm({ ...productForm, market: event.target.value })}>
                      {marketOptions.map((m) => <option key={m.code} value={m.code}>{m.name} ({currencySymbol(m.currency)} {m.currency.toUpperCase()})</option>)}
                    </select>
                  </label>
                ) : <p className="admin-note">Sold in the seller&rsquo;s country store.</p>}
                <label>Name<b className="admin-req" title="Required"> *</b><input required value={productForm.name} onChange={(event) => setProductForm({ ...productForm, name: event.target.value })} /></label>
                <label>SKU<input value={productForm.sku ?? ''} placeholder="Auto-generated if left blank" onChange={(event) => setProductForm({ ...productForm, sku: event.target.value })} /></label>
                <label>Regular price ({currencySymbol()})<input type="number" min="0" step="0.01" placeholder="blank = not on sale" value={productForm.compare_at} onChange={(event) => setProductForm({ ...productForm, compare_at: event.target.value })} /></label>
                <label>Sale price ({currencySymbol()})<b className="admin-req" title="Required"> *</b><input required type="number" min="0" step="0.01" value={productForm.price} onChange={(event) => setProductForm({ ...productForm, price: event.target.value })} /></label>
                <label>Return window (days, max {feesForm?.max_return_days ?? 90})<input type="number" min="0" max={feesForm?.max_return_days ?? 90} placeholder={`default ${feesForm?.return_window_days ?? 30} · 0 = non-returnable`} value={productForm.return_days ?? ''} onChange={(event) => setProductForm({ ...productForm, return_days: event.target.value })} /></label>
                {productForm.per_store_stock
                  ? <label>Inventory<input type="text" value="Per store — see below" disabled title="This product tracks stock per store; the counts are in the Store stock section." /></label>
                  : <label>Inventory<input type="number" min="0" value={productForm.inventory_quantity} onChange={(event) => setProductForm({ ...productForm, inventory_quantity: event.target.value })} /></label>}
                <label className={productForm.is_active ? 'admin-check admin-check-live on' : 'admin-check admin-check-live'}><input type="checkbox" checked={productForm.is_active} onChange={(event) => setProductForm({ ...productForm, is_active: event.target.checked })} /> Active (visible in store)</label>
                <label className="admin-check"><input type="checkbox" checked={productForm.condition === 'refurbished'} onChange={(event) => setProductForm({ ...productForm, condition: event.target.checked ? 'refurbished' : '' })} /> Refurbished / second-hand (shows a &ldquo;Refurbished&rdquo; tag)</label>
              </div>
              {productForm.id && productForm.kind !== 'ad' && <LightningDeal product={productForm} path={`/admin/products/${productForm.id}/lightning`} headers={authHeaders} rules={settings?.deal_rules} onSaved={(p) => { setProductForm((f) => ({ ...f, lightning_starts_at: p.lightning_starts_at, lightning_ends_at: p.lightning_ends_at, lightning_qty: p.lightning_qty, lightning_pct: p.lightning_pct, lightning_pct_min: p.lightning_pct_min, lightning_pct_max: p.lightning_pct_max, lightning_repeat: p.lightning_repeat })); loadProducts() }} />}
              <p className="muted">Deals fill themselves: <b>Unbeatable deals</b> = the biggest discounts, spread across categories (the cut-off % follows the catalogue); <b>Exclusive offers</b> = the lowest-priced products in each country (&ldquo;Under $X&rdquo; / &ldquo;Under ₹X&rdquo;); <b>Lightning deals</b> = products put on a lightning deal, topped up with best sellers. Set the rules in Settings → Deals. They also show on category pages, filtered to that category.</p>
              {!productForm.shop_id && <div className="admin-form-grid">
                <label>Product kind
                  <select value={productForm.kind ?? 'live'} onChange={(event) => setProductForm({ ...productForm, kind: event.target.value })}>
                    <option value="live">Live product — sold here</option>
                    <option value="demo">Demo product — sample listing (show / hide all demos at once)</option>
                    <option value="ad">Ad — partner product with your affiliate link</option>
                  </select>
                </label>
                {productForm.kind === 'ad' && <>
                  <label>Partner (affiliate) link<input required type="url" maxLength="1000" placeholder="https://…" value={productForm.affiliate_url} onChange={(event) => setProductForm({ ...productForm, affiliate_url: event.target.value })} /></label>
                  <label>Partner / shop name <small className="muted">e.g. Amazon</small><input maxLength="80" value={productForm.affiliate_merchant} onChange={(event) => setProductForm({ ...productForm, affiliate_merchant: event.target.value })} /></label>
                  <p className="muted wz-wide">Shown with the other products in its category, marked &ldquo;Ad&rdquo; with a light border, with its image, name, price and description like any product. It can&rsquo;t be added to the cart: the &ldquo;View on partner&rdquo; button opens the partner link in a new tab (your store stays open). Clicks are counted in the product list. Show or hide all ads at once above the list.</p>
                </>}
              </div>}
              </section>
              <section className="admin-form-section"><h4>Images</h4>
              <label>Image<b className="admin-req" title="Required"> *</b> <span className="muted">(a product can&rsquo;t go live without a name, category, image and price)</span>
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
              </section>
              <section className="admin-form-section"><h4>Video</h4>
              <label>Video <span className="muted">(optional — plays on hover over the product image)</span>
                <div className="admin-image-field">
                  {productForm.video_url && <video src={mediaUrl(productForm.video_url)} className="admin-image-preview" muted loop onError={(event) => { event.currentTarget.style.display = 'none' }} />}
                  <input placeholder="Video URL (mp4), or upload →" value={productForm.video_url ?? ''} onChange={(event) => setProductForm({ ...productForm, video_url: event.target.value })} />
                  <input type="file" accept="video/mp4,video/webm,video/quicktime" disabled={imgBusy} onChange={(event) => { uploadVideo(event.target.files?.[0]); event.target.value = '' }} />
                  {productForm.video_url && <button type="button" className="act ghost" onClick={() => setProductForm({ ...productForm, video_url: '' })}>Clear</button>}
                </div>
              </label>
              </section>
              <section className="admin-form-section"><h4>Description</h4>
              <label>Description<textarea rows="2" value={productForm.description ?? ''} onChange={(event) => setProductForm({ ...productForm, description: event.target.value })} /></label>
              </section>

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

              {/* Last: return conditions don't apply to every product (e.g. downloads). */}
              {String(productForm.return_days ?? '') !== '0' && <ReturnPolicyFields value={productForm.return_policy} onChange={(return_policy) => setProductForm({ ...productForm, return_policy })} />}
              <div className="admin-form-actions">
                <button className="act" type="submit">Save</button>
                <button className="act ghost" type="button" onClick={() => setProductForm(null)}>Cancel</button>
              </div>
            </form>
          )}

          {!productForm && <>
          {productsMeta && (
            <div className={`admin-demo-bar${productsMeta.demos_hidden ? ' hidden' : ''}`}>
              <span><b>Demo products:</b> {productsMeta.demo_count} · {productsMeta.demos_hidden ? 'hidden from the store' : 'shown on the store'}</span>
              <button className="act" type="button" onClick={() => setDemosHidden(!productsMeta.demos_hidden)}>{productsMeta.demos_hidden ? 'Show demo products' : 'Hide demo products'}</button>
              <button className="act ghost" type="button" onClick={() => markAllDemo(true)}>Mark all listed as demo</button>
              <button className="act ghost" type="button" onClick={() => markAllDemo(false)}>Mark all listed as not demo</button>
            </div>
          )}
          {productsMeta && (productsMeta.affiliate_count > 0 || productsMeta.affiliates_hidden) && (
            <div className={`admin-demo-bar${productsMeta.affiliates_hidden ? ' hidden' : ''}`}>
              <span><b>Affiliate products:</b> {productsMeta.affiliate_count} · {productsMeta.affiliates_hidden ? 'hidden from the store' : 'shown on the store, marked “Ad”'}</span>
              <button className="act" type="button" onClick={() => setAffiliatesHidden(!productsMeta.affiliates_hidden)}>{productsMeta.affiliates_hidden ? 'Show affiliate products' : 'Hide affiliate products'}</button>
            </div>
          )}
          {listBusy.products && products.length === 0 ? <Loading>Loading products…</Loading> : products.length === 0 ? <p className="admin-empty">No products{activeMarket !== 'ALL' ? ` in ${marketOptions.find((m) => m.code === activeMarket)?.name ?? activeMarket}` : ''}.{activeMarket !== 'ALL' && marketOptions.length > 1 && <> <button type="button" className="link" onClick={() => switchAdminMarket('ALL')}>Show all countries</button></>}</p> : (
            <table className="admin-table">
              <thead><tr><th>Name</th><th>SKU</th><th>Category</th><th>Shop</th><th>Status</th><th>Price</th><th>Stock</th><th>Variants</th><th>Active</th><th>Demo / Ad</th><th></th></tr></thead>
              <tbody>
                {products.map((product) => {
                  const packs = (product.variants ?? []).filter((v) => v.is_active).length
                  return (
                  <tr key={product.id}>
                    <td>{product.name}{countryBadge(product.market)}{product.product_type === 'digital' && <span className="pill pill-digital" title="Digital download">⬇ Digital</span>}{product.condition === 'refurbished' && <span className="pill pill-digital">Refurbished</span>}{product.affiliate_url && <span className="pill pill-pending" title={`Affiliate — ${product.affiliate_url}`}>↗ Affiliate · {product.affiliate_clicks ?? 0} click{product.affiliate_clicks === 1 ? '' : 's'}</span>}{(product.followup_items ?? []).length > 0 && <span className="admin-note" title={product.followup_items.join(' ')}>⚠ seller to add {product.followup_items.length} detail{product.followup_items.length === 1 ? '' : 's'}</span>}{product.shop_id && ['pending', 'draft'].includes(product.status) && (product.followups?.later ?? []).length > 0 && <span className="admin-note" title={product.followups.later.join(' ')}>⚠ {product.followups.later.length} detail{product.followups.later.length === 1 ? '' : 's'} missing</span>}</td>
                    <td>{product.sku}</td>
                    <td>{product.category?.name ?? '—'}</td>
                    <td>{product.shop?.name ?? <span className="muted">{brandName()}</span>}</td>
                    <td><span className={`pill pill-${product.status}`}>{PRODUCT_STATUS_LABELS[product.status] ?? product.status}</span>{product.pending_changes && <span className="pill pill-pending" title={`Seller edited it${product.pending_submitted_at ? ` on ${new Date(product.pending_submitted_at).toLocaleDateString()}` : ''} — the live version keeps selling until you approve`}>Changes waiting</span>}{product.low_traffic_offers > 0 && <span className="pill pill-rejected" title="A sales boost offer is waiting for the seller">Low traffic</span>}{product.status === 'rejected' && product.rejection_reason && <p className="admin-note">{product.rejection_reason}</p>}{(product.missing_compliance ?? []).length > 0 && <p className="admin-note">Docs missing: {product.missing_compliance.join(', ')}</p>}</td>
                    <td>{packs ? `${money(Math.min(...product.variants.filter((v) => v.is_active).map((v) => v.price_cents)), MARKET_CURRENCY[product.market])}+` : <>{money(product.price_cents, MARKET_CURRENCY[product.market])}{product.compare_at_price_cents > product.price_cents && <s className="muted" style={{ marginLeft: 5 }}>{money(product.compare_at_price_cents, MARKET_CURRENCY[product.market])}</s>}</>}</td>
                    <td className={(product.effective_stock ?? product.inventory_quantity) <= 5 ? 'low' : ''}>{packs ? '—' : (product.effective_stock ?? product.inventory_quantity)}{productStore && !packs ? <span className="admin-note">at {stores.find((s) => String(s.id) === String(productStore))?.name ?? 'store'}</span> : null}</td>
                    <td>{packs || '—'}</td>
                    <td>{product.is_active ? 'Yes' : 'No'}{product.deletion_requested_at && <span className="pill pill-rejected" title={product.deletion_reason ? `Seller: ${product.deletion_reason}` : 'The seller no longer has this product'}>Removal requested</span>}{product.support_until && <span className="admin-note">Buyers covered until {new Date(product.support_until).toLocaleDateString()}</span>}</td>
                    <td>
                      <select className={`admin-demo-select${product.is_demo || product.affiliate_url ? ' on' : ''}`} value={product.affiliate_url ? 'ad' : product.is_demo ? 'demo' : 'real'} aria-label="Live, demo or ad" onChange={(event) => setProductKind(product, event.target.value)}>
                        <option value="real">Not demo</option>
                        <option value="demo">Demo</option>
                        {!product.shop_id && <option value="ad">Ad</option>}
                      </select>
                    </td>
                    <td className="admin-actions">
                      <button className="act" type="button" onClick={() => openProductEdit(product)}>Edit</button>
                      {product.product_type === 'digital' && <button className="act ghost" type="button" onClick={() => setDigitalFilesOf(product)}>Files</button>}
                      {product.shop_id && <button className="act ghost" type="button" onClick={() => setListingReview(product)}>Review listing</button>}
                      {product.shop_id && ['pending', 'draft'].includes(product.status) && <>
                        <button className="act" type="button" disabled={busyId === product.id} title={(product.followups?.later ?? []).length ? `Still missing: ${product.followups.later.join(' ')}` : undefined} onClick={() => approveProduct(product)}>{(product.followups?.later ?? []).length || product.status === 'draft' ? 'Approve anyway' : 'Approve'}</button>
                        <button className="act danger" type="button" disabled={busyId === product.id} onClick={() => rejectProduct(product)}>Reject</button>
                      </>}
                      {product.shop_id && product.status === 'approved' && product.pending_changes && <>
                        <button className="act" type="button" disabled={busyId === product.id} title="Make the seller's edit live" onClick={() => approveProduct(product)}>Approve changes</button>
                        <button className="act danger" type="button" disabled={busyId === product.id} title="Drop the edit — the live version stays" onClick={() => rejectProduct(product)}>Reject changes</button>
                      </>}
                      {product.shop_id && product.status === 'approved' && !product.pending_changes && <button className="act danger" type="button" disabled={busyId === product.id} onClick={() => rejectProduct(product)}>Reject</button>}
                      {product.shop_id && product.status === 'rejected' && <button className="act" type="button" disabled={busyId === product.id} onClick={() => approveProduct(product)}>Approve</button>}
                      {product.deletion_requested_at ? <>
                        {product.support_until ? <span className="muted" title="Past buyers are covered by returns / warranty — it can be removed after this date">Can remove after {new Date(product.support_until).toLocaleDateString()}</span> : <button className="act danger" type="button" disabled={busyId === product.id} onClick={() => decideDeletion(product, 'remove')}>Remove</button>}
                        <button className="act ghost" type="button" disabled={busyId === product.id} onClick={() => decideDeletion(product, 'decline')}>Keep</button>
                      </> : <button className="act danger" type="button" onClick={() => removeProduct(product)}>Delete</button>}
                    </td>
                  </tr>
                )})}
              </tbody>
            </table>
          )}
          <Pager page={productsMeta?.current_page ?? productsPage} pageCount={productsMeta?.last_page ?? 1} total={productsMeta?.total ?? products.length} onPage={setProductsPage} pageSize={pageSize} onPageSize={setPageSize} />
          </>}
        </section>
      )}

      {tab === 'categories' && (
        <section className="admin-panel">
          {/* While a category is open, just its form — back via Categories → All categories. */}
          {!categoryForm && <>
          <div className="admin-toolbar">
            <button className="act" type="button" onClick={() => { setCategoryForm({ ...EMPTY_CATEGORY }); scrollFormIntoView('admin-category-form') }}>New category</button>
          </div>
          </>}

          {categoryForm && (<>
            <form id="admin-category-form" className="admin-form" onSubmit={saveCategory}>
              <button type="button" className="act ghost admin-back" onClick={() => setCategoryForm(null)}>&larr; All categories</button>
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
                <label>Parent category
                  <select value={categoryForm.parent_id ?? ''} onChange={(event) => setCategoryForm({ ...categoryForm, parent_id: event.target.value })}>
                    <option value="">— None (top level) —</option>
                    {categories.filter((c) => c.id !== categoryForm.id && (c.depth ?? 0) < 2 && !(c.path ?? '').startsWith(`${categoryLabel(categories.find((x) => x.id === categoryForm.id))} ›`)).map((c) => <option key={c.id} value={c.id}>{categoryLabel(c)}</option>)}
                  </select>
                </label>
                <label>Type
                  <select value={categoryForm.parent_id ? (categories.find((c) => String(c.id) === String(categoryForm.parent_id))?.kind ?? 'physical') : (categoryForm.kind ?? 'physical')} disabled={!!categoryForm.parent_id} onChange={(event) => setCategoryForm({ ...categoryForm, kind: event.target.value })}>
                    <option value="physical">Physical products</option>
                    <option value="digital">Digital downloads</option>
                  </select>
                </label>
                <label>Slug (optional)<input value={categoryForm.slug ?? ''} onChange={(event) => setCategoryForm({ ...categoryForm, slug: event.target.value })} /></label>
                <label>Sort order<input type="number" min="0" value={categoryForm.sort_order} onChange={(event) => setCategoryForm({ ...categoryForm, sort_order: event.target.value })} /></label>
                <label className="admin-check"><input type="checkbox" checked={categoryForm.is_active} onChange={(event) => setCategoryForm({ ...categoryForm, is_active: event.target.checked })} /> Active</label>
                <label className="admin-check"><input type="checkbox" checked={categoryForm.show_on_home !== false} onChange={(event) => setCategoryForm({ ...categoryForm, show_on_home: event.target.checked })} /> Show on homepage</label>
              </div>
              <p className="muted">Up to 3 levels, e.g. Downloadable &rsaquo; Games &rsaquo; Arcade. A subcategory takes its top category&rsquo;s type: sellers see only physical categories for physical products and only digital ones for downloads. Shoppers opening a category also see its subcategories&rsquo; products. Homepage category tiles use this category&rsquo;s name, image and sort order.</p>
              <div className="admin-form-actions">
                <button className="act" type="submit">Save</button>
                <button className="act ghost" type="button" onClick={() => setCategoryForm(null)}>Cancel</button>
              </div>
            </form>
            {categoryForm.id
              ? <CategoryDetailsEditor key={categoryForm.id} categoryId={categoryForm.id} headers={jsonHeaders} onMessage={setMessage} onError={fail} />
              : <p className="muted">Save the category first, then set the product details sellers fill in for it.</p>}
          </>)}

          {!categoryForm && commonDetailsOpen && <CategoryDetailsEditor headers={jsonHeaders} onMessage={setMessage} onError={fail} />}
          {!categoryForm && !commonDetailsOpen && <>
          {listBusy.categories && categories.length === 0 ? <Loading>Loading categories…</Loading> : categories.length === 0 ? <p className="admin-empty">No categories.</p> : (
            <table className="admin-table">
              <thead><tr><th>Image</th><th>Name</th><th>Type</th><th>Slug</th><th>Products</th><th>Sort</th><th>Active</th><th>Homepage</th><th></th></tr></thead>
              <tbody>
                {pageSlice(shownCategories, categoriesPage).map((category) => (
                  <tr key={category.id}>
                    <td>{category.image_url ? <img className="admin-banner-thumb" src={mediaUrl(category.image_url)} alt="" /> : <span className="muted">—</span>}</td>
                    <td style={{ paddingLeft: 16 + (category.depth ?? 0) * 22 }}>{category.children_count > 0
                      ? <button type="button" className="cat-fold" aria-expanded={openCategories.has(category.id)} title={openCategories.has(category.id) ? 'Hide subcategories' : 'Show subcategories'} onClick={() => setOpenCategories((cur) => { const next = new Set(cur); if (next.has(category.id)) next.delete(category.id); else next.add(category.id); return next })}>{openCategories.has(category.id) ? '▾' : '▸'}</button>
                      : (category.depth ?? 0) > 0 && <span className="muted cat-fold-spacer">&#8627;</span>}{category.name}{category.children_count > 0 && <span className="admin-note">{category.children_count} subcategor{category.children_count === 1 ? 'y' : 'ies'}</span>}</td>
                    <td>{category.kind === 'digital' ? 'Digital' : 'Physical'}</td>
                    <td>{category.slug}</td>
                    <td>{category.products_count ?? 0}</td>
                    <td>{category.sort_order}</td>
                    <td>{category.is_active ? 'Yes' : 'No'}</td>
                    <td>{category.show_on_home !== false ? 'Yes' : 'No'}</td>
                    <td className="admin-actions">
                      <button className="act" type="button" onClick={() => { setCategoryForm({ id: category.id, name: category.name, slug: category.slug, image_url: category.image_url ?? '', sort_order: category.sort_order, is_active: category.is_active, show_on_home: category.show_on_home !== false, parent_id: category.parent_id ? String(category.parent_id) : '', kind: category.kind ?? 'physical' }); scrollFormIntoView('admin-category-form') }}>Edit</button>
                      {(category.depth ?? 0) < 2 && <button className="act ghost" type="button" title="Add a subcategory under this one" onClick={() => { setCategoryForm({ ...EMPTY_CATEGORY, parent_id: String(category.id), show_on_home: false }); scrollFormIntoView('admin-category-form') }}>+ Sub</button>}
                      <button className="act danger" type="button" onClick={() => removeCategory(category)}>Delete</button>
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          )}
          <Pager page={categoriesPage} pageCount={Math.max(1, Math.ceil(shownCategories.length / pageSize))} total={shownCategories.length} onPage={setCategoriesPage} pageSize={pageSize} onPageSize={setPageSize} />
          </>}
        </section>
      )}

      {tab === 'reviews' && <AdminReviews authHeaders={authHeaders} onMessage={setMessage} onPending={setPendingReviews} />}
      {tab === 'emails' && <EmailsPanel authHeaders={authHeaders} defaultMarket={workMarket} onMessage={setMessage} />}

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
                    <td>{rider.name}{countryBadge(rider.stores?.[0]?.country)}</td>
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
          <TrademarkReview authHeaders={authHeaders} jsonHeaders={jsonHeaders} viewDocument={viewKycDocument} fail={fail} />
          <DecorationReview authHeaders={authHeaders} jsonHeaders={jsonHeaders} fail={fail} />

          {listBusy.sellers && sellers.length === 0 ? <Loading>Loading applications…</Loading> : sellers.length === 0 ? <p className="admin-empty">No {sellerStatus === 'all' ? '' : SELLER_STATUS_LABELS[sellerStatus].toLowerCase() + ' '}applications{activeMarket === 'ALL' ? '' : ` in ${marketOptions.find((m) => m.code === activeMarket)?.name ?? activeMarket}`}.{Object.entries(sellersElsewhere).filter(([, n]) => n > 0).map(([code, n]) => <> {n} seller{n === 1 ? ' is' : 's are'} in {marketOptions.find((m) => m.code === code)?.name ?? code} — <button key={code} type="button" className="link" onClick={() => switchAdminMarket(code)}>switch to {(marketOptions.find((m) => m.code === code)?.currency ?? '').toUpperCase()}</button>.</>)}</p> : (
            <table className="admin-table">
              <thead><tr><th>Shop</th><th>Contact</th><th>Country</th><th>Business type</th><th>Status</th><th>Last message</th><th>Submitted</th><th></th></tr></thead>
              <tbody>
                {pageSlice(sellers, sellersPage).map((seller) => (
                  <tr key={seller.id}>
                    <td>{seller.shop?.name ?? '—'}{countryBadge(seller.shop?.market ?? seller.country)}</td>
                    <td>{seller.user?.name}<br /><span className="muted">{seller.user?.email}</span></td>
                    <td>{seller.country}</td>
                    <td>{seller.business_type}</td>
                    <td><span className={`pill pill-${seller.status}`}>{SELLER_STATUS_LABELS[seller.status] ?? seller.status}</span>{seller.reviews_pending > 0 && <span className="pill pill-pending" title="Onboarding tasks (tax, compliance, bank) waiting for review">{seller.reviews_pending} to review</span>}</td>
                    <td>{seller.last_message ? <span className="muted">{seller.last_message.is_staff ? 'You: ' : ''}{seller.last_message.body.length > 60 ? `${seller.last_message.body.slice(0, 60)}…` : seller.last_message.body}</span> : <span className="muted">—</span>}</td>
                    <td>{seller.submitted_at ? new Date(seller.submitted_at).toLocaleDateString() : '—'}</td>
                    <td className="admin-actions">
                      <button className="act" type="button" onClick={() => openSellerDetail(seller.id)}>{seller.status === 'pending' || seller.reviews_pending > 0 ? 'Review' : 'View'}</button>
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
            <span className="muted"><b>Stores are your warehouses or delivery hubs</b> — where stock is kept, riders pick up orders, and each delivery radius starts. Customers outside every active store&rsquo;s radius can browse but can&rsquo;t check out. (Your company&rsquo;s registered address for bills is in Settings &rarr; Business &amp; tax details.)</span>
          </div>

          {storeForm && (
            <form id="admin-store-form" className="admin-form" onSubmit={saveStore}>
              <h3>{storeForm.id ? `Edit store #${storeForm.id}` : 'New store (warehouse / hub)'}</h3>
              <div className="admin-form-grid">
                <label>Name<input value={storeForm.name} placeholder="Main Store" onChange={(event) => setStoreForm({ ...storeForm, name: event.target.value })} /></label>
                <label>Street<input required value={storeForm.line1} onChange={(event) => setStoreForm({ ...storeForm, line1: event.target.value })} /></label>
                <label>Line 2<input value={storeForm.line2 ?? ''} onChange={(event) => setStoreForm({ ...storeForm, line2: event.target.value })} /></label>
                <label>City<input required value={storeForm.city} onChange={(event) => setStoreForm({ ...storeForm, city: event.target.value })} /></label>
                <label>State<input required maxLength="60" value={storeForm.state} onChange={(event) => setStoreForm({ ...storeForm, state: event.target.value })} /></label>
                <label>Postal code<input required maxLength="12" value={storeForm.postal_code} onChange={(event) => setStoreForm({ ...storeForm, postal_code: event.target.value })} /></label>
                <label>Country<select value={storeForm.country || workMarket} onChange={(event) => setStoreForm({ ...storeForm, country: event.target.value })}>{marketOptions.map((m) => <option key={m.code} value={m.code}>{m.name}</option>)}</select></label>
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
              {/* Custom page URL: letters, numbers and dashes; blank = made from the title. */}
              <label className="admin-page-url-field">Page URL
                <span className="admin-url-row">
                  <span className="admin-url-prefix">{STORE_PAGE_BASE}</span>
                  <input value={pageForm.slug} maxLength={160} placeholder={toSlug(pageForm.title) || 'made-from-the-title'} readOnly={!!pageForm.id}
                    onChange={(event) => setPageForm({ ...pageForm, slug: toSlug(event.target.value, true) })}
                    onBlur={() => setPageForm((f) => ({ ...f, slug: toSlug(f.slug) }))} />
                </span>
                <span className="muted">{pageForm.id
                  ? 'A page’s URL can’t be changed once it’s created. For a different URL, create a new page with it (and delete this one if it’s no longer needed).'
                  : 'Leave blank to make it from the title. Letters, numbers and dashes only — it can’t be changed after the page is created.'}</span>
              </label>
              {/* Where the page goes (the Pages menu groups) — asked up front, not hidden in Page settings. */}
              <div className="admin-page-placement">
                <div className="admin-subhead">Add this page to<b className="admin-req" title="Required"> *</b></div>
                    <p className="muted">Where this page is tracked as belonging, for the sidebar/list here — tick every menu it's actually linked from on the live site (a page can be in more than one).</p>
                    <div className="admin-check-list">
                      {NAV_PAGE_GROUPS.map((opt) => (
                        <label className="admin-check" key={opt}>
                          <input type="checkbox" checked={pageForm.menu_placements.includes(opt)}
                            onChange={(event) => setPageForm((f) => ({
                              ...f,
                              menu_placements: event.target.checked ? [...f.menu_placements, opt] : f.menu_placements.filter((v) => v !== opt),
                            }))} /> {NAV_PAGE_LABELS[opt]}
                        </label>
                      ))}
                      <label className="admin-check"><input type="checkbox" checked={pageForm.menu_placements.length === 0 && !!pageForm.no_menu} onChange={(event) => setPageForm((f) => ({ ...f, no_menu: event.target.checked, menu_placements: event.target.checked ? [] : f.menu_placements }))} /> Not in a menu yet (only under All pages)</label>
                    </div>
                    {pageForm.menu_placements.includes('seller_footer') && (
                      <label>Sellers must read, accept and sign this page
                        <select value={pageForm.acceptance_for ?? ''} onChange={(event) => setPageForm({ ...pageForm, acceptance_for: event.target.value })}>
                          <option value="">No — for information</option>
                          <option value="selling">Yes — before they can list or update products</option>
                          <option value="international">Yes — before they can sell abroad</option>
                        </select>
                      </label>
                    )}
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
                      <label>Parent page (optional)
                        <input list="admin-page-slugs" value={pageForm.parent_slug ?? ''} placeholder="e.g. seller-services-agreement" onChange={(event) => setPageForm({ ...pageForm, parent_slug: event.target.value })} />
                        <datalist id="admin-page-slugs">{pages.filter((p) => p.slug !== pageForm.slug).map((p) => <option key={p.slug} value={p.slug}>{p.title}</option>)}</datalist>
                      </label>
                      <label>Sort order<input type="number" min="0" max="9999" value={pageForm.sort_order} onChange={(event) => setPageForm({ ...pageForm, sort_order: event.target.value })} /></label>
                      <label className="admin-check"><input type="checkbox" checked={pageForm.show_in_footer} onChange={(event) => setPageForm({ ...pageForm, show_in_footer: event.target.checked })} /> Show in footer</label>
                      <label className="admin-check"><input type="checkbox" checked={pageForm.is_published} onChange={(event) => setPageForm({ ...pageForm, is_published: event.target.checked })} /> Published</label>
                    </div>

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
                    <td className="admin-actions"><button className="act" type="button" onClick={() => openSupportChat(t, ISSUE_LABELS)}>Open</button><button className="act ghost" type="button" onClick={() => openThread(t.id)}>Details</button></td>
                  </tr>
                ))}
              </tbody>
            </table>
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

      {/* Footer settings live under Store settings (were Pages → Footer). */}
      {(tab === 'branding' || tab === 'footer') && (
        <section className="admin-panel">
          {!footerForm ? <Loading>Loading…</Loading> : (
            <form className="admin-form" onSubmit={saveFooter}>
              <h3>Footer</h3>
              <p className="muted">The storefront footer. &ldquo;Useful Links&rdquo; also lists your published content pages; the links below are added after them.</p>
              <div className="admin-form-grid">
                <label>Copyright line<input maxLength="160" value={footerForm.copyright} onChange={(event) => setFooterForm({ ...footerForm, copyright: event.target.value })} placeholder={`© {year} ${brandName()}`} /></label>
                <label>App Store URL<input value={footerForm.app_store_url} onChange={(event) => setFooterForm({ ...footerForm, app_store_url: event.target.value })} placeholder="https://apps.apple.com/…" /></label>
                <label>Google Play URL<input value={footerForm.play_store_url} onChange={(event) => setFooterForm({ ...footerForm, play_store_url: event.target.value })} placeholder="https://play.google.com/…" /></label>
              </div>
              <p className="muted"><code>{'{year}'}</code> becomes the current year and <code>{'{store}'}</code> the store name (Store settings), so renaming the store updates the footer too.</p>

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
              <p className="muted">Unlocked — locks when you open another menu or after a minute without activity. <button type="button" className="link" onClick={relockSecure}>Lock now</button></p>

              {secureSection === 'payouts' && <SellerPayouts key={adminMarket} headers={secureHeaders} onMessage={setMessage} onError={(error) => { if (/Unlock the Secure access/.test(error.message)) relockSecure(); fail(error) }} onOpenSeller={(id) => { goTab('sellers'); openSellerDetail(id) }} />}

              {secureSection === 'fees' && settings?.payout_fees && <>
                <WithdrawalFees key={`fees-${workMarket}`} settings={settings} market={workMarket} marketName={marketOptions.find((m) => m.code === workMarket)?.name ?? workMarket} currency={activeCurrency} save={(patch) => saveSetting(patch, { 'X-Secure-Access': secureToken })} onMessage={setMessage} />
                {settings.payout_notes && (
                  <form className="admin-form" key={`payout-note-${workMarket}`} onSubmit={async (event) => { event.preventDefault(); const text = new FormData(event.currentTarget).get('note'); if (await saveSetting({ payout_notes: { [workMarket]: text } }, { 'X-Secure-Access': secureToken })) setMessage('Payout note saved.') }}>
                    <h3>Payout note for sellers — {marketOptions.find((m) => m.code === workMarket)?.name ?? workMarket}</h3>
                    <p className="muted">Shown to sellers next to &ldquo;Request payout&rdquo;, with their minimum, maximum per payout and daily limit. Explain how you pay and the banks&rsquo; own limits (e.g. a maximum per transfer). Clear it to go back to the default text. Switch country in the top bar to edit another one.</p>
                    <textarea name="note" rows={3} maxLength={1000} defaultValue={settings.payout_notes[workMarket] ?? ''} />
                    <div className="admin-form-actions"><button className="act" type="submit">Save payout note</button></div>
                  </form>
                )}
              </>}

              {secureSection === 'access' && <>
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

              {sellerRateForm && settings && (
                <form className="admin-form" onSubmit={saveSellerRates}>
                  <h3>Seller commission — new vs. established sellers</h3>
                  <p className="muted">
                    Established sellers pay the commission rate under Charges ({((settings.commission_rate_bps ?? 0) / 100).toFixed(2)}% in {settings.home_market_name ?? 'the home market'}; other countries use their own rate).
                    Give sellers a different rate for their first days after approval — e.g. a lower intro rate to attract new shops.
                  </p>
                  <div className="admin-form-grid">
                    <label>New-seller commission (%)
                      <input type="number" min="0" max="100" step="0.01" value={sellerRateForm.new_pct} placeholder="Same as established" onChange={(event) => setSellerRateForm({ ...sellerRateForm, new_pct: event.target.value })} />
                    </label>
                    <label>Counts as new for (days after approval)
                      <input type="number" min="1" max="3650" step="1" value={sellerRateForm.days} onChange={(event) => setSellerRateForm({ ...sellerRateForm, days: event.target.value })} />
                    </label>
                  </div>
                  <p className="muted">Leave the new-seller rate blank to charge everyone the same. Applies to all countries, judged by each order&rsquo;s date; refunds take back the same rate the order paid.</p>
                  <div className="admin-form-actions"><button className="act" type="submit">Save seller commission</button></div>
                </form>
              )}

              <KeptRates headers={secureHeaders} onMessage={setMessage} onError={fail} />

              <SalesTaxKey settings={settings} save={(patch) => saveSetting(patch, { 'X-Secure-Access': secureToken })} />

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
                  <h3 style={{ marginTop: 18 }}>Live tracking — AfterShip</h3>
                  <p className="muted">
                    Tracks every package sellers ship with their own courier (Delhivery, Blue Dart, UPS, FedEx, USPS and hundreds more) from its tracking number,
                    and shows the courier&rsquo;s latest status to the buyer, the seller and you. {settings.courier?.tracking_api_key_set ? <b>On.</b> : <b>Off until you add an API key.</b>}
                  </p>
                  <label>AfterShip API key
                    <input type="password" autoComplete="off" value={courierForm.tracking_api_key}
                      placeholder={settings.courier?.tracking_api_key_set ? `current: ${settings.courier.tracking_api_key_hint} — leave blank to keep` : 'From AfterShip → Settings → API keys'}
                      onChange={(event) => setCourierForm({ ...courierForm, tracking_api_key: event.target.value })} />
                  </label>
                  <label>Webhook secret (optional)
                    <input type="password" autoComplete="off" value={courierForm.tracking_webhook_secret}
                      placeholder={settings.courier?.tracking_webhook_secret_set ? `current: ${settings.courier.tracking_webhook_secret_hint} — leave blank to keep` : 'From AfterShip → Settings → Webhooks'}
                      onChange={(event) => setCourierForm({ ...courierForm, tracking_webhook_secret: event.target.value })} />
                  </label>
                  {settings.courier?.tracking_webhook_url && <p className="muted">For instant updates, add this webhook URL in AfterShip (Settings → Webhooks): <code>{settings.courier.tracking_webhook_url}</code>. Without it, tracking still refreshes whenever an order is viewed.</p>}
                  <p className="muted">Secrets are stored in the database and shown afterwards only as a hint.</p>
                  <div className="admin-form-actions"><button className="act" type="submit">Save courier settings</button></div>
                </form>
              )}
              </>}
            </>
          )}
        </section>
      )}

      {(tab === 'settings' || tab === 'shipping') && (
        <section className="admin-panel">
          {!settings || !feesForm ? <Loading>Loading settings…</Loading> : (
            <>
              {tab === 'settings' && <>
              {(settings.setup_checklist ?? []).length > 0 && (() => {
                const todo = settings.setup_checklist.filter((c) => !c.ok)
                return (
                  <section className={`admin-group admin-setup${todo.length ? ' todo' : ''}`}>
                    <h3 className="admin-group-title">Setup checklist {todo.length ? <span className="pill pill-pending">{todo.length} to do</span> : <span className="pill pill-approved">All set</span>}</h3>
                    <ul className="admin-setup-list">
                      {settings.setup_checklist.map((c) => <li key={c.key} className={c.ok ? 'ok' : 'todo'}><span aria-hidden>{c.ok ? '✓' : '!'}</span> <b>{c.label}</b>{!c.ok && <small className="muted"> — {c.hint}</small>}{c.go && <button type="button" className={c.ok ? 'act ghost admin-setup-go' : 'act admin-setup-go'} onClick={() => { goTab(c.go[0]); if (c.go[1]) setShipSection(c.go[1]) }}>{c.ok ? 'Change' : 'Set up'}</button>}</li>)}
                    </ul>
                  </section>
                )
              })()}
              <section className="admin-group">
                <h3 className="admin-group-title">Selling</h3>
              <HouseShop headers={authHeaders} onMessage={setMessage} />
              <div className="admin-form">
                <h4>Deals — filled automatically</h4>
                <div className="admin-form-grid">
                  {[['lightning_hours', 'Lightning deal length (hours)'], ['lightning_min_pct', 'Lightning deal: minimum % off'], ['lightning_max', 'Lightning deals shown (topped up with best sellers)'], ['lightning_max_share', 'Most of an owner’s products on lightning deals at once (%)'], ['unbeatable_min', 'Unbeatable deals: at least this many'], ['unbeatable_max', 'Unbeatable deals shown'], ['exclusive_min', 'Exclusive offers: at least this many cheapest products'], ['exclusive_max', 'Exclusive offers shown at most']].map(([key, label]) => (
                    <label key={key}>{label}<input type="number" min="1" max="500" defaultValue={settings.deal_rules?.[key] ?? ''} onBlur={(event) => saveSetting({ deal_rules: { [key]: Math.max(1, Number(event.target.value || 1)) } })} /></label>
                  ))}
                </div>
                <p className="muted"><b>Lightning deals:</b> sellers (and you, on any product) start one from the product — a start time and units on offer; it shows with a countdown and &ldquo;% claimed&rdquo;. When fewer are running, the most bought products fill the section. <b>Unbeatable deals:</b> about a third of the products (between the at-least and most numbers) with the biggest discounts (&ldquo;compare at&rdquo; vs price), taken in turn from each category. There&rsquo;s no fixed %: the cut-off is the discount of the last product that fits, so it rises and falls with your catalogue{settings.deal_cutoffs ? ` — right now ${Object.entries(settings.deal_cutoffs).map(([c, p]) => `${c}: ${p == null ? 'no discounts yet' : `${p}%+ off`}`).join(', ')}` : ''}. Best sellers fill it only if too few products are discounted at all. <b>Exclusive offers:</b> each country&rsquo;s cheapest products in its own currency, shown as &ldquo;Under $X&rdquo; / &ldquo;Under ₹X&rdquo;. A product is in only one section; ads are never included; demo products follow the demo show/hide switch.</p>
              </div>


              </section>
              {settings.fx && <CurrencySettings fx={settings.fx} saveSetting={saveSetting} onSaved={setMessage} />}


              <div className="admin-form">
                <h3>Digital downloads</h3>
                <div className="admin-form-grid">
                  <label>Largest file a seller can upload (MB)<input type="number" min="1" max="4096" defaultValue={settings.digital_max_file_mb ?? 50} onBlur={(event) => saveSetting({ digital_max_file_mb: Math.max(1, Number(event.target.value) || 50) })} /></label>
                  <label>All uploads of one product, total (MB)<input type="number" min="1" max="20480" defaultValue={settings.digital_max_product_mb ?? 200} onBlur={(event) => saveSetting({ digital_max_product_mb: Math.max(1, Number(event.target.value) || 200) })} /></label>
                </div>
                <p className="muted">Uploaded files sit on this server&rsquo;s disk — shared hosting may object to large ZIPs. Sellers add bigger files as a download link to where they host them (Google Drive, Dropbox, their own server).</p>
              </div>

              <div className="admin-form">
                <h3>Store decoration</h3>
                <div className="admin-form-grid">
                  <label>Live products a store needs before its design shows<input type="number" min="0" max="1000" defaultValue={settings.decoration_min_products ?? 30} onBlur={(event) => saveSetting({ decoration_min_products: Number(event.target.value) || 0 })} /></label>
                  <label>Share of submitted designs to spot-check (%)<input type="number" min="0" max="100" defaultValue={Math.round((settings.decoration_spot_check_rate ?? 0.3) * 100)} onBlur={(event) => saveSetting({ decoration_spot_check_rate: Math.max(0, Math.min(100, Number(event.target.value) || 0)) / 100 })} /></label>
                </div>
                <p className="muted">Below the product minimum, shoppers see the store’s default page even if a design is live. Spot-checked designs wait on the Sellers tab until you approve them.</p>
              </div>

              <div className="admin-form">
                <h3>Countries</h3>
                <p className="muted">Countries enabled here appear as options in seller registration (business type, tax-ID format, and address labels all follow whichever country a seller picks). Enabling just one keeps the platform single-country; enabling several turns on multi-country selection everywhere that depends on it.</p>
                {/* Show / hide each country. Hidden: gone from the storefront country choice and seller sign-up;
                    shoppers there are sent to the default country. Its sellers keep their accounts, open orders
                    and payouts, and their products still show in open countries they ship to. */}
                <div className="admin-toggles">
                  {(settings.all_countries ?? []).map((country) => {
                    const activeCodes = (settings.active_countries ?? []).map((c) => c.code)
                    const shown = activeCodes.includes(country.code)
                    const isDefault = country.code === (settings.home_market ?? 'US')
                    return <label className={`admin-toggle${isDefault ? ' locked' : ''}`} key={country.code} title={isDefault ? 'The default country is always shown — pick another default first to hide it' : undefined}>
                      <input type="checkbox" role="switch" checked={shown || isDefault} disabled={isDefault} onChange={(event) => {
                        if (!event.target.checked && !window.confirm(`Hide ${country.name}? Shoppers there will see the ${marketOptions.find((m) => m.code === (settings.home_market ?? 'US'))?.name ?? 'default'} store instead, and new sellers can't sign up from ${country.name}. Its existing sellers, orders and payouts keep working.`)) return
                        const next = event.target.checked ? [...activeCodes, country.code] : activeCodes.filter((code) => code !== country.code)
                        saveSetting({ active_countries: next })
                      }} />
                      <span className="admin-toggle-track" aria-hidden><span /></span>
                      <span>{country.name} ({country.code}) <small className="muted">{isDefault ? 'Shown · default' : shown ? 'Shown' : 'Hidden'}</small></span>
                    </label>
                  })}
                </div>
                <label style={{ marginTop: 10 }}>Default country (shoppers and this console start here)
                  <select value={settings.home_market ?? 'US'} onChange={(event) => saveSetting({ home_market: event.target.value })}>
                    {marketOptions.map((m) => <option key={m.code} value={m.code}>{m.name} ({m.currency.toUpperCase()})</option>)}
                  </select>
                </label>
                <p className="muted">Every country can have its own {brandName()} stores, riders and products — pick the country in the top bar before adding them. Running only in India? Make India the default and untick the United States.</p>
              </div>

              <BusinessDetails settings={settings} save={saveSetting} onSaved={setMessage} />

              <p className="muted">Withdrawal fees and paying sellers are under <button type="button" className="link" onClick={() => { setSecureSection('fees'); goTab('secure') }}>Secure access</button>.</p>

              <p className="muted admin-currency-note">Showing charges &amp; payouts for <b>{marketOptions.find((m) => m.code === workMarket)?.name} ({activeCurrency.toUpperCase()} {currencySymbol(activeCurrency)})</b> — switch currency in the top bar.</p>
              {chargesMarket !== 'home' && (settings.markets ?? []).some((m) => m.code === chargesMarket)
                ? <MarketSettings key={chargesMarket} settings={settings} only={chargesMarket} save={saveSetting} onSaved={setMessage} />
                : <>
              <form className="admin-form" onSubmit={saveFees}>
                <h3>Marketplace commission &amp; seller payouts ({(settings.home_currency ?? 'usd').toUpperCase()} {currencySymbol(settings.home_currency)})</h3>
                <p className="muted">The platform's cut of every order line sold through a seller's shop, credited to the seller's ledger balance net of this commission. Doesn&rsquo;t apply to {brandName()}&rsquo;s own catalog.</p>
                <div className="admin-form-grid">
                  <label>Commission rate — established sellers (%)<input type="number" min="0" step="0.01" value={feesForm.commission_rate_pct} onChange={(event) => setFeesForm({ ...feesForm, commission_rate_pct: event.target.value })} /></label>
                  <label className="admin-check"><input type="checkbox" checked={!!feesForm.commission_apply_existing} onChange={(event) => setFeesForm({ ...feesForm, commission_apply_existing: event.target.checked })} /> Apply a rate change to existing sellers too{settings.kept_rate_sellers?.[settings.home_market] ? ` (${settings.kept_rate_sellers[settings.home_market]} on an older rate)` : ''} — unticked, they keep their current rate; move chosen ones later in Secure access</label>
                  <label>Minimum payout ($)<input type="number" min="0" step="0.01" value={feesForm.min_payout} onChange={(event) => setFeesForm({ ...feesForm, min_payout: event.target.value })} /></label>
                  <label>Maximum per payout ($)<input type="number" min="0" step="0.01" value={feesForm.max_payout} onChange={(event) => setFeesForm({ ...feesForm, max_payout: event.target.value })} /></label>
                  <label>Daily payout cap, all sellers ($)<input type="number" min="0" step="0.01" placeholder="0 = no cap" value={feesForm.daily_payout_cap} onChange={(event) => setFeesForm({ ...feesForm, daily_payout_cap: event.target.value })} /></label>
                  <label>Default return window (days)<input type="number" min="0" max={feesForm.max_return_days || 365} value={feesForm.return_window_days} onChange={(event) => setFeesForm({ ...feesForm, return_window_days: event.target.value })} /></label>
                  <label>Maximum return window (days)<input type="number" min="0" max="365" value={feesForm.max_return_days} onChange={(event) => setFeesForm({ ...feesForm, max_return_days: event.target.value })} /></label>
                  <label>Return pickup fee charged to seller ($)<input type="number" min="0" step="0.01" value={feesForm.return_pickup_fee} onChange={(event) => setFeesForm({ ...feesForm, return_pickup_fee: event.target.value })} /></label>
                  <label>{brandName()} label postage charged to seller ($)<input type="number" min="0" step="0.01" value={feesForm.label_postage} onChange={(event) => setFeesForm({ ...feesForm, label_postage: event.target.value })} /></label>
                </div>
                <p className="muted">A seller's balance must reach the minimum before a payout can be recorded — batches small amounts into one transfer instead of paying out per order (the norm across marketplaces). The maximum caps a single transfer (banks limit these too) — a bigger balance is paid over several. The daily cap limits the total paid to all sellers in one day, to stay inside your own account's transfer limit; 0 = no cap. Label postage is deducted per {brandName()}-bought label while the built-in test courier is used — a connected real courier charges its own rate.</p>

                <div className="admin-form-actions"><button className="act" type="submit">Save charges</button></div>
              </form>

              <section className="admin-group">
                <h3 className="admin-group-title">Checkout charges</h3>
              <form className="admin-form" onSubmit={saveFees}>
                <h3>Checkout charges ({(settings.home_currency ?? 'usd').toUpperCase()} {currencySymbol(settings.home_currency)})</h3>
                <p className="muted">Charged to <b>customers</b> at checkout and shown on their bill.</p>


                <fieldset className="admin-fieldset">
                  <legend>Other charges</legend>
                  <div className="admin-form-grid">
                    <label>Handling fee ($)<input type="number" min="0" step="0.01" value={feesForm.handling_fee} onChange={(event) => setFeesForm({ ...feesForm, handling_fee: event.target.value })} /></label>
                    <label>Small-cart fee ($)<input type="number" min="0" step="0.01" value={feesForm.small_cart_fee} onChange={(event) => setFeesForm({ ...feesForm, small_cart_fee: event.target.value })} /></label>
                    <label>…applied below ($)<input type="number" min="0" step="0.01" value={feesForm.small_cart_min} onChange={(event) => setFeesForm({ ...feesForm, small_cart_min: event.target.value })} /></label>
                    <label>Default tax rate (%)<input type="number" min="0" step="0.01" value={feesForm.tax_rate_pct} onChange={(event) => setFeesForm({ ...feesForm, tax_rate_pct: event.target.value })} /></label>
                  </div>
                </fieldset>

                <div className="admin-form-actions"><button className="act" type="submit">Save charges</button></div>
              </form>

              </section>
              {settings?.sales_tax && <SalesTaxSettings key={settings.sales_tax.mode + JSON.stringify(settings.sales_tax.states.map((x) => x.rate_bps))} settings={settings} save={saveSetting} headers={jsonHeaders} onSettings={setSettings} onMessage={setMessage} onError={fail} />}
                </>}


              </>}
              {/* Shipping (left menu): one form at a time, picked in its submenu. Same forms and saves as before. */}
              {tab === 'shipping' && <>
              {shipSection === 'options' && <>
              <div className="admin-form">
                <h4>Seller shipping options</h4>
                <label>&ldquo;{brandName()} collects &amp; delivers&rdquo; option for sellers
                  <select value={settings.nextech_pickup ?? 'available'} onChange={(event) => saveSetting({ nextech_pickup: event.target.value })}>
                    <option value="available" disabled={settings.nextech_pickup !== 'available' && !(settings.own_stores_count > 0) && !settings.courier_connected}>Available — sellers can choose it{settings.nextech_pickup !== 'available' && !(settings.own_stores_count > 0) && !settings.courier_connected ? ' (add a store with riders or connect a courier first)' : ''}</option>
                    <option value="disabled">Shown but unselectable</option>
                    <option value="hidden">Hidden</option>
                  </select>
                </label>
                <label>{brandName()} shipping labels (&ldquo;I ship, {brandName()} label&rdquo;)
                  <select value={settings.nextech_label_mode ?? 'manual'} onChange={(event) => saveSetting({ nextech_label_mode: event.target.value })}>
                    <option value="manual">Built-in — label PDF generated instantly from your templates (you can replace any)</option>
                    <option value="auto" disabled={settings.nextech_label_mode !== 'auto' && !settings.courier_connected}>Courier API — paid carrier label bought through the courier connection{!settings.courier_connected ? ' (connect a courier in Secure access first)' : ''}</option>
                  </select>
                </label>
                <p className="muted">Built-in: the seller gets a printable address label straight away (templates below). Courier API needs a real courier account connected in Secure access.</p>
                <p className="muted">Turn it off to have sellers ship their own orders (own courier or a {brandName()}-bought label), taking pickups off {brandName()}. Sellers already using it keep it for existing products, see a notice to switch, and can&rsquo;t add new products until they set up their own shipping.</p>
              </div>
              {/* The built-in label option prints from these templates — so they're here, and only for that option. */}
              {settings.nextech_label_mode !== 'auto' && <LabelTemplates authHeaders={authHeaders} onMessage={setMessage} />}
              </>}
              {shipSection === 'local' && <>
              <div className="admin-form">
                <h4>Sellers&rsquo; own delivery (local)</h4>
                <label>&ldquo;Own delivery&rdquo; option for sellers who ship themselves
                  <select value={settings.seller_local_delivery ?? 'available'} onChange={(event) => saveSetting({ seller_local_delivery: event.target.value })}>
                    <option value="available">Available — sellers can deliver nearby orders with their own delivery person</option>
                    <option value="hidden">Hidden</option>
                  </select>
                </label>
                {settings.seller_local_delivery !== 'hidden' && <label>Largest distance a seller can cover (km)
                  <input type="number" min="1" max="100" step="0.5" defaultValue={settings.seller_local_max_km ?? 25} onBlur={(event) => saveSetting({ seller_local_max_km: Number(event.target.value || 25) })} />
                </label>}
                <p className="muted">Buyers within the seller&rsquo;s distance get the seller&rsquo;s own delivery (free or the seller&rsquo;s flat fee, paid to them). There&rsquo;s no tracking number: the buyer gets a delivery code, and the seller must enter it to mark the order delivered. You see the code and every step on the order and in chat. Hidden: sellers can&rsquo;t offer it and all their orders go by courier.</p>
              </div>
              </>}
              {shipSection === 'abroad' && <>
              <div className="admin-form">
                <h4>Selling abroad</h4>
                <label className="admin-check"><input type="checkbox" checked={settings.intl_requires_approval !== false} onChange={(event) => saveSetting({ intl_requires_approval: event.target.checked })} /> Sellers must sign export terms before selling abroad</label>
                <p className="muted">The seller gives their export ID (in India the IEC, with its certificate), accepts the pages you mark &ldquo;before they can sell abroad&rdquo; (Pages → Seller policies &amp; rules) and signs a declaration that they ship only legal goods and declare them truthfully. Signing approves it straight away; you can stop any seller in Sellers → View. Sellers already shipping abroad when you switch this on keep doing so.</p>
              </div>
              </>}
              {shipSection === 'updates' && <>
              <div className="admin-form">
                <h4>Chasing sellers for order updates</h4>
                <div className="admin-form-grid">
                  {[['pack_hours', 'Remind if a new order isn’t packed after (hours)', 168], ['repeat_hours', 'Repeat the reminder every (hours) until it’s updated', 72], ['escalate_hours', 'Alert me when an order is still not shipped this long after its ship-by date (hours)', 168]].map(([key, label, max]) => (
                    <label key={key}>{label}
                      <input type="number" min="1" max={max} step="1" defaultValue={settings.seller_update_rules?.[key] ?? ''} onBlur={(event) => saveSetting({ seller_update_rules: { ...(settings.seller_update_rules ?? {}), [key]: Math.min(max, Math.max(1, Number(event.target.value || 1))) } })} />
                    </label>
                  ))}
                </div>
                <p className="muted">Sellers get an alert the moment an order arrives. After that, an hourly check emails them about each order waiting on them — not packed, due to ship, no tracking, out for delivery too long, cash not confirmed — at most once per repeat interval, and the same list shows in Seller Center. Orders still unshipped past ship-by are emailed to you once; the order shows how many reminders were sent.</p>
              </div>
              </>}
              {shipSection === 'cod' && <>
              <div className="admin-form">
                <h4>Cash on delivery on sellers&rsquo; own deliveries</h4>
                <label>Who can offer it
                  <select value={settings.seller_cod_mode ?? 'approved'} onChange={(event) => saveSetting({ seller_cod_mode: event.target.value })}>
                    <option value="approved">Each seller as I set it (Sellers &rarr; View &rarr; Cash on delivery)</option>
                    <option value="off">Off for every seller</option>
                    <option value="all">On for any seller who switches it on</option>
                  </select>
                </label>
                {settings.seller_cod_mode !== 'off' && <div className="admin-form-grid wide">
                  {marketOptions.map((m) => (
                    <label key={m.code}>Pause it when a seller owes {brandName()} more than ({currencySymbol(m.currency)}, {m.name})
                      <input type="number" min="0" step="1" defaultValue={((settings.seller_cod_max_owed?.[m.code] ?? 0) / 100).toFixed(0)} onBlur={(event) => saveSetting({ seller_cod_max_owed: { [m.code]: Math.max(0, Math.round(Number(event.target.value || 0) * 100)) } })} />
                    </label>
                  ))}
                </div>}
                <p className="muted">The seller&rsquo;s courier collects the cash and the seller keeps it; {brandName()}&rsquo;s commission and fees come out of their next orders&rsquo; earnings. Every cash order a seller keeps shows under <b>Sellers</b> in the top bar (and is emailed), with what each seller owes. Never for sellers in another country.</p>
              </div>
              </>}
              {shipSection === 'own' && <>
              <section className="admin-group">
                <h3 className="admin-group-title">{brandName()} delivery — own stores &amp; riders</h3>
                <div className="admin-form">
                  {(settings.own_stores_count ?? 0) > 0 ? <>
                    <label>How {brandName()}&rsquo;s own stock is delivered
                      <select value={settings.nextech_own_delivery ?? 'on'} onChange={(event) => saveSetting({ nextech_own_delivery: event.target.value })}>
                        <option value="on" disabled={settings.nextech_own_delivery === 'off' && !(settings.riders_count > 0)}>Own riders within each store&rsquo;s delivery radius, courier beyond it{settings.nextech_own_delivery === 'off' && !(settings.riders_count > 0) ? ' (add riders first)' : ''}</option>
                        <option value="off">Courier for every order, even nearby</option>
                      </select>
                    </label>
                    <p className="muted">Each store&rsquo;s radius is set under Stores. Outside every radius, orders go by courier (the courier connection, or one you book by hand and enter on the order).</p>
                    {settings.nextech_own_delivery !== 'off' && <><label className="admin-check">
                      <input type="checkbox" checked={settings.rider_auto_assign !== false} onChange={(event) => saveSetting({ rider_auto_assign: event.target.checked })} />
                      Auto-assign riders to orders
                    </label>
                    <p className="muted">For orders delivered from {brandName()}&rsquo;s own stores ({settings.own_stores_count} store{settings.own_stores_count === 1 ? '' : 's'}, {settings.riders_count ?? 0} rider{settings.riders_count === 1 ? '' : 's'}): when an order is ready, the nearest on-shift rider linked to its store is assigned (preferring riders with fewer active jobs); if none is eligible it waits in the pickup pool. Sellers&rsquo; own deliveries don&rsquo;t use these riders. Manage riders and stores under Riders and Stores.</p></>}
                  </> : <p className="muted">Rider auto-assign appears here once you add a {brandName()} store (Stores) and riders (Riders). It&rsquo;s only for delivering {brandName()}&rsquo;s own stock — sellers ship their own orders.</p>}
                </div>
              {/* Delivery fee customers pay when NexTech delivers (moved from Settings → charges; same save). */}
              {chargesMarket !== 'home' && (settings.markets ?? []).some((m) => m.code === chargesMarket)
                ? <MarketSettings key={`delivery-${chargesMarket}`} settings={settings} only={chargesMarket} part="delivery" save={saveSetting} onSaved={setMessage} />
                : <form className="admin-form" onSubmit={saveFees}>
                <h3>Delivery fee ({(settings.home_currency ?? 'usd').toUpperCase()} {currencySymbol(settings.home_currency)})</h3>
                <p className="muted">Charged to <b>customers</b> when {brandName()} delivers (own riders or courier) and shown on their bill.</p>
                <fieldset className="admin-fieldset">
                  <label className="admin-radio-row">Charge model
                    <span>
                      <label><input type="radio" name="delivery_mode" checked={feesForm.delivery_mode === 'fixed'} onChange={() => setFeesForm({ ...feesForm, delivery_mode: 'fixed' })} /> Fixed</label>
                      <label title={!(settings.own_stores_count > 0) ? 'Distance is measured from your stores — add a store first' : undefined}><input type="radio" name="delivery_mode" disabled={feesForm.delivery_mode !== 'distance' && !(settings.own_stores_count > 0)} checked={feesForm.delivery_mode === 'distance'} onChange={() => setFeesForm({ ...feesForm, delivery_mode: 'distance' })} /> By distance{!(settings.own_stores_count > 0) ? ' (needs a store)' : ''}</label>
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
                <div className="admin-form-actions"><button className="act" type="submit">Save delivery fee</button></div>
              </form>}
              {/* Cash on delivery on NexTech's own deliveries — riders or the courier collect it (sellers' own: Seller cash on delivery). */}
              <div className="admin-form">
                <h4>Cash on delivery — {brandName()}&rsquo;s deliveries</h4>
                <label className="admin-check">
                  <input type="checkbox" checked={!!settings.cod_enabled} onChange={(event) => saveSetting({ cod_enabled: event.target.checked })} />
                  Accept cash on delivery
                </label>
                <p className="muted">When on, customers can choose to pay with cash at checkout. Cash-on-delivery orders are confirmed immediately; mark them paid from the Orders tab once the courier collects the cash.</p>
              </div>
              {/* Rider pay belongs with own stores & riders (moved from Settings → charges; same save). */}
              {settings.nextech_own_delivery === 'off'
                ? <p className="muted">Rider pay is hidden while every order goes by courier — choose own riders above to set it.</p>
                : (settings.own_stores_count > 0 || settings.riders_count > 0)
                ? (chargesMarket !== 'home' && (settings.markets ?? []).some((m) => m.code === chargesMarket)
                  ? <MarketSettings key={`rider-${chargesMarket}`} settings={settings} only={chargesMarket} part="rider" save={saveSetting} onSaved={setMessage} />
                  : <form className="admin-form" onSubmit={saveFees}>
                <h3>Rider pay ({(settings.home_currency ?? 'usd').toUpperCase()} {currencySymbol(settings.home_currency)})</h3>
                <p className="muted">Paid by {brandName()} <b>to its riders</b> for each delivery — customers never see this. A free delivery to the customer is still paid to the rider.</p>
                <div className="admin-form-grid">
                  <label>Base pay per delivery ($)<input type="number" min="0" step="0.01" value={feesForm.rider_base_pay} onChange={(event) => setFeesForm({ ...feesForm, rider_base_pay: event.target.value })} /></label>
                  <label>Per mile ($)<input type="number" min="0" step="0.01" value={feesForm.rider_per_mile} onChange={(event) => setFeesForm({ ...feesForm, rider_per_mile: event.target.value })} /></label>
                  <label>Minimum rider payout ($)<input type="number" min="0" step="0.01" value={feesForm.rider_min_payout} onChange={(event) => setFeesForm({ ...feesForm, rider_min_payout: event.target.value })} /></label>
                  <label>Maximum per rider payout ($)<input type="number" min="0" step="0.01" value={feesForm.rider_max_payout} onChange={(event) => setFeesForm({ ...feesForm, rider_max_payout: event.target.value })} /></label>
                </div>
                <p className="muted">Each completed delivery credits the rider the base pay plus the per-mile rate for the straight-line distance from the store to the customer. Riders can request a payout once they&rsquo;re owed the minimum — COD cash they still hold is deducted first.</p>
                <div className="admin-form-actions"><button className="act" type="submit">Save rider pay</button></div>
              </form>)
                : <p className="muted">Rider pay settings appear once you add a {brandName()} store and riders.</p>}
              </section>
              </>}
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
              <button className="act ghost" type="button" style={{ marginLeft: 8 }} onClick={() => openSupportChat(thread, ISSUE_LABELS)}>Open chat</button>
            </p>
            {thread.rating != null && (
              <p className="admin-chat-rating">
                <span className="admin-review-stars">{'★'.repeat(thread.rating)}<span className="dim">{'★'.repeat(5 - thread.rating)}</span></span>
                <span className="muted"> customer rated this chat{thread.rated_at ? ` · ${new Date(thread.rated_at).toLocaleDateString()}` : ''}</span>
                {thread.rating_comment && <span className="admin-chat-rating-c">“{thread.rating_comment}”</span>}
              </p>
            )}
            <div className="admin-form admin-notes" style={{ marginTop: 12 }}>
              <h4>Internal notes</h4>
              <p className="muted">Only staff see these — never shown in the chat.</p>
              {(thread.messages ?? []).filter((m) => m.internal).map((m) => (
                <div key={m.id} className="admin-note-row">
                  <span>🔒 {m.body}<small className="muted"> · {new Date(m.created_at).toLocaleString()}</small></span>
                  <button className="act ghost" type="button" onClick={() => deleteThreadNote(m.id)}>Delete</button>
                </div>
              ))}
              <textarea rows="2" maxLength="2000" placeholder="Add a note for later…" value={noteDraft} onChange={(event) => setNoteDraft(event.target.value)} />
              <div className="admin-form-actions"><button className="act" type="button" disabled={!noteDraft.trim()} onClick={addThreadNote}>Add note</button></div>
            </div>
            {thread.order_id && !String(thread.issue_type).startsWith('seller_') && (
              thread.seller_shop
                ? <p className="muted">Seller in this chat: <b>{thread.seller_shop.name}</b></p>
                : (thread.seller_options ?? []).length > 0 && (
                  <p className="muted">Needs the seller?{' '}
                    {thread.seller_options.map((shop) => <button key={shop.id} className="act ghost" type="button" style={{ marginLeft: 6 }} onClick={() => bringInSeller(shop.id)}>Bring in {shop.name}</button>)}
                  </p>
                )
            )}

            {(thread.order?.shop_shipping ?? []).length > 0 && (
              <div className="admin-form" style={{ marginTop: 16 }}>
                <h4>Delivery status · Order #{thread.order.id}</h4>
                {thread.order.shop_shipping.map((ss) => {
                  const pks = (thread.order.packages ?? []).filter((pk) => pk.shop_id === ss.shop_id)
                  return (
                    <div key={ss.id}>
                      <p className="muted"><b>{ss.shop?.name ?? `Shop #${ss.shop_id}`}</b> · {ss.method === 'local' ? 'own delivery (local)' : ss.mode === 'label' ? `${brandName()} label` : 'own courier'} · ship by {new Date(ss.ship_by).toLocaleDateString()} · arrives {new Date(ss.deliver_from).toLocaleDateString()}–{new Date(ss.deliver_by).toLocaleDateString()}{ss.reminder_count > 0 ? ` · seller reminded ${ss.reminder_count}×` : ''}</p>
                      {pks.length === 0 ? <p className="muted">{ss.packed_at ? `Packed ${new Date(ss.packed_at).toLocaleString()} — not shipped yet` : 'Not packed yet'}{new Date(ss.ship_by) < new Date() ? ' (overdue)' : ''}</p> : pks.map((pk) => (
                        <p key={pk.id} className="muted">📦 {pk.carrier_label ?? pk.carrier} {pk.tracking_url ? <a href={pk.tracking_url} target="_blank" rel="noreferrer">{pk.tracking_number}</a> : pk.tracking_number} · <span className={`pill pill-${pk.status}`}>{pk.status.replaceAll('_', ' ')}</span>{pk.tracking_detail ? ` · ${pk.tracking_detail}` : ''}{pk.delivery_code ? ` · delivery code ${pk.delivery_code}` : ''} · updated {new Date(pk.progress_updated_at ?? pk.shipped_at).toLocaleString()}</p>
                      ))}
                    </div>
                  )
                })}
              </div>
            )}

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

      {cancelling && (
        <div className="admin-change-overlay" role="dialog" aria-modal="true" aria-label="Cancel order">
          <form className="admin-change-box" onSubmit={async (event) => {
            event.preventDefault()
            const reason = [cancelling.reason, cancelling.note.trim()].filter(Boolean).join(' — ')
            if (!reason) return
            await patchOrder(cancelling.order, { status: 'cancelled', cancel_reason: reason })
            setCancelling(null)
          }}>
            <h3>Cancel order #{cancelling.order.id}</h3>
            {['paid', 'partially_refunded'].includes(cancelling.order.payment_status) && <p className="muted">This order is paid — it&rsquo;ll show as <b>Refund due</b> (Orders filter and the 🔔 bell) until you refund it.</p>}
            <label>Reason
              <select required value={cancelling.reason} onChange={(event) => setCancelling((c) => ({ ...c, reason: event.target.value }))}>
                <option value="" disabled>Choose…</option>
                {CANCEL_REASONS.map((r) => <option key={r} value={r}>{r}</option>)}
              </select>
            </label>
            <label>Details {cancelling.reason === 'Other' ? '' : '(optional)'}<textarea rows={2} maxLength={400} required={cancelling.reason === 'Other'} value={cancelling.note} placeholder="e.g. which item is missing" onChange={(event) => setCancelling((c) => ({ ...c, note: event.target.value }))} /></label>
            <div className="admin-form-actions">
              <button className="act danger" type="submit" disabled={busyId === cancelling.order.id}>Cancel order</button>
              <button className="act ghost" type="button" onClick={() => setCancelling(null)}>Keep order</button>
            </div>
          </form>
        </div>
      )}

      {digitalFilesOf && <AdminDigitalFiles product={digitalFilesOf} authHeaders={authHeaders} onClose={() => setDigitalFilesOf(null)} />}

      {changeRequest && (
        <div className="admin-change-overlay" role="dialog" aria-modal="true" aria-label="Request changes">
          <form className="admin-change-box" onSubmit={sendChangeRequest}>
            <h3>Request changes — {changeRequest.seller.shop?.name ?? changeRequest.seller.company_name}</h3>
            <p className="muted">Tick what the seller needs to fix. They see these items highlighted in their application and get them as a message.</p>
            <div className="admin-change-items">
              {CHANGE_ITEMS.map(([key, label]) => {
                const on = key in changeRequest.picked
                return (
                  <div key={key} className={on ? 'admin-change-item on' : 'admin-change-item'}>
                    <label className="admin-check"><input type="checkbox" checked={on} onChange={(event) => setChangeRequest((c) => {
                      const picked = { ...c.picked }
                      if (event.target.checked) picked[key] = ''; else delete picked[key]
                      return { ...c, picked }
                    })} /> {label}</label>
                    {on && <input placeholder="What's wrong (optional)" maxLength={500} value={changeRequest.picked[key]} onChange={(event) => setChangeRequest((c) => ({ ...c, picked: { ...c.picked, [key]: event.target.value } }))} />}
                  </div>
                )
              })}
            </div>
            <label>Message to the seller (optional)<textarea rows={3} maxLength={2000} value={changeRequest.reason} onChange={(event) => setChangeRequest((c) => ({ ...c, reason: event.target.value }))} /></label>
            <div className="admin-form-actions">
              <button className="act" type="submit" disabled={busyId === changeRequest.seller.id || (Object.keys(changeRequest.picked).length === 0 && !changeRequest.reason.trim())}>Send back {Object.keys(changeRequest.picked).length > 0 ? `(${Object.keys(changeRequest.picked).length} item${Object.keys(changeRequest.picked).length === 1 ? '' : 's'})` : ''}</button>
              <button className="act ghost" type="button" onClick={() => setChangeRequest(null)}>Cancel</button>
            </div>
          </form>
        </div>
      )}

      {sellerDetail && (
        <div className="seller-page-overlay" role="dialog" aria-modal="true" aria-label="Seller details">
          <div className="seller-page">
            {sellerDetail.loading ? <Loading>Loading…</Loading> : (() => {
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
              const address = (parts) => parts.filter(Boolean).join(', ') || '—'
              return (
                <>
                  <header className="seller-page-head">
                    <div>
                      <button className="link" type="button" onClick={() => setSellerDetail(null)}>&larr; All sellers</button>
                      <h2>{d.shop?.name ?? d.company_name} {countryBadge(d.shop?.market ?? d.country)}</h2>
                      <p className="muted"><span className={`pill pill-${d.status}`}>{SELLER_STATUS_LABELS[d.status] ?? d.status}</span> · submitted {d.submitted_at ? new Date(d.submitted_at).toLocaleString() : '—'}{d.reviewer ? ` · reviewed by ${d.reviewer.name}${d.reviewed_at ? ` on ${new Date(d.reviewed_at).toLocaleDateString()}` : ''}` : ''}</p>
                    </div>
                    <div className="admin-form-actions">
                      <button className="act ghost" type="button" disabled={busyId === d.id} onClick={() => messageSeller(d)}>Message seller</button>
                      {(d.status === 'pending' || d.status === 'rejected') && <button className="act ghost" type="button" disabled={busyId === d.id} onClick={() => requestSellerChanges(d)}>Request changes</button>}
                      {d.status === 'pending' && <>
                        <button className="act" type="button" disabled={busyId === d.id} onClick={() => {
                          if (d.address_document_path && !d.address_verified_at && !window.confirm('Have you opened the proof of address and checked it matches the registered address?')) return
                          sellerAction(d, 'approve', { address_checked: true })
                        }}>Approve</button>
                        <button className="act danger" type="button" disabled={busyId === d.id} onClick={() => rejectSeller(d)}>Reject</button>
                      </>}
                      {d.status === 'approved' && <button className="act danger" type="button" disabled={busyId === d.id} title="Hides the shop and all its products; you can reinstate later" onClick={() => suspendSeller(d)}>Deactivate</button>}
                      {d.status !== 'removed' && <button className="act danger" type="button" disabled={busyId === d.id} title="Deletes their products and closes the shop for good" onClick={() => removeSeller(d)}>Remove seller</button>}
                      {d.status === 'suspended' && <button className="act" type="button" disabled={busyId === d.id} onClick={() => sellerAction(d, 'reinstate')}>Reinstate</button>}
                      <button className="act ghost" type="button" onClick={() => setSellerDetail(null)}>Close</button>
                    </div>
                  </header>
                  {d.rejection_reason && <p className="admin-cash-holding overdue">{d.status === 'needs_changes' ? 'Changes requested: ' : 'Reason: '}{d.rejection_reason}</p>}
                  {d.status === 'needs_changes' && <p className="muted">Waiting on the seller to edit and resubmit.</p>}

                  {d.shop && (
                    <div className="crm-stats">
                      <div><b>{money(Math.max(0, d.available_cents ?? 0) >= (d.min_payout_cents ?? 0) ? Math.max(0, d.available_cents ?? 0) : 0, d.currency)}</b><span>Available to pay out{(d.available_cents ?? 0) > 0 && (d.available_cents ?? 0) < (d.min_payout_cents ?? 0) ? ` (${money(d.available_cents, d.currency)} cleared, below minimum)` : ''}</span></div>
                      <div><b>{money(d.pending_cents ?? 0, d.currency)}</b><span>Held for returns / warranty</span></div>
                      <div><b>{money(d.balance_cents ?? 0, d.currency)}</b><span>Total balance</span></div>
                      <div><b>{money(d.min_payout_cents ?? 0, d.currency)}</b><span>Minimum payout</span></div>
                      <div><b>{d.shop.is_active ? 'Live' : 'Hidden'}</b><span>Shop</span></div>
                    </div>
                  )}

                  {d.shop && (d.intl_approval || (d.policies ?? []).length > 0) && (
                    <section className="seller-card">
                      {d.intl_approval && <>
                        <h4>International selling · <span className={`pill pill-${d.intl_approval.status === 'approved' ? 'approved' : 'rejected'}`}>{d.intl_approval.status === 'approved' ? 'Allowed' : 'Stopped'}</span></h4>
                        <p className="muted">{d.intl_rules?.id_label ?? 'Export ID'}: <b>{d.intl_approval.export_id ?? '—'}</b>{d.intl_approval.document_path && <> · <button type="button" className="link" onClick={() => viewKycDocument(d.intl_approval.document_path)}>{d.intl_rules?.document_label ?? 'Document'}</button></>}</p>
                        {d.intl_approval.signed_name && <p className="muted">Declaration signed by <b>{d.intl_approval.signed_name}</b> on {new Date(d.intl_approval.signed_at).toLocaleString()}{d.intl_approval.ip ? ` · IP ${d.intl_approval.ip}` : ''}</p>}
                        {d.intl_approval.reason && <p className="muted">Note: {d.intl_approval.reason}</p>}
                        <div className="admin-form-actions">{d.intl_approval.status === 'approved'
                          ? <button type="button" className="act danger" disabled={busyId === d.id} onClick={() => intlAction(d, 'revoke')}>Stop international selling</button>
                          : <button type="button" className="act" disabled={busyId === d.id} onClick={() => intlAction(d, 'reinstate')}>Allow again</button>}</div>
                      </>}
                      {(d.policies ?? []).length > 0 && <>
                        <h4>Policies signed</h4>
                        <ul className="admin-plain-list">
                          {d.policies.map((p) => <li key={p.slug}>{p.accepted ? '✓' : '✗'} {p.title} <span className="muted">({p.for === 'international' ? 'to sell abroad' : 'to sell'}) — {p.accepted ? `signed by ${p.accepted.signed_name}, ${new Date(p.accepted.accepted_at).toLocaleString()}` : p.outdated ? 'accepted an older version' : 'not accepted yet'}</span></li>)}
                        </ul>
                      </>}
                    </section>
                  )}

                  <div className="seller-page-grid">
                    <section className="seller-card">
                      <h4>Business</h4>
                      <dl className="admin-dl">
                        <div><dt>Company</dt><dd>{d.company_name}</dd></div>
                        <div><dt>Type</dt><dd>{d.business_type}</dd></div>
                        <div><dt>Country</dt><dd>{d.country}</dd></div>
                        <div><dt>Tax ID</dt><dd>{d.tax_id}</dd></div>
                        <div><dt>Registered address</dt><dd>{address([d.registered_line1, d.registered_line2, d.registered_city, d.registered_state, d.registered_postal_code, d.registered_country])}</dd></div>
                      </dl>
                    </section>

                    <section className="seller-card">
                      <h4>Tax information</h4>
                      <dl className="admin-dl">
                        <div><dt>Registered tax ID</dt><dd>{d.tax_id || '—'}</dd></div>
                        <div><dt>{d.country === 'IN' ? 'GSTIN / PAN' : 'Tax number'}</dt><dd>{d.tax_info?.tax_number || 'Not added yet'}</dd></div>
                        {d.tax_info?.enrolment_number && <div><dt>GST enrolment</dt><dd>{d.tax_info.enrolment_number}</dd></div>}
                        {d.tax_info?.tax_code && <div><dt>Default item tax code</dt><dd>{d.tax_codes?.[d.tax_info.tax_code] ?? d.tax_info.tax_code}</dd></div>}
                        <div><dt>Status</dt><dd>{d.tax_status ? STATUS_TEXT[d.tax_status] : d.tax_info?.tax_number ? 'Step 2 not done' : 'Not started'}{d.tax_submitted_at ? ` · submitted ${new Date(d.tax_submitted_at).toLocaleDateString()}` : ''}</dd></div>
                        {d.tax_info?.terms_accepted_at && <div><dt>Tax terms accepted</dt><dd>{new Date(d.tax_info.terms_accepted_at).toLocaleString()}</dd></div>}
                      </dl>
                      {d.tax_info?.certificate_path && <div className="admin-form-actions"><button className="act ghost" type="button" onClick={() => viewKycDocument(d.tax_info.certificate_path)}>Tax certificate{d.tax_info.certificate_name ? ` (${d.tax_info.certificate_name})` : ''}</button></div>}
                    </section>

                    <section className="seller-card">
                      <h4>Seller &amp; contact</h4>
                      <dl className="admin-dl">
                        <div><dt>Name</dt><dd>{d.contact_name}</dd></div>
                        <div><dt>Email</dt><dd>{d.user?.email}</dd></div>
                        <div><dt>ID</dt><dd>{SELLER_ID_TYPE_LABELS[d.id_type] ?? d.id_type}: {d.id_number}</dd></div>
                        <div><dt>Date of birth</dt><dd>{String(d.date_of_birth ?? '').slice(0, 10)}</dd></div>
                        <div><dt>Pickup</dt><dd>{d.pickup_phone}{d.pickup_phone ? ' · ' : ''}{d.pickup_same_as_registered ? 'Same as registered address' : address([d.pickup_line1, d.pickup_line2, d.pickup_city, d.pickup_state, d.pickup_postal_code, d.pickup_country])}</dd></div>
                      </dl>
                      <div className="admin-form-actions">
                        <button className="act ghost" type="button" onClick={() => viewKycDocument(d.id_document_path)}>ID document</button>
                        <button className="act ghost" type="button" onClick={() => viewKycDocument(d.business_document_path)}>Business document</button>
                        {d.address_document_path ? <button className="act ghost" type="button" onClick={() => viewKycDocument(d.address_document_path)}>Proof of address</button> : <span className="muted">No proof of address (applied before it was required)</span>}
                      </div>
                      {d.address_verified_at && <p className="muted">✓ Registered address checked {new Date(d.address_verified_at).toLocaleDateString()} — locked; it changes only when you request it.</p>}
                      {(d.registered_history ?? []).length > 0 && <p className="muted">Earlier addresses: {d.registered_history.map((h, i) => <span key={i}>{i > 0 && '; '}{[h.registered_line1, h.registered_line2, h.registered_city, h.registered_state, h.registered_postal_code, h.registered_country].filter(Boolean).join(', ')} (until {new Date(h.replaced_at).toLocaleDateString()})</span>)}</p>}
                    </section>

                    {d.shop && (
                      <section className="seller-card">
                        <h4>Shop &amp; shipping</h4>
                        <dl className="admin-dl">
                          <div><dt>Shop</dt><dd>{d.shop.name} ({d.shop.is_active ? 'live' : 'hidden'})</dd></div>
                          {d.cod && <div><dt>Cash on delivery</dt><dd>
                            {d.cod.mode === 'off' ? 'Off for all sellers (Settings → Seller shipping)' : d.cod.mode === 'all' ? `Allowed for all sellers — ${d.cod.accepts ? 'on in their shop' : 'they haven’t switched it on'}` : d.cod.approved ? `Allowed — ${d.cod.accepts ? 'on in their shop' : 'they haven’t switched it on'}` : 'Not allowed'}
                            {d.cod.owed_cents > 0 && <span className="admin-note" style={{ color: '#a23b28' }}>Owes {brandName()} {money(d.cod.owed_cents, d.currency)}{d.cod.owed_cents > d.cod.max_owed_cents ? ' — over the limit, paused' : ''}</span>}
                            {d.cod.mode === 'approved' && <button className={d.cod.approved ? 'act ghost' : 'act'} type="button" disabled={busyId === d.id} style={{ marginLeft: 8 }} onClick={() => { if (d.cod.approved || window.confirm(`Allow cash on delivery for ${d.shop.name}? They keep the cash; ${brandName()}’s share comes out of their next orders’ earnings.`)) sellerAction(d, 'cod', { approved: !d.cod.approved }) }}>{d.cod.approved ? 'Stop cash on delivery' : 'Allow cash on delivery'}</button>}
                          </dd></div>}
                          <div><dt>Shipping</dt><dd>{{ nextech: `${brandName()} collects & delivers this seller’s orders`, self: 'Ships with their own courier (tracking entered in Seller Center)', label: `Ships on ${brandName()}-bought labels (postage deducted from earnings)` }[d.shop.fulfillment_mode ?? 'nextech']}</dd></div>
                        </dl>
                      </section>
                    )}

                    {d.shop && d.requirement_rules && (
                      <section className="seller-card">
                        <h4>Requirements</h4>
                        <p className="muted">Extra rules for this seller — off by default. Listing checks, shipping setup, the onboarding checklist and the store-design minimum always apply.</p>
                        <div className="seller-switches">
                          {Object.entries(d.requirement_rules).map(([key, [label, help]]) => (
                            <label className="seller-switch" key={key}>
                              <input type="checkbox" checked={!!d.requirements?.[key]} disabled={busyId === d.id} onChange={(event) => sellerAction(d, 'requirements', { [key]: event.target.checked })} />
                              <span><b>{label}</b><small className="muted">{help}</small></span>
                            </label>
                          ))}
                        </div>
                      </section>
                    )}

                    {d.status === 'approved' && (
                      <section className="seller-card wide">
                        <h4>Onboarding tasks</h4>
                        <div className="seller-tasks">
                          <div>
                            <p><b>1. Tax information</b> — {d.tax_status ? STATUS_TEXT[d.tax_status] : d.tax_info?.tax_number ? 'Step 2 not done' : 'Not started'}{d.tax_submitted_at ? ` · submitted ${new Date(d.tax_submitted_at).toLocaleDateString()}` : ''}</p>
                            {d.tax_note && <p className="muted">“{d.tax_note}”</p>}
                            {d.tax_info?.tax_number && <p className="muted">Tax number {d.tax_info.tax_number} (registered: {d.tax_id}){d.tax_info.enrolment_number ? ` · GST enrolment ${d.tax_info.enrolment_number}` : ''}{d.tax_info.tax_code ? ` · default item tax code: ${d.tax_codes?.[d.tax_info.tax_code] ?? d.tax_info.tax_code}` : ''}{d.tax_info.certificate_path && <> · <button className="link" type="button" onClick={() => viewKycDocument(d.tax_info.certificate_path)}>View certificate</button></>}</p>}
                            {reviewButtons('tax', d.tax_status, d.tax_status === 'pending')}
                          </div>
                          <div>
                            <p><b>2. Compliance information</b> — {d.compliance_status ? STATUS_TEXT[d.compliance_status] : 'Not started'}{d.compliance_submitted_at ? ` · submitted ${new Date(d.compliance_submitted_at).toLocaleDateString()}` : ''}</p>
                            {d.compliance_note && <p className="muted">“{d.compliance_note}”</p>}
                            {people.map((p) => (
                              <p className="muted" key={p.id}>{p.legal_name}{p.is_primary ? ' (primary contact)' : ''} — {p.roles.map((r) => roleNames[r]).join(', ')}{p.ownership_pct != null ? ` · ${p.ownership_pct}% owned` : ''} · born {p.date_of_birth} in {p.place_of_birth} · citizen of {p.citizenship} · {p.id_type} {p.id_number} ({p.id_country}, expires {p.id_expiry}) · {address([p.address?.line1, p.address?.line2, p.address?.city, p.address?.state, p.address?.postal_code, p.address?.country])}</p>
                            ))}
                            {(d.compliance?.documents ?? []).length > 0 && (
                              <div className="admin-form-actions">
                                {d.compliance.documents.map((doc) => <button key={doc.path} className="act ghost" type="button" onClick={() => viewKycDocument(doc.path)}>{d.corporate_document_types?.[doc.type] ?? doc.type}</button>)}
                              </div>
                            )}
                            {reviewButtons('compliance', d.compliance_status, d.compliance_status === 'pending')}
                          </div>
                          <div>
                            <p><b>3. Bank account</b> — {d.bank_status ? STATUS_TEXT[d.bank_status] : 'Not started'}{d.bank_submitted_at ? ` · submitted ${new Date(d.bank_submitted_at).toLocaleDateString()}` : ''}</p>
                            {d.bank_note && <p className="muted">“{d.bank_note}”</p>}
                            {d.payout_details?.document_path && <p className="muted">Check the bank document matches: holder, {d.payout_details.bank_code_label ?? 'routing number'} and account number, issued {d.payout_details.document_issued_on} (must be within 180 days). <button className="link" type="button" onClick={() => viewKycDocument(d.payout_details.document_path)}>View bank document</button></p>}
                            {reviewButtons('bank', d.bank_status, d.bank_status === 'processing')}
                          </div>
                        </div>
                      </section>
                    )}

                    {d.shop && (
                      <section className="seller-card wide">
                        <h4>Payouts</h4>
                        {d.pending_payout_request && <p className="admin-payout-request">💸 Seller requested <strong>{money(d.pending_payout_request.amount_cents, d.currency)}</strong> on {new Date(d.pending_payout_request.created_at).toLocaleDateString()}. Pay or decline it in Secure access → Payouts.</p>}
                        <p className="muted">{d.payout_method
                          ? (d.payout_method === 'bank' ? <>Bank transfer — {d.payout_details?.holder_name}, {d.payout_details?.bank_name}, acct {d.payout_details?.account_number} · {d.payout_details?.bank_code_label ?? 'routing'} {d.payout_details?.routing_number}</> : d.payout_method === 'stripe' ? <>Stripe — {d.stripe_account_id ?? 'not set up'}{d.stripe_ready ? '' : ' (setup not finished)'}</> : <>PayPal — {d.payout_details?.paypal_email ?? d.payout_details?.email}</>)
                          : 'No payout method on file yet.'}
                          {d.payout_details?.payout_currency && <> · wants payment in <b>{d.payout_details.payout_currency.toUpperCase()}</b>{d.payout_details.currency_confirmed_at ? ` (seller confirmed their account accepts it on ${new Date(d.payout_details.currency_confirmed_at).toLocaleDateString()})` : ''}</>}
                          {d.payout_method ? ` · The withdrawal fee for this method is deducted automatically (Secure access → Withdrawal fees)` : ''}{d.max_payout_cents > 0 ? ` · Max per payout: ${money(d.max_payout_cents, d.currency)}` : ''}{d.daily_payout_remaining_cents != null ? ` · ${money(d.daily_payout_remaining_cents, d.currency)} left today (all sellers)` : ''}</p>
                        {(d.pending_orders ?? []).length > 0 && <p className="muted">Held: {d.pending_orders.map((p) => (p.order_missing ? `${money(p.amount_cents, d.currency)} sale credit with no order on record (held — check the ledger)` : `#${p.order_id} ${money(p.amount_cents, d.currency)} ${p.releases_at ? `→ ${new Date(p.releases_at).toLocaleDateString()}` : '(not delivered)'}`)).join(' · ')}</p>}
                        {d.status === 'approved' && (
                          <div className="admin-form-actions">
                            {((d.available_cents ?? 0) > 0 || d.pending_payout_request) && <button className="act" type="button" onClick={() => { setSecureSection('payouts'); goTab('secure') }}>Pay in Secure access → Payouts</button>}
                            {(d.available_cents ?? 0) < (d.min_payout_cents ?? 0) && <span className="muted">Available balance is below the {money(d.min_payout_cents ?? 0, d.currency)} minimum — earnings still inside their return window can&rsquo;t be paid out yet.</span>}
                          </div>
                        )}
                        <SellerLedgerTable sellerId={d.id} authHeaders={authHeaders} labels={LEDGER_TYPE_LABELS} />
                      </section>
                    )}
                  </div>
                </>
              )
            })()}
          </div>
        </div>
      )}

      <AdminChatDock authHeaders={authHeaders} issueLabels={ISSUE_LABELS} onSupportDetails={openThread} />

      {listingReview && (
        <ListingReview
          product={listingReview}
          currency={MARKET_CURRENCY[listingReview.market]}
          authHeaders={authHeaders}
          jsonHeaders={jsonHeaders}
          viewDocument={viewKycDocument}
          busy={busyId === listingReview.id}
          fail={fail}
          onClose={() => setListingReview(null)}
          onAction={(action) => (action === 'approve' ? approveProduct(listingReview) : rejectProduct(listingReview))}
        />
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

              {(() => {
                // A buyer asked to ship somewhere else before the order left.
                const change = (orderDetail.address_changes ?? o.address_changes ?? []).find((c) => c.status === 'pending')
                if (!change) return null
                const a = change.address ?? {}
                const decide = async (decision) => {
                  const note = decision === 'decline' ? window.prompt('Why can’t the address be changed? The buyer sees this.', '') : null
                  if (decision === 'decline' && !note) return
                  try {
                    const response = await fetch(`${API_URL}/admin/orders/${o.id}/address-change/${change.id}`, { method: 'POST', headers: jsonHeaders(), body: JSON.stringify({ decision, note }) })
                    const data = await readJson(response)
                    if (!response.ok) throw new Error(data.message ?? Object.values(data.errors ?? {})[0]?.[0] ?? 'Could not update the request.')
                    setOrderDetail(data.data)
                    setOrders((current) => current.map((x) => (x.id === o.id ? { ...x, ...data.data } : x)))
                  } catch (error) { fail(error) }
                }
                return (
                  <div className="admin-payout-request">
                    <p><strong>Buyer asked to change the shipping address</strong> ({new Date(change.created_at).toLocaleString()})</p>
                    <p className="muted">New: {[a.name, a.line1, a.line2, [a.city, a.state, a.postal_code].filter(Boolean).join(', ')].filter(Boolean).join(' · ')}</p>
                    {(o.items ?? []).some((i) => i.fulfilled_by === 'seller') && <p className="muted">The seller shipping it can also accept or decline this in their Seller Center.</p>}
                    <div className="admin-form-actions">
                      <button className="act" type="button" onClick={() => decide('approve')}>Accept new address</button>
                      <button className="act ghost" type="button" onClick={() => decide('decline')}>Decline</button>
                    </div>
                  </div>
                )
              })()}

              <h4>Items ({o.items?.length ?? 0})</h4>
              <table className="admin-table admin-order-items">
                <thead><tr><th>Item</th><th>Qty</th><th>Unit</th><th>Total</th></tr></thead>
                <tbody>
                  {(o.items ?? []).map((it) => (
                    <tr key={it.id}>
                      <td>{it.product_name}{it.variant_label && <span className="admin-note">{it.variant_label}</span>}<PersonalizationView value={it.personalization} download /></td>
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
                        <p><b>Shipped by {ss.shop?.name ?? `shop #${ss.shop_id}`}</b> · {ss.method === 'local' ? 'seller’s own delivery (local)' : ss.mode === 'label' ? `${brandName()} label` : 'own courier'} · shipping {ss.free_shipping ? 'free (seller covers)' : money(ss.fee_cents, o.currency)} · ship by {new Date(ss.ship_by).toLocaleDateString()} · arrives {new Date(ss.deliver_from).toLocaleDateString()}–{new Date(ss.deliver_by).toLocaleDateString()}</p>
                        {pks.length === 0 && <p className="muted">Not shipped yet{ss.packed_at ? ` · packed ${new Date(ss.packed_at).toLocaleString()}` : ' · not packed'}{new Date(ss.ship_by) < new Date() && o.status !== 'cancelled' ? ' — overdue' : ''}.</p>}
                        {ss.reminder_count > 0 && <p className="muted">Seller reminded {ss.reminder_count}× · last {new Date(ss.reminded_at).toLocaleString()}{ss.escalated_at ? ' · flagged overdue to admin' : ''}</p>}
                        {pks.map((pk) => (
                          <div key={pk.id} className="muted admin-pkg">
                            📦 {pk.carrier_label ?? pk.carrier} {pk.tracking_url ? <a href={pk.tracking_url} target="_blank" rel="noreferrer">{pk.tracking_number}</a> : pk.tracking_number}
                            {' · '}<span className={`pill pill-${pk.status}`}>{pk.status.replace('_', ' ')}</span>
                            {' · '}{(pk.items ?? []).reduce((n, it) => n + it.quantity, 0)} item(s) · shipped {new Date(pk.shipped_at).toLocaleDateString()}{pk.edit_count ? ` · tracking edited ${pk.edit_count}×` : ''}{pk.delivery_code ? ` · buyer's delivery code ${pk.delivery_code}` : ''}
                            <PackageProgress pkg={pk} packedAt={ss.packed_at} cod={o.payment_method === 'cod'} />
                            <TrackingTimeline pkg={pk} />
                            {pk.has_label_file && <>{' '}<button type="button" className="link" onClick={() => downloadPackageLabel(pk)}>Label file</button></>}
                            {' '}<button type="button" className="link" disabled={busyId === o.id} onClick={() => editPackageTracking(o, pk)}>Edit tracking</button>
                            {pk.status !== 'delivered' && <button type="button" className="link" disabled={busyId === o.id} onClick={() => patchPackage(o, pk, { status: 'delivered' })}> Mark delivered</button>}
                            {!['lost', 'delivered'].includes(pk.status) && <button type="button" className="link" disabled={busyId === o.id} onClick={() => { if (window.confirm('Mark this package as lost?')) patchPackage(o, pk, { status: 'lost' }) }}> Lost</button>}
                          </div>
                        ))}
                      </div>
                    )
                  })}
                </div>
              )}
              {o.delivery_method === 'online_courier' && (
                o.shipment ? (
                  <p className="muted">{o.shipment.carrier} · Tracking {o.shipment.tracking_url ? <a href={o.shipment.tracking_url} target="_blank" rel="noreferrer">{o.shipment.tracking_number}</a> : o.shipment.tracking_number} · <span className={`pill pill-${o.shipment.status}`}>{o.shipment.status.replace('_', ' ')}</span>{o.shipment.provider === 'manual' ? ' · booked by hand' : ''}
                    {o.status !== 'completed' && o.status !== 'cancelled' && o.shipment.status !== 'delivered' && o.shipment.provider !== 'manual' && (
                      <button type="button" className="link" disabled={busyId === o.id} onClick={() => syncTracking(o)}> Sync tracking</button>
                    )}
                  </p>
                ) : <p className="muted">Online courier — awaiting booking.</p>
              )}
              {(o.items ?? []).some((it) => it.fulfilled_by === 'nextech') && !['pending_payment', 'cancelled', 'completed'].includes(o.status) && !(o.delivery_partner_id && o.status === 'out_for_delivery') && (
                <ManualCourier order={o} carriers={settings?.carriers?.[o.market] ?? []} headers={jsonHeaders} onDone={(msg) => { setMessage(msg); openOrderById(o.id) }} />
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
