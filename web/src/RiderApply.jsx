import { useCallback, useEffect, useState } from 'react'
import { brandName } from './useBranding'


const API_URL = import.meta.env.VITE_API_URL ?? 'http://127.0.0.1:8000/api'

const VEHICLES = [['bicycle', 'Bicycle'], ['scooter', 'Scooter'], ['motorbike', 'Motorbike'], ['car', 'Car']]
const DOC_LABELS = { license_document_path: 'licence', rc_document_path: 'vehicle RC', id_document_path: 'ID proof', education_document_path: 'education document', photo_path: 'photo' }
const EMPTY = { phone: '', email: '', date_of_birth: '', experience_months: '', home_address: '', home_lat: null, home_lng: null, store_ids: [], vehicle_type: 'scooter', own_vehicle: false, license_number: '', license_document_path: '', rc_document_path: '', id_document_path: '', education_document_path: '', photo_path: '', education: '', work_history: '', health_issue: false, health_details: '', consent_removal: false, signed_name: '', signed_place: '', payout_method: '', holder_name: '', bank_name: '', account_number: '', routing_number: '', payout_email: '' }
const age = (dob) => { if (!dob) return null; const d = new Date(dob); const n = new Date(); return n.getFullYear() - d.getFullYear() - (n < new Date(n.getFullYear(), d.getMonth(), d.getDate()) ? 1 : 0) }

async function readJson(response) {
  const text = await response.text()
  try { return text ? JSON.parse(text) : {} } catch { return {} }
}

/**
 * Shown on /rider to a signed-in account that isn't a rider yet: apply to
 * deliver for a store near you (nearest first, with how many riders each
 * already has), then wait for admin approval. Once approved, `onApproved`
 * switches into the rider console.
 */
