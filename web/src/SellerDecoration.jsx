import { useCallback, useEffect, useState } from 'react'
import { mediaUrl } from './mediaUrl'
import { DecorationView, SectionView } from './StoreDecorationView'

// Seller Center -> My account -> Store decoration (modelled on Temu's):
// versions of the store page for desktop and mobile, each built from
// drag-and-drop sections on a canvas. Submitting checks for missing content
// (red pop-up), NexTech may spot-check it, and an approved version can be
// published — one live version per platform.

const API_URL = import.meta.env.VITE_API_URL ?? 'http://127.0.0.1:8000/api'

async function readJson(response) {
  const text = await response.text()
  try { return text ? JSON.parse(text) : {} } catch { return {} }
}

async function send(headers, path, method = 'GET', body) {
  const isForm = body instanceof FormData
  const response = await fetch(`${API_URL}${path}`, { method, headers: { ...headers(), ...(body && !isForm ? { 'Content-Type': 'application/json' } : {}) }, body: body ? (isForm ? body : JSON.stringify(body)) : undefined })
  const data = await readJson(response)
  if (!response.ok) {
    const error = new Error(data.message ?? Object.values(data.errors ?? {})[0]?.[0] ?? 'Something went wrong.')
    error.problems = data.problems
    throw error
  }
  return data
}

const SECTION_TYPES = [
  ['banner', 'Banner', 'Slideshow of large images', false],
  ['category', 'Category section', 'Your categories as tiles', false],
  ['products', 'Product section', 'Hand-picked products', false],
  ['video', 'Video section', 'Show the product in action', false],
  ['announcement', 'Announcement bar', 'A line of news or a promo', true],
  ['image_grid', 'Image grid', '2–4 linked images', true],
  ['auto_products', 'Auto product list', 'Best sellers, new, top rated…', true],
  ['countdown', 'Sale countdown', 'Timer for a sales event', true],
  ['brand_story', 'Brand story', 'Image with your story', true],
  ['spacer', 'Spacer', 'Breathing room', true],
]
const TYPE_LABEL = Object.fromEntries(SECTION_TYPES.map(([k, l]) => [k, l]))
const STATUS = { draft: ['Draft', 'hidden'], in_review: ['In review', 'pending'], approved: ['Approved', 'approved'], rejected: ['Rejected', 'rejected'] }
const uid = () => Math.random().toString(36).slice(2, 10)

function blank(type) {
  const base = { id: uid(), type, title: '' }
  switch (type) {
    case 'banner': return { ...base, slides: [{ image_url: '', link: null }], autoplay: true }
    case 'category': return { ...base, title: 'Shop by category', items: [] }
    case 'products': return { ...base, title: 'Featured products', product_ids: [], layout: 'grid' }
    case 'video': return { ...base, video_url: '', poster_url: '', caption: '' }
    case 'announcement': return { ...base, text: '', bg_color: '#1f2328', text_color: '#ffffff', link: null }
    case 'image_grid': return { ...base, columns: 2, tiles: [{ image_url: '', link: null }, { image_url: '', link: null }] }
    case 'auto_products': return { ...base, title: 'Best sellers', source: 'best_selling', limit: 8 }
    case 'countdown': return { ...base, title: 'Flash sale', subtitle: '', ends_at: '', bg_color: '#e5432c', product_ids: [] }
    case 'brand_story': return { ...base, title: 'Our story', text: '', image_url: '', image_side: 'left' }
    default: return { ...base, size: 'medium' }
  }
}

