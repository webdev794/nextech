import { useCallback, useEffect, useMemo, useState } from 'react'

// Seller Center onboarding tasks (modelled on Temu's): 1 tax information,
// 2 additional compliance information, 3 bank account, 4 shipping templates
// (SellerShipping.jsx). Tasks 1–3 are reviewed by NexTech in the admin console.

const API_URL = import.meta.env.VITE_API_URL ?? 'http://127.0.0.1:8000/api'

async function readJson(response) {
  const text = await response.text()
  try { return text ? JSON.parse(text) : {} } catch { return {} }
}

async function send(headers, path, method = 'GET', body) {
  const response = await fetch(`${API_URL}${path}`, { method, headers: { ...headers(), ...(body ? { 'Content-Type': 'application/json' } : {}) }, body: body ? JSON.stringify(body) : undefined })
  const data = await readJson(response)
  if (!response.ok) throw new Error(data.message ?? Object.values(data.errors ?? {})[0]?.[0] ?? 'Something went wrong.')
  return data
}

async function uploadDocument(headers, kind, file) {
  const body = new FormData()
  body.append('file', file)
  body.append('kind', kind)
  const response = await fetch(`${API_URL}/seller/kyc-document`, { method: 'POST', headers: headers(), body })
  const data = await readJson(response)
  if (!response.ok) throw new Error(data.message ?? Object.values(data.errors ?? {})[0]?.[0] ?? 'Could not upload the document.')
  return { path: data.data.path, name: file.name }
}

// Every ISO country the browser can name (citizenship, place of issue, residence).
const RETIRED = new Set(['AN', 'BU', 'CS', 'CT', 'DD', 'DY', 'EU', 'EZ', 'FQ', 'FX', 'HV', 'JT', 'MI', 'NH', 'NQ', 'NT', 'PC', 'PU', 'PZ', 'QO', 'QU', 'RH', 'SU', 'TP', 'UN', 'VD', 'WK', 'YD', 'YU', 'ZR', 'ZZ'])
const COUNTRIES = (() => {
  try {
    const names = new Intl.DisplayNames(['en'], { type: 'region' })
    const out = []
    for (let a = 65; a <= 90; a += 1) for (let b = 65; b <= 90; b += 1) {
      const code = String.fromCharCode(a, b)
      const name = names.of(code)
      if (name && name !== code && !RETIRED.has(code) && !code.startsWith('X') && !code.startsWith('Q')) out.push([code, name])
    }
    return out.sort((x, y) => x[1].localeCompare(y[1]))
  } catch { return [['IN', 'India'], ['US', 'United States']] }
})()
const countryName = (code) => COUNTRIES.find(([c]) => c === code)?.[1] ?? code

const STATUS = {
  todo: ['Not started', 'hidden'], step2: ['Step 2 left', 'pending'], pending: ['Under review', 'pending'], approved: ['Completed', 'approved'],
  rejected: ['Action needed', 'rejected'], processing: ['Processing', 'pending'], linked: ['Successfully linked', 'approved'], failed: ['Verification failed', 'rejected'], done: ['Completed', 'approved'],
}
const StatusPill = ({ status }) => { const [label, tone] = STATUS[status] ?? STATUS.todo; return <span className={`sc-pill ${tone}`}>{label}</span> }
const complete = (status) => ['approved', 'linked', 'done'].includes(status)

function useOnboarding(headers) {
  const [data, setData] = useState(null)
  const [msg, setMsg] = useState('')
  useEffect(() => {
    send(headers, '/seller/onboarding').then((d) => setData(d.data)).catch((e) => setMsg(e.message))
  }, [headers])
  const submit = useCallback(async (path, body, okMsg) => {
    setMsg('')
    try {
      const d = await send(headers, path, 'POST', body)
      setData(d.data)
      if (okMsg) setMsg(okMsg)
      return true
    } catch (e) { setMsg(e.message); return false }
  }, [headers])
  return { data, msg, setMsg, submit }
}

function Uploader({ headers, kind, label, value, onChange, required, onError }) {
  const [busy, setBusy] = useState(false)
  return (
    <label>{label}{required ? '' : ' (optional)'}
      <input type="file" accept=".jpg,.jpeg,.png,.pdf" disabled={busy} onChange={async (e) => {
        const file = e.target.files?.[0]
        e.target.value = ''
        if (!file) return
        setBusy(true)
        try { onChange(await uploadDocument(headers, kind, file)) } catch (err) { onError(err.message) } finally { setBusy(false) }
      }} />
      <small className="sc-muted">{busy ? 'Uploading…' : value?.name ? `Uploaded: ${value.name}` : 'JPG, PNG or PDF, up to 8 MB.'}</small>
    </label>
  )
}

