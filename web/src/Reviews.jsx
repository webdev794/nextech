import { useCallback, useEffect, useState } from 'react'
import { mediaUrl } from './mediaUrl'
import './Reviews.css'

// Buyer reviews on the storefront (backend ReviewController): a product's
// approved reviews with star/photo filters and "Helpful", a reviewer's public
// page, and writing a review (stars, text, up to 6 photos) for a delivered
// item from Your orders. Reviews wait for admin approval before anyone sees them.

const API_URL = import.meta.env.VITE_API_URL ?? 'http://127.0.0.1:8000/api'
const token = () => { try { return localStorage.getItem('gdp_token') } catch { return null } }
const headers = (json) => ({ Accept: 'application/json', ...(token() ? { Authorization: `Bearer ${token()}` } : {}), ...(json ? { 'Content-Type': 'application/json' } : {}) })

async function readJson(response) {
  const text = await response.text()
  try { return text ? JSON.parse(text) : {} } catch { return {} }
}

const fmtDate = (d) => new Date(d).toLocaleDateString(undefined, { year: 'numeric', month: 'short', day: 'numeric' })

export function Stars({ value, size = 14, label }) {
  const filled = Math.round(Math.max(0, Math.min(5, Number(value) || 0)))
  return (
    <span className="rv-stars" style={{ fontSize: size }} role="img" aria-label={label ?? `${Number(value).toFixed(1)} out of 5 stars`}>
      {[1, 2, 3, 4, 5].map((i) => <span key={i} aria-hidden className={i <= filled ? 'on' : ''}>★</span>)}
    </span>
  )
}

function ReviewCard({ review, onReviewer, onProduct, showProduct = false }) {
  const [helpful, setHelpful] = useState({ count: review.helpful_count, voted: review.voted })
  const [msg, setMsg] = useState('')
  async function toggleHelpful() {
    if (!token()) { setMsg('Sign in to mark reviews helpful.'); return }
    const res = await fetch(`${API_URL}/reviews/${review.id}/helpful`, { method: 'POST', headers: headers() })
    const data = await readJson(res)
    if (res.ok) setHelpful({ count: data.data.helpful_count, voted: data.data.voted })
    else setMsg(data.message ?? 'Could not save.')
  }
  return (
    <article className="rv-card">
      <header className="rv-card-head">
        {onReviewer ? <button type="button" className="rv-name" onClick={() => onReviewer(review.reviewer.id)}>{review.reviewer.name}</button> : <strong>{review.reviewer.name}</strong>}
        <span className="rv-date">{fmtDate(review.created_at)}</span>
      </header>
      <Stars value={review.rating} />
      <span className="rv-verified">Verified purchase{review.variant_label ? ` · ${review.variant_label}` : ''}</span>
      {showProduct && review.product && <button type="button" className="rv-product" onClick={() => onProduct?.(review.product.slug)}>{review.product.image_url && <img src={mediaUrl(review.product.image_url)} alt="" />}<span>{review.product.name}</span></button>}
      {review.body && <p className="rv-body">{review.body}</p>}
      {review.images?.length > 0 && <div className="rv-photos">{review.images.map((src) => <a key={src} href={mediaUrl(src)} target="_blank" rel="noreferrer"><img src={mediaUrl(src)} alt="Buyer photo" loading="lazy" /></a>)}</div>}
      <button type="button" className={`rv-helpful${helpful.voted ? ' on' : ''}`} onClick={toggleHelpful}>👍 Helpful{helpful.count > 0 ? ` (${helpful.count})` : ''}</button>
      {msg && <p className="rv-msg">{msg}</p>}
    </article>
  )
}

