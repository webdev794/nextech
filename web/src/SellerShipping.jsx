import { useCallback, useEffect, useState } from 'react'

// Seller Center shipping (Temu-style): Shipping settings — how orders ship,
// ship-from addresses, shipping templates, working days — and "Ship orders"
// for sellers who ship themselves: confirm shipment, buy a NexTech label,
// correct tracking (one by one or in bulk), mark delivered.

const API_URL = import.meta.env.VITE_API_URL ?? 'http://127.0.0.1:8000/api'
import { currencySymbol, storeMoney } from './money'

const money = (cents) => storeMoney(cents ?? 0)
const shortDate = (d) => (d ? new Date(d).toLocaleDateString([], { month: 'short', day: 'numeric' }) : '—')

async function readJson(response) {
  const text = await response.text()
  try { return text ? JSON.parse(text) : {} } catch { return {} }
}

async function send(headers, path, method = 'GET', body) {
  const response = await fetch(`${API_URL}${path}`, { method, headers: { ...headers(), ...(body ? { 'Content-Type': 'application/json' } : {}) }, body: body ? JSON.stringify(body) : undefined })
  const data = await readJson(response)
  if (!response.ok) {
    const error = new Error(data.message ?? Object.values(data.errors ?? {})[0]?.[0] ?? 'Something went wrong.')
    error.formatWarning = !!data.format_warning
    throw error
  }
  return data
}

const MODES = [
  ['nextech', 'NexTech collects & delivers', 'NexTech picks orders up from your ship-from address and delivers them. Customers pay NexTech’s delivery fee. Nothing to set up.'],
  ['self', 'I ship with my own courier', 'You pack and hand orders to your courier (UPS, USPS, FedEx…), then enter the tracking number. You set shipping fees and transit times in templates.'],
  ['label', 'I ship, NexTech label', 'You pack and ship, but get the shipping label from NexTech’s courier account — download it, print it and stick it on the package. Postage is deducted from your earnings.'],
]
const EMPTY_ADDRESS = { name: '', line1: '', line2: '', city: '', state: '', postal_code: '', phone: '', contact_name: '', is_default: false }
const EMPTY_GROUP = { regions: [], address_types: ['standard'], transit_min_days: 2, transit_max_days: 5, fee: '' }