const Banner = ({ tone = 'warn', children }) => <div className={`sc-alert ${tone}`}><span>{children}</span></div>
const SupportLine = ({ onSupport }) => <p className="sc-muted ob-support">Visit the support center if you have any questions. <button type="button" className="sc-link" onClick={onSupport}>Contact us</button></p>

// ---------------------------------------------------------------------------
// Homepage card
// ---------------------------------------------------------------------------
export function OnboardingTasks({ headers, go, hasProducts, onAddProduct }) {
  const [tasks, setTasks] = useState(null)
  useEffect(() => { send(headers, '/seller/onboarding').then((d) => setTasks(d.data.tasks)).catch(() => {}) }, [headers])
  if (!tasks) return null
  const bankLocked = !['pending', 'approved'].includes(tasks.compliance)
  const rows = [
    ['tax', 'Task 1', 'Add tax information', 'Your tax registration number and default item tax code.', tasks.tax],
    ['compliance', 'Task 2', 'Add additional compliance information', 'To comply with local laws and regulations, provide business role information.', tasks.compliance],
    ['bank', 'Task 3', 'Add your bank account', bankLocked ? 'Available after you add additional compliance information.' : 'Add your bank account to request payment.', tasks.bank],
    ['shipping', 'Task 4', 'Set up shipping templates', 'Set up your shipping methods so your products can be shipped.', tasks.shipping],
  ]
  const left = rows.filter((r) => !complete(r[4])).length
  if (!left && hasProducts) return null
  return (
    <div className="sc-card">
      <h2 className="sc-h2">Get your shop ready <span className="sc-muted">{rows.length - left}/{rows.length} done</span></h2>
      <ul className="ss-checklist ob-tasks">
        {rows.map(([key, task, title, text, status]) => (
          <li key={key} className={complete(status) ? 'done' : ''}>
            <span><small className="sc-muted">{task}</small><b>{title}</b><small className="sc-muted">{text}</small></span>
            <span className="ob-task-end">
              {status !== 'todo' && <StatusPill status={status} />}
              {!complete(status) && <button type="button" className="sc-primary" disabled={key === 'bank' && bankLocked} onClick={() => go(key)}>{['rejected', 'failed'].includes(status) ? 'Fix' : status === 'todo' || status === 'step2' ? 'Set up' : 'View'}</button>}
            </span>
          </li>
        ))}
        {!hasProducts && <li><span><b>List your first product</b></span><span className="ob-task-end"><button type="button" className="sc-primary" onClick={onAddProduct}>Add product</button></span></li>}
      </ul>
    </div>
  )
}