/** Approved reviews for a product page, with a star breakdown and filters. */
export function ProductReviews({ productId, onReviewer }) {
  const [filter, setFilter] = useState({ rating: null, photos: false })
  const [state, setState] = useState({ rows: [], summary: null, page: 1, last: 1, loading: true })

  const load = useCallback(async (page, append) => {
    const q = new URLSearchParams({ page: String(page), ...(filter.rating ? { rating: String(filter.rating) } : {}), ...(filter.photos ? { photos: '1' } : {}) })
    try {
      const data = await readJson(await fetch(`${API_URL}/products/${productId}/reviews?${q}`, { headers: headers() }))
      setState((s) => ({ rows: append ? [...s.rows, ...(data.data ?? [])] : (data.data ?? []), summary: s.summary && append ? s.summary : (filter.rating || filter.photos ? (s.summary ?? data.summary) : data.summary), page: data.meta?.current_page ?? page, last: data.meta?.last_page ?? 1, loading: false }))
    } catch { setState((s) => ({ ...s, loading: false })) }
  }, [productId, filter])

  useEffect(() => { Promise.resolve().then(() => load(1, false)) }, [load])

  const summary = state.summary
  if (state.loading && !summary) return null
  if (!summary || summary.count === 0) {
    return <section className="pdp-reviews"><h2>Ratings &amp; reviews</h2><p className="rv-empty">No reviews yet. Bought this? Review it from Your orders once it&rsquo;s delivered.</p></section>
  }
  return (
    <section className="pdp-reviews">
      <h2>Ratings &amp; reviews</h2>
      <div className="rv-summary">
        <div className="rv-score"><b>{summary.average.toFixed(1)}</b><Stars value={summary.average} size={18} /><span className="rv-score-count">{summary.count} {summary.count === 1 ? 'review' : 'reviews'} · verified purchases</span></div>
        <div className="rv-bars">
          {[5, 4, 3, 2, 1].map((star) => {
            const n = summary.by_star?.[star] ?? 0
            return (
              <button type="button" key={star} className={`rv-bar${filter.rating === star ? ' on' : ''}`} disabled={!n} onClick={() => setFilter((f) => ({ ...f, rating: f.rating === star ? null : star }))}>
                <span>{star} ★</span><span className="rv-bar-track"><span style={{ width: `${summary.count ? (n / summary.count) * 100 : 0}%` }} /></span><span>{n}</span>
              </button>
            )
          })}
        </div>
      </div>
      <div className="rv-filters">
        <button type="button" className={!filter.rating && !filter.photos ? 'on' : ''} onClick={() => setFilter({ rating: null, photos: false })}>All</button>
        <button type="button" className={filter.photos ? 'on' : ''} onClick={() => setFilter((f) => ({ ...f, photos: !f.photos }))}>With photos</button>
        {filter.rating && <button type="button" className="on" onClick={() => setFilter((f) => ({ ...f, rating: null }))}>{filter.rating} ★ ✕</button>}
      </div>
      {state.rows.length === 0 ? <p className="rv-empty">No reviews match.</p> : <div className="rv-grid">{state.rows.map((r) => <ReviewCard key={r.id} review={r} onReviewer={onReviewer} />)}</div>}
      {state.page < state.last && <button type="button" className="switch-auth pdp-see-all" onClick={() => load(state.page + 1, true)}>Show more reviews</button>}
    </section>
  )
}

/** A reviewer's public page: who they are (first name + initial) and their approved reviews. */
export function ReviewerPage({ userId, onBack, onProduct }) {
  const [rating, setRating] = useState(null)
  const [state, setState] = useState(null)
  const load = useCallback(async (page, append) => {
    const q = new URLSearchParams({ page: String(page), ...(rating ? { rating: String(rating) } : {}) })
    try {
      const data = await readJson(await fetch(`${API_URL}/reviewers/${userId}/reviews?${q}`, { headers: headers() }))
      setState((s) => ({ profile: data.profile ?? s?.profile, rows: append ? [...(s?.rows ?? []), ...(data.data ?? [])] : (data.data ?? []), page: data.meta?.current_page ?? page, last: data.meta?.last_page ?? 1 }))
    } catch { setState({ profile: null, rows: [], page: 1, last: 1 }) }
  }, [userId, rating])
  useEffect(() => { Promise.resolve().then(() => load(1, false)) }, [load])

  if (!state) return <p className="rv-empty">Loading…</p>
  const p = state.profile
  return (
    <article className="rv-reviewer">
      <button type="button" className="text-button" onClick={onBack}>&larr; Back</button>
      {p && <header className="rv-reviewer-head">
        <span className="rv-avatar" aria-hidden>{p.name.slice(0, 1)}</span>
        <div><h1>{p.name}</h1><p>{p.reviews} {p.reviews === 1 ? 'review' : 'reviews'} · {p.helpfuls} helpful</p></div>
      </header>}
      <div className="rv-filters">
        <button type="button" className={!rating ? 'on' : ''} onClick={() => setRating(null)}>All</button>
        {[5, 4, 3, 2, 1].map((s) => <button type="button" key={s} disabled={!p?.by_star?.[s]} className={rating === s ? 'on' : ''} onClick={() => setRating(s)}>{s} ★ ({p?.by_star?.[s] ?? 0})</button>)}
      </div>
      {state.rows.length === 0 ? <p className="rv-empty">No reviews to show.</p> : <div className="rv-grid">{state.rows.map((r) => <ReviewCard key={r.id} review={r} showProduct onProduct={onProduct} />)}</div>}
      {state.page < state.last && <button type="button" className="switch-auth pdp-see-all" onClick={() => load(state.page + 1, true)}>Show more</button>}
    </article>
  )
}

