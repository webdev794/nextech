import { useState } from 'react'

// Admin → Settings → Charges: US sales tax by state. Each state's rate comes
// from the built-in table; edit any, or leave one blank to use the default
// rate. "Fetch automatically" fills the table from the sales-tax lookup
// (API key in Secure access), which also gives exact ZIP-code rates at checkout.

const API_URL = import.meta.env.VITE_API_URL ?? 'http://127.0.0.1:8000/api'
const toPct = (bps) => (bps == null ? '' : (bps / 100).toFixed(bps % 10 ? 3 : 2))
const toBps = (pct) => (String(pct).trim() === '' ? null : Math.max(0, Math.min(10000, Math.round(Number(pct) * 100))))
const formFrom = (tax) => ({ mode: tax?.mode ?? 'state', rates: Object.fromEntries((tax?.states ?? []).map((s) => [s.code, toPct(s.rate_bps)])) })

export function SalesTaxSettings({ settings, save, headers, onSettings, onMessage, onError }) {
  const tax = settings?.sales_tax
  const [form, setForm] = useState(() => formFrom(tax))
  const [query, setQuery] = useState('')
  const [busy, setBusy] = useState(false)
  if (!tax) return null

  const apply = (next) => setForm(formFrom(next.sales_tax))
  async function submit(event) {
    event.preventDefault()
    const saved = await save({ sales_tax_mode: form.mode, sales_tax_states: Object.fromEntries(Object.entries(form.rates).map(([code, pct]) => [code, toBps(pct)])) })
    if (saved) { apply(saved); onMessage('Sales tax saved.') }
  }
  async function reset() {
    if (!window.confirm('Reset every state to the built-in rate?')) return
    const saved = await save({ sales_tax_reset_states: true })
    if (saved) { apply(saved); onMessage('State rates reset to the built-in table.') }
  }
  async function fetchRates() {
    setBusy(true)
    try {
      const response = await fetch(`${API_URL}/admin/settings/sales-tax/fetch`, { method: 'POST', headers: headers() })
      const data = await response.json().catch(() => ({}))
      if (!response.ok) throw new Error(data.message ?? 'Could not fetch rates.')
      onSettings(data.data)
      apply(data.data)
      const { updated = [], failed = [] } = data.result ?? {}
      onMessage(`Fetched ${updated.length} state rate${updated.length === 1 ? '' : 's'}${failed.length ? ` — couldn't get ${failed.join(', ')} (kept as before)` : ''}. Review and they're already saved.`)
    } catch (error) { onError(error) } finally { setBusy(false) }
  }

  const q = query.trim().toLowerCase()
  const rows = tax.states.filter((s) => !q || s.name.toLowerCase().includes(q) || s.code.toLowerCase() === q)
  const defaultPct = toPct(settings.tax_rate_bps)
  return (
    <form className="admin-form" onSubmit={submit}>
      <h3>US sales tax</h3>
      <p className="muted">Sales tax is charged by where the order goes. With a lookup API key (Secure access), checkout uses the exact rate for the buyer&rsquo;s ZIP code (city and county tax included), cached for 30 days; otherwise the state&rsquo;s rate below. A blank state, or an unknown address, uses the default tax rate ({defaultPct}%) under Other charges.</p>
      <label>How to charge
        <select value={form.mode} onChange={(event) => setForm({ ...form, mode: event.target.value })}>
          <option value="state">By state / ZIP code (recommended)</option>
          <option value="flat">One rate everywhere (the default tax rate)</option>
        </select>
      </label>
      {form.mode === 'state' && <>
        <div className="admin-form-actions">
          <input type="search" placeholder="Search state" value={query} onChange={(event) => setQuery(event.target.value)} />
          <button className="act ghost" type="button" disabled={busy} onClick={fetchRates} title={tax.api_key_set ? 'Fill state rates from the lookup' : 'Add a lookup API key in Secure access first'}>{busy ? 'Fetching…' : 'Fetch automatically'}</button>
          <button className="act ghost" type="button" onClick={reset}>Reset to built-in rates</button>
        </div>
        {!tax.api_key_set && <p className="muted">No lookup key yet — using the state table. Add a free API Ninjas key in Secure access to fetch rates and use ZIP-code rates.</p>}
        <div className="admin-form-grid">
          {rows.map((s) => (
            <label key={s.code}>{s.name} ({s.code}) %
              <input type="number" min="0" max="25" step="0.001" value={form.rates[s.code] ?? ''} placeholder={`default ${defaultPct}`} title={`Built-in: ${toPct(s.builtin_bps)}%`} onChange={(event) => setForm({ ...form, rates: { ...form.rates, [s.code]: event.target.value } })} />
            </label>
          ))}
        </div>
      </>}
      <div className="admin-form-actions"><button className="act" type="submit">Save sales tax</button></div>
    </form>
  )
}

/** Secure access: the sales-tax lookup API key (API Ninjas). */
export function SalesTaxKey({ settings, save }) {
  const tax = settings?.sales_tax
  const [key, setKey] = useState('')
  if (!tax) return null
  return (
    <form className="admin-form" onSubmit={async (event) => { event.preventDefault(); if (await save({ sales_tax_api_key: key.trim() })) setKey('') }}>
      <h3>Sales tax lookup — API Ninjas</h3>
      <p className="muted">Free key from api-ninjas.com (free tier has a monthly request limit). Used for exact US ZIP-code rates at checkout and for &ldquo;Fetch automatically&rdquo; under Charges. Each ZIP is fetched once and cached for 30 days ({tax.cached_zips} cached); if the lookup fails, checkout uses the state table.</p>
      <label>API key
        <input type="password" autoComplete="off" value={key} placeholder={tax.api_key_set ? `current: ${tax.api_key_hint} — leave blank to keep` : 'API key'} onChange={(event) => setKey(event.target.value)} />
      </label>
      <div className="admin-form-actions">
        <button className="act" type="submit" disabled={!key.trim()}>Save key</button>
        {tax.cached_zips > 0 && <button className="act ghost" type="button" onClick={() => save({ sales_tax_clear_cache: true })}>Clear cached ZIP rates</button>}
      </div>
    </form>
  )
}
