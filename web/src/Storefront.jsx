import { useEffect, useMemo, useRef, useState } from 'react'
import { CardElement, Elements, useElements, useStripe } from '@stripe/react-stripe-js'
import { loadStripe } from '@stripe/stripe-js'
import { renderMarkdown } from './markdown'
import { mediaUrl } from './mediaUrl'
import { ChatPhotoPicker, ChatPhotos } from './ChatPhotos'
import { PageSection } from './PageSections'
import { flag, setStoreCurrency, storeMoney } from './money'
import './StorefrontBase.css'
import './Storefront.css'
import './Checkout.css'

const API_URL = import.meta.env.VITE_API_URL ?? 'http://127.0.0.1:8000/api'
const FEEDBACK_OPTIONS = [
  { value: 1, label: 'Very poor' },
  { value: 2, label: 'Poor' },
  { value: 3, label: 'Fair' },
  { value: 4, label: 'Good' },
  { value: 5, label: 'Excellent' },
]
const REVIEW_NAMES = ['Alex P.', 'Jordan K.', 'Sam R.', 'Taylor M.', 'Morgan D.', 'Casey L.', 'Riley B.', 'Jamie S.', 'Drew C.', 'Avery N.', 'Quinn T.', 'Reese W.']
const REVIEW_TEXTS = {
  5: ['Exactly as described, works great and arrived quickly.', 'Really happy with this purchase, would buy again.', 'Great quality for the price, highly recommend.', 'Exceeded my expectations, five stars.'],
  4: ['Good product overall, does what it says.', 'Solid value, a couple of minor nitpicks but happy with it.', 'Works well, packaging could be better.', 'Pretty good, would consider buying again.'],
  3: ["It's okay, does the job but nothing special.", 'Average quality, expected a bit more for the price.', 'Works fine but instructions were unclear.', 'Decent, though delivery took longer than expected.'],
}
// Deterministic per-product review placeholders — same product always renders
// the same reviews, no backend review table needed for this display-only list.
function generateReviews(product) {
  let seed = (Number(product.id) * 2654435761) >>> 0
  const rand = () => { seed = (seed * 1664525 + 1013904223) >>> 0; return seed / 4294967296 }
  const base = Number(product.rating_avg) || 4.5
  const count = product.rating_count > 0 ? 8 : 4
  return Array.from({ length: count }, (_, i) => {
    const wobble = Math.round((rand() - 0.5) * 2)
    const rating = Math.max(3, Math.min(5, Math.round(base) + wobble))
    const daysAgo = 3 + Math.floor(rand() * 180)
    const date = new Date(Date.now() - daysAgo * 86400000)
    const texts = REVIEW_TEXTS[rating] ?? REVIEW_TEXTS[3]
    return {
      id: i,
      name: REVIEW_NAMES[Math.floor(rand() * REVIEW_NAMES.length)],
      rating,
      verified: rand() > 0.15,
      date: date.toLocaleDateString(undefined, { year: 'numeric', month: 'short', day: 'numeric' }),
      text: texts[Math.floor(rand() * texts.length)],
    }
  })
}
// The publishable key comes from GET /api/config (backend .env or admin
// Settings → Payments); VITE_STRIPE_PUBLISHABLE_KEY is only a local fallback.
// loadStripe must run once per key, so promises are cached.
const stripePromises = {}
const stripeFor = (key) => (key ? (stripePromises[key] ??= loadStripe(key)) : null)
const fallbackProducts = [
  { id: 1, name: 'Apple iPhone 15 Pro', price_cents: 99900, category: { name: 'Mobiles & Smartphones' }, image_url: '/img/products/1.webp' },
  { id: 2, name: 'Samsung Galaxy S24', price_cents: 79900, category: { name: 'Mobiles & Smartphones' }, image_url: '/img/products/2.webp' },
  { id: 3, name: 'Apple MacBook Air M3', price_cents: 109900, category: { name: 'Laptops & Computers' }, image_url: '/img/products/6.webp' },
  { id: 4, name: 'Dell XPS 13', price_cents: 99900, category: { name: 'Laptops & Computers' }, image_url: '/img/products/7.webp' },
  { id: 5, name: 'Sony WH-1000XM5', price_cents: 34900, category: { name: 'Audio & Headphones' }, image_url: '/img/products/11.jpg' },
  { id: 6, name: 'Apple AirPods Pro 2', price_cents: 24900, category: { name: 'Audio & Headphones' }, image_url: '/img/products/12.webp' },
]
const categoriesFallback = [
  { id: 1, name: 'Mobiles & Smartphones', image_url: '/img/cat/mobiles-smartphones.webp' },
  { id: 2, name: 'Laptops & Computers', image_url: '/img/cat/laptops-computers.webp' },
  { id: 3, name: 'Audio & Headphones', image_url: '/img/cat/audio-headphones.jpg' },
]

// Amounts in the shopper's selected market's currency (or an order's own).
function price(cents, currency) { return storeMoney(cents, currency) }

// How to title a chosen option. If the variant label already carries the
// product identity ("Large Spinach") show it alone; if it's just an attribute
// ("Green", "1 kg") keep the product name for context ("Baby Spinach · Green").
function variantTitle(productName, variantLabel) {
  const name = productName ?? ''
  if (!variantLabel) return name
  const words = name.toLowerCase().split(/\s+/).filter((w) => w.length > 2)
  const label = variantLabel.toLowerCase()
  return words.some((w) => label.includes(w)) ? variantLabel : `${productName} · ${variantLabel}`
}

// [key, label, SVG path (24x24)] — rendered in the footer when a URL is set.
const FOOTER_SOCIALS = [
  ['facebook', 'Facebook', 'M9.198 21.5h4v-8.01h3.604l.396-3.98h-4V7.5a1 1 0 0 1 1-1h3v-4h-3a5 5 0 0 0-5 5v2.01h-2l-.396 3.98h2.396v8.01Z'],
  ['x', 'X', 'M18.244 2.25h3.308l-7.227 8.26 8.502 11.24H16.17l-5.214-6.817L4.99 21.75H1.68l7.73-8.835L1.254 2.25H8.08l4.713 6.231 5.45-6.231Zm-1.161 17.52h1.833L7.084 4.126H5.117L17.083 19.77Z'],
  ['instagram', 'Instagram', 'M12 2.163c3.204 0 3.584.012 4.85.07 3.252.148 4.771 1.691 4.919 4.919.058 1.265.069 1.645.069 4.849 0 3.205-.012 3.584-.069 4.849-.149 3.225-1.664 4.771-4.919 4.919-1.266.058-1.644.07-4.85.07-3.204 0-3.584-.012-4.849-.07-3.26-.149-4.771-1.699-4.919-4.92-.058-1.265-.07-1.644-.07-4.849 0-3.204.013-3.583.07-4.849.149-3.227 1.664-4.771 4.919-4.919 1.266-.057 1.645-.069 4.849-.069ZM12 0C8.741 0 8.333.014 7.053.072 2.695.272.273 2.69.073 7.052.014 8.333 0 8.741 0 12c0 3.259.014 3.668.072 4.948.2 4.358 2.618 6.78 6.98 6.98C8.333 23.986 8.741 24 12 24c3.259 0 3.668-.014 4.948-.072 4.354-.2 6.782-2.618 6.979-6.98.059-1.28.073-1.689.073-4.948 0-3.259-.014-3.667-.072-4.947-.196-4.354-2.617-6.78-6.979-6.98C15.668.014 15.259 0 12 0Zm0 5.838a6.162 6.162 0 1 0 0 12.324 6.162 6.162 0 0 0 0-12.324ZM12 16a4 4 0 1 1 0-8 4 4 0 0 1 0 8Zm6.406-11.845a1.44 1.44 0 1 0 0 2.881 1.44 1.44 0 0 0 0-2.881Z'],
  ['linkedin', 'LinkedIn', 'M20.447 20.452h-3.554v-5.569c0-1.328-.027-3.037-1.852-3.037-1.853 0-2.136 1.445-2.136 2.939v5.667H9.351V9h3.414v1.561h.046c.477-.9 1.637-1.85 3.37-1.85 3.601 0 4.267 2.37 4.267 5.455v6.286ZM5.337 7.433a2.062 2.062 0 0 1-2.063-2.065 2.064 2.064 0 1 1 2.063 2.065Zm1.782 13.019H3.555V9h3.564v11.452ZM22.225 0H1.771C.792 0 0 .774 0 1.729v20.542C0 23.227.792 24 1.771 24h20.451C23.2 24 24 23.227 24 22.271V1.729C24 .774 23.2 0 22.222 0h.003Z'],
  ['youtube', 'YouTube', 'M23.498 6.186a3.016 3.016 0 0 0-2.122-2.136C19.505 3.545 12 3.545 12 3.545s-7.505 0-9.377.505A3.017 3.017 0 0 0 .502 6.186C0 8.07 0 12 0 12s0 3.93.502 5.814a3.016 3.016 0 0 0 2.122 2.136c1.871.505 9.376.505 9.376.505s7.505 0 9.377-.505a3.015 3.015 0 0 0 2.122-2.136C24 15.93 24 12 24 12s0-3.93-.502-5.814ZM9.545 15.568V8.432L15.818 12l-6.273 3.568Z'],
]

const CATEGORY_EMOJI = [
  [/health|fitness/i, '\u{1F4AA}'],
  [/flagship/i, '\u{1F451}'],
  [/\bcar\b|automotive|vehicle/i, '\u{1F697}'],
  [/paan/i, '\u{1F343}'],
  [/dairy|milk|cheese|bread.*egg|egg.*bread/i, '\u{1F95B}'],
  [/fruit|vegetable|veg\b|produce/i, '\u{1F966}'],
  [/cold ?drink|soft ?drink|juice|beverage|soda|water/i, '\u{1F964}'],
  [/snack|munch|namkeen|chips|wafer/i, '\u{1F37F}'],
  [/breakfast|instant|cereal|noodle|oats/i, '\u{1F963}'],
  [/sweet|chocolate|candy|dessert|mithai/i, '\u{1F36B}'],
  [/bak|biscuit|bread|cookie|rusk/i, '\u{1F35E}'],
  [/tea|coffee|health ?drink/i, '\u{2615}'],
  [/atta|rice|dal|pulse|flour|grain|pantry|staple/i, '\u{1F35A}'],
  [/masala|spice|\boil\b|ghee|condiment/i, '\u{1F9C2}'],
  [/sauce|spread|ketchup|\bjam\b|pickle|dip/i, '\u{1F96B}'],
  [/chicken|meat|fish|seafood|poultry|mutton|egg/i, '\u{1F357}'],
  [/organic|premium|healthy living/i, '\u{1F331}'],
  [/baby/i, '\u{1F37C}'],
  [/pharma|wellness|medicine|first aid/i, '\u{1F48A}'],
  [/clean|detergent|repellent|disinfect/i, '\u{1F9FD}'],
  [/pet\b|pet ?care/i, '\u{1F43E}'],
  [/personal ?care|beauty|cosmetic|hygiene|skin|hair/i, '\u{1F9F4}'],
  [/home|office|kitchen|household|lifestyle|stationery/i, '\u{1F3E0}'],
  [/frozen/i, '\u{1F9CA}'],
]
function categoryEmoji(name = '') { return (CATEGORY_EMOJI.find(([re]) => re.test(name)) ?? [null, '\u{1F6D2}'])[1] }

// Groupings for the Categories mega-menu's left rail — our catalog is a single
// department (electronics), so these aren't real DB-backed parent categories,
// just a sensible split of the 20 real categories into browsable clusters.
const CATEGORY_GROUPS = [
  ['Phones & Tablets', ['Mobiles & Smartphones', 'Mobile Accessories', 'Kids & Baby Tech']],
  ['Computers & Office', ['Laptops & Computers', 'Computer Accessories', 'Storage Devices', 'Networking Devices', 'Office Electronics']],
  ['Audio & Entertainment', ['Audio & Headphones', 'Televisions', 'Gaming Consoles & Accessories']],
  ['Wearables & Cameras', ['Smart Watches & Wearables', 'Cameras & Photography', 'Health & Fitness Tech']],
  ['Smart Home & Appliances', ['Home Appliances', 'Smart Home', 'Personal Care Electronics']],
  ['Power & Charging', ['Power Banks & Chargers']],
  ['Car & Premium', ['Car Electronics', 'Premium & Flagship']],
]

const SORT_CHANNELS = ['best_selling', 'top_rated', 'newest']

// Lightweight, dependency-free placeholder shown under a product image while
// it loads (or in place of a broken one) — a generic photo glyph, no network
// request, no per-product guessing.
function imgPlaceholder() {
  return <span className="img-ph" aria-hidden>
    <svg viewBox="0 0 24 24" width="1em" height="1em" fill="none" stroke="currentColor" strokeWidth="1.5" strokeLinecap="round" strokeLinejoin="round"><rect x="3" y="4" width="18" height="16" rx="2" /><circle cx="8.5" cy="9.5" r="1.5" /><path d="M21 15l-5-5-5 5-3-3-5 5" /></svg>
    <b>Loading</b>
  </span>
}


const CANCELLABLE_STAGES = ['pending_payment', 'confirmed', 'packing', 'ready_for_delivery']

const ISSUE_TYPES = [
  ['item_missing', 'Item missing'],
  ['item_damaged', 'Item damaged'],
  ['wrong_item', 'Wrong item'],
  ['not_delivered', "Didn't receive order"],
  ['payment_issue', 'Payment issue'],
  ['other', 'Something else'],
]
// Not offered in the "new request" picker — only the delivery rider opens these.
const ISSUE_LABEL_EXTRA = { delivery: 'Delivery message' }
const issueLabel = (type) => ISSUE_LABEL_EXTRA[type] ?? (ISSUE_TYPES.find(([t]) => t === type) ?? [null, type])[1]

function orderLabel(order) {
  if (order.payment_status === 'refund_pending') return 'Refund pending'
  if (order.payment_status === 'refunded') return 'Refunded'
  if (order.payment_status === 'paid') return order.payment_method === 'cod' ? 'Cash collected' : 'Paid'
  if (order.payment_status === 'failed') return 'Payment failed'
  if (order.payment_status === 'cancelled') return 'Cancelled'
  if (order.payment_method === 'cod') return 'Cash on delivery'
  return 'Awaiting payment'
}

const DELIVERY_STAGES = ['confirmed', 'packing', 'ready_for_delivery', 'out_for_delivery', 'completed']
const DELIVERY_LABELS = { confirmed: 'Confirmed', packing: 'Packing', ready_for_delivery: 'Ready for delivery', out_for_delivery: 'Out for delivery', completed: 'Delivered', cancelled: 'Cancelled' }

async function responseJson(response) {
  const text = await response.text()
  const jsonStart = Math.min(...['{', '['].map((token) => {
    const index = text.indexOf(token)
    return index === -1 ? text.length : index
  }))

  return JSON.parse(text.slice(jsonStart))
}

function StarPicker({ value, onChange, readOnly }) {
  return (
    <span className="star-picker" role="radiogroup" aria-label="Rating">
      {[1, 2, 3, 4, 5].map((n) => (
        <button key={n} type="button" role="radio" aria-checked={n === value} aria-label={`${n} star${n === 1 ? '' : 's'}`}
          className={n <= value ? 'star on' : 'star'} disabled={readOnly} onClick={() => onChange?.(n)}>★</button>
      ))}
    </span>
  )
}

/**
 * Customer's 1–5 rating and optional private note for the rider on an order or a
 * delivery chat. The comment is shown only to the NexTech team, never the rider.
 */
function RiderRating({ orderId, existing, source, onSaved }) {
  const [rating, setRating] = useState(existing?.rating ?? 0)
  const [comment, setComment] = useState(existing?.comment ?? '')
  const [editing, setEditing] = useState(!existing)
  const [busy, setBusy] = useState(false)
  const [msg, setMsg] = useState('')

  async function submit() {
    if (!rating) { setMsg('Tap a star to rate.'); return }
    setBusy(true); setMsg('')
    try {
      const res = await fetch(`${API_URL}/orders/${orderId}/rider-review`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', Accept: 'application/json', Authorization: `Bearer ${localStorage.getItem('gdp_token')}` },
        body: JSON.stringify({ rating, comment: comment.trim() || null, source }),
      })
      const data = await responseJson(res)
      if (!res.ok) throw new Error(data.message ?? 'Could not save your rating.')
      onSaved?.(data.data)
      setEditing(false)
      setMsg('Thanks for the feedback!')
    } catch (error) { setMsg(error.message) } finally { setBusy(false) }
  }

  if (!editing) {
    return (
      <div className="rider-rating done">
        <span>You rated your rider</span>
        <StarPicker value={rating} readOnly />
        <button type="button" className="text-button" onClick={() => setEditing(true)}>Edit</button>
      </div>
    )
  }

  return (
    <div className="rider-rating">
      <span className="rider-rating-h">Rate your delivery rider</span>
      <StarPicker value={rating} onChange={setRating} />
      <textarea rows="2" maxLength="1000" placeholder="Add a note for the NexTech team (optional, private — the rider never sees it)" value={comment} onChange={(event) => setComment(event.target.value)} />
      <div className="rider-rating-actions">
        <button type="button" className="text-button" disabled={busy} onClick={submit}>{existing ? 'Update rating' : 'Submit rating'}</button>
        {existing && <button type="button" className="text-button" onClick={() => { setEditing(false); setRating(existing.rating); setComment(existing.comment ?? '') }}>Cancel</button>}
      </div>
      {msg && <p className="rider-rating-msg">{msg}</p>}
    </div>
  )
}

/**
 * Customer's 1–5 rating of a support conversation, shown at the end of the chat
 * once staff have replied. One rating per thread; a repeat submission edits it.
 */
function ChatRating({ thread, onSaved }) {
  const rated = thread.rating != null
  const [rating, setRating] = useState(thread.rating ?? 0)
  const [comment, setComment] = useState(thread.rating_comment ?? '')
  const [editing, setEditing] = useState(!rated)
  const [busy, setBusy] = useState(false)
  const [msg, setMsg] = useState('')

  async function submit() {
    if (!rating) { setMsg('Tap a star to rate.'); return }
    setBusy(true); setMsg('')
    try {
      const res = await fetch(`${API_URL}/support/threads/${thread.id}/rating`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', Accept: 'application/json', Authorization: `Bearer ${localStorage.getItem('gdp_token')}` },
        body: JSON.stringify({ rating, comment: comment.trim() || null }),
      })
      const data = await responseJson(res)
      if (!res.ok) throw new Error(data.message ?? 'Could not save your rating.')
      onSaved?.(data.data)
      setEditing(false)
      setMsg('Thanks for the feedback!')
    } catch (error) { setMsg(error.message) } finally { setBusy(false) }
  }

  if (!editing) {
    return (
      <div className="rider-rating done chat-rating">
        <span>You rated this chat</span>
        <StarPicker value={rating} readOnly />
        <button type="button" className="text-button" onClick={() => setEditing(true)}>Edit</button>
      </div>
    )
  }

  return (
    <div className="rider-rating chat-rating">
      <span className="rider-rating-h">How was this conversation?</span>
      <StarPicker value={rating} onChange={setRating} />
      <textarea rows="2" maxLength="1000" placeholder="Anything we could do better? (optional)" value={comment} onChange={(event) => setComment(event.target.value)} />
      <div className="rider-rating-actions">
        <button type="button" className="text-button" disabled={busy} onClick={submit}>{rated ? 'Update rating' : 'Submit rating'}</button>
        {rated && <button type="button" className="text-button" onClick={() => { setEditing(false); setRating(thread.rating); setComment(thread.rating_comment ?? '') }}>Cancel</button>}
      </div>
      {msg && <p className="rider-rating-msg">{msg}</p>}
    </div>
  )
}

function PaymentForm({ clientSecret, onComplete, savedCards = [] }) {
  const stripe = useStripe()
  const elements = useElements()
  const [message, setMessage] = useState('')
  const [submitting, setSubmitting] = useState(false)
  const defaultCard = savedCards.find((c) => c.is_default) ?? savedCards[0]
  const [choice, setChoice] = useState(defaultCard ? defaultCard.id : 'new') // pm id | 'new'
  const [saveCard, setSaveCard] = useState(false)
  const usingSaved = choice !== 'new'

  async function pay(event) {
    event.preventDefault()
    if (!stripe) return
    if (!usingSaved && !elements) return
    setSubmitting(true)
    setMessage('')
    const confirmData = usingSaved
      ? { payment_method: choice }
      : { payment_method: { card: elements.getElement(CardElement) }, ...(saveCard ? { setup_future_usage: 'off_session' } : {}) }
    const result = await stripe.confirmCardPayment(clientSecret, confirmData)
    if (result.error) setMessage(result.error.message)
    else if (result.paymentIntent?.status === 'succeeded') onComplete(result.paymentIntent.id)
    setSubmitting(false)
  }

  return <form className="payment-form" onSubmit={pay}>
    {savedCards.length > 0 && <div className="pay-cards" role="radiogroup" aria-label="Card">
      {savedCards.map((card) => (
        <label key={card.id} className={choice === card.id ? 'pay-card active' : 'pay-card'}>
          <input type="radio" name="paycard" checked={choice === card.id} onChange={() => setChoice(card.id)} />
          <span style={{ textTransform: 'capitalize' }}>{card.brand} &bull;&bull;&bull;&bull; {card.last4}</span>
          <em>{String(card.exp_month).padStart(2, '0')}/{String(card.exp_year).slice(-2)}</em>
        </label>
      ))}
      <label className={choice === 'new' ? 'pay-card active' : 'pay-card'}>
        <input type="radio" name="paycard" checked={choice === 'new'} onChange={() => setChoice('new')} />
        <span>Use a new card</span>
      </label>
    </div>}
    {!usingSaved && <>
      <label>Card details<CardElement options={{ style: { base: { fontSize: '16px', color: '#20291f', fontFamily: 'Okra, sans-serif' } } }} /></label>
      <label className="account-check"><input type="checkbox" checked={saveCard} onChange={(event) => setSaveCard(event.target.checked)} /> Save this card for next time</label>
    </>}
    <button className="checkout-button" type="submit" disabled={submitting || !stripe}>{submitting ? 'Processing...' : 'Pay securely'} <span>&rarr;</span></button>
    {message && <p className="auth-message">{message}</p>}
  </form>
}

// Adds a card to the customer without charging it, via a Stripe SetupIntent.
function AddCardForm({ onDone, onCancel }) {
  const stripe = useStripe()
  const elements = useElements()
  const [message, setMessage] = useState('')
  const [busy, setBusy] = useState(false)

  async function submit(event) {
    event.preventDefault()
    if (!stripe || !elements) return
    setBusy(true)
    setMessage('')
    try {
      const res = await fetch(`${API_URL}/billing/setup-intent`, { method: 'POST', headers: { Accept: 'application/json', Authorization: `Bearer ${localStorage.getItem('gdp_token')}` } })
      const data = await res.json().catch(() => ({}))
      if (!res.ok) throw new Error(data.message ?? 'Could not start card setup.')
      const result = await stripe.confirmCardSetup(data.data.client_secret, { payment_method: { card: elements.getElement(CardElement) } })
      if (result.error) throw new Error(result.error.message)
      onDone()
    } catch (error) { setMessage(error.message) }
    setBusy(false)
  }

  return <form className="payment-form" onSubmit={submit}>
    <label>Card details<CardElement options={{ style: { base: { fontSize: '15px', color: '#20291f', fontFamily: 'Okra, sans-serif' } } }} /></label>
    <div className="checkout-links">
      <button className="checkout-button" type="submit" disabled={busy || !stripe}>{busy ? 'Saving…' : 'Save card'}</button>
      <button className="switch-auth" type="button" onClick={onCancel}>Cancel</button>
    </div>
    {message && <p className="auth-message">{message}</p>}
  </form>
}