// ---------------------------------------------------------------------------
// Task 1: tax information
// ---------------------------------------------------------------------------
export function TaxInformation({ headers, onSupport }) {
  const { data, msg, setMsg, submit } = useOnboarding(headers)
  const [open, setOpen] = useState(null) // 1 | 2
  const [step1, setStep1] = useState(null)
  const [step2, setStep2] = useState(null)
  if (!data) return <div className="sc-card"><p className="sc-muted">{msg || 'Loading…'}</p></div>

  const cfg = data.config
  const info = data.tax.info ?? {}
  const status = data.tax.status
  const step1Done = !!info.tax_number
  const openStep1 = () => { setStep1({ tax_number: info.tax_number ?? '', certificate: info.certificate_path ? { path: info.certificate_path, name: info.certificate_name } : null }); setOpen(1) }
  const openStep2 = () => { setStep2({ tax_code: info.tax_code ?? Object.keys(cfg.tax_codes ?? {})[0] ?? '', agree: false }); setOpen(2) }

  return (
    <>
      <h1 className="sc-title">Tax information</h1>
      {msg && <div className="sc-alert warn"><span>{msg}</span><button type="button" onClick={() => setMsg('')}>OK</button></div>}
      {status === 'pending' && <Banner>Your tax information is being reviewed — this takes 1–3 business days (excluding holidays).</Banner>}
      {status === 'rejected' && <Banner tone="danger">Your tax information was sent back: {data.tax.note} Update it below and submit again.</Banner>}
      {status === 'approved' && <Banner tone="ok">Your tax information is approved.</Banner>}
      <div className="sc-card">
        <p>Adding tax information consists of two steps.</p>
        <ul className="ss-checklist ob-tasks">
          <li className={step1Done ? 'done' : ''}>
            <span><small className="sc-muted">Step 1</small><b>Add your {cfg.tax_number_label}</b>{step1Done && <small className="sc-muted">{info.tax_number}{info.certificate_name ? ` · ${info.certificate_name}` : ''}</small>}</span>
            <button type="button" className={step1Done ? 'seller-btn ghost' : 'sc-primary'} onClick={openStep1}>{step1Done ? 'Edit' : 'Set up'}</button>
          </li>
          <li className={info.tax_code ? 'done' : ''}>
            <span><small className="sc-muted">Step 2</small><b>Configure tax calculation settings</b>{info.tax_code && <small className="sc-muted">Default item tax code: {cfg.tax_codes?.[info.tax_code] ?? info.tax_code}</small>}</span>
            <button type="button" className={info.tax_code ? 'seller-btn ghost' : 'sc-primary'} disabled={!step1Done} onClick={openStep2}>{info.tax_code ? 'Edit' : 'Set up'}</button>
          </li>
        </ul>
        <SupportLine onSupport={onSupport} />
      </div>

      {open === 1 && step1 && (
        <div className="ss-overlay" role="presentation" onClick={() => setOpen(null)}>
          <form className="ss-modal" onClick={(e) => e.stopPropagation()} onSubmit={async (e) => {
            e.preventDefault()
            if (await submit('/seller/onboarding/tax-number', { tax_number: step1.tax_number, certificate_path: step1.certificate?.path ?? null, certificate_name: step1.certificate?.name ?? null }, info.tax_code ? 'Saved — your tax information is back in review.' : 'Saved. Now configure your tax calculation settings (step 2).')) setOpen(null)
          }}>
            <h2 className="sc-h2">Add your {cfg.tax_number_label}</h2>
            <label>{cfg.tax_number_label}<input required value={step1.tax_number} onChange={(e) => setStep1({ ...step1, tax_number: e.target.value })} /></label>
            <p className="sc-muted">Make sure it&rsquo;s valid and matches the one you gave when you registered.</p>
            <Uploader headers={headers} kind="tax_certificate" label={cfg.tax_certificate_label} required={!!cfg.tax_certificate_required} value={step1.certificate} onChange={(certificate) => setStep1((s) => ({ ...s, certificate }))} onError={setMsg} />
            <p className="sc-muted">The name and address on the certificate must match your registration: <b>{data.company_name}</b>, {data.registered_address.join(', ')}.</p>
            <div className="ss-actions"><button type="button" className="seller-btn ghost" onClick={() => setOpen(null)}>Cancel</button><button type="submit" className="sc-primary" disabled={cfg.tax_certificate_required && !step1.certificate}>Submit</button></div>
          </form>
        </div>
      )}

      {open === 2 && step2 && (
        <div className="ss-overlay" role="presentation" onClick={() => setOpen(null)}>
          <form className="ss-modal" onClick={(e) => e.stopPropagation()} onSubmit={async (e) => {
            e.preventDefault()
            if (await submit('/seller/onboarding/tax-settings', step2, 'Thank you for completing the tax information setup. It will be reviewed within 1–3 business days (excluding holidays).')) setOpen(null)
          }}>
            <h2 className="sc-h2">Tax calculation settings</h2>
            <label>Default item tax code
              <select required value={step2.tax_code} onChange={(e) => setStep2({ ...step2, tax_code: e.target.value })}>
                {Object.entries(cfg.tax_codes ?? {}).map(([code, label]) => <option key={code} value={code}>{label}</option>)}
              </select>
            </label>
            <p className="sc-muted">Used for tax on every item that doesn&rsquo;t set its own tax code.</p>
            <label className="sc-check"><input type="checkbox" checked={step2.agree} onChange={(e) => setStep2({ ...step2, agree: e.target.checked })} /> I confirm this tax code is correct for my items and agree to NexTech&rsquo;s tax calculation terms.</label>
            <div className="ss-actions"><button type="button" className="seller-btn ghost" onClick={() => setOpen(null)}>Cancel</button><button type="submit" className="sc-primary" disabled={!step2.agree}>Submit</button></div>
          </form>
        </div>
      )}
    </>
  )
}

// ---------------------------------------------------------------------------
// Task 2: additional compliance information
// ---------------------------------------------------------------------------
const ROLE_INFO = [
  ['ubo', 'Ultimate beneficial owners', 'An ultimate beneficial owner (UBO) is anyone who owns at least 25% of the company. If no one fits this, any senior manager can be the beneficial owner.'],
  ['director', 'Directors', 'At least one person registered as a company director, responsible for supervising its activities and affairs.'],
  ['executive', 'Executives', 'At least one person with significant control who manages and guides the business — e.g. CEO, CFO, COO, president, vice president, general partner or other senior executive.'],
]
const ID_TYPES = [['passport', 'Passport'], ['national_id', 'National ID card'], ['drivers_license', 'Driver’s license']]
const EMPTY_ADDRESS = { line1: '', line2: '', city: '', state: '', postal_code: '', country: '' }
const emptyPerson = (roles, country) => ({ legal_name: '', roles, ownership_pct: '', citizenship: country, place_of_birth: '', date_of_birth: '', id_country: country, id_type: 'passport', id_number: '', id_expiry: '', address: { ...EMPTY_ADDRESS, country } })
const personKey = (name, dob) => `${String(name).trim().replace(/\s+/g, ' ').toLowerCase()}|${dob}`

