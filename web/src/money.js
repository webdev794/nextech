// Money formatting shared by every surface. Amounts are always minor units
// (cents / paise) in the currency of the market they belong to — a US order
// is in USD, an Indian seller's ledger in INR.

const LOCALES = { usd: 'en-US', inr: 'en-IN' }
const formatters = {}

export function formatMoney(cents, currency = 'usd') {
  const code = String(currency || 'usd').toLowerCase()
  formatters[code] ??= new Intl.NumberFormat(LOCALES[code] ?? 'en-US', { style: 'currency', currency: code.toUpperCase(), minimumFractionDigits: 2, maximumFractionDigits: 2 })
  return formatters[code].format((Number(cents) || 0) / 100)
}


// Flag emoji for an ISO country code ("IN" -> 🇮🇳).
export function flag(code = '') {
  return String(code).toUpperCase().replace(/[A-Z]/g, (c) => String.fromCodePoint(127397 + c.charCodeAt(0)))
}

// The currency the storefront is showing right now (its selected market).
let storeCurrency = 'usd'
export function setStoreCurrency(currency) { storeCurrency = currency || 'usd' }
export function storeMoney(cents, currency) { return formatMoney(cents, currency || storeCurrency) }
// No currency given = the one the current surface is showing.
export function currencySymbol(currency) {
  return formatMoney(0, currency || storeCurrency).replace(/[\d.,\s]/g, '')
}
