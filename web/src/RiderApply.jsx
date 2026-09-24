import { useCallback, useEffect, useState } from 'react'

const API_URL = import.meta.env.VITE_API_URL ?? 'http://127.0.0.1:8000/api'

const VEHICLES = [['bicycle', 'Bicycle'], ['scooter', 'Scooter'], ['motorbike', 'Motorbike'], ['car', 'Car']]
const EMPTY = { phone: '', home_address: '', home_lat: null, home_lng: null, store_id: '', vehicle_type: 'scooter', license_number: '', license_document_path: '' }

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

  async function uploadLicense(file) {
    if (!file) return
    setUploading(true)
    setMessage('')
    try {
      const body = new FormData()
      body.append('file', file)
      body.append('kind', 'license_document')
      const response = await fetch(`${API_URL}/seller/kyc-document`, { method: 'POST', headers: headers(), body })
      const data = await readJson(response)
      if (!response.ok) throw new Error(data.message ?? Object.values(data.errors ?? {})[0]?.[0] ?? 'Upload failed.')
      setForm((f) => ({ ...f, license_document_path: data.data.path }))
    } catch (error) { setMessage(error.message) } finally { setUploading(false) }
  }

  async function submit(event) {
    event.preventDefault()
    setMessage('')
    if (!form.store_id) { setMessage('Pick the store you want to deliver for.'); return }
    setBusy(true)
    try {
      const payload = { ...form, store_id: Number(form.store_id) }
      if (payload.vehicle_type === 'bicycle') { payload.license_number = null; payload.license_document_path = null }
      const response = await fetch(`${API_URL}/rider-application`, { method: 'POST', headers: headers(true), body: JSON.stringify(payload) })
      const data = await readJson(response)
      if (!response.ok) throw new Error(data.message ?? Object.values(data.errors ?? {})[0]?.[0] ?? 'Could not send your application.')
      setReapply(false)
      await loadState()
    } catch (error) { setMessage(error.message) } finally { setBusy(false) }
  }

  if (!state) return <div className="admin-gate"><p>Loading&hellip;</p></div>

  const app = state.application
  const showForm = !app || (app.status === 'rejected' && reapply)
  const needsLicense = form.vehicle_type !== 'bicycle'

  return (
    <div className="admin-gate rider-apply-gate">
      <div className="admin-gate-card rider-apply-card">
        <h1>Deliver with NexTech</h1>

        {app?.status === 'pending' && (
          <>
            <p className="rider-apply-status pending">Your application to deliver for <strong>{app.store?.name ?? 'your chosen store'}</strong> is being reviewed. We&rsquo;ll switch you into the rider app as soon as it&rsquo;s approved.</p>
            <p className="admin-gate-sub">Submitted {new Date(app.created_at).toLocaleDateString()}.</p>
          </>
        )}

        {app?.status === 'rejected' && !reapply && (
          <>
            <p className="rider-apply-status rejected">Your application wasn&rsquo;t approved{app.rejection_reason ? `: ${app.rejection_reason}` : '.'}</p>
            <button type="button" className="rider-apply-btn" onClick={() => { setForm({ ...EMPTY, phone: app.phone ?? '', home_address: app.home_address ?? '', home_lat: app.home_lat, home_lng: app.home_lng, vehicle_type: app.vehicle_type ?? 'scooter' }); setReapply(true) }}>Apply again</button>
          </>
        )}

        {showForm && (
          <form className="rider-apply-form" onSubmit={submit}>
            <p className="admin-gate-sub">Earn per delivery from a store near you. Pick your store, tell us how you get around, and an admin will review your application.</p>

            <label>Phone<input required type="tel" value={form.phone} onChange={(event) => setForm({ ...form, phone: event.target.value })} /></label>
            <label>Home address<input required value={form.home_address} placeholder="Street, city, ZIP" onChange={(event) => setForm({ ...form, home_address: event.target.value, home_lat: null, home_lng: null })} /></label>
            <div className="rider-apply-row">
              <button type="button" className="rider-apply-btn ghost" disabled={locating} onClick={locateMe}>{locating ? 'Locating…' : '📍 Use my location'}</button>
              <button type="button" className="rider-apply-btn ghost" disabled={!form.home_address.trim()} onClick={() => loadStores({ address: form.home_address.trim() })}>Find stores near this address</button>
            </div>

            <fieldset className="rider-apply-stores">
              <legend>Store to deliver for</legend>
              {stores.length === 0 && <p className="admin-gate-sub">No stores available yet.</p>}
              {stores.map((store) => (
                <label key={store.id} className={String(form.store_id) === String(store.id) ? 'active' : ''}>
                  <input type="radio" name="store" value={store.id} checked={String(form.store_id) === String(store.id)} onChange={() => setForm({ ...form, store_id: store.id })} />
                  <span>
                    <strong>{store.name}</strong>
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
                <label>Licence photo or PDF
                  <input type="file" accept="image/*,application/pdf" disabled={uploading} onChange={(event) => uploadLicense(event.target.files?.[0])} />
                </label>
                {form.license_document_path && <p className="admin-gate-sub">✓ Licence uploaded.</p>}
              </>
            )}

            <button type="submit" disabled={busy || uploading || (needsLicense && !form.license_document_path)}>{busy ? 'Sending…' : 'Send application'} &rarr;</button>
          </form>
        )}

        {message && <p className="admin-gate-msg">{message}</p>}
        <button type="button" className="admin-gate-link" onClick={onSignOut}>Sign out</button>
      </div>
    </div>
  )
}