// The editor's raw section -> what the renderer shows (products / categories filled in).
function toDisplay(s, lookup) {
  const product = (id) => lookup.products.find((p) => p.id === id)
  const category = (id) => lookup.categories.find((c) => c.id === id)
  const link = (l) => {
    if (!l) return null
    if (l.type === 'product' && product(l.value)) return { type: 'product', slug: product(l.value).slug }
    if (l.type === 'category' && category(l.value)) return { type: 'category', name: category(l.value).name }
    return null
  }
  switch (s.type) {
    case 'banner': return { ...s, slides: s.slides.map((x) => ({ ...x, link: link(x.link) })) }
    case 'image_grid': return { ...s, tiles: s.tiles.map((x) => ({ ...x, link: link(x.link) })) }
    case 'announcement': return { ...s, link: link(s.link) }
    case 'category': return { ...s, items: s.items.map((it) => category(it.category_id) && { name: category(it.category_id).name, slug: category(it.category_id).slug, image_url: it.image_url || category(it.category_id).image_url }).filter(Boolean) }
    case 'products':
    case 'countdown': return { ...s, products: s.product_ids.map(product).filter(Boolean) }
    case 'auto_products': {
      const list = [...lookup.products]
      const sorted = s.source === 'on_sale' ? list.filter((p) => p.compare_at_price_cents > p.price_cents) : list
      return { ...s, products: sorted.slice(0, s.limit) }
    }
    default: return s
  }
}

// Image upload with the recommended size checked (aspect ratio within 3%).
function ImageField({ headers, label, value, spec, onChange, onError }) {
  const [busy, setBusy] = useState(false)
  async function pick(file) {
    if (!file) return
    if (file.size > 3 * 1048576) { onError('Images must be 3 MB or smaller.'); return }
    const url = URL.createObjectURL(file)
    const size = await new Promise((resolve) => { const img = new Image(); img.onload = () => resolve([img.width, img.height]); img.onerror = () => resolve(null); img.src = url })
    URL.revokeObjectURL(url)
    if (!size) { onError('Could not read that image.'); return }
    if (spec && Math.abs(size[0] / size[1] - spec[0] / spec[1]) / (spec[0] / spec[1]) > 0.03) { onError(`${label} should be ${spec[0]}×${spec[1]} px (same shape) — this one is ${size[0]}×${size[1]}.`); return }
    setBusy(true)
    try {
      const body = new FormData()
      body.append('file', file)
      onChange((await send(headers, '/seller/media', 'POST', body)).data.url)
    } catch (e) { onError(e.message) } finally { setBusy(false) }
  }
  return (
    <label>{label}{spec && <span className="de-spec">{spec[0]}×{spec[1]} px, up to 3 MB</span>}
      {value && <img className="de-thumb" src={mediaUrl(value)} alt="" />}
      <input type="file" accept="image/jpeg,image/png,image/webp" disabled={busy} onChange={(e) => { const f = e.target.files?.[0]; e.target.value = ''; pick(f) }} />
      {busy && <span className="de-spec">Uploading…</span>}
      {value && <button type="button" className="sc-link" onClick={() => onChange('')}>Remove image</button>}
    </label>
  )
}

function LinkField({ value, lookup, onChange }) {
  const type = value?.type ?? 'none'
  return (
    <label>Link to
      <select value={type} onChange={(e) => onChange(e.target.value === 'none' ? null : { type: e.target.value, value: e.target.value === 'product' ? lookup.products[0]?.id : lookup.categories[0]?.id })}>
        <option value="none">Nothing</option><option value="product">A product</option><option value="category">A category</option>
      </select>
      {type === 'product' && <select value={value.value ?? ''} onChange={(e) => onChange({ type, value: Number(e.target.value) })}>{lookup.products.map((p) => <option key={p.id} value={p.id}>{p.name}</option>)}</select>}
      {type === 'category' && <select value={value.value ?? ''} onChange={(e) => onChange({ type, value: Number(e.target.value) })}>{lookup.categories.map((c) => <option key={c.id} value={c.id}>{c.name}</option>)}</select>}
    </label>
  )
}

