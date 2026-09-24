import { lazy, Suspense, useCallback, useEffect, useState } from 'react'
import './Admin.css'
import './Rider.css'

const RiderConsole = lazy(() => import('./RiderConsole'))
const RiderApply = lazy(() => import('./RiderApply'))

const API_URL = import.meta.env.VITE_API_URL ?? 'http://127.0.0.1:8000/api'
const STORE_URL = import.meta.env.BASE_URL || '/'

async function readJson(response) {
  const text = await response.text()
  try { return text ? JSON.parse(text) : {} } catch { return {} }
}

/**
 * Dedicated /rider entry point — the delivery-rider console. Same idea as
 * /admin, except a signed-in account that isn't a rider yet lands on the
 * rider application (RiderApply) instead of being turned away, and new
 * people can create an account right here to apply.
 */
export default function RiderEntry() {
  const [token, setToken] = useState(() => localStorage.getItem('gdp_token') || '')
  const [authed, setAuthed] = useState(false)
  const [applicant, setApplicant] = useState(false) // signed in, not a rider (yet)
  const [signup, setSignup] = useState(false)
  const [checking, setChecking] = useState(() => !!localStorage.getItem('gdp_token'))
  const [form, setForm] = useState({ name: '', email: '', password: '', password_confirmation: '' })
  const [otp, setOtp] = useState(null)
  const [code, setCode] = useState('')
  const [message, setMessage] = useState('')

  const accept = useCallback((data) => {
    if (!data?.token) return false
    if (data.user?.is_admin) {
      setMessage('Administrator accounts can’t be riders — use the admin console.')
      return false
    }
    localStorage.setItem('gdp_token', data.token)
    localStorage.setItem('gdp_user', JSON.stringify(data.user))
    setToken(data.token)
    if (data.user?.is_rider) setAuthed(true)
    else setApplicant(true)
    setMessage('')
    return true
  }, [])

  const signOut = useCallback(() => {
    localStorage.removeItem('gdp_token')
    localStorage.removeItem('gdp_user')
    setAuthed(false)
    setApplicant(false)
    setToken('')
  }, [])

  const approved = useCallback(() => { setApplicant(false); setAuthed(true) }, [])

  useEffect(() => {
    const existing = localStorage.getItem('gdp_token')
    if (!existing) return
    let cancelled = false
    fetch(`${API_URL}/user`, { headers: { Accept: 'application/json', Authorization: `Bearer ${existing}` } })
      .then(readJson)
      .then((user) => {
        if (cancelled || !user?.id) return
        setToken(existing)
        if (user.is_rider) setAuthed(true)
        else if (!user.is_admin) setApplicant(true)
      })
      .catch(() => {})
      .finally(() => { if (!cancelled) setChecking(false) })
    return () => { cancelled = true }
  }, [])

  async function submitSignup(event) {
    event.preventDefault()
    setMessage('')
    try {
      const response = await fetch(`${API_URL}/auth/register`, { method: 'POST', headers: { 'Content-Type': 'application/json', Accept: 'application/json' }, body: JSON.stringify({ name: form.name.trim(), email: form.email.trim(), password: form.password, password_confirmation: form.password_confirmation }) })
      const data = await readJson(response)
      if (!response.ok) throw new Error(data.message ?? Object.values(data.errors ?? {})[0]?.[0] ?? 'Could not create your account.')
      if (data.token) { accept(data); return }
      setOtp({ email: data.email ?? form.email.trim(), purpose: data.purpose ?? 'register' })
      setCode('')
      setMessage(data.message ?? 'Enter the code we emailed you to finish creating your account.')
    } catch (error) {
      setMessage(error instanceof TypeError ? 'The API is offline. Start Laravel on port 8000 and try again.' : error.message)
    }
  }

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
      <RiderConsole token={token} onSignOut={signOut} />
    </Suspense>
  )
  if (applicant) return (
    <Suspense fallback={<div className="admin-gate"><p>Loading&hellip;</p></div>}>
      <RiderApply token={token} onApproved={approved} onSignOut={signOut} />
    </Suspense>
  )

  return (
    <div className="admin-gate">
      <form className="admin-gate-card" onSubmit={otp ? submitCode : signup ? submitSignup : submitPassword}>
        <h1>{signup ? 'Become a rider' : 'Rider sign-in'}</h1>
        <p className="admin-gate-sub">{otp
          ? `Enter the 6-digit code sent to ${otp.email}.`
          : signup
            ? 'Create an account, then apply to deliver for a store near you.'
            : 'For delivery riders. Sign in to see your deliveries — or to apply if you’re new.'}</p>
        {otp
          ? <input required inputMode="numeric" autoComplete="one-time-code" pattern="[0-9]*" maxLength="8" placeholder="6-digit code" value={code} onChange={(event) => setCode(event.target.value.replace(/[^0-9]/g, ''))} />
          : <>
              {signup && <input required autoComplete="name" placeholder="Full name" value={form.name} onChange={(event) => setForm({ ...form, name: event.target.value })} />}
              <input required type="email" autoComplete="username" placeholder="Email" value={form.email} onChange={(event) => setForm({ ...form, email: event.target.value })} />
              <input required type="password" minLength={signup ? 8 : undefined} autoComplete={signup ? 'new-password' : 'current-password'} placeholder="Password" value={form.password} onChange={(event) => setForm({ ...form, password: event.target.value })} />
              {signup && <input required type="password" minLength="8" autoComplete="new-password" placeholder="Confirm password" value={form.password_confirmation} onChange={(event) => setForm({ ...form, password_confirmation: event.target.value })} />}
            </>}
        <button type="submit">{otp ? 'Verify' : signup ? 'Create account' : 'Sign in'} &rarr;</button>
        {message && <p className="admin-gate-msg">{message}</p>}
        {otp && <button type="button" className="admin-gate-link" onClick={() => { setOtp(null); setMessage('') }}>Use a different account</button>}
        {!otp && <button type="button" className="admin-gate-link" onClick={() => { setSignup((v) => !v); setMessage('') }}>{signup ? 'Already have an account? Sign in' : 'New here? Apply to become a rider'}</button>}
        <a className="admin-gate-back" href={STORE_URL}>&larr; Back to store</a>
      </form>
    </div>
  )
}
