import { useState } from 'react'
import { mediaUrl } from './mediaUrl'
import './Personalization.css'

// Buyer photo personalization (backend App\Support\Personalization): the
// picker on a product page (upload photos + optional note before adding to
// cart), and the viewer that shows them on the order to the buyer, the
// seller and admin.

const API_URL = import.meta.env.VITE_API_URL ?? 'http://127.0.0.1:8000/api'

async function readJson(response) {
  const text = await response.text()
  try { return text ? JSON.parse(text) : {} } catch { return {} }
}

export function PersonalizationPicker({ settings, value, onChange, signedIn, onSignIn }) {
  const [busy, setBusy] = useState(false)
  const [msg, setMsg] = useState('')
  const photos = value?.photos ?? []
  const max = settings.max_photos ?? 1

  async function upload(files) {
    setMsg('')
    setBusy(true)
    const added = []
    for (const file of [...files].slice(0, max - photos.length)) {
      const form = new FormData()
      form.append('file', file)
      try {
        const res = await fetch(`${API_URL}/personalization-images`, { method: 'POST', headers: { Accept: 'application/json', Authorization: `Bearer ${localStorage.getItem('gdp_token')}` }, body: form })
        const data = await readJson(res)
        if (!res.ok) throw new Error(data.message ?? Object.values(data.errors ?? {})[0]?.[0] ?? 'Could not upload that photo.')
        added.push(data.data.url)
      } catch (e) { setMsg(e.message); break }
    }
    setBusy(false)
    if (added.length) onChange({ ...value, photos: [...photos, ...added] })
  }

  return (
    <div className="pz">
      <p className="pz-title">📷 Personalize it {settings.required ? <b className="pz-req">— photo required</b> : <span className="pz-opt">(optional)</span>}</p>
      {settings.instructions && <p className="pz-help">{settings.instructions}</p>}
      {!signedIn ? (
        <button type="button" className="pz-signin" onClick={onSignIn}>Sign in to upload your photo</button>
      ) : (
        <div className="pz-photos">
          {photos.map((src) => (
            <span key={src} className="pz-thumb">
              <img src={mediaUrl(src)} alt="Your photo" />
              <button type="button" aria-label="Remove photo" onClick={() => onChange({ ...value, photos: photos.filter((p) => p !== src) })}>✕</button>
            </span>
          ))}
          {photos.length < max && (
            <label className={`pz-add${busy ? ' busy' : ''}`}>
              {busy ? 'Uploading…' : `+ ${photos.length ? 'Add photo' : 'Upload photo'}`}
              <input type="file" accept="image/jpeg,image/png,image/webp,image/gif" multiple={max > 1} hidden disabled={busy} onChange={(e) => { upload(e.target.files); e.target.value = '' }} />
            </label>
          )}
          <small className="pz-count">{photos.length}/{max} · JPG or PNG, up to 15 MB each</small>
        </div>
      )}
      {settings.note_label && signedIn && (
        <label className="pz-note">{settings.note_label}
          <input maxLength={500} value={value?.note ?? ''} onChange={(e) => onChange({ ...value, note: e.target.value })} />
        </label>
      )}
      {msg && <p className="pz-msg">{msg}</p>}
    </div>
  )
}

/** The buyer's photos (and note) on an order line — with download links for the seller. */
export function PersonalizationView({ value, download = false }) {
  if (!value || (!(value.photos ?? []).length && !value.note)) return null
  return (
    <div className="pz-view">
      <span className="pz-view-label">📷 Buyer&rsquo;s {(value.photos ?? []).length === 1 ? 'photo' : 'photos'}</span>
      {(value.photos ?? []).map((src, i) => (
        <a key={src} href={mediaUrl(src)} target="_blank" rel="noreferrer" {...(download ? { download: `photo-${i + 1}` } : {})}><img src={mediaUrl(src)} alt={`Buyer photo ${i + 1}`} loading="lazy" /></a>
      ))}
      {value.note && <span className="pz-view-note">“{value.note}”</span>}
    </div>
  )
}
