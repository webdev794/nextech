import { useEffect, useState } from 'react'
import { mediaUrl } from './mediaUrl'
import './ProductDocuments.css'

// "Product guides and documents", like Temu's product page: sellers upload
// PDFs (user manual, quick-start guide, warranty card…), each with a name and
// language. Buyers open one link that shows a popup with a dropdown to pick
// the document, a preview, and a download button.

const LANGUAGES = ['English', 'Spanish', 'French', 'German', 'Italian', 'Portuguese', 'Chinese', 'Japanese', 'Korean', 'Arabic', 'Hindi']
const MAX_DOCS = 10
const MAX_BYTES = 20 * 1048576

function size(n) {
  if (!n) return ''
  return n < 1048576 ? `${Math.max(1, Math.round(n / 1024))} KB` : `${(n / 1048576).toFixed(1)} MB`
}

const label = (d) => (d.language ? `${d.title} (${d.language})` : d.title)

/** Seller Center → Add product: upload and name the PDFs. `onUpload(file)` resolves to { url, size_bytes }. */
export function ProductDocumentsEditor({ value, onChange, onUpload }) {
  const docs = value ?? []
  const [busy, setBusy] = useState(false)
  const [msg, setMsg] = useState('')
  const set = (i, patch) => onChange(docs.map((d, j) => (j === i ? { ...d, ...patch } : d)))

  async function add(file) {
    if (!file) return
    setMsg('')
    if (file.type !== 'application/pdf' && !/\.pdf$/i.test(file.name)) { setMsg('Documents must be PDF files.'); return }
    if (file.size > MAX_BYTES) { setMsg('Documents can be up to 20 MB.'); return }
    setBusy(true)
    try {
      const d = await onUpload(file)
      const title = file.name.replace(/\.pdf$/i, '').replace(/[_-]+/g, ' ').trim().slice(0, 80) || 'User manual'
      onChange([...docs, { title, language: 'English', url: d.url, size_bytes: d.size_bytes ?? file.size }])
    } catch (e) { setMsg(e.message) } finally { setBusy(false) }
  }

  return (
    <div className="pdoc-edit">
      <span className="wz-label">Product guides and documents <small className="sc-muted">optional — PDF user manuals, quick-start guides, warranty cards; add one per language</small></span>
      {docs.map((d, i) => (
        <div key={d.url} className="pdoc-edit-row">
          <span className="pdoc-icon" aria-hidden>PDF</span>
          <input value={d.title} maxLength={80} placeholder="Name, e.g. User manual" onChange={(e) => set(i, { title: e.target.value })} />
          <input value={d.language ?? ''} maxLength={40} list="pdoc-languages" placeholder="Language" onChange={(e) => set(i, { language: e.target.value })} />
          <a href={mediaUrl(d.url)} target="_blank" rel="noreferrer">View{d.size_bytes ? ` · ${size(d.size_bytes)}` : ''}</a>
          <button type="button" className="pdoc-del" aria-label="Remove document" onClick={() => onChange(docs.filter((_, j) => j !== i))}>✕</button>
        </div>
      ))}
      <datalist id="pdoc-languages">{LANGUAGES.map((l) => <option key={l} value={l} />)}</datalist>
      {docs.length < MAX_DOCS && <label className="pdoc-add">{busy ? 'Uploading…' : '+ Upload PDF'}<input type="file" accept="application/pdf,.pdf" hidden disabled={busy} onChange={(e) => { add(e.target.files?.[0]); e.target.value = '' }} /></label>}
      {msg && <small className="wz-err">{msg}</small>}
    </div>
  )
}

/** Product page: one link that opens the documents popup. */
export function ProductDocuments({ documents }) {
  const docs = (documents ?? []).filter((d) => d?.url && d?.title)
  const [open, setOpen] = useState(false)
  const [pick, setPick] = useState(0)

  useEffect(() => {
    if (!open) return undefined
    const onKey = (e) => { if (e.key === 'Escape') setOpen(false) }
    window.addEventListener('keydown', onKey)
    return () => window.removeEventListener('keydown', onKey)
  }, [open])

  if (!docs.length) return null
  const doc = docs[Math.min(pick, docs.length - 1)]
  const href = mediaUrl(doc.url)
  return (
    <>
      <button type="button" className="pdoc-link" onClick={() => { setPick(0); setOpen(true) }}>
        <span className="pdoc-icon" aria-hidden>PDF</span>
        <span>Product guides and documents</span>
        <span aria-hidden className="pdoc-caret">&rsaquo;</span>
      </button>
      {open && (
        <div className="overlay" role="presentation" onClick={() => setOpen(false)}>
          <div className="pdoc-modal" role="dialog" aria-modal="true" aria-labelledby="pdoc-title" onClick={(e) => e.stopPropagation()}>
            <button className="close-button" type="button" onClick={() => setOpen(false)} aria-label="Close">x</button>
            <h2 id="pdoc-title">Product guides and documents</h2>
            <select value={pick} onChange={(e) => setPick(Number(e.target.value))} aria-label="Choose a document">
              {docs.map((d, i) => <option key={d.url} value={i}>{label(d)}</option>)}
            </select>
            <iframe className="pdoc-frame" key={href} src={href} title={label(doc)} />
            <div className="pdoc-actions">
              <a href={href} target="_blank" rel="noreferrer">Open in new tab</a>
              <a className="pdoc-download" href={href} download={`${label(doc)}.pdf`}>Download{doc.size_bytes ? ` (${size(doc.size_bytes)})` : ''}</a>
            </div>
          </div>
        </div>
      )}
    </>
  )
}
