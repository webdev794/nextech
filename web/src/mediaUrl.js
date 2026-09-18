// Resolve an admin-/seeder-provided image path against the app's base path.
// Root-relative paths like "/img/pages/x.jpg" otherwise 404 when the app is
// served from a sub-folder (e.g. https://host/gdp/). Absolute URLs and data:
// URIs pass through unchanged.
const BASE = (import.meta.env.BASE_URL || '/').replace(/\/$/, '')

// Files under /storage/... or /api/media/file/... are served by the Laravel
// backend, not the web app's own public folder — in dev those are two
// different origins (Vite on :5173, API on :8000), so they need the API's
// origin, not BASE. In production the backend serves the built SPA too, so
// this collapses to the same value as BASE and the prefix is a no-op.
const API_URL = import.meta.env.VITE_API_URL ?? 'http://127.0.0.1:8000/api'
const API_ORIGIN = API_URL.replace(/\/api\/?$/, '')

export function mediaUrl(url) {
  if (!url || typeof url !== 'string') return url
  if (/^(https?:)?\/\//i.test(url) || url.startsWith('data:') || url.startsWith('blob:')) return url
  if (url.startsWith('/storage/') || url.startsWith('/api/media/file/')) return API_ORIGIN + url
  return url.startsWith('/') ? BASE + url : `${BASE}/${url}`
}
