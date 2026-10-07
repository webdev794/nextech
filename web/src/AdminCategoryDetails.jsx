import { useCallback, useEffect, useRef, useState } from 'react'

// Admin → Categories → edit → Product details: the boxes sellers fill in for
// products in this category (text, number, dropdown, checkbox list, yes/no).
// A detail or a choice that products already use is locked: it can be renamed
// but not removed (shown in red with how many products use it).

const API_URL = import.meta.env.VITE_API_URL ?? 'http://127.0.0.1:8000/api'
const TYPES = [['text', 'Text box'], ['number', 'Number box'], ['select', 'Dropdown (pick one)'], ['multiselect', 'Checkboxes (pick several)'], ['checkbox', 'Single checkbox (yes / no)']]
const CHOICES = ['select', 'multiselect']
const plural = (n, word) => `${n} ${word}${n === 1 ? '' : 's'}`

function OptionsEditor({ options, used, onChange }) {
  const [draft, setDraft] = useState('')
  const add = () => {
    const parts = draft.split(/[,\n]/).map((s) => s.trim()).filter((s) => s && !options.includes(s))
    if (parts.length) onChange([...options, ...parts])
    setDraft('')
  }
  return (
    <div className="cd-options">
      {options.map((o) => (
        <span key={o} className={`cd-chip${used?.[o] ? ' is-locked' : ''}`} title={used?.[o] ? `Used by ${plural(used[o], 'product')} — can’t be removed` : ''}>
          {o}{used?.[o] ? <small> 🔒 {used[o]}</small> : <button type="button" aria-label={`Remove ${o}`} onClick={() => onChange(options.filter((x) => x !== o))}>×</button>}
        </span>
      ))}
      <input value={draft} placeholder="Add a choice (comma for several)" onChange={(e) => setDraft(e.target.value)} onKeyDown={(e) => { if (e.key === 'Enter') { e.preventDefault(); add() } }} onBlur={add} />
    </div>
  )
}

