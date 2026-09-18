import { API_URL } from './config';

// Images come back as paths relative to the API's origin (e.g.
// "/img/products/1.webp", "/storage/products/x.png", "/api/media/file/...").
// Absolute URLs pass through unchanged.
const API_ORIGIN = API_URL.replace(/\/api\/?$/, '');

export function mediaUrl(url) {
  if (!url || typeof url !== 'string') return null;
  if (/^(https?:)?\/\//i.test(url) || url.startsWith('data:')) return url;
  return url.startsWith('/') ? `${API_ORIGIN}${url}` : `${API_ORIGIN}/${url}`;
}
