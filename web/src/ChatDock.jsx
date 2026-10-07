import { useCallback, useEffect, useRef, useState } from 'react'
import { createPortal } from 'react-dom'
import { OPEN_CHAT, chime, readStored, useChatSound, useDraggable, writeStored } from './chatDockUtils'
import { openChatOnTop } from './chatPip'
import { ChatPhotoPicker, ChatPhotos } from './ChatPhotos'
import './ChatDock.css'

// Messenger-style chat windows, docked bottom right — the one chat design
// for every surface (admin console, Seller Center, storefront, rider
// console). Several can be open side by side: a new one opens to the left of
// the others, and when there's no more room the oldest folds into a tab.
// Each window loads the latest page of messages, pages back with "Load
// earlier messages", checks for new ones every few seconds (also while
// folded) and chimes, badges and flashes on an incoming message.

const POLL_MS = 5000

function SpeakerIcon({ on }) {
  return (
    <svg viewBox="0 0 24 24" width="14" height="14" aria-hidden="true" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
      <path d="M4 9v6h4l5 4V5L8 9H4z" fill="currentColor" />
      {on ? <><path d="M16 9.5a3.5 3.5 0 0 1 0 5" /><path d="M18.5 7a7 7 0 0 1 0 10" /></> : <path d="M16 9l6 6M22 9l-6 6" />}
    </svg>
  )
}

