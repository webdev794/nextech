import { createRoot } from 'react-dom/client'
import { ChatWindow } from './ChatDock'
import { brandName } from './useBranding'

// Pops a chat out into an always-on-top window (Chrome / Edge Document
// Picture-in-Picture), so it floats over other tabs and apps. Returns false
// where the browser doesn't support it; onClosed runs when it's closed.
export async function openChatOnTop(props, onClosed) {
  if (!('documentPictureInPicture' in window)) return false
  try {
    const pip = await window.documentPictureInPicture.requestWindow({ width: 380, height: 320 })
    for (const sheet of [...document.styleSheets]) {
      try {
        const style = pip.document.createElement('style')
        style.textContent = [...sheet.cssRules].map((rule) => rule.cssText).join('\n')
        pip.document.head.appendChild(style)
      } catch {
        if (sheet.href) { const link = pip.document.createElement('link'); link.rel = 'stylesheet'; link.href = sheet.href; pip.document.head.appendChild(link) }
      }
    }
    pip.document.title = `${props.chat.name} — ${brandName()} chat`
    pip.document.body.style.margin = '0'
    const host = pip.document.createElement('div')
    host.className = 'chatdock-popup'
    pip.document.body.appendChild(host)
    const root = createRoot(host)
    root.render(<ChatWindow popup {...props} onClose={() => pip.close()} onToggle={() => {}} />)
    pip.addEventListener('pagehide', () => { root.unmount(); onClosed?.() })
    return true
  } catch { return false }
}
