// Minimal, dependency-free Markdown -> HTML for admin-authored content pages.
// Input is HTML-escaped first, then a small set of tags is reintroduced, so the
// output is safe to inject with dangerouslySetInnerHTML.

const escapeHtml = (text) => text
  .replace(/&/g, '&amp;')
  .replace(/</g, '&lt;')
  .replace(/>/g, '&gt;')

const inline = (text) => escapeHtml(text)
  .replace(/\*\*([^*\n]+)\*\*/g, '<strong>$1</strong>')
  .replace(/(^|[^*])\*([^*\n]+)\*/g, '$1<em>$2</em>')
  .replace(/`([^`\n]+)`/g, '<code>$1</code>')
  .replace(/\[([^\]\n]+)\]\(([^)\s]+)\)/g, (match, label, href) =>
    /^(https?:\/\/|\/|mailto:)/.test(href)
      ? `<a href="${href}" target="_blank" rel="noopener noreferrer">${label}</a>`
      : label)

export function renderMarkdown(source = '') {
  const lines = String(source).replace(/\r\n/g, '\n').split('\n')
  const html = []
  let paragraph = []
  let listType = null // 'ul' | 'ol'

  const flushParagraph = () => {
    if (paragraph.length) {
      html.push(`<p>${inline(paragraph.join(' '))}</p>`)
      paragraph = []
    }
  }
  const closeList = () => {
    if (listType) {
      html.push(`</${listType}>`)
      listType = null
    }
  }

  for (const raw of lines) {
    const line = raw.trimEnd()

    if (!line.trim()) { flushParagraph(); closeList(); continue }

    const heading = line.match(/^(#{1,4})\s+(.*)$/)
    if (heading) {
      flushParagraph(); closeList()
      const level = heading[1].length
      html.push(`<h${level}>${inline(heading[2])}</h${level}>`)
      continue
    }

    if (/^(-{3,}|\*{3,})$/.test(line.trim())) {
      flushParagraph(); closeList()
      html.push('<hr>')
      continue
    }

    const ordered = line.match(/^\s*\d+\.\s+(.*)$/)
    const unordered = line.match(/^\s*[-*]\s+(.*)$/)
    if (ordered || unordered) {
      flushParagraph()
      const want = ordered ? 'ol' : 'ul'
      if (listType !== want) { closeList(); html.push(`<${want}>`); listType = want }
      html.push(`<li>${inline((ordered ? ordered[1] : unordered[1]))}</li>`)
      continue
    }

    closeList()
    paragraph.push(line.trim())
  }

  flushParagraph()
  closeList()
  return html.join('\n')
}