function ProductPicker({ ids, lookup, max, onChange }) {
  const [q, setQ] = useState('')
  const shown = lookup.products.filter((p) => !q.trim() || p.name.toLowerCase().includes(q.trim().toLowerCase()))
  return (
    <div className="de-item">
      <span className="de-spec">{ids.length}/{max} chosen · only live products can be shown</span>
      <input placeholder="Search your products" value={q} onChange={(e) => setQ(e.target.value)} />
      <div className="de-picker">{shown.map((p) => <label key={p.id}><input type="checkbox" checked={ids.includes(p.id)} disabled={!ids.includes(p.id) && ids.length >= max} onChange={(e) => onChange(e.target.checked ? [...ids, p.id] : ids.filter((x) => x !== p.id))} />{p.name}</label>)}
        {!lookup.products.length && <span className="de-spec">No live products yet.</span>}</div>
    </div>
  )
}

function SectionForm({ s, set, headers, lookup, spec, onError }) {
  const title = <label>Title (optional)<input maxLength="80" value={s.title} onChange={(e) => set({ title: e.target.value })} /></label>
  switch (s.type) {
    case 'banner':
      return <>{title}
        {s.slides.map((sl, i) => (
          <div className="de-item" key={i}>
            <b>Slide {i + 1}</b>
            <ImageField headers={headers} label="Image" spec={spec.banner} value={sl.image_url} onError={onError} onChange={(url) => set({ slides: s.slides.map((x, j) => (j === i ? { ...x, image_url: url } : x)) })} />
            <LinkField value={sl.link} lookup={lookup} onChange={(link) => set({ slides: s.slides.map((x, j) => (j === i ? { ...x, link } : x)) })} />
            {s.slides.length > 1 && <button type="button" className="sc-link" onClick={() => set({ slides: s.slides.filter((_, j) => j !== i) })}>Remove slide</button>}
          </div>
        ))}
        {s.slides.length < 6 && <button type="button" className="seller-btn ghost" onClick={() => set({ slides: [...s.slides, { image_url: '', link: null }] })}>+ Add slide</button>}
        <label className="sc-check"><input type="checkbox" checked={s.autoplay} onChange={(e) => set({ autoplay: e.target.checked })} /> Play slides automatically</label>
      </>
    case 'category':
      return <>{title}
        <div className="de-picker">{lookup.categories.map((c) => {
          const on = s.items.some((it) => it.category_id === c.id)
          return <label key={c.id}><input type="checkbox" checked={on} onChange={(e) => set({ items: e.target.checked ? [...s.items, { category_id: c.id, image_url: '' }] : s.items.filter((it) => it.category_id !== c.id) })} />{c.name}</label>
        })}{!lookup.categories.length && <span className="de-spec">Your store has no live categories yet.</span>}</div>
        {s.items.map((it, i) => <ImageField key={it.category_id} headers={headers} label={`Image for ${lookup.categories.find((c) => c.id === it.category_id)?.name ?? 'category'} (optional)`} spec={spec.category} value={it.image_url} onError={onError} onChange={(url) => set({ items: s.items.map((x, j) => (j === i ? { ...x, image_url: url } : x)) })} />)}
      </>
    case 'products':
      return <>{title}
        <label>Layout<select value={s.layout} onChange={(e) => set({ layout: e.target.value })}><option value="grid">Grid</option><option value="carousel">Carousel (scrolls sideways)</option></select></label>
        <ProductPicker ids={s.product_ids} lookup={lookup} max={20} onChange={(ids) => set({ product_ids: ids })} />
      </>
    case 'video':
      return <>{title}
        <label>Video (MP4/WebM/MOV, up to 100 MB)
          {s.video_url && <video className="de-thumb" src={mediaUrl(s.video_url)} muted />}
          <input type="file" accept="video/mp4,video/webm,video/quicktime" onChange={async (e) => {
            const f = e.target.files?.[0]; e.target.value = ''
            if (!f) return
            if (f.size > 100 * 1048576) { onError('Videos must be 100 MB or smaller.'); return }
            try { const body = new FormData(); body.append('file', f); set({ video_url: (await send(headers, '/seller/product-video', 'POST', body)).data.url }) } catch (err) { onError(err.message) }
          }} />
        </label>
        <ImageField headers={headers} label="Cover image (optional)" spec={spec.poster} value={s.poster_url} onError={onError} onChange={(url) => set({ poster_url: url })} />
        <label>Caption (optional)<input maxLength="200" value={s.caption} onChange={(e) => set({ caption: e.target.value })} /></label>
      </>
    case 'announcement':
      return <>
        <label>Text<input maxLength="160" value={s.text} onChange={(e) => set({ text: e.target.value })} placeholder="e.g. Free shipping on orders this week" /></label>
        <label>Background<input type="color" value={s.bg_color} onChange={(e) => set({ bg_color: e.target.value })} /></label>
        <label>Text colour<input type="color" value={s.text_color} onChange={(e) => set({ text_color: e.target.value })} /></label>
        <LinkField value={s.link} lookup={lookup} onChange={(link) => set({ link })} />
      </>
    case 'image_grid':
      return <>{title}
        <label>Columns<select value={s.columns} onChange={(e) => set({ columns: Number(e.target.value) })}>{[2, 3, 4].map((n) => <option key={n} value={n}>{n}</option>)}</select></label>
        {s.tiles.map((t, i) => (
          <div className="de-item" key={i}>
            <b>Tile {i + 1}</b>
            <ImageField headers={headers} label="Image" spec={spec.tile} value={t.image_url} onError={onError} onChange={(url) => set({ tiles: s.tiles.map((x, j) => (j === i ? { ...x, image_url: url } : x)) })} />
            <LinkField value={t.link} lookup={lookup} onChange={(link) => set({ tiles: s.tiles.map((x, j) => (j === i ? { ...x, link } : x)) })} />
            {s.tiles.length > 2 && <button type="button" className="sc-link" onClick={() => set({ tiles: s.tiles.filter((_, j) => j !== i) })}>Remove tile</button>}
          </div>
        ))}
        {s.tiles.length < 8 && <button type="button" className="seller-btn ghost" onClick={() => set({ tiles: [...s.tiles, { image_url: '', link: null }] })}>+ Add tile</button>}
      </>
    case 'auto_products':
      return <>{title}
        <label>Show<select value={s.source} onChange={(e) => set({ source: e.target.value })}><option value="best_selling">Best sellers</option><option value="newest">Newest arrivals</option><option value="top_rated">Top rated</option><option value="on_sale">On sale</option></select></label>
        <label>How many<input type="number" min="4" max="20" value={s.limit} onChange={(e) => set({ limit: Math.max(4, Math.min(20, Number(e.target.value) || 8)) })} /></label>
        <p className="de-spec">Updates by itself as your sales and products change.</p>
      </>
    case 'countdown':
      return <>
        <label>Sale title<input maxLength="80" value={s.title} onChange={(e) => set({ title: e.target.value })} /></label>
        <label>Subtitle (optional)<input maxLength="160" value={s.subtitle} onChange={(e) => set({ subtitle: e.target.value })} /></label>
        <label>Ends at<input type="datetime-local" value={s.ends_at} onChange={(e) => set({ ends_at: e.target.value })} /></label>
        <label>Colour<input type="color" value={s.bg_color} onChange={(e) => set({ bg_color: e.target.value })} /></label>
        <p className="de-spec">The section hides itself when the sale ends.</p>
        <ProductPicker ids={s.product_ids} lookup={lookup} max={12} onChange={(ids) => set({ product_ids: ids })} />
      </>
    case 'brand_story':
      return <>{title}
        <ImageField headers={headers} label="Image" spec={spec.story} value={s.image_url} onError={onError} onChange={(url) => set({ image_url: url })} />
        <label>Image side<select value={s.image_side} onChange={(e) => set({ image_side: e.target.value })}><option value="left">Left</option><option value="right">Right</option></select></label>
        <label>Your story<textarea rows="6" maxLength="1500" value={s.text} onChange={(e) => set({ text: e.target.value })} /></label>
      </>
    default:
      return <label>Height<select value={s.size} onChange={(e) => set({ size: e.target.value })}><option value="small">Small</option><option value="medium">Medium</option><option value="large">Large</option></select></label>
  }
}