export default function Storefront() {
  const [stripeKey, setStripeKey] = useState(import.meta.env.VITE_STRIPE_PUBLISHABLE_KEY || '')
  const stripePromise = stripeFor(stripeKey)
  const [categories, setCategories] = useState([])
  const [products, setProducts] = useState([])
  const [query, setQuery] = useState('')
  const [activeCategory, setActiveCategory] = useState(null)
  const [categoriesMenuGroup, setCategoriesMenuGroup] = useState(CATEGORY_GROUPS[0][0])
  // Dropdown panels open on CSS :hover, which a click can't dismiss (the mouse
  // hasn't moved) — this force-closes one after a menu action, and clears
  // automatically once the cursor actually leaves the trigger.
  const [closedMenu, setClosedMenu] = useState(null) // null | 'categories' | 'support' | 'account'
  // Force-closing the panel on click (via pointer-events/visibility) pulls it out
  // from under the still-stationary cursor, which makes the browser immediately
  // fire a real mouseleave on the wrapper — so the panel would already be hidden
  // by :hover/:focus-within turning false on their own. But the clicked button
  // keeps DOM focus after a click, and :focus-within doesn't care about the
  // mouse, so it re-opens the panel the instant the forced-close class lifts.
  // Blurring the clicked control removes that stuck-open path.
  function menuAction(menu, fn) { return (event) => { fn(); event.currentTarget.blur(); setClosedMenu(menu) } }
  function menuLeave() { setClosedMenu(null) }
  // The searchbar re-filters the existing home grid in place rather than
  // navigating to a new page, so without this the results can sit off-screen
  // below the fold — scroll it into view so the change is actually seen.
  function scrollToProductGrid() { setTimeout(() => (productGridRef.current ?? mainRef.current)?.scrollIntoView({ behavior: 'smooth', block: 'start' }), 350) }
  // The mega-menu links to a dedicated category page (like the deals pages)
  // rather than filtering the home grid in place — that in-place path had no
  // heading and hid the grid entirely on a genuinely empty result.
  function selectCategoryFromMenu(cat) { openCategoryPage(cat.slug) }

  // Promo bar above the main menu row: each column cycles through a couple
  // of messages on a shared timer, rather than staying static.
  const [promoTick, setPromoTick] = useState(0)
  useEffect(() => {
    const id = setInterval(() => setPromoTick((t) => t + 1), 4500)
    return () => clearInterval(id)
  }, [])

  const [showBackToTop, setShowBackToTop] = useState(false)
  const [feedbackOpen, setFeedbackOpen] = useState(false)
  const [feedbackRating, setFeedbackRating] = useState(null)
  const [feedbackSent, setFeedbackSent] = useState(false)
  const [cart, setCart] = useState(() => {
    try {
      const saved = JSON.parse(localStorage.getItem('gdp_cart') ?? '[]')
      return (Array.isArray(saved) ? saved : [])
        .filter((item) => item && item.id != null)
        .map((item) => ({ variantId: null, name: '', price_cents: 0, compare_at_price_cents: null, quantity: 1, ...item, key: item.key ?? `${item.id}:${item.variantId ?? ''}` }))
    } catch { return [] }
  })
  const [pickedVariant, setPickedVariant] = useState({})
  const [productView, setProductView] = useState(null) // product | 'loading' | null
  const [galleryIndex, setGalleryIndex] = useState(0)
  const [showVideo, setShowVideo] = useState(false)
  const [reviewsExpanded, setReviewsExpanded] = useState(false)
  const [loading, setLoading] = useState(true)
  const [offline, setOffline] = useState(false)
  const [pages, setPages] = useState([])
  const [pageView, setPageView] = useState(null) // { slug, title, content } | 'loading' | null
  const [location, setLocation] = useState(() => {
    try { return JSON.parse(localStorage.getItem('gdp_location') ?? 'null') }
    catch { return null }
  })
  // Never auto-prompt for a location on load — like Amazon, it's only asked
  // for at checkout (via the "Change location" links there).
  const [locationOpen, setLocationOpen] = useState(false)
  const [editAddress, setEditAddress] = useState(false)
  const [locationQuery, setLocationQuery] = useState('')
  const [locationResults, setLocationResults] = useState([])
  const [locationBusy, setLocationBusy] = useState(false)
  const [locationMsg, setLocationMsg] = useState('')
  const [stores, setStores] = useState([])
  const [banners, setBanners] = useState([])
  const [homeTiles, setHomeTiles] = useState([])
  const [lightningDeals, setLightningDeals] = useState([])
  const [unbeatableDeals, setUnbeatableDeals] = useState([])
  const [dealsPage, setDealsPageState] = useState(null)
  const [dealsCategory, setDealsCategory] = useState(null)
  const [dealsExclusive, setDealsExclusive] = useState([])
  const [dealsProducts, setDealsProducts] = useState([])
  const [shopSlug, setShopSlug] = useState(null)
  const [shopInfo, setShopInfo] = useState(null) // null | 'loading' | { name, … } | { missing: true }
  const [shopLinkCopied, setShopLinkCopied] = useState(false)
  const [dealsProductPage, setDealsProductPage] = useState(0)
  const [dealsHasMore, setDealsHasMore] = useState(false)
  const [dealsLoading, setDealsLoading] = useState(true)
  const [dealsLoadingMore, setDealsLoadingMore] = useState(false)
  const dealsCatsRef = useRef(null)
  const dealsCatsDrag = useRef({ down: false, moved: false, startX: 0, scrollLeft: 0 })
  const dealsExclusiveRef = useRef(null)
  const dealsExclusiveDrag = useRef({ down: false, moved: false, startX: 0, scrollLeft: 0 })
  const homeCatsWrapRef = useRef(null)
  const mainRef = useRef(null)
  const dealsCatsWrapRef = useRef(null)
  const dealsExclusiveWrapRef = useRef(null)
  const [branding, setBranding] = useState(null)
  const [footer, setFooter] = useState(null)
  const mapRef = useRef(null)
  const markerRef = useRef(null)
  const mapNodeRef = useRef(null)
  const homeCatsRef = useRef(null)
  const homeCatsDrag = useRef({ down: false, moved: false, startX: 0, scrollLeft: 0 })
  const productGridRef = useRef(null)
  const [itemsPerRow, setItemsPerRow] = useState(() => (window.innerWidth <= 640 ? 2 : 5))
  const locationRef = useRef(null)
  const [cartOpen, setCartOpen] = useState(false)
  const [trayLift, setTrayLift] = useState(0) // px the cart pill is dragged up; snaps back to 0 on scroll
  const [trayDragging, setTrayDragging] = useState(false)
  const trayDragRef = useRef(null)
  const [checkoutOpen, setCheckoutOpen] = useState(false)
  const [checkoutStep, setCheckoutStep] = useState('address')
  const [authMode, setAuthMode] = useState(null)
  const blankAuthForm = { name: '', email: '', password: '', password_confirmation: '', code: '', line1: '', city: '', state: '', postal_code: '' }
  const [authForm, setAuthForm] = useState(blankAuthForm)
  const [otpStage, setOtpStage] = useState(null)
  const [otpCode, setOtpCode] = useState('')
  const [authTab, setAuthTab] = useState('code')
  const [pwMode, setPwMode] = useState('signin') // 'signin' | 'signup' | 'forgot' (Password tab)
  const [resetSent, setResetSent] = useState(false)
  const [authMessage, setAuthMessage] = useState('')
  const [checkoutForm, setCheckoutForm] = useState({ name: '', line1: '', city: '', state: '', postal_code: '' })
  const [deliveryNote, setDeliveryNote] = useState('')
  const [phone, setPhone] = useState('')
  const [checkoutMessage, setCheckoutMessage] = useState('')
  const [paymentMethod, setPaymentMethod] = useState('card')
  const [codEnabled, setCodEnabled] = useState(false)
  const [giftCard, setGiftCard] = useState({ code: '', pin: '', checked: null })
  // Market = the country store being browsed (US / India …): its products,
  // currency, fees and address format. Empty = the platform's home market.
  // First visit: guess from the device time zone (India → India store); after
  // that the delivery location decides, and the header picker overrides.
  const [market, setMarket] = useState(() => {
    try {
      const saved = localStorage.getItem('nextech_market')
      if (saved) return saved
    } catch { /* private mode */ }
    const tz = Intl.DateTimeFormat().resolvedOptions().timeZone ?? ''
    return /^Asia\/(Kolkata|Calcutta)$/.test(tz) ? 'IN' : ''
  })
  const [markets, setMarkets] = useState([])
  const [grievanceOfficer, setGrievanceOfficer] = useState(null)
  const [fees, setFees] = useState({ tax_rate_bps: 0, delivery_mode: 'fixed', delivery_fee_cents: 0, delivery_near_fee_cents: 0, delivery_far_fee_cents: 0, free_delivery_threshold_cents: 0, handling_fee_cents: 0, small_cart_fee_cents: 0, small_cart_min_cents: 0 })
  // The country store being shown (declared early — hooks below depend on it).
  const activeMarket = (market && (markets.length === 0 || markets.some((m) => m.code === market)) ? market : null) || fees.market || 'US'
  const marketProfile = markets.find((m) => m.code === activeMarket) ?? null
  const taxInclusive = marketProfile?.tax_mode === 'inclusive'
  const marketName = (code) => markets.find((m) => m.code === code)?.name ?? code
  setStoreCurrency(marketProfile?.currency ?? fees.currency ?? 'usd')
  const [serviceable, setServiceable] = useState(null)
  const [sellerQuote, setSellerQuote] = useState(null) // shipping for items sellers ship themselves
  const [order, setOrder] = useState(null)
  const [currentUser, setCurrentUser] = useState(() => {
    try {
      const saved = JSON.parse(localStorage.getItem('gdp_user') ?? 'null')
      // Ignore stale / malformed data left by an earlier version of the site.
      return saved && typeof saved === 'object' && (saved.email || saved.id) ? saved : null
    } catch { return null }
  })
  const [addresses, setAddresses] = useState([])
  const [selectedAddressId, setSelectedAddressId] = useState('')
  const [accountOpen, setAccountOpen] = useState(false)
  const [accountTab, setAccountTab] = useState('profile')
  const [accountMsg, setAccountMsg] = useState('')
  const [profileForm, setProfileForm] = useState({ name: '', phone: '' })
  const [pwForm, setPwForm] = useState({ current: '', next: '', confirm: '' })
  const [addrForm, setAddrForm] = useState(null) // null | { id?, label, name, line1, line2, city, state, postal_code, is_default }
  const [cards, setCards] = useState(null) // null = not loaded; [] = none
  const [cardsBusy, setCardsBusy] = useState(false)
  const [addingCard, setAddingCard] = useState(false)
  const [ordersOpen, setOrdersOpen] = useState(false)
  const [orders, setOrders] = useState([])
  const [ordersLoading, setOrdersLoading] = useState(false)
  const [ordersMessage, setOrdersMessage] = useState('')
  const [supportView, setSupportView] = useState(null) // null | 'list' | 'new' | thread object
  const [threads, setThreads] = useState([])
  const [supportForm, setSupportForm] = useState({ about_order: false, order_id: '', issue_type: 'item_missing', message: '' })
  const [supportReply, setSupportReply] = useState('')
  const [supportPhotos, setSupportPhotos] = useState([]) // photo URLs waiting to be sent (reply or new request)
  const [supportBusy, setSupportBusy] = useState(false)
  const [supportMsg, setSupportMsg] = useState('')
  const [supportUnread, setSupportUnread] = useState(0)

  useEffect(() => {
    localStorage.setItem('gdp_cart', JSON.stringify(cart))
  }, [cart])

  // How many product cards fit per row so "See more" can reveal whole rows
  // at a time. The grid is a fixed 5-column desktop / 2-column mobile layout
  // (see .product-grid in StorefrontBase.css), so this mirrors that
  // breakpoint directly instead of measuring the DOM — a DOM measurement
  // taken before the grid's first paint would race the very first product
  // fetch (which reads itemsPerRow to size its per_page) and could lock in
  // the wrong count for the rest of that page's life.
  useEffect(() => {
    const compute = () => setItemsPerRow(window.innerWidth <= 640 ? 2 : 5)
    window.addEventListener('resize', compute)
    return () => window.removeEventListener('resize', compute)
  }, [])


  // Prefill the checkout phone field from the account once it loads, without
  // clobbering anything the customer is mid-way through typing.
  useEffect(() => {
    if (currentUser?.phone) setPhone((current) => current || currentUser.phone)
  }, [currentUser])

  useEffect(() => {
    if (!checkoutOpen && !locationOpen && !accountOpen) return
    const token = localStorage.getItem('gdp_token')
    if (!token) return
    fetch(`${API_URL}/addresses`, { headers: { Accept: 'application/json', Authorization: `Bearer ${token}` } })
      .then(responseJson)
      .then((data) => {
        setAddresses(data.data ?? [])
        if (checkoutOpen && data.data?.[0]) setSelectedAddressId(String(data.data[0].id))
      })
      .catch(() => setAddresses([]))
  }, [checkoutOpen, locationOpen, accountOpen])

  useEffect(() => {
    if (!accountOpen) return
    setAccountMsg('')
    setProfileForm({ name: currentUser?.name ?? '', phone: currentUser?.phone ?? '' })
  }, [accountOpen, currentUser])

  useEffect(() => {
    if (!accountOpen || accountTab !== 'cards' || cards !== null) return
    loadCards()
  }, [accountOpen, accountTab]) // eslint-disable-line react-hooks/exhaustive-deps

  // Have the shopper's saved cards ready when the payment step opens.
  useEffect(() => {
    if (!order?.clientSecret || !stripePromise || cards !== null) return
    loadCards()
  }, [order?.clientSecret]) // eslint-disable-line react-hooks/exhaustive-deps

  useEffect(() => {
    if (!ordersOpen) return
    const token = localStorage.getItem('gdp_token')
    if (!token) return
    fetch(`${API_URL}/orders`, { headers: { Accept: 'application/json', Authorization: `Bearer ${token}` } })
      .then(responseJson)
      .then((data) => setOrders(data.data ?? []))
      .catch(() => setOrders([]))
      .finally(() => setOrdersLoading(false))
  }, [ordersOpen])

  useEffect(() => {
    fetch(`${API_URL}/config`, { headers: { Accept: 'application/json', ...(market ? { 'X-Market': market } : {}) } })
      .then(responseJson)
      .then((data) => { setMarkets(data.data?.markets ?? []); setGrievanceOfficer(data.data?.grievance_officer ?? null); setCodEnabled(!!data.data?.cod_enabled); setStores(data.data?.stores ?? []); setBanners(data.data?.banners ?? []); setHomeTiles(data.data?.home_tiles ?? []); setBranding(data.data?.branding ?? null); setFooter(data.data?.footer ?? null); if (data.data?.stripe_publishable_key) setStripeKey(data.data.stripe_publishable_key); if (data.data) setFees(data.data) })
      .catch(() => { setCodEnabled(false); setStores([]); setBanners([]); setHomeTiles([]) })
  }, [market])

  // Apply admin-configured branding: theme palette, accent colours, tab title
  // and favicon, all driven from GET /api/config.
  useEffect(() => {
    if (!branding) return
    const root = document.documentElement
    const s = root.style
    const dark = branding.theme === 'dark'
    s.setProperty('--paper', dark ? '#12160f' : '#f3f5f2')
    s.setProperty('--surface', dark ? '#1b211a' : '#ffffff')
    s.setProperty('--line', dark ? '#2b332a' : '#e4e8e3')
    s.setProperty('--muted', dark ? '#9aa79c' : '#6b7770')
    s.setProperty('--ink', dark ? '#eef1ec' : '#18211c')
    s.setProperty('--lime', dark ? '#1c2a1c' : '#eaf7e5')
    if (branding.color_brand) s.setProperty('--green', branding.color_brand)
    if (branding.color_accent) s.setProperty('--yellow', branding.color_accent)
    // The heading colour only overrides the theme default when it was actually customised.
    if (branding.color_heading && branding.color_heading.toLowerCase() !== '#18211c') s.setProperty('--ink', branding.color_heading)
    s.setProperty('--shell-max', branding.layout_width === 'full' ? 'none' : '1280px')
    root.style.colorScheme = dark ? 'dark' : 'light'

    const name = branding.store_name || 'NexTech'
    document.title = branding.tagline ? `${name} | ${branding.tagline}` : name
    if (branding.favicon_url) {
      let link = document.querySelector("link[rel='icon']")
      if (!link) { link = document.createElement('link'); link.rel = 'icon'; document.head.appendChild(link) }
      link.href = mediaUrl(branding.favicon_url)
    }
  }, [branding])

  useEffect(() => { locationRef.current = location })
  // The map's pin handler outlives renders — always call the current applyLocation.
  const applyLocationRef = useRef(null)
  useEffect(() => { applyLocationRef.current = applyLocation })

  // Prefill the checkout address text from the chosen location when the modal opens.
  useEffect(() => {
    if (!checkoutOpen || !location) return
    setCheckoutForm((form) => {
      if (form.line1) return form
      const line1 = location.line1 || (location.full || location.label || '').split(',').slice(0, 3).join(', ').trim()
      if (!line1 && !location.city) return form
      return {
        ...form,
        line1: line1 || form.line1,
        city: form.city || location.city || '',
        state: form.state || location.state || '',
        postal_code: form.postal_code || location.postal_code || '',
      }
    })
  }, [checkoutOpen, location])

  // Check the saved location against the store delivery radius. Runs on mount
  // for a stored location and again whenever the location's coordinates change.
  useEffect(() => {
    const lat = location?.lat
    const lng = location?.lon
    if (lat == null || lng == null) { setServiceable(null); return }
    let cancelled = false
    fetch(`${API_URL}/delivery-eta?lat=${lat}&lng=${lng}${market ? `&market=${market}` : ''}`, { headers: { Accept: 'application/json' } })
      .then(responseJson)
      .then((data) => { if (!cancelled) setServiceable(data.data ?? null) })
      .catch(() => { if (!cancelled) setServiceable(null) })
    return () => { cancelled = true }
  }, [location?.lat, location?.lon, market])

  useEffect(() => {
    const token = localStorage.getItem('gdp_token')
    if (!token) return
    fetch(`${API_URL}/user`, { headers: { Accept: 'application/json', Authorization: `Bearer ${token}` } })
      .then(responseJson)
      .then((user) => { setCurrentUser(user); localStorage.setItem('gdp_user', JSON.stringify(user)) })
      .catch(() => { localStorage.removeItem('gdp_token'); localStorage.removeItem('gdp_user'); setCurrentUser(null) })
  }, [])

  // Pass the chosen location so the API can scope the catalog to the store that
  // serves this customer — a product that the nearest store doesn't stock is
  // left out. No location (or out of area) returns the full catalog.
  const catalogParams = new URLSearchParams({
    ...(market ? { market } : {}),
    ...(location?.lat != null && location?.lon != null ? { lat: location.lat, lng: location.lon } : {}),
  }).toString()
  const catalogQuery = catalogParams ? `?${catalogParams}` : ''

  // Categories are a small payload and feed the homepage tiles, so fetch them
  // on their own — don't make the homepage wait on the full product list.
  useEffect(() => {
    fetch(`${API_URL}/categories${catalogQuery}`, { headers: { Accept: 'application/json' } })
      .then((response) => { if (!response.ok) throw new Error('offline'); return responseJson(response) })
      .then((data) => setCategories(data.data ?? []))
      .catch(() => { setOffline(true); setCategories(categoriesFallback) })
  }, [catalogQuery])

  // A small random sample per deal type, for the homepage deals strip — the
  // API re-rolls the sample on every request, so a refresh shows different
  // products without any client-side shuffling.
  useEffect(() => {
    const sep = catalogQuery ? '&' : '?'
    fetch(`${API_URL}/deals${catalogQuery}${sep}deal_type=lightning&limit=3`, { headers: { Accept: 'application/json' } })
      .then(responseJson).then((data) => setLightningDeals(data.data ?? [])).catch(() => setLightningDeals([]))
    fetch(`${API_URL}/deals${catalogQuery}${sep}deal_type=unbeatable&limit=3`, { headers: { Accept: 'application/json' } })
      .then(responseJson).then((data) => setUnbeatableDeals(data.data ?? [])).catch(() => setUnbeatableDeals([]))
  }, [catalogQuery])

  // Deals page: a random exclusive-offer sample for this deal type, re-rolled
  // whenever the page is (re)opened.
  useEffect(() => {
    if (dealsPage !== 'lightning') { setDealsExclusive([]); return }
    const sep = catalogQuery ? '&' : '?'
    fetch(`${API_URL}/deals${catalogQuery}${sep}deal_type=${dealsPage}&exclusive=1&limit=18`, { headers: { Accept: 'application/json' } })
      .then(responseJson).then((data) => setDealsExclusive(data.data ?? [])).catch(() => setDealsExclusive([]))
  }, [dealsPage, catalogQuery])

  // "All under $X" cap for the Exclusive Offer strip — the highest price among
  // the cheapest 10 (or fewer, if the bucket is smaller) exclusive-offer items
  // currently loaded, Temu-style. Always derived live, never hidden for count.
  const dealsExclusiveCapCents = useMemo(() => {
    if (dealsExclusive.length === 0) return null
    const cheapestTen = [...dealsExclusive].sort((a, b) => a.price_cents - b.price_cents).slice(0, 10)
    return Math.max(...cheapestTen.map((p) => p.price_cents))
  }, [dealsExclusive])

  const dealsCategorySlug = useMemo(
    () => categories.find((c) => c.name === dealsCategory)?.slug ?? null,
    [categories, dealsCategory],
  )

  const buildDealsProductsUrl = (page) => {
    const params = new URLSearchParams()
    if (dealsPage === 'exclusive') params.set('exclusive', '1')
    else if (SORT_CHANNELS.includes(dealsPage)) params.set('sort', dealsPage)
    else if (dealsPage === 'shop') params.set('shop', shopSlug ?? '')
    else if (dealsPage !== 'category') params.set('deal_type', dealsPage)
    if (dealsCategorySlug) params.set('category', dealsCategorySlug)
    params.set('per_page', String(Math.min(50, Math.max(itemsPerRow * 8, 8))))
    params.set('page', String(page))
    const sep = catalogQuery ? '&' : '?'
    return `${API_URL}/products${catalogQuery}${sep}${params.toString()}`
  }

  // Deals page: first page of products for this deal type (+ optional
  // category filter from the carousel), re-fetched whenever either changes.
  useEffect(() => {
    if (!dealsPage) return
    let cancelled = false
    setDealsLoading(true)
    setDealsProducts([])
    setDealsProductPage(0)
    setDealsHasMore(false)
    fetch(buildDealsProductsUrl(1), { headers: { Accept: 'application/json' } })
      .then((response) => { if (!response.ok) throw new Error('offline'); return responseJson(response) })
      .then((data) => {
        if (cancelled) return
        setDealsProducts(data.data ?? [])
        setDealsProductPage(1)
        setDealsHasMore(1 < (data.last_page ?? 1))
      })
      .catch(() => { if (!cancelled) setDealsProducts([]) })
      .finally(() => { if (!cancelled) setDealsLoading(false) })
    return () => { cancelled = true }
  }, [dealsPage, dealsCategorySlug, catalogQuery, shopSlug]) // eslint-disable-line react-hooks/exhaustive-deps

  function loadMoreDealsProducts() {
    if (dealsLoadingMore || !dealsHasMore) return
    setDealsLoadingMore(true)
    const nextPage = dealsProductPage + 1
    fetch(buildDealsProductsUrl(nextPage), { headers: { Accept: 'application/json' } })
      .then((response) => { if (!response.ok) throw new Error('offline'); return responseJson(response) })
      .then((data) => {
        setDealsProducts((prev) => prev.concat(data.data ?? []))
        setDealsProductPage(nextPage)
        setDealsHasMore(nextPage < (data.last_page ?? 1))
      })
      .catch(() => {})
      .finally(() => setDealsLoadingMore(false))
  }

  // Debounce the search box so typing doesn't fire a request per keystroke —
  // the product list is now paged server-side, not filtered from a local copy.
  const [debouncedQuery, setDebouncedQuery] = useState('')
  useEffect(() => {
    const timer = setTimeout(() => setDebouncedQuery(query.trim()), 300)
    return () => clearTimeout(timer)
  }, [query])

  const activeCategorySlug = useMemo(
    () => categories.find((c) => c.name === activeCategory)?.slug ?? null,
    [categories, activeCategory],
  )

  // "See more" loads one page at a time instead of the whole catalogue, so the
  // batch size roughly matches 8 rows of cards at the current column count.
  const productsPerPage = () => Math.min(50, Math.max(itemsPerRow * 8, 8))
  const buildProductsUrl = (page) => {
    const params = new URLSearchParams()
    if (debouncedQuery.length >= 2) params.set('search', debouncedQuery)
    else if (activeCategorySlug) params.set('category', activeCategorySlug)
    params.set('per_page', String(productsPerPage()))
    params.set('page', String(page))
    const sep = catalogQuery ? '&' : '?'
    return `${API_URL}/products${catalogQuery}${sep}${params.toString()}`
  }

  const [productPage, setProductPage] = useState(0)
  const [hasMorePages, setHasMorePages] = useState(false)
  const [loadingMore, setLoadingMore] = useState(false)

  // Fetch only the first page for the active filter (category, search, or
  // neither) — resets whenever the filter changes. Further pages are fetched
  // on demand by loadMoreProducts(), below.
  useEffect(() => {
    let cancelled = false
    setLoading(true)
    setProducts([])
    setProductPage(0)
    setHasMorePages(false)
    // The API rejects a search shorter than 2 chars; show nothing rather than
    // fall through to an unfiltered fetch while the user is still typing.
    if (debouncedQuery.length === 1) { setLoading(false); return undefined }
    fetch(buildProductsUrl(1), { headers: { Accept: 'application/json' } })
      .then((response) => { if (!response.ok) throw new Error('offline'); return responseJson(response) })
      .then((data) => {
        if (cancelled) return
        setProducts(data.data ?? [])
        setProductPage(1)
        setHasMorePages(1 < (data.last_page ?? 1))
      })
      .catch(() => { if (!cancelled) { setOffline(true); setProducts(fallbackProducts) } })
      .finally(() => { if (!cancelled) setLoading(false) })
    return () => { cancelled = true }
  }, [catalogQuery, activeCategorySlug, debouncedQuery]) // eslint-disable-line react-hooks/exhaustive-deps

  function loadMoreProducts() {
    if (loadingMore || !hasMorePages) return
    setLoadingMore(true)
    const nextPage = productPage + 1
    fetch(buildProductsUrl(nextPage), { headers: { Accept: 'application/json' } })
      .then((response) => { if (!response.ok) throw new Error('offline'); return responseJson(response) })
      .then((data) => {
        setProducts((prev) => prev.concat(data.data ?? []))
        setProductPage(nextPage)
        setHasMorePages(nextPage < (data.last_page ?? 1))
      })
      .catch(() => {})
      .finally(() => setLoadingMore(false))
  }

  // Content pages: load the footer list once, and keep the open page in sync
  // with a #/p/<slug> hash so links are shareable and Back works.
  useEffect(() => {
    fetch(`${API_URL}/pages`, { headers: { Accept: 'application/json' } })
      .then(responseJson).then((data) => setPages(data.data ?? [])).catch(() => setPages([]))
  }, [])

  useEffect(() => {
    const sync = () => {
      const match = window.location.hash.match(/^#\/p\/([a-z0-9-]+)$/)
      if (!match) { setPageView(null); return }
      const slug = match[1]
      setPageView((current) => (current && current.slug === slug ? current : 'loading'))
      fetch(`${API_URL}/pages/${slug}`, { headers: { Accept: 'application/json' } })
        .then(responseJson)
        .then((data) => setPageView(data.data ?? null))
        .catch(() => setPageView({ slug, title: 'Page not found', content: 'That page does not exist.' }))
    }
    sync()
    window.addEventListener('hashchange', sync)
    return () => window.removeEventListener('hashchange', sync)
  }, [])

  // Deals pages: #/deals/lightning or #/deals/unbeatable. A plain category
  // browse (from the Categories menu) reuses the same page shell as
  // #/deals/category/<slug> — categories loads async, so this re-resolves
  // the name once it's populated.
  useEffect(() => {
    const sync = () => {
      const shopMatch = window.location.hash.match(/^#\/shop\/([a-z0-9-]+)$/)
      if (shopMatch) {
        setDealsPageState('shop')
        setShopSlug(shopMatch[1])
        setDealsCategory(null)
        return
      }
      setShopSlug(null)
      const categoryMatch = window.location.hash.match(/^#\/deals\/category\/([a-z0-9-]+)$/)
      if (categoryMatch) {
        setDealsPageState('category')
        setDealsCategory(categories.find((c) => c.slug === categoryMatch[1])?.name ?? null)
        return
      }
      const match = window.location.hash.match(/^#\/deals\/(lightning|unbeatable|exclusive|best_selling|top_rated|newest)$/)
      setDealsPageState(match ? match[1] : null)
      setDealsCategory(null)
    }
    sync()
    window.addEventListener('hashchange', sync)
    return () => window.removeEventListener('hashchange', sync)
  }, [categories])

  // Product detail page: #/product/<slug>.
  useEffect(() => {
    const sync = () => {
      const match = window.location.hash.match(/^#\/product\/([a-z0-9-]+)$/)
      if (!match) { setProductView(null); return }
      const slug = match[1]
      setProductView((current) => (current && current !== 'loading' && current.slug === slug ? current : 'loading'))
      setGalleryIndex(0)
      setShowVideo(false)
      setReviewsExpanded(false)
      fetch(`${API_URL}/products/${slug}`, { headers: { Accept: 'application/json' } })
        .then(responseJson)
        .then((data) => setProductView(data.data ?? null))
        .catch(() => setProductView(null))
    }
    sync()
    window.addEventListener('hashchange', sync)
    return () => window.removeEventListener('hashchange', sync)
  }, [])

  // Show the floating "back to top" button once the page has scrolled down.
  useEffect(() => {
    const onScroll = () => setShowBackToTop(window.scrollY > 400)
    onScroll()
    window.addEventListener('scroll', onScroll, { passive: true })
    return () => window.removeEventListener('scroll', onScroll)
  }, [])

  // A seller's own shop page (#/shop/<slug>) — its header info; products load
  // through the deals-page fetch above with ?shop=<slug>.
  useEffect(() => {
    if (!shopSlug) { Promise.resolve().then(() => setShopInfo(null)); return }
    let cancelled = false
    Promise.resolve().then(() => { if (!cancelled) setShopInfo('loading') })
    fetch(`${API_URL}/shops/${shopSlug}`, { headers: { Accept: 'application/json' } })
      .then((response) => (response.ok ? responseJson(response) : Promise.reject(new Error('missing'))))
      .then((data) => { if (!cancelled) setShopInfo(data.data ?? { missing: true }) })
      .catch(() => { if (!cancelled) setShopInfo({ missing: true }) })
    return () => { cancelled = true }
  }, [shopSlug])

  function openShop(slug) {
    window.location.hash = `#/shop/${slug}`
    window.scrollTo({ top: 0 })
  }

  function copyShopLink() {
    const url = `${window.location.origin}${window.location.pathname}#/shop/${shopSlug}`
    navigator.clipboard?.writeText(url).then(() => { setShopLinkCopied(true); setTimeout(() => setShopLinkCopied(false), 2000) }).catch(() => {})
  }

  function openDeals(type) {
    window.location.hash = `#/deals/${type}`
    window.scrollTo({ top: 0 })
  }
  function openCategoryPage(slug) {
    window.location.hash = `#/deals/category/${slug}`
    window.scrollTo({ top: 0 })
  }
  function closeDeals() {
    if (window.location.hash) window.location.hash = ''
    else setDealsPageState(null)
  }

  function openPage(slug) {
    window.location.hash = `#/p/${slug}`
    window.scrollTo({ top: 0 })
  }
  function closePage() {
    if (window.location.hash) window.location.hash = ''
    else setPageView(null)
  }

  function openProduct(product) {
    window.location.hash = `#/product/${product.slug || product.id}`
    window.scrollTo({ top: 0 })
  }
  function closeProduct() {
    if (window.location.hash) window.location.hash = ''
    else setProductView(null)
  }

  const searching = query.trim().length > 0
  const locationUsable = !!(location && location.city && (location.line1 || location.postal_code))
  const defaultAddress = addresses.find((address) => address.is_default) ?? addresses[0] ?? null
  // How checkout resolves the delivery address, unless the user edits it:
  // the location just entered, else the account's saved address, else a form.
  const deliveryMode = editAddress ? 'form' : locationUsable ? 'location' : defaultAddress ? 'saved' : 'form'

  const outOfArea = !!(serviceable && serviceable.configured && !serviceable.deliverable)
  const needsPhone = !phone.trim()
  const blockCheckout = (outOfArea && deliveryMode === 'location') || needsPhone
  const UNSERVICEABLE_MSG = "We don't deliver to your area yet — we're expanding fast and will reach you soon."
  const selectedAddress = addresses.find((address) => String(address.id) === String(selectedAddressId))
  const deliveryAddressSummary = deliveryMode === 'location'
    ? [location.full || location.label, checkoutForm.line1].filter(Boolean).join(' — ')
    : deliveryMode === 'saved'
      ? (selectedAddress ? `${selectedAddress.line1}, ${selectedAddress.city}` : `${defaultAddress.line1}, ${defaultAddress.city} ${defaultAddress.state} ${defaultAddress.postal_code}`)
      : selectedAddress
        ? `${selectedAddress.line1}, ${selectedAddress.city}`
        : [checkoutForm.line1, checkoutForm.city, checkoutForm.state].filter(Boolean).join(', ') || 'New address'
  // Promo bar columns, each a small set of messages that swap on promoTick.
  // Only real, currently-live features are advertised here.
  const appStoreUrl = footer?.app_store_url || footer?.play_store_url || null
  const promoColumns = useMemo(() => {
    const truckGlyph = <svg aria-hidden width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round"><rect x="1" y="7" width="14" height="10" rx="1" /><path d="M15 10h4l3 3v4h-7z" /><circle cx="6" cy="19" r="2" /><circle cx="17" cy="19" r="2" /></svg>
    const shieldGlyph = <svg aria-hidden width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round"><path d="M12 3l7 3v6c0 4.5-3 7.5-7 9-4-1.5-7-4.5-7-9V6z" /><polyline points="9 12 11 14 15 10" /></svg>
    const cashGlyph = <svg aria-hidden width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round"><rect x="2" y="6" width="20" height="12" rx="2" /><circle cx="12" cy="12" r="3" /><path d="M6 6v.01M18 18v-.01" /></svg>
    const phoneGlyph = <svg aria-hidden width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round"><rect x="6" y="2" width="12" height="20" rx="2" /><line x1="11" y1="18" x2="13" y2="18" /></svg>
    const headsetGlyph = <svg aria-hidden width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round"><path d="M3 12a9 9 0 0 1 18 0" /><path d="M3 12v5a2 2 0 0 0 2 2h1v-7H4a1 1 0 0 0-1 1z" /><path d="M21 12v5a2 2 0 0 1-2 2h-1v-7h2a1 1 0 0 1 1 1z" /></svg>

    const columns = [
      {
        icon: truckGlyph,
        messages: fees.free_delivery_threshold_cents > 0
          ? [{ title: 'Free shipping', sub: `On orders over ${price(fees.free_delivery_threshold_cents)}` }, { title: 'Fast delivery', sub: 'Tracked door-to-door delivery' }]
          : [{ title: 'Fast delivery', sub: 'Tracked door-to-door delivery' }],
        onClick: () => openPage('shipping-info'),
      },
      {
        icon: shieldGlyph,
        messages: [{ title: 'Purchase protection', sub: 'Refund for any issues' }, { title: 'Easy returns', sub: 'Simple return & refund policy' }],
        onClick: () => openPage('purchase-protection'),
      },
    ]
    columns.push({
      icon: cashGlyph,
      messages: codEnabled
        ? [{ title: 'Cash on delivery', sub: 'Pay when it arrives' }, { title: 'Secure payments', sub: 'Your details stay protected' }]
        : [{ title: 'Secure payments', sub: 'Your details stay protected' }],
      onClick: () => openPage('faqs'),
    })
    if (appStoreUrl) columns.push({
      icon: phoneGlyph,
      messages: [{ title: `Get the ${branding?.store_name || 'NexTech'} App`, sub: 'Shop faster on mobile' }],
      href: appStoreUrl,
    }); else columns.push({
      icon: headsetGlyph,
      messages: [{ title: '24/7 Support', sub: 'We’re here if anything comes up' }],
      onClick: () => openPage('support-center'),
    })
    return columns
  }, [fees.free_delivery_threshold_cents, codEnabled, appStoreUrl, branding?.store_name])

  // Filtering (category / search) now happens server-side, page by page —
  // `products` already holds exactly the rows for the active filter.
  const visibleProducts = products

  const categoryCounts = useMemo(() => {
    const counts = {}
    for (const product of products) {
      const name = product.category?.name
      if (name) counts[name] = (counts[name] ?? 0) + 1
    }
    return counts
  }, [products])

  // A few product names per category for the homepage feature cards.
  const categorySamples = useMemo(() => {
    const samples = {}
    for (const product of products) {
      const name = product.category?.name
      if (!name) continue
      ;(samples[name] ??= []).push(product.name)
    }
    return samples
  }, [products])

  const cartCount = cart.reduce((sum, item) => sum + item.quantity, 0)
  const cartTotal = cart.reduce((sum, item) => sum + item.price_cents * item.quantity, 0)
  // Enrich each line with the current catalog price / discount, so the cart
  // shows sale pricing even for items added before this data existed.
  const cartView = useMemo(() => cart.map((item) => {
    const p = products.find((x) => x.id === item.id)
    const v = p && item.variantId ? (p.variants ?? []).find((x) => x.id === item.variantId) : null
    const unit = v ? v.price_cents : (p ? p.price_cents : item.price_cents)
    const regRaw = v ? v.compare_at_price_cents : (p ? p.compare_at_price_cents : item.compare_at_price_cents)
    const reg = (regRaw != null && regRaw > unit) ? regRaw : null
    return { ...item, unit, reg, onSale: reg != null, lineReg: (reg ?? unit) * item.quantity }
  }), [cart, products])
  const cartRegularTotal = cartView.reduce((sum, line) => sum + line.lineReg, 0)
  const cartQty = useMemo(() => Object.fromEntries(cart.map((item) => [item.key, item.quantity])), [cart])

  // Items from sellers who ship themselves are charged the seller's own
  // shipping (template fee + delivery dates) instead of NexTech's delivery fee.
  const shipState = checkoutForm.state || location?.state || defaultAddress?.state || ''
  const quoteKey = JSON.stringify([market, shipState, cart.map((item) => [item.id, item.quantity, item.price_cents])])
  useEffect(() => {
    if (!cart.length) { Promise.resolve().then(() => setSellerQuote(null)); return undefined }
    let cancelled = false
    fetch(`${API_URL}/shipping/quote`, { method: 'POST', headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-Market': activeMarket }, body: JSON.stringify({ state: shipState || null, lines: cart.map((item) => ({ product_id: item.id, quantity: item.quantity, price_cents: item.price_cents })) }) })
      .then(responseJson)
      .then((data) => { if (!cancelled) setSellerQuote(data.data ?? null) })
      .catch(() => { if (!cancelled) setSellerQuote(null) })
    return () => { cancelled = true }
  }, [quoteKey, activeMarket]) // eslint-disable-line react-hooks/exhaustive-deps

  const sellerShippedIds = sellerQuote?.seller_shipped_product_ids ?? []
  const hasSellerShipped = cart.some((item) => sellerShippedIds.includes(item.id))

  // Client-side estimate of the fee breakdown; the server total is authoritative.
  const est = useMemo(() => {
    const sub = cartTotal
    const nextechSub = cart.filter((item) => !sellerShippedIds.includes(item.id)).reduce((sum, item) => sum + item.price_cents * item.quantity, 0)
    const sellerShipping = sellerQuote?.total_cents ?? 0
    const tax = Math.round((sub * fees.tax_rate_bps) / 10000)
    // Distance mode: the fee for the current pin comes from /api/delivery-eta,
    // which re-fires as the pin moves. Otherwise the flat fee.
    const baseDelivery = fees.delivery_mode === 'distance' && serviceable?.delivery_fee_cents != null
      ? serviceable.delivery_fee_cents
      : fees.delivery_fee_cents
    const delivery = nextechSub === 0 || nextechSub >= fees.free_delivery_threshold_cents ? 0 : baseDelivery
    const handling = sub > 0 ? fees.handling_fee_cents : 0
    const smallCart = sub > 0 && sub < fees.small_cart_min_cents ? fees.small_cart_fee_cents : 0
    return {
      sub, tax, delivery, handling, smallCart, sellerShipping,
      total: sub + tax + delivery + sellerShipping + handling + smallCart,
      toFreeDelivery: delivery > 0 ? fees.free_delivery_threshold_cents - nextechSub : 0,
      toNoSmallCart: smallCart > 0 ? fees.small_cart_min_cents - sub : 0,
    }
  }, [cartTotal, fees, serviceable, cart, sellerQuote]) // eslint-disable-line react-hooks/exhaustive-deps


  function switchMarket(code, { fromLocation = false } = {}) {
    if (code === activeMarket || !markets.some((m) => m.code === code)) return
    const why = fromLocation ? `Your delivery location is in ${marketName(code)}. Switch to the ${marketName(code)} store? ` : `Switching to ${marketName(code)} `
    if (cart.length && !window.confirm(`${why}${fromLocation ? 'Your cart will be emptied' : 'empties your cart'} — items can't ship between countries.${fromLocation ? '' : ' Continue?'}`)) return
    setCart([])
    setMarket(code)
    try { localStorage.setItem('nextech_market', code) } catch { /* private mode */ }
  }

  function lineKey(productId, variantId) {
    return `${productId}:${variantId ?? ''}`
  }

  function add(product, variant) {
    if (product.market && product.market !== activeMarket) {
      window.alert(`This item is sold in the ${marketName(product.market)} store — switch country at the top of the page to buy it.`)
      return
    }
    const key = lineKey(product.id, variant?.id)
    setCart((current) => {
      const found = current.find((item) => item.key === key)
      if (found) return current.map((item) => item.key === key ? { ...item, quantity: item.quantity + 1 } : item)
      return [...current, {
        key,
        id: product.id,
        variantId: variant?.id ?? null,
        variantLabel: variant?.label ?? null,
        name: product.name,
        price_cents: variant?.price_cents ?? product.price_cents,
        compare_at_price_cents: (variant ? variant.compare_at_price_cents : product.compare_at_price_cents) ?? null,
        image_url: variant?.image_url || product.image_url,
        quantity: 1,
      }]
    })
  }

  function updateQuantity(key, amount) {
    setCart((current) => current.flatMap((item) => {
      if (item.key !== key) return [item]
      const quantity = item.quantity + amount
      return quantity > 0 ? [{ ...item, quantity }] : []
    }))
  }

  // Shared by the product card and the detail popup so both agree on which
  // variant is selected, its price/stock, and the cart quantity for it.
  function variantView(product) {
    const variants = product.variants ?? []
    const hasVariants = variants.length > 0
    const options = hasVariants
      ? [{ id: '', label: product.name, price_cents: product.price_cents, compare_at_price_cents: product.compare_at_price_cents, inventory_quantity: product.inventory_quantity, image_url: product.image_url }, ...variants]
      : []
    const chosen = hasVariants
      ? (options.find((o) => String(o.id) === String(pickedVariant[product.id] ?? '')) ?? options[0])
      : null
    const variant = chosen && chosen.id !== '' ? chosen : null
    const unitPrice = chosen ? chosen.price_cents : product.price_cents
    const compareAt = chosen ? chosen.compare_at_price_cents : product.compare_at_price_cents
    const onSale = compareAt != null && compareAt > unitPrice
    const pctOff = onSale ? Math.round((1 - unitPrice / compareAt) * 100) : 0
    const stock = chosen ? chosen.inventory_quantity : product.inventory_quantity
    const key = lineKey(product.id, variant?.id)
    const qty = cartQty[key] ?? 0
    const img = (chosen?.image_url) || product.image_url
    return { variants, hasVariants, options, chosen, variant, unitPrice, compareAt, onSale, pctOff, stock, key, qty, img }
  }

  // Every distinct photo for a product: its own image, its gallery (seller
  // multi-image uploads), plus each variant's own image (anything reusing an
  // already-listed photo doesn't add a duplicate).
  function galleryImages(product) {
    const urls = [product.image_url, ...(product.images ?? []).map((i) => i.url), ...(product.variants ?? []).map((v) => v.image_url)].filter(Boolean)
    return [...new Set(urls)]
  }

  // The floating "View cart" pill can be dragged upward to reveal text it covers;
  // it slides back to its resting spot as soon as the page is scrolled.
  function trayPointerDown(event) {
    if (event.target.closest('button')) return // let the View cart tap through
    trayDragRef.current = { startY: event.clientY, base: trayLift }
    setTrayDragging(true)
    event.currentTarget.setPointerCapture?.(event.pointerId)
  }
  function trayPointerMove(event) {
    if (!trayDragRef.current) return
    const dy = event.clientY - trayDragRef.current.startY
    setTrayLift(Math.max(-320, Math.min(0, trayDragRef.current.base + dy)))
  }
  function trayPointerUp(event) {
    if (!trayDragRef.current) return
    trayDragRef.current = null
    setTrayDragging(false)
    event.currentTarget.releasePointerCapture?.(event.pointerId)
  }

  useEffect(() => {
    if (trayLift === 0) return
    const reset = () => setTrayLift(0)
    window.addEventListener('scroll', reset, { passive: true })
    return () => window.removeEventListener('scroll', reset)
  }, [trayLift])

  async function submitAuth(event) {
    event.preventDefault()
    setAuthMessage('')
    try {
      const response = await fetch(`${API_URL}/auth/start`, { method: 'POST', headers: { 'Content-Type': 'application/json', Accept: 'application/json' }, body: JSON.stringify({ email: authForm.email.trim() }) })
      const data = await responseJson(response)
      if (!response.ok) throw new Error(data.message ?? Object.values(data.errors ?? {})[0]?.[0] ?? 'Please check your email.')
      if (data.token) { finishAuth(data); return }
      setOtpStage({ email: data.email, purpose: data.purpose })
      setOtpCode('')
      setAuthMessage(data.known ? 'Welcome back — enter the code we emailed you.' : 'Enter the code to finish creating your account.')
    } catch (error) {
      setAuthMessage(error instanceof TypeError ? 'The API is offline. Start Laravel on port 8000 and try again.' : error.message)
    }
  }

  async function submitPassword(event) {
    event.preventDefault()
    setAuthMessage('')

    if (pwMode === 'forgot') {
      try {
        if (!resetSent) {
          const r = await fetch(`${API_URL}/auth/forgot-password`, { method: 'POST', headers: { 'Content-Type': 'application/json', Accept: 'application/json' }, body: JSON.stringify({ email: authForm.email.trim() }) })
          const d = await responseJson(r)
          setResetSent(true)
          setAuthMessage(d.message ?? 'If that email has an account, a reset code is on its way.')
          return
        }
        if (authForm.password !== authForm.password_confirmation) { setAuthMessage('The two passwords don’t match.'); return }
        const r = await fetch(`${API_URL}/auth/reset-password`, { method: 'POST', headers: { 'Content-Type': 'application/json', Accept: 'application/json' }, body: JSON.stringify({ email: authForm.email.trim(), code: authForm.code.trim(), password: authForm.password, password_confirmation: authForm.password_confirmation }) })
        const d = await responseJson(r)
        if (!r.ok) throw new Error(d.message ?? Object.values(d.errors ?? {})[0]?.[0] ?? 'Could not reset the password.')
        finishAuth(d)
      } catch (error) {
        setAuthMessage(error instanceof TypeError ? 'The API is offline. Start Laravel on port 8000 and try again.' : error.message)
      }
      return
    }

    const signup = pwMode === 'signup'
    if (signup && authForm.password !== authForm.password_confirmation) {
      setAuthMessage('The two passwords don’t match.'); return
    }
    try {
      const url = signup ? `${API_URL}/auth/register` : `${API_URL}/auth/login`
      const body = signup
        ? { name: authForm.name.trim(), email: authForm.email.trim(), password: authForm.password, password_confirmation: authForm.password_confirmation }
        : { email: authForm.email.trim(), password: authForm.password }
      const response = await fetch(url, { method: 'POST', headers: { 'Content-Type': 'application/json', Accept: 'application/json' }, body: JSON.stringify(body) })
      const data = await responseJson(response)
      if (!response.ok) throw new Error(data.message ?? Object.values(data.errors ?? {})[0]?.[0] ?? (signup ? 'Could not create the account.' : 'The email or password is incorrect.'))
      if (data.token) { finishAuth(data); return }
      // OTP is enforced on the server — fall through to the code step.
      setOtpStage({ email: data.email, purpose: data.purpose ?? (signup ? 'register' : 'login') })
      setOtpCode('')
      setAuthMessage(data.message ?? 'Enter the code we emailed you to finish.')
    } catch (error) {
      setAuthMessage(error instanceof TypeError ? 'The API is offline. Start Laravel on port 8000 and try again.' : error.message)
    }
  }

  function finishAuth(data) {
    // Staff accounts sign in through the dedicated /admin page, never here, so a
    // customer can't land in an admin session by typing the wrong credentials.
    if (data.user?.is_admin) {
      fetch(`${API_URL}/auth/logout`, { method: 'POST', headers: { Accept: 'application/json', Authorization: `Bearer ${data.token}` } }).catch(() => {})
      localStorage.removeItem('gdp_token')
      localStorage.removeItem('gdp_user')
      setAuthForm(blankAuthForm)
      setOtpStage(null)
      setOtpCode('')
      setAuthTab('code')
      setAuthMessage(`That's an administrator account — sign in at ${import.meta.env.BASE_URL}admin`)
      return
    }
    localStorage.setItem('gdp_token', data.token)
    localStorage.setItem('gdp_user', JSON.stringify(data.user))
    setCurrentUser(data.user)
    setAuthMessage(`Welcome, ${data.user.name}.`)
    setAuthForm(blankAuthForm)
    setAuthMode(null)
    setOtpStage(null)
    setOtpCode('')
    setAuthTab('code')
    if (cart.length) setCheckoutOpen(true)
  }

  async function submitOtp(event) {
    event.preventDefault()
    setAuthMessage('')
    try {
      const response = await fetch(`${API_URL}/auth/verify-otp`, { method: 'POST', headers: { 'Content-Type': 'application/json', Accept: 'application/json' }, body: JSON.stringify({ ...otpStage, code: otpCode.trim() }) })
      const data = await responseJson(response)
      if (!response.ok) throw new Error(data.message ?? 'That code did not work.')
      finishAuth(data)
    } catch (error) {
      setAuthMessage(error instanceof TypeError ? 'The API is offline. Start Laravel on port 8000 and try again.' : error.message)
    }
  }

  async function resendOtp() {
    setAuthMessage('')
    try {
      const response = await fetch(`${API_URL}/auth/resend-otp`, { method: 'POST', headers: { 'Content-Type': 'application/json', Accept: 'application/json' }, body: JSON.stringify({ ...otpStage }) })
      const data = await responseJson(response)
      setAuthMessage(data.message ?? (response.ok ? 'A new code is on its way.' : 'Could not resend the code.'))
    } catch {
      setAuthMessage('Could not resend the code.')
    }
  }

  async function logout() {
    const token = localStorage.getItem('gdp_token')
    if (token) await fetch(`${API_URL}/auth/logout`, { method: 'POST', headers: { Accept: 'application/json', Authorization: `Bearer ${token}` } }).catch(() => {})
    localStorage.removeItem('gdp_token')
    localStorage.removeItem('gdp_user')
    setCurrentUser(null)
  }

  async function checkGiftCard() {
    const token = localStorage.getItem('gdp_token')
    if (!token) { setGiftCard((g) => ({ ...g, checked: { error: 'Sign in first.' } })); return }
    if (!giftCard.code.trim() || !giftCard.pin.trim()) return
    try {
      const res = await fetch(`${API_URL}/gift-cards/check`, { method: 'POST', headers: { 'Content-Type': 'application/json', Accept: 'application/json', Authorization: `Bearer ${token}` }, body: JSON.stringify({ code: giftCard.code.trim(), pin: giftCard.pin.trim() }) })
      const data = await responseJson(res)
      setGiftCard((g) => ({ ...g, checked: res.ok ? { balance_cents: data.data.balance_cents } : { error: data.message ?? 'Not valid.' } }))
    } catch { setGiftCard((g) => ({ ...g, checked: { error: 'Could not check right now.' } })) }
  }

  async function submitCheckout(event) {
    event.preventDefault()
    setCheckoutMessage('')
    setOrdersMessage('')
    const token = localStorage.getItem('gdp_token')
    if (!token) {
      setCheckoutOpen(false)
      setCheckoutStep('address')
      setAuthMode('login')
      setAuthMessage('Sign in to place your order — your cart is saved.')
      return
    }

    // Pass the location so the cart's stock check reads the serving store's shelf.
    const here = location?.lat != null && location?.lon != null ? { lat: Number(location.lat), lng: Number(location.lon) } : {}
    try {
      // The local cart is the source of truth — clear any leftovers server-side first.
      await fetch(`${API_URL}/cart`, { method: 'DELETE', headers: { Accept: 'application/json', Authorization: `Bearer ${token}` } })
      for (const item of cart) {
        await fetch(`${API_URL}/cart/items`, { method: 'POST', headers: { 'Content-Type': 'application/json', Accept: 'application/json', Authorization: `Bearer ${token}` }, body: JSON.stringify({ product_id: item.id, product_variant_id: item.variantId ?? null, quantity: item.quantity, ...here }) })
      }
      let checkoutBody
      if (deliveryMode !== 'location' && selectedAddressId) {
        checkoutBody = { address_id: Number(selectedAddressId) }
      } else if (deliveryMode === 'saved' && defaultAddress) {
        checkoutBody = { address_id: defaultAddress.id }
      } else {
        // Fall back to the chosen location's fields (and finally its display
        // string) so line1 is never blank even if the form wasn't touched.
        const fromFull = (location?.full || location?.label || '').split(',').slice(0, 3).join(', ').trim()
        const addressPayload = {
          ...checkoutForm,
          name: checkoutForm.name || currentUser?.name || 'Customer',
          line1: checkoutForm.line1?.trim() || location?.line1 || fromFull,
          city: checkoutForm.city || location?.city || null,
          state: checkoutForm.state || location?.state || null,
          postal_code: checkoutForm.postal_code || location?.postal_code || null,
          label: 'Home',
          is_default: addresses.length === 0,
        }
        if (!addressPayload.line1) throw new Error('Add a street / house detail for the delivery address.')
        // Carry the map pin's exact coordinates so the server checks delivery
        // against the pin, not a re-geocode of the typed address.
        if (location?.lat != null && location?.lon != null) {
          addressPayload.latitude = Number(location.lat)
          addressPayload.longitude = Number(location.lon)
        }
        const addressResponse = await fetch(`${API_URL}/addresses`, { method: 'POST', headers: { 'Content-Type': 'application/json', Accept: 'application/json', Authorization: `Bearer ${token}` }, body: JSON.stringify(addressPayload) })
        const addressData = await responseJson(addressResponse)
        if (!addressResponse.ok) throw new Error(addressData.message ?? 'Address could not be saved.')
        checkoutBody = { address_id: addressData.data.id }
      }
      const method = codEnabled && !hasSellerShipped ? paymentMethod : 'card'
      const trimmedPhone = phone.trim()
      const gc = giftCard.code.trim() && giftCard.pin.trim()
        ? { gift_card_code: giftCard.code.trim(), gift_card_pin: giftCard.pin.trim() }
        : {}
      const response = await fetch(`${API_URL}/checkout`, { method: 'POST', headers: { 'Content-Type': 'application/json', Accept: 'application/json', Authorization: `Bearer ${token}`, 'X-Market': activeMarket }, body: JSON.stringify({ ...checkoutBody, payment_method: method, delivery_instructions: deliveryNote.trim() || null, phone: trimmedPhone, ...gc }) })
      const data = await responseJson(response)
      if (!response.ok) throw new Error(data.message ?? 'Checkout could not be completed.')
      if (trimmedPhone && currentUser && currentUser.phone !== trimmedPhone) {
        const updated = { ...currentUser, phone: trimmedPhone }
        setCurrentUser(updated)
        localStorage.setItem('gdp_user', JSON.stringify(updated))
      }
      setGiftCard({ code: '', pin: '', checked: null })
      if (data.data.payment_status === 'paid' && method !== 'cod') {
        // Gift card covered the whole order — nothing to pay online.
        setOrder({ ...data.data, paid: true })
      } else if (method === 'cod') {
        setOrder({ ...data.data, cod: true })
      } else {
        if (!stripePromise) throw new Error('Card payments aren’t set up yet — add the Stripe publishable key in admin Settings → Payments.')
        const paymentResponse = await fetch(`${API_URL}/orders/${data.data.id}/payment-intent`, { method: 'POST', headers: { Accept: 'application/json', Authorization: `Bearer ${token}` } })
        const paymentData = await responseJson(paymentResponse)
        if (!paymentResponse.ok) throw new Error(paymentData.message ?? 'Payment setup could not be completed.')
        setOrder({ ...data.data, clientSecret: paymentData.data.client_secret })
      }
      setCart([])
      setCartOpen(false)
      setCheckoutOpen(false)
      setCheckoutStep('address')
      setCheckoutMessage('')
      setDeliveryNote('')
    } catch (error) { setCheckoutMessage(error.message) }
  }

  async function resumePayment(entry) {
    setOrdersMessage('')
    const token = localStorage.getItem('gdp_token')
    if (!token) { setOrdersMessage('Please sign in first.'); return }
    try {
      if (!stripePromise) throw new Error('Card payments aren’t set up yet — add the Stripe publishable key in admin Settings → Payments.')
      const response = await fetch(`${API_URL}/orders/${entry.id}/payment-intent`, { method: 'POST', headers: { Accept: 'application/json', Authorization: `Bearer ${token}` } })
      const data = await responseJson(response)
      if (!response.ok) throw new Error(data.message ?? 'Payment could not be started.')
      if (data.data.payment_status === 'paid' || !data.data.client_secret) {
        setOrders((current) => current.map((row) => row.id === entry.id ? { ...row, payment_status: 'paid', status: 'confirmed' } : row))
        return
      }
      setOrder({ ...entry, clientSecret: data.data.client_secret })
      setOrdersOpen(false)
    } catch (error) { setOrdersMessage(error.message) }
  }

  // A seller-shipped package arrived — the customer confirms it (the seller or
  // NexTech can too); the order completes once every package is delivered.
  async function confirmPackageReceived(order, pkg) {
    if (!window.confirm('Confirm this package has arrived?')) return
    try {
      const response = await authPost(`/orders/${order.id}/packages/${pkg.id}/received`, {})
      if (!response.ok) throw new Error((await responseJson(response)).message ?? 'Could not confirm.')
      const data = await responseJson(await authGet('/orders'))
      setOrders(data.data ?? [])
    } catch (error) { setOrdersMessage(error.message) }
  }

  async function cancelOrder(entry) {
    if (!window.confirm(`Cancel order #${entry.id}? This can't be undone.`)) return
    setOrdersMessage('')
    const token = localStorage.getItem('gdp_token')
    if (!token) { setOrdersMessage('Please sign in first.'); return }
    try {
      const response = await fetch(`${API_URL}/orders/${entry.id}/cancel`, { method: 'POST', headers: { Accept: 'application/json', Authorization: `Bearer ${token}` } })
      const data = await responseJson(response)
      if (!response.ok) throw new Error(data.message ?? 'Could not cancel the order.')
      setOrders((current) => current.map((row) => row.id === entry.id ? { ...row, ...data.data } : row))
      setOrdersMessage(data.data.payment_status === 'refund_pending' ? 'Order cancelled — your refund is being processed.' : 'Order cancelled.')
    } catch (error) { setOrdersMessage(error.message) }
  }

  async function downloadReceipt(orderId) {
    setOrdersMessage('')
    const token = localStorage.getItem('gdp_token')
    if (!token) { setOrdersMessage('Please sign in first.'); return }
    try {
      const response = await fetch(`${API_URL}/orders/${orderId}/receipt`, { headers: { Accept: 'application/pdf', Authorization: `Bearer ${token}` } })
      if (response.status === 403) throw new Error('The bill is ready once payment is done — or, for cash on delivery, once the order is placed.')
      if (!response.ok) throw new Error('Could not generate the bill. Please try again.')
      const blob = await response.blob()
      const url = URL.createObjectURL(blob)
      const link = document.createElement('a')
      link.href = url
      link.download = `bill-order-${orderId}.pdf`
      document.body.appendChild(link)
      link.click()
      link.remove()
      URL.revokeObjectURL(url)
    } catch (error) { setOrdersMessage(error.message) }
  }

  const authGet = (path) => fetch(`${API_URL}${path}`, { headers: { Accept: 'application/json', Authorization: `Bearer ${localStorage.getItem('gdp_token')}` } })
  const authPost = (path, body) => fetch(`${API_URL}${path}`, { method: 'POST', headers: { 'Content-Type': 'application/json', Accept: 'application/json', Authorization: `Bearer ${localStorage.getItem('gdp_token')}` }, body: JSON.stringify(body) })
  const authSend = (path, method, body) => fetch(`${API_URL}${path}`, { method, headers: { 'Content-Type': 'application/json', Accept: 'application/json', Authorization: `Bearer ${localStorage.getItem('gdp_token')}` }, body: body ? JSON.stringify(body) : undefined })

  function openAccount(tab = 'profile') {
    if (!localStorage.getItem('gdp_token')) { setAuthMode('login'); setAuthMessage('Sign in to manage your account.'); return }
    setAccountTab(tab)
    setAddrForm(null)
    setAccountOpen(true)
  }

  async function saveProfile(event) {
    event.preventDefault()
    setAccountMsg('')
    try {
      const data = await responseJson(await authSend('/profile', 'PATCH', { name: profileForm.name.trim(), phone: profileForm.phone.trim() || null }))
      const updated = { ...currentUser, name: data.data?.name ?? profileForm.name, phone: data.data?.phone ?? null }
      setCurrentUser(updated)
      localStorage.setItem('gdp_user', JSON.stringify(updated))
      setAccountMsg('Profile saved.')
    } catch { setAccountMsg('Could not save your profile.') }
  }

  async function changePassword(event) {
    event.preventDefault()
    setAccountMsg('')
    if (pwForm.next !== pwForm.confirm) { setAccountMsg('The two new passwords don’t match.'); return }
    try {
      const res = await authSend('/profile/password', 'PATCH', { current_password: pwForm.current, password: pwForm.next, password_confirmation: pwForm.confirm })
      const data = await responseJson(res)
      if (!res.ok) throw new Error(data.message ?? Object.values(data.errors ?? {})[0]?.[0] ?? 'Could not change the password.')
      setPwForm({ current: '', next: '', confirm: '' })
      setAccountMsg('Password changed.')
    } catch (error) { setAccountMsg(error.message) }
  }

  function reloadAddresses() {
    authGet('/addresses').then(responseJson).then((d) => setAddresses(d.data ?? [])).catch(() => {})
  }

  async function saveAddress(event) {
    event.preventDefault()
    setAccountMsg('')
    const { id, ...body } = addrForm
    body.is_default = !!addrForm.is_default
    try {
      const res = await authSend(id ? `/addresses/${id}` : '/addresses', id ? 'PATCH' : 'POST', body)
      if (!res.ok) throw new Error()
      setAddrForm(null)
      reloadAddresses()
    } catch { setAccountMsg('Could not save that address.') }
  }

  async function deleteAddress(addressId) {
    if (!window.confirm('Delete this address?')) return
    try {
      const res = await authSend(`/addresses/${addressId}`, 'DELETE')
      if (!res.ok && res.status !== 204) throw new Error()
      reloadAddresses()
    } catch { setAccountMsg('Could not delete that address.') }
  }

  async function makeDefaultAddress(addressId) {
    try {
      await authSend(`/addresses/${addressId}`, 'PATCH', { is_default: true })
      reloadAddresses()
    } catch { setAccountMsg('Could not update the default address.') }
  }

  async function loadCards() {
    setCardsBusy(true)
    try {
      const data = await responseJson(await authGet('/billing/payment-methods'))
      setCards(data.data ?? [])
    } catch { setCards([]) }
    finally { setCardsBusy(false) }
  }

  async function deleteCard(pmId) {
    if (!window.confirm('Remove this card?')) return
    setCardsBusy(true)
    try {
      const res = await authSend(`/billing/payment-methods/${pmId}`, 'DELETE')
      if (!res.ok && res.status !== 204) throw new Error()
      await loadCards()
    } catch { setAccountMsg('Could not remove that card.'); setCardsBusy(false) }
  }

  async function makeDefaultCard(pmId) {
    setCardsBusy(true)
    try {
      const res = await authSend(`/billing/payment-methods/${pmId}/default`, 'POST')
      if (!res.ok && res.status !== 204) throw new Error()
      await loadCards()
    } catch { setAccountMsg('Could not set the default card.'); setCardsBusy(false) }
  }

  async function openSupport(order) {
    if (!localStorage.getItem('gdp_token')) { setAuthMode('login'); setAuthMessage('Sign in to contact support.'); return }
    setSupportMsg('')
    setSupportView('list')
    loadThreads()
    if (!orders.length) authGet('/orders').then(responseJson).then((d) => setOrders(d.data ?? [])).catch(() => {})
    if (order) { setSupportForm({ about_order: true, order_id: String(order.id), issue_type: 'item_missing', message: '' }); setSupportView('new') }
  }

  function loadThreads() {
    authGet('/support/threads').then(responseJson).then((d) => { setThreads(d.data ?? []); markThreadsSeen(d.data ?? []) }).catch(() => {})
  }

  async function openThread(id) {
    setSupportMsg('')
    try {
      const data = await responseJson(await authGet(`/support/threads/${id}`))
      setSupportView(data.data)
      markThreadsSeen([data.data])
    } catch { setSupportMsg('Could not open that conversation.') }
  }

  async function submitSupport() {
    if (supportForm.about_order && !supportForm.order_id) { setSupportMsg('Select which order this is about.'); return }
    if (!supportForm.message.trim()) { setSupportMsg('Add a message describing the problem.'); return }
    setSupportBusy(true); setSupportMsg('')
    try {
      const body = { issue_type: supportForm.issue_type, message: supportForm.message.trim() }
      if (supportForm.about_order && supportForm.order_id) body.order_id = Number(supportForm.order_id)
      if (supportPhotos.length) body.attachments = supportPhotos
      const response = await authPost('/support/threads', body)
      const data = await responseJson(response)
      if (!response.ok) throw new Error(data.message ?? 'Could not send your request.')
      setSupportForm({ about_order: false, order_id: '', issue_type: 'item_missing', message: '' })
      setSupportPhotos([])
      setSupportView(data.data)
      loadThreads()
    } catch (error) { setSupportMsg(error.message) } finally { setSupportBusy(false) }
  }

  async function sendSupportReply() {
    const body = supportReply.trim()
    if ((!body && !supportPhotos.length) || typeof supportView !== 'object' || !supportView) return
    setSupportBusy(true)
    try {
      const response = await authPost(`/support/threads/${supportView.id}/messages`, { body, ...(supportPhotos.length ? { attachments: supportPhotos } : {}) })
      const data = await responseJson(response)
      if (!response.ok) throw new Error(data.message ?? 'Message not sent.')
      setSupportReply('')
      setSupportPhotos([])
      setSupportView(data.data)
    } catch (error) { setSupportMsg(error.message) } finally { setSupportBusy(false) }
  }

  // Lets the customer wrap up a conversation on their side — leaves a note
  // for the admin reading the thread later, then closes the widget.
  async function endChat() {
    if (typeof supportView !== 'object' || !supportView) return
    setSupportBusy(true)
    try {
      const response = await authPost(`/support/threads/${supportView.id}/end`, {})
      const data = await responseJson(response)
      if (!response.ok) throw new Error(data.message ?? 'Could not end the chat.')
      loadThreads()
      setSupportView(null)
    } catch (error) { setSupportMsg(error.message) } finally { setSupportBusy(false) }
  }

  // Unread = a thread whose latest message is from staff (incl. the delivery
  // rider) and the customer hasn't opened it since. "Seen" timestamps per thread
  // live in localStorage.
  const readSeen = () => { try { return JSON.parse(localStorage.getItem('gdp_support_seen') || '{}') } catch { return {} } }
  const threadHasStaffUnread = (t, seen) => {
    const s = t.last_staff_message_at
    if (!s) return false
    if (t.last_message_at && new Date(s) < new Date(t.last_message_at)) return false // customer sent the latest
    return seen[t.id] !== s
  }
  const markThreadsSeen = (list) => {
    const seen = readSeen()
    ;(list ?? []).forEach((t) => { if (t.last_staff_message_at) seen[t.id] = t.last_staff_message_at })
    try { localStorage.setItem('gdp_support_seen', JSON.stringify(seen)) } catch { /* private mode */ }
    setSupportUnread(0)
  }

  // Background check for new staff/rider messages while the support panel is
  // closed, so the header "Help" link can show a dot.
  useEffect(() => {
    if (!currentUser) { setSupportUnread(0); return }
    let stopped = false
    const check = async () => {
      if (supportView) return // panel open — it manages "seen" itself
      try {
        const d = await responseJson(await authGet('/support/threads'))
        if (stopped) return
        const seen = readSeen()
        setSupportUnread((d.data ?? []).filter((t) => threadHasStaffUnread(t, seen)).length)
      } catch { /* keep last */ }
    }
    check()
    const timer = setInterval(check, 20000)
    return () => { stopped = true; clearInterval(timer) }
  }, [currentUser, supportView])

  // Poll the open conversation for new staff replies.
  const activeThreadId = (supportView && typeof supportView === 'object') ? supportView.id : null
  useEffect(() => {
    if (!activeThreadId) return
    const timer = setInterval(async () => {
      try {
        const response = await fetch(`${API_URL}/support/threads/${activeThreadId}`, { headers: { Accept: 'application/json', Authorization: `Bearer ${localStorage.getItem('gdp_token')}` } })
        const data = await responseJson(response)
        setSupportView((current) => (current && typeof current === 'object' && current.id === activeThreadId ? data.data : current))
      } catch { /* keep last */ }
    }, 4000)
    return () => clearInterval(timer)
  }, [activeThreadId])

  async function switchToCashOnDelivery() {
    const current = order
    const token = localStorage.getItem('gdp_token')
    if (!token || !current) return
    try {
      const response = await fetch(`${API_URL}/orders/${current.id}/payment-method`, { method: 'PATCH', headers: { 'Content-Type': 'application/json', Accept: 'application/json', Authorization: `Bearer ${token}` }, body: JSON.stringify({ payment_method: 'cod' }) })
      const data = await responseJson(response)
      if (!response.ok) throw new Error(data.message ?? 'Could not switch to cash on delivery.')
      setOrder({ ...current, ...data.data, clientSecret: null, cod: true })
      setCart([])
      setOrders([])
    } catch (error) { setOrder((o) => o ? { ...o, switchError: error.message } : o) }
  }

  async function finalizePayment() {
    const paid = order
    setOrder((current) => current ? { ...current, clientSecret: null, paid: true } : current)
    const token = localStorage.getItem('gdp_token')
    if (!token || !paid) return
    // Reconcile with the server so the order is confirmed even when the Stripe
    // CLI webhook listener is not running.
    await fetch(`${API_URL}/orders/${paid.id}/payment-intent`, { method: 'POST', headers: { Accept: 'application/json', Authorization: `Bearer ${token}` } }).catch(() => {})
    setOrders([])
  }

  function applyLocation(address, { close = true } = {}) {
    setLocation(address)
    // The location's country picks the country store (the header picker can still override).
    if (address?.country) switchMarket(address.country, { fromLocation: true })
    localStorage.setItem('gdp_location', JSON.stringify(address))
    // Seed the checkout address text. line1 falls back to the display name so it
    // is never blank; city/state/postcode are best-effort now.
    const line1 = address.line1 || (address.full || address.label || '').split(',').slice(0, 3).join(', ').trim()
    if (line1 || address.city || address.postal_code) {
      setCheckoutForm((form) => ({
        ...form,
        line1: line1 || form.line1,
        city: address.city || form.city,
        state: address.state || form.state,
        postal_code: address.postal_code || form.postal_code,
      }))
    }
    if (close) {
      setLocationOpen(false)
      setLocationResults([])
      setLocationQuery('')
      setLocationMsg('')
    }
  }

  // Reverse geocode a point via the backend; the returned lat/lon is forced to
  // the exact point asked for so the delivery-radius check uses it verbatim.
  async function reverseGeocode(lat, lng) {
    try {
      const data = await responseJson(await fetch(`${API_URL}/geocode/reverse?lat=${lat}&lng=${lng}`, { headers: { Accept: 'application/json' } }))
      if (data.data) return { ...data.data, lat, lon: lng }
    } catch { /* fall through to a bare pin */ }
    return { label: 'Pinned location', full: '', line1: '', city: '', state: '', postal_code: '', lat, lon: lng }
  }

  async function detectLocation() {
    setLocationMsg('')
    if (!navigator.geolocation) { setLocationMsg('This browser cannot detect location.'); return }
    setLocationBusy(true)
    navigator.geolocation.getCurrentPosition(async (pos) => {
      try {
        const { latitude, longitude } = pos.coords
        const address = await reverseGeocode(latitude, longitude)
        mapRef.current?.setView([latitude, longitude], 16)
        applyLocation(address)
      } catch {
        setLocationMsg('Could not read that location.')
      } finally {
        setLocationBusy(false)
      }
    }, (error) => {
      setLocationBusy(false)
      setLocationMsg(error.code === 1 ? 'Location permission was denied.' : 'Could not get your location.')
    }, { enableHighAccuracy: true, timeout: 10000 })
  }

  async function searchLocation(event) {
    event.preventDefault()
    const term = locationQuery.trim()
    if (term.length < 3) return
    setLocationBusy(true)
    setLocationMsg('')
    try {
      // Bias results to whatever the map is looking at (else the saved location),
      // so a multi-store shop finds addresses near the right city's store.
      const c = mapRef.current?.getCenter?.() ?? (locationRef.current?.lat != null ? { lat: locationRef.current.lat, lng: locationRef.current.lon } : null)
      const near = c ? `&lat=${c.lat}&lng=${c.lng}` : ''
      const data = await responseJson(await fetch(`${API_URL}/geocode/search?q=${encodeURIComponent(term)}${near}`, { headers: { Accept: 'application/json' } }))
      const results = Array.isArray(data.data) ? data.data : []
      setLocationResults(results.slice(1))
      if (!results.length) {
        setLocationMsg('No match for that address — drop the pin on the map instead.')
      } else {
        // Jump the map and pin straight to the best match; the user fine-tunes
        // by dragging, or picks one of the other matches below.
        mapRef.current?.setView([Number(results[0].lat), Number(results[0].lon)], 16)
        applyLocation(results[0], { close: false })
      }
    } catch {
      setLocationMsg('Address lookup is unavailable right now.')
    } finally {
      setLocationBusy(false)
    }
  }

  // Build the Leaflet map while the location modal is open. Leaflet and its CSS
  // are loaded on demand so they stay out of the initial bundle.
  useEffect(() => {
    if (!locationOpen) return
    let cancelled = false
    ;(async () => {
      const [{ default: L }] = await Promise.all([
        import('leaflet'),
        import('leaflet/dist/leaflet.css'),
      ])
      if (cancelled || !mapNodeRef.current || mapRef.current) return

      const [icon2x, icon1x, shadow] = await Promise.all([
        import('leaflet/dist/images/marker-icon-2x.png'),
        import('leaflet/dist/images/marker-icon.png'),
        import('leaflet/dist/images/marker-shadow.png'),
      ])
      const icon = L.icon({
        iconRetinaUrl: icon2x.default, iconUrl: icon1x.default, shadowUrl: shadow.default,
        iconSize: [25, 41], iconAnchor: [12, 41], popupAnchor: [1, -34], shadowSize: [41, 41],
      })

      const loc = locationRef.current
      const firstStore = stores.find((s) => s.latitude != null && s.longitude != null)
      const start = loc?.lat != null && loc?.lon != null
        ? [Number(loc.lat), Number(loc.lon)]
        : firstStore ? [Number(firstStore.latitude), Number(firstStore.longitude)] : [20, 0]

      const map = L.map(mapNodeRef.current, { zoomControl: true, scrollWheelZoom: false }).setView(start, (loc?.lat != null || firstStore) ? 14 : 2)
      mapRef.current = map
      L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        maxZoom: 19, attribution: '&copy; OpenStreetMap contributors',
      }).addTo(map)

      stores.forEach((s) => {
        if (s.latitude == null || s.longitude == null) return
        L.circle([Number(s.latitude), Number(s.longitude)], {
          radius: Number(s.delivery_radius_km) * 1000, color: '#3f7d43', weight: 1, fillColor: '#3f7d43', fillOpacity: 0.06,
        }).addTo(map)
      })

      const marker = L.marker(start, { draggable: true, icon }).addTo(map)
      markerRef.current = marker
      const pick = (latlng) => {
        marker.setLatLng(latlng)
        setLocationMsg('')
        reverseGeocode(latlng.lat, latlng.lng).then((address) => applyLocationRef.current?.(address, { close: false }))
      }
      marker.on('dragend', () => pick(marker.getLatLng()))
      map.on('click', (event) => pick(event.latlng))
      setTimeout(() => map.invalidateSize(), 0)
    })()

    return () => {
      cancelled = true
      if (mapRef.current) { mapRef.current.remove(); mapRef.current = null; markerRef.current = null }
    }
  }, [locationOpen, stores])

  // Keep the map marker on the current location (search jump, detect, saved address).
  useEffect(() => {
    if (!mapRef.current || !markerRef.current || location?.lat == null || location?.lon == null) return
    const point = [Number(location.lat), Number(location.lon)]
    markerRef.current.setLatLng(point)
    mapRef.current.setView(point, Math.max(mapRef.current.getZoom(), 15))
  }, [location?.lat, location?.lon])

  // A banner or homepage tile: an in-app category link wins, else a custom URL.
  function openHomeTarget(target) {
    if (target.category_slug) {
      const cat = categories.find((c) => c.slug === target.category_slug)
      if (cat) { setActiveCategory(cat.name); setQuery(''); return }
    }
    if (target.link_url) window.open(target.link_url, '_blank', 'noopener')
  }

  // Curated homepage tiles when an admin has set them; otherwise every category.
  const catBySlug = Object.fromEntries(categories.map((c) => [c.slug, c]))
  const homeTileList = homeTiles.length
    ? homeTiles
    : categories.filter((c) => c.show_on_home !== false).map((c) => ({ id: `cat-${c.id}`, title: c.name, image_url: c.image_url, category_slug: c.slug, link_url: null }))
  const tileMeta = (tile) => {
    const name = tile.category_slug ? catBySlug[tile.category_slug]?.name : null
    return {
      label: tile.title || name || 'Shop',
      count: tile.count ?? (name ? (categoryCounts[name] ?? 0) : null),
      samples: name ? (categorySamples[name] ?? []) : [],
    }
  }

  // Hide a carousel's arrow once it can't scroll further that way — checked
  // on scroll, on resize, and whenever the underlying list changes.
  useEffect(() => {
    const pairs = [
      [homeCatsRef, homeCatsWrapRef],
      [dealsCatsRef, dealsCatsWrapRef],
      [dealsExclusiveRef, dealsExclusiveWrapRef],
    ]
    const update = (scrollEl, wrapEl) => {
      if (!scrollEl || !wrapEl) return
      wrapEl.classList.toggle('at-start', scrollEl.scrollLeft <= 1)
      wrapEl.classList.toggle('at-end', scrollEl.scrollLeft >= scrollEl.scrollWidth - scrollEl.clientWidth - 1)
    }
    const entries = pairs
      .filter(([scrollRef]) => scrollRef.current)
      .map(([scrollRef, wrapRef]) => {
        const handler = () => update(scrollRef.current, wrapRef.current)
        scrollRef.current.addEventListener('scroll', handler, { passive: true })
        handler()
        return { scrollRef, handler }
      })
    const onResize = () => entries.forEach(({ handler }) => handler())
    window.addEventListener('resize', onResize)
    return () => {
      entries.forEach(({ scrollRef, handler }) => scrollRef.current?.removeEventListener('scroll', handler))
      window.removeEventListener('resize', onResize)
    }
  }, [homeTileList.length, dealsCategory, dealsExclusive.length])

  // Five stars, each filled in proportion to how close `rating` is to that
  // star's position — e.g. rating 3.4 renders 3 full stars and a ~40% fourth.
  function starIcons(rating, keyPrefix) {
    return [0, 1, 2, 3, 4].map((i) => {
      const fillPct = Math.max(0, Math.min(1, rating - i)) * 100
      const gradId = `${keyPrefix}-${i}`
      return <svg key={i} aria-hidden width="11" height="11" viewBox="0 0 24 24">
        <defs><linearGradient id={gradId}><stop offset={`${fillPct}%`} stopColor="currentColor" /><stop offset={`${fillPct}%`} stopColor="transparent" /></linearGradient></defs>
        <path d="M12 2l2.9 6.4 7 .8-5.2 4.8 1.4 6.9L12 17.6 5.9 20.9l1.4-6.9L2.1 9.2l7-.8z" fill={`url(#${gradId})`} stroke="currentColor" strokeWidth="1" strokeLinejoin="round" />
      </svg>
    })
  }

  function productCard(product) {
    const { hasVariants, options, chosen, variant, unitPrice, compareAt, onSale, pctOff, stock, key, qty, img } = variantView(product)
    return <article className={stock === 0 ? 'pcard sold-out' : 'pcard'} key={product.id}>
      <button className="pcard-img" type="button" aria-label={`View details for ${product.name}`} onClick={() => openProduct(product)}
        onMouseEnter={(event) => { const v = event.currentTarget.querySelector('.pcard-video'); if (v) { v.currentTime = 0; v.play().catch(() => {}); v.classList.add('is-playing') } }}
        onMouseLeave={(event) => { const v = event.currentTarget.querySelector('.pcard-video'); if (v) { v.pause(); v.classList.remove('is-playing') } }}
      >{stock === 0 && <span className="pcard-oos">Out of stock</span>}{onSale && stock !== 0 && <span className="pcard-off">{pctOff}% off</span>}{imgPlaceholder()}{img && <img src={mediaUrl(img)} alt="" loading="lazy" onError={(event) => { event.currentTarget.style.display = 'none' }} />}{product.video_url && <>
          <video className="pcard-video" src={mediaUrl(product.video_url)} muted loop playsInline preload="none" />
          <span className="pcard-play-badge" aria-hidden><svg viewBox="0 0 24 24" width="12" height="12" fill="currentColor"><path d="M8 5v14l11-7z" /></svg></span>
        </>}{product.description && <span className="pcard-desc-tip">{product.description}</span>}</button>
      <p className="pcard-cat">{product.category?.name ?? 'Uncategorized'}</p>
      <h3>{variantTitle(product.name, variant?.label)}</h3>
      {hasVariants && <select className="pcard-variant" aria-label={`${product.name} option`} value={String(chosen?.id ?? '')} onChange={(event) => setPickedVariant((current) => ({ ...current, [product.id]: event.target.value }))}>{options.map((o) => <option key={o.id === '' ? 'base' : o.id} value={String(o.id)}>{o.label} — {price(o.price_cents)}</option>)}</select>}
      <div className="pcard-foot"><span className="pcard-price">{onSale ? <><strong className="on-sale">{price(unitPrice)}</strong><span className="pcard-compare-at">Compare at: <s>{price(compareAt)}</s> <i className="pcard-info" title="The higher of the manufacturer's list price or a recent selling price on NexTech.">?</i></span></> : <strong>{price(unitPrice)}</strong>}</span>{qty === 0
        ? <button className="add-btn" type="button" disabled={stock === 0} onClick={() => add(product, variant)}>{stock === 0 ? 'OUT' : <svg aria-hidden viewBox="0 0 1024 1024" className="add-btn-icon"><path d="M409.7 752.4c31.8 0 57.6 25.8 57.5 57.6 0 31.8-25.8 57.6-57.5 57.6-31.8 0-57.6-25.8-57.6-57.6 0-31.8 25.8-57.6 57.6-57.6z m327.5 0c31.8 0 57.6 25.8 57.6 57.6 0 31.8-25.8 57.6-57.6 57.6-31.8 0-57.6-25.8-57.5-57.6 0-31.8 25.8-57.6 57.5-57.6z m-541-563.2c21.6 0 40.7 4.8 60.2 16.6 20.9 12.6 37 31.5 47.1 55.9l3.6 9.7 1.5 6.2 18.5 113.1 31.4 199.2c2.9 17.9 17.5 31.7 35.1 33.7l4.9 0.3 347.2 0c18.3 0 34.2-12.3 39.1-30.1l1.1-5.2 48.6-260.5c4.5-24.3 27.9-40.4 52.3-35.8 22.3 4.2 37.9 24.3 36.5 47.1l-0.7 5.1-48.4 259.5c-9.7 60.2-59.9 105.6-120.8 109.2l-7.7 0.3-347.2 0c-63.8 0-118.1-46.2-128.5-109.4l-36.3-230.3-12.4-76.3-1-2.5c-2.1-4.9-4.7-8.4-7.5-10.6l-2.7-1.9c-3.3-2-6.8-3.1-10.1-3.5l-3.8-0.2-85.3 0c-24.7 0-44.8-20.1-44.8-44.8 0-22.7 16.9-41.7 39.6-44.5l5.2-0.3 85.3 0z m382.2-1.2c22.7 0 41.7 16.9 44.5 39.6l0.3 5.2 0 66.1 66.2 0c23.1 0 42.1 17.5 44.5 39.9l0.3 4.9c0 22.7-16.9 41.7-39.6 44.5l-5.2 0.3-66.2 0 0 66.1c0 23.1-17.5 42.1-39.9 44.6l-4.9 0.2c-22.7 0-41.7-16.9-44.4-39.5l-0.4-5.3 0-66.1-66.1 0c-23.1 0-42.1-17.5-44.5-39.9l-0.3-4.9c0-22.7 16.9-41.7 39.6-44.5l5.2-0.3 66.1 0 0-66.1c0-23.1 17.5-42.1 40-44.6l4.8-0.2z" /></svg>}</button>
        : <span className="stepper"><button type="button" aria-label="Remove one" onClick={() => updateQuantity(key, -1)}>&minus;</button><b>{qty}</b><button type="button" aria-label="Add one" disabled={stock != null && qty >= stock} onClick={() => updateQuantity(key, 1)}>+</button></span>}</div>
      {(product.units_sold > 0 || product.rating_count > 0) && <p className="pcard-rating">
        {product.units_sold > 0 && <span className="pcard-sold">{product.units_sold} sold</span>}
        {product.units_sold > 0 && product.rating_count > 0 && <span className="pcard-rating-sep">|</span>}
        {product.rating_count > 0 && <span className="pcard-rating-single">
          <svg aria-hidden width="12" height="12" viewBox="0 0 24 24" fill="currentColor"><path d="M12 2l2.9 6.4 7 .8-5.2 4.8 1.4 6.9L12 17.6 5.9 20.9l1.4-6.9L2.1 9.2l7-.8z" /></svg>
          {Number(product.rating_avg).toFixed(1)}({product.rating_count})
        </span>}
      </p>}
    </article>
  }

  const seeMoreArrow = <svg aria-hidden width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.5" strokeLinecap="round" strokeLinejoin="round"><polyline points="6 9 12 15 18 9" /></svg>

  const productGrid = <>
    <div className="product-grid" ref={productGridRef}>{visibleProducts.map(productCard)}{!visibleProducts.length && <p className="empty-state">Nothing here yet.</p>}</div>
    {hasMorePages && <button type="button" className="see-more-btn" disabled={loadingMore} onClick={loadMoreProducts}>{loadingMore ? 'Loading…' : <>See more {seeMoreArrow}</>}</button>}
    {!hasMorePages && visibleProducts.length > 0 && <p className="no-more-items"><i /><span>No more items.</span><i /></p>}
  </>

  // A shop page's strip lists only the categories that shop sells in (with
  // the shop's own counts), not the homepage selection.
  const shopTiles = shopInfo && shopInfo !== 'loading' && !shopInfo.missing
    ? (shopInfo.categories ?? []).map((c) => ({ id: `shop-cat-${c.id}`, title: c.name, image_url: c.image_url, category_slug: c.slug, link_url: null, count: c.count }))
    : []

  function categoryCarousel(scrollRef, dragRef, wrapRef, activeLabel, onSelect, showRecommended, pillStyle, tiles = homeTileList) {
    return tiles.length > 0 && <section className={pillStyle ? 'home-cats-wrap home-cats-wrap-pill' : 'home-cats-wrap'} aria-label="Shop by category" ref={wrapRef}>
      <button type="button" className="home-cats-arrow home-cats-arrow-left" aria-label="Scroll categories left" onClick={() => scrollRef.current?.scrollBy({ left: -400, behavior: 'smooth' })}><svg aria-hidden width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.5" strokeLinecap="round" strokeLinejoin="round"><polyline points="15 6 9 12 15 18" /></svg></button>
      <div className="home-cats" ref={scrollRef}
        onMouseDown={(event) => { dragRef.current = { down: true, moved: false, startX: event.pageX, scrollLeft: scrollRef.current.scrollLeft } }}
        onMouseMove={(event) => {
          const state = dragRef.current
          if (!state.down) return
          const delta = event.pageX - state.startX
          if (Math.abs(delta) > 4) state.moved = true
          event.preventDefault()
          scrollRef.current.scrollLeft = state.scrollLeft - delta
        }}
        onMouseUp={() => { dragRef.current.down = false }}
        onMouseLeave={() => { dragRef.current.down = false }}
        onClickCapture={(event) => { if (dragRef.current.moved) { event.preventDefault(); event.stopPropagation(); dragRef.current.moved = false } }}
      >
        {showRecommended && (pillStyle ? <button className="home-cat-pill" type="button" onClick={() => onSelect(null)}>Recommended</button> : <button className="home-cat" type="button" onClick={() => onSelect(null)}>
          <span className="home-cat-img" aria-hidden><svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2"><rect x="3" y="3" width="7" height="7" rx="1.5" /><rect x="14" y="3" width="7" height="7" rx="1.5" /><rect x="3" y="14" width="7" height="7" rx="1.5" /><rect x="14" y="14" width="7" height="7" rx="1.5" /></svg></span>
          <span className="home-cat-label">Recommended</span>
        </button>)}
        {tiles.map((tile) => {
          const meta = tileMeta(tile)
          const isActive = !!activeLabel && meta.label === activeLabel
          if (pillStyle) return <button className={isActive ? 'home-cat-pill active' : 'home-cat-pill'} type="button" key={tile.id} onClick={() => onSelect(isActive ? null : tile)}>{meta.label}</button>
          return <button className={isActive ? 'home-cat active' : 'home-cat'} type="button" key={tile.id} onClick={() => onSelect(isActive ? null : tile)}>
          <span className="home-cat-img" aria-hidden>{categoryEmoji(meta.label)}{tile.image_url && <img src={mediaUrl(tile.image_url)} alt="" loading="lazy" draggable={false} onError={(event) => { event.currentTarget.style.display = 'none' }} />}</span>
          <span className="home-cat-label">{meta.label}</span>
        </button>
        })}
      </div>
      <button type="button" className="home-cats-arrow home-cats-arrow-right" aria-label="Scroll categories right" onClick={() => scrollRef.current?.scrollBy({ left: 400, behavior: 'smooth' })}><svg aria-hidden width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.5" strokeLinecap="round" strokeLinejoin="round"><polyline points="9 6 15 12 9 18" /></svg></button>
    </section>
  }

  const chevronDown = <svg aria-hidden width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.5" strokeLinecap="round" strokeLinejoin="round"><polyline points="6 9 12 15 18 9" /></svg>
  const chevronRight = <svg aria-hidden width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.5" strokeLinecap="round" strokeLinejoin="round"><polyline points="9 6 15 12 9 18" /></svg>
  // Categories unticked "Show on homepage" in admin are hidden from the home
  // page's browsing (Categories menu, category rail) — still reachable by
  // search and direct links.
  const homeCategories = categories.filter((c) => c.show_on_home !== false)
  const groupCategories = (group) => (CATEGORY_GROUPS.find(([g]) => g === group)?.[1] ?? [])
    .map((name) => homeCategories.find((c) => c.name === name))
    .filter(Boolean)
  const menuGroups = CATEGORY_GROUPS.filter(([group]) => groupCategories(group).length > 0)
  const activeGroupCategories = groupCategories(menuGroups.some(([g]) => g === categoriesMenuGroup) ? categoriesMenuGroup : menuGroups[0]?.[0])
  const starGlyph = <svg aria-hidden width="14" height="14" viewBox="0 0 24 24" fill="currentColor"><path d="M12 2l2.9 6.4 7 .8-5.2 4.8 1.4 6.9L12 17.6 5.9 20.9l1.4-6.9L2.1 9.2l7-.8z" /></svg>
  const flameGlyph = <svg aria-hidden width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round"><path d="M8.5 14.5A2.5 2.5 0 0 0 11 12c0-1.38-.5-2-1-3-1.072-2.143-.224-4.054 2-6 .5 2.5 2 4.9 4 6.5 2 1.6 3 3.5 3 5.5a7 7 0 1 1-14 0c0-1.153.433-2.294 1-3a2.5 2.5 0 0 0 2.5 2.5z" /></svg>

  return <><div className="app-shell">
    <header className="topbar">
      <div className="promo-bar">
        <div className="promo-bar-items">
          {promoColumns.map((column, index) => {
            const message = column.messages[promoTick % column.messages.length]
            return column.href
              ? <a key={index} className="promo-bar-item" href={column.href} target="_blank" rel="noopener noreferrer">
                {column.icon}
                <span className="promo-bar-item-copy" key={message.title}><b>{message.title}</b><small>{message.sub}</small></span>
              </a>
              : <button key={index} type="button" className="promo-bar-item" onClick={column.onClick}>
                {column.icon}
                <span className="promo-bar-item-copy" key={message.title}><b>{message.title}</b><small>{message.sub}</small></span>
              </button>
          })}
        </div>
      </div>
      <div className="topbar-row">
        <a className="brand" href={import.meta.env.BASE_URL || '/'} aria-label={`${branding?.store_name || 'NexTech'} home`}>{branding?.logo_url
          ? <img className="brand-logo" src={mediaUrl(branding.logo_url)} alt={branding?.store_name || 'NexTech'} />
          : <><span className="brand-mark">{(branding?.store_name || 'n').trim().charAt(0).toLowerCase() || 'n'}</span>{(branding?.store_name || 'nextech').toLowerCase()}</>}</a>
        <nav className="topbar-quicklinks" aria-label="Quick browse">
          <button type="button" className={dealsPage === 'best_selling' ? 'quicklink active' : 'quicklink'} onClick={() => openDeals('best_selling')}>{flameGlyph}Best-Selling</button>
          <button type="button" className={dealsPage === 'top_rated' ? 'quicklink active' : 'quicklink'} onClick={() => openDeals('top_rated')}>{starGlyph}5-Star Rated</button>
          <button type="button" className={dealsPage === 'newest' ? 'quicklink active' : 'quicklink'} onClick={() => openDeals('newest')}>New In</button>
        </nav>
        <div className={closedMenu === 'categories' ? 'nav-dropdown categories-dropdown menu-closed' : 'nav-dropdown categories-dropdown'} onMouseLeave={menuLeave}>
          <button type="button" className="nav-dropdown-trigger" aria-haspopup="true">Categories {chevronDown}</button>
          <div className="nav-dropdown-panel categories-panel" role="menu">
            <div className="nav-dropdown-panel-inner categories-panel-inner">
              <div className="categories-panel-left">
                {menuGroups.map(([group]) => <button type="button" key={group} className={(menuGroups.some(([g]) => g === categoriesMenuGroup) ? categoriesMenuGroup : menuGroups[0]?.[0]) === group ? 'categories-group active' : 'categories-group'} onMouseEnter={() => setCategoriesMenuGroup(group)} onFocus={() => setCategoriesMenuGroup(group)}>{group}{chevronRight}</button>)}
              </div>
              <div className="categories-panel-right">
                <div className="categories-panel-right-head">All {menuGroups.some(([g]) => g === categoriesMenuGroup) ? categoriesMenuGroup : menuGroups[0]?.[0]} {chevronRight}</div>
                <div className="categories-panel-grid">
                  {activeGroupCategories.map((cat) => <button type="button" role="menuitem" key={cat.id} className="categories-panel-item" onClick={menuAction('categories', () => selectCategoryFromMenu(cat))}>
                    <span className="categories-panel-item-img" aria-hidden>{categoryEmoji(cat.name)}{cat.image_url && <img src={mediaUrl(cat.image_url)} alt="" loading="lazy" onError={(event) => { event.currentTarget.style.display = 'none' }} />}</span>
                    <span className="categories-panel-item-label" title={cat.name}>{cat.name}</span>
                  </button>)}
                </div>
              </div>
            </div>
          </div>
        </div>
        <label className="searchbar">
          <input aria-label="Search products" value={query} onChange={(event) => setQuery(event.target.value)} placeholder="Search for phones, laptops, headphones…" />
          <button type="button" className="searchbar-btn" aria-label="Search" onClick={scrollToProductGrid}><svg aria-hidden width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.5" strokeLinecap="round" strokeLinejoin="round"><circle cx="11" cy="11" r="7" /><line x1="21" y1="21" x2="16.65" y2="16.65" /></svg></button>
        </label>
        <div className="topbar-actions">
          {markets.length > 1 && <label className="market-picker" title="Shopping in">
            <span aria-hidden>{flag(activeMarket)}</span>
            <select aria-label="Country" value={activeMarket} onChange={(event) => switchMarket(event.target.value)}>
              {markets.map((m) => <option key={m.code} value={m.code}>{m.name} · {m.currency.toUpperCase()}</option>)}
            </select>
          </label>}
          {currentUser ? <div className={closedMenu === 'account' ? 'nav-dropdown account-dropdown menu-closed' : 'nav-dropdown account-dropdown'} onMouseLeave={menuLeave}>
            <button type="button" className="link-btn account-trigger" aria-haspopup="true">
              <span className="account-avatar" aria-hidden>{(currentUser.name || currentUser.email || '?').trim().charAt(0).toUpperCase()}</span>
              <span className="account-trigger-copy"><small>Hello, {(currentUser.name || currentUser.email || 'there').split(' ')[0]}</small><b>Orders &amp; Account</b></span>
            </button>
            <div className="nav-dropdown-panel account-panel" role="menu">
              <div className="nav-dropdown-panel-inner">
                <button type="button" role="menuitem" className="menu-icon-item" onClick={menuAction('account', () => { setOrdersOpen(true); setOrdersLoading(true); setOrders([]); setOrdersMessage('') })}><svg aria-hidden width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round"><path d="M6 3h9l5 5v13a1 1 0 0 1-1 1H6a1 1 0 0 1-1-1V4a1 1 0 0 1 1-1z" /><path d="M14 3v5h5" /><line x1="8" y1="13" x2="16" y2="13" /><line x1="8" y1="17" x2="13" y2="17" /></svg>Orders</button>
                <button type="button" role="menuitem" className="menu-icon-item" onClick={menuAction('account', () => openAccount('profile'))}><svg aria-hidden width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round"><circle cx="12" cy="8" r="4" /><path d="M4 21c0-4.4 3.6-8 8-8s8 3.6 8 8" /></svg>Account</button>
                {currentUser.is_admin && <button type="button" role="menuitem" className="menu-icon-item" onClick={() => { window.location.href = `${import.meta.env.BASE_URL}admin` }}><svg aria-hidden width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round"><circle cx="12" cy="12" r="3" /><path d="M19.4 15a1.7 1.7 0 0 0 .3 1.9l.1.1a2 2 0 1 1-2.8 2.8l-.1-.1a1.7 1.7 0 0 0-1.9-.3 1.7 1.7 0 0 0-1 1.5V21a2 2 0 1 1-4 0v-.1a1.7 1.7 0 0 0-1-1.6 1.7 1.7 0 0 0-1.9.3l-.1.1a2 2 0 1 1-2.8-2.8l.1-.1a1.7 1.7 0 0 0 .3-1.9 1.7 1.7 0 0 0-1.5-1H3a2 2 0 1 1 0-4h.1a1.7 1.7 0 0 0 1.6-1 1.7 1.7 0 0 0-.3-1.9l-.1-.1a2 2 0 1 1 2.8-2.8l.1.1a1.7 1.7 0 0 0 1.9.3H9a1.7 1.7 0 0 0 1-1.5V3a2 2 0 1 1 4 0v.1a1.7 1.7 0 0 0 1 1.5 1.7 1.7 0 0 0 1.9-.3l.1-.1a2 2 0 1 1 2.8 2.8l-.1.1a1.7 1.7 0 0 0-.3 1.9V9a1.7 1.7 0 0 0 1.5 1H21a2 2 0 1 1 0 4h-.1a1.7 1.7 0 0 0-1.5 1z" /></svg>Admin</button>}
                {currentUser.is_rider && <button type="button" role="menuitem" className="menu-icon-item" onClick={() => { window.location.href = `${import.meta.env.BASE_URL}rider` }}><svg aria-hidden width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round"><rect x="1" y="7" width="14" height="10" rx="1" /><path d="M15 10h4l3 3v4h-7z" /><circle cx="6" cy="19" r="2" /><circle cx="17" cy="19" r="2" /></svg>Deliveries</button>}
                <button type="button" role="menuitem" className="menu-icon-item" onClick={logout}><svg aria-hidden width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4" /><polyline points="16 17 21 12 16 7" /><line x1="21" y1="12" x2="9" y2="12" /></svg>Log out</button>
              </div>
            </div>
          </div> : <button className="link-btn" type="button" onClick={() => { setAuthMode('login'); setAuthMessage('') }}>Sign in</button>}
          <div className={closedMenu === 'support' ? 'nav-dropdown support-dropdown menu-closed' : 'nav-dropdown support-dropdown'} onMouseLeave={menuLeave}>
            <button type="button" className={supportUnread ? 'link-btn has-dot' : 'link-btn'} aria-haspopup="true">Support{supportUnread ? <span className="link-dot" aria-label={`${supportUnread} new message${supportUnread === 1 ? '' : 's'}`} /> : null}</button>
            <div className="nav-dropdown-panel support-panel" role="menu">
              <div className="nav-dropdown-panel-inner">
                <button type="button" role="menuitem" className="menu-icon-item" onClick={menuAction('support', () => openPage('support-center'))}><svg aria-hidden width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round"><path d="M3 12a9 9 0 0 1 18 0" /><path d="M3 12v5a2 2 0 0 0 2 2h1v-7H4a1 1 0 0 0-1 1z" /><path d="M21 12v5a2 2 0 0 1-2 2h-1v-7h2a1 1 0 0 1 1 1z" /></svg>Support center</button>
                <button type="button" role="menuitem" className="menu-icon-item" onClick={menuAction('support', () => openPage('safety-center'))}><svg aria-hidden width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round"><path d="M12 3l7 3v6c0 4.5-3 7.5-7 9-4-1.5-7-4.5-7-9V6z" /></svg>Safety center</button>
                <button type="button" role="menuitem" className="menu-icon-item" onClick={menuAction('support', () => openSupport())}><svg aria-hidden width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round"><path d="M21 11.5a8.38 8.38 0 0 1-8.5 8.5 8.5 8.5 0 0 1-4-1L3 20l1-5.5A8.38 8.38 0 0 1 3 11.5 8.5 8.5 0 0 1 11.5 3 8.38 8.38 0 0 1 21 11.5z" /></svg>Chat with us</button>
                <button type="button" role="menuitem" className="menu-icon-item" onClick={menuAction('support', () => openPage('purchase-protection'))}><svg aria-hidden width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round"><path d="M12 3l7 3v6c0 4.5-3 7.5-7 9-4-1.5-7-4.5-7-9V6z" /><polyline points="9 12 11 14 15 10" /></svg>Purchase protection</button>
                <button type="button" role="menuitem" className="menu-icon-item" onClick={menuAction('support', () => openPage('privacy'))}><svg aria-hidden width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round"><rect x="4" y="10" width="16" height="10" rx="2" /><path d="M8 10V7a4 4 0 0 1 8 0v3" /></svg>Privacy policy</button>
                <button type="button" role="menuitem" className="menu-icon-item" onClick={menuAction('support', () => openPage('terms'))}><svg aria-hidden width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round"><path d="M6 3h9l5 5v13a1 1 0 0 1-1 1H6a1 1 0 0 1-1-1V4a1 1 0 0 1 1-1z" /><path d="M14 3v5h5" /><line x1="8" y1="13" x2="16" y2="13" /><line x1="8" y1="17" x2="13" y2="17" /></svg>Terms of use</button>
              </div>
            </div>
          </div>
          <div className="region-pill" aria-hidden="true"><span>&#127482;&#127480;</span> English</div>
          <button className="cart-pill" type="button" onClick={() => setCartOpen(true)} aria-label={`Cart with ${cartCount} items`}>
            <svg aria-hidden width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round"><circle cx="9" cy="21" r="1" /><circle cx="20" cy="21" r="1" /><path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6" /></svg>
            {cartCount > 0 && <b>{cartCount}</b>}
          </button>
        </div>
      </div>
    </header>
    <div className="menu-scrim" aria-hidden="true" />
    <main className="catalog" ref={mainRef}>
      {pageView ? (() => {
        const withSections = pageView !== 'loading' && Array.isArray(pageView.sections) && pageView.sections.length > 0
        const hasBanner = pageView !== 'loading' && !!pageView.banner_image
        return (
        <article className={`page-view${withSections ? ' page-view-wide' : (hasBanner ? ' has-banner' : '')}`}>
          <button type="button" className="page-back" onClick={closePage}>&larr; Back to shopping</button>
          {pageView === 'loading'
            ? <div className="empty-state">Loading…</div>
            : <>{pageView.banner_image
                ? <div className="page-hero"><img src={mediaUrl(pageView.banner_image)} alt="" /><h1>{pageView.title}</h1></div>
                : <h1>{pageView.title}</h1>}{withSections
                ? <div className="page-sections">{pageView.sections.map((section, index) => <PageSection key={index} section={section} />)}</div>
                : <div className="page-content" dangerouslySetInnerHTML={{ __html: renderMarkdown(pageView.content) }} />}</>}
        </article>
        )
      })() : dealsPage ? (() => {
        const dealsLabel = dealsPage === 'lightning' ? 'Lightning Deals'
          : dealsPage === 'unbeatable' ? 'Unbeatable Deals'
          : dealsPage === 'exclusive' ? 'Exclusive Offer'
          : dealsPage === 'best_selling' ? 'Best-Selling'
          : dealsPage === 'top_rated' ? '5-Star Rated'
          : dealsPage === 'newest' ? 'New In'
          : dealsPage === 'shop' ? (shopInfo && shopInfo !== 'loading' && !shopInfo.missing ? shopInfo.name : 'Shop')
          : dealsCategory || 'Category'
        return <article className={`deals-page deals-page-${dealsPage}`}>
          <button type="button" className="page-back" onClick={closeDeals}>&larr; Back to shopping</button>
          <nav className="deals-breadcrumb" aria-label="Breadcrumb">
            <a href={import.meta.env.BASE_URL || '/'}>Home</a> <span aria-hidden>&rsaquo;</span> <span>{dealsLabel}</span>
          </nav>
          {dealsPage === 'shop' ? (
            shopInfo === 'loading' || !shopInfo ? <div className="empty-state">Loading…</div>
              : shopInfo.missing ? <div className="empty-state">This shop isn&rsquo;t open right now.</div>
              : <header className="shop-hero" style={shopInfo.banner_url ? { backgroundImage: `url(${mediaUrl(shopInfo.banner_url)})` } : undefined}>
                  <div className="shop-hero-inner">
                    {shopInfo.logo_url ? <img className="shop-hero-logo" src={mediaUrl(shopInfo.logo_url)} alt="" /> : <span className="shop-hero-logo placeholder" aria-hidden>{shopInfo.name.slice(0, 1).toUpperCase()}</span>}
                    <div className="shop-hero-text">
                      <h1>{shopInfo.name}</h1>
                      <p>{[shopInfo.category, `${shopInfo.products_count} product${shopInfo.products_count === 1 ? '' : 's'}`, shopInfo.since ? `on NexTech since ${new Date(shopInfo.since).toLocaleDateString([], { month: 'short', year: 'numeric' })}` : null].filter(Boolean).join(' · ')}</p>
                      {shopInfo.description && <p className="shop-hero-desc">{shopInfo.description}</p>}
                      {shopInfo.market && shopInfo.market !== activeMarket && <p className="shop-hero-market">This shop sells in {marketName(shopInfo.market)}. <button type="button" onClick={() => switchMarket(shopInfo.market)}>Shop in {marketName(shopInfo.market)}</button></p>}
                    </div>
                    <button type="button" className="shop-hero-share" onClick={copyShopLink}>{shopLinkCopied ? 'Link copied' : 'Share shop'}</button>
                  </div>
                </header>
          ) : <div className="deals-page-titlebar"><h1>{dealsLabel}</h1></div>}

          {dealsPage === 'lightning' && dealsExclusive.length > 0 && <section className="deals-exclusive" aria-label="Exclusive offers">
            <div className="deals-exclusive-head"><h2>Exclusive Offer{dealsExclusiveCapCents != null && <span className="deals-exclusive-cap">All under {price(dealsExclusiveCapCents)}</span>}</h2><button type="button" className="deals-see-all" onClick={() => openDeals('exclusive')}>See all <svg aria-hidden width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.5" strokeLinecap="round" strokeLinejoin="round"><polyline points="9 6 15 12 9 18" /></svg></button></div>
            <div className="deals-exclusive-wrap" ref={dealsExclusiveWrapRef}>
              <button type="button" className="home-cats-arrow home-cats-arrow-left" aria-label="Scroll left" onClick={() => dealsExclusiveRef.current?.scrollBy({ left: -(dealsExclusiveRef.current.clientWidth * 0.6), behavior: 'smooth' })}><svg aria-hidden width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.5" strokeLinecap="round" strokeLinejoin="round"><polyline points="15 6 9 12 15 18" /></svg></button>
              <div className="deals-grid deals-grid-lg" ref={dealsExclusiveRef}
                onMouseDown={(event) => { dealsExclusiveDrag.current = { down: true, moved: false, startX: event.pageX, scrollLeft: dealsExclusiveRef.current.scrollLeft } }}
                onMouseMove={(event) => {
                  const state = dealsExclusiveDrag.current
                  if (!state.down) return
                  const delta = event.pageX - state.startX
                  if (Math.abs(delta) > 4) state.moved = true
                  event.preventDefault()
                  dealsExclusiveRef.current.scrollLeft = state.scrollLeft - delta
                }}
                onMouseUp={() => { dealsExclusiveDrag.current.down = false }}
                onMouseLeave={() => { dealsExclusiveDrag.current.down = false }}
                onClickCapture={(event) => { if (dealsExclusiveDrag.current.moved) { event.preventDefault(); event.stopPropagation(); dealsExclusiveDrag.current.moved = false } }}
              >
                {dealsExclusive.map((product) => {
                  const { unitPrice, img } = variantView(product)
                  return <button type="button" className="deals-item" key={product.id} onClick={() => openProduct(product)}>
                    <span className="deals-item-img" aria-hidden>{imgPlaceholder()}{img && <img src={mediaUrl(img)} alt="" loading="lazy" onError={(event) => { event.currentTarget.style.display = 'none' }} />}</span>
                    <span className="deals-item-price">{price(unitPrice)}</span>
                    {product.rating_count > 0 && <span className="deals-item-rating">
                      <span className="deals-item-stars">{starIcons(Number(product.rating_avg), `dstar-${product.id}`)}</span>
                      <span className="deals-item-rating-count">{product.rating_count}</span>
                    </span>}
                  </button>
                })}
              </div>
              <button type="button" className="home-cats-arrow home-cats-arrow-right" aria-label="Scroll right" onClick={() => dealsExclusiveRef.current?.scrollBy({ left: dealsExclusiveRef.current.clientWidth * 0.6, behavior: 'smooth' })}><svg aria-hidden width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.5" strokeLinecap="round" strokeLinejoin="round"><polyline points="9 6 15 12 9 18" /></svg></button>
            </div>
          </section>}

          {dealsPage !== 'exclusive' && (dealsPage !== 'shop' || shopTiles.length > 1) && categoryCarousel(dealsCatsRef, dealsCatsDrag, dealsCatsWrapRef, dealsCategory, (tile) => setDealsCategory(tile ? tileMeta(tile).label : null), true, false, dealsPage === 'shop' ? shopTiles : homeTileList)}

          {dealsLoading ? <div className="empty-state">Loading…</div> : <>
            <div className="catalog-head"><h2>{dealsCategory || ''}</h2><span>{dealsProducts.length} items</span></div>
            <div className="product-grid" ref={productGridRef}>{dealsProducts.map(productCard)}{!dealsProducts.length && <p className="empty-state">Nothing here yet.</p>}</div>
            {dealsHasMore && <button type="button" className="see-more-btn" disabled={dealsLoadingMore} onClick={loadMoreDealsProducts}>{dealsLoadingMore ? 'Loading…' : <>View more {seeMoreArrow}</>}</button>}
            {!dealsHasMore && dealsProducts.length > 0 && <p className="no-more-items"><i /><span>No more items.</span><i /></p>}
          </>}
        </article>
      })() : productView ? (() => {
        if (productView === 'loading') return <article className="product-page"><div className="empty-state">Loading…</div></article>
        const product = productView
        const { hasVariants, options, chosen, variant, unitPrice, compareAt, onSale, pctOff, stock, key, qty } = variantView(product)
        const images = galleryImages(product)
        const activeImg = images[galleryIndex] ?? images[0]
        function pickVariant(opt) {
          setPickedVariant((current) => ({ ...current, [product.id]: String(opt.id) }))
          const idx = opt.image_url ? images.indexOf(opt.image_url) : -1
          if (idx >= 0) { setGalleryIndex(idx); setShowVideo(false) }
        }
        const reviews = generateReviews(product)
        const shownReviews = reviewsExpanded ? reviews : reviews.slice(0, 4)
        return <article className="product-page">
          <button type="button" className="page-back" onClick={closeProduct}>&larr; Back to shopping</button>
          <nav className="deals-breadcrumb" aria-label="Breadcrumb">
            <a href={import.meta.env.BASE_URL || '/'}>Home</a> <span aria-hidden>&rsaquo;</span>
            {product.category && <><a href={import.meta.env.BASE_URL || '/'} onClick={(event) => { event.preventDefault(); closeProduct(); setActiveCategory(product.category.name) }}>{product.category.name}</a> <span aria-hidden>&rsaquo;</span></>}
            <span>{product.name}</span>
          </nav>
          <div className="pdp-columns">
            <div className="pdp-left">
              <div className="pdp-gallery pm-gallery">
                {(images.length > 1 || product.video_url) && <div className="pm-thumbs">
                  {product.video_url && <button type="button" className={showVideo ? 'pm-thumb pm-thumb-video active' : 'pm-thumb pm-thumb-video'} onClick={() => setShowVideo(true)} aria-label="Play video">
                    <video src={mediaUrl(product.video_url)} muted playsInline preload="metadata" />
                    <span className="pm-thumb-play" aria-hidden><svg viewBox="0 0 24 24" width="14" height="14" fill="currentColor"><path d="M8 5v14l11-7z" /></svg></span>
                  </button>}
                  {images.map((url, i) => <button type="button" key={url} className={!showVideo && i === galleryIndex ? 'pm-thumb active' : 'pm-thumb'} onMouseEnter={() => { setGalleryIndex(i); setShowVideo(false) }} onClick={() => { setGalleryIndex(i); setShowVideo(false) }} aria-label={`Photo ${i + 1}`}><img src={mediaUrl(url)} alt="" /></button>)}
                </div>}
                <div className="pm-main-img" aria-hidden>{stock === 0 && <span className="pcard-oos">Out of stock</span>}{onSale && stock !== 0 && <span className="pcard-off">{pctOff}% off</span>}{imgPlaceholder()}{showVideo && product.video_url
                  ? <video src={mediaUrl(product.video_url)} controls autoPlay muted loop playsInline />
                  : activeImg && <img src={mediaUrl(activeImg)} alt="" onError={(event) => { event.currentTarget.style.display = 'none' }} />}</div>
              </div>

              {product.rating_count > 0 && <section className="pdp-reviews">
                <h2>Ratings &amp; reviews</h2>
                <div className="pdp-reviews-summary">
                  <span className="pdp-reviews-score">{Number(product.rating_avg).toFixed(1)}</span>
                  <span className="pcard-rating-single pdp-reviews-stars">{starIcons(Number(product.rating_avg), `pdpsum-${product.id}`)}</span>
                  <span className="muted">{product.rating_count} reviews &middot; verified purchases</span>
                </div>
                <div className="pdp-review-grid">{shownReviews.map((r) => <div className="pdp-review" key={r.id}>
                  <div className="pdp-review-head"><strong>{r.name}</strong><span className="pdp-review-date">{r.date}</span></div>
                  <span className="pcard-rating-single">{starIcons(r.rating, `pdprev-${product.id}-${r.id}`)}</span>
                  {r.verified && <span className="pdp-verified">Verified purchase</span>}
                  <p>{r.text}</p>
                </div>)}</div>
                {!reviewsExpanded && reviews.length > 4 && <button type="button" className="switch-auth pdp-see-all" onClick={() => setReviewsExpanded(true)}>See all {reviews.length} reviews</button>}
              </section>}

              <section className="pdp-details">
                <h2>Product details</h2>
                <dl>
                  <div><dt>Category</dt><dd>{product.category?.name ?? 'Uncategorized'}</dd></div>
                  {product.sku && <div><dt>SKU</dt><dd>{product.sku}</dd></div>}
                  {product.country_of_origin && <div><dt>Country of origin</dt><dd>{product.country_of_origin}</dd></div>}
                  {product.manufacturer_info && <div><dt>Manufacturer / importer</dt><dd>{product.manufacturer_info}</dd></div>}
                  <div><dt>Availability</dt><dd>{stock === 0 ? 'Out of stock' : 'In stock'}</dd></div>
                </dl>
              </section>

              {images.length > 0 && <section className="pdp-images">
                {images.map((url) => <img key={url} src={mediaUrl(url)} alt="" loading="lazy" onError={(event) => { event.currentTarget.style.display = 'none' }} />)}
              </section>}
            </div>
            <div className="pdp-right">
              <div className="pdp-buybox">
                <p className="pcard-cat">{product.category?.name ?? 'Uncategorized'}</p>
                {(() => {
                  const days = product.return_days ?? fees.return_window_days
                  return days != null && <p className="pdp-sold-by">{Number(days) === 0 ? 'Non-returnable item' : `Returns accepted within ${days} days of delivery`}</p>
                })()}
                {product.shop?.slug && product.shop.is_active && <p className="pdp-sold-by">Sold by <button type="button" onClick={() => openShop(product.shop.slug)}>{product.shop.name}</button></p>}
                <h1 id="pdp-title">{variantTitle(product.name, variant?.label)}</h1>
                {(product.units_sold > 0 || product.rating_count > 0) && <p className="pcard-rating pdp-rating">
                  {product.units_sold > 0 && <span className="pcard-sold">{product.units_sold} sold</span>}
                  {product.units_sold > 0 && product.rating_count > 0 && <span className="pcard-rating-sep">|</span>}
                  {product.rating_count > 0 && <span className="pcard-rating-single">{starIcons(Number(product.rating_avg), `pdpstar-${product.id}`)}<b>{Number(product.rating_avg).toFixed(1)}</b> ({product.rating_count})</span>}
                </p>}
                <div className="pm-price">{onSale ? <><strong className="on-sale">{price(unitPrice)}</strong>{taxInclusive ? <span className="pdp-mrp">MRP <s>{price(compareAt)}</s></span> : <s>{price(compareAt)}</s>}</> : <strong>{price(unitPrice)}</strong>}</div>{taxInclusive && <p className="pdp-tax-note">Inclusive of all taxes</p>}
                {hasVariants && <div className="pdp-swatches" role="radiogroup" aria-label="Choose an option">{options.map((o) => <button type="button" key={o.id === '' ? 'base' : o.id} className={String(chosen?.id ?? '') === String(o.id) ? 'pdp-swatch active' : 'pdp-swatch'} onClick={() => pickVariant(o)} title={`${o.label} — ${price(o.price_cents)}`}>
                  <span className="pdp-swatch-img">{(o.image_url || product.image_url) && <img src={mediaUrl(o.image_url || product.image_url)} alt="" />}</span>
                  <span className="pdp-swatch-label">{o.label}</span>
                </button>)}</div>}
                <p className="pm-desc">{product.description || 'No description available yet.'}</p>
                {qty === 0
                  ? <button className="add-btn pm-add pdp-add" type="button" disabled={stock === 0} onClick={() => add(product, variant)}>{stock === 0 ? 'OUT OF STOCK' : 'ADD TO CART'}</button>
                  : <span className="stepper pm-add pdp-add"><button type="button" aria-label="Remove one" onClick={() => updateQuantity(key, -1)}>&minus;</button><b>{qty}</b><button type="button" aria-label="Add one" disabled={stock != null && qty >= stock} onClick={() => updateQuantity(key, 1)}>+</button></span>}
              </div>
            </div>
          </div>
        </article>
      })() : <>
      {offline && <div className="api-note">Showing sample products while the API is offline.</div>}
      {outOfArea && <div className="area-note">{UNSERVICEABLE_MSG}</div>}

      {!searching ? (
        <>
          {loading && banners.length === 0 && homeTileList.length === 0 && <div className="empty-state">Loading…</div>}
          {(lightningDeals.length > 0 || unbeatableDeals.length > 0) && <section className="deals-strip" aria-label="Deals">
            {[['lightning', 'Lightning Deals', lightningDeals], ['unbeatable', 'Unbeatable Deals', unbeatableDeals]].map(([key, label, deals]) => deals.length > 0 && (
              <button type="button" className={`deals-col deals-${key}`} key={key} onClick={() => openDeals(key)}>
                <div className="deals-head">
                  <h3>{label}</h3>
                  <svg aria-hidden width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.5" strokeLinecap="round" strokeLinejoin="round"><polyline points="9 6 15 12 9 18" /></svg>
                </div>
                <div className="deals-grid">
                  {deals.map((product) => {
                    const { unitPrice, img } = variantView(product)
                    return <span className="deals-item" key={product.id}>
                      <span className="deals-item-img" aria-hidden>{imgPlaceholder()}{img && <img src={mediaUrl(img)} alt="" loading="lazy" onError={(event) => { event.currentTarget.style.display = 'none' }} />}</span>
                      <span className="deals-item-price">{price(unitPrice)}</span>
                      {product.rating_count > 0 && <span className="deals-strip-rating">
                        <span className="deals-strip-stars">{starIcons(Number(product.rating_avg), `sstar-${product.id}`)}</span>
                        <span className="deals-strip-rating-count">{product.rating_count}</span>
                      </span>}
                    </span>
                  })}
                </div>
              </button>
            ))}
          </section>}

          {categoryCarousel(homeCatsRef, homeCatsDrag, homeCatsWrapRef, activeCategory, (tile) => { if (tile) openHomeTarget(tile); else setActiveCategory(null) }, true, true)}

          {/* With a category picked, always render the grid (it has its own
              "Nothing here yet" fallback) so a genuinely empty category
              doesn't just disappear with no heading or message. */}
          {activeCategory && <div className="catalog-head"><h2>{activeCategory}</h2><span>{visibleProducts.length} items</span></div>}
          {(activeCategory || products.length > 0) && (loading ? <div className="empty-state">Loading…</div> : productGrid)}
        </>
      ) : loading ? <div className="empty-state">Loading…</div> : (
        <>
          <nav className="cat-rail" aria-label="Product categories">
            <button className="cat-tile" type="button" onClick={() => { setActiveCategory(null); setQuery('') }}><span className="cat-ico" aria-hidden>&#8592;</span>All</button>
            {homeCategories.map((category) => <button className={activeCategory === category.name ? 'cat-tile active' : 'cat-tile'} type="button" key={category.id} onClick={() => { setActiveCategory(category.name); setQuery('') }}><span className="cat-ico" aria-hidden>{categoryEmoji(category.name)}{category.image_url && <img src={mediaUrl(category.image_url)} alt="" loading="lazy" onError={(event) => { event.currentTarget.style.display = 'none' }} />}</span>{category.name}</button>)}
          </nav>
          <div className="catalog-head"><h2>{`Results for “${query.trim()}”`}</h2><span>{visibleProducts.length} items</span></div>
          {productGrid}
        </>
      )}
      </>}
    </main>
    <div className="side-toolbar" role="toolbar">
      <button type="button" className={supportUnread ? 'side-toolbar-btn has-dot' : 'side-toolbar-btn'} onClick={() => openSupport()} aria-label="Messages">
        <svg aria-hidden viewBox="0 0 1024 1024"><path d="M802.9 169.9c73.3 0 132.7 59.4 132.7 132.6l0 387.5c0 73.3-59.4 132.7-132.7 132.7l-178.8-0.1-53.8 67.5c-24.2 30.3-67.2 36.7-99 15.9l-5.8-4.2c-4.3-3.5-8.3-7.4-11.8-11.7l-53.9-67.5-178.7 0.1c-70.6 0-128.4-55.2-132.4-124.9l-0.3-7.8 0-387.5c0-73.3 59.4-132.7 132.7-132.6z m0 79.1l-581.8 0c-29.6 0-53.5 24-53.5 53.5l0 387.5c0 29.6 24 53.5 53.5 53.6l216.8 0 74.1 92.6 74.1-92.6 216.8 0c29.6 0 53.5-24 53.5-53.6l0-387.5c0-29.6-24-53.5-53.5-53.5z m-290.9 193.2c32.6 0 59.1 26.4 59.1 59.1 0 32.6-26.4 59.1-59.1 59-32.6 0-59.1-26.4-59.1-59 0-32.6 26.4-59.1 59.1-59.1z m-196.9 0c32.6 0 59.1 26.4 59.1 59.1 0 32.6-26.4 59.1-59.1 59-32.6 0-59.1-26.4-59.1-59 0-32.6 26.4-59.1 59.1-59.1z m393.8 0c32.6 0 59.1 26.4 59.1 59.1 0 32.6-26.4 59.1-59.1 59-32.6 0-59.1-26.4-59.1-59 0-32.6 26.4-59.1 59.1-59.1z" /></svg>
        {supportUnread ? <i className="side-toolbar-dot" aria-hidden /> : null}
      </button>
      <button type="button" className="side-toolbar-btn" onClick={() => { setFeedbackOpen(true); setFeedbackRating(null); setFeedbackSent(false) }} aria-label="Feedback">
        <svg aria-hidden viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2"><path d="M12 20h9" /><path d="M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4Z" /></svg>
      </button>
      {showBackToTop && <button type="button" className="side-toolbar-btn" onClick={() => window.scrollTo({ top: 0, behavior: 'smooth' })} aria-label="Back to top">
        <svg aria-hidden viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.5" strokeLinecap="round" strokeLinejoin="round"><polyline points="18 15 12 9 6 15" /></svg>
        <span>Top</span>
      </button>}
    </div>
    {feedbackOpen && <div className="overlay" role="presentation" onClick={() => setFeedbackOpen(false)}><div className="auth-modal feedback-modal" role="dialog" aria-modal="true" aria-labelledby="feedback-title" onClick={(event) => event.stopPropagation()}>
      <button className="close-button" type="button" onClick={() => setFeedbackOpen(false)} aria-label="Close feedback">x</button>
      {feedbackSent ? <><h2 id="feedback-title">Thanks for the feedback!</h2><p className="auth-intro">We&rsquo;ll use it to keep improving.</p></> : <>
        <h2 id="feedback-title">We are here to improve your experience!</h2>
        <p className="auth-intro">Your feedback matters! Please tell us what you think of our website below.</p>
        <p className="feedback-question">How do you feel about your visit on our site today?</p>
        <div className="feedback-scale" role="radiogroup" aria-label="Rate your visit">
          {FEEDBACK_OPTIONS.map((option) => <button type="button" key={option.value} className={feedbackRating === option.value ? 'feedback-option active' : 'feedback-option'} role="radio" aria-checked={feedbackRating === option.value} onClick={() => setFeedbackRating(option.value)}>
            <span className="feedback-dot" aria-hidden />
            <span>{option.label}</span>
          </button>)}
        </div>
        <button className="checkout-button feedback-submit" type="button" disabled={!feedbackRating} onClick={async () => {
          const token = localStorage.getItem('gdp_token')
          try {
            await fetch(`${API_URL}/site-feedback`, { method: 'POST', headers: { 'Content-Type': 'application/json', Accept: 'application/json', ...(token ? { Authorization: `Bearer ${token}` } : {}) }, body: JSON.stringify({ rating: feedbackRating }) })
          } catch { /* best-effort */ }
          setFeedbackSent(true)
        }}>Share with {branding?.store_name || 'NexTech'}</button>
      </>}
    </div></div>}
    {cartCount > 0 && <aside className={`cart-tray${trayDragging ? ' dragging' : ''}`} aria-live="polite" style={{ transform: `translateX(-50%) translateY(${trayLift}px)` }} onPointerDown={trayPointerDown} onPointerMove={trayPointerMove} onPointerUp={trayPointerUp} onPointerCancel={trayPointerUp}><div><strong>{cartCount} {cartCount === 1 ? 'item' : 'items'} in your cart</strong><span>{price(cartTotal)} subtotal</span></div><button type="button" onClick={() => setCartOpen(true)}>View cart <span>&rarr;</span></button></aside>}
    {cartOpen && <div className="overlay" role="presentation" onClick={() => setCartOpen(false)}><aside className="drawer" role="dialog" aria-modal="true" aria-labelledby="cart-title" onClick={(event) => event.stopPropagation()}><div className="drawer-header"><div><p className="eyebrow">Ready when you are</p><h2 id="cart-title">Your cart</h2></div><button className="close-button" type="button" onClick={() => setCartOpen(false)} aria-label="Close cart">x</button></div>{cart.length ? <><div className="drawer-items">{cartView.map((item) => <div className="drawer-item" key={item.key}><div className="mini-visual" aria-hidden>{imgPlaceholder()}{item.image_url && <img src={mediaUrl(item.image_url)} alt="" loading="lazy" onError={(event) => { event.currentTarget.style.display = 'none' }} />}</div><div className="drawer-item-copy"><strong>{variantTitle(item.name, item.variantLabel)}</strong><span>{item.onSale ? <><strong className="on-sale">{price(item.unit)}</strong> <s>{price(item.reg)}</s></> : price(item.unit)}{item.quantity > 1 && <> &middot; {item.quantity} pcs = {item.onSale ? <><strong className="on-sale">{price(item.unit * item.quantity)}</strong> <s>{price(item.lineReg)}</s></> : price(item.unit * item.quantity)}</>}</span></div><div className="quantity"><button type="button" onClick={() => updateQuantity(item.key, -1)}>-</button><span>{item.quantity}</span><button type="button" onClick={() => updateQuantity(item.key, 1)}>+</button></div></div>)}</div><div className="drawer-summary"><div><span>Subtotal</span><span>{cartRegularTotal > est.sub ? <><s className="on-sale">{price(cartRegularTotal)}</s> {price(est.sub)}</> : price(est.sub)}</span></div><div><span>Delivery</span><span>{est.delivery === 0 ? 'FREE' : price(est.delivery)}</span></div>{hasSellerShipped && <div><span>Shipping from sellers</span><span>{est.sellerShipping === 0 ? 'FREE' : price(est.sellerShipping)}</span></div>}<div><span>Handling</span><span>{price(est.handling)}</span></div>{est.smallCart > 0 && <div><span>Small cart fee</span><span>{price(est.smallCart)}</span></div>}{taxInclusive ? <div><span>{marketProfile?.tax_label ?? 'Tax'}</span><span>Included in prices</span></div> : <div><span>Tax</span><span>{price(est.tax)}</span></div>}<div className="drawer-summary-total"><strong>Estimated total</strong><strong>{price(est.total)}</strong></div></div>{fees.delivery_mode === 'distance' && serviceable?.delivery_fee_cents == null && <p className="drawer-nudge">Delivery fee is based on distance — set your location for the exact amount.</p>}{est.toFreeDelivery > 0 && <p className="drawer-nudge">Add {price(est.toFreeDelivery)} more for free delivery.</p>}{est.toNoSmallCart > 0 && <p className="drawer-nudge">Add {price(est.toNoSmallCart)} more to drop the {price(est.smallCart)} small-cart fee.</p>}{hasSellerShipped && (sellerQuote?.shops ?? []).map((q) => <p key={q.shop_id} className="drawer-nudge">Ships from {q.shop_name} · {q.free_shipping ? 'free shipping' : price(q.fee_cents)} · arrives {new Date(q.deliver_from).toLocaleDateString([], { month: 'short', day: 'numeric' })}–{new Date(q.deliver_by).toLocaleDateString([], { month: 'short', day: 'numeric' })}{sellerQuote.state_known ? '' : ' (estimate — add your address for exact shipping)'}</p>)}{(sellerQuote?.unshippable ?? []).length > 0 && sellerQuote.state_known && <p className="drawer-nudge">The seller can&rsquo;t ship {sellerQuote.unshippable.join(', ')} to your state.</p>}<button className="checkout-button" type="button" onClick={() => { setCartOpen(false); setCheckoutOpen(true); setCheckoutStep('address'); setCheckoutMessage('') }}>Continue to checkout <span>&rarr;</span></button></> : <div className="empty-cart"><div className="empty-cart-mark">+</div><h3>Your cart is empty</h3><p>Find something good in the essentials below.</p><button type="button" onClick={() => setCartOpen(false)}>Keep shopping</button></div>}</aside></div>}
    {authMode && <div className="overlay" role="presentation" onClick={() => { setAuthMode(null); setOtpStage(null); setAuthTab('code'); setPwMode('signin') }}><div className="auth-modal" role="dialog" aria-modal="true" aria-labelledby="auth-title" onClick={(event) => event.stopPropagation()}><button className="close-button" type="button" onClick={() => { setAuthMode(null); setOtpStage(null); setAuthTab('code'); setPwMode('signin') }} aria-label="Close authentication">x</button><p className="eyebrow">A better way to shop tech</p>{otpStage ? <><h2 id="auth-title">Enter your code</h2><p className="auth-intro">We emailed a 6-digit code to {otpStage.email}. It expires in 10 minutes.</p><form onSubmit={submitOtp}><input required inputMode="numeric" autoComplete="one-time-code" pattern="[0-9]*" maxLength="8" placeholder="6-digit code" value={otpCode} onChange={(event) => setOtpCode(event.target.value.replace(/[^0-9]/g, ''))} /><button className="checkout-button" type="submit">Verify <span>&rarr;</span></button></form>{authMessage && <p className="auth-message">{authMessage}</p>}<button className="switch-auth" type="button" onClick={resendOtp}>Resend code</button><button className="switch-auth" type="button" onClick={() => { setOtpStage(null); setAuthMessage('') }}>Use a different email</button></> : <><h2 id="auth-title">Sign in or sign up</h2><div className="auth-tabs" role="tablist"><button type="button" role="tab" aria-selected={authTab === 'code'} className={authTab === 'code' ? 'auth-tab active' : 'auth-tab'} onClick={() => { setAuthTab('code'); setAuthMessage('') }}>Email code</button><button type="button" role="tab" aria-selected={authTab === 'password'} className={authTab === 'password' ? 'auth-tab active' : 'auth-tab'} onClick={() => { setAuthTab('password'); setAuthMessage('') }}>Password</button></div>{authTab === 'password' ? (() => {
        const forgot = pwMode === 'forgot'
        const signup = pwMode === 'signup'
        const wantNewPw = signup || (forgot && resetSent)
        return <>
          <p className="auth-intro">{signup ? 'Create an account with a password.' : forgot ? (resetSent ? 'Enter the code we emailed and your new password.' : 'Enter your email to get a reset code.') : 'Sign in with your email and password.'}</p>
          <form onSubmit={submitPassword}>
            {signup && <input required autoComplete="name" placeholder="Your name" value={authForm.name} onChange={(event) => setAuthForm({ ...authForm, name: event.target.value })} />}
            <input required type="email" autoComplete="email" placeholder="Email address" disabled={forgot && resetSent} value={authForm.email} onChange={(event) => setAuthForm({ ...authForm, email: event.target.value })} />
            {forgot && resetSent && <input required inputMode="numeric" pattern="[0-9]*" maxLength="8" autoComplete="one-time-code" placeholder="6-digit code" value={authForm.code} onChange={(event) => setAuthForm({ ...authForm, code: event.target.value.replace(/[^0-9]/g, '') })} />}
            {!forgot && <input required type="password" autoComplete={signup ? 'new-password' : 'current-password'} minLength={signup ? 8 : undefined} placeholder={signup ? 'Password (min 8 characters)' : 'Password'} value={authForm.password} onChange={(event) => setAuthForm({ ...authForm, password: event.target.value })} />}
            {forgot && resetSent && <input required type="password" autoComplete="new-password" minLength={8} placeholder="New password (min 8 characters)" value={authForm.password} onChange={(event) => setAuthForm({ ...authForm, password: event.target.value })} />}
            {wantNewPw && <input required type="password" autoComplete="new-password" placeholder="Confirm password" value={authForm.password_confirmation} onChange={(event) => setAuthForm({ ...authForm, password_confirmation: event.target.value })} />}
            <button className="checkout-button" type="submit">{signup ? 'Create account' : forgot ? (resetSent ? 'Reset password' : 'Send reset code') : 'Sign in'} <span>&rarr;</span></button>
          </form>
          {pwMode === 'signin' && <button className="switch-auth" type="button" onClick={() => { setPwMode('forgot'); setResetSent(false); setAuthMessage('') }}>Forgot password?</button>}
          <button className="switch-auth" type="button" onClick={() => { setPwMode(pwMode === 'signin' ? 'signup' : 'signin'); setResetSent(false); setAuthMessage('') }}>{signup ? 'Already have an account? Sign in' : forgot ? 'Back to sign in' : 'New here? Create an account'}</button>
        </>
      })() : <><p className="auth-intro">Enter your email and we&rsquo;ll send a 6-digit code. No password needed &mdash; if you&rsquo;re new, your account is created automatically.</p><form onSubmit={submitAuth}><input required type="email" autoComplete="email" placeholder="Email address" value={authForm.email} onChange={(event) => setAuthForm({ ...authForm, email: event.target.value })} /><button className="checkout-button" type="submit">Continue <span>&rarr;</span></button></form></>}{authMessage && <p className="auth-message">{authMessage}</p>}</>}</div></div>}
    {checkoutOpen && <div className="overlay" role="presentation" onClick={() => { setCheckoutOpen(false); setCheckoutStep('address') }}><div className="auth-modal checkout-modal" role="dialog" aria-modal="true" aria-labelledby="checkout-title" onClick={(event) => event.stopPropagation()}><button className="close-button" type="button" onClick={() => { setCheckoutOpen(false); setCheckoutStep('address') }} aria-label="Close checkout">x</button><p className="eyebrow">Almost there</p>{checkoutStep === 'address' ? <><h2 id="checkout-title">{deliveryMode === 'form' ? 'Where should we deliver?' : 'Confirm delivery address'}</h2><p className="auth-intro">Your total will be calculated and confirmed securely by the server.</p>{deliveryMode === 'location' ? <><div className="loc-current"><strong>Deliver to</strong> {location.full || location.label}</div><input placeholder="Flat / house / building &amp; street" value={checkoutForm.line1} onChange={(event) => setCheckoutForm({ ...checkoutForm, line1: event.target.value })} /><div className="checkout-links"><button type="button" className="switch-auth" onClick={() => { setCheckoutOpen(false); setLocationOpen(true) }}>Change location</button><button type="button" className="switch-auth" onClick={() => setEditAddress(true)}>Edit full address</button></div></> : deliveryMode === 'saved' ? <><div className="loc-current"><strong>Deliver to</strong> {defaultAddress.line1}, {defaultAddress.city} {defaultAddress.state} {defaultAddress.postal_code}</div>{addresses.length > 1 && <label className="address-picker">Choose address<select value={selectedAddressId || String(defaultAddress.id)} onChange={(event) => setSelectedAddressId(event.target.value)}>{addresses.map((address) => <option key={address.id} value={address.id}>{address.label} - {address.line1}, {address.city}</option>)}</select></label>}<div className="checkout-links"><button type="button" className="switch-auth" onClick={() => { setCheckoutOpen(false); setLocationOpen(true) }}>Change location</button><button type="button" className="switch-auth" onClick={() => { setSelectedAddressId(''); setEditAddress(true) }}>Enter a new address</button></div></> : <>{addresses.length > 0 && <label className="address-picker">Saved address<select value={selectedAddressId} onChange={(event) => setSelectedAddressId(event.target.value)}>{addresses.map((address) => <option key={address.id} value={address.id}>{address.label} - {address.line1}, {address.city}</option>)}<option value="">Use a new address</option></select></label>}<form onSubmit={(event) => { event.preventDefault(); if (!blockCheckout) setCheckoutStep('checkout') }}>{!selectedAddressId && <><input required placeholder="Full name" value={checkoutForm.name} onChange={(event) => setCheckoutForm({ ...checkoutForm, name: event.target.value })} /><input required placeholder="Street address" value={checkoutForm.line1} onChange={(event) => setCheckoutForm({ ...checkoutForm, line1: event.target.value })} /><div className="form-row"><input required placeholder="City" value={checkoutForm.city} onChange={(event) => setCheckoutForm({ ...checkoutForm, city: event.target.value })} /><input required maxLength="60" list="market-states" placeholder="State" value={checkoutForm.state} onChange={(event) => setCheckoutForm({ ...checkoutForm, state: event.target.value })} /><datalist id="market-states">{Object.entries(marketProfile?.states ?? {}).map(([code, name]) => <option key={code} value={name} />)}</datalist></div><input required maxLength="12" placeholder={marketProfile?.postal_label ?? 'ZIP code'} value={checkoutForm.postal_code} onChange={(event) => setCheckoutForm({ ...checkoutForm, postal_code: event.target.value })} /></>}</form></>}<label className="checkout-phone"><span>Phone number{currentUser?.phone ? '' : ' — the delivery rider may call you'}</span><input type="tel" required maxLength="32" placeholder={activeMarket === 'IN' ? 'e.g. +91 98765 43210' : 'e.g. +1 555 987 6543'} value={phone} onChange={(event) => setPhone(event.target.value)} /></label><textarea className="delivery-note" rows="2" maxLength="500" placeholder="Delivery instructions (optional) — e.g. leave at the gate, call on arrival" value={deliveryNote} onChange={(event) => setDeliveryNote(event.target.value)} /><button className="checkout-button" type="button" onClick={() => setCheckoutStep('checkout')} disabled={blockCheckout}>Continue to checkout <span>&rarr;</span></button>{blockCheckout && <p className="auth-message">{outOfArea && deliveryMode === 'location' ? UNSERVICEABLE_MSG : 'Add a phone number so your delivery rider can reach you.'}</p>}</> : <><h2 id="checkout-title">Checkout</h2><p className="auth-intro">Have a gift card, or want to pay another way? Do it here — then place your order.</p><div className="loc-current"><strong>Deliver to</strong> {deliveryAddressSummary}</div><button type="button" className="switch-auth" onClick={() => setCheckoutStep('address')}>&larr; Edit delivery address</button><details className="gift-card-field"><summary>Have a refund gift card?</summary><div className="form-row"><input placeholder="Gift card (GC-XXXX-XXXX)" value={giftCard.code} onChange={(event) => setGiftCard({ ...giftCard, code: event.target.value, checked: null })} /><input placeholder="Password" value={giftCard.pin} onChange={(event) => setGiftCard({ ...giftCard, pin: event.target.value, checked: null })} /></div><button type="button" className="switch-auth" onClick={checkGiftCard}>Check balance</button>{giftCard.checked?.error && <span className="auth-message">{giftCard.checked.error}</span>}{giftCard.checked?.balance_cents != null && <span className="gift-card-ok">Balance {price(giftCard.checked.balance_cents)} — applied at checkout (any remainder stays on the card).</span>}</details>{codEnabled && hasSellerShipped && <p className="drawer-nudge">Cash on delivery isn&rsquo;t available for items shipped directly by sellers — you&rsquo;ll pay by card.</p>}{codEnabled && !hasSellerShipped && <><p className="pay-methods-label">How would you like to pay?</p><div className="pay-methods" role="radiogroup" aria-label="Payment method"><button type="button" role="radio" aria-checked={paymentMethod === 'card'} className={paymentMethod === 'card' ? 'pay-method active' : 'pay-method'} onClick={() => setPaymentMethod('card')}><strong>Pay online</strong><span>Card via Stripe</span></button><button type="button" role="radio" aria-checked={paymentMethod === 'cod'} className={paymentMethod === 'cod' ? 'pay-method active' : 'pay-method'} onClick={() => setPaymentMethod('cod')}><strong>Cash on delivery</strong><span>Pay when it arrives</span></button></div></>}<button className="checkout-button" type="button" onClick={submitCheckout} disabled={blockCheckout}>{codEnabled && paymentMethod === 'cod' && !hasSellerShipped ? 'Place order' : 'Review order'} <span>&rarr;</span></button>{checkoutMessage && <p className="auth-message">{checkoutMessage}</p>}</>}</div></div>}
    {order?.clientSecret && <div className="overlay" role="presentation" onClick={() => setOrder(null)}><div className="auth-modal checkout-modal payment-modal" role="dialog" aria-modal="true" aria-labelledby="payment-title" onClick={(event) => event.stopPropagation()}><button className="close-button" type="button" onClick={() => setOrder(null)} aria-label="Close payment">x</button><p className="eyebrow">Secure payment</p><h2 id="payment-title">Finish your order.</h2><p className="auth-intro">Order #{order.id} · {price(order.total_cents, order.currency)} USD</p><Elements stripe={stripePromise}><PaymentForm clientSecret={order.clientSecret} onComplete={finalizePayment} savedCards={cards ?? []} /></Elements>{codEnabled && <button className="switch-auth" type="button" onClick={switchToCashOnDelivery}>Pay with cash on delivery instead</button>}<button className="switch-auth" type="button" onClick={() => setOrder(null)}>Pay later from Order history</button>{order.switchError && <p className="auth-message">{order.switchError}</p>}</div></div>}
    {order && !order.clientSecret && <div className="overlay" role="presentation" onClick={() => setOrder(null)}><div className="auth-modal order-modal" role="dialog" aria-modal="true" aria-labelledby="order-title" onClick={(event) => event.stopPropagation()}><p className="eyebrow">{order.cod ? 'Order confirmed' : order.paid ? 'Payment submitted' : 'Payment setup needed'}</p><h2 id="order-title">{order.cod || order.paid ? 'You’re all set.' : 'Order created.'}</h2><p className="auth-intro">{order.cod ? `Order #${order.id} is confirmed. Pay with cash when your order arrives.` : `Order #${order.id} is ${order.paid ? 'being confirmed by Stripe.' : 'waiting for Stripe test keys.'}`}</p><div className="order-breakdown"><div><span>Subtotal</span><span>{price(order.subtotal_cents, order.currency)}</span></div><div><span>Delivery</span><span>{order.delivery_fee_cents === 0 ? 'FREE' : price(order.delivery_fee_cents, order.currency)}</span></div><div><span>Handling</span><span>{price(order.handling_fee_cents ?? 0)}</span></div>{order.small_cart_fee_cents > 0 && <div><span>Small cart fee</span><span>{price(order.small_cart_fee_cents, order.currency)}</span></div>}{order.tax_included_cents > 0 ? <div><span>Includes GST</span><span>{price(order.tax_included_cents, order.currency)}</span></div> : <div><span>Tax</span><span>{price(order.tax_cents, order.currency)}</span></div>}{order.gift_card_discount_cents > 0 && <div><span>Gift card</span><span>&minus;{price(order.gift_card_discount_cents, order.currency)}</span></div>}</div>{order.delivery_instructions && <p className="auth-intro" style={{ margin: '12px 0 0' }}>Note to courier: &ldquo;{order.delivery_instructions}&rdquo;</p>}<div className="order-total"><span>{order.paid ? 'Paid' : order.cod ? 'Pay on delivery' : 'Order total'}</span><strong>{price(order.total_cents, order.currency)}</strong></div>{(order.cod || order.paid) && <button className="text-button order-receipt" type="button" onClick={() => downloadReceipt(order.id)}>Download bill (PDF)</button>}<button className="checkout-button" type="button" onClick={() => setOrder(null)}>Keep shopping <span>&rarr;</span></button>{ordersMessage && <p className="auth-message">{ordersMessage}</p>}</div></div>}
    {accountOpen && <div className="overlay" role="presentation" onClick={() => { setAccountOpen(false); setAddrForm(null) }}>
      <div className="auth-modal account-modal" role="dialog" aria-modal="true" aria-labelledby="account-title" onClick={(event) => event.stopPropagation()}>
        <button className="close-button" type="button" onClick={() => { setAccountOpen(false); setAddrForm(null) }} aria-label="Close account">x</button>
        <p className="eyebrow">Signed in as {currentUser?.email}</p>
        <h2 id="account-title">Your account</h2>
        <div className="auth-tabs" role="tablist">
          {[['profile', 'Profile'], ['addresses', 'Addresses'], ['cards', 'Payment methods']].map(([key, label]) => (
            <button key={key} type="button" role="tab" aria-selected={accountTab === key} className={accountTab === key ? 'auth-tab active' : 'auth-tab'} onClick={() => { setAccountTab(key); setAccountMsg(''); setAddrForm(null) }}>{label}</button>
          ))}
        </div>

        {accountTab === 'profile' && (
          <form className="account-form" onSubmit={saveProfile}>
            <label>Name<input required value={profileForm.name} onChange={(event) => setProfileForm({ ...profileForm, name: event.target.value })} /></label>
            <label>Phone<input type="tel" maxLength="32" placeholder="+1 555 987 6543" value={profileForm.phone} onChange={(event) => setProfileForm({ ...profileForm, phone: event.target.value })} /></label>
            {!currentUser?.is_admin && <p className="account-hint">Email is used to sign in and can&rsquo;t be changed here — contact support to update it.</p>}
            <button className="checkout-button" type="submit">Save profile</button>
          </form>
        )}

        {accountTab === 'profile' && (
          <form className="account-form" onSubmit={changePassword} style={{ marginTop: 18, borderTop: '1px solid var(--line)', paddingTop: 16 }}>
            <h3 className="account-sub">Change password</h3>
            <label>Current password<input required type="password" autoComplete="current-password" value={pwForm.current} onChange={(event) => setPwForm({ ...pwForm, current: event.target.value })} /></label>
            <label>New password<input required type="password" autoComplete="new-password" minLength={8} placeholder="min 8 characters" value={pwForm.next} onChange={(event) => setPwForm({ ...pwForm, next: event.target.value })} /></label>
            <label>Confirm new password<input required type="password" autoComplete="new-password" value={pwForm.confirm} onChange={(event) => setPwForm({ ...pwForm, confirm: event.target.value })} /></label>
            <button className="checkout-button" type="submit">Update password</button>
          </form>
        )}

        {accountTab === 'addresses' && (addrForm ? (
          <form className="account-form" onSubmit={saveAddress}>
            <h3 className="account-sub">{addrForm.id ? 'Edit address' : 'New address'}</h3>
            <label>Label<input maxLength="40" placeholder="Home, Work…" value={addrForm.label ?? ''} onChange={(event) => setAddrForm({ ...addrForm, label: event.target.value })} /></label>
            <label>Full name<input required maxLength="120" value={addrForm.name ?? ''} onChange={(event) => setAddrForm({ ...addrForm, name: event.target.value })} /></label>
            <label>Address line 1<input required maxLength="255" value={addrForm.line1 ?? ''} onChange={(event) => setAddrForm({ ...addrForm, line1: event.target.value })} /></label>
            <label>Address line 2<input maxLength="255" value={addrForm.line2 ?? ''} onChange={(event) => setAddrForm({ ...addrForm, line2: event.target.value })} /></label>
            <div className="form-row3">
              <label>City<input maxLength="100" value={addrForm.city ?? ''} onChange={(event) => setAddrForm({ ...addrForm, city: event.target.value })} /></label>
              <label>State<input maxLength="60" value={addrForm.state ?? ''} onChange={(event) => setAddrForm({ ...addrForm, state: event.target.value })} /></label>
              <label>ZIP<input maxLength="12" value={addrForm.postal_code ?? ''} onChange={(event) => setAddrForm({ ...addrForm, postal_code: event.target.value })} /></label>
            </div>
            <label className="account-check"><input type="checkbox" checked={!!addrForm.is_default} onChange={(event) => setAddrForm({ ...addrForm, is_default: event.target.checked })} /> Use as my default address</label>
            <div className="checkout-links">
              <button className="checkout-button" type="submit">{addrForm.id ? 'Save address' : 'Add address'}</button>
              <button className="switch-auth" type="button" onClick={() => setAddrForm(null)}>Cancel</button>
            </div>
          </form>
        ) : (
          <>
            {addresses.length === 0 ? <p className="auth-intro">No saved addresses yet.</p> : <ul className="account-list">
              {addresses.map((address) => <li key={address.id} className="account-row">
                <div>
                  <strong>{address.label || 'Address'}{address.is_default && <span className="account-tag">Default</span>}</strong>
                  <span>{[address.line1, address.line2, address.city, address.state, address.postal_code].filter(Boolean).join(', ')}</span>
                </div>
                <div className="account-row-actions">
                  {!address.is_default && <button type="button" className="text-button" onClick={() => makeDefaultAddress(address.id)}>Make default</button>}
                  <button type="button" className="text-button" onClick={() => setAddrForm({ ...address })}>Edit</button>
                  <button type="button" className="text-button danger" onClick={() => deleteAddress(address.id)}>Delete</button>
                </div>
              </li>)}
            </ul>}
            <button className="checkout-button" type="button" onClick={() => setAddrForm({ label: '', name: currentUser?.name ?? '', line1: '', line2: '', city: '', state: '', postal_code: '', is_default: addresses.length === 0 })}>Add address</button>
          </>
        ))}

        {accountTab === 'cards' && (!stripePromise ? (
          <p className="auth-intro">Card management needs Stripe keys — set them in admin Settings → Payments.</p>
        ) : addingCard ? (
          <Elements stripe={stripePromise}>
            <AddCardForm onDone={() => { setAddingCard(false); loadCards() }} onCancel={() => setAddingCard(false)} />
          </Elements>
        ) : (
          <>
            {cards === null || cardsBusy ? <p className="auth-intro">Loading cards…</p> : cards.length === 0 ? <p className="auth-intro">No saved cards yet.</p> : <ul className="account-list">
              {cards.map((card) => <li key={card.id} className="account-row">
                <div>
                  <strong style={{ textTransform: 'capitalize' }}>{card.brand} &bull;&bull;&bull;&bull; {card.last4}{card.is_default && <span className="account-tag">Default</span>}</strong>
                  <span>Expires {String(card.exp_month).padStart(2, '0')}/{String(card.exp_year).slice(-2)}</span>
                </div>
                <div className="account-row-actions">
                  {!card.is_default && <button type="button" className="text-button" disabled={cardsBusy} onClick={() => makeDefaultCard(card.id)}>Make default</button>}
                  <button type="button" className="text-button danger" disabled={cardsBusy} onClick={() => deleteCard(card.id)}>Remove</button>
                </div>
              </li>)}
            </ul>}
            <button className="checkout-button" type="button" onClick={() => setAddingCard(true)}>Add a card</button>
          </>
        ))}

        {accountMsg && <p className="auth-message">{accountMsg}</p>}
      </div>
    </div>}
    {ordersOpen && <div className="overlay" role="presentation" onClick={() => setOrdersOpen(false)}><div className="auth-modal orders-modal" role="dialog" aria-modal="true" aria-labelledby="orders-title" onClick={(event) => event.stopPropagation()}><button className="close-button" type="button" onClick={() => setOrdersOpen(false)} aria-label="Close orders">x</button><p className="eyebrow">Your orders</p><h2 id="orders-title">Order history</h2>{ordersLoading ? <p className="auth-intro">Loading your orders...</p> : orders.length === 0 ? <p className="auth-intro">No orders yet. Your completed checkouts will appear here.</p> : <ul className="orders-list">{orders.map((entry) => <li className="order-row" key={entry.id}><div className="order-row-head"><strong>Order #{entry.id}</strong><span className={`order-badge order-badge-${entry.payment_status}`}>{orderLabel(entry)}</span></div><div className="order-row-meta"><span>{new Date(entry.created_at).toLocaleDateString()}</span><span>{entry.items?.length ?? 0} {entry.items?.length === 1 ? 'item' : 'items'}</span><strong>{price(entry.total_cents, entry.currency)}</strong></div>{(entry.payment_status === 'paid' || entry.payment_method === 'cod') && DELIVERY_STAGES.includes(entry.status) && <div className="order-track" aria-label={`Delivery status: ${DELIVERY_LABELS[entry.status]}`}>{DELIVERY_STAGES.map((stage, index) => <span key={stage} className={index <= DELIVERY_STAGES.indexOf(entry.status) ? 'track-step done' : 'track-step'} title={DELIVERY_LABELS[stage]} />)}<em>{DELIVERY_LABELS[entry.status]}</em></div>}{entry.status === 'cancelled' && <p className="order-track-note">Cancelled</p>}{entry.status === 'out_for_delivery' && entry.delivery_code && new Date(entry.delivery_code_expires_at) > new Date() && <p className="order-handover">Delivery code <b>{entry.delivery_code}</b> — read this to your rider to confirm you got the order.</p>}{entry.payment_method !== 'cod' && entry.status !== 'cancelled' && entry.payment_status !== 'paid' && entry.payment_status !== 'cancelled' && <button className="text-button order-pay" type="button" onClick={() => resumePayment(entry)}>Complete payment <span>&rarr;</span></button>}{CANCELLABLE_STAGES.includes(entry.status) && <button className="text-button order-cancel" type="button" onClick={() => cancelOrder(entry)}>Cancel order</button>}{(entry.payment_status === 'paid' || entry.payment_method === 'cod') && entry.status !== 'cancelled' && <button className="text-button order-receipt" type="button" onClick={() => downloadReceipt(entry.id)}>Download bill (PDF)</button>}{(entry.shop_shipping ?? []).map((ss) => { const pk = (entry.packages ?? []).filter((p) => p.shop_id === ss.shop_id); return <div key={ss.id} className="order-seller-ship"><strong>Shipped by {ss.shop?.name ?? 'the seller'}</strong>{pk.length === 0 ? <span>{entry.status === 'cancelled' ? 'Cancelled' : `Ships by ${new Date(ss.ship_by).toLocaleDateString()} · arrives ${new Date(ss.deliver_from).toLocaleDateString([], { month: 'short', day: 'numeric' })}–${new Date(ss.deliver_by).toLocaleDateString([], { month: 'short', day: 'numeric' })}`}</span> : pk.map((p) => <span key={p.id}>{p.carrier} {p.tracking_url ? <a href={p.tracking_url} target="_blank" rel="noreferrer">{p.tracking_number}</a> : p.tracking_number} · {p.status === 'delivered' ? `delivered ${new Date(p.delivered_at).toLocaleDateString()}` : p.status.replace('_', ' ')}{p.status !== 'delivered' && ['shipped', 'in_transit'].includes(p.status) && <button type="button" className="text-button" onClick={() => confirmPackageReceived(entry, p)}>Confirm received</button>}</span>)}</div> })}<button className="text-button order-help" type="button" onClick={() => { setOrdersOpen(false); openSupport(entry) }}>Get help</button>{entry.status === 'completed' && entry.delivery_partner_id && <RiderRating orderId={entry.id} existing={entry.rider_review} source="delivery" onSaved={(rv) => setOrders((current) => current.map((row) => row.id === entry.id ? { ...row, rider_review: rv } : row))} />}</li>)}</ul>}{ordersMessage && <p className="auth-message">{ordersMessage}</p>}</div></div>}
    {locationOpen && <div className="overlay" role="presentation" onClick={() => { if (location) setLocationOpen(false) }}><div className="auth-modal location-modal" role="dialog" aria-modal="true" aria-labelledby="loc-title" onClick={(event) => event.stopPropagation()}>{location && <button className="close-button" type="button" onClick={() => setLocationOpen(false)} aria-label="Close location">x</button>}<p className="eyebrow">Deliver to</p><h2 id="loc-title">Where are you?</h2><p className="auth-intro">Drop the pin on your building — that&rsquo;s the location we deliver to. Search or &ldquo;detect&rdquo; just move the map near your area.</p>
      {outOfArea && <p className="loc-unserviceable">{UNSERVICEABLE_MSG}</p>}
      <div className="loc-tools">
        <button className="loc-detect" type="button" onClick={detectLocation} disabled={locationBusy}>{locationBusy ? 'Locating…' : 'Detect my location'} <span aria-hidden>&#9678;</span></button>
        <form className="loc-search" onSubmit={searchLocation}><input placeholder="Search an area, road or landmark" value={locationQuery} onChange={(event) => setLocationQuery(event.target.value)} /><button type="submit" disabled={locationBusy || locationQuery.trim().length < 3}>Search</button></form>
      </div>
      {locationResults.length > 0 && <div className="loc-results"><p className="loc-saved-h">Move map to</p>{locationResults.map((place, index) => <button key={`${place.lat},${place.lon},${index}`} type="button" className="loc-result" onClick={() => { mapRef.current?.setView([Number(place.lat), Number(place.lon)], 16); applyLocation(place, { close: false }); setLocationResults([]) }}><strong>{place.label}</strong><span>{place.full}</span></button>)}</div>}
      <div ref={mapNodeRef} className="loc-map" aria-label="Pick your delivery location on the map" />
      <p className="loc-map-hint">Drag the pin to your exact door. We use the pin, not the typed address.</p>
      {location && <div className="loc-current"><strong>Pin</strong> {location.full || location.label}</div>}
      {currentUser && addresses.length > 0 && <div className="loc-saved"><p className="loc-saved-h">Saved addresses</p>{addresses.map((address) => <button key={address.id} type="button" className="loc-result" onClick={() => applyLocation({ label: `${address.label || 'Address'} · ${address.city}`, full: `${address.line1}, ${address.city} ${address.state} ${address.postal_code}`, line1: address.line1, city: address.city, state: address.state, postal_code: address.postal_code })}><strong>{address.label || 'Address'}</strong><span>{address.line1}, {address.city} {address.state} {address.postal_code}</span></button>)}</div>}
      {locationMsg && <p className="auth-message">{locationMsg}</p>}
      {location && <button className="checkout-button loc-confirm" type="button" onClick={() => { setLocationOpen(false); setLocationResults([]); setLocationQuery(''); setLocationMsg('') }}>Deliver to this location <span>&rarr;</span></button>}
    </div></div>}
    {supportView && <div className="overlay" role="presentation" onClick={() => setSupportView(null)}><div className="auth-modal support-modal" role="dialog" aria-modal="true" aria-labelledby="support-title" onClick={(event) => event.stopPropagation()}><button className="close-button" type="button" onClick={() => setSupportView(null)} aria-label="Close support">x</button><p className="eyebrow">We&rsquo;re here to help</p>
      {supportView === 'list' ? <>
        <h2 id="support-title">Support</h2>
        <button className="checkout-button" type="button" onClick={() => { setSupportForm({ about_order: false, order_id: '', issue_type: 'item_missing', message: '' }); setSupportView('new') }}>New request <span>&rarr;</span></button>
        {threads.length === 0 ? <p className="auth-intro">No conversations yet.</p> : <ul className="support-list">{threads.map((t) => <li key={t.id}><button type="button" onClick={() => openThread(t.id)}><strong>{issueLabel(t.issue_type)}{t.order_id ? ` · Order #${t.order_id}` : ''}</strong><span>{t.status === 'resolved' ? 'Resolved' : 'Open'} · {t.last_message_at ? new Date(t.last_message_at).toLocaleDateString() : ''}</span></button></li>)}</ul>}
      </> : supportView === 'new' ? <>
        <h2 id="support-title">New request</h2>
        <p className="pay-methods-label">Is this about an order?</p>
        <div className="issue-chips" role="radiogroup" aria-label="Is this about an order?">
          <button type="button" role="radio" aria-checked={!supportForm.about_order} className={!supportForm.about_order ? 'issue-chip active' : 'issue-chip'} onClick={() => setSupportForm({ ...supportForm, about_order: false, order_id: '', issue_type: 'other' })}>General question</button>
          <button type="button" role="radio" aria-checked={supportForm.about_order} className={supportForm.about_order ? 'issue-chip active' : 'issue-chip'} onClick={() => setSupportForm({ ...supportForm, about_order: true })}>About an order</button>
        </div>
        {supportForm.about_order && (orders.length === 0
          ? <p className="auth-intro">You have no orders yet.</p>
          : <label className="support-field">Which order?
              <select value={supportForm.order_id} onChange={(event) => setSupportForm({ ...supportForm, order_id: event.target.value })}>
                <option value="">Select an order…</option>
                {orders.map((o) => <option key={o.id} value={o.id}>Order #{o.id} · {price(o.total_cents)}</option>)}
              </select>
            </label>)}
        {supportForm.about_order && orders.length > 0 && <div className="issue-chips" role="radiogroup" aria-label="Issue type">{ISSUE_TYPES.map(([type, label]) => <button key={type} type="button" role="radio" aria-checked={supportForm.issue_type === type} className={supportForm.issue_type === type ? 'issue-chip active' : 'issue-chip'} onClick={() => setSupportForm({ ...supportForm, issue_type: type })}>{label}</button>)}</div>}
        <textarea className="delivery-note" rows="3" maxLength="2000" placeholder="Tell us what happened" value={supportForm.message} onChange={(event) => setSupportForm({ ...supportForm, message: event.target.value })} />
        <ChatPhotoPicker photos={supportPhotos} onChange={setSupportPhotos} token={localStorage.getItem('gdp_token')} onError={setSupportMsg} disabled={supportBusy} />
        <button className="checkout-button" type="button" disabled={supportBusy} onClick={submitSupport}>Send <span>&rarr;</span></button>
        <button className="switch-auth" type="button" onClick={() => setSupportView('list')}>Back</button>
      </> : <>
        <h2 id="support-title">{issueLabel(supportView.issue_type)}{supportView.order_id ? ` · Order #${supportView.order_id}` : ''}</h2>
        {supportView.status === 'resolved' && <p className="loc-unserviceable">This conversation is resolved. Reply to re-open it.</p>}
        {supportView.issue_type === 'delivery' && (() => {
          const chatOrder = orders.find((o) => o.id === supportView.order_id)
          return chatOrder && chatOrder.delivery_partner_id
            ? <RiderRating orderId={chatOrder.id} existing={chatOrder.rider_review} source="chat" onSaved={(rv) => setOrders((current) => current.map((row) => row.id === chatOrder.id ? { ...row, rider_review: rv } : row))} />
            : null
        })()}
        <div className="chat-log">{(supportView.messages ?? []).map((m) => <div key={m.id} className={`chat-msg ${m.from_seller ? 'staff seller' : m.is_staff ? 'staff' : m.user_id ? 'me' : 'system'}`}>{m.body && <span>{m.body}</span>}<ChatPhotos urls={m.attachments} /><em>{m.from_seller ? `${supportView.seller_shop?.name ?? 'Seller'} (seller) · ` : ''}{new Date(m.created_at).toLocaleString()}</em></div>)}</div>
        {supportView.issue_type !== 'delivery' && (supportView.rating != null || (supportView.messages ?? []).some((m) => m.is_staff)) && (
          <ChatRating key={supportView.id} thread={supportView} onSaved={(t) => { setSupportView(t); loadThreads() }} />
        )}
        <ChatPhotoPicker photos={supportPhotos} onChange={setSupportPhotos} token={localStorage.getItem('gdp_token')} onError={setSupportMsg} disabled={supportBusy} />
        <div className="chat-send"><input placeholder={supportPhotos.length ? 'Add a note (optional)' : 'Type a message'} value={supportReply} onChange={(event) => setSupportReply(event.target.value)} onKeyDown={(event) => { if (event.key === 'Enter') sendSupportReply() }} /><button type="button" disabled={supportBusy || (!supportReply.trim() && !supportPhotos.length)} onClick={sendSupportReply}>Send</button></div>
        {supportView.status !== 'resolved' && <button className="end-chat-button" type="button" disabled={supportBusy} onClick={endChat}>End Chat</button>}
        <button className="switch-auth" type="button" onClick={() => { setSupportView('list'); loadThreads() }}>All conversations</button>
      </>}
      {supportMsg && <p className="auth-message">{supportMsg}</p>}
    </div></div>}
  </div>
  <footer className="site-footer" style={{ '--footer-bg': footer?.bg_color, '--footer-text': footer?.text_color }}>
    {(() => {
      const footerPages = pages.filter((p) => p.show_in_footer)
      const companyPages = footerPages.filter((p) => (p.footer_group ?? 'company') === 'company')
      const helpPages = footerPages.filter((p) => p.footer_group === 'help')
      const legalPages = footerPages.filter((p) => p.footer_group === 'legal')
      const otherPages = footerPages.filter((p) => !['company', 'help', 'legal', 'bottom'].includes(p.footer_group ?? 'company'))
      const customLinks = footer?.links ?? []
      const hasApp = !!(footer?.app_store_url || footer?.play_store_url)
      const hasSocials = FOOTER_SOCIALS.some(([key]) => footer?.socials?.[key])
      return <div className="site-footer-cols">
        {(companyPages.length > 0 || otherPages.length > 0 || customLinks.length > 0) && <div>
          <h4>Company info</h4>
          <ul>
            {companyPages.map((p) => <li key={p.slug}><button type="button" onClick={() => openPage(p.slug)}>{p.title}</button></li>)}
            {otherPages.map((p) => <li key={p.slug}><button type="button" onClick={() => openPage(p.slug)}>{p.title}</button></li>)}
            {customLinks.map((link, index) => <li key={`fl-${index}`}><a href={link.url} target="_blank" rel="noopener noreferrer">{link.label}</a></li>)}
          </ul>
        </div>}
        {legalPages.length > 0 && <div>
          <h4>Customer service</h4>
          <ul>{legalPages.map((p) => <li key={p.slug}><button type="button" onClick={() => openPage(p.slug)}>{p.title}</button></li>)}</ul>
        </div>}
        {helpPages.length > 0 && <div>
          <h4>Help</h4>
          <ul>{helpPages.map((p) => <li key={p.slug}><button type="button" onClick={() => openPage(p.slug)}>{p.title}</button></li>)}</ul>
        </div>}
        {(hasApp || hasSocials) && <div>
          {hasApp && <>
            <h4>Download the App</h4>
            <div className="site-footer-app">
              {footer?.app_store_url && <a className="app-badge" href={footer.app_store_url} target="_blank" rel="noopener noreferrer" aria-label="Download on the App Store">
                <svg viewBox="0 0 24 24" width="19" height="19" aria-hidden="true"><path fill="currentColor" d="M17.05 12.53c-.03-2.79 2.28-4.13 2.38-4.19-1.3-1.9-3.32-2.16-4.04-2.19-1.72-.17-3.35 1.01-4.22 1.01-.87 0-2.21-.99-3.63-.96-1.87.03-3.59 1.09-4.55 2.76-1.94 3.37-.5 8.36 1.39 11.09.92 1.34 2.02 2.84 3.46 2.79 1.39-.06 1.91-.9 3.59-.9 1.67 0 2.15.9 3.62.87 1.49-.03 2.44-1.37 3.36-2.71 1.06-1.56 1.5-3.07 1.52-3.15-.03-.02-2.92-1.12-2.95-4.46zM14.28 4.38c.77-.93 1.29-2.23 1.15-3.52-1.11.04-2.45.74-3.24 1.67-.71.82-1.33 2.13-1.16 3.39 1.24.1 2.5-.63 3.25-1.54z"/></svg>
                <span><small>Download on the</small><b>App Store</b></span>
              </a>}
              {footer?.play_store_url && <a className="app-badge" href={footer.play_store_url} target="_blank" rel="noopener noreferrer" aria-label="Get it on Google Play">
                <svg viewBox="0 0 24 24" width="18" height="18" aria-hidden="true">
                  <path fill="#00E0FF" d="M3.3 2.06a1 1 0 0 0-.4.82v18.24a1 1 0 0 0 .4.82l10.2-9.94z"/>
                  <path fill="#00E676" d="m17.53 8.53-3.42 3.33 3.42 3.33 4.06-2.35a1.02 1.02 0 0 0 0-1.96z"/>
                  <path fill="#FFC107" d="M17.53 8.53 5.4 1.56a1.06 1.06 0 0 0-1.13.02l9.84 9.6z"/>
                  <path fill="#FF3D47" d="M14.11 11.86 4.27 21.44a1.06 1.06 0 0 0 1.13.02l12.13-6.99z"/>
                </svg>
                <span><small>GET IT ON</small><b>Google Play</b></span>
              </a>}
            </div>
          </>}
          {hasSocials && <>
            <h4>Connect with Us</h4>
            <span className="site-footer-socials">
              {FOOTER_SOCIALS.filter(([key]) => footer?.socials?.[key]).map(([key, label, path]) => (
                <a key={key} href={footer.socials[key]} target="_blank" rel="noopener noreferrer" aria-label={label}>
                  <svg viewBox="0 0 24 24" width="18" height="18" fill="currentColor" aria-hidden="true"><path d={path} /></svg>
                </a>
              ))}
            </span>
          </>}
        </div>}
      </div>
    })()}
    {activeMarket === 'IN' && grievanceOfficer?.name && <p className="site-footer-grievance">Grievance Officer: {grievanceOfficer.name}{grievanceOfficer.designation ? `, ${grievanceOfficer.designation}` : ''}{grievanceOfficer.email ? ` · ${grievanceOfficer.email}` : ''}{grievanceOfficer.phone ? ` · ${grievanceOfficer.phone}` : ''}{grievanceOfficer.address ? ` · ${grievanceOfficer.address}` : ''}</p>}
    <div className="site-footer-bottom">
      <span className="site-footer-copy">{(footer?.copyright || '© {year} NexTech').replace('{year}', String(new Date().getFullYear()))}</span>
      {pages.filter((p) => p.show_in_footer && p.footer_group === 'bottom').map((p) => (
        <button key={p.slug} type="button" className="site-footer-legal-link" onClick={() => openPage(p.slug)}>
          {p.slug === 'privacy-choices' && <svg aria-hidden width="16" height="10" viewBox="0 0 32 20"><rect x="1" y="1" width="30" height="18" rx="9" fill="#0a5ad1" /><circle cx="10" cy="10" r="7" fill="#fff" /><path d="M20 6l6 8M26 6l-6 8" stroke="#fff" strokeWidth="2" strokeLinecap="round" /></svg>}
          {p.title}
        </button>
      ))}
    </div>
  </footer>
  </>
}
