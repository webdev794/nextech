import { useState } from 'react'
import { currencySymbol } from './money'

// Settings → Countries: each non-home market's own checkout fees and seller
// payout limits (in that market's currency), plus India's grievance officer.
// Home-market (US) fees stay in the forms below this panel.

const FEE_FIELDS = [
  ['delivery_fee_cents', 'Delivery fee'],
  ['free_delivery_threshold_cents', 'Free delivery above'],
  ['handling_fee_cents', 'Handling fee'],
  ['small_cart_fee_cents', 'Small-cart fee'],
  ['small_cart_min_cents', '…applied below'],
]
const PCT_FIELDS = [['commission_rate_bps', 'Commission rate (%)']]
const PAYOUT_FIELDS = [
  ['min_payout_cents', 'Minimum seller payout'],
  ['max_payout_cents', 'Maximum per payout'],
  ['daily_payout_cap_cents', 'Daily payout cap (0 = none)'],
  ['return_pickup_fee_cents', 'Return pickup fee charged to seller'],
]
const RIDER_FIELDS = [
  ['base_cents', 'Rider base pay per delivery'],
  ['per_mile_cents', 'Rider pay per mile'],
  ['min_payout_cents', 'Minimum rider payout'],
  ['max_payout_cents', 'Maximum per rider payout'],
]
const OFFICER_FIELDS = [['name', 'Name'], ['designation', 'Designation'], ['email', 'Email'], ['phone', 'Phone'], ['address', 'Address']]

const toUnits = (cents) => ((cents ?? 0) / 100).toFixed(2)
const toCents = (value) => Math.max(0, Math.round(Number(value || 0) * 100))

function formFrom(market) {
  const form = {}
  for (const [key] of FEE_FIELDS) form[`fees.${key}`] = toUnits(market.fees?.[key])
  for (const [key] of PAYOUT_FIELDS) form[`payouts.${key}`] = toUnits(market.payouts?.[key])
  for (const [key] of PCT_FIELDS) form[`payouts.${key}`] = toUnits(market.payouts?.[key])
  for (const [key] of RIDER_FIELDS) form[`rider.${key}`] = toUnits(market.rider_pay?.[key])
  return form
}

export function MarketSettings({ settings, save, onSaved, only }) {
  const markets = (settings?.markets ?? []).filter((m) => !only || m.code === only)
  const [forms, setForms] = useState(() => Object.fromEntries(markets.map((m) => [m.code, formFrom(m)])))
  const [officer, setOfficer] = useState(() => ({ name: '', designation: '', email: '', phone: '', address: '', ...(settings?.grievance_officer ?? {}) }))

  if (markets.length === 0) return null

  async function saveMarket(event, market) {
    event.preventDefault()
    const form = forms[market.code] ?? {}
    const market_fees = Object.fromEntries(FEE_FIELDS.map(([key]) => [key, toCents(form[`fees.${key}`])]))
    const market_payouts = Object.fromEntries([...PAYOUT_FIELDS, ...PCT_FIELDS].map(([key]) => [key, toCents(form[`payouts.${key}`])]))
    const market_rider_pay = Object.fromEntries(RIDER_FIELDS.map(([key]) => [key, toCents(form[`rider.${key}`])]))
    const patch = { market: market.code, market_fees: { ...market_fees, delivery_near_fee_cents: market_fees.delivery_fee_cents, delivery_far_fee_cents: market_fees.delivery_fee_cents }, market_payouts, market_rider_pay }
    if (market.code === 'IN') patch.grievance_officer = officer
    const saved = await save(patch)
    if (saved) {
      const updated = (saved.markets ?? []).find((m) => m.code === market.code)
      if (updated) setForms((current) => ({ ...current, [market.code]: formFrom(updated) }))
      onSaved?.(`${market.name} settings saved.`)
    }
  }

  return markets.map((market) => {
    const form = forms[market.code] ?? formFrom(market)
    const sym = currencySymbol(market.currency)
    const set = (key, value) => setForms((current) => ({ ...current, [market.code]: { ...form, [key]: value } }))
    return (
      <form key={market.code} className="admin-form" onSubmit={(event) => saveMarket(event, market)}>
        <h3>{market.name} — charges &amp; payouts ({market.currency.toUpperCase()} {sym})</h3>
        <p className="muted">
          Shoppers in {market.name} see only {market.name} products, priced in {sym}. Orders are delivered by NexTech riders from {market.name} stores within their radius, otherwise by courier or the seller&rsquo;s own shipping.
          {market.code === 'IN' && ' Prices include GST (sellers set HSN code and GST rate per product). TCS 0.5% (GST sec. 52) and TDS 0.1% (sec. 194-O) are withheld from sellers’ sales automatically.'}
        </p>
        <h4>Delivery &amp; checkout charges</h4>
        <div className="admin-form-grid">
          {FEE_FIELDS.map(([key, label]) => <label key={key}>{label} ({sym})<input type="number" min="0" step="0.01" value={form[`fees.${key}`]} onChange={(event) => set(`fees.${key}`, event.target.value)} /></label>)}
        </div>
        <h4>Seller commission &amp; payouts</h4>
        <div className="admin-form-grid">
          {PCT_FIELDS.map(([key, label]) => <label key={key}>{label}<input type="number" min="0" max="100" step="0.01" value={form[`payouts.${key}`]} onChange={(event) => set(`payouts.${key}`, event.target.value)} /></label>)}
          {PAYOUT_FIELDS.map(([key, label]) => <label key={key}>{label} ({sym})<input type="number" min="0" step="0.01" value={form[`payouts.${key}`]} onChange={(event) => set(`payouts.${key}`, event.target.value)} /></label>)}
        </div>
        <h4>Rider pay</h4>
        <div className="admin-form-grid">
          {RIDER_FIELDS.map(([key, label]) => <label key={key}>{label} ({sym})<input type="number" min="0" step="0.01" value={form[`rider.${key}`]} onChange={(event) => set(`rider.${key}`, event.target.value)} /></label>)}
        </div>
        {market.code === 'IN' && <>
          <h4>Grievance officer</h4>
          <p className="muted">Shown in the India storefront footer — required by the Consumer Protection (E-Commerce) Rules, 2020. Complaints must be acknowledged within 48 hours and resolved within one month.</p>
          <div className="admin-form-grid">
            {OFFICER_FIELDS.map(([key, label]) => <label key={key}>{label}<input value={officer[key] ?? ''} onChange={(event) => setOfficer({ ...officer, [key]: event.target.value })} /></label>)}
          </div>
        </>}
        <button className="act" type="submit">Save {market.name} settings</button>
      </form>
    )
  })
}
