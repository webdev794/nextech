import { useCallback, useEffect, useState } from 'react'
import { useLiveRefresh } from './useLiveRefresh'

// Admin → Careers: the roles on the Careers page (open / closed) and the
// applications that come in (rider roles use the rider application instead).

const API_URL = import.meta.env.VITE_API_URL ?? 'http://127.0.0.1:8000/api'
const EMPTY = { title: '', kind: 'general', location: '', store_ids: [], description: '', requirements: '', is_open: true }
const STATUS = { new: 'New', reviewed: 'Reviewed', hired: 'Hired', declined: 'Declined' }

export function AdminCareers({ headers, onMessage, section = 'roles' }) {
  const [data, setData] = useState(null)
  const [form, setForm] = useState(null)
  const [stores, setStores] = useState([])
  useEffect(() => { fetch(`${API_URL}/admin/stores`, { headers: headers() }).then((r) => r.json()).then((d) => setStores(d.data ?? [])).catch(() => {}) }, [headers])
  const call = useCallback(async (path, method = 'GET', body) => {
    const res = await fetch(`${API_URL}${path}`, { method, headers: headers(), body: body ? JSON.stringify(body) : undefined })
    const d = await res.json().catch(() => ({}))
    if (!res.ok) { onMessage?.(d.message ?? 'That failed.'); return false }
    setData(d.data); return true
  }, [headers, onMessage])
  const load = useCallback(() => call('/admin/careers'), [call])
  useEffect(() => { Promise.resolve().then(load) }, [load])
  useLiveRefresh(load)

  async function save(event) {
    event.preventDefault()
    if (await call(form.id ? `/admin/careers/roles/${form.id}` : '/admin/careers/roles', form.id ? 'PUT' : 'POST', form)) { setForm(null); onMessage?.('Role saved.') }
  }

  if (!data) return <section className="admin-panel"><p className="muted">Loading…</p></section>
  return (
    <section className="admin-panel">
      {section === 'roles' ? <>
      <h3>Open roles</h3>
      <p className="muted">Jobs shown on the Careers page. Rider jobs send people to the rider application; other jobs use a short form — see Careers → Applications.</p>
      <table className="admin-table"><thead><tr><th>Role</th><th>Type</th><th>Where</th><th>Applications</th><th>Status</th><th /></tr></thead>
        <tbody>{data.roles.map((r) => (
          <tr key={r.id}>
            <td>{r.title}</td><td>{r.kind === 'rider' ? 'Rider (rider application)' : 'Short form'}</td><td>{[r.location, (r.store_ids ?? []).map((id) => stores.find((s) => s.id === id)?.name).filter(Boolean).join(', ')].filter(Boolean).join(' · ') || '—'}</td><td>{r.kind === 'rider' ? 'see Riders' : r.applications_count}</td>
            <td>{r.is_open ? <span className="pill pill-approved">Open</span> : <span className="pill">Closed</span>}</td>
            <td className="admin-actions">
              <button type="button" className="act ghost" onClick={() => setForm({ ...EMPTY, ...r })}>Edit</button>
              <button type="button" className="act ghost" onClick={() => call(`/admin/careers/roles/${r.id}`, 'PUT', { ...r, is_open: !r.is_open })}>{r.is_open ? 'Close' : 'Open'}</button>
              <button type="button" className="act danger" onClick={() => window.confirm(`Delete “${r.title}” and its applications?`) && call(`/admin/careers/roles/${r.id}`, 'DELETE')}>Delete</button>
            </td>
          </tr>
        ))}</tbody>
      </table>
      {form ? (
        <form className="admin-form" onSubmit={save}>
          <label>Title<input required maxLength={120} value={form.title} onChange={(e) => setForm({ ...form, title: e.target.value })} /></label>
          <label>Type<select value={form.kind} onChange={(e) => setForm({ ...form, kind: e.target.value })}><option value="general">Short form (name, email, CV link, message)</option><option value="rider">Rider (uses the rider application)</option></select></label>
          <label>Where<input maxLength={120} placeholder="e.g. Mohali, or Remote" value={form.location ?? ''} onChange={(e) => setForm({ ...form, location: e.target.value })} /></label>
          <fieldset className="admin-fieldset"><legend>For these stores (optional — a seller&rsquo;s store also sees the applications)</legend>
            <div className="careers-stores">{stores.filter((s) => s.is_active || s.shop_id).map((s) => (
              <label key={s.id} className="admin-check"><input type="checkbox" checked={(form.store_ids ?? []).includes(s.id)} onChange={(e) => setForm({ ...form, store_ids: e.target.checked ? [...(form.store_ids ?? []), s.id] : (form.store_ids ?? []).filter((x) => x !== s.id) })} /> {s.name}{s.city ? ` (${s.city})` : ''}{s.shop_id ? ' · seller' : ''}</label>
            ))}</div>
          </fieldset>
          <label>What the job is<textarea rows="3" value={form.description ?? ''} onChange={(e) => setForm({ ...form, description: e.target.value })} /></label>
          <label>What you need <small className="muted">(one per line)</small><textarea rows="5" value={form.requirements ?? ''} onChange={(e) => setForm({ ...form, requirements: e.target.value })} /></label>
          <label className="admin-check"><input type="checkbox" checked={form.is_open} onChange={(e) => setForm({ ...form, is_open: e.target.checked })} /> Open (shown on the Careers page)</label>
          <div className="admin-form-actions"><button type="submit" className="act">Save role</button><button type="button" className="act ghost" onClick={() => setForm(null)}>Cancel</button></div>
        </form>
      ) : <button type="button" className="act" onClick={() => setForm(EMPTY)}>+ Add a role</button>}
      </> : <>
      <h3>Applications</h3>
      <p className="muted">People who applied from the Careers page (rider applications are under Riders).</p>
      {data.applications.length === 0 ? <p className="muted">None yet.</p> : (
        <table className="admin-table"><thead><tr><th>When</th><th>Role</th><th>Applicant</th><th>Message</th><th>Status</th></tr></thead>
          <tbody>{data.applications.map((a) => (
            <tr key={a.id}>
              <td>{new Date(a.created_at).toLocaleDateString()}</td>
              <td>{a.role?.title}</td>
              <td>{a.name}<br /><a href={`mailto:${a.email}`}>{a.email}</a>{a.phone ? ` · ${a.phone}` : ''}{a.link && <><br /><a href={a.link} target="_blank" rel="noreferrer">CV / profile</a></>}</td>
              <td>{a.message || <span className="muted">—</span>}</td>
              <td><select value={a.status} onChange={(e) => call(`/admin/careers/applications/${a.id}`, 'PATCH', { status: e.target.value })}>{Object.entries(STATUS).map(([v, l]) => <option key={v} value={v}>{l}</option>)}</select></td>
            </tr>
          ))}</tbody>
        </table>
      )}
      </>}
    </section>
  )
}