// chat: { key, name, subtitle, minimized, max, unread }. adapter: { load(before),
// send(body) } (chatAdapter()). labels: who's who, by message `from`.
// dock: the dock's useDraggable() (docked / floating, dragging).
// Unread: messages from the other side newer than the last one the person
// read, remembered per chat in this browser (readKey). The window reports the
// count with onUnread(key, n) — it flashes until the person opens the chat,
// clicks into it or its text box, or replies. onRead(chat, page) then lets
// the page clear its own unread counts.
export function ChatWindow({ chat, adapter, labels = {}, readKey, onClose, onToggle, onMaximize, onUnread, onRead, onPopOut, onDetails, dock, popup = false }) {
  const [messages, setMessages] = useState(null)
  const [hasMore, setHasMore] = useState(false)
  const [loadingOlder, setLoadingOlder] = useState(false)
  const [draft, setDraft] = useState('')
  const [photos, setPhotos] = useState([])
  const [error, setError] = useState('')
  const [busy, setBusy] = useState(false)
  const [sound, toggleSound] = useChatSound()
  const lastSeen = useRef(null)
  const logRef = useRef(null)
  const keepScroll = useRef(null)
  const pagedBack = useRef(false)
  const minimized = chat.minimized
  // Latest chat / onRead without restarting the poll on every render.
  const latest = useRef({ chat, onRead })
  useEffect(() => { latest.current = { chat, onRead } })
  const storeRead = readKey ?? `nextech_chat_read:${chat.key}`
  const readUpTo = useRef(readStored(storeRead, null))
  const lastPage = useRef(null)

  // The person has seen everything loaded so far.
  const markRead = useCallback(() => {
    const page = lastPage.current
    if (!page) return
    const newest = page.messages.filter((m) => !m.mine && m.from !== 'system').reduce((max, m) => Math.max(max, m.id), 0)
    if (newest > (readUpTo.current ?? 0)) { readUpTo.current = newest; writeStored(storeRead, newest) }
    if (latest.current.chat.unread) onUnread?.(chat.key, 0)
    latest.current.onRead?.(latest.current.chat, page)
  }, [storeRead, chat.key, onUnread])

  // Merge the newest page into what's loaded, keeping older pages the user paged back to.
  const merge = useCallback((page) => {
    if (!pagedBack.current) setHasMore(page.has_more)
    setMessages((prev) => {
      const oldest = page.messages[0]?.id
      const kept = prev && oldest ? prev.filter((m) => m.id < oldest) : []
      return [...kept, ...page.messages]
    })
  }, [])

  const load = useCallback(() => {
    adapter.load().then((page) => {
      setError('')
      lastPage.current = page
      const incoming = page.messages.filter((m) => !m.mine && m.from !== 'system')
      const newest = incoming.length ? incoming[incoming.length - 1].id : 0
      // Never opened here before: whatever came after their own last message is unread.
      if (readUpTo.current === null) {
        readUpTo.current = page.messages.filter((m) => m.mine).reduce((max, m) => Math.max(max, m.id), 0)
        writeStored(storeRead, readUpTo.current)
      }
      if (lastSeen.current !== null && newest > lastSeen.current) {
        chime()
        if (document.visibilityState !== 'visible' && 'Notification' in window && Notification.permission === 'granted') {
          try { new Notification(`${chat.name}: new message`, { body: incoming[incoming.length - 1].body?.slice(0, 120) ?? '' }) } catch { /* ignore */ }
        }
      }
      lastSeen.current = Math.max(lastSeen.current ?? 0, newest)
      merge(page)
      const unread = incoming.filter((m) => m.id > readUpTo.current).length
      if (unread !== (latest.current.chat.unread ?? 0)) onUnread?.(chat.key, unread)
    }).catch((e) => setError(e.message))
  }, [adapter, chat.key, chat.name, storeRead, onUnread, merge])

  useEffect(() => {
    Promise.resolve().then(load)
    const t = setInterval(load, POLL_MS)
    return () => clearInterval(t)
  }, [load])

  // Opening a folded tab counts as reading it, and refreshes it straight away.
  const wasMinimized = useRef(minimized)
  useEffect(() => {
    if (wasMinimized.current && !minimized) Promise.resolve().then(() => { markRead(); load() })
    wasMinimized.current = minimized
  }, [minimized, load, markRead])

  async function loadOlder() {
    if (!messages?.length) return
    setLoadingOlder(true)
    try {
      const page = await adapter.load(messages[0].id)
      pagedBack.current = true
      if (logRef.current) keepScroll.current = logRef.current.scrollHeight - logRef.current.scrollTop
      setMessages((prev) => [...page.messages.filter((m) => m.id < (prev[0]?.id ?? Infinity)), ...prev])
      setHasMore(page.has_more)
    } catch (e) { setError(e.message) } finally { setLoadingOlder(false) }
  }

  // Stay at the bottom for new messages; keep the reading position when older ones are added on top.
  const newestId = messages?.length ? messages[messages.length - 1].id : 0
  const count = messages?.length ?? 0
  useEffect(() => {
    const log = logRef.current
    if (minimized || !log) return
    if (keepScroll.current !== null) { log.scrollTop = log.scrollHeight - keepScroll.current; keepScroll.current = null } else log.scrollTop = log.scrollHeight
  }, [newestId, count, minimized])

  async function send(event) {
    event.preventDefault()
    if (!draft.trim() && !photos.length) return
    setBusy(true)
    setError('')
    try {
      const page = await adapter.send(draft.trim(), photos)
      lastPage.current = page
      merge(page)
      markRead()
      setDraft('')
      setPhotos([])
    } catch (e) { setError(e.message) } finally { setBusy(false) }
  }

  const who = (m) => (m.mine ? 'You' : labels[m.from] ?? '')
  return (
    <div className={`chatdock-win${minimized ? ' min' : ''}${chat.max ? ' max' : ''}${chat.unread > 0 ? ' alert' : ''}${dock?.floating ? ' floating' : ''}`}
      onPointerDownCapture={() => { if (!minimized && chat.unread > 0) markRead() }} onFocusCapture={() => { if (!minimized && chat.unread > 0) markRead() }}>
      <div className="chatdock-head" onPointerDown={dock?.onPointerDown} onClick={() => { if (!dock?.wasDragged()) onToggle?.(chat.key) }} title={dock?.floating ? 'Drag to move' : undefined}>
        <span className="chatdock-title" title={[chat.name, chat.subtitle].filter(Boolean).join(' — ')}><b>{chat.name}</b>{chat.unread > 0 && <span className="chatdock-badge">{chat.unread}</span>}{!minimized && chat.subtitle && <small>{chat.subtitle}</small>}</span>
        <span className="chatdock-tools" onClick={(e) => e.stopPropagation()}>
          <button type="button" className={sound ? '' : 'off'} title={sound ? 'Sound on — click to mute' : 'Sound off — click to turn on'} aria-pressed={sound} onClick={toggleSound}><SpeakerIcon on={sound} /></button>
          {onDetails && !minimized && <button type="button" title="Details and actions" onClick={() => onDetails(chat)}>⋯</button>}
          {dock && !popup && !minimized && <button type="button" title={dock.floating ? 'Dock to the corner' : 'Float — then drag it anywhere'} onClick={() => dock.setFloating(!dock.floating)}>{dock.floating ? '⤓' : '⇱'}</button>}
          {onPopOut && !popup && !minimized && <button type="button" title="Pop out — stays on top of other windows" onClick={() => onPopOut(chat)}>⧉</button>}
          {!popup && <button type="button" title={minimized ? 'Open' : 'Minimize'} onClick={() => onToggle?.(chat.key)}>{minimized ? '▴' : '–'}</button>}
          {!minimized && !popup && onMaximize && <button type="button" title={chat.max ? 'Restore size' : 'Maximize'} onClick={() => onMaximize(chat.key)}>{chat.max ? '❐' : '□'}</button>}
          {onClose && <button type="button" title="Close" onClick={() => onClose(chat.key)}>×</button>}
        </span>
      </div>
      {!minimized && (
        <>
          <div className="chatdock-log" ref={logRef}>
            {hasMore && <button type="button" className="chatdock-older" disabled={loadingOlder} onClick={loadOlder}>{loadingOlder ? 'Loading…' : 'Load earlier messages'}</button>}
            {!messages ? <p className="chatdock-muted">{error || 'Loading…'}</p> : messages.length === 0 ? <p className="chatdock-muted">No messages yet — say hello.</p> : messages.map((m) => (
              <div key={m.id} className={`chatdock-msg ${m.from}${m.mine ? ' mine' : ''}`}>
                {m.body && <span>{m.body}</span>}
                <ChatPhotos urls={m.attachments} />
                <em>{who(m)}{who(m) ? ' · ' : ''}{new Date(m.created_at).toLocaleString([], { dateStyle: 'short', timeStyle: 'short' })}</em>
              </div>
            ))}
          </div>
          {error && messages && <p className="chatdock-error">{error}</p>}
          {adapter.token && <div className="chatdock-photos"><ChatPhotoPicker photos={photos} onChange={setPhotos} token={adapter.token()} onError={setError} disabled={busy} /></div>}
          <form className="chatdock-send" onSubmit={send}>
            <textarea rows="1" value={draft} placeholder={photos.length ? 'Add a note (optional)…' : 'Type a message…'} onChange={(e) => setDraft(e.target.value)} onKeyDown={(e) => { if (e.key === 'Enter' && !e.shiftKey) send(e) }} />
            <button type="submit" className="chatdock-sendbtn" disabled={busy || (!draft.trim() && !photos.length)}>Send</button>
          </form>
        </>
      )}
    </div>
  )
}