/** Account → Your reviews: everything the buyer wrote, with its status. */
export function MyReviews({ onProduct, onOrders, onProfile }) {
  const [state, setState] = useState(null)
  const load = useCallback(async (page, append) => {
    try {
      const data = await readJson(await fetch(`${API_URL}/my/reviews?page=${page}`, { headers: headers() }))
      setState((s) => ({ rows: append ? [...(s?.rows ?? []), ...(data.data ?? [])] : (data.data ?? []), page: data.meta?.current_page ?? page, last: data.meta?.last_page ?? 1, total: data.meta?.total ?? 0, profileId: data.profile_id }))
    } catch { setState({ rows: [], page: 1, last: 1, total: 0 }) }
  }, [])
  useEffect(() => { Promise.resolve().then(() => load(1, false)) }, [load])

  if (!state) return <p className="rv-empty">Loading…</p>
  if (!state.rows.length) {
    return <p className="account-hint">You haven&rsquo;t reviewed anything yet. Once an order is delivered, open <button type="button" className="text-button" onClick={onOrders}>Your orders</button> and choose &ldquo;Write a review&rdquo; next to the item.</p>
  }
  const label = { pending: 'Waiting for approval', approved: 'Published', rejected: 'Not published' }
  return (
    <div className="rv-mine">
      <p className="rv-mine-head">{state.total} {state.total === 1 ? 'review' : 'reviews'}{state.rows.some((r) => r.status === 'approved') && <> · <button type="button" className="text-button" onClick={() => onProfile(state.profileId)}>See your public reviews page</button></>}</p>
      <div className="rv-grid">
        {state.rows.map((r) => (
          <article key={r.id} className="rv-card">
            {r.product && <button type="button" className="rv-product" onClick={() => onProduct(r.product.slug)}>{r.product.image_url && <img src={mediaUrl(r.product.image_url)} alt="" />}<span>{r.product.name}{r.variant_label ? ` · ${r.variant_label}` : ''}</span></button>}
            <header className="rv-card-head"><Stars value={r.rating} /><span className="rv-date">{fmtDate(r.created_at)}</span></header>
            <span className={`rv-status rv-status-${r.status}`}>{label[r.status] ?? r.status}{r.status === 'approved' && r.helpful_count > 0 ? ` · ${r.helpful_count} found it helpful` : ''}</span>
            {r.body && <p className="rv-body">{r.body}</p>}
            {r.images?.length > 0 && <div className="rv-photos">{r.images.map((src) => <a key={src} href={mediaUrl(src)} target="_blank" rel="noreferrer"><img src={mediaUrl(src)} alt="" loading="lazy" /></a>)}</div>}
          </article>
        ))}
      </div>
      {state.page < state.last && <button type="button" className="switch-auth pdp-see-all" onClick={() => load(state.page + 1, true)}>Show more</button>}
    </div>
  )
}

