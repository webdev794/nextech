import { mediaUrl } from './mediaUrl'
import { useBranding } from './useBranding'
import './BrandLogo.css'

// The store logo for the admin, Seller Center and rider headers: the logo
// image admin set in Store settings, or the store's first letter until there
// is one. `onDark` puts the image on a light pill so dark logos stay visible.
export function BrandLogo({ fallback, onDark = false, className = '' }) {
  const branding = useBranding()
  const name = branding?.store_name || 'NexTech'
  if (branding?.logo_url) {
    return <span className={`brand-logo-wrap${onDark ? ' on-dark' : ''} ${className}`}><img src={mediaUrl(branding.logo_url)} alt={name} /></span>
  }
  return <span className={`brand-logo-letter ${className}`} aria-hidden>{fallback ?? name.trim().charAt(0).toUpperCase()}</span>
}