// How many windows fit side by side at this width (the rest fold into tabs).
const roomFor = () => Math.max(1, Math.min(3, Math.floor((window.innerWidth - 300) / 350)))

// The "Messages" tab: every chat this person has, a dot on ones with news.
function Launcher({ label, items, alerts, onOpen, dock }) {
  const [open, setOpen] = useState(false)
  return (
    <div className={`chatdock-win min chatdock-launcher${alerts.size ? ' alert' : ''}`}>
      {open && (
        <div className="chatdock-list">
          {items.length === 0 ? <p className="chatdock-muted">No conversations yet.</p> : items.map((item) => (
            <button key={item.key} type="button" onClick={() => { setOpen(false); onOpen(item) }}>
              <b>{item.name}{alerts.has(item.key) && <i className="chatdock-dot" />}</b>
              {item.subtitle && <small>{item.subtitle}</small>}
            </button>
          ))}
        </div>
      )}
      <div className="chatdock-head" onPointerDown={dock.onPointerDown} onClick={() => { if (!dock.wasDragged()) setOpen((v) => !v) }} title="Drag sideways to move to the other corner">
        <span className="chatdock-title"><b>💬 {label}</b>{alerts.size > 0 && <span className="chatdock-badge">{alerts.size}</span>}</span>
        <span className="chatdock-tools" onClick={(e) => e.stopPropagation()}>
          <button type="button" title={dock.side === 'left' ? 'Move chats to the bottom right' : 'Move chats to the bottom left'} onClick={() => dock.setSide(dock.side === 'left' ? 'right' : 'left')}>⇆</button>
          <button type="button" title={open ? 'Hide list' : 'Show conversations'} onClick={() => setOpen((v) => !v)}>{open ? '▾' : '▴'}</button>
        </span>
      </div>
    </div>
  )
}