// ---------------------------------------------------------------------------
// Editor
// ---------------------------------------------------------------------------
function Editor({ headers, version, lookup, specs, onBack, onSaved }) {
  const [name, setName] = useState(version.name)
  const [page, setPage] = useState(version.page ?? {})
  const [sections, setSections] = useState(version.sections ?? [])
  const [selected, setSelected] = useState(null) // section id | 'page' | null
  const [dirty, setDirty] = useState(false)
  const [msg, setMsg] = useState('')
  const [problems, setProblems] = useState(null)
  const [drag, setDrag] = useState(null) // { from: index } | { add: type }
  const [dropAt, setDropAt] = useState(null)
  const spec = specs[version.platform] ?? specs.desktop
  const readOnly = version.is_live || version.status === 'in_review'

  const change = (fn) => { setSections(fn); setDirty(true) }
  const setSection = (id, patch) => change((list) => list.map((s) => (s.id === id ? { ...s, ...patch } : s)))
  const add = (type, at = sections.length) => { const s = blank(type); change((list) => [...list.slice(0, at), s, ...list.slice(at)]); setSelected(s.id) }
  const move = (from, to) => change((list) => { const next = [...list]; const [item] = next.splice(from, 1); next.splice(to > from ? to - 1 : to, 0, item); return next })

  function onDrop(at) {
    if (drag?.add) add(drag.add, at)
    else if (drag?.from != null && drag.from !== at && drag.from + 1 !== at) move(drag.from, at)
    setDrag(null)
    setDropAt(null)
  }

  async function save() {
    try {
      const d = await send(headers, `/seller/decorations/${version.id}`, 'PATCH', { name, page, sections })
      setDirty(false)
      onSaved(d.data)
      setMsg('Saved.')
      return d.data
    } catch (e) { setMsg(e.message); return null }
  }

  async function submit() {
    const saved = dirty ? await save() : version
    if (!saved) return
    try {
      const d = await send(headers, `/seller/decorations/${version.id}/submit`, 'POST')
      onSaved(d.data)
      setMsg(d.data.status === 'in_review' ? 'Submitted — NexTech is spot-checking this version. You can publish it once it’s approved.' : 'Submitted and approved — publish it from the version list.')
    } catch (e) {
      if (e.problems) setProblems(e.problems)
      else setMsg(e.message)
    }
  }

  const current = sections.find((s) => s.id === selected)
  return (
    <>
      <div className="de-top">
        <div className="sc-head"><button type="button" className="sc-link" onClick={() => { if (!dirty || window.confirm('Leave without saving your changes?')) onBack() }}>&larr; Versions</button><input value={name} maxLength="80" disabled={readOnly} onChange={(e) => { setName(e.target.value); setDirty(true) }} /><span className="sc-pill hidden">{version.platform === 'mobile' ? 'Mobile' : 'Desktop'}</span></div>
        <div className="ss-actions">
          {readOnly ? <span className="sc-muted">{version.is_live ? 'Live — make a copy to change it.' : 'In review — wait for the result or make a copy.'}</span> : <>
            <button type="button" className="seller-btn ghost" disabled={!dirty} onClick={save}>Save</button>
            <button type="button" className="sc-primary" onClick={submit}>Submit</button>
          </>}
        </div>
      </div>
      {msg && <div className="sc-alert warn"><span>{msg}</span><button type="button" onClick={() => setMsg('')}>OK</button></div>}
      <div className="de-body">
        <aside className="de-palette">
          <h3>Sections</h3>
          <span className="de-spec">Drag onto the canvas, or click to add at the end.</span>
          {SECTION_TYPES.map(([type, label, hint, extra]) => (
            <button type="button" key={type} draggable={!readOnly} disabled={readOnly} onDragStart={() => setDrag({ add: type })} onDragEnd={() => { setDrag(null); setDropAt(null) }} onClick={() => add(type)}>
              <span>{label}<small>{hint}</small></span>{extra && <span className="de-new">NexTech</span>}
            </button>
          ))}
        </aside>

        <div className="de-canvas-wrap">
          <div className={`de-canvas ${version.platform}`}>
            <div className="sd-page" style={{ '--sd-bg': page.background_color, '--sd-accent': page.accent_color }}>
              <div className={`de-bgzone${selected === 'page' ? ' on' : ''}`} onClick={() => setSelected('page')} title="Click to edit the page background">
                {page.background_image_url ? <div className="sd-bg"><img src={mediaUrl(page.background_image_url)} alt="" /></div> : 'Background image — click to edit'}
              </div>
              {sections.map((s, i) => (
                <div key={s.id} className={`de-sec${selected === s.id ? ' on' : ''}${dropAt === i ? ' drop-before' : ''}`}
                  draggable={!readOnly}
                  onDragStart={() => setDrag({ from: i })}
                  onDragEnd={() => { setDrag(null); setDropAt(null) }}
                  onDragOver={(e) => { e.preventDefault(); setDropAt(i) }}
                  onDrop={(e) => { e.preventDefault(); onDrop(i) }}
                  onClick={() => setSelected(s.id)}>
                  {!readOnly && <div className="de-sec-tools">
                    <button type="button" disabled={i === 0} onClick={(e) => { e.stopPropagation(); move(i, i - 1) }} title="Move up">↑</button>
                    <button type="button" disabled={i === sections.length - 1} onClick={(e) => { e.stopPropagation(); move(i, i + 2) }} title="Move down">↓</button>
                    <button type="button" onClick={(e) => { e.stopPropagation(); change((list) => [...list.slice(0, i + 1), { ...structuredClone(s), id: uid() }, ...list.slice(i + 1)]) }} title="Duplicate">⧉</button>
                    <button type="button" onClick={(e) => { e.stopPropagation(); change((list) => list.filter((x) => x.id !== s.id)); setSelected(null) }} title="Delete">✕</button>
                  </div>}
                  <SectionView section={toDisplay(s, lookup)} />
                </div>
              ))}
              <div className={`de-drop-end${drag ? ' active' : ''}`} onDragOver={(e) => { e.preventDefault(); setDropAt(sections.length) }} onDrop={(e) => { e.preventDefault(); onDrop(sections.length) }}>{sections.length ? 'Drop here to add at the end' : 'Drag sections here'}</div>
            </div>
          </div>
        </div>

        <aside className="de-panel">
          {selected === 'page' ? (
            <>
              <h3>Page background</h3>
              <ImageField headers={headers} label="Background image" spec={spec.background} value={page.background_image_url} onError={setMsg} onChange={(url) => { setPage({ ...page, background_image_url: url }); setDirty(true) }} />
              <label>Background colour<input type="color" value={page.background_color ?? '#f3f4f6'} disabled={readOnly} onChange={(e) => { setPage({ ...page, background_color: e.target.value }); setDirty(true) }} /></label>
              <label>Accent colour (prices)<input type="color" value={page.accent_color ?? '#fb7701'} disabled={readOnly} onChange={(e) => { setPage({ ...page, accent_color: e.target.value }); setDirty(true) }} /></label>
              <button type="button" className="sc-link" onClick={() => setSelected(null)}>&larr; Page layout</button>
            </>
          ) : current ? (
            <>
              <h3>{TYPE_LABEL[current.type]}</h3>
              <fieldset disabled={readOnly} className="de-item" style={{ border: 0, padding: 0 }}>
                <SectionForm s={current} set={(patch) => setSection(current.id, patch)} headers={headers} lookup={lookup} spec={spec} onError={setMsg} />
              </fieldset>
              <button type="button" className="sc-link" onClick={() => setSelected(null)}>&larr; Page layout</button>
            </>
          ) : (
            <>
              <h3>Page layout</h3>
              <ul className="de-layout">
                <li className={selected === 'page' ? 'on' : ''} onClick={() => setSelected('page')}>Background &amp; colours</li>
                {sections.map((s, i) => <li key={s.id} onClick={() => setSelected(s.id)}><span>{i + 1}. {TYPE_LABEL[s.type]}{s.title ? ` — ${s.title}` : ''}</span></li>)}
              </ul>
              {!sections.length && <p className="de-spec">Empty — drag sections from the left.</p>}
              <p className="de-spec">Images must match the sizes shown when you add them. Only use logos, images, videos and text you have the rights to — NexTech removes content that breaks the rules.</p>
            </>
          )}
        </aside>
      </div>

      {problems && (
        <div className="ss-overlay" role="presentation" onClick={() => setProblems(null)}>
          <div className="ss-modal" onClick={(e) => e.stopPropagation()}>
            <div className="sc-alert danger"><span><b>Fix these before submitting</b></span></div>
            <ul className="ob-list de-problems">{problems.map((p) => <li key={p}>{p}</li>)}</ul>
            <div className="ss-actions"><button type="button" className="sc-primary" onClick={() => setProblems(null)}>OK</button></div>
          </div>
        </div>
      )}
    </>
  )
}

