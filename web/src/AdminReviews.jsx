import { useCallback, useEffect, useState } from 'react'
import { mediaUrl } from './mediaUrl'
import { Stars } from './Reviews'

// Admin -> Reviews (backend AdminReviewController): every buyer review waits
// here until approved — on the product page and the reviewer's public page,
// or the product page only — or rejected with a note. Reviews (and their
// photos) can be deleted.

const API_URL = import.meta.env.VITE_API_URL ?? 'http://127.0.0.1:8000/api'
const STATUSES = [['pending', 'Waiting'], ['approved', 'Approved'], ['rejected', 'Rejected'], ['all', 'All']]

async function readJson(response) {
  const text = await response.text()
  try { return text ? JSON.parse(text) : {} } catch { return {} }
}

export function AdminReviews({ authHeaders, onMessage, onPending }) {
  const [filter, setFilter] = useState({ status: 'pending', rating: '', search: '', page: 1 })
  const [search, setSearch] = useState('')
  const [data, setData] = useState(null)
  const [busy, setBusy] = useState(null)

  const load = useCallback(async () => {
    const q = new URLSearchParams({ status: filter.status, page: String(filter.page), ...(filter.rating ? { rating: filter.rating } : {}), ...(filter.search ? { search: filter.search } : {}) })
    try {
      const d = await readJson(await fetch(`${API_URL}/admin/reviews?${q}`, { headers: authHeaders() }))
      setData(d)
      onPending?.(d.pending ?? 0)
    } catch { onMessage('Could not load reviews.') }
  }, [authHeaders, filter, onMessage, onPending])
  useEffect(() => { Promise.resolve().then(load) }, [load])

  async function act(review, path, body, method = 'POST', done = '') {
    setBusy(review.id)
    try {
      const res = await fetch(`${API_URL}/admin/reviews/${review.id}${path}`, { method, headers: { ...authHeaders(), 'Content-Type': 'application/json' }, body: body ? JSON.stringify(body) : undefined })
      if (!res.ok) throw new Error((await readJson(res)).message ?? 'Could not save.')
      onMessage(done)
      await load()
    } catch (e) { onMessage(e.message) }
    setBusy(null)
  }

  const reject = (r) => {
    const note = window.prompt('Why is this review not being published? (kept for your records; the buyer isn\'t told)', '')
    if (note !== null) act(r, '/reject', { note: note.trim() || null }, 'POST', 'Review rejected.')
  }
  const remove = (r) => { if (window.confirm('Delete this review and its photos for good?')) act(r, '', null, 'DELETE', 'Review deleted.') }

  return (
    <section className="admin-panel">
      <div className="admin-toolbar admin-rv-toolbar">
        <div className="admin-filters">
          {STATUSES.map(([value, label]) => (
            <button key={value} type="button" className={filter.status === value ? 'chip active' : 'chip'} onClick={() => setFilter((f) => ({ ...f, status: value, page: 1 }))}>
              {label}{value === 'pending' && data?.pending ? ` (${data.pending})` : ''}
            </button>
          ))}
        </div>
        <select value={filter.rating} onChange={(e) => setFilter((f) => ({ ...f, rating: e.target.value, page: 1 }))} aria-label="Stars">
          <option value="">All stars</option>
          {[5, 4, 3, 2, 1].map((s) => <option key={s} value={s}>{s} stars</option>)}
        </select>
        <form onSubmit={(e) => { e.preventDefault(); setFilter((f) => ({ ...f, search: search.trim(), page: 1 })) }}>
          <input type="search" placeholder="Search review, product, buyer" value={search} onChange={(e) => setSearch(e.target.value)} />
        </form>
      </div>

      {!data ? <p className="muted">Loading…</p> : data.data?.length === 0 ? <p className="muted">{filter.status === 'pending' ? 'No reviews waiting — all caught up.' : 'No reviews here.'}</p> : (
        <div className="admin-rv-list">
          {data.data.map((r) => (
            <article key={r.id} className={`admin-rv admin-rv-${r.status}`}>
              <div className="admin-rv-product">
                {r.product?.image_url && <img src={mediaUrl(r.product.image_url)} alt="" />}
                <div>
                  <b>{r.product?.name ?? 'Deleted product'}</b>
                  <span className="muted">{r.shop ? `Sold by ${r.shop}` : 'NexTech'}{r.variant_label ? ` · ${r.variant_label}` : ''}</span>
                </div>
              </div>
              <div className="admin-rv-main">
                <p className="admin-rv-meta"><Stars value={r.rating} /> <b>{r.reviewer_full_name ?? r.reviewer?.name}</b> <span className="muted">{r.reviewer_email} · shown as &ldquo;{r.reviewer?.name}&rdquo; · {new Date(r.created_at).toLocaleString()}</span></p>
                {r.body ? <p className="admin-rv-body">{r.body}</p> : <p className="muted">No written review — stars only.</p>}
                {r.images?.length > 0 && <div className="admin-rv-photos">{r.images.map((src) => <a key={src} href={mediaUrl(src)} target="_blank" rel="noreferrer"><img src={mediaUrl(src)} alt="Buyer photo" /></a>)}</div>}
                <p className="muted admin-rv-state">
                  {r.status === 'pending' && 'Waiting for approval'}
                  {r.status === 'approved' && `Published${r.show_on_profile ? ' on the product page and the reviewer\'s page' : ' on the product page only'} · ${r.helpful_count} helpful`}
                  {r.status === 'rejected' && `Rejected${r.admin_note ? `: ${r.admin_note}` : ''}`}
                </p>
              </div>
              <div className="admin-rv-actions">
                {r.status !== 'approved' && <>
                  <button type="button" className="act" disabled={busy === r.id} onClick={() => act(r, '/approve', { show_on_profile: true }, 'POST', 'Review published.')}>Approve</button>
                  <button type="button" className="act ghost" disabled={busy === r.id} onClick={() => act(r, '/approve', { show_on_profile: false }, 'POST', 'Review published on the product page only.')}>Approve, product page only</button>
                </>}
                {r.status !== 'rejected' && <button type="button" className="act ghost" disabled={busy === r.id} onClick={() => reject(r)}>{r.status === 'approved' ? 'Unpublish' : 'Reject'}</button>}
                <button type="button" className="link danger" disabled={busy === r.id} onClick={() => remove(r)}>Delete</button>
              </div>
            </article>
          ))}
        </div>
      )}

      {data?.meta?.last_page > 1 && (
        <div className="admin-pager">
          <button type="button" disabled={filter.page <= 1} onClick={() => setFilter((f) => ({ ...f, page: f.page - 1 }))}>&lsaquo; Prev</button>
          <span>Page {data.meta.current_page} of {data.meta.last_page} · {data.meta.total} reviews</span>
          <button type="button" disabled={filter.page >= data.meta.last_page} onClick={() => setFilter((f) => ({ ...f, page: f.page + 1 }))}>Next &rsaquo;</button>
        </div>
      )}
    </section>
  )
}