// The dock. adapterFor(chat) → { adapter, labels, onDetails? } for a chat
// { key, kind, id, name }. launcher: { label, items } or { label, load() }
// — items { key, kind, id, name, subtitle, stamp }; a changed `stamp` means
// something new came in on it. initial: windows to start with the first
// time (e.g. a folded NexTech chat). Opening elsewhere: openChat(chat).
export function ChatDock({ storeKey, adapterFor, launcher, initial = [], onPopOutFallback, onRead }) {
  const [chats, setChats] = useState(() => readStored(storeKey, initial).map((c) => ({ ...c, unread: 0 })))
  const dock = useDraggable(`${storeKey}_pos`)
  const [adapters] = useState(() => new Map())
  const baseTitle = useRef(document.title)

  useEffect(() => { writeStored(storeKey, chats.map(({ key, kind, id, name, subtitle, minimized, max }) => ({ key, kind, id, name, subtitle, minimized, max }))) }, [storeKey, chats])


  // Launcher list, and which of its chats have news the person hasn't opened.
  const [loaded, setLoaded] = useState([])
  const items = launcher?.items ?? loaded
  const [seen, setSeen] = useState(() => readStored(`${storeKey}_seen`, null))
  const itemsRef = useRef(items)
  const openRef = useRef([])
  const openKeys = chats.filter((c) => !c.minimized).map((c) => c.key)
  useEffect(() => { itemsRef.current = items; openRef.current = openKeys })
  // Marks chats as seen at their current stamp. First load: what's already there isn't news.
  const markStamps = useCallback((list, keys) => setSeen((prev) => {
    const next = { ...(prev ?? Object.fromEntries(list.map((i) => [i.key, i.stamp]))) }
    list.forEach((i) => { if (keys.includes(i.key)) next[i.key] = i.stamp })
    return prev && JSON.stringify(next) === JSON.stringify(prev) ? prev : next
  }), [])
  const loadItems = launcher?.load
  useEffect(() => {
    if (!loadItems) return undefined
    let alive = true
    const tick = () => loadItems().then((list) => {
      if (!alive) return
      setLoaded(list)
      markStamps(list, openRef.current)
    }).catch(() => {})
    tick()
    const t = setInterval(tick, 20000)
    return () => { alive = false; clearInterval(t) }
  }, [loadItems, markStamps])
  const alerts = new Set(seen ? items.filter((i) => i.stamp && seen[i.key] !== i.stamp && !openKeys.includes(i.key)).map((i) => i.key) : [])
  useEffect(() => { if (seen) writeStored(`${storeKey}_seen`, seen) }, [storeKey, seen])
  const show = useCallback((chat) => {
    setChats((list) => {
      const rest = list.filter((c) => c.key !== chat.key)
      const existing = list.find((c) => c.key === chat.key)
      let next = [...rest, { max: false, unread: 0, ...existing, ...chat, minimized: false }]
      // No room? Fold the oldest open windows into tabs.
      const open = next.filter((c) => !c.minimized)
      const fold = new Set(open.slice(0, Math.max(0, open.length - roomFor())).map((c) => c.key))
      next = next.map((c) => (fold.has(c.key) ? { ...c, minimized: true, max: false } : c))
      return next.slice(-8)
    })
    markStamps(itemsRef.current, [chat.key])
    if ('Notification' in window && Notification.permission === 'default') Notification.requestPermission().catch(() => {})
  }, [markStamps])

  useEffect(() => {
    const onOpen = (event) => show(event.detail)
    window.addEventListener(OPEN_CHAT, onOpen)
    return () => window.removeEventListener(OPEN_CHAT, onOpen)
  }, [show])

  const alertCount = alerts.size
  const prevAlerts = useRef(0)
  useEffect(() => { if (alertCount > prevAlerts.current) chime(); prevAlerts.current = alertCount }, [alertCount])

  // Flash the tab title while anything is unread.
  const unread = chats.reduce((sum, c) => sum + (c.unread || 0), 0) + alertCount
  useEffect(() => {
    const base = baseTitle.current
    if (!unread) { document.title = base; return undefined }
    let on = false
    const t = setInterval(() => { on = !on; document.title = on ? `(${unread}) New message` : base }, 1200)
    return () => { clearInterval(t); document.title = base }
  }, [unread])

  const onUnread = useCallback((key, n) => setChats((list) => list.map((c) => (c.key === key && c.unread !== n ? { ...c, unread: n } : c))), [])
  const close = useCallback((key) => setChats((list) => list.filter((c) => c.key !== key)), [])
  const toggle = useCallback((key) => {
    const chat = chats.find((c) => c.key === key)
    if (chat?.minimized) show(chat)
    else setChats((list) => list.map((c) => (c.key === key ? { ...c, minimized: true } : c)))
  }, [chats, show])

  const setupOf = (chat) => {
    if (!adapters.has(chat.key)) adapters.set(chat.key, adapterFor(chat))
    return adapters.get(chat.key)
  }
  const popOut = async (chat) => {
    const setup = setupOf(chat)
    const onTop = await openChatOnTop({ chat: { ...chat, max: true, minimized: false, unread: 0 }, adapter: setup.adapter, labels: setup.labels })
    if (onTop || onPopOutFallback?.(chat)) close(chat.key)
  }

  if (!chats.length && !launcher) return null
  // Rendered into <body>: a transformed ancestor (page layouts) would pin a
  // fixed element to itself instead of the window's bottom-right corner.
  return createPortal(
    <div className={`chatdock${dock.side === 'left' ? ' left' : ''}`} style={dock.style}>
      {launcher && <Launcher label={launcher.label} items={items} alerts={alerts} onOpen={show} dock={dock} />}
      {chats.map((chat) => {
        const setup = setupOf(chat)
        return (
          <ChatWindow key={chat.key} chat={chat} adapter={setup.adapter} labels={setup.labels} dock={dock}
            onDetails={setup.onDetails} readKey={`${storeKey}:read:${chat.key}`} onUnread={onUnread} onRead={onRead} onClose={close} onToggle={toggle}
            onMaximize={(key) => setChats((list) => list.map((c) => (c.key === key ? { ...c, max: !c.max } : c)))}
            onPopOut={'documentPictureInPicture' in window || onPopOutFallback ? popOut : undefined} />
        )
      })}
    </div>,
    document.body,
  )
}
