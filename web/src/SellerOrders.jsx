import { useCallback, useEffect, useState } from 'react'
import { storeMoney } from './money'

// Seller Center -> Manage orders (modelled on Temu's): Pending / Unshipped /
// Shipped / Canceled tabs, "action needed" filters, a date range (last 30
// days by default), sorting, and a search by up to 100 IDs at a time.

const API_URL = import.meta.env.VITE_API_URL ?? 'http://127.0.0.1:8000/api'
const MAX_IDS = 100

async function readJson(response) {
  const text = await response.text()
  try { return text ? JSON.parse(text) : {} } catch { return {} }
}

async function send(headers, path, method = 'GET', body) {
  const response = await fetch(`${API_URL}${path}`, { method, headers: { ...headers(), ...(body ? { 'Content-Type': 'application/json' } : {}) }, body: body ? JSON.stringify(body) : undefined })
  const data = await readJson(response)
  if (!response.ok) throw new Error(data.message ?? Object.values(data.errors ?? {})[0]?.[0] ?? 'Something went wrong.')
  return data
}

const money = (cents) => storeMoney(cents ?? 0)
const dateTime = (value) => (value ? new Date(value).toLocaleString([], { dateStyle: 'medium', timeStyle: 'short' }) : '—')
const shortDate = (value) => (value ? new Date(`${String(value).slice(0, 10)}T12:00:00`).toLocaleDateString([], { month: 'short', day: 'numeric', year: 'numeric' }) : '—')

const STATUS_TABS = [
  ['pending', 'Pending', 'Orders with this status do not need to be shipped yet. An order usually moves from Pending to Unshipped about 30 minutes after it’s placed.'],
  ['unshipped', 'Unshipped', 'Items in these orders haven’t shipped. Check the ship-by date and make sure they arrive within the promised delivery time.'],
  ['shipped', 'Shipped', 'Orders that have been partly or fully shipped.'],
  ['cancelled', 'Canceled', 'Orders that were canceled or fully refunded.'],
  ['all', 'All', 'Every order in the selected period.'],
]
const STATUS_PILL = { pending: ['Pending', 'hidden'], unshipped: ['Unshipped', 'pending'], shipped: ['Shipped', 'approved'], cancelled: ['Canceled', 'rejected'] }
const ACTIONS = [
  ['buyer_contacted', 'Buyer contacted buyer service', 'The buyer asked NexTech support about these orders.'],
  ['address_change', 'Buyer requested address change', 'Accept or decline before you ship.'],
  ['delay_risk', 'At risk of delayed shipment', 'Ship today so the order isn’t canceled for shipping late.'],
]
const ID_TYPES = [['any', 'All ID types'], ['order', 'Order ID'], ['goods', 'Goods ID'], ['sku', 'SKU ID'], ['tracking', 'Tracking number'], ['item', 'Order item ID']]
const RANGES = [['7', 'Last 7 days'], ['30', 'Last 30 days'], ['90', 'Last 90 days'], ['180', 'Last 180 days'], ['365', 'Last 12 months'], ['custom', 'Custom range']]
const SORTS = [['newest', 'Order date: newest first'], ['oldest', 'Order date: oldest first'], ['ship_by', 'Ship-by date: soonest first']]

const addressLines = (a) => [a?.name, a?.line1, a?.line2, [a?.city, a?.state, a?.postal_code].filter(Boolean).join(', ')].filter(Boolean)

