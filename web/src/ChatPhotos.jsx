import { useState } from 'react'
import { mediaUrl } from './mediaUrl'
import './ChatPhotos.css'

const API_URL = import.meta.env.VITE_API_URL ?? 'http://127.0.0.1:8000/api'
const MAX_CHAT_PHOTOS = 4

// Upload photos for a support chat message (POST /support/attachments, any
// signed-in user). Resolves to the list of media URLs to send with the message.
async function uploadChatPhotos(files, token) {
  const urls = []
  for (const file of files) {
    const body = new FormData()
    body.append('file', file)
    const response = await fetch(`${API_URL}/support/attachments`, { method: 'POST', headers: { Accept: 'application/json', Authorization: `Bearer ${token}` }, body })
    const data = await response.json().catch(() => ({}))
    if (!response.ok) throw new Error(data.message ?? Object.values(data.errors ?? {})[0]?.[0] ?? 'Photo upload failed.')
    urls.push(data.data.url)
  }
  return urls
}

// Thumbnails for a message's photos; each opens full size in a new tab.
export function ChatPhotos({ urls }) {
  if (!urls?.length) return null
  return (
    <span className="chat-photos">
      {urls.map((url) => (
        <a key={url} href={mediaUrl(url)} target="_blank" rel="noreferrer" title="Open full size">
          <img src={mediaUrl(url)} alt="Attached photo" loading="lazy" />
        </a>
      ))}
    </span>
  )
}

// A "📷 Photos" button (multi-select) plus the pending thumbnails, each
// removable, shown above the message box until the message is sent.
export function ChatPhotoPicker({ photos, onChange, token, onError, disabled }) {
  const [busy, setBusy] = useState(false)

  async function add(fileList) {
    const files = [...(fileList ?? [])].slice(0, MAX_CHAT_PHOTOS - photos.length)
    if (!files.length) return
    setBusy(true)
    try {
      const urls = await uploadChatPhotos(files, token)
      onChange([...photos, ...urls])
    } catch (error) {
      onError?.(error.message)
    } finally {
      setBusy(false)
    }
  }

  return (
    <div className="chat-photo-picker">
      {photos.map((url) => (
        <span key={url} className="chat-photo-pending">
          <img src={mediaUrl(url)} alt="" />
          <button type="button" aria-label="Remove photo" onClick={() => onChange(photos.filter((u) => u !== url))}>&times;</button>
        </span>
      ))}
      {photos.length < MAX_CHAT_PHOTOS && (
        <label className={`chat-photo-add${busy || disabled ? ' busy' : ''}`}>
          {busy ? 'Uploading…' : `📷 ${photos.length ? 'Add more' : 'Add photos'}`}
          <input type="file" accept="image/*" multiple disabled={busy || disabled} onChange={(event) => { add(event.target.files); event.target.value = '' }} />
        </label>
      )}
      {photos.length > 0 && <span className="chat-photo-count">{photos.length}/{MAX_CHAT_PHOTOS}</span>}
    </div>
  )
}