export default function RiderApply({ token, onApproved, onSignOut }) {
  const [state, setState] = useState(null) // { is_rider, application }
  const [form, setForm] = useState(EMPTY)
  const [stores, setStores] = useState([])
  const [locating, setLocating] = useState(false)
  const [uploading, setUploading] = useState(false)
  const [busy, setBusy] = useState(false)
  const [message, setMessage] = useState('')
  const [reapply, setReapply] = useState(false)

  const headers = useCallback((json) => ({ Accept: 'application/json', Authorization: `Bearer ${token}`, ...(json ? { 'Content-Type': 'application/json' } : {}) }), [token])

  const loadState = useCallback(async () => {
    try {
      const data = await readJson(await fetch(`${API_URL}/rider-application`, { headers: headers() }))
      setState(data.data ?? { is_rider: false, application: null })
      if (data.data?.is_rider) onApproved()
    } catch { setMessage('Cannot reach the server.') }
  }, [headers, onApproved])

  const loadStores = useCallback(async (params = {}) => {
    const query = new URLSearchParams(Object.entries(params).filter(([, v]) => v != null && v !== ''))
    try {
      const data = await readJson(await fetch(`${API_URL}/rider-application/stores?${query}`, { headers: headers() }))
      setStores(data.data?.stores ?? [])
      if (data.data?.located) setForm((f) => ({ ...f, home_lat: f.home_lat ?? data.data.located.lat, home_lng: f.home_lng ?? data.data.located.lng }))
    } catch { /* keep last */ }
  }, [headers])

  useEffect(() => {
    Promise.resolve().then(() => { loadState(); loadStores() })
    // While pending, check back so approval switches over on its own.
    const t = setInterval(loadState, 30000)
    return () => clearInterval(t)
  }, [loadState, loadStores])

  function locateMe() {
    if (!navigator.geolocation) { setMessage('Location is not available in this browser — type your address instead.'); return }
    setLocating(true)
    navigator.geolocation.getCurrentPosition(
      (pos) => {
        const { latitude: lat, longitude: lng } = pos.coords
        setForm((f) => ({ ...f, home_lat: lat, home_lng: lng }))
        loadStores({ lat, lng }).finally(() => setLocating(false))
      },
      () => { setLocating(false); setMessage('Could not get your location — type your address and press “Find stores”.') },
      { enableHighAccuracy: false, timeout: 10000 },
    )
  }

  // Private uploads (only the applicant, the store and the seller deciding can open them).
  // Each document shows its own error, can be removed (×), and the same file can't be used twice.
  const [docs, setDocs] = useState({}) // field → { name, size, error }
  const [docKey, setDocKey] = useState(0) // resets the file pickers after Remove
  async function upload(file, kind, field) {
    if (!file) return
    setMessage('')
    if (!/\.(jpe?g|png|pdf)$/i.test(file.name)) { setDocs((d) => ({ ...d, [field]: { error: 'This file type isn’t accepted — use a JPG, PNG or PDF.' } })); return }
    const same = Object.entries(docs).find(([f, d]) => f !== field && d?.name && d.name === file.name && d.size === file.size)
    if (same) { setDocs((d) => ({ ...d, [field]: { error: `That’s the same file as your ${DOC_LABELS[same[0]]} — upload the right document.` } })); return }
    setUploading(true)
    try {
      const body = new FormData()
      body.append('file', file)
      body.append('kind', kind)
      const response = await fetch(`${API_URL}/seller/kyc-document`, { method: 'POST', headers: headers(), body })
      const data = await readJson(response)
      if (!response.ok) throw new Error(data.message ?? Object.values(data.errors ?? {})[0]?.[0] ?? 'Upload failed.')
      setForm((f) => ({ ...f, [field]: data.data.path }))
      setDocs((d) => ({ ...d, [field]: { name: file.name, size: file.size } }))
    } catch (error) { setDocs((d) => ({ ...d, [field]: { error: error.message } })) } finally { setUploading(false) }
  }
  const removeDoc = (field) => { setForm((f) => ({ ...f, [field]: '' })); setDocs((d) => ({ ...d, [field]: undefined })); setDocKey((k) => k + 1) }
  const docField = (label, kind, field, accept = 'image/jpeg,image/png,application/pdf') => (
    <div className={`rider-apply-doc${docs[field]?.error ? ' has-error' : ''}`}>
      <label>{label}
        <input key={`${field}-${docKey}`} type="file" accept={accept} disabled={uploading} onChange={(event) => upload(event.target.files?.[0], kind, field)} />
      </label>
      {form[field] && !docs[field]?.error && <p className="admin-gate-sub">✓ {docs[field]?.name ?? 'Uploaded'} <button type="button" className="rider-apply-x" title="Remove and choose another file" onClick={() => removeDoc(field)}>×</button></p>}
      {docs[field]?.error && <p className="admin-gate-msg">{docs[field].error} <button type="button" className="rider-apply-x" title="Clear" onClick={() => removeDoc(field)}>×</button></p>}
    </div>
  )

  // Everything still missing, in plain words (shown above the button instead of a dead button).
  function missing() {
    const m = []
    if (!form.phone.trim()) m.push('Phone')
    if (!form.email.trim()) m.push('Email')
    if (!form.date_of_birth) m.push('Date of birth')
    else if (tooYoung) m.push(`Age — riders must be at least ${minAge}`)
    if (!form.education.trim()) m.push('Highest education')
    if (form.experience_months === '') m.push('Work experience (0 if none)')
    if (!form.home_address.trim()) m.push('Home address')
    if (!form.store_ids.length) m.push('Tick at least one store')
    if (needsLicense && !form.license_number.trim()) m.push('Driving licence number')
    if (needsLicense && !form.license_document_path) m.push('Licence photo or PDF')
    if (needsLicense && !form.rc_document_path) m.push('Vehicle RC')
    if (!form.id_document_path) m.push('ID proof')
    if (!form.photo_path) m.push('Your photo')
    if (form.health_issue && !form.health_details.trim()) m.push('Health details')
    if (!form.own_vehicle) m.push('Tick “I have my own vehicle”')
    if (!form.consent_removal) m.push('Tick the agreement')
    if (form.signed_name.trim().length < 2) m.push('Your full name (signature)')
    if (form.signed_place.trim().length < 2) m.push('Your current town or city')
    Object.entries(docs).forEach(([f, d]) => { if (d?.error) m.push(`Fix: ${DOC_LABELS[f]}`) })
    return m
  }

  async function submit(event) {
    event.preventDefault()
    setMessage('')
    const todo = missing()
    if (todo.length) { setMessage(`Please fill in: ${todo.join(' · ')}`); return }
    setBusy(true)
    try {
      const payload = { ...form, store_ids: form.store_ids.map(Number), experience_months: Number(form.experience_months || 0), education_document_path: form.education_document_path || null, health_details: form.health_issue ? form.health_details : null }
      if (payload.vehicle_type === 'bicycle') payload.rc_document_path = null
      if (payload.vehicle_type === 'bicycle') { payload.license_number = null; payload.license_document_path = null }
      const response = await fetch(`${API_URL}/rider-application`, { method: 'POST', headers: headers(true), body: JSON.stringify(payload) })
      const data = await readJson(response)
      if (!response.ok) throw new Error(data.errors ? Object.values(data.errors).flat().join(' · ') : (data.message ?? 'Could not send your application.'))
      setReapply(false)
      await loadState()
    } catch (error) { setMessage(error.message) } finally { setBusy(false) }
  }

  if (!state) return <div className="admin-gate"><p>Loading&hellip;</p></div>

  const app = state.application
  const showForm = !app || (app.status === 'rejected' && reapply)
  const needsLicense = form.vehicle_type !== 'bicycle'
  const chosen = stores.find((s) => String(s.id) === String(form.store_ids[0]))
  // Stores in the applicant's order of priority (first ticked = first choice).
  const toggleStore = (id) => setForm((f) => ({ ...f, store_ids: f.store_ids.includes(id) ? f.store_ids.filter((x) => x !== id) : [...f.store_ids, id] }))
  const moveStore = (id, by) => setForm((f) => { const list = [...f.store_ids]; const i = list.indexOf(id); const j = i + by; if (i < 0 || j < 0 || j >= list.length) return f; [list[i], list[j]] = [list[j], list[i]]; return { ...f, store_ids: list } })
  const minAge = chosen?.min_age ?? 18
  const tooYoung = !!form.date_of_birth && age(form.date_of_birth) < minAge

  return (
    <div className="admin-gate rider-apply-gate">
      <div className="admin-gate-card rider-apply-card">
        <h1>Deliver with {brandName()}</h1>

        {['pending', 'seller_accepted'].includes(app?.status) && (
          <>
            <p className="rider-apply-status pending">Your application to deliver for <strong>{app.store?.name ?? 'your chosen store'}</strong> is being reviewed. We&rsquo;ll switch you into the rider app as soon as it&rsquo;s approved.</p>
            <p className="admin-gate-sub">Submitted {new Date(app.created_at).toLocaleDateString()}.</p>
          </>
        )}

        {app?.status === 'rejected' && !reapply && (
          <>
            <p className="rider-apply-status rejected">Your application wasn&rsquo;t approved{app.rejection_reason ? `: ${app.rejection_reason}` : '.'}</p>
            <button type="button" className="rider-apply-btn" onClick={() => { setForm({ ...EMPTY, phone: app.phone ?? '', home_address: app.home_address ?? '', home_lat: app.home_lat, home_lng: app.home_lng, vehicle_type: app.vehicle_type ?? 'scooter', email: app.email ?? '', date_of_birth: app.date_of_birth ? String(app.date_of_birth).slice(0, 10) : '', experience_months: app.experience_months ?? '' }); setReapply(true) }}>Apply again</button>
          </>
        )}

        {showForm && (
          <form className="rider-apply-form" onSubmit={submit}>
            <p className="admin-gate-sub">Earn per delivery from a store near you. Pick your store, tell us about yourself and how you get around; the store reviews your application. You use your <b>own vehicle</b> and pay its running costs.</p>

            <label>Phone<input required type="tel" value={form.phone} onChange={(event) => setForm({ ...form, phone: event.target.value })} /></label>
            <label>Email<input required type="email" value={form.email} onChange={(event) => setForm({ ...form, email: event.target.value })} /></label>
            <label>Date of birth <small>(at least {minAge} years old)</small><input required type="date" value={form.date_of_birth} max={new Date().toISOString().slice(0, 10)} onChange={(event) => setForm({ ...form, date_of_birth: event.target.value })} /></label>
            {tooYoung && <p className="admin-gate-msg">Riders must be at least {minAge} years old.</p>}
            <label>Highest education<input required maxLength="160" value={form.education} placeholder="e.g. High school, diploma, degree" onChange={(event) => setForm({ ...form, education: event.target.value })} /></label>
            <label>Past work <small>optional — jobs, how long</small><textarea rows={2} maxLength="2000" value={form.work_history} onChange={(event) => setForm({ ...form, work_history: event.target.value })} /></label>
            <label>Delivery or driving work experience (months) <small>0 if none</small><input required type="number" min="0" max="600" value={form.experience_months} onChange={(event) => setForm({ ...form, experience_months: event.target.value })} /></label>
            <label>Home address<input required value={form.home_address} placeholder="Street, city, ZIP" onChange={(event) => setForm({ ...form, home_address: event.target.value, home_lat: null, home_lng: null })} /></label>
            <div className="rider-apply-row">
              <button type="button" className="rider-apply-btn ghost" disabled={locating} onClick={locateMe}>{locating ? 'Locating…' : '📍 Use my location'}</button>
              <button type="button" className="rider-apply-btn ghost" disabled={!form.home_address.trim()} onClick={() => loadStores({ address: form.home_address.trim() })}>Find stores near this address</button>
            </div>

            <fieldset className="rider-apply-stores">
              <legend>Stores you can deliver for — tick all that suit you, first choice first</legend>
              {stores.length === 0 && <p className="admin-gate-sub">No store is hiring near you right now — check back later.</p>}
              {form.store_ids.length > 1 && <ol className="rider-apply-priority">{form.store_ids.map((id, i) => <li key={id}>{stores.find((s) => s.id === id)?.name ?? `Store ${id}`} <button type="button" disabled={i === 0} onClick={() => moveStore(id, -1)} aria-label="Higher priority">↑</button><button type="button" disabled={i === form.store_ids.length - 1} onClick={() => moveStore(id, 1)} aria-label="Lower priority">↓</button></li>)}</ol>}
              {stores.map((store) => (
                <label key={store.id} className={form.store_ids.includes(store.id) ? 'active' : ''}>
                  <input type="checkbox" value={store.id} checked={form.store_ids.includes(store.id)} onChange={() => toggleStore(store.id)} />
                  <span>
                    <strong>{store.name}</strong>{store.seller && <small>Deliver for {store.seller} (a seller on {brandName()})</small>}
                    {store.address && <small>{store.address}</small>}
                    <small>{store.distance_miles != null ? `${store.distance_miles} mi away · ` : ''}{store.riders_count} rider{store.riders_count === 1 ? '' : 's'}</small>
                  </span>
                </label>
              ))}
            </fieldset>

            <label>Vehicle
              <select value={form.vehicle_type} onChange={(event) => setForm({ ...form, vehicle_type: event.target.value })}>
                {VEHICLES.map(([value, label]) => <option key={value} value={value}>{label}</option>)}
              </select>
            </label>
            {needsLicense && (
              <>
                <label>Driving licence number<input required value={form.license_number} onChange={(event) => setForm({ ...form, license_number: event.target.value })} /></label>
                {docField('Licence photo or PDF', 'license_document', 'license_document_path')}
                {docField('Vehicle RC (registration certificate)', 'vehicle_rc', 'rc_document_path')}
              </>
            )}

            {docField('ID proof (photo or PDF) — must show your date of birth', 'id_document', 'id_document_path')}
            {docField('Education document (optional)', 'education_document', 'education_document_path')}
            {docField('Your photo (passport size, face clearly visible)', 'rider_photo', 'photo_path', 'image/jpeg,image/png')}
            <label className="rider-apply-check"><input type="checkbox" checked={form.health_issue} onChange={(event) => setForm({ ...form, health_issue: event.target.checked })} /> I have a health condition that could affect delivery work</label>
            {form.health_issue && <label>Tell us briefly<input required maxLength="300" value={form.health_details} onChange={(event) => setForm({ ...form, health_details: event.target.value })} /></label>}
            <label className="rider-apply-check"><input type="checkbox" checked={form.own_vehicle} onChange={(event) => setForm({ ...form, own_vehicle: event.target.checked })} /> I have my own vehicle and pay its fuel and running costs.</label>
            <label className="rider-apply-check"><input type="checkbox" checked={form.consent_removal} onChange={(event) => setForm({ ...form, consent_removal: event.target.checked })} /> I agree that if stores aren&rsquo;t available, or for bad behaviour, health or other issues, the seller or {brandName()} can remove me at any time. I&rsquo;ll give at least 30 days&rsquo; notice before I stop working; if I leave without notice, my final pay is settled only after the store checks my open orders and any cash I hold, and I may not be hired again. I work the hours the store sets, use my own vehicle, and I&rsquo;m paid per delivery as the store sets it, through {brandName()}. {brandName()}&rsquo;s decision is final on pay, working hours, days off and other matters; the seller I deliver for can also decide these for their store; I hand over any cash I collect the same day, and while I hold cash over the limit I can&rsquo;t take deliveries for any store.</label>

            {/* Last step: the store's terms, then sign with name + place. (How you're paid is added in the Rider app after approval.) */}
            {(
              <div className="rider-apply-terms">
                <h3>Before you join — the store&rsquo;s terms</h3>
                <ul>{(chosen?.terms ?? []).map((t) => <li key={t}>{t}</li>)}</ul>
                <p className="admin-gate-sub">Sign below to accept these terms and join.</p>
                <label>Your full name (signature)<input required minLength={2} maxLength={120} autoComplete="name" value={form.signed_name} onChange={(event) => setForm({ ...form, signed_name: event.target.value })} /></label>
                <label>Your current town or city<input required minLength={2} maxLength={120} value={form.signed_place} onChange={(event) => setForm({ ...form, signed_place: event.target.value })} /></label>
                <p className="admin-gate-sub">Date: {new Date().toLocaleDateString()}</p>
              </div>
            )}
            <p className="admin-gate-sub">How you want to be paid is added later, in the Rider app (Earnings → Payout method), once you&rsquo;re approved.</p>
            {message && <p className="admin-gate-msg rider-apply-missing">{message}</p>}
            <button type="submit" disabled={busy || uploading}>{busy ? 'Sending…' : 'Sign and submit to join'} &rarr;</button>
          </form>
        )}

        {message && !showForm && <p className="admin-gate-msg">{message}</p>}
        <button type="button" className="admin-gate-link" onClick={onSignOut}>Sign out</button>
      </div>
    </div>
  )
}

