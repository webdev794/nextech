import { useEffect, useState } from 'react'
import { mediaUrl } from './mediaUrl'
import './Downloads.css'

// Account → Your downloads: every digital product the buyer bought, with
// its files (each fetched through a short-lived signed link), license keys
// and install instructions. Backend: DigitalDownloadController.

const API_URL = import.meta.env.VITE_API_URL ?? 'http://127.0.0.1:8000/api'
const auth = () => ({ Accept: 'application/json', Authorization: `Bearer ${localStorage.getItem('gdp_token')}` })

async function readJson(response) {
  const text = await response.text()
  try { return text ? JSON.parse(text) : {} } catch { return {} }
}

function size(n) {
  if (!n) return ''
  const u = ['B', 'KB', 'MB', 'GB']
  let v = n
  let i = 0
  while (v >= 1024 && i < u.length - 1) { v /= 1024; i += 1 }
  return `${v.toFixed(i && v < 10 ? 1 : 0)} ${u[i]}`
}

export function MyDownloads({ onProduct, onShop }) {
  const [rows, setRows] = useState(null)
  const [msg, setMsg] = useState('')
  const [copied, setCopied] = useState('')

  const load = () => fetch(`${API_URL}/downloads`, { headers: auth() }).then(readJson).then((d) => setRows(d.data ?? [])).catch(() => setRows([]))
  useEffect(() => { load() }, [])

  async function download(row, file) {
    setMsg('')
    try {
      const res = await fetch(`${API_URL}/downloads/${row.order_item_id}/files/${file.id}/link`, { method: 'POST', headers: auth() })
      const data = await readJson(res)
      if (!res.ok) throw new Error(data.message ?? 'Could not start the download.')
      // Start the download without leaving the page.
      const a = document.createElement('a')
      a.href = data.data.url
      a.rel = 'noopener'
      document.body.appendChild(a)
      a.click()
      a.remove()
      setTimeout(load, 2000)
      setTimeout(load, 6000)
    } catch (e) { setMsg(e.message) }
  }

  async function copy(key) {
    try { await navigator.clipboard.writeText(key); setCopied(key); setTimeout(() => setCopied(''), 1500) } catch { /* ignore */ }
  }

  if (rows === null) return <p className="account-hint">Loading your downloads…</p>
  if (!rows.length) return <p className="account-hint">No downloads yet. Games, software, e-books and other digital products you buy appear here, ready to download right after payment. <button type="button" className="text-button" onClick={onShop}>Start shopping</button></p>

  return (
    <div className="dl">
      {msg && <p className="auth-message">{msg}</p>}
      {rows.map((row) => (
        <article key={row.order_item_id} className="dl-card">
          <header className="dl-head">
            {row.image_url && <img src={mediaUrl(row.image_url)} alt="" />}
            <div>
              <button type="button" className="dl-name" onClick={() => row.product_slug && onProduct(row.product_slug)}>{row.product_name}</button>
              <span className="dl-meta">Order #{row.order_id} · {new Date(row.purchased_at).toLocaleDateString()}</span>
            </div>
          </header>
          {!row.ready ? <p className="dl-wait">Your download is ready once payment is complete.</p> : <>
            {row.license_keys.length > 0 && (
              <div className="dl-keys">
                <span>{row.license_keys.length === 1 ? 'License key' : 'License keys'}</span>
                {row.license_keys.map((key) => <code key={key}>{key}<button type="button" onClick={() => copy(key)}>{copied === key ? 'Copied' : 'Copy'}</button></code>)}
              </div>
            )}
            <ul className="dl-files">
              {row.files.map((f) => {
                const left = row.download_limit ? row.download_limit - f.used : null
                return (
                  <li key={f.id}>
                    <span><b>{f.name}</b>{f.size_bytes ? <small> · {size(f.size_bytes)}</small> : null}{left !== null && <small> · {left > 0 ? `${left} download${left === 1 ? '' : 's'} left` : 'no downloads left'}</small>}</span>
                    <button type="button" className="checkout-button dl-btn" disabled={left !== null && left <= 0} onClick={() => download(row, f)}>⬇ Download</button>
                  </li>
                )
              })}
              {!row.files.length && <li className="dl-wait">The seller hasn&rsquo;t added the file yet — contact support if it doesn&rsquo;t appear soon.</li>}
            </ul>
            {row.instructions && <p className="dl-help"><b>How to install / activate:</b> {row.instructions}</p>}
          </>}
        </article>
      ))}
    </div>
  )
}