function CountrySelect({ value, onChange }) {
  return <select required value={value} onChange={(e) => onChange(e.target.value)}><option value="">Select…</option>{COUNTRIES.map(([code, name]) => <option key={code} value={code}>{name}</option>)}</select>
}

function AddressFields({ value, onChange }) {
  const set = (patch) => onChange({ ...value, ...patch })
  return (
    <>
      <label>Residential address line 1<input required value={value.line1} onChange={(e) => set({ line1: e.target.value })} /></label>
      <label>Address line 2 (optional)<input value={value.line2 ?? ''} onChange={(e) => set({ line2: e.target.value })} /></label>
      <div className="ss-row">
        <label>City / town<input required value={value.city} onChange={(e) => set({ city: e.target.value })} /></label>
        <label>State / region<input value={value.state ?? ''} onChange={(e) => set({ state: e.target.value })} /></label>
        <label>Postal code<input required value={value.postal_code} onChange={(e) => set({ postal_code: e.target.value })} /></label>
      </div>
      <label>Country<CountrySelect value={value.country} onChange={(country) => set({ country })} /></label>
    </>
  )
}

export function ComplianceInformation({ headers, onSupport, go }) {
  const { data, msg, setMsg, submit } = useOnboarding(headers)
  const [form, setForm] = useState(null)
  const [personForm, setPersonForm] = useState(null) // { index|null, person }
  const [docType, setDocType] = useState('')

  useEffect(() => {
    if (!data || form) return
    const saved = data.compliance.data
    const primary = saved?.people?.find((p) => p.is_primary)
    Promise.resolve().then(() => setForm({
      primary: primary
        ? { roles: primary.roles, ownership_pct: primary.ownership_pct ?? '', citizenship: primary.citizenship, place_of_birth: primary.place_of_birth, id_expiry: primary.id_expiry, address: { ...EMPTY_ADDRESS, ...primary.address } }
        // The primary contact is auto-filled as the executive, beneficial owner and director.
        : { roles: ['ubo', 'director', 'executive'], ownership_pct: '', citizenship: data.country, place_of_birth: '', id_expiry: '', address: { ...EMPTY_ADDRESS, country: data.country } },
      people: (saved?.people ?? []).filter((p) => !p.is_primary).map((p) => ({ ...p, ownership_pct: p.ownership_pct ?? '', address: { ...EMPTY_ADDRESS, ...p.address } })),
      documents: saved?.documents ?? [],
    }))
  }, [data, form])

  const docTypes = useMemo(() => Object.entries(data?.config?.corporate_documents ?? {}), [data])
  if (!data || !form) return <div className="sc-card"><p className="sc-muted">{msg || 'Loading…'}</p></div>

  const pc = data.primary_contact
  const status = data.compliance.status
  const company = data.is_company
  const needsDocs = company || data.business_type === 'proprietorship'
  const everyone = [{ legal_name: pc.legal_name, roles: form.primary.roles, primary: true }, ...form.people]
  const setPrimary = (patch) => setForm((f) => ({ ...f, primary: { ...f.primary, ...patch } }))
  const toggleRole = (who, role, on) => {
    if (who === 'primary') setPrimary({ roles: on ? [...new Set([...form.primary.roles, role])] : form.primary.roles.filter((r) => r !== role) })
    else setForm((f) => ({ ...f, people: f.people.map((p, i) => (i === who ? { ...p, roles: on ? [...new Set([...p.roles, role])] : p.roles.filter((r) => r !== role) } : p)) }))
  }

  function savePerson(event) {
    event.preventDefault()
    const { index, person } = personForm
    const key = personKey(person.legal_name, person.date_of_birth)
    if (key === personKey(pc.legal_name, pc.date_of_birth)) { setMsg(`${person.legal_name} is your primary contact — don’t create them again. Use “Choose” to give the primary contact a role.`); return }
    if (form.people.some((p, i) => i !== index && personKey(p.legal_name, p.date_of_birth) === key)) { setMsg(`${person.legal_name} is already listed — give that person every role they hold instead of adding them again.`); return }
    if (!person.roles.length) { setMsg('Pick at least one role.'); return }
    setForm((f) => ({ ...f, people: index == null ? [...f.people, person] : f.people.map((p, i) => (i === index ? person : p)) }))
    setPersonForm(null)
    setMsg('')
  }

  async function submitAll(event) {
    event.preventDefault()
    const body = {
      primary: { ...form.primary, ownership_pct: form.primary.ownership_pct === '' ? null : Number(form.primary.ownership_pct) },
      people: form.people.map((p) => ({ ...p, ownership_pct: p.ownership_pct === '' ? null : Number(p.ownership_pct) })),
      documents: form.documents,
    }
    if (await submit('/seller/onboarding/compliance', body, 'Thank you for completing the additional compliance information setup. NexTech will review it shortly.')) window.scrollTo({ top: 0 })
  }

  return (
    <>
      <h1 className="sc-title">Additional compliance information</h1>
      {msg && <div className="sc-alert warn"><span>{msg}</span><button type="button" onClick={() => setMsg('')}>OK</button></div>}
      {status === 'pending' && <Banner>Submitted — NexTech is reviewing your compliance information. {data.tasks.bank === 'todo' && <button type="button" className="sc-link" onClick={() => go('bank')}>Next: add your bank account</button>}</Banner>}
      {status === 'rejected' && <Banner tone="danger">Your compliance information was sent back: {data.compliance.note} Update it below and submit again.</Banner>}
      {status === 'approved' && <Banner tone="ok">Your compliance information is approved.</Banner>}

      <form onSubmit={submitAll}>
        <div className="sc-card ob-intro">
          <h2 className="sc-h2">Senior management personnel information</h2>
          <p className="sc-muted">To comply with the laws and regulations where you sell, NexTech evaluates each seller&rsquo;s risk level from the information provided, and may ask for more. We handle your information with the utmost care — see the <a href="#/p/seller-privacy-policy">Seller Privacy Policy</a>.</p>
          {!company && <p>As {data.business_type === 'individual' ? 'an individual seller' : 'a sole proprietor'}, you are your business&rsquo;s owner, director and executive — complete your details below.</p>}
        </div>

        <div className="sc-card">
          <div className="sc-head"><h2 className="sc-h2">{pc.legal_name} <span className="sc-pill approved">Primary contact</span></h2></div>
          <p className="sc-muted">Your primary contact is filled in from your seller registration and can&rsquo;t be edited on this page.{company && <> Don&rsquo;t use &ldquo;Create new person&rdquo; to add {pc.legal_name} again — use &ldquo;Choose&rdquo; to give them a role.</>}</p>
          <dl className="ob-facts">
            <dt>Legal name</dt><dd>{pc.legal_name}</dd>
            <dt>Date of birth</dt><dd>{pc.date_of_birth}</dd>
            <dt>Proof of identity</dt><dd>{pc.id_type} · {pc.id_number} ({countryName(pc.id_country)})</dd>
          </dl>
          <div className="ob-grid">
            <div className="ss-row">
              <label>Citizenship<CountrySelect value={form.primary.citizenship} onChange={(citizenship) => setPrimary({ citizenship })} /></label>
              <label>Place of birth<input required value={form.primary.place_of_birth} placeholder="City, country" onChange={(e) => setPrimary({ place_of_birth: e.target.value })} /></label>
              <label>ID date of expiry<input required type="date" value={form.primary.id_expiry} onChange={(e) => setPrimary({ id_expiry: e.target.value })} /></label>
            </div>
            <AddressFields value={form.primary.address} onChange={(address) => setPrimary({ address })} />
          </div>
        </div>

        {company && ROLE_INFO.map(([role, title, text]) => {
          const holders = everyone.map((p, i) => ({ ...p, idx: i - 1 })).filter((p) => p.roles.includes(role))
          const others = everyone.map((p, i) => ({ ...p, idx: i - 1 })).filter((p) => !p.roles.includes(role))
          return (
            <div className="sc-card" key={role}>
              <div className="sc-head">
                <h2 className="sc-h2">{title}</h2>
                <span className="ob-task-end">
                  {others.length > 0 && (
                    <select className="ob-choose" value="" onChange={(e) => { if (e.target.value !== '') toggleRole(e.target.value === 'primary' ? 'primary' : Number(e.target.value), role, true) }}>
                      <option value="">Choose…</option>
                      {others.map((p) => <option key={p.idx} value={p.primary ? 'primary' : p.idx}>{p.legal_name}{p.primary ? ' (primary contact)' : ''}</option>)}
                    </select>
                  )}
                  <button type="button" className="seller-btn ghost" onClick={() => setPersonForm({ index: null, person: emptyPerson([role], data.country) })}>+ Create new person</button>
                </span>
              </div>
              <p className="sc-muted">{text}</p>
              {holders.length === 0 ? <p className="ob-missing">Add at least one.</p> : (
                <ul className="ob-people">
                  {holders.map((p) => (
                    <li key={p.idx}>
                      <span><b>{p.legal_name}</b>{p.primary && <small className="sc-muted"> — primary contact</small>}{role === 'ubo' && (p.primary ? form.primary.ownership_pct : p.ownership_pct) !== '' && <small className="sc-muted"> · {p.primary ? form.primary.ownership_pct : p.ownership_pct}% owned</small>}</span>
                      <span className="sc-actions">
                        {!p.primary && <button type="button" onClick={() => setPersonForm({ index: p.idx, person: { ...form.people[p.idx] } })}>Edit</button>}
                        <button type="button" className="danger" onClick={() => toggleRole(p.primary ? 'primary' : p.idx, role, false)}>Remove</button>
                      </span>
                    </li>
                  ))}
                </ul>
              )}
              {role === 'ubo' && form.primary.roles.includes('ubo') && (
                <label className="ob-inline">{pc.legal_name}&rsquo;s ownership (%)<input type="number" min="0" max="100" step="0.01" value={form.primary.ownership_pct} onChange={(e) => setPrimary({ ownership_pct: e.target.value })} /></label>
              )}
            </div>
          )
        })}

        {company && form.people.some((p) => !p.roles.length) && (
          <div className="sc-card"><p className="ob-missing">These people have no role left — give them one or delete them.</p><ul className="ob-people">{form.people.map((p, i) => !p.roles.length && (
            <li key={i}><span><b>{p.legal_name}</b> <small className="sc-muted">no role</small></span><span className="sc-actions"><button type="button" onClick={() => setPersonForm({ index: i, person: { ...p } })}>Edit</button><button type="button" className="danger" onClick={() => setForm((f) => ({ ...f, people: f.people.filter((_, j) => j !== i) }))}>Delete</button></span></li>
          ))}</ul></div>
        )}

        <div className="sc-card">
          <h2 className="sc-h2">Upload corporate documents</h2>
          <p className="sc-muted">Make sure the documents are legible and the most current version. {needsDocs ? 'Upload at least one business certification document.' : 'Optional for individual sellers.'}</p>
          <p className="sc-muted">They must show: <b>name of firm</b> {data.company_name} · <b>registration number</b> {data.registered_tax_id} · <b>business address</b> {data.registered_address.join(', ')}</p>
          {form.documents.length > 0 && (
            <ul className="ob-people">
              {form.documents.map((d, i) => (
                <li key={d.path}><span><b>{data.config.corporate_documents?.[d.type] ?? d.type}</b> <small className="sc-muted">{d.name}</small></span><span className="sc-actions"><button type="button" className="danger" onClick={() => setForm((f) => ({ ...f, documents: f.documents.filter((_, j) => j !== i) }))}>Remove</button></span></li>
              ))}
            </ul>
          )}
          <div className="ss-row ob-upload">
            <label>Document type<select value={docType || docTypes[0]?.[0] || ''} onChange={(e) => setDocType(e.target.value)}>{docTypes.map(([k, v]) => <option key={k} value={k}>{v}</option>)}</select></label>
            <Uploader headers={headers} kind="corporate_document" label="File" required value={null} onError={setMsg} onChange={(file) => setForm((f) => ({ ...f, documents: [...f.documents, { type: docType || docTypes[0]?.[0], ...file }] }))} />
          </div>
        </div>

        <div className="sc-card">
          <p>Please review the information to make sure it&rsquo;s correct before submitting.</p>
          <div className="ss-actions"><button type="submit" className="sc-primary">{status ? 'Submit again' : 'Submit'}</button></div>
          <SupportLine onSupport={onSupport} />
        </div>
      </form>

      {personForm && (
        <div className="ss-overlay" role="presentation" onClick={() => setPersonForm(null)}>
          <form className="ss-modal ss-wide" onClick={(e) => e.stopPropagation()} onSubmit={savePerson}>
            <h2 className="sc-h2">{personForm.index == null ? 'Create new person' : `Edit ${personForm.person.legal_name}`}</h2>
            <p className="sc-muted">Follow the details exactly as they appear on the person&rsquo;s proof of identity.</p>
            {(() => {
              const p = personForm.person
              const set = (patch) => setPersonForm((f) => ({ ...f, person: { ...f.person, ...patch } }))
              return (
                <>
                  <label>Legal name<input required value={p.legal_name} onChange={(e) => set({ legal_name: e.target.value })} /></label>
                  <div className="ob-roles">
                    {ROLE_INFO.map(([role, title]) => <label key={role} className="sc-check"><input type="checkbox" checked={p.roles.includes(role)} onChange={(e) => set({ roles: e.target.checked ? [...new Set([...p.roles, role])] : p.roles.filter((r) => r !== role) })} /> {title.replace(/s$/, '')}</label>)}
                  </div>
                  {p.roles.includes('ubo') && <label>Ownership (%)<input type="number" min="0" max="100" step="0.01" value={p.ownership_pct} onChange={(e) => set({ ownership_pct: e.target.value })} /></label>}
                  <div className="ss-row">
                    <label>Citizenship<CountrySelect value={p.citizenship} onChange={(citizenship) => set({ citizenship })} /></label>
                    <label>Place of birth<input required value={p.place_of_birth} placeholder="City, country" onChange={(e) => set({ place_of_birth: e.target.value })} /></label>
                    <label>Date of birth<input required type="date" value={p.date_of_birth} onChange={(e) => set({ date_of_birth: e.target.value })} /></label>
                  </div>
                  <div className="ss-row">
                    <label>Country of issue<CountrySelect value={p.id_country} onChange={(id_country) => set({ id_country })} /></label>
                    <label>Proof of identity type<select value={p.id_type} onChange={(e) => set({ id_type: e.target.value })}>{ID_TYPES.map(([v, l]) => <option key={v} value={v}>{l}</option>)}</select></label>
                  </div>
                  <div className="ss-row">
                    <label>Proof of identity number<input required value={p.id_number} onChange={(e) => set({ id_number: e.target.value })} /></label>
                    <label>Date of expiry<input required type="date" value={p.id_expiry} onChange={(e) => set({ id_expiry: e.target.value })} /></label>
                  </div>
                  <AddressFields value={p.address} onChange={(address) => set({ address })} />
                </>
              )
            })()}
            <div className="ss-actions">
              {personForm.index != null && <button type="button" className="seller-btn ghost danger" onClick={() => { setForm((f) => ({ ...f, people: f.people.filter((_, i) => i !== personForm.index) })); setPersonForm(null) }}>Delete person</button>}
              <button type="button" className="seller-btn ghost" onClick={() => setPersonForm(null)}>Cancel</button>
              <button type="submit" className="sc-primary">Save</button>
            </div>
          </form>
        </div>
      )}
    </>
  )
}