export function ManageOrders({ headers, go, shipsItself, onSummary }) {
  const [filters, setFilters] = useState({ status: 'unshipped', action: '', range: '30', from: '', to: '', sort: 'newest', page: 1 })
  const [search, setSearch] = useState({ q: '', type: 'any', applied: '' })
  const [data, setData] = useState(null)
  const [msg, setMsg] = useState('')
  const [detail, setDetail] = useState(null)
  const [busy, setBusy] = useState(false)

  const load = useCallback(() => {
    if (filters.range === 'custom' && (!filters.from || !filters.to)) return
    const qs = new URLSearchParams({ status: filters.status, range: filters.range, sort: filters.sort, page: String(filters.page) })
    if (filters.action) qs.set('action', filters.action)
    if (filters.range === 'custom') { qs.set('from', filters.from); qs.set('to', filters.to) }
    if (search.applied) { qs.set('q', search.applied); qs.set('q_type', search.type) }
    setBusy(true)
    send(headers, `/seller/orders?${qs}`)
      .then((d) => { setData(d.data); onSummary?.(d.data.summary) })
      .catch((e) => setMsg(e.message))
      .finally(() => setBusy(false))
  }, [headers, filters, search.applied, search.type, onSummary])
  useEffect(() => { Promise.resolve().then(load) }, [load])

  const set = (patch) => setFilters((f) => ({ ...f, page: 1, ...patch }))

  function runSearch(event) {
    event.preventDefault()
    const ids = search.q.split(/[\s,]+/).filter(Boolean)
    if (ids.length > MAX_IDS) { setMsg(`You can search up to ${MAX_IDS} IDs at a time — you entered ${ids.length}.`); return }
    setMsg('')
    setSearch((s) => ({ ...s, applied: ids.join(',') }))
    set({})
  }

  async function decide(order, decision) {
    const note = decision === 'decline' ? window.prompt('Why can’t the address be changed? The buyer sees this.', '') : null
    if (decision === 'decline' && !note) return
    if (decision === 'approve' && !window.confirm('Ship this order to the new address?')) return
    try {
      const d = await send(headers, `/seller/orders/${order.id}/address-change/${order.address_change.id}`, 'POST', { decision, note })
      setDetail(d.data)
      setMsg(decision === 'approve' ? 'Address updated — ship to the new address.' : 'Declined — the order ships to the original address.')
      load()
    } catch (e) { setMsg(e.message) }
  }

  const counts = data?.counts ?? {}
  const actions = data?.actions ?? {}
  const orders = data?.orders ?? []
  const pages = data ? Math.max(1, Math.ceil(data.total / data.per_page)) : 1
  const tabInfo = STATUS_TABS.find(([k]) => k === filters.status)

  return (
    <>
      <div className="so-head">
        <h1 className="sc-title">Manage orders</h1>
        <form className="so-search" onSubmit={runSearch}>
          <select value={search.type} onChange={(e) => setSearch({ ...search, type: e.target.value })} aria-label="ID type">{ID_TYPES.map(([v, l]) => <option key={v} value={v}>{l}</option>)}</select>
          <input value={search.q} onChange={(e) => setSearch({ ...search, q: e.target.value })} placeholder="Up to 100 IDs, separated by commas" aria-label="Search IDs" />
          <button type="submit" className="sc-primary">Search</button>
          {search.applied && <button type="button" className="seller-btn ghost" onClick={() => { setSearch({ q: '', type: 'any', applied: '' }); set({}) }}>Clear</button>}
        </form>
      </div>
      <p className="sc-muted so-intro">View buyers&rsquo; orders, see what needs your action, and process shipments.</p>
      {msg && <div className="sc-alert warn"><span>{msg}</span><button type="button" onClick={() => setMsg('')}>OK</button></div>}

      <div className="sc-card">
        <h2 className="sc-h2">Action needed</h2>
        <div className="sc-action-grid">
          {ACTIONS.map(([key, label, hint]) => (
            <button type="button" key={key} title={hint} className={`sc-action${filters.action === key ? ' sel' : ''}${actions[key] ? ' hot' : ''}`} onClick={() => set({ action: filters.action === key ? '' : key })}>
              <span>{label}</span><strong>{actions[key] ?? 0}</strong>
            </button>
          ))}
        </div>
        <p className="sc-muted so-note">If a buyer contacts you with a shipping address and asks you to ship an order that&rsquo;s still <b>Pending</b>, don&rsquo;t ship it. Address changes go through the buyer&rsquo;s order page, and appear here for you to accept.</p>
      </div>

      {search.applied ? (
        <div className="sc-alert warn"><span>Showing results for {search.applied.split(',').length} ID{search.applied.split(',').length === 1 ? '' : 's'} across all dates{data?.not_found?.length ? ` · not found: ${data.not_found.join(', ')}` : ''}.</span></div>
      ) : filters.action ? (
        <div className="sc-alert warn"><span>Filtered: {ACTIONS.find(([k]) => k === filters.action)?.[1]}</span><button type="button" onClick={() => set({ action: '' })}>Clear</button></div>
      ) : (
        <div className="sc-tabs">
          {STATUS_TABS.map(([key, label]) => <button type="button" key={key} className={filters.status === key ? 'active' : ''} onClick={() => set({ status: key })}>{label} <small>{counts[key] ?? 0}</small></button>)}
        </div>
      )}
      {!search.applied && !filters.action && tabInfo && <p className="sc-muted so-note">{tabInfo[2]}</p>}

      <div className="so-filters">
        <label>Order date
          <select value={filters.range} disabled={!!search.applied} onChange={(e) => set({ range: e.target.value })}>{RANGES.map(([v, l]) => <option key={v} value={v}>{l}</option>)}</select>
        </label>
        {filters.range === 'custom' && !search.applied && <>
          <label>From<input type="date" value={filters.from} max={filters.to || undefined} onChange={(e) => set({ from: e.target.value })} /></label>
          <label>To<input type="date" value={filters.to} min={filters.from || undefined} onChange={(e) => set({ to: e.target.value })} /></label>
        </>}
        <label>Sort by
          <select value={filters.sort} onChange={(e) => set({ sort: e.target.value })}>{SORTS.map(([v, l]) => <option key={v} value={v}>{l}</option>)}</select>
        </label>
        {busy && <span className="sc-muted">Loading…</span>}
      </div>

      <div className="sc-card sc-table-card">
        <div className="sc-table-wrap">
          <table className="sc-table">
            <thead><tr><th>Order</th><th>Items</th><th>Ship by · delivery</th><th>Tracking</th><th>Status</th><th></th></tr></thead>
            <tbody>
              {orders.map((o) => (
                <tr key={o.id}>
                  <td>
                    <button type="button" className="sc-link" onClick={() => setDetail(o)}>#{o.id}</button>
                    <small className="sc-muted">{dateTime(o.created_at)}</small>
                    <span className="so-flags">
                      {o.buyer_contacted_at && <span className="sc-pill pending" title={`Last message ${dateTime(o.buyer_contacted_at)}`}>Buyer contacted support</span>}
                      {o.address_change && <span className="sc-pill rejected">Address change requested</span>}
                      {o.delay_risk && <span className="sc-pill rejected">{o.overdue ? 'Ship-by date passed' : 'Ship today'}</span>}
                    </span>
                  </td>
                  <td>{o.items.map((i) => <div key={i.id}>{i.product_name}{i.variant_label ? ` · ${i.variant_label}` : ''} <span className="sc-muted">× {i.quantity}</span><small className="sc-muted">Goods ID {i.product_id ?? '—'} · SKU {i.sku ?? '—'}</small></div>)}</td>
                  <td>{o.fulfilled_by === 'nextech' ? <span className="sc-muted">NexTech delivers</span> : <>{shortDate(o.ship_by)}<small className="sc-muted">arrives {shortDate(o.deliver_from)}–{shortDate(o.deliver_by)}</small></>}</td>
                  <td>{o.packages.length ? o.packages.map((p) => <div key={p.id}>{p.carrier} {p.tracking_url ? <a href={p.tracking_url} target="_blank" rel="noreferrer">{p.tracking_number}</a> : p.tracking_number}</div>) : <span className="sc-muted">—</span>}</td>
                  <td>
                    <span className={`sc-pill ${STATUS_PILL[o.status]?.[1] ?? ''}`}>{STATUS_PILL[o.status]?.[0] ?? o.status}</span>
                    {o.pending_until && <small className="sc-muted">until {new Date(o.pending_until).toLocaleTimeString([], { timeStyle: 'short' })}</small>}
                    {o.awaiting_payment && <small className="sc-muted">awaiting payment</small>}
                  </td>
                  <td className="sc-actions">
                    <button type="button" onClick={() => setDetail(o)}>Details</button>
                    {shipsItself && o.status === 'unshipped' && o.fulfilled_by !== 'nextech' && !o.address_change && <button type="button" onClick={() => go('ship-orders')}>Ship</button>}
                  </td>
                </tr>
              ))}
              {data && orders.length === 0 && <tr><td colSpan="6" className="sc-empty">No orders match.</td></tr>}
            </tbody>
          </table>
        </div>
        {pages > 1 && (
          <div className="so-pager">
            <button type="button" className="seller-btn ghost" disabled={filters.page <= 1} onClick={() => setFilters((f) => ({ ...f, page: f.page - 1 }))}>Previous</button>
            <span className="sc-muted">Page {filters.page} of {pages} · {data.total} orders</span>
            <button type="button" className="seller-btn ghost" disabled={filters.page >= pages} onClick={() => setFilters((f) => ({ ...f, page: f.page + 1 }))}>Next</button>
          </div>
        )}
        <p className="sc-muted sc-foot">Only your items in each order are shown. Still have questions? <button type="button" className="sc-link" onClick={() => go('messages')}>Contact your NexTech selling partner</button>.</p>
      </div>

      {detail && (
        <div className="ss-overlay" role="presentation" onClick={() => setDetail(null)}>
          <div className="ss-modal ss-wide" onClick={(e) => e.stopPropagation()}>
            <div className="sc-head"><h2 className="sc-h2">Order #{detail.id}</h2><span className={`sc-pill ${STATUS_PILL[detail.status]?.[1] ?? ''}`}>{STATUS_PILL[detail.status]?.[0]}</span></div>
            <p className="sc-muted">Placed {dateTime(detail.created_at)}</p>
            {detail.status === 'pending' && <div className="sc-alert warn"><span>{detail.awaiting_payment ? 'The buyer hasn’t finished paying — don’t ship this order.' : `Pending — don’t ship yet. It moves to Unshipped at ${new Date(detail.pending_until).toLocaleTimeString([], { timeStyle: 'short' })}.`}</span></div>}
            {detail.buyer_contacted_at && <div className="sc-alert warn"><span>The buyer contacted NexTech buyer service about this order (last message {dateTime(detail.buyer_contacted_at)}). NexTech will message you if anything is needed from you.</span></div>}
            {detail.address_change && (
              <div className="ss-setup">
                <p><b>The buyer asked to ship to a new address</b> <span className="sc-muted">({dateTime(detail.address_change.created_at)})</span></p>
                <div className="so-addr-compare">
                  <div><small className="sc-muted">Current</small>{addressLines(detail.ship_to).map((l) => <div key={l}>{l}</div>)}</div>
                  <div><small className="sc-muted">Requested</small>{addressLines(detail.address_change.address).map((l) => <div key={l}>{l}</div>)}</div>
                </div>
                <div className="ss-actions"><button type="button" className="seller-btn ghost" onClick={() => decide(detail, 'decline')}>Decline</button><button type="button" className="sc-primary" onClick={() => decide(detail, 'approve')}>Accept new address</button></div>
              </div>
            )}
            {detail.address_change_nextech && <p className="sc-muted">The buyer asked to change the address — NexTech handles it for orders it delivers.</p>}
            <dl className="ob-facts">
              <dt>{detail.fulfilled_by === 'nextech' ? 'Delivering to' : 'Ship to'}</dt><dd>{addressLines(detail.ship_to).join(', ') || '—'}</dd>
              {detail.fulfilled_by !== 'nextech' && <><dt>Ship by</dt><dd className={detail.overdue ? 'sc-low' : ''}>{shortDate(detail.ship_by)}{detail.overdue ? ' — overdue' : ''}</dd><dt>Delivery promised</dt><dd>{shortDate(detail.deliver_from)} – {shortDate(detail.deliver_by)}</dd></>}
              <dt>Fulfilled by</dt><dd>{{ seller: 'You ship it', mixed: 'You ship some items; NexTech delivers the rest', nextech: 'NexTech picks up and delivers' }[detail.fulfilled_by]}</dd>
            </dl>
            <table className="sc-table">
              <thead><tr><th>Item</th><th>Goods ID</th><th>SKU ID</th><th>Order item ID</th><th>Qty</th><th>Total</th></tr></thead>
              <tbody>{detail.items.map((i) => <tr key={i.id}><td>{i.product_name}{i.variant_label ? ` · ${i.variant_label}` : ''}</td><td>{i.product_id ?? '—'}</td><td>{i.sku ?? '—'}</td><td>{i.id}</td><td>{i.quantity}</td><td>{money(i.line_total_cents)}</td></tr>)}</tbody>
            </table>
            {detail.packages.length > 0 && (
              <>
                <h3 className="ss-sub">Packages</h3>
                {detail.packages.map((p) => <p key={p.id}>{p.carrier} {p.tracking_url ? <a href={p.tracking_url} target="_blank" rel="noreferrer">{p.tracking_number}</a> : p.tracking_number} · {p.status.replace('_', ' ')} · shipped {dateTime(p.shipped_at)}</p>)}
              </>
            )}
            <div className="ss-actions">
              {shipsItself && detail.status === 'unshipped' && detail.fulfilled_by !== 'nextech' && !detail.address_change && <button type="button" className="sc-primary" onClick={() => { setDetail(null); go('ship-orders') }}>Ship this order</button>}
              <button type="button" className="seller-btn ghost" onClick={() => setDetail(null)}>Close</button>
            </div>
          </div>
        </div>
      )}
    </>
  )
}
