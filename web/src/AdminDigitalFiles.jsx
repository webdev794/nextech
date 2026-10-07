import { useCallback, useEffect, useState } from 'react'
import { LinkStatus } from './SellerDigitalFiles'

// Admin → Products → Files: what a digital product delivers — uploaded files
// (download to inspect), the seller's hosted links with their last check
// (re-check or open them), license-key stock and download settings.

const API_URL = import.meta.env.VITE_API_URL ?? 'http://127.0.0.1:8000/api'

const size = (b) => (!b ? '—' : b >= 1024 ** 3 ? `${(b / 1024 ** 3).toFixed(1)} GB` : b >= 1024 ** 2 ? `${(b / 1024 ** 2).toFixed(1)} MB` : `${Math.max(1, Math.round(b / 1024))} KB`)

export function AdminDigitalFiles({ product, authHeaders, onClose }) {
  const [data, setData] = useState(null)
  const [msg, setMsg] = useState('')
  const [busy, setBusy] = useState(null)

  const call = useCallback(async (path, method = 'GET') => {
    const res = await fetch(`${API_URL}/admin/products/${product.id}${path}`, { method, headers: { Accept: 'application/json', ...authHeaders() } })
    const json = await res.json().catch(() => ({}))
    if (!res.ok) throw new Error(json.message ?? 'Something went wrong.')
    return json.data
  }, [product.id, authHeaders])

  useEffect(() => { Promise.resolve().then(() => call('/digital').then(setData).catch((e) => setMsg(e.message))) }, [call])

  async function recheck(file) {
    setBusy(file.id); setMsg('')
    try { setData(await call(`/files/${file.id}/check`, 'POST')) } catch (e) { setMsg(e.message) } finally { setBusy(null) }
  }

  // Uploaded files are private: fetch with the admin's token, then save.
  async function download(file) {
    setBusy(file.id); setMsg('')
    try {
      const res = await fetch(`${API_URL}/admin/products/${product.id}/files/${file.id}/download`, { headers: authHeaders() })
      if (!res.ok) throw new Error('Could not download the file.')
      const url = URL.createObjectURL(await res.blob())
      const a = document.createElement('a')
      a.href = url; a.download = file.original_name || file.name; a.click()
      setTimeout(() => URL.revokeObjectURL(url), 10000)
    } catch (e) { setMsg(e.message) } finally { setBusy(null) }
  }

  const s = data?.settings
  return (
    <div className="admin-change-overlay" role="dialog" aria-modal="true" aria-label="Digital files" onClick={onClose}>
      <div className="admin-change-box admin-digital-box" onClick={(e) => e.stopPropagation()}>
        <h3>Files — {product.name}</h3>
        {!data ? <p className="muted">{msg || 'Loading…'}</p> : <>
          <p className="muted">Download limit: {s.download_limit === 0 ? 'unlimited' : `${s.download_limit} per unit bought`}{s.license_keys ? ` · License keys: ${data.keys_available} left, ${data.keys_assigned} given to buyers` : ''}</p>
          {s.instructions && <p className="muted">Instructions for buyers: {s.instructions}</p>}
          {data.files.length === 0 ? <p className="muted">No files or links yet.</p> : (
            <table className="admin-table">
              <thead><tr><th>Name buyers see</th><th>File / link</th><th>Size</th><th>Downloads</th><th></th></tr></thead>
              <tbody>
                {data.files.map((f) => (
                  <tr key={f.id}>
                    <td>{f.name}</td>
                    <td className="admin-td-name">{f.external_url ? <><a href={f.external_url} target="_blank" rel="noreferrer">{f.external_url}</a><LinkStatus file={f} /></> : f.original_name}</td>
                    <td>{size(f.size_bytes)}</td>
                    <td>{f.downloads}</td>
                    <td className="admin-actions">
                      {f.external_url
                        ? <button className="act ghost" type="button" disabled={busy === f.id} onClick={() => recheck(f)}>{busy === f.id ? 'Checking…' : 'Re-check'}</button>
                        : <button className="act ghost" type="button" disabled={busy === f.id} onClick={() => download(f)}>Download</button>}
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          )}
          {msg && <p className="muted">{msg}</p>}
        </>}
        <div className="admin-form-actions"><button className="act ghost" type="button" onClick={onClose}>Close</button></div>
      </div>
    </div>
  )
}
