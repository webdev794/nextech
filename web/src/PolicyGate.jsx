import { useEffect, useRef, useState } from 'react'
import { renderMarkdown } from './markdown'
import { PageSection } from './PageSections'

// Seller policies admin marks as needing acceptance ("to sell" / "to sell
// abroad"). PolicyAccept is the read-to-the-end + tick + typed-name signature
// box; PolicyGate is the step-by-step window that opens when a seller tries a
// task needing them: one policy after another, each signature saved as it's
// given, then it closes and the task carries on (nothing they typed is lost).

const API_URL = import.meta.env.VITE_API_URL ?? 'http://127.0.0.1:8000/api'

async function readJson(response) {
  try { return await response.json() } catch { return {} }
}

// Read to the end, tick, sign with your name: the acceptance is kept with the
// date and the exact text; a changed policy asks to accept again.
export function PolicyAccept({ page, status, headers, defaultName, onAccepted, compact }) {
  const endRef = useRef(null)
  const [read, setRead] = useState(false)
  const [agree, setAgree] = useState(false)
  const [name, setName] = useState(defaultName)
  const [msg, setMsg] = useState('')
  const [busy, setBusy] = useState(false)
  useEffect(() => {
    const el = endRef.current
    if (!el || typeof IntersectionObserver === 'undefined') { Promise.resolve().then(() => setRead(true)); return undefined }
    const io = new IntersectionObserver((entries) => { if (entries.some((e) => e.isIntersecting)) setRead(true) })
    io.observe(el)
    return () => io.disconnect()
  }, [page.slug])

  if (status?.accepted) {
    return <div ref={endRef} className="sc-alert ok"><span>✓ Accepted and signed by <b>{status.accepted.signed_name}</b> on {new Date(status.accepted.accepted_at).toLocaleString()}.</span></div>
  }

  async function accept(event) {
    event.preventDefault()
    setMsg('')
    setBusy(true)
    try {
      const response = await fetch(`${API_URL}/seller/policies/${page.slug}/accept`, { method: 'POST', headers: { ...headers(), 'Content-Type': 'application/json' }, body: JSON.stringify({ agree, signed_name: name }) })
      const data = await readJson(response)
      if (!response.ok) throw new Error(data.message ?? Object.values(data.errors ?? {})[0]?.[0] ?? 'Could not save.')
      onAccepted(data.data)
    } catch (e) { setMsg(e.message) } finally { setBusy(false) }
  }

  return (
    <form ref={endRef} className={compact ? 'seller-policy-accept' : 'sc-card seller-policy-accept'} onSubmit={accept}>
      {!compact && <h2 className="sc-h2">{status?.outdated ? 'This policy has changed — accept the new version' : 'Accept and sign'}</h2>}
      {status?.outdated && compact && <p className="sc-muted">This policy has changed since you last accepted it.</p>}
      <p className="sc-muted">{page.acceptance_for === 'international' ? 'Needed before you can sell abroad.' : 'Needed before you can list or update products.'} Your name, today&rsquo;s date and this version of the text are kept as your signature.</p>
      {!read && <p className="sc-muted">Scroll to the end of the policy to accept it.</p>}
      <label className="sc-check"><input type="checkbox" disabled={!read} checked={agree} onChange={(e) => setAgree(e.target.checked)} /> I have read &ldquo;{page.title}&rdquo; and accept it.</label>
      <label>Your full name (signature)<input required minLength="3" maxLength="160" disabled={!read} value={name} onChange={(e) => setName(e.target.value)} /></label>
      <p className="sc-muted">Date: {new Date().toLocaleDateString()}</p>
      {msg && <p className="sc-alert warn">{msg}</p>}
      <div><button type="submit" className="sc-primary" disabled={busy || !read || !agree || name.trim().length < 3}>Accept and sign</button></div>
    </form>
  )
}

// The window: mounted once in Seller Center, opened by requestPolicies().
export function PolicyGate({ headers, defaultName, onStatus }) {
  const [queue, setQueue] = useState(null) // { list: [{slug,title}], index, resolve }
  const [page, setPage] = useState(null)
  const [name, setName] = useState(defaultName)

  useEffect(() => {
    const open = (event) => setQueue({ list: event.detail.policies, index: 0, resolve: event.detail.resolve })
    window.addEventListener('nextech:policies', open)
    return () => window.removeEventListener('nextech:policies', open)
  }, [])

  const current = queue?.list[queue.index]
  useEffect(() => {
    if (!current) return undefined
    let stop = false
    Promise.resolve().then(() => setPage(null))
    fetch(`${API_URL}/pages/${current.slug}`, { headers: { Accept: 'application/json' } }).then(readJson)
      .then((d) => { if (!stop) setPage(d?.data ?? { slug: current.slug, title: current.title, content: '' }) })
      .catch(() => { if (!stop) setPage({ slug: current.slug, title: current.title, content: 'Could not load this policy.' }) })
    return () => { stop = true }
  }, [current])

  if (!queue || !current) return null
  const total = queue.list.length

  function close(done) {
    queue.resolve(done)
    setQueue(null)
    setPage(null)
  }

  function accepted(list) {
    onStatus?.(list)
    if (queue.index + 1 >= total) close(true)
    else setQueue((q) => ({ ...q, index: q.index + 1 }))
  }

  return (
    <div className="ss-overlay" role="presentation">
      <div className="ss-modal ss-wide policy-gate" role="dialog" aria-modal="true" aria-label="Accept policies">
        <div className="policy-gate-head">
          <h2 className="sc-h2">Before you continue — {queue.index + 1} of {total}</h2>
          <button type="button" className="seller-btn ghost" onClick={() => close(false)}>Later</button>
        </div>
        <ol className="policy-gate-steps">{queue.list.map((p, i) => <li key={p.slug} className={i < queue.index ? 'done' : i === queue.index ? 'current' : ''}>{i < queue.index ? '✓ ' : ''}{p.title}</li>)}</ol>
        {!page ? <p className="sc-muted">Loading&hellip;</p> : (
          <div className="policy-gate-body">
            <h3>{page.title}</h3>
            {Array.isArray(page.sections) && page.sections.length > 0
              ? <div className="page-sections">{page.sections.map((section, index) => <PageSection key={index} section={section} />)}</div>
              : <div className="page-content" dangerouslySetInnerHTML={{ __html: renderMarkdown(page.content ?? '') }} />}
            <PolicyAccept key={page.slug} compact page={{ ...page, acceptance_for: page.acceptance_for ?? current.for }} status={{ outdated: current.outdated }} headers={headers} defaultName={name} onAccepted={(list) => { const signed = list.find((p) => p.slug === page.slug)?.accepted?.signed_name; if (signed) setName(signed); accepted(list) }} />
          </div>
        )}
        <p className="sc-muted">Each policy you sign is saved straight away. When you&rsquo;ve signed them all, this closes and what you were doing carries on.</p>
      </div>
    </div>
  )
}