// ---------------------------------------------------------------------------
// Shipping settings
// ---------------------------------------------------------------------------
export function ShippingSettings({ headers, onChanged }) {
  const [data, setData] = useState(null)
  const [tab, setTab] = useState('general')
  const [msg, setMsg] = useState('')
  const [addressForm, setAddressForm] = useState(null)
  const [templateForm, setTemplateForm] = useState(null)
  const [freeRuleOpen, setFreeRuleOpen] = useState(false)
  const [freeRuleTick, setFreeRuleTick] = useState(false)
  const [wantMode, setWantMode] = useState(null) // self | label, picked before setup was finished

  const load = useCallback(() => {
    send(headers, '/seller/shipping').then((d) => setData(d.data)).catch((e) => setMsg(e.message))
  }, [headers])
  useEffect(() => { Promise.resolve().then(load) }, [load])

  async function patch(body, okMsg) {
    setMsg('')
    try {
      const d = await send(headers, '/seller/shipping', 'PATCH', body)
      setData(d.data)
      onChanged?.(d.data)
      if (okMsg) setMsg(okMsg)
      return true
    } catch (e) { setMsg(e.message); return false }
  }

  async function saveAddress(event) {
    event.preventDefault()
    setMsg('')
    const { id, ...body } = addressForm
    try {
      await send(headers, id ? `/seller/shipping/addresses/${id}` : '/seller/shipping/addresses', id ? 'PATCH' : 'POST', body)
      setAddressForm(null)
      load()
    } catch (e) { setMsg(e.message) }
  }

  async function removeAddress(address) {
    if (!window.confirm(`Delete ${address.name}?`)) return
    try { await send(headers, `/seller/shipping/addresses/${address.id}`, 'DELETE'); load() } catch (e) { setMsg(e.message) }
  }

  function newTemplate() {
    if (!data.free_shipping_accepted_at) { setFreeRuleTick(false); setFreeRuleOpen(true); return }
    if (!data.addresses.length) { setMsg('Add a ship-from address first.'); setTab('general'); return }
    setTemplateForm({ name: '', product_type: 'standard', shop_address_id: data.addresses.find((a) => a.is_default)?.id ?? data.addresses[0].id, handling_days: 1, is_default: !data.templates.length, groups: [{ ...EMPTY_GROUP, regions: ['ALL'] }] })
  }

  function editTemplate(t) {
    setTemplateForm({ id: t.id, name: t.name, product_type: t.product_type, shop_address_id: t.shop_address_id, handling_days: t.handling_days, is_default: t.is_default, groups: t.groups.map((g) => ({ regions: g.regions, address_types: g.address_types ?? Object.keys(data.address_types ?? { standard: 1 }), transit_min_days: g.transit_min_days, transit_max_days: g.transit_max_days, fee: (g.fee_cents / 100).toFixed(2) })) })
  }

  async function saveTemplate(event) {
    event.preventDefault()
    setMsg('')
    const { id, groups, ...rest } = templateForm
    const body = { ...rest, handling_days: Number(rest.handling_days) || 1, groups: groups.map((g) => ({ regions: g.regions, address_types: g.address_types, transit_min_days: Number(g.transit_min_days), transit_max_days: Number(g.transit_max_days), fee_cents: Math.round(Number(g.fee || 0) * 100) })) }
    try {
      await send(headers, id ? `/seller/shipping/templates/${id}` : '/seller/shipping/templates', id ? 'PATCH' : 'POST', body)
      setTemplateForm(null)
      if (!id && !data.templates.length) setMsg('Thank you for completing the shipping templates setup. You can view your templates here on the Shipping settings page.')
      load()
    } catch (e) { setMsg(e.message) }
  }

  async function removeTemplate(t) {
    if (!window.confirm(`Delete template "${t.name}"? Products using it switch to your default template.`)) return
    try { await send(headers, `/seller/shipping/templates/${t.id}`, 'DELETE'); load() } catch (e) { setMsg(e.message) }
  }

  if (!data) return <div className="sc-card"><p className="sc-muted">{msg || 'Loading…'}</p></div>

  const states = Object.entries(data.states)
  const usedElsewhere = (gi) => new Set(templateForm.groups.flatMap((g, i) => (i === gi ? [] : g.regions)))
  const setGroup = (gi, patchObj) => setTemplateForm((f) => ({ ...f, groups: f.groups.map((g, i) => (i === gi ? { ...g, ...patchObj } : g)) }))

  return (
    <>
      <h1 className="sc-title">Shipping settings</h1>
      {msg && <div className="sc-alert warn"><span>{msg}</span><button type="button" onClick={() => setMsg('')}>OK</button></div>}
      <div className="sc-tabs">
        <button type="button" className={tab === 'general' ? 'active' : ''} onClick={() => setTab('general')}>General shipping settings</button>
        <button type="button" className={tab === 'templates' ? 'active' : ''} onClick={() => setTab('templates')}>Shipping templates <small>{data.templates.length}</small></button>
      </div>

      {tab === 'general' ? (
        <>
          <div className="sc-card">
            <h2 className="sc-h2">How your orders ship</h2>
            <div className="ss-modes">
              {MODES.filter(([value]) => value !== 'nextech' || data.nextech_pickup !== 'hidden' || data.fulfillment_mode === 'nextech').map(([value, title, text]) => {
                // A phased-out NexTech pickup is never shown as picked — the seller must choose one of the other modes.
                const locked = value === 'nextech' && data.nextech_pickup !== 'available'
                const checked = wantMode ? wantMode === value : data.fulfillment_mode === value && !locked
                return (
                  <label key={value} className={`ss-mode${checked ? ' active' : ''}${locked ? ' disabled' : ''}`}>
                    <input type="radio" name="fulfillment_mode" disabled={locked} checked={checked} onChange={() => {
                      // Shipping yourself needs setup first — show the steps instead of a refused save.
                      if (value !== 'nextech' && !data.setup_complete) { setWantMode(value); return }
                      setWantMode(null)
                      patch({ fulfillment_mode: value }, 'Saved — new orders use this.')
                    }} />
                    <strong>{title}{locked && (data.fulfillment_mode === 'nextech' ? ' — being phased out' : ' — not available')}{value !== 'nextech' && !data.setup_complete && <small className="ss-needs-setup">Set up first</small>}</strong>
                    <span>{text}</span>
                  </label>
                )
              })}
            </div>
            {data.fulfillment_mode === 'nextech' && data.nextech_pickup !== 'available' && <p className="ss-warn">NexTech pickup is being phased out — complete the 3 steps below, then choose &ldquo;I ship with my own courier&rdquo; or &ldquo;I ship, NexTech label&rdquo;. You can&rsquo;t add new products until you do.</p>}
            {(!data.setup_complete || wantMode) && (() => {
              const steps = [
                ['Add a ship-from address', data.addresses.length > 0, () => setAddressForm({ ...EMPTY_ADDRESS, is_default: !data.addresses.length })],
                ['Accept the free-shipping rule', !!data.free_shipping_accepted_at, () => { setFreeRuleTick(false); setFreeRuleOpen(true) }],
                ['Create a shipping template (states, transit days, fee)', data.templates.some((t) => (t.groups ?? []).length > 0), () => { setTab('templates'); newTemplate() }],
              ]
              const wantTitle = MODES.find(([v]) => v === wantMode)?.[1]
              return (
                <div className="ss-setup">
                  <p><b>{wantTitle ? `To switch to “${wantTitle}”, finish these steps:` : 'To ship orders yourself, finish these steps:'}</b></p>
                  <ul className="ss-checklist">
                    {steps.map(([label, done, go]) => (
                      <li key={label} className={done ? 'done' : ''}>
                        <span>{done ? '✓' : '○'} {label}</span>
                        {!done && <button type="button" className="sc-primary" onClick={go}>Do it</button>}
                      </li>
                    ))}
                  </ul>
                  {wantMode && data.setup_complete && <button type="button" className="sc-primary" onClick={async () => { if (await patch({ fulfillment_mode: wantMode }, 'Saved — new orders use this.')) setWantMode(null) }}>Switch to &ldquo;{wantTitle}&rdquo; now</button>}
                </div>
              )
            })()}
          </div>

          <div className="sc-card">
            <div className="sc-head"><h2 className="sc-h2">Ship-from addresses</h2><button type="button" className="sc-primary" onClick={() => setAddressForm({ ...EMPTY_ADDRESS, is_default: !data.addresses.length })}>+ Add a new address</button></div>
            {data.addresses.length === 0 ? <p className="sc-muted">No addresses yet. Add your warehouse or the place you ship from.</p> : (
              <table className="sc-table">
                <thead><tr><th>Name</th><th>Address</th><th>Contact</th><th></th></tr></thead>
                <tbody>
                  {data.addresses.map((a) => (
                    <tr key={a.id}>
                      <td><b>{a.name}</b>{a.is_default && <span className="sc-pill approved">Default</span>}</td>
                      <td>{[a.line1, a.line2, `${a.city}, ${a.state} ${a.postal_code}`].filter(Boolean).join(', ')}</td>
                      <td>{a.contact_name}<small className="sc-muted">{a.phone}</small></td>
                      <td className="sc-actions"><button type="button" onClick={() => setAddressForm({ ...EMPTY_ADDRESS, ...a, line2: a.line2 ?? '' })}>Edit</button><button type="button" className="danger" onClick={() => removeAddress(a)}>Delete</button></td>
                    </tr>
                  ))}
                </tbody>
              </table>
            )}
          </div>

          <div className="sc-card">
            <h2 className="sc-h2">Order fulfillment settings</h2>
            <p className="sc-muted">Working days count towards your handling time and the delivery dates customers see. By default you don&rsquo;t work weekends or public holidays.</p>
            <div className="ss-days">
              <label className="sc-check"><input type="checkbox" checked={data.ships_saturday} onChange={(e) => patch({ ships_saturday: e.target.checked })} /> I ship on Saturdays</label>
              <label className="sc-check"><input type="checkbox" checked={data.ships_sunday} onChange={(e) => patch({ ships_sunday: e.target.checked })} /> I ship on Sundays</label>
            </div>
            <h3 className="ss-sub">Holiday settings</h3>
            <p className="sc-muted">Tick a holiday only if both you and your courier work that day — ticked days count in delivery estimates.</p>
            <div className="ss-holidays">
              {data.holidays.map((h) => (
                <label key={h.key} className="sc-check">
                  <input type="checkbox" checked={data.working_holidays.includes(h.key)} onChange={(e) => patch({ working_holidays: e.target.checked ? [...data.working_holidays, h.key] : data.working_holidays.filter((k) => k !== h.key) })} />
                  {h.name} <span className="sc-muted">{new Date(h.date).toLocaleDateString([], { month: 'short', day: 'numeric', year: 'numeric' })}</span>
                </label>
              ))}
            </div>
          </div>
        </>
      ) : (
        <>
          <div className="sc-card ss-free">
            <div>
              <h2 className="sc-h2">Free shipping rule</h2>
              <p>Orders from your shop totalling <b>{money(data.free_shipping_threshold_cents)}</b> or more ship free to the customer — you cover the shipping cost.</p>
            </div>
            {data.free_shipping_accepted_at ? <span className="sc-pill approved">Accepted {shortDate(data.free_shipping_accepted_at)}</span> : <button type="button" className="sc-primary" onClick={() => { setFreeRuleTick(false); setFreeRuleOpen(true) }}>Review &amp; accept</button>}
          </div>
          <div className="sc-card">
            <div className="sc-head"><h2 className="sc-h2">Shipping templates</h2><button type="button" className="sc-primary" onClick={newTemplate}>+ Add template</button></div>
            {data.templates.length === 0 ? <p className="sc-muted">No templates yet. A template sets which states you ship to, the transit time and the fee for each.</p> : (
              <table className="sc-table">
                <thead><tr><th>Template</th><th>Ships from</th><th>Handling</th><th>Regions · address type · transit · fee</th><th></th></tr></thead>
                <tbody>
                  {data.templates.map((t) => (
                    <tr key={t.id}>
                      <td><b>{t.name}</b>{t.is_default && <span className="sc-pill approved">Default</span>}<small className="sc-muted">{t.product_type}</small></td>
                      <td>{t.address?.name ?? '—'}</td>
                      <td>{t.handling_days} working day{t.handling_days === 1 ? '' : 's'}</td>
                      <td>{t.groups.map((g) => <div key={g.id}>{g.regions.includes('ALL') ? 'All other states' : g.regions.join(', ')} · {(g.address_types ?? Object.keys(data.address_types ?? {})).map((a) => data.address_types?.[a] ?? a).join(' / ')} · {g.transit_min_days}–{g.transit_max_days} days · {g.fee_cents ? money(g.fee_cents) : 'Free'}</div>)}</td>
                      <td className="sc-actions"><button type="button" onClick={() => editTemplate(t)}>Edit</button><button type="button" className="danger" onClick={() => removeTemplate(t)}>Delete</button></td>
                    </tr>
                  ))}
                </tbody>
              </table>
            )}
            <p className="sc-muted">Transit times must be accurate — they set the delivery dates customers see. Regions with different transit times may need their own group or template.</p>
          </div>
        </>
      )}

      {addressForm && (
        <div className="ss-overlay" role="presentation" onClick={() => setAddressForm(null)}>
          <form className="ss-modal" onSubmit={saveAddress} onClick={(e) => e.stopPropagation()}>
            <h2 className="sc-h2">{addressForm.id ? 'Edit address' : 'Add a new address'}</h2>
            <label>Address name<input required value={addressForm.name} placeholder="e.g. Main warehouse" onChange={(e) => setAddressForm({ ...addressForm, name: e.target.value })} /></label>
            <label>Country / region<input value={data.country_name ?? data.market} disabled /></label>
            <label>Address line 1<input required value={addressForm.line1} onChange={(e) => setAddressForm({ ...addressForm, line1: e.target.value })} /></label>
            <label>Address line 2 (optional)<input value={addressForm.line2} onChange={(e) => setAddressForm({ ...addressForm, line2: e.target.value })} /></label>
            <div className="ss-row">
              <label>City<input required value={addressForm.city} onChange={(e) => setAddressForm({ ...addressForm, city: e.target.value })} /></label>
              <label>State<select required value={addressForm.state} onChange={(e) => setAddressForm({ ...addressForm, state: e.target.value })}><option value="">Select…</option>{states.map(([code, name]) => <option key={code} value={code}>{name}</option>)}</select></label>
              <label>{data.postal_label ?? 'ZIP code'}<input required value={addressForm.postal_code} onChange={(e) => setAddressForm({ ...addressForm, postal_code: e.target.value })} /></label>
            </div>
            <div className="ss-row">
              <label>Contact person<input required value={addressForm.contact_name} onChange={(e) => setAddressForm({ ...addressForm, contact_name: e.target.value })} /></label>
              <label>Phone number<input required value={addressForm.phone} onChange={(e) => setAddressForm({ ...addressForm, phone: e.target.value })} /></label>
            </div>
            <label className="sc-check"><input type="checkbox" checked={!!addressForm.is_default} onChange={(e) => setAddressForm({ ...addressForm, is_default: e.target.checked })} /> Set as default shipping address</label>
            <div className="ss-actions"><button type="button" className="seller-btn ghost" onClick={() => setAddressForm(null)}>Cancel</button><button type="submit" className="sc-primary">Save</button></div>
          </form>
        </div>
      )}

      {templateForm && (
        <div className="ss-overlay" role="presentation" onClick={() => setTemplateForm(null)}>
          <form className="ss-modal ss-wide" onSubmit={saveTemplate} onClick={(e) => e.stopPropagation()}>
            <h2 className="sc-h2">{templateForm.id ? 'Edit shipping template' : 'Add shipping template'}</h2>
            <div className="ss-row">
              <label>Template name<input required value={templateForm.name} onChange={(e) => setTemplateForm({ ...templateForm, name: e.target.value })} /></label>
              <label>Product type<select value={templateForm.product_type} onChange={(e) => setTemplateForm({ ...templateForm, product_type: e.target.value })}><option value="standard">Standard products</option><option value="oversized">Oversized products</option><option value="fragile">Fragile products</option></select></label>
            </div>
            <div className="ss-row">
              <label>Ships from<select required value={templateForm.shop_address_id} onChange={(e) => setTemplateForm({ ...templateForm, shop_address_id: Number(e.target.value) })}>{data.addresses.map((a) => <option key={a.id} value={a.id}>{a.name} — {a.city}, {a.state}</option>)}</select></label>
              <label>Handling time (working days)<input type="number" min="1" max="10" value={templateForm.handling_days} onChange={(e) => setTemplateForm({ ...templateForm, handling_days: e.target.value })} /></label>
            </div>
            <h3 className="ss-sub">Standard shipping</h3>
            {templateForm.groups.map((g, gi) => {
              const taken = usedElsewhere(gi)
              return (
                <div className="ss-group" key={gi}>
                  <div className="ss-group-head">
                    <strong>Group {gi + 1}</strong>
                    {templateForm.groups.length > 1 && <button type="button" className="sc-link" onClick={() => setTemplateForm((f) => ({ ...f, groups: f.groups.filter((_, i) => i !== gi) }))}>Remove group</button>}
                  </div>
                  <label className="sc-check"><input type="checkbox" disabled={taken.has('ALL')} checked={g.regions.includes('ALL')} onChange={(e) => setGroup(gi, { regions: e.target.checked ? ['ALL'] : [] })} /> All other states</label>
                  {!g.regions.includes('ALL') && (
                    <div className="ss-states">
                      {states.map(([code, name]) => (
                        <label key={code} className={`ss-state${g.regions.includes(code) ? ' on' : ''}${taken.has(code) ? ' taken' : ''}`} title={name}>
                          <input type="checkbox" disabled={taken.has(code)} checked={g.regions.includes(code)} onChange={(e) => setGroup(gi, { regions: e.target.checked ? [...g.regions, code] : g.regions.filter((r) => r !== code) })} />{code}
                        </label>
                      ))}
                    </div>
                  )}
                  <div className="ss-addr-types">
                    <span>Address type</span>
                    {Object.entries(data.address_types ?? {}).map(([type, label]) => (
                      <label key={type} className="sc-check"><input type="checkbox" checked={g.address_types.includes(type)} onChange={(e) => setGroup(gi, { address_types: e.target.checked ? [...g.address_types, type] : g.address_types.filter((a) => a !== type) })} /> {label}</label>
                    ))}
                  </div>
                  <div className="ss-row">
                    <label>Transit time from (days)<input type="number" min="1" max="30" required value={g.transit_min_days} onChange={(e) => setGroup(gi, { transit_min_days: e.target.value })} /></label>
                    <label>to (days)<input type="number" min="1" max="30" required value={g.transit_max_days} onChange={(e) => setGroup(gi, { transit_max_days: e.target.value })} /></label>
                    <label>Shipping fee ({currencySymbol()})<input type="number" min="0" step="0.01" placeholder="0 = free" value={g.fee} onChange={(e) => setGroup(gi, { fee: e.target.value })} /></label>
                  </div>
                </div>
              )
            })}
            <button type="button" className="seller-btn ghost" onClick={() => setTemplateForm((f) => ({ ...f, groups: [...f.groups, { ...EMPTY_GROUP }] }))}>+ Add group</button>
            <p className="sc-muted">The fee is paid by the customer when your shop&rsquo;s part of the order is under {money(data.free_shipping_threshold_cents)}; above that it ships free and you cover it.</p>
            <label className="sc-check"><input type="checkbox" checked={!!templateForm.is_default} onChange={(e) => setTemplateForm({ ...templateForm, is_default: e.target.checked })} /> Set as the default shipping template</label>
            <div className="ss-actions"><button type="button" className="seller-btn ghost" onClick={() => setTemplateForm(null)}>Cancel</button><button type="submit" className="sc-primary">Save</button></div>
          </form>
        </div>
      )}

      {freeRuleOpen && (
        <div className="ss-overlay" role="presentation" onClick={() => setFreeRuleOpen(false)}>
          <div className="ss-modal" onClick={(e) => e.stopPropagation()}>
            <h2 className="sc-h2">Free shipping rule</h2>
            <p>When the retail total of the items from your shop on an order is <b>{money(data.free_shipping_threshold_cents)}</b> or more, the customer gets free shipping and you cover the shipping cost. Below that, the customer pays the fee in your shipping template.</p>
            <label className="sc-check"><input type="checkbox" checked={freeRuleTick} onChange={(e) => setFreeRuleTick(e.target.checked)} /> I have read and agree to the free shipping rule</label>
            <div className="ss-actions">
              <button type="button" className="seller-btn ghost" onClick={() => setFreeRuleOpen(false)}>Cancel</button>
              <button type="button" className="sc-primary" disabled={!freeRuleTick} onClick={async () => { if (await patch({ accept_free_shipping: true })) setFreeRuleOpen(false) }}>Confirm</button>
            </div>
          </div>
        </div>
      )}
    </>
  )
}