// ---------------------------------------------------------------------------
// Task 3: bank account
// ---------------------------------------------------------------------------
const mask = (value) => (value ? `•••• ${String(value).slice(-4)}` : '')

export function BankAccount({ headers, onSupport, go, onChanged }) {
  const { data, msg, setMsg, submit } = useOnboarding(headers)
  const [form, setForm] = useState(null)
  const [confirming, setConfirming] = useState(false)
  if (!data) return <div className="sc-card"><p className="sc-muted">{msg || 'Loading…'}</p></div>

  const bankCfg = data.config.bank ?? {}
  const maxAge = data.config.bank_document_max_age_days ?? 180
  const { status, details, note } = data.bank
  const locked = !['pending', 'approved'].includes(data.compliance.status)
  const openForm = () => setForm({
    holder_name: details?.holder_name ?? data.company_name ?? '', bank_name: details?.bank_name ?? '', bank_code: details?.routing_number ?? '', account_number: details?.account_number ?? '',
    document: null, document_issued_on: '',
    // Allowed issue dates: the last maxAge days.
    today: new Date().toISOString().slice(0, 10), oldest: new Date(Date.now() - maxAge * 864e5).toISOString().slice(0, 10),
  })
  const detailsList = details && (
    <dl className="ob-facts">
      <dt>Bank location</dt><dd>{countryName(details.bank_country ?? data.country)}</dd>
      <dt>Account holder&rsquo;s name</dt><dd>{details.holder_name}</dd>
      <dt>Bank</dt><dd>{details.bank_name}</dd>
      <dt>{details.bank_code_label ?? bankCfg.code_label}</dt><dd>{details.routing_number}</dd>
      <dt>{bankCfg.account_label ?? 'Account number'}</dt><dd>{mask(details.account_number)}</dd>
    </dl>
  )
  const docRequirements = (
    <ul className="ob-list">
      <li>Account holder&rsquo;s name</li><li>{bankCfg.code_label}</li><li>{bankCfg.account_label ?? 'Account number'}</li><li>Document issue date — within the last {maxAge} days</li>
    </ul>
  )

  return (
    <>
      <h1 className="sc-title">Bank account</h1>
      {msg && <div className="sc-alert warn"><span>{msg}</span><button type="button" onClick={() => setMsg('')}>OK</button></div>}

      {locked ? (
        <div className="sc-card">
          <p>Add your bank account to request payment after adding additional compliance information.</p>
          <button type="button" className="sc-primary" onClick={() => go('compliance')}>Add compliance information first</button>
        </div>
      ) : form ? (
        <form className="sc-card ob-form" onSubmit={(e) => { e.preventDefault(); setConfirming(true) }}>
          <h2 className="sc-h2">{status === 'failed' ? 'Verify my bank account' : 'Add bank information'}</h2>
          <label>Bank location<input value={countryName(data.country)} disabled /></label>
          <label>Account holder&rsquo;s name<input required value={form.holder_name} onChange={(e) => setForm({ ...form, holder_name: e.target.value })} /></label>
          <label>Bank name<input required value={form.bank_name} onChange={(e) => setForm({ ...form, bank_name: e.target.value })} /></label>
          <div className="ss-row">
            <label>{bankCfg.code_label}<input required value={form.bank_code} pattern={bankCfg.code_regex} onChange={(e) => setForm({ ...form, bank_code: e.target.value.toUpperCase() })} /></label>
            <label>{bankCfg.account_label ?? 'Account number'}<input required inputMode="numeric" value={form.account_number} pattern={bankCfg.account_regex} onChange={(e) => setForm({ ...form, account_number: e.target.value.replace(/\s/g, '') })} /></label>
          </div>
          <h3 className="ss-sub">Bank document</h3>
          <p className="sc-muted">A bank statement or bank letter. It must contain:</p>
          {docRequirements}
          <p className="sc-muted">The details above must exactly match the uploaded bank document.</p>
          <div className="ss-row">
            <Uploader headers={headers} kind="bank_document" label="Bank document" required value={form.document} onError={setMsg} onChange={(document) => setForm((f) => ({ ...f, document }))} />
            <label>Document issue date<input required type="date" value={form.document_issued_on} max={form.today} min={form.oldest} onChange={(e) => setForm({ ...form, document_issued_on: e.target.value })} /></label>
          </div>
          <p className="ob-secure">🔒 NexTech protects your bank information — it&rsquo;s stored privately and only used to pay you.</p>
          <div className="ss-actions">
            {status && <button type="button" className="seller-btn ghost" onClick={() => setForm(null)}>Cancel</button>}
            <button type="submit" className="sc-primary" disabled={!form.document}>Continue</button>
          </div>
        </form>
      ) : status === 'processing' ? (
        <div className="sc-card">
          <div className="sc-head"><h2 className="sc-h2">Bank account</h2><StatusPill status="processing" /></div>
          <p>The bank account information you submitted is being processed. Verification usually takes 1–2 business days (excluding holidays).</p>
          {detailsList}
        </div>
      ) : status === 'linked' ? (
        <div className="sc-card">
          <div className="sc-head"><h2 className="sc-h2">Bank account</h2><StatusPill status="linked" /></div>
          <p>Your bank account is linked — payouts are sent here.</p>
          {detailsList}
          <div className="ss-actions"><button type="button" className="seller-btn ghost" onClick={() => { if (window.confirm('Changing your bank account pauses payout requests until the new account is verified (1–2 business days). Continue?')) openForm() }}>Change bank account</button></div>
        </div>
      ) : status === 'failed' ? (
        <div className="sc-card">
          <div className="sc-head"><h2 className="sc-h2">Bank account</h2><StatusPill status="failed" /></div>
          <p>The bank document you provided failed verification{note ? `: ${note}` : '.'} Please provide a bank document for verification again.</p>
          {docRequirements}
          <div className="ss-actions"><button type="button" className="sc-primary" onClick={openForm}>Verify my bank account</button></div>
        </div>
      ) : (
        <div className="sc-card">
          <p>Add your bank account to request payment. NexTech verifies it within 1–2 business days.</p>
          <button type="button" className="sc-primary" onClick={openForm}>Set up</button>
        </div>
      )}
      <SupportLine onSupport={onSupport} />

      {confirming && form && (
        <div className="ss-overlay" role="presentation" onClick={() => setConfirming(false)}>
          <div className="ss-modal" onClick={(e) => e.stopPropagation()}>
            <h2 className="sc-h2">Confirm your bank account information</h2>
            <p className="ob-secure">🔒 All data is safeguarded.</p>
            <dl className="ob-facts">
              <dt>Bank location</dt><dd>{countryName(data.country)}</dd>
              <dt>Account holder&rsquo;s name</dt><dd>{form.holder_name}</dd>
              <dt>Bank</dt><dd>{form.bank_name}</dd>
              <dt>{bankCfg.code_label}</dt><dd>{form.bank_code}</dd>
              <dt>{bankCfg.account_label ?? 'Account number'}</dt><dd>{form.account_number}</dd>
              <dt>Bank document</dt><dd>{form.document?.name} · issued {form.document_issued_on}</dd>
            </dl>
            <div className="ss-actions">
              <button type="button" className="seller-btn ghost" onClick={() => setConfirming(false)}>Cancel</button>
              <button type="button" className="sc-primary" onClick={async () => {
                const ok = await submit('/seller/onboarding/bank', { holder_name: form.holder_name, bank_name: form.bank_name, bank_code: form.bank_code, account_number: form.account_number, document_path: form.document.path, document_name: form.document.name, document_issued_on: form.document_issued_on }, 'Submitted — your bank account is being verified (usually 1–2 business days).')
                setConfirming(false)
                if (ok) { setForm(null); onChanged?.('processing') }
              }}>Confirm</button>
            </div>
          </div>
        </div>
      )}
    </>
  )
}
