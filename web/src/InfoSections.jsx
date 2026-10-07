import './InfoSections.css'

// Seller-written text sections on a product page — FAQs, Specifications,
// System requirements (e.g. Windows version and RAM a game needs) or their
// own heading. The seller fills simple text boxes; buyers see each as a card
// with its own soft background. FAQ lines starting "Q:" / "A:" are shown as
// question-and-answer pairs.

const SECTION_KINDS = [
  ['faq', 'FAQs', 'Q: Does it work offline?\nA: Yes, after the first activation.\n\nQ: How many PCs can I install it on?\nA: Up to 2.'],
  ['specs', 'Specifications', 'Developer: Starfield Games\nRelease: 2026\nSize: 18 GB\nModes: Single player, Online co-op'],
  ['requirements', 'System requirements', 'Minimum:\nOS: Windows 10 64-bit\nProcessor: Intel Core i5-8400\nRAM: 8 GB\nGraphics: GTX 1060 6 GB\nStorage: 20 GB free\n\nRecommended:\nRAM: 16 GB\nGraphics: RTX 2070'],
  ['custom', 'Your own section', 'Anything else buyers should know'],
]

export function InfoSectionsEditor({ value, onChange }) {
  const sections = value ?? []
  const set = (i, patch) => onChange(sections.map((s, j) => (j === i ? { ...s, ...patch } : s)))
  return (
    <div className="ins-edit">
      <span className="wz-label">Extra sections <small className="sc-muted">optional — shown on your product page, e.g. FAQs, specifications, system requirements</small></span>
      {sections.map((s, i) => {
        const kind = SECTION_KINDS.find(([k]) => k === s.kind) ?? SECTION_KINDS[3]
        return (
          <div key={i} className={`ins-edit-card ins-${s.kind}`}>
            <div className="ins-edit-head">
              <input value={s.title} maxLength={80} placeholder="Section heading" onChange={(e) => set(i, { title: e.target.value })} />
              <button type="button" disabled={i === 0} aria-label="Move up" onClick={() => onChange(sections.map((x, j) => (j === i - 1 ? sections[i] : j === i ? sections[i - 1] : x)))}>↑</button>
              <button type="button" className="ins-del" aria-label="Remove section" onClick={() => onChange(sections.filter((_, j) => j !== i))}>✕</button>
            </div>
            <textarea rows={6} maxLength={4000} value={s.body} placeholder={kind[2]} onChange={(e) => set(i, { body: e.target.value })} />
            {s.kind === 'faq' && <small className="sc-muted">Start questions with &ldquo;Q:&rdquo; and answers with &ldquo;A:&rdquo; — they&rsquo;re shown as pairs.</small>}
          </div>
        )
      })}
      {sections.length < 8 && (
        <div className="ins-add">
          {SECTION_KINDS.map(([kind, label]) => (
            <button key={kind} type="button" onClick={() => onChange([...sections, { kind, title: kind === 'custom' ? '' : label, body: '' }])}>+ {label}</button>
          ))}
        </div>
      )}
    </div>
  )
}

function FaqBody({ text }) {
  const pairs = []
  let current = null
  for (const raw of text.split(/\r?\n/)) {
    const line = raw.trim()
    if (/^q[:.)-]\s*/i.test(line)) { current = { q: line.replace(/^q[:.)-]\s*/i, ''), a: '' }; pairs.push(current) } else if (/^a[:.)-]\s*/i.test(line) && current) { current.a = line.replace(/^a[:.)-]\s*/i, '') } else if (line && current) { current.a += (current.a ? ' ' : '') + line }
  }
  if (!pairs.length) return <p className="ins-text">{text}</p>
  return <dl className="ins-qa">{pairs.map((p, i) => <div key={i}><dt>{p.q}</dt>{p.a && <dd>{p.a}</dd>}</div>)}</dl>
}

/** Product page: the seller's sections, each on its own background. */
export function InfoSections({ sections }) {
  const list = (sections ?? []).filter((s) => s?.title?.trim() && s?.body?.trim())
  if (!list.length) return null
  return (
    <div className="ins">
      {list.map((s, i) => (
        <section key={i} className={`ins-card ins-${s.kind}`}>
          <h2><span aria-hidden>{{ faq: '❓', specs: '📋', requirements: '🖥️', custom: '📌' }[s.kind] ?? '📌'}</span>{s.title}</h2>
          {s.kind === 'faq' ? <FaqBody text={s.body} /> : <p className="ins-text">{s.body}</p>}
        </section>
      ))}
    </div>
  )
}
