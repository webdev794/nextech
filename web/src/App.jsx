import { lazy, Suspense } from 'react'
import Storefront from './Storefront'

// The admin console and the rider console each live at their own URL
// (BASE + "admin" / BASE + "rider") so staff credentials are never entered on
// the shopper sign-in. A hash (#/admin, #/rider) works too where path rewrites
// aren't available.
const base = import.meta.env.BASE_URL || '/'
const path = window.location.pathname.replace(/\/$/, '')
const routeIs = (name) => path === `${base}${name}`.replace(/\/$/, '') || window.location.hash === `#/${name}`
const isAdminRoute = routeIs('admin')
const isRiderRoute = routeIs('rider')
const isSellerRoute = routeIs('seller')

// Every staff/partner console is a separate chunk — shoppers never download them.
const AdminEntry = lazy(() => import('./AdminEntry'))
const RiderEntry = lazy(() => import('./RiderEntry'))
const SellerEntry = lazy(() => import('./SellerEntry'))

const fallback = (label) => <div style={{ padding: 40, font: '14px system-ui, sans-serif', color: '#555' }}>Loading {label}…</div>

function App() {
  if (isAdminRoute) return <Suspense fallback={fallback('admin')}><AdminEntry /></Suspense>
  if (isRiderRoute) return <Suspense fallback={fallback('rider app')}><RiderEntry /></Suspense>
  if (isSellerRoute) return <Suspense fallback={fallback('seller center')}><SellerEntry /></Suspense>
  return <Storefront />
}

export default App
