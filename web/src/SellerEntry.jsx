import { lazy, Suspense, useCallback, useEffect, useState } from 'react'
import './Seller.css'

// The Seller Center itself is a big module — load it only once someone is
// signed in, so the sign-in gate paints immediately on a cold refresh.
const Seller = lazy(() => import('./Seller'))

const API_URL = import.meta.env.VITE_API_URL ?? 'http://127.0.0.1:8000/api'
const STORE_URL = import.meta.env.BASE_URL || '/'

async function readJson(response) {
  const text = await response.text()
  try { return text ? JSON.parse(text) : {} } catch { return {} }
}

/**
 * Dedicated /seller entry point. A seller is just a customer account with a
 * Seller record attached — one login, not a separate credential space — so
 * this reuses the exact same gdp_token/gdp_user auth and /api/auth/* endpoints
 * as the storefront, unlike /admin and /rider which gate on a role flag.
 */
export default function SellerEntry() {
  const [token, setToken] = useState(() => localStorage.getItem('gdp_token') || '')
  const [authed, setAuthed] = useState(false)
  const [checking, setChecking] = useState(() => !!localStorage.getItem('gdp_token'))
  const [signup, setSignup] = useState(false)
  const [form, setForm] = useState({ name: '', email: '', password: '', password_confirmation: '' })
  const [otp, setOtp] = useState(null)
  const [code, setCode] = useState('')
  const [message, setMessage] = useState('')

  const accept = useCallback((data) => {
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
      .then((user) => { if (!cancelled && user?.id) { setToken(existing); setAuthed(true) } })
      .catch(() => {})
      .finally(() => { if (!cancelled) setChecking(false) })
    return () => { cancelled = true }
  }, [])

  useEffect(() => {
    let cancelled = false
    fetch(`${API_URL}/config`, { headers: { Accept: 'application/json' } })
      .then(readJson)
      .then(({ data }) => {
        if (cancelled || !data?.branding) return
        const name = data.branding.store_name || 'NexTech'
        document.title = `${name} · Seller Center`
      })
      .catch(() => {})
    return () => { cancelled = true }
  }, [])

  async function submitPassword(event) {
    event.preventDefault()
    setMessage('')
    if (signup && form.password !== form.password_confirmation) { setMessage('The two passwords don’t match.'); return }
    try {
      const url = signup ? `${API_URL}/auth/register` : `${API_URL}/auth/login`
      const body = signup
        ? { name: form.name.trim(), email: form.email.trim(), password: form.password, password_confirmation: form.password_confirmation }
        : { email: form.email.trim(), password: form.password }
      const response = await fetch(url, { method: 'POST', headers: { 'Content-Type': 'application/json', Accept: 'application/json' }, body: JSON.stringify(body) })
      const data = await readJson(response)
      if (!response.ok) throw new Error(data.message ?? Object.values(data.errors ?? {})[0]?.[0] ?? (signup ? 'Could not create the account.' : 'The email or password is incorrect.'))
      if (data.token) { accept(data); return }
      setOtp({ email: data.email, purpose: data.purpose ?? (signup ? 'register' : 'login') })
      setCode('')
      setMessage(data.message ?? 'Enter the code we emailed you to finish.')
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
      if (accept(data)) setOtp(null)
    } catch (error) {
      setMessage(error instanceof TypeError ? 'The API is offline. Start Laravel on port 8000 and try again.' : error.message)
    }
  }

  if (checking) return <div className="seller-gate"><p>Loading&hellip;</p></div>
  if (authed) return (
    <Suspense fallback={<div className="seller-gate"><p>Loading Seller Center&hellip;</p></div>}>
      <Seller token={token} onSignOut={() => { localStorage.removeItem('gdp_token'); localStorage.removeItem('gdp_user'); setAuthed(false); setToken('') }} />
    </Suspense>
  )

  return (
    <div className="seller-gate">
      <form className="seller-gate-card" onSubmit={otp ? submitCode : submitPassword}>
        <h1>NexTech Seller Center</h1>
        <p className="seller-gate-sub">{otp
          ? `Enter the 6-digit code sent to ${otp.email}.`
          : 'Sign in with your NexTech account to start or manage your seller application. New here? Create an account below.'}</p>
        {otp
          ? <input required inputMode="numeric" autoComplete="one-time-code" pattern="[0-9]*" maxLength="8" placeholder="6-digit code" value={code} onChange={(event) => setCode(event.target.value.replace(/[^0-9]/g, ''))} />
          : <>
              {signup && <input required autoComplete="name" placeholder="Full name" value={form.name} onChange={(event) => setForm({ ...form, name: event.target.value })} />}
              <input required type="email" autoComplete="username" placeholder="Email" value={form.email} onChange={(event) => setForm({ ...form, email: event.target.value })} />
              <input required type="password" autoComplete={signup ? 'new-password' : 'current-password'} placeholder="Password" value={form.password} onChange={(event) => setForm({ ...form, password: event.target.value })} />
              {signup && <input required type="password" autoComplete="new-password" placeholder="Confirm password" value={form.password_confirmation} onChange={(event) => setForm({ ...form, password_confirmation: event.target.value })} />}
            </>}
        <button type="submit">{otp ? 'Verify' : signup ? 'Create account' : 'Sign in'} &rarr;</button>
        {message && <p className="seller-gate-msg">{message}</p>}
        {otp && <button type="button" className="seller-gate-link" onClick={() => { setOtp(null); setMessage('') }}>Use a different account</button>}
        {!otp && <button type="button" className="seller-gate-link" onClick={() => { setSignup((s) => !s); setMessage('') }}>{signup ? 'Already have an account? Sign in' : "Don't have an account? Sign up"}</button>}
        <a className="seller-gate-back" href={STORE_URL}>&larr; Back to store</a>
      </form>
    </div>
  )
}