// `categoryId` edits one category's list; without it, the common details every physical product gets.
export function CategoryDetailsEditor({ categoryId, headers, onMessage, onError }) {
  const path = categoryId ? `${API_URL}/admin/categories/${categoryId}/details` : `${API_URL}/admin/categories/common-details`
  const [info, setInfo] = useState(null)
  const [fields, setFields] = useState([])
  const [editing, setEditing] = useState(false)
  const [busy, setBusy] = useState(false)
  const [problems, setProblems] = useState([])
  const onErrorRef = useRef(onError)
  useEffect(() => { onErrorRef.current = onError })

  const show = useCallback((d) => { setInfo(d); setFields(d.fields.map((f) => ({ ...f, options: f.options ?? [] }))); setEditing(d.own); setProblems([]) }, [])
  useEffect(() => {
    fetch(path, { headers: headers() })
      .then(async (r) => { const d = await r.json().catch(() => ({})); if (!r.ok) throw new Error(d.message ?? 'Could not load product details.'); show(d.data) })
      .catch((e) => onErrorRef.current(e))
  }, [path, headers, show])

  async function save(next) {
    setBusy(true); setProblems([])
    try {
      const r = await fetch(path, { method: 'PUT', headers: headers(), body: JSON.stringify({ fields: next }) })
      const d = await r.json().catch(() => ({}))
      if (!r.ok) { setProblems(d.errors ? Object.values(d.errors).flat() : [d.message ?? 'Could not save.']); return }
      show(d.data)
      onMessage(next === null ? 'This category uses its parent’s product details again.' : categoryId ? 'Product details saved — sellers see them when adding products in this category.' : 'Common product details saved — every category asks for them.')
    } catch (e) { onError(e) } finally { setBusy(false) }
  }

  if (!info) return <div className="admin-form"><p className="muted">Loading product details…</p></div>
  const usage = info.usage ?? {}
  const set = (i, patch) => setFields(fields.map((f, n) => (n === i ? { ...f, ...patch } : f)))
  const move = (i, by) => { const next = [...fields]; const [f] = next.splice(i, 1); next.splice(i + by, 0, f); setFields(next) }
  const usedAny = Object.keys(usage).some((k) => fields.some((f) => f.key === k))

  return (
    <div className="admin-form cd-editor">
      <h3>{categoryId ? 'Product details sellers fill in' : 'Common product details — every category'}</h3>
      <p className="muted">{categoryId
        ? <>The boxes sellers see on <b>Add product → Product details</b> for this category (and subcategories that don&rsquo;t have their own list), above the common details every category has.</>
        : <>Asked for every physical product, in every category — new categories get them automatically. Each category can add its own details on top (Categories → edit a category).</>} Shoppers see the answers on the product page. A detail or choice that products already use is <span className="cd-locked-word">locked</span> — you can rename it, but not remove it.</p>

      {!editing ? (
        <>
          <p>This category uses the details from <b>{info.inherited_from}</b>{fields.length ? ':' : ' — only the common details below.'}</p>
          {fields.length > 0 && <ul className="cd-readonly">{fields.map((f) => <li key={f.key}>{f.label} <span className="muted">— {TYPES.find((t) => t[0] === f.type)?.[1] ?? f.type}{f.options?.length ? `: ${f.options.join(', ')}` : ''}</span></li>)}</ul>}
          <div className="admin-form-actions"><button type="button" className="act" onClick={() => setEditing(true)}>Give this category its own list</button></div>
        </>
      ) : (
        <>
          {fields.length === 0 && <p className="muted">No details yet — add the first one.</p>}
          {fields.map((f, i) => {
            const used = f.key ? usage[f.key] : null
            const choice = CHOICES.includes(f.type)
            return (
              <fieldset key={f.key ?? `new-${i}`} className={`cd-field${used ? ' is-used' : ''}`}>
                <div className="admin-form-grid">
                  <label>Label<input value={f.label} maxLength={80} placeholder="e.g. Screen size" onChange={(e) => set(i, { label: e.target.value })} /></label>
                  <label>Kind of box
                    <select value={f.type} onChange={(e) => set(i, { type: e.target.value })}>
                      {TYPES.map(([v, l]) => <option key={v} value={v} disabled={!!used && v !== f.type && !(CHOICES.includes(v) && choice)}>{l}</option>)}
                    </select>
                  </label>
                  {['text', 'number'].includes(f.type) && <label>Unit (optional)<input value={f.unit ?? ''} maxLength={20} placeholder="e.g. inches, mAh, W" onChange={(e) => set(i, { unit: e.target.value })} /></label>}
                  {f.type !== 'checkbox' && <label className="admin-check"><input type="checkbox" checked={!!f.required} onChange={(e) => set(i, { required: e.target.checked })} /> Sellers must fill it in</label>}
                </div>
                {choice && <><span className="muted">Choices</span><OptionsEditor options={f.options ?? []} used={used?.options} onChange={(options) => set(i, { options })} /></>}
                {f.when && <p className="muted">Shown only when {Object.entries(f.when).map(([k, v]) => `${fields.find((x) => x.key === k)?.label ?? k} is ${[].concat(v).join(' or ')}`).join(', ')}.</p>}
                <div className="cd-field-actions">
                  <button type="button" className="act ghost" disabled={i === 0} onClick={() => move(i, -1)} aria-label="Move up">↑</button>
                  <button type="button" className="act ghost" disabled={i === fields.length - 1} onClick={() => move(i, 1)} aria-label="Move down">↓</button>
                  {(info.core ?? []).includes(f.key) ? <span className="cd-locked">🔒 Needed by the store (warranty &amp; order follow-ups){used ? ` · used by ${plural(used.count, 'product')}` : ''}</span>
                    : used ? <span className="cd-locked">🔒 Locked — used by {plural(used.count, 'product')}</span>
                    : <button type="button" className="act ghost" onClick={() => setFields(fields.filter((_, n) => n !== i))}>Remove</button>}
                </div>
              </fieldset>
            )
          })}
          <button type="button" className="act ghost" onClick={() => setFields([...fields, { label: '', type: 'text', options: [] }])}>+ Add a detail</button>
          {problems.length > 0 && <ul className="cd-problems">{problems.map((p) => <li key={p}>{p}</li>)}</ul>}
          <div className="admin-form-actions">
            <button type="button" className="act" disabled={busy || fields.some((f) => !f.label.trim() || (CHOICES.includes(f.type) && !(f.options ?? []).length))} onClick={() => save(fields)}>{busy ? 'Saving…' : 'Save product details'}</button>
            {!categoryId ? null : info.own
              ? <button type="button" className="act ghost" disabled={busy || usedAny} title={usedAny ? 'Products use these details' : ''} onClick={() => { if (window.confirm('Remove this category’s own list and use its parent’s again?')) save(null) }}>Use parent&rsquo;s list instead</button>
              : <button type="button" className="act ghost" onClick={() => show(info)}>Cancel</button>}
          </div>
          {fields.some((f) => CHOICES.includes(f.type) && !(f.options ?? []).length) && <p className="muted">Dropdowns and checkbox lists need at least one choice.</p>}
        </>
      )}

      {info.common?.length > 0 && <p className="muted">Also asked for every product: {info.common.map((f) => f.label).join(', ')}. Add a detail with the same name here to replace one for this category.</p>}
    </div>
  )
}
