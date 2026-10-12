import { useEffect, useState } from 'react'
import { brandName } from './useBranding'

// Extra parts of two content pages: the Careers page lists open roles (Apply),
// and the Partner page shows the ways to work with the store.

const API_URL = import.meta.env.VITE_API_URL ?? 'http://127.0.0.1:8000/api'
const BASE = import.meta.env.BASE_URL || '/'

export function CareersRoles() {
  const [roles, setRoles] = useState(null)
  const [open, setOpen] = useState(null)
  const [form, setForm] = useState({ name: '', email: '', phone: '', link: '', message: '' })
  const [msg, setMsg] = useState('')
  useEffect(() => {
    fetch(`${API_URL}/careers/roles`, { headers: { Accept: 'application/json' } }).then((r) => r.json()).then((d) => setRoles(d.data ?? [])).catch(() => setRoles([]))
  }, [])

  async function apply(role) {
    setMsg('')
    try {
      const token = localStorage.getItem('gdp_token')
      const res = await fetch(`${API_URL}/careers/roles/${role.id}/apply`, { method: 'POST', headers: { Accept: 'application/json', 'Content-Type': 'application/json', ...(token ? { Authorization: `Bearer ${token}` } : {}) }, body: JSON.stringify({ ...form, link: form.link.trim() || null }) })
      const d = await res.json().catch(() => ({}))
      if (!res.ok) throw new Error(d.message ?? 'Could not send.')
      setMsg(d.message); setOpen(null); setForm({ name: '', email: '', phone: '', link: '', message: '' })
    } catch (e) { setMsg(e.message) }
  }

  if (roles === null) return <p>Loading open roles…</p>
  return (
    <section className="careers-roles">
      <h2>Open roles</h2>
      {msg && <p className="careers-msg">{msg}</p>}
      {roles.length === 0 ? <p>No open roles right now — check back soon.</p> : roles.map((r) => (
        <article key={r.id} className="careers-role">
          <h3>{r.title}</h3>
          {r.location && <p className="careers-where">{r.location}</p>}
          {r.description && <p>{r.description}</p>}
          {r.requirements && <div><b>What you need:</b><ul className="careers-needs">{r.requirements.split('\n').filter((l) => l.trim()).map((l) => <li key={l}>{l}</li>)}</ul></div>}
          {r.kind === 'rider'
            ? <a className="careers-btn" href={`${BASE}rider/apply`}>Apply</a>
            : open === r.id ? (
              <form className="careers-form" onSubmit={(e) => { e.preventDefault(); apply(r) }}>
                <input required placeholder="Full name" value={form.name} onChange={(e) => setForm({ ...form, name: e.target.value })} />
                <input required type="email" placeholder="Email" value={form.email} onChange={(e) => setForm({ ...form, email: e.target.value })} />
                <input placeholder="Phone (optional)" value={form.phone} onChange={(e) => setForm({ ...form, phone: e.target.value })} />
                <input type="url" placeholder="Link to your CV or profile (optional)" value={form.link} onChange={(e) => setForm({ ...form, link: e.target.value })} />
                <textarea rows="3" placeholder="Why you'd like this role (optional)" value={form.message} onChange={(e) => setForm({ ...form, message: e.target.value })} />
                <button type="submit" className="careers-btn">Send application</button>
                <button type="button" className="careers-link" onClick={() => setOpen(null)}>Cancel</button>
              </form>
            ) : <button type="button" className="careers-btn" onClick={() => { setMsg(''); setOpen(r.id) }}>Apply</button>}
        </article>
      ))}
    </section>
  )
}

export function PartnerCards() {
  const cards = [
    { title: 'Sell with us', text: `Open a shop on ${brandName()} and reach buyers in your country. You set your prices and shipping; we bring the customers.`, cta: 'Start selling', href: `${BASE}seller` },
    { title: 'Deliver with us', text: 'Deliver orders from a store near you on your own vehicle, during the store’s working hours, and get paid per delivery.', cta: 'Apply to deliver', href: `${BASE}rider/apply` },
    { title: 'Other partnerships', text: 'Brands, logistics companies, affiliates and press — tell us what you have in mind.', cta: 'Contact us', href: '#/p/contact' },
  ]
  return (
    <section className="partner-cards">
      {cards.map((c) => (
        <article key={c.title} className="partner-card">
          <h3>{c.title}</h3>
          <p>{c.text}</p>
          <a className="careers-btn" href={c.href}>{c.cta}</a>
        </article>
      ))}
      <p className="partner-more">Looking for a job with us? See <a href="#/p/careers">Careers</a>.</p>
    </section>
  )
}