// ---------------------------------------------------------------------------
// Ship orders (sellers who ship themselves)
// ---------------------------------------------------------------------------
export function ShipOrders({ headers, mode }) {
  const [rows, setRows] = useState(null)
  const [settings, setSettings] = useState(null)
  const [tab, setTab] = useState('to_ship')
  const [msg, setMsg] = useState('')
  const [shipForm, setShipForm] = useState(null) // { order, items: {id: qty}, address, carrier, tracking, label, warn }
  const [editForm, setEditForm] = useState(null) // { package, carrier, tracking, warn }
  const [selected, setSelected] = useState([])
  const [bulk, setBulk] = useState(null) // { [packageId]: { carrier, tracking } }

  const load = useCallback(() => {
    send(headers, '/seller/fulfillment').then((d) => setRows(d.data)).catch((e) => setMsg(e.message))
    send(headers, '/seller/shipping').then((d) => setSettings(d.data)).catch(() => {})
  }, [headers])
  useEffect(() => { Promise.resolve().then(load) }, [load])

  if (!rows) return <div className="sc-card"><p className="sc-muted">{msg || 'Loading…'}</p></div>

  const packages = rows.flatMap((o) => o.packages.map((p) => ({ ...p, order: o })))
  const toShip = rows.filter((o) => o.to_ship > 0 && o.status !== 'cancelled')
  const shipped = packages.filter((p) => p.status === 'shipped' || p.status === 'in_transit')
  const done = packages.filter((p) => !['shipped', 'in_transit'].includes(p.status))
  const carriers = settings?.carriers ?? []
  const defaultAddress = settings?.addresses?.find((a) => a.is_default)?.id ?? settings?.addresses?.[0]?.id ?? ''
  // Manual labels: the seller requests one, NexTech uploads it to download.
  const manualLabels = settings?.label_mode === 'manual'
  const labelTemplates = settings?.label_templates ?? []
  // Units not yet shipped and not waiting on a requested label.
  const free = (i) => Math.max(0, i.remaining - (i.label_requested ?? 0))

  function openShip(order, useLabel) {
    setMsg('')
    setShipForm({ order, label: useLabel, items: Object.fromEntries(order.items.filter((i) => free(i) > 0).map((i) => [i.id, free(i)])), address: defaultAddress, carrier: carriers[0]?.value ?? 'Other', tracking: '', note: '', template: settings?.label_template_id ?? labelTemplates[0]?.id ?? '', warn: false })
  }

  // Ship a package with the label NexTech uploaded: its items are fixed by the request.
  function openShipWithLabel(order, request) {
    setMsg('')
    const items = Object.fromEntries(request.items.map((i) => [i.order_item_id, i.quantity]))
    setShipForm({ order, label: false, labelRequest: request, items, address: request.ship_from_address_id ?? defaultAddress, carrier: carriers[0]?.value ?? 'Other', tracking: '', note: '', warn: false })
  }

  // Admin-uploaded labels are private files — fetch with the seller's auth.
  async function downloadLabel(p, isRequest = false) {
    setMsg('')
    try {
      const response = await fetch(`${API_URL}/seller/fulfillment/${isRequest ? 'label-requests' : 'packages'}/${p.id}/label`, { headers: headers() })
      if (!response.ok) throw new Error('Could not download the label.')
      const url = URL.createObjectURL(await response.blob())
      const a = document.createElement('a')
      a.href = url
      a.download = `label-order-${p.order_id ?? p.order?.id}-${p.id}`
      a.click()
      setTimeout(() => URL.revokeObjectURL(url), 5000)
    } catch (e) { setMsg(e.message) }
  }

  async function submitShip(event) {
    event.preventDefault()
    const items = Object.entries(shipForm.items).filter(([, q]) => Number(q) > 0).map(([id, q]) => ({ order_item_id: Number(id), quantity: Number(q) }))
    if (!items.length) { setMsg('Select at least one item for the package.'); return }
    if (!shipForm.label && !window.confirm('Once submitted, this package can’t be split or merged with other orders. Submit?')) return
    try {
      if (shipForm.label && manualLabels) {
        await send(headers, `/seller/fulfillment/orders/${shipForm.order.id}/label-request`, 'POST', { items, ship_from_address_id: Number(shipForm.address), note: shipForm.note.trim() || null, label_template_id: shipForm.template ? Number(shipForm.template) : null })
        setMsg(labelTemplates.length ? 'Label ready — download it from the order below.' : 'Label requested — NexTech will upload it here for you to download and print.')
      } else if (shipForm.label) {
        await send(headers, `/seller/fulfillment/orders/${shipForm.order.id}/label`, 'POST', { items, ship_from_address_id: Number(shipForm.address) })
      } else {
        await send(headers, `/seller/fulfillment/orders/${shipForm.order.id}/ship`, 'POST', { items, ship_from_address_id: Number(shipForm.address), carrier: shipForm.carrier, tracking_number: shipForm.tracking, ignore_format_warning: shipForm.warn, label_request_id: shipForm.labelRequest?.id ?? null })
      }
      setShipForm(null)
      load()
    } catch (e) {
      if (e.formatWarning) setShipForm((f) => ({ ...f, warn: true }))
      setMsg(e.message)
    }
  }

  async function submitEdit(event) {
    event.preventDefault()
    try {
      await send(headers, `/seller/fulfillment/packages/${editForm.package.id}`, 'PATCH', { carrier: editForm.carrier, tracking_number: editForm.tracking, ignore_format_warning: editForm.warn })
      setEditForm(null)
      load()
    } catch (e) {
      if (e.formatWarning) setEditForm((f) => ({ ...f, warn: true }))
      setMsg(e.message)
    }
  }

  async function submitBulk(event) {
    event.preventDefault()
    if (!window.confirm('Update tracking for these packages? Each change counts towards the 3-change limit per package.')) return
    try {
      await send(headers, '/seller/fulfillment/packages/bulk', 'POST', { packages: Object.entries(bulk).map(([id, v]) => ({ id: Number(id), carrier: v.carrier, tracking_number: v.tracking })) })
      setBulk(null)
      setSelected([])
      load()
    } catch (e) { setMsg(e.message) }
  }

  async function act(path, body) {
    setMsg('')
    try { await send(headers, path, 'POST', body); load() } catch (e) { setMsg(e.message) }
  }

  const packageRow = (p, withSelect) => (
    <tr key={p.id}>
      {withSelect && <td><input type="checkbox" disabled={!p.can_edit} checked={selected.includes(p.id)} onChange={(e) => setSelected((s) => (e.target.checked ? [...s, p.id] : s.filter((x) => x !== p.id)))} /></td>}
      <td><b>#{p.order.id}</b><small className="sc-muted">{p.order.ship_to.name} · {p.order.ship_to.city}, {p.order.ship_to.state}</small></td>
      <td>{p.carrier}{p.label_source === 'nextech' && <small className="sc-muted">NexTech label · {money(p.label_cost_cents)}</small>}</td>
      <td>{p.tracking_url ? <a href={p.tracking_url} target="_blank" rel="noreferrer">{p.tracking_number}</a> : p.tracking_number}{p.edit_count > 0 && <small className="sc-muted">edited {p.edit_count}/3</small>}</td>
      <td>{shortDate(p.shipped_at)}</td>
      <td><span className={`sc-pill ${p.status === 'delivered' ? 'approved' : ['lost', 'returned'].includes(p.status) ? 'rejected' : 'pending'}`}>{p.status.replace('_', ' ')}</span></td>
      <td className="sc-actions">
        {p.can_edit && <button type="button" onClick={() => setEditForm({ package: p, carrier: p.carrier, tracking: p.tracking_number, warn: false })}>Edit tracking</button>}
        {p.label_url && <a href={p.label_url} target="_blank" rel="noreferrer">Print label</a>}
        {p.has_label_file && <button type="button" onClick={() => downloadLabel(p)}>Download label</button>}
        {p.label_source === 'nextech' && !p.has_label_file && ['shipped', 'in_transit'].includes(p.status) && <button type="button" onClick={() => act(`/seller/fulfillment/packages/${p.id}/sync`)}>Refresh tracking</button>}
        {(p.label_source === 'own' || p.has_label_file) && ['shipped', 'in_transit'].includes(p.status) && <button type="button" onClick={() => { if (window.confirm('Mark this package as delivered?')) act(`/seller/fulfillment/packages/${p.id}/delivered`) }}>Mark delivered</button>}
      </td>
    </tr>
  )

  return (
    <>
      <div className="sc-card">
        <h2 className="sc-h2">Orders you ship</h2>
        <div className="sc-action-grid">
          <button type="button" className={tab === 'to_ship' ? 'sc-action sel' : 'sc-action'} onClick={() => setTab('to_ship')}><span>To ship</span><strong>{toShip.length}</strong></button>
          <button type="button" className={toShip.some((o) => o.overdue) ? 'sc-action hot' : 'sc-action'} onClick={() => setTab('to_ship')}><span>Overdue shipment</span><strong>{toShip.filter((o) => o.overdue).length}</strong></button>
          <button type="button" className={tab === 'shipped' ? 'sc-action sel' : 'sc-action'} onClick={() => setTab('shipped')}><span>Shipped</span><strong>{shipped.length}</strong></button>
          <button type="button" className={tab === 'done' ? 'sc-action sel' : 'sc-action'} onClick={() => setTab('done')}><span>Delivered / closed</span><strong>{done.length}</strong></button>
        </div>
      </div>
      {msg && <div className="sc-alert warn"><span>{msg}</span><button type="button" onClick={() => setMsg('')}>OK</button></div>}
      <div className="sc-tabs">
        {[['to_ship', `To ship ${toShip.length}`], ['shipped', `Shipped ${shipped.length}`], ['done', 'Delivered']].map(([key, label]) => <button key={key} type="button" className={tab === key ? 'active' : ''} onClick={() => setTab(key)}>{label}</button>)}
      </div>
      <div className="sc-card sc-table-card">
        {tab === 'to_ship' ? (
          <div className="sc-table-wrap">
            <table className="sc-table">
              <thead><tr><th>Order</th><th>Ship to</th><th>Items to ship</th><th>Ship by</th><th>Arrives</th><th>Actions</th></tr></thead>
              <tbody>
                {toShip.map((o) => (
                  <tr key={o.id}>
                    <td><b>#{o.id}</b><small className="sc-muted">{shortDate(o.created_at)}</small></td>
                    <td>{o.ship_to.name}<small className="sc-muted">{[o.ship_to.line1, o.ship_to.line2].filter(Boolean).join(', ')}<br />{o.ship_to.city}, {o.ship_to.state} {o.ship_to.postal_code}</small></td>
                    <td>{o.items.filter((i) => i.remaining > 0).map((i) => <div key={i.id}>{i.product_name}{i.variant_label ? ` · ${i.variant_label}` : ''} <span className="sc-muted">× {i.remaining}</span></div>)}</td>
                    <td className={o.overdue ? 'sc-low' : ''}>{shortDate(o.shipping?.ship_by)}{o.overdue && <small>Overdue</small>}</td>
                    <td>{shortDate(o.shipping?.deliver_from)}–{shortDate(o.shipping?.deliver_by)}</td>
                    <td className="sc-actions">
                      {(o.label_requests ?? []).filter((r) => r.status === 'requested').map((r) => (
                        <small key={r.id} className="ss-label-wait">Label requested {shortDate(r.created_at)} — waiting for NexTech <button type="button" onClick={() => { if (window.confirm('Cancel this label request?')) act(`/seller/fulfillment/label-requests/${r.id}/cancel`) }}>Cancel</button></small>
                      ))}
                      {(o.label_requests ?? []).filter((r) => r.status === 'ready' && !r.order_package_id).map((r) => (
                        <div key={r.id} className="ss-label-ready">
                          <small>Label ready</small>
                          <button type="button" className="link" onClick={() => { if (window.confirm('Discard this label? You can create a new one.')) act(`/seller/fulfillment/label-requests/${r.id}/cancel`) }}>Discard</button>
                          <button type="button" onClick={() => downloadLabel(r, true)}>Download label (PDF)</button>
                          {r.label_template_id && labelTemplates.length > 1 && <select value={r.label_template_id} title="Label layout" onChange={(e) => act(`/seller/fulfillment/label-requests/${r.id}/template`, { label_template_id: Number(e.target.value) })}>{labelTemplates.map((t) => <option key={t.id} value={t.id}>{t.name}</option>)}</select>}
                          <button type="button" onClick={() => openShipWithLabel(o, r)}>Shipped — add tracking</button>
                        </div>
                      ))}
                      {(o.label_requests ?? []).filter((r) => r.status === 'cancelled' && r.admin_note && r.admin_note !== 'Cancelled by the seller.').slice(0, 1).map((r) => <small key={r.id} className="sc-low">Label request declined: {r.admin_note}</small>)}
                      {o.items.some((i) => free(i) > 0) && <>
                        {mode !== 'label' && <button type="button" onClick={() => openShip(o, false)}>Confirm shipment</button>}
                        <button type="button" onClick={() => openShip(o, true)}>{manualLabels ? (labelTemplates.length ? 'Get shipping label' : 'Request NexTech label') : 'Buy NexTech label'}</button>
                      </>}
                    </td>
                  </tr>
                ))}
                {toShip.length === 0 && <tr><td colSpan="6" className="sc-empty">Nothing to ship right now.</td></tr>}
              </tbody>
            </table>
          </div>
        ) : (
          <>
            {tab === 'shipped' && (
              <div className="sc-filter">
                <button type="button" disabled={!selected.length} onClick={() => setBulk(Object.fromEntries(selected.map((id) => { const p = packages.find((x) => x.id === id); return [id, { carrier: p.carrier, tracking: p.tracking_number }] })))}>Edit shipping information ({selected.length})</button>
              </div>
            )}
            <div className="sc-table-wrap">
              <table className="sc-table">
                <thead><tr>{tab === 'shipped' && <th></th>}<th>Order</th><th>Carrier</th><th>Tracking</th><th>Shipped</th><th>Status</th><th>Actions</th></tr></thead>
                <tbody>
                  {(tab === 'shipped' ? shipped : done).map((p) => packageRow(p, tab === 'shipped'))}
                  {(tab === 'shipped' ? shipped : done).length === 0 && <tr><td colSpan="7" className="sc-empty">No packages here.</td></tr>}
                </tbody>
              </table>
            </div>
          </>
        )}
        <p className="sc-muted sc-foot">Enter tracking only after the courier has the package. Tracking can be changed up to 3 times per package, until it&rsquo;s delivered. NexTech labels bought automatically update tracking by themselves; uploaded labels are marked delivered by you, the customer or NexTech.</p>
      </div>

      {shipForm && (
        <div className="ss-overlay" role="presentation" onClick={() => setShipForm(null)}>
          <form className="ss-modal" onSubmit={submitShip} onClick={(e) => e.stopPropagation()}>
            <h2 className="sc-h2">{shipForm.label ? (manualLabels ? 'Request NexTech shipping label' : 'Buy NexTech shipping label') : 'Confirm shipment'} · Order #{shipForm.order.id}</h2>
            <p className="sc-muted">Ship to: {shipForm.order.ship_to.name}, {shipForm.order.ship_to.line1}, {shipForm.order.ship_to.city}, {shipForm.order.ship_to.state} {shipForm.order.ship_to.postal_code}</p>
            <h3 className="ss-sub">Items in this package</h3>
            {shipForm.labelRequest ? shipForm.order.items.filter((i) => shipForm.items[i.id]).map((i) => <div className="ss-item" key={i.id}><span>{i.product_name}{i.variant_label ? ` · ${i.variant_label}` : ''}</span><span>× {shipForm.items[i.id]}</span><span className="sc-muted">on NexTech label</span></div>) : shipForm.order.items.filter((i) => free(i) > 0).map((i) => (
              <div className="ss-item" key={i.id}>
                <span>{i.product_name}{i.variant_label ? ` · ${i.variant_label}` : ''}</span>
                <input type="number" min="0" max={free(i)} value={shipForm.items[i.id] ?? 0} onChange={(e) => setShipForm((f) => ({ ...f, items: { ...f.items, [i.id]: Math.min(free(i), Math.max(0, Number(e.target.value))) } }))} />
                <span className="sc-muted">of {free(i)}</span>
              </div>
            ))}
            <label>Ship from<select required value={shipForm.address} onChange={(e) => setShipForm({ ...shipForm, address: e.target.value })}>{(settings?.addresses ?? []).map((a) => <option key={a.id} value={a.id}>{a.name} — {a.city}, {a.state}</option>)}</select></label>
            {shipForm.label && manualLabels ? (
              <>
                {labelTemplates.length > 0 ? (
                  <label>Label layout<select value={shipForm.template} onChange={(e) => setShipForm({ ...shipForm, template: e.target.value })}>{labelTemplates.map((t) => <option key={t.id} value={t.id}>{t.name}</option>)}</select></label>
                ) : (
                  <label>Package details for NexTech (optional)<input maxLength="500" placeholder="e.g. 1.2 kg, box 30×20×10 cm" value={shipForm.note} onChange={(e) => setShipForm({ ...shipForm, note: e.target.value })} /></label>
                )}
                <p className="sc-muted">{labelTemplates.length > 0 ? 'Your label PDF is made instantly with the ship-from and customer addresses. Download and print it, stick it on the package, hand it to the courier, then add the tracking number. You can switch layout any time.' : 'NexTech uploads the label here as a PDF — usually within one working day. Download and print it, stick it on the package, hand it to the courier, then add the tracking number.'}</p>
              </>
            ) : shipForm.label ? (
              <p className="sc-muted">NexTech books the label on its courier account; postage is deducted from your earnings on this order and tracking updates automatically. Print the label and attach it to the package.</p>
            ) : (
              <>
                <div className="ss-row">
                  <label>Carrier<select value={shipForm.carrier} onChange={(e) => setShipForm({ ...shipForm, carrier: e.target.value, warn: false })}>{carriers.map((c) => <option key={c.value} value={c.value}>{c.label}</option>)}</select></label>
                  <label>Tracking number<input required value={shipForm.tracking} onChange={(e) => setShipForm({ ...shipForm, tracking: e.target.value, warn: false })} /></label>
                </div>
                {shipForm.warn && <p className="sc-alert warn">The tracking number doesn&rsquo;t match {shipForm.carrier}&rsquo;s usual format. Double-check it — submit again to confirm it&rsquo;s correct.</p>}
                <p className="sc-muted">Enter the tracking number after handing the package to the courier. Wrong details stop you and the customer seeing tracking updates.</p>
              </>
            )}
            <div className="ss-actions"><button type="button" className="seller-btn ghost" onClick={() => setShipForm(null)}>Cancel</button><button type="submit" className="sc-primary">{shipForm.label ? (manualLabels ? (labelTemplates.length ? 'Create label' : 'Request label') : 'Buy label') : shipForm.warn ? 'Submit anyway' : 'Submit'}</button></div>
          </form>
        </div>
      )}

      {editForm && (
        <div className="ss-overlay" role="presentation" onClick={() => setEditForm(null)}>
          <form className="ss-modal" onSubmit={submitEdit} onClick={(e) => e.stopPropagation()}>
            <h2 className="sc-h2">Edit package · Order #{editForm.package.order.id}</h2>
            <p className="sc-muted">Changes used: {editForm.package.edit_count}/3. Not possible once delivered, returned or lost.</p>
            <div className="ss-row">
              <label>Courier<select value={editForm.carrier} onChange={(e) => setEditForm({ ...editForm, carrier: e.target.value, warn: false })}>{carriers.map((c) => <option key={c.value} value={c.value}>{c.label}</option>)}</select></label>
              <label>Tracking ID<input required value={editForm.tracking} onChange={(e) => setEditForm({ ...editForm, tracking: e.target.value, warn: false })} /></label>
            </div>
            {editForm.warn && <p className="sc-alert warn">That doesn&rsquo;t look like a {editForm.carrier} tracking number — save again to confirm.</p>}
            <div className="ss-actions"><button type="button" className="seller-btn ghost" onClick={() => setEditForm(null)}>Cancel</button><button type="submit" className="sc-primary">{editForm.warn ? 'Save anyway' : 'Save'}</button></div>
          </form>
        </div>
      )}

      {bulk && (
        <div className="ss-overlay" role="presentation" onClick={() => setBulk(null)}>
          <form className="ss-modal ss-wide" onSubmit={submitBulk} onClick={(e) => e.stopPropagation()}>
            <h2 className="sc-h2">Edit shipping information</h2>
            <table className="sc-table">
              <thead><tr><th>Order</th><th>Courier</th><th>Tracking ID</th></tr></thead>
              <tbody>
                {Object.entries(bulk).map(([id, v]) => {
                  const p = packages.find((x) => x.id === Number(id))
                  return (
                    <tr key={id}>
                      <td>#{p?.order.id}</td>
                      <td><select value={v.carrier} onChange={(e) => setBulk((b) => ({ ...b, [id]: { ...b[id], carrier: e.target.value } }))}>{carriers.map((c) => <option key={c.value} value={c.value}>{c.label}</option>)}</select></td>
                      <td><input required value={v.tracking} onChange={(e) => setBulk((b) => ({ ...b, [id]: { ...b[id], tracking: e.target.value } }))} /></td>
                    </tr>
                  )
                })}
              </tbody>
            </table>
            <p className="sc-muted">Double-check every row — each change uses one of the package&rsquo;s 3 allowed changes.</p>
            <div className="ss-actions"><button type="button" className="seller-btn ghost" onClick={() => setBulk(null)}>Cancel</button><button type="submit" className="sc-primary">Update</button></div>
          </form>
        </div>
      )}
    </>
  )
}
