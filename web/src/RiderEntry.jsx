import { lazy, Suspense, useCallback, useEffect, useState } from 'react'
import './Admin.css'
import './Rider.css'

const RiderConsole = lazy(() => import('./RiderConsole'))

const API_URL = import.meta.env.VITE_API_URL ?? 'http://127.0.0.1:8000/api'
const STORE_URL = import.meta.env.BASE_URL || '/'

async function readJson(response) {
  const text = await response.text()
  try { return text ? JSON.parse(text) : {} } catch { return {} }
}

/**
 * Dedicated /rider entry point — the delivery-rider console. Same idea as
 * /admin: an existing session is reused only when it belongs to a rider,
 * otherwise sign in with email + password.
 */
export default function RiderEntry() {
  const [token, setToken] = useState(() => localStorage.getItem('gdp_token') || '')
  const [authed, setAuthed] = useState(false)
  const [checking, setChecking] = useState(() => !!localStorage.getItem('gdp_token'))
  const [form, setForm] = useState({ email: '', password: '' })
  const [otp, setOtp] = useState(null)
  const [code, setCode] = useState('')
  const [message, setMessage] = useState('')

  const accept = useCallback((data) => {
    if (!data?.user?.is_rider) {
      setMessage('This account is not a delivery rider.')
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
      .then((user) => { if (!cancelled && user?.is_rider) { setToken(existing); setAuthed(true) } })
      .catch(() => {})
      .finally(() => { if (!cancelled) setChecking(false) })
    return () => { cancelled = true }
  }, [])

  async function submitPassword(event) {
    event.preventDefault()
    setMessage('')
    try {
      const response = await fetch(`${API_URL}/auth/login`, { method: 'POST', headers: { 'Content-Type': 'application/json', Accept: 'application/json' }, body: JSON.stringify({ email: form.email.trim(), password: form.password }) })
      const data = await readJson(response)
      if (!response.ok) throw new Error(data.message ?? Object.values(data.errors ?? {})[0]?.[0] ?? 'The email or password is incorrect.')
      if (data.token) { accept(data); return }
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
      if (accept(data)) setOtp(null)
    } catch (error) {
      setMessage(error instanceof TypeError ? 'The API is offline. Start Laravel on port 8000 and try again.' : error.message)
    }
  }

  if (checking) return <div className="admin-gate"><p>Loading&hellip;</p></div>
  if (authed) return (
    <Suspense fallback={<div className="admin-gate"><p>Loading&hellip;</p></div>}>
      <RiderConsole token={token} onSignOut={() => { localStorage.removeItem('gdp_token'); localStorage.removeItem('gdp_user'); setAuthed(false); setToken('') }} />
    </Suspense>
  )

  return (
    <div className="admin-gate">
      <form className="admin-gate-card" onSubmit={otp ? submitCode : submitPassword}>
        <h1>Rider sign-in</h1>
        <p className="admin-gate-sub">{otp
          ? `Enter the 6-digit code sent to ${otp.email}.`
          : 'For delivery riders. Sign in to see your assigned deliveries.'}</p>
        {otp
          ? <input required inputMode="numeric" autoComplete="one-time-code" pattern="[0-9]*" maxLength="8" placeholder="6-digit code" value={code} onChange={(event) => setCode(event.target.value.replace(/[^0-9]/g, ''))} />
          : <>
              <input required type="email" autoComplete="username" placeholder="Rider email" value={form.email} onChange={(event) => setForm({ ...form, email: event.target.value })} />
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