/** Write a review for one delivered item. */
function WriteReview({ order, item, onClose, onSaved }) {
  const [rating, setRating] = useState(0)
  const [hover, setHover] = useState(0)
  const [body, setBody] = useState('')
  const [images, setImages] = useState([])
  const [busy, setBusy] = useState(false)
  const [msg, setMsg] = useState('')

  async function upload(files) {
    setMsg('')
    for (const file of [...files].slice(0, 6 - images.length)) {
      const form = new FormData()
      form.append('file', file)
      const res = await fetch(`${API_URL}/review-images`, { method: 'POST', headers: headers(), body: form })
      const data = await readJson(res)
      if (res.ok) setImages((list) => [...list, data.data.url])
      else { setMsg(data.message ?? Object.values(data.errors ?? {})[0]?.[0] ?? 'Could not upload that photo.'); break }
    }
  }

  async function submit(event) {
    event.preventDefault()
    if (!rating) { setMsg('Choose a star rating.'); return }
    setBusy(true)
    const res = await fetch(`${API_URL}/orders/${order.id}/items/${item.id}/review`, { method: 'POST', headers: headers(true), body: JSON.stringify({ rating, body: body.trim() || null, images }) })
    const data = await readJson(res)
    setBusy(false)
    if (!res.ok) { setMsg(data.message ?? Object.values(data.errors ?? {})[0]?.[0] ?? 'Could not save your review.'); return }
    onSaved({ id: data.data.id, order_item_id: item.id, rating, status: 'pending' })
  }

  return (
    <div className="overlay" role="presentation" onClick={onClose}>
      <form className="rv-write" role="dialog" aria-modal="true" aria-labelledby="rv-write-title" onClick={(e) => e.stopPropagation()} onSubmit={submit}>
        <h2 id="rv-write-title">Review {item.product_name}{item.variant_label ? ` (${item.variant_label})` : ''}</h2>
        <div className="rv-pick" role="radiogroup" aria-label="Star rating" onMouseLeave={() => setHover(0)}>
          {[1, 2, 3, 4, 5].map((s) => <button type="button" key={s} role="radio" aria-checked={rating === s} aria-label={`${s} star${s > 1 ? 's' : ''}`} className={(hover || rating) >= s ? 'on' : ''} onMouseEnter={() => setHover(s)} onClick={() => setRating(s)}>★</button>)}
          <span>{['', 'Poor', 'Fair', 'Good', 'Very good', 'Excellent'][hover || rating]}</span>
        </div>
        <label>Your review (optional)
          <textarea rows={5} maxLength={2000} value={body} placeholder="What did you like or dislike? How are you using it?" onChange={(e) => setBody(e.target.value)} />
        </label>
        <div className="rv-upload">
          {images.map((src) => <span key={src} className="rv-thumb"><img src={mediaUrl(src)} alt="" /><button type="button" aria-label="Remove photo" onClick={() => setImages((l) => l.filter((x) => x !== src))}>✕</button></span>)}
          {images.length < 6 && <label className="rv-add-photo">+ Photo<input type="file" accept="image/jpeg,image/png,image/webp,image/gif" multiple hidden onChange={(e) => { upload(e.target.files); e.target.value = '' }} /></label>}
        </div>
        <p className="rv-note">Reviews appear after a quick check by our team. Your first name and last initial are shown.</p>
        {msg && <p className="rv-msg">{msg}</p>}
        <div className="rv-actions"><button type="button" className="text-button" onClick={onClose}>Cancel</button><button type="submit" className="checkout-button" disabled={busy}>{busy ? 'Saving…' : 'Submit review'}</button></div>
      </form>
    </div>
  )
}

/**
 * Under an order in Your orders: its items, each with "Write a review" once
 * delivered, or the review's status.
 */
export function OrderItemReviews({ order, onReviewed }) {
  const [writing, setWriting] = useState(null)
  const deliveredItem = (item) => order.status === 'completed'
    || (order.packages ?? []).some((p) => p.status === 'delivered' && (p.items ?? []).some((pi) => pi.order_item_id === item.id))
  const items = (order.items ?? []).filter((item) => item.product_id && (item.review || deliveredItem(item)))
  if (!items.length) return null
  const label = { pending: 'Review submitted — waiting for approval', approved: 'Reviewed', rejected: 'Review not published' }
  return (
    <div className="rv-order-items">
      {items.map((item) => (
        <div key={item.id} className="rv-order-item">
          <span>{item.product_name}{item.variant_label ? ` (${item.variant_label})` : ''}</span>
          {item.review
            ? <span className={`rv-status rv-status-${item.review.status}`}><Stars value={item.review.rating} size={12} /> {label[item.review.status] ?? item.review.status}</span>
            : <button type="button" className="text-button" onClick={() => setWriting(item)}>Write a review</button>}
        </div>
      ))}
      {writing && <WriteReview order={order} item={writing} onClose={() => setWriting(null)} onSaved={(review) => { setWriting(null); onReviewed(order.id, writing.id, review) }} />}
    </div>
  )
}
