import { useCallback, useEffect, useState } from 'react'

// Seller Center → Add product → a digital product's download files and
// license keys (backend SellerDigitalController). Big files go up in 5 MB
// chunks so games and installers get past the server's upload limit; a
// seller-hosted link works too. Files stay private — buyers only reach them
// through signed links from "Your downloads" after paying.

const API_URL = import.meta.env.VITE_API_URL ?? 'http://127.0.0.1:8000/api'

async function readJson(response) {
  const text = await response.text()
  try { return text ? JSON.parse(text) : {} } catch { return {} }
}

function formatBytes(n) {
  if (n == null) return ''
  if (n < 1024) return `${n} B`
  const units = ['KB', 'MB', 'GB']
  let v = n / 1024
  let i = 0
  while (v >= 1024 && i < units.length - 1) { v /= 1024; i += 1 }
  return `${v.toFixed(v < 10 ? 1 : 0)} ${units[i]}`
}

export function DigitalFiles({ headers, productId, licenseKeys }) {
  const [data, setData] = useState(null)
  const [msg, setMsg] = useState('')
  const [progress, setProgress] = useState(null) // { name, pct }
  const [link, setLink] = useState({ name: '', url: '' })
  const [keys, setKeys] = useState('')

  const call = useCallback(async (path, method = 'GET', body, isForm = false) => {
    const res = await fetch(`${API_URL}/seller/products/${productId}${path}`, { method, headers: { ...headers(), ...(body && !isForm ? { 'Content-Type': 'application/json' } : {}) }, body: body ? (isForm ? body : JSON.stringify(body)) : undefined })
    const json = await readJson(res)
    if (!res.ok) throw new Error(json.message ?? Object.values(json.errors ?? {})[0]?.[0] ?? 'Something went wrong.')
    return json
  }, [headers, productId])

  useEffect(() => { Promise.resolve().then(() => call('/digital').then((d) => setData(d.data)).catch((e) => setMsg(e.message))) }, [call])

  async function upload(file) {
    if (!file || !data) return
    if (file.size > data.max_file_bytes) { setMsg(`Files can be up to ${formatBytes(data.max_file_bytes)} — for bigger ones, add a download link instead.`); return }
    if (data.max_product_bytes && (data.used_bytes ?? 0) + file.size > data.max_product_bytes) { setMsg(`This product's uploads can total ${formatBytes(data.max_product_bytes)} (${formatBytes(data.used_bytes ?? 0)} used) — remove a file, or add a download link instead.`); return }
    setMsg('')
    const size = data.chunk_bytes
    const total = Math.max(1, Math.ceil(file.size / size))
    const uploadId = `u${Date.now().toString(36)}${Math.random().toString(36).slice(2, 10)}`
    try {
      for (let i = 0; i < total; i += 1) {
        const form = new FormData()
        form.append('upload_id', uploadId)
        form.append('index', String(i))
        form.append('total', String(total))
        form.append('name', file.name)
        form.append('chunk', file.slice(i * size, (i + 1) * size), 'chunk')
        setProgress({ name: file.name, pct: Math.round((i / total) * 100) })
        const json = await call('/files/chunk', 'POST', form, true)
        if (i === total - 1) setData(json.data)
      }
      setMsg(`Uploaded ${file.name}.`)
    } catch (e) { setMsg(`Upload stopped: ${e.message}`) }
    setProgress(null)
  }

  async function act(fn, ok) {
    setMsg('')
    try { const json = await fn(); if (json?.data) setData(json.data); if (ok) setMsg(typeof ok === 'function' ? ok(json) : ok) } catch (e) { setMsg(e.message) }
  }

  if (!data) return <p className="sc-muted">{msg || 'Loading files…'}</p>
  return (
    <div className="dg">
      <h3 className="ss-sub">Download files <b className="wz-req" title="Required"> *</b></h3>
      <p className="sc-muted">What buyers download after paying — installers, game files, e-books, ZIPs… Up to {formatBytes(data.max_file_bytes)} each{data.max_product_bytes ? `, ${formatBytes(data.max_product_bytes)} in total (${formatBytes(data.used_bytes ?? 0)} used)` : ''}; files stay private. For bigger files (large games, ZIPs), add a download link to where you host them — e.g. Google Drive, Dropbox or your own server.</p>
      {data.files.length > 0 && (
        <table className="sc-table dg-files">
          <thead><tr><th>Name buyers see</th><th>File</th><th>Size</th><th></th></tr></thead>
          <tbody>
            {data.files.map((f) => (
              <tr key={f.id}>
                <td><input defaultValue={f.name} maxLength={160} onBlur={(e) => { if (e.target.value.trim() && e.target.value !== f.name) act(() => call(`/files/${f.id}`, 'PATCH', { name: e.target.value.trim() }), 'Renamed.') }} /></td>
                <td>{f.external_url ? <><a href={f.external_url} target="_blank" rel="noreferrer">Hosted link</a><LinkStatus file={f} /></> : f.original_name}</td>
                <td>{f.size_bytes ? formatBytes(f.size_bytes) : '—'}</td>
                <td className="sc-actions">{f.external_url && <button type="button" onClick={() => act(() => call(`/files/${f.id}/check`, 'POST'), 'Link checked.')}>Re-check</button>}<button type="button" className="danger" onClick={() => { if (window.confirm(`Remove "${f.name}"? Buyers who already bought it lose this download.`)) act(() => call(`/files/${f.id}`, 'DELETE'), 'Removed.') }}>Remove</button></td>
              </tr>
            ))}
          </tbody>
        </table>
      )}
      <div className="dg-add">
        {progress ? (
          <div className="dg-progress"><span>Uploading {progress.name}… {progress.pct}%</span><span className="dg-bar"><span style={{ width: `${progress.pct}%` }} /></span></div>
        ) : (
          <label className="sc-primary dg-upload">⬆ Upload a file<input type="file" hidden onChange={(e) => { upload(e.target.files?.[0]); e.target.value = '' }} /></label>
        )}
        <p className="sc-muted dg-or"><b>Or add a download link</b> instead of uploading — Google Drive, Dropbox or your own server. Buyers only get it after paying, in Your downloads; make sure the link opens without asking them to sign in.</p>
        <form className="dg-link" onSubmit={(e) => { e.preventDefault(); act(() => call('/files/link', 'POST', { name: link.name.trim(), external_url: link.url.trim() }), 'Link added.').then(() => setLink({ name: '', url: '' })) }}>
          <input placeholder="Name (e.g. Mac version)" value={link.name} maxLength={160} required onChange={(e) => setLink({ ...link, name: e.target.value })} />
          <input type="url" placeholder="https://… your download link" value={link.url} required onChange={(e) => setLink({ ...link, url: e.target.value })} />
          <button type="submit">Add link</button>
        </form>
      </div>

      {licenseKeys && (
        <>
          <h3 className="ss-sub">License keys</h3>
          <p className="sc-muted">Each buyer gets one key per copy, shown next to their download. Stock = keys not yet sold: <b>{data.keys_available} available</b> · {data.keys_assigned} sold.</p>
          <textarea className="dg-keys" rows={4} value={keys} placeholder={'One key per line, e.g.\nABCD-1234-EFGH-5678'} onChange={(e) => setKeys(e.target.value)} />
          <div className="ss-actions">
            <button type="button" className="sc-primary" disabled={!keys.trim()} onClick={() => act(() => call('/license-keys', 'POST', { keys }), (j) => `Added ${j.result.added} key${j.result.added === 1 ? '' : 's'}${j.result.skipped ? ` (${j.result.skipped} duplicate${j.result.skipped === 1 ? '' : 's'} skipped)` : ''}.`).then(() => setKeys(''))}>Add keys</button>
            {data.keys_available > 0 && <button type="button" className="danger" onClick={() => { if (window.confirm('Delete all unsold keys?')) act(() => call('/license-keys', 'DELETE'), 'Unsold keys deleted.') }}>Delete unsold keys</button>}
          </div>
        </>
      )}
      {msg && <p className="sc-alert warn"><span>{msg}</span></p>}
    </div>
  )
}

// A hosted link's last check: works (a file), opens a page, or broken.
export function LinkStatus({ file }) {
  if (!file.external_url || !file.link_status) return null
  const label = { ok: '✓ Link works', page: '⚠ Opens a page', broken: '✕ Link broken' }[file.link_status] ?? file.link_status
  return <span className={`dg-link-status ${file.link_status}`} title={file.link_checked_at ? `Checked ${new Date(file.link_checked_at).toLocaleString()}` : undefined}>{label}{file.link_note ? ` — ${file.link_note}` : ''}</span>
}