// ---------------------------------------------------------------------------
// Decoration list page
// ---------------------------------------------------------------------------
export function StoreDecoration({ headers }) {
  const [data, setData] = useState(null)
  const [platform, setPlatform] = useState('desktop')
  const [selectedId, setSelectedId] = useState(null)
  const [editing, setEditing] = useState(null)
  const [msg, setMsg] = useState('')

  const load = useCallback(() => { send(headers, '/seller/decorations').then((d) => setData(d.data)).catch((e) => setMsg(e.message)) }, [headers])
  useEffect(() => { Promise.resolve().then(load) }, [load])

  if (!data) return <div className="sc-card"><p className="sc-muted">{msg || 'Loading…'}</p></div>

  const lookup = { products: data.products, categories: data.categories }
  const versions = data.versions.filter((v) => v.platform === platform)
  const selected = versions.find((v) => v.id === selectedId) ?? versions[0]

  async function act(path, method = 'POST', body, okMsg) {
    setMsg('')
    try {
      const d = await send(headers, path, method, body)
      if (okMsg) setMsg(okMsg)
      load()
      return d?.data
    } catch (e) { setMsg(e.problems ? `${e.message} ${e.problems.join(' ')}` : e.message); return null }
  }

  if (editing) {
    return <Editor key={editing.id} headers={headers} version={editing} lookup={lookup} specs={data.image_specs}
      onBack={() => { setEditing(null); load() }}
      onSaved={(v) => { setEditing(v); setData((d) => ({ ...d, versions: d.versions.map((x) => (x.id === v.id ? v : x)) })) }} />
  }

  return (
    <>
      <h1 className="sc-title">Store decoration</h1>
      <p className="sc-muted">Design how your store page looks, separately for desktop and mobile. Banners, product displays and videos help shoppers stay longer and buy more. Only one version per platform can be live at a time.</p>
      {msg && <div className="sc-alert warn"><span>{msg}</span><button type="button" onClick={() => setMsg('')}>OK</button></div>}
      {data.live_products < data.min_products && <div className="sc-alert warn"><span>Shoppers see your default store page until you have {data.min_products} live products — you have {data.live_products}. You can still design and publish now.</span></div>}

      <div className="sc-tabs">
        {['desktop', 'mobile'].map((p) => <button type="button" key={p} className={platform === p ? 'active' : ''} onClick={() => { setPlatform(p); setSelectedId(null) }}>{p === 'desktop' ? 'Desktop' : 'Mobile'} <small>{data.versions.filter((v) => v.platform === p).length}</small></button>)}
      </div>

      {!versions.length ? (
        <div className="sc-card">
          <p>No {platform} versions yet.</p>
          <button type="button" className="sc-primary" onClick={async () => { const v = await act('/seller/decorations', 'POST', { platform }); if (v) setEditing(v) }}>Create a {platform} version</button>
        </div>
      ) : (
        <div className="de-list">
          <div className="sc-card">
            <div className="sc-head"><h2 className="sc-h2">Versions</h2><button type="button" className="sc-primary" disabled={versions.length >= data.max_versions} onClick={async () => { const v = await act('/seller/decorations', 'POST', { platform }); if (v) setEditing(v) }}>+ New version</button></div>
            <div className="ob-people">
              {versions.map((v) => (
                <div key={v.id} className={`de-version${selected?.id === v.id ? ' on' : ''}`} onClick={() => setSelectedId(v.id)}>
                  <span><b>{v.name}</b> {v.is_live ? <span className="sc-pill approved">Live</span> : <span className={`sc-pill ${STATUS[v.status]?.[1]}`}>{STATUS[v.status]?.[0]}</span>}</span>
                  {v.status === 'rejected' && v.review_note && <small className="sc-low">Rejected: {v.review_note}</small>}
                  <small className="sc-muted">Updated {new Date(v.updated_at).toLocaleString()}</small>
                  <span className="sc-actions" onClick={(e) => e.stopPropagation()}>
                    {!v.is_live && v.status !== 'in_review' && <button type="button" onClick={() => setEditing(v)}>Edit</button>}
                    {(v.status === 'draft' || v.status === 'rejected') && <button type="button" onClick={() => act(`/seller/decorations/${v.id}/submit`, 'POST', null, 'Submitted.')}>Submit</button>}
                    {v.status === 'approved' && !v.is_live && <button type="button" onClick={() => act(`/seller/decorations/${v.id}/publish`, 'POST', null, `Published — your ${platform} store page now uses “${v.name}”.`)}>Publish</button>}
                    {v.is_live && <button type="button" onClick={() => act(`/seller/decorations/${v.id}/unpublish`, 'POST', null, 'Unpublished — shoppers see the default store page.')}>Unpublish</button>}
                    <button type="button" disabled={versions.length >= data.max_versions} onClick={() => act('/seller/decorations', 'POST', { platform, copy_from: v.id }, 'Copied.')}>Copy</button>
                    {!v.is_live && <button type="button" className="danger" onClick={() => { if (window.confirm(`Delete “${v.name}”?`)) act(`/seller/decorations/${v.id}`, 'DELETE') }}>Delete</button>}
                  </span>
                </div>
              ))}
            </div>
            <p className="sc-muted">Submitted versions are checked for missing content; NexTech also spot-checks some before they can be published.</p>
          </div>
          <div className={`de-preview ${platform}`}>
            {selected && <DecorationView design={{ page: selected.page, sections: (selected.sections ?? []).map((s) => toDisplay(s, lookup)) }} platform={platform} shopName={data.shop.name} />}
          </div>
        </div>
      )}

      {!data.terms_accepted_at && (
        <div className="ss-overlay" role="presentation">
          <div className="ss-modal">
            <h2 className="sc-h2">Data Processing Agreement</h2>
            <p>Store decoration lets you upload images, videos and text that NexTech stores and shows to shoppers. By continuing you agree that NexTech processes this content under the <a href="#/p/global-data-protection-exhibit" target="_blank" rel="noreferrer">Data Processing Agreement</a>, and that everything you upload is yours to use and follows the law and the Seller Rules.</p>
            <div className="ss-actions"><button type="button" className="sc-primary" onClick={() => act('/seller/decorations/accept-terms')}>I agree</button></div>
          </div>
        </div>
      )}
    </>
  )
}
