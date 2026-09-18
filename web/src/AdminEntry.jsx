import { lazy, Suspense, useCallback, useEffect, useState } from 'react'
import { mediaUrl } from './mediaUrl'
import './Admin.css'

// The console itself is a big module — load it only once an admin is signed in,
// so the sign-in gate paints immediately on a cold refresh.
const Admin = lazy(() => import('./Admin'))

const API_URL = import.meta.env.VITE_API_URL ?? 'http://127.0.0.1:8000/api'
const STORE_URL = import.meta.env.BASE_URL || '/'

async function readJson(response) {
  const text = await response.text()
  try { return text ? JSON.parse(text) : {} } catch { return {} }
}

/**
 * Dedicated /admin entry point. Kept separate from the storefront sign-in so a
 * customer never types staff credentials into the shopper login (and vice
 * versa). An existing browser session is reused only when it belongs to an
 * administrator; anything else has to sign in here with email + password.
 */
export default function AdminEntry() {
  const [token, setToken] = useState(() => localStorage.getItem('gdp_token') || '')
  const [authed, setAuthed] = useState(false)
  const [checking, setChecking] = useState(() => !!localStorage.getItem('gdp_token'))
  const [form, setForm] = useState({ email: '', password: '' })
  const [otp, setOtp] = useState(null)
  const [code, setCode] = useState('')
  const [message, setMessage] = useState('')

  const acceptAdmin = useCallback((data) => {
    if (!data?.user?.is_admin) {
      setMessage('This account is not an administrator.')
      return false
    }
    localStorage.setItem('gdp_token', data.token)
    localStorage.setItem('gdp_user', JSON.stringify(data.user))
    setToken(data.token)
    setAuthed(true)
    setMessage('')
    return true
  }, [])

  useEffect(() => {
    const existing = localStorage.getItem('gdp_token')
    if (!existing) return
    let cancelled = false
    fetch(`${API_URL}/user`, { headers: { Accept: 'application/json', Authorization: `Bearer ${existing}` } })
      .then(readJson)
      .then((user) => { if (!cancelled && user?.is_admin) { setToken(existing); setAuthed(true) } })
      .catch(() => {})
      .finally(() => { if (!cancelled) setChecking(false) })
    return () => { cancelled = true }
  }, [])

  // Tab title + favicon for the admin pages (the storefront does the same from
  // its own copy of /api/config). index.html carries the default favicon.
  useEffect(() => {
    let cancelled = false
    fetch(`${API_URL}/config`, { headers: { Accept: 'application/json' } })
      .then(readJson)
      .then(({ data }) => {
        if (cancelled || !data?.branding) return
        const name = data.branding.store_name || 'NexTech'
        document.title = `${name} · Admin`
        if (data.branding.favicon_url) {
          let link = document.querySelector("link[rel='icon']")
          if (!link) { link = document.createElement('link'); link.rel = 'icon'; document.head.appendChild(link) }
          link.href = mediaUrl(data.branding.favicon_url)
        }
      })
      .catch(() => {})
    return () => { cancelled = true }
  }, [])

  async function submitPassword(event) {
    event.preventDefault()
    setMessage('')
    try {
      const response = await fetch(`${API_URL}/auth/login`, { method: 'POST', headers: { 'Content-Type': 'application/json', Accept: 'application/json' }, body: JSON.stringify({ email: form.email.trim(), password: form.password }) })
      const data = await readJson(response)
      if (!response.ok) throw new Error(data.message ?? Object.values(data.errors ?? {})[0]?.[0] ?? 'The email or password is incorrect.')
      if (data.token) { acceptAdmin(data); return }
      setOtp({ email: data.email, purpose: data.purpose ?? 'login' })
      setCode('')
      setMessage(data.message ?? 'Enter the code we emailed you to finish signing in.')
    } catch (error) {
      setMessage(error instanceof TypeError ? 'The API is offline. Start Laravel on port 8000 and try again.' : error.message)
    }
  }

  async function submitCode(event) {
    event.preventDefault()
    setMessage('')
    try {
      const response = await fetch(`${API_URL}/auth/verify-otp`, { method: 'POST', headers: { 'Content-Type': 'application/json', Accept: 'application/json' }, body: JSON.stringify({ ...otp, code: code.trim() }) })
      const data = await readJson(response)
      if (!response.ok) throw new Error(data.message ?? 'That code is invalid or has expired.')
      if (acceptAdmin(data)) setOtp(null)
    } catch (error) {
      setMessage(error instanceof TypeError ? 'The API is offline. Start Laravel on port 8000 and try again.' : error.message)
    }
  }

  if (checking) return <div className="admin-gate"><p>Loading&hellip;</p></div>
  if (authed) return (
    <Suspense fallback={<div className="admin-gate"><p>Loading console&hellip;</p></div>}>
      <Admin token={token} onClose={() => { window.location.href = STORE_URL }} />
    </Suspense>
  )

  return (
    <div className="admin-gate">
      <form className="admin-gate-card" onSubmit={otp ? submitCode : submitPassword}>
        <h1>Administrator sign-in</h1>
        <p className="admin-gate-sub">{otp
          ? `Enter the 6-digit code sent to ${otp.email}.`
          : 'This area is for store staff. Customers should sign in on the storefront.'}</p>
        {otp
          ? <input required inputMode="numeric" autoComplete="one-time-code" pattern="[0-9]*" maxLength="8" placeholder="6-digit code" value={code} onChange={(event) => setCode(event.target.value.replace(/[^0-9]/g, ''))} />
          : <>
              <input required type="email" autoComplete="username" placeholder="Admin email" value={form.email} onChange={(event) => setForm({ ...form, email: event.target.value })} />
              <input required type="password" autoComplete="current-password" placeholder="Password" value={form.password} onChange={(event) => setForm({ ...form, password: event.target.value })} />
            </>}
        <button type="submit">{otp ? 'Verify' : 'Sign in'} &rarr;</button>
        {message && <p className="admin-gate-msg">{message}</p>}
        {otp && <button type="button" className="admin-gate-link" onClick={() => { setOtp(null); setMessage('') }}>Use a different account</button>}
        <a className="admin-gate-back" href={STORE_URL}>&larr; Back to store</a>
      </form>
    </div>
  )
}
