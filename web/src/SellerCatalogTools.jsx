import { useCallback, useEffect, useState } from 'react'
import { mediaUrl } from './mediaUrl'
import { checkProductImage } from './productImageCheck'
import { storeMoney } from './money'

// Seller Center catalog tools, modelled on Temu's Seller Center:
//  - Add products via upload (Excel template per category, upload, results)
//  - Pricing health (sales boost offers for "Low traffic" products, pricing records)
//  - the once-a-day sales boost pop-up
//  - Product compliance (documents + origin per product)
//  - Account health -> Trademarks

const API_URL = import.meta.env.VITE_API_URL ?? 'http://127.0.0.1:8000/api'
const money = (cents) => storeMoney(cents ?? 0)
const MAX_TEMPLATE_CATEGORIES = 5
const MAX_UPLOAD_ROWS = 500

async function readJson(response) {
  const text = await response.text()
  try { return text ? JSON.parse(text) : {} } catch { return {} }
}

async function send(headers, path, method = 'GET', body) {
  const isForm = body instanceof FormData
  const response = await fetch(`${API_URL}${path}`, { method, headers: { ...headers(), ...(body && !isForm ? { 'Content-Type': 'application/json' } : {}) }, body: body ? (isForm ? body : JSON.stringify(body)) : undefined })
  const data = await readJson(response)
  if (!response.ok) throw new Error(data.message ?? Object.values(data.errors ?? {})[0]?.[0] ?? 'Something went wrong.')
  return data
}

async function uploadDoc(headers, kind, file) {
  const body = new FormData()
  body.append('file', file)
  body.append('kind', kind)
  return (await send(headers, '/seller/kyc-document', 'POST', body)).data.path
}

function saveBlob(blob, name) {
  const url = URL.createObjectURL(blob)
  const a = document.createElement('a')
  a.href = url
  a.download = name
  a.click()
  setTimeout(() => URL.revokeObjectURL(url), 5000)
}

const loadExcel = async () => (await import('exceljs')).default
const dateTime = (v) => (v ? new Date(v).toLocaleString([], { dateStyle: 'medium', timeStyle: 'short' }) : '—')

// ---------------------------------------------------------------------------
// Spreadsheet template (Add products via upload)
// ---------------------------------------------------------------------------
const RED = 'FFF4CCCC'
const GREY = 'FFD9D9D9'
const ORANGE = 'FFFF9900'

function templateColumns(cats, config) {
  const range = (n, fn) => Array.from({ length: n }, (_, i) => fn(i + 1))
  const cols = [
    { section: 'Product Identity', key: 'category', label: 'Category', required: true, help: 'One of the categories this template was made for.', list: 'categories' },
    { section: 'Product Identity', key: 'product_name', label: 'Product name', required: true, help: 'Up to 160 characters.' },
    { section: 'Product Identity', key: 'contribution_goods', label: 'Contribution Goods', help: 'Your own product code. Rows (SKUs) with the same code make up one product; leave empty for a single-SKU product.' },
    { section: 'Product Identity', key: 'contribution_sku', label: 'Contribution SKU', help: 'Your own code for this SKU (variant).' },
    { section: 'Product Identity', key: 'brand', label: 'Brand (trademark)', help: 'An approved trademark registered under Account health. Leave empty if unbranded.', list: 'trademarks' },
    { section: 'Product Description', key: 'description', label: 'Product description', required: true, help: 'Shown on the product page.' },
    ...range(5, (n) => ({ section: 'Product Description', key: `bullet_point_${n}`, label: `Bullet point ${n}`, help: 'Key selling point.' })),
    ...range(5, (n) => ({ section: 'Product Description', key: `detail_image_url_${n}`, label: `Detail image URL ${n}`, help: 'Public image address (https://…), shown in the product details section.' })),
    { section: 'Product Description', key: 'product_video_url', label: 'Product video URL', help: 'Public video address, shown at the top of the product page. Max 100 MB, 3 minutes, 720p.' },
    { section: 'Product Description', key: 'detail_video_url', label: 'Detail video URL', help: 'Public video address, shown in the product details section.' },
  ]
  // Product details: every field any selected category asks for.
  const seen = new Map()
  cats.forEach((c) => c.attributes.forEach((a) => { if (!seen.has(a.key)) seen.set(a.key, a) }))
  seen.forEach((a) => cols.push({
    section: 'Product Detail', key: `detail_${a.key}`, label: `${a.label}${a.unit ? ` (${a.unit})` : ''}`, attribute: a,
    help: `${a.type === 'multiselect' ? 'One or more of (separate with ;): ' : a.options ? 'One of: ' : ''}${(a.options ?? []).join(', ')}${a.when ? ` — only when ${Object.entries(a.when).map(([k, v]) => `${k} is ${v.join(' / ')}`).join(', ')}` : ''}`,
    list: a.type === 'select' ? `attr_${a.key}` : null,
  }))
  cols.push(
    { section: 'Sale Property (at least one, at most two)', key: 'variation_theme', label: 'Variation theme', help: `What the SKUs differ by: one type, or two joined with × (e.g. Color × Size). Types: ${config.variation_types.join(', ')}.`, list: 'themes' },
    { section: 'Sale Property (at least one, at most two)', key: 'variation_value_1', label: 'Variation value 1', help: 'This SKU’s value for the first type (e.g. Black).' },
    { section: 'Sale Property (at least one, at most two)', key: 'variation_value_2', label: 'Variation value 2', help: 'This SKU’s value for the second type (e.g. 256 GB).' },
    ...range(10, (n) => ({ section: 'Variations', key: `sku_image_url_${n}`, label: `SKU image URL ${n}`, required: n === 1, help: 'Public image address (https://…). Square 1:1, 800×800 px, 3 MB max. Image 1 is the main image.' })),
    { section: 'Variations', key: 'quantity', label: 'Quantity', required: true, help: 'Stock for this SKU.' },
    { section: 'Variations', key: 'base_price', label: 'Base price', required: true, help: 'Price for this SKU, in your market’s currency.' },
    { section: 'Variations', key: 'weight_g', label: 'Weight (g)', help: 'Actual weight, packaged.' },
    { section: 'Variations', key: 'length_mm', label: 'Length (mm)' },
    { section: 'Variations', key: 'width_mm', label: 'Width (mm)' },
    { section: 'Variations', key: 'height_mm', label: 'Height (mm)' },
    { section: 'Variations', key: 'price_reference_url', label: 'Same product elsewhere (URL)', help: 'Optional link that backs up your price.' },
    { section: 'Offer', key: 'handling_time', label: 'Handling time (days)', required: config.ships_itself, help: `Working days from order to shipment: ${config.handling_days.join(', ')}.`, list: 'handling' },
    { section: 'Offer', key: 'shipping_template', label: 'Shipping template', help: 'The name of one of your shipping templates; empty = your default.', list: 'templates' },
    { section: 'Qualifications', key: 'country_of_origin', label: 'Country/Region of origin', required: true },
    { section: 'Qualifications', key: 'province_of_origin', label: 'Province/State of origin' },
  )
  if (config.market === 'IN') {
    cols.push(
      { section: 'Qualifications', key: 'hsn_code', label: 'HSN code', required: true },
      { section: 'Qualifications', key: 'gst_rate', label: 'GST rate (%)', required: true, help: 'e.g. 18' },
      { section: 'Qualifications', key: 'manufacturer_info', label: 'Manufacturer / packer / importer', required: true, help: 'Name and address.' },
    )
  }
  return cols
}

const colLetter = (n) => { let s = ''; for (let x = n; x > 0; x = Math.floor((x - 1) / 26)) s = String.fromCharCode(65 + ((x - 1) % 26)) + s; return s }

async function buildTemplate(cats, config, templates) {
  const ExcelJS = await loadExcel()
  const wb = new ExcelJS.Workbook()
  wb.creator = 'NexTech Seller Center'
  const cols = templateColumns(cats, config)
  const lastRow = 3 + MAX_UPLOAD_ROWS

  const intro = wb.addWorksheet('Instructions')
  intro.getColumn(1).width = 110
  ;[
    'NexTech — Add products via upload',
    '',
    '1. Fill in the Template tab: one row per SKU. Rows with the same Contribution Goods code are one product.',
    '2. The Data Definitions tab explains every column.',
    '3. Colors: red = required, grey = leave empty for that category, orange edge = required only after an earlier answer, no color = optional.',
    '4. Product information (name, description, details, theme, offer, origin) must be identical on every SKU row of a product.',
    '5. Image and video URLs must be public — they must open without logging in.',
    '',
    'Image requirements — non-apparel: 1:1, 800×800 px, 3 MB max · apparel: 3:4, 1340×1785 px, 3 MB max. Up to 10 images, one URL per cell.',
    'Show only the product for sale, clearly, from several angles. You must hold the rights to every image. Images below standard may be rejected.',
    'Product video: up to 100 MB, 3 minutes, at least 720p. Shown at the top of the product page.',
    '',
    'Use the latest template: download a new one whenever you list products, as the fields and valid values change from time to time.',
    `Categories in this template: ${cats.map((c) => c.name).join(', ')}.`,
  ].forEach((line, i) => { intro.getCell(`A${i + 1}`).value = line })
  intro.getCell('A1').font = { bold: true, size: 14 }

  // Lists for dropdowns (hidden).
  const lists = wb.addWorksheet('Lists', { state: 'veryHidden' })
  const listRefs = {}
  let listCol = 1
  const addList = (name, values) => {
    if (!values.length) return
    const letter = colLetter(listCol++)
    values.forEach((v, i) => { lists.getCell(`${letter}${i + 1}`).value = v })
    listRefs[name] = `Lists!$${letter}$1:$${letter}$${values.length}`
  }
  addList('categories', cats.map((c) => c.name))
  addList('trademarks', config.trademarks.map((t) => t.name))
  const types = config.variation_types
  addList('themes', [...types, ...types.flatMap((a, i) => types.slice(i + 1).map((b) => `${a} × ${b}`))])
  addList('handling', config.handling_days.map(String))
  addList('templates', templates.map((t) => t.name))
  cols.filter((c) => c.list?.startsWith('attr_')).forEach((c) => addList(c.list, c.attribute.options))

  const defs = wb.addWorksheet('Data Definitions')
  defs.columns = [{ header: 'Section', width: 26 }, { header: 'Field', width: 30 }, { header: 'Column key', width: 26 }, { header: 'Required', width: 30 }, { header: 'Meaning & valid values', width: 90 }]
  defs.getRow(1).font = { bold: true }
  cols.forEach((c) => {
    let required = c.required ? 'Required' : 'Optional'
    if (c.attribute) {
      const req = cats.filter((cat) => cat.attributes.some((a) => a.key === c.attribute.key && a.required)).map((cat) => cat.name)
      const na = cats.filter((cat) => !cat.attributes.some((a) => a.key === c.attribute.key)).map((cat) => cat.name)
      required = [req.length ? `Required for: ${req.join(', ')}${c.attribute.when ? ' (conditional)' : ''}` : 'Optional', na.length ? `Leave empty for: ${na.join(', ')}` : ''].filter(Boolean).join(' · ')
    }
    defs.addRow([c.section, c.label, c.key, required, c.help ?? ''])
  })

  const sheet = wb.addWorksheet('Template', { views: [{ state: 'frozen', xSplit: 2, ySplit: 3 }] })
  let start = 1
  cols.forEach((c, i) => {
    const col = i + 1
    sheet.getColumn(col).width = Math.max(14, Math.min(34, c.label.length + 4))
    sheet.getCell(2, col).value = c.label
    sheet.getCell(3, col).value = c.key
    sheet.getCell(2, col).font = { bold: true }
    sheet.getCell(3, col).font = { color: { argb: 'FF999999' }, size: 9 }
    if (c.help) sheet.getCell(2, col).note = c.help
    if (c.required) sheet.getCell(2, col).fill = { type: 'pattern', pattern: 'solid', fgColor: { argb: RED } }
    const last = i === cols.length - 1 || cols[i + 1].section !== c.section
    if (last) {
      sheet.mergeCells(1, start, 1, col)
      sheet.getCell(1, start).value = c.section
      sheet.getCell(1, start).font = { bold: true, color: { argb: 'FFFFFFFF' } }
      sheet.getCell(1, start).fill = { type: 'pattern', pattern: 'solid', fgColor: { argb: 'FF1F2328' } }
      start = col + 1
    }
    const ref = `${colLetter(col)}4:${colLetter(col)}${lastRow}`
    if (c.list && listRefs[c.list]) {
      sheet.dataValidations.add(ref, { type: 'list', allowBlank: true, formulae: [listRefs[c.list]], showErrorMessage: c.list !== 'themes', errorStyle: 'warning', error: 'Pick a value from the list.' })
    }
    const cell = `${colLetter(col)}4`
    if (c.required) {
      sheet.addConditionalFormatting({ ref, rules: [{ type: 'expression', priority: 1, formulae: [`AND(ISBLANK(${cell}),NOT(ISBLANK($A4)))`], style: { fill: { type: 'pattern', pattern: 'solid', bgColor: { argb: RED } } } }] })
    }
    if (c.attribute) {
      const isIn = (names) => (names.length ? `OR(${names.map((n) => `$A4="${n.replace(/"/g, '""')}"`).join(',')})` : 'FALSE')
      const has = cats.filter((cat) => cat.attributes.some((a) => a.key === c.attribute.key))
      const req = has.filter((cat) => cat.attributes.find((a) => a.key === c.attribute.key).required && !c.attribute.when).map((cat) => cat.name)
      const na = cats.filter((cat) => !has.includes(cat)).map((cat) => cat.name)
      const rules = []
      if (na.length) rules.push({ type: 'expression', priority: 1, formulae: [isIn(na)], style: { fill: { type: 'pattern', pattern: 'solid', bgColor: { argb: GREY } } } })
      if (req.length) rules.push({ type: 'expression', priority: 2, formulae: [`AND(ISBLANK(${cell}),${isIn(req)})`], style: { fill: { type: 'pattern', pattern: 'solid', bgColor: { argb: RED } } } })
      if (c.attribute.when) {
        const side = { style: 'medium', color: { argb: ORANGE } }
        rules.push({ type: 'expression', priority: 3, formulae: [isIn(has.map((cat) => cat.name))], style: { border: { top: side, left: side, bottom: side, right: side } } })
      }
      if (rules.length) sheet.addConditionalFormatting({ ref, rules })
    }
  })

  const meta = wb.addWorksheet('Meta', { state: 'veryHidden' })
  meta.getCell('A1').value = JSON.stringify({ category_ids: cats.map((c) => c.id), market: config.market, generated_at: new Date().toISOString(), version: 1 })
  return wb.xlsx.writeBuffer()
}

// Read the Template tab back into rows keyed by column key.
async function readTemplate(file) {
  const ExcelJS = await loadExcel()
  const wb = new ExcelJS.Workbook()
  await wb.xlsx.load(await file.arrayBuffer())
  const sheet = wb.getWorksheet('Template')
  const metaCell = wb.getWorksheet('Meta')?.getCell('A1').value
  if (!sheet || !metaCell) throw new Error('This isn’t a NexTech product template — download one from step 1 and fill it in.')
  const meta = JSON.parse(String(metaCell))
  const keys = []
  sheet.getRow(3).eachCell({ includeEmpty: true }, (cell, col) => { keys[col] = String(cell.value ?? '').trim() })
  const text = (v) => {
    if (v == null) return ''
    if (v instanceof Date) return v.toISOString().slice(0, 10)
    if (typeof v === 'object') {
      if (v.richText) return v.richText.map((r) => r.text).join('')
      if (v.text != null) return String(typeof v.text === 'object' ? v.text.richText?.map((r) => r.text).join('') ?? '' : v.text)
      if (v.result != null) return String(v.result)
      if (v.hyperlink) return v.hyperlink
      return ''
    }
    return String(v).trim()
  }
  const rows = []
  for (let r = 4; r <= sheet.rowCount; r += 1) {
    const row = {}
    sheet.getRow(r).eachCell({ includeEmpty: false }, (cell, col) => { if (keys[col]) row[keys[col]] = text(cell.value) })
    if (Object.values(row).some((v) => v !== '')) rows.push({ ...row, _sheet_row: r })
  }
  return { rows, meta }
}

async function resultsWorkbook(task) {
  const ExcelJS = await loadExcel()
  const wb = new ExcelJS.Workbook()
  const sheet = wb.addWorksheet('Processing results')
  const rows = task.rows ?? []
  const keys = [...new Set(rows.flatMap((r) => Object.keys(r)).filter((k) => k !== '_sheet_row'))]
  sheet.addRow(['Sheet row', 'Result', 'What to fix', ...keys]).font = { bold: true }
  const LABEL = { submitted: 'Submitted', draft: 'Saved as draft — fix and resubmit', failed: 'Not created' }
  ;(task.results ?? []).forEach((res) => {
    const row = rows[res.row] ?? {}
    const added = sheet.addRow([row._sheet_row ?? res.row + 4, LABEL[res.status] ?? res.status, res.messages.join('\n'), ...keys.map((k) => row[k] ?? '')])
    if (res.status !== 'submitted') added.getCell(2).fill = { type: 'pattern', pattern: 'solid', fgColor: { argb: RED } }
    added.getCell(3).alignment = { wrapText: true }
  })
  sheet.getColumn(3).width = 70
  return wb.xlsx.writeBuffer()
}

const XLSX = 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'

export function BulkUpload({ headers, go, openProduct }) {
  const [config, setConfig] = useState(null)
  const [templates, setTemplates] = useState([])
  const [picked, setPicked] = useState([])
  const [tasks, setTasks] = useState([])
  const [msg, setMsg] = useState('')
  const [busy, setBusy] = useState('')
  const [results, setResults] = useState(null)

  const loadTasks = useCallback(() => { send(headers, '/seller/product-uploads').then((d) => setTasks(d.data)).catch(() => {}) }, [headers])
  useEffect(() => {
    send(headers, '/seller/catalog-config').then((d) => setConfig(d.data)).catch((e) => setMsg(e.message))
    send(headers, '/seller/shipping').then((d) => setTemplates(d.data.templates ?? [])).catch(() => {})
    Promise.resolve().then(loadTasks)
  }, [headers, loadTasks])

  if (!config) return <div className="sc-card"><p className="sc-muted">{msg || 'Loading…'}</p></div>

  async function generate() {
    setBusy('template')
    setMsg('')
    try {
      const cats = config.categories.filter((c) => picked.includes(c.id))
      const buffer = await buildTemplate(cats, config, templates)
      saveBlob(new Blob([buffer], { type: XLSX }), `nextech-product-template-${new Date().toISOString().slice(0, 10)}.xlsx`)
    } catch (e) { setMsg(e.message) } finally { setBusy('') }
  }

  async function uploadSheet(file) {
    if (!file) return
    setBusy('upload')
    setMsg('')
    try {
      const { rows, meta } = await readTemplate(file)
      if (!rows.length) throw new Error('The Template tab has no products yet.')
      if (rows.length > MAX_UPLOAD_ROWS) throw new Error(`Upload at most ${MAX_UPLOAD_ROWS} rows at a time.`)
      const body = new FormData()
      body.append('file', file)
      body.append('rows', JSON.stringify(rows))
      body.append('category_ids', JSON.stringify(meta.category_ids ?? []))
      const d = await send(headers, '/seller/product-uploads', 'POST', body)
      setResults(d.data)
      loadTasks()
    } catch (e) { setMsg(e.message) } finally { setBusy('') }
  }

  async function openResults(task) {
    try { setResults((await send(headers, `/seller/product-uploads/${task.id}`)).data) } catch (e) { setMsg(e.message) }
  }

  async function downloadUploaded(task) {
    try {
      const response = await fetch(`${API_URL}/seller/product-uploads/${task.id}/file`, { headers: headers() })
      if (!response.ok) throw new Error('Could not download the file.')
      saveBlob(await response.blob(), task.file_name)
    } catch (e) { setMsg(e.message) }
  }

  // Quick drafts from images alone — one draft per image, completed later.
  async function draftsFromImages(files) {
    if (!files?.length) return
    setBusy('images')
    setMsg('')
    const images = []
    try {
      for (const file of files) {
        const problem = await checkProductImage(file)
        if (problem) { setMsg(`${file.name}: ${problem}`); continue }
        const body = new FormData()
        body.append('file', file)
        images.push({ url: (await send(headers, '/seller/product-media', 'POST', body)).data.url, name: file.name })
      }
      if (images.length) {
        const d = await send(headers, '/seller/products/drafts-from-images', 'POST', { images })
        setMsg(`${d.data.created} draft${d.data.created === 1 ? '' : 's'} created — complete ${d.data.created === 1 ? 'it' : 'them'} under Manage products → Incomplete.`)
      }
    } catch (e) { setMsg(e.message) } finally { setBusy('') }
  }

  const STATUS = { processing: ['Processing', 'pending'], completed: ['Completed', 'approved'], action_required: ['Action required', 'rejected'] }

  return (
    <>
      <h1 className="sc-title">Add products via upload</h1>
      <p className="sc-muted">List products in bulk in three steps: download the latest template, fill in the Excel file, upload it.</p>
      {msg && <div className="sc-alert warn"><span>{msg}</span><button type="button" onClick={() => setMsg('')}>OK</button></div>}

      <div className="sc-card">
        <h2 className="sc-h2">Step 01 — Download the latest template</h2>
        <p className="sc-muted">Different categories need different templates. Choose up to {MAX_TEMPLATE_CATEGORIES} categories for the products you’ll upload. Always use the latest template — valid values and rules change from time to time.</p>
        <div className="bu-cats">
          {config.categories.map((c) => {
            const on = picked.includes(c.id)
            return <label key={c.id} className={`wz-cat${on ? ' on' : ''}`}><input type="checkbox" checked={on} disabled={!on && picked.length >= MAX_TEMPLATE_CATEGORIES} onChange={(e) => setPicked((p) => (e.target.checked ? [...p, c.id] : p.filter((x) => x !== c.id)))} />{c.name}</label>
          })}
        </div>
        <div className="ss-actions"><button type="button" className="sc-primary" disabled={!picked.length || !!busy} onClick={generate}>{busy === 'template' ? 'Generating…' : `Generate template (${picked.length}/${MAX_TEMPLATE_CATEGORIES})`}</button></div>
      </div>

      <div className="sc-card">
        <h2 className="sc-h2">Step 02 — Edit your Excel file</h2>
        <ul className="ob-list">
          <li><b>Template</b> tab: one row per SKU. Rows with the same <i>Contribution Goods</i> code are one product; product information must match on all of them.</li>
          <li><b>Data Definitions</b> tab: what every column means and its valid values.</li>
          <li>Colors: <span className="bu-swatch red" /> required · <span className="bu-swatch grey" /> leave empty for that category · <span className="bu-swatch orange" /> required only after an earlier answer · no color = optional.</li>
          <li>Images: 1:1, 800×800 px, 3 MB max (apparel 3:4, 1340×1785 px). Up to 10 per product, one public URL per cell. Video: 100 MB, 3 minutes, 720p max/min.</li>
          <li>Variation theme: one type or two joined with ×, e.g. <i>Color × Storage capacity</i>. Single-SKU products can leave it empty.</li>
        </ul>
      </div>

      <div className="sc-card">
        <h2 className="sc-h2">Step 03 — Upload your Excel file</h2>
        <label className="sc-primary bu-file">{busy === 'upload' ? 'Processing…' : 'Upload spreadsheet'}<input type="file" accept=".xlsx" disabled={!!busy} onChange={(e) => { const f = e.target.files?.[0]; e.target.value = ''; uploadSheet(f) }} /></label>
        <p className="sc-muted">No image URLs? Use the image upload tool instead: <label className="sc-link bu-inline">upload product images<input type="file" accept="image/jpeg,image/png" multiple disabled={!!busy} onChange={(e) => { const f = [...(e.target.files ?? [])]; e.target.value = ''; draftsFromImages(f) }} /></label> — each image becomes a draft to complete.{busy === 'images' && ' Uploading…'}</p>
        {tasks.length > 0 && (
          <div className="sc-table-wrap">
            <table className="sc-table">
              <thead><tr><th>File</th><th>Uploaded</th><th>Status</th><th>Records</th><th>With errors</th><th></th></tr></thead>
              <tbody>{tasks.map((t) => (
                <tr key={t.id}>
                  <td>{t.file_name}</td><td>{dateTime(t.created_at)}</td>
                  <td><span className={`sc-pill ${STATUS[t.status]?.[1] ?? ''}`}>{STATUS[t.status]?.[0] ?? t.status}</span></td>
                  <td>{t.records}</td><td className={t.error_records ? 'sc-low' : ''}>{t.error_records}</td>
                  <td className="sc-actions"><button type="button" onClick={() => openResults(t)}>View processing results</button><button type="button" onClick={async () => { const full = (await send(headers, `/seller/product-uploads/${t.id}`)).data; saveBlob(new Blob([await resultsWorkbook(full)], { type: XLSX }), `results-${t.file_name}`) }}>Download processing results</button><button type="button" onClick={() => downloadUploaded(t)}>Uploaded file</button></td>
                </tr>
              ))}</tbody>
            </table>
          </div>
        )}
        <p className="sc-muted"><b>Completed</b>: every product was submitted and goes on sale once NexTech approves it. <b>Action required</b>: some products have errors and were saved as drafts — fix them under <button type="button" className="sc-link" onClick={() => go('products', 'draft')}>Manage products → Incomplete</button>.</p>
      </div>

      {results && (
        <div className="ss-overlay" role="presentation" onClick={() => setResults(null)}>
          <div className="ss-modal ss-wide" onClick={(e) => e.stopPropagation()}>
            <div className="sc-head"><h2 className="sc-h2">Processing results — {results.file_name}</h2><span className={`sc-pill ${STATUS[results.status]?.[1] ?? ''}`}>{STATUS[results.status]?.[0]}</span></div>
            <p>Records processed from this upload: <b>{results.records}</b> · Records with errors: <b className={results.error_records ? 'sc-low' : ''}>{results.error_records}</b></p>
            {results.error_records > 0 && <p className="sc-alert warn">Some products need more information. Open each draft to add what’s missing — images, videos, product guides or other details — then submit.</p>}
            <div className="sc-table-wrap">
              <table className="sc-table">
                <thead><tr><th>Row</th><th>Product</th><th>Result</th><th>What to fix</th><th></th></tr></thead>
                <tbody>{(results.results ?? []).map((r) => {
                  const row = results.rows?.[r.row] ?? {}
                  return (
                    <tr key={r.row}>
                      <td>{row._sheet_row ?? r.row + 4}</td>
                      <td>{row.product_name || '—'}{row.contribution_sku && <small className="sc-muted">{row.contribution_sku}</small>}</td>
                      <td><span className={`sc-pill ${r.status === 'submitted' ? 'approved' : 'rejected'}`}>{{ submitted: 'Submitted', draft: 'Draft', failed: 'Failed' }[r.status]}</span></td>
                      <td>{r.messages.length ? <ul className="ob-list">{r.messages.map((m) => <li key={m}>{m}</li>)}</ul> : <span className="sc-muted">—</span>}</td>
                      <td>{r.status === 'draft' && r.product_id && <button type="button" className="sc-link" onClick={() => { setResults(null); openProduct(r.product_id) }}>Upload files &amp; fix</button>}</td>
                    </tr>
                  )
                })}</tbody>
              </table>
            </div>
            <div className="ss-actions"><button type="button" className="seller-btn ghost" onClick={async () => saveBlob(new Blob([await resultsWorkbook(results)], { type: XLSX }), `results-${results.file_name}`)}>Download processing results</button><button type="button" className="sc-primary" onClick={() => setResults(null)}>Close</button></div>
          </div>
        </div>
      )}
    </>
  )
}

// ---------------------------------------------------------------------------
// Pricing health: sales boost offers + pricing records
// ---------------------------------------------------------------------------
export function PricingHealth({ headers, onChanged }) {
  const [tab, setTab] = useState('boost')
  const [groups, setGroups] = useState(null)
  const [records, setRecords] = useState([])
  const [selected, setSelected] = useState([])
  const [msg, setMsg] = useState('')

  const load = useCallback(() => {
    send(headers, '/seller/sales-boost').then((d) => setGroups(d.data)).catch((e) => setMsg(e.message))
    send(headers, '/seller/pricing-records').then((d) => setRecords(d.data)).catch(() => {})
  }, [headers])
  useEffect(() => { Promise.resolve().then(load) }, [load])

  async function decide(ids, decision) {
    if (!ids.length) return
    if (decision === 'reject' && !window.confirm(`Reject ${ids.length === 1 ? 'this offer' : `${ids.length} offers`}? Each rejected variation is closed (taken off sale).`)) return
    try {
      await send(headers, '/seller/sales-boost/decide', 'POST', { offer_ids: ids, decision })
      setSelected([])
      setMsg(decision === 'accept' ? 'Prices adjusted — see Pricing records.' : 'Offers rejected and those variations closed.')
      load()
      onChanged?.()
    } catch (e) { setMsg(e.message) }
  }

  const pending = (groups ?? []).flatMap((g) => g.offers.filter((o) => o.status === 'pending').map((o) => o.id))

  return (
    <>
      <h1 className="sc-title">Pricing health</h1>
      {msg && <div className="sc-alert warn"><span>{msg}</span><button type="button" onClick={() => setMsg('')}>OK</button></div>}
      <div className="sc-tabs">
        <button type="button" className={tab === 'boost' ? 'active' : ''} onClick={() => setTab('boost')}>Sales boost <small>{groups?.length ?? 0}</small></button>
        <button type="button" className={tab === 'records' ? 'active' : ''} onClick={() => setTab('records')}>Pricing records <small>{records.length}</small></button>
      </div>
      {tab === 'boost' ? (
        <>
          <div className="sc-card">
            <p>Products with too little price advantage are marked <span className="sc-pill rejected">Low traffic</span> and shown less. NexTech suggests a lower price for each affected variation — it’s your choice: <b>adjust</b> to the recommended price, or <b>reject</b> the offer and close that variation.</p>
            <p className="sc-muted">A product regains full search and recommendation exposure once all its offers are handled, with at least one price adjusted.</p>
          </div>
          {groups && groups.length === 0 && <div className="sc-card"><p className="sc-muted">No sales boost offers right now.</p></div>}
          {pending.length > 0 && (
            <div className="sc-filter pb-batch">
              <label className="sc-check"><input type="checkbox" checked={selected.length === pending.length} onChange={(e) => setSelected(e.target.checked ? pending : [])} /> Select all</label>
              <button type="button" disabled={!selected.length} onClick={() => decide(selected, 'accept')}>Adjust price ({selected.length})</button>
              <button type="button" disabled={!selected.length} onClick={() => decide(selected, 'reject')}>Reject &amp; close ({selected.length})</button>
            </div>
          )}
          {(groups ?? []).map((g) => (
            <div className="sc-card" key={g.product.id}>
              <div className="sc-head">
                <h2 className="sc-h2" title={g.offers.map((o) => `${o.variant?.label ?? 'Product'}: ${o.status}`).join('\n')}>{g.product.name} <small className="sc-muted">{g.product.sku}</small></h2>
                <span className="pb-progress" title={`${g.processed} of ${g.total} variations handled`}><i style={{ width: `${(g.processed / g.total) * 100}%` }} />{g.processed}/{g.total} handled</span>
              </div>
              <div className="sc-table-wrap">
                <table className="sc-table">
                  <thead><tr><th></th><th>Variation</th><th>Current price</th><th>Recommended price</th><th>Status</th><th></th></tr></thead>
                  <tbody>{g.offers.map((o) => (
                    <tr key={o.id}>
                      <td>{o.status === 'pending' && <input type="checkbox" checked={selected.includes(o.id)} onChange={(e) => setSelected((s) => (e.target.checked ? [...s, o.id] : s.filter((x) => x !== o.id)))} />}</td>
                      <td>{o.variant ? <>{o.variant.label}<small className="sc-muted">{o.variant.sku}</small></> : 'Whole product'}</td>
                      <td>{money(o.current_price_cents)}</td>
                      <td><b>{money(o.recommended_price_cents)}</b><small className="sc-muted">{Math.round((1 - o.recommended_price_cents / o.current_price_cents) * 100)}% lower</small></td>
                      <td><span className={`sc-pill ${o.status === 'accepted' ? 'approved' : o.status === 'rejected' ? 'rejected' : 'pending'}`}>{{ pending: 'Waiting for you', accepted: 'Price adjusted', rejected: 'Closed' }[o.status]}</span></td>
                      <td className="sc-actions">{o.status === 'pending' && <><button type="button" onClick={() => decide([o.id], 'accept')}>Adjust price</button><button type="button" className="danger" onClick={() => decide([o.id], 'reject')}>Reject</button></>}</td>
                    </tr>
                  ))}</tbody>
                </table>
              </div>
              {g.processed > 0 && g.accepted === 0 && <p className="ob-missing">Adjust at least one variation’s price for this product to get the sales boost.</p>}
            </div>
          ))}
        </>
      ) : (
        <div className="sc-card sc-table-card">
          <div className="sc-table-wrap">
            <table className="sc-table">
              <thead><tr><th>Date</th><th>Product</th><th>Variation</th><th>Old price</th><th>New price</th><th>Reason</th></tr></thead>
              <tbody>
                {records.map((r) => <tr key={r.id}><td>{dateTime(r.created_at)}</td><td>{r.product?.name}</td><td>{r.variant?.label ?? '—'}</td><td>{money(r.old_price_cents)}</td><td><b>{money(r.new_price_cents)}</b></td><td>Sales boost offer accepted</td></tr>)}
                {records.length === 0 && <tr><td colSpan="6" className="sc-empty">No price changes yet.</td></tr>}
              </tbody>
            </table>
          </div>
        </div>
      )}
    </>
  )
}

// Once a day, on the first visit: which products have sales boost offers.
export function SalesBoostPopup({ headers, shopId, go }) {
  const [groups, setGroups] = useState(null)
  useEffect(() => {
    const key = `nextech_boost_popup_${shopId}`
    const today = new Date().toISOString().slice(0, 10)
    let seen = null
    try { seen = localStorage.getItem(key) } catch { /* private mode */ }
    if (seen === today) return undefined
    let cancelled = false
    send(headers, '/seller/sales-boost').then((d) => {
      if (cancelled || !d.data.length) return
      setGroups(d.data)
      try { localStorage.setItem(key, today) } catch { /* ignore */ }
    }).catch(() => {})
    return () => { cancelled = true }
  }, [headers, shopId])
  if (!groups) return null
  return (
    <div className="ss-overlay" role="presentation" onClick={() => setGroups(null)}>
      <div className="ss-modal" onClick={(e) => e.stopPropagation()}>
        <h2 className="sc-h2">Sales boost offers for your products</h2>
        <p className="sc-muted">These products are marked Low traffic. Adjusting to the recommended price can bring back visibility.</p>
        <ul className="ob-people">{groups.map((g) => <li key={g.product.id}><span><b>{g.product.name}</b> <small className="sc-muted">{g.total - g.processed} offer{g.total - g.processed === 1 ? '' : 's'} waiting</small></span></li>)}</ul>
        <div className="ss-actions"><button type="button" className="seller-btn ghost" onClick={() => setGroups(null)}>Later</button><button type="button" className="sc-primary" onClick={() => { setGroups(null); go('pricing') }}>View offers</button></div>
      </div>
    </div>
  )
}

// ---------------------------------------------------------------------------
// Product compliance
// ---------------------------------------------------------------------------
const applies = (field, details) => Object.entries(field.when ?? {}).every(([key, vals]) => [].concat(details?.[key] ?? []).some((v) => vals.map(String).includes(String(v))))

export function ProductCompliance({ headers, products, reload }) {
  const [config, setConfig] = useState(null)
  const [open, setOpen] = useState(null) // { product, documents, country_of_origin }
  const [msg, setMsg] = useState('')
  const [busy, setBusy] = useState(false)
  useEffect(() => { send(headers, '/seller/catalog-config').then((d) => setConfig(d.data)).catch((e) => setMsg(e.message)) }, [headers])
  if (!config) return <div className="sc-card"><p className="sc-muted">{msg || 'Loading…'}</p></div>

  const docsFor = (p) => (config.categories.find((c) => c.id === p.category_id)?.compliance ?? []).filter((d) => applies(d, p.product_details))

  async function save() {
    setBusy(true)
    try {
      await send(headers, `/seller/products/${open.product.id}/compliance`, 'PATCH', { documents: open.documents, country_of_origin: open.country_of_origin || null })
      setOpen(null)
      setMsg('Compliance information saved.')
      reload()
    } catch (e) { setMsg(e.message) } finally { setBusy(false) }
  }

  return (
    <>
      <h1 className="sc-title">Product compliance</h1>
      <p className="sc-muted">Different categories and markets need different documents. Products can only be listed once their required documents are in. Make sure everything is accurate — false information can lead to penalties, compensation to buyers and legal consequences.</p>
      {msg && <div className="sc-alert warn"><span>{msg}</span><button type="button" onClick={() => setMsg('')}>OK</button></div>}
      <div className="sc-card sc-table-card">
        <div className="sc-table-wrap">
          <table className="sc-table">
            <thead><tr><th>Product</th><th>Country of origin</th><th>Documents</th><th></th></tr></thead>
            <tbody>
              {products.map((p) => (
                <tr key={p.id}>
                  <td><b>{p.name}</b><small className="sc-muted">{p.category?.name ?? 'No category yet'}</small></td>
                  <td>{p.country_of_origin || <span className="sc-low">Missing</span>}</td>
                  <td>{p.missing_compliance?.length ? <span className="sc-low">Missing: {p.missing_compliance.join(', ')}</span> : <span className="sc-pill approved">Complete</span>}<small className="sc-muted">{(p.compliance?.documents ?? []).length} uploaded</small></td>
                  <td className="sc-actions"><button type="button" onClick={() => setOpen({ product: p, documents: p.compliance?.documents ?? [], country_of_origin: p.country_of_origin ?? '' })}>Upload / view</button></td>
                </tr>
              ))}
              {products.length === 0 && <tr><td colSpan="4" className="sc-empty">No products yet.</td></tr>}
            </tbody>
          </table>
        </div>
      </div>
      {open && (
        <div className="ss-overlay" role="presentation" onClick={() => setOpen(null)}>
          <div className="ss-modal ss-wide" onClick={(e) => e.stopPropagation()}>
            <h2 className="sc-h2">Compliance — {open.product.name}</h2>
            <label>Country/Region of origin<input value={open.country_of_origin} onChange={(e) => setOpen({ ...open, country_of_origin: e.target.value })} /></label>
            <ul className="ob-people">
              {docsFor(open.product).map((d) => (
                <li key={d.key}>
                  <span><b>{d.label}</b>{d.required ? <small className="wz-req"> required</small> : <small className="sc-muted"> optional</small>}{open.documents.filter((x) => x.type === d.key).map((h) => <small key={h.path} className="sc-muted"> · {h.name} <button type="button" className="sc-link" onClick={() => setOpen({ ...open, documents: open.documents.filter((x) => x !== h) })}>remove</button></small>)}</span>
                  <label className="seller-btn ghost wz-upload">Upload<input type="file" accept=".pdf,.jpg,.jpeg,.png" disabled={busy} onChange={async (e) => {
                    const f = e.target.files?.[0]
                    e.target.value = ''
                    if (!f) return
                    try { const path = await uploadDoc(headers, 'product_document', f); setOpen((o) => ({ ...o, documents: [...o.documents, { type: d.key, path, name: f.name }] })) } catch (err) { setMsg(err.message) }
                  }} /></label>
                </li>
              ))}
              {docsFor(open.product).length === 0 && <li><span className="sc-muted">Choose a category for this product first (edit the product).</span></li>}
            </ul>
            <div className="ss-actions"><button type="button" className="seller-btn ghost" onClick={() => setOpen(null)}>Cancel</button><button type="button" className="sc-primary" disabled={busy} onClick={save}>Save</button></div>
          </div>
        </div>
      )}
    </>
  )
}

// ---------------------------------------------------------------------------
// Account health: trademarks
// ---------------------------------------------------------------------------
export function AccountHealth({ headers, country }) {
  const [trademarks, setTrademarks] = useState(null)
  const [form, setForm] = useState(null)
  const [msg, setMsg] = useState('')
  const [busy, setBusy] = useState(false)
  const load = useCallback(() => { send(headers, '/seller/trademarks').then((d) => setTrademarks(d.data)).catch((e) => setMsg(e.message)) }, [headers])
  useEffect(() => { Promise.resolve().then(load) }, [load])

  async function uploadLogo(file, apply) {
    if (!file) return
    const body = new FormData()
    body.append('file', file)
    try { apply((await send(headers, '/seller/media', 'POST', body)).data.url) } catch (e) { setMsg(e.message) }
  }

  async function submit(event) {
    event.preventDefault()
    setBusy(true)
    try {
      await send(headers, '/seller/trademarks', 'POST', { name: form.name, registration_number: form.registration_number, registration_country: form.registration_country, certificate_path: form.certificate_path, logo_url: form.logo_url || null })
      setForm(null)
      setMsg('Trademark submitted — NexTech will review it.')
      load()
    } catch (e) { setMsg(e.message) } finally { setBusy(false) }
  }

  const STATUS = { pending: ['Under review', 'pending'], approved: ['Approved', 'approved'], rejected: ['Rejected', 'rejected'] }
  return (
    <>
      <div className="sc-head"><h1 className="sc-title">Account health</h1><button type="button" className="sc-primary" onClick={() => setForm({ name: '', registration_number: '', registration_country: country ?? 'US', certificate_path: '', certificate_name: '', logo_url: '' })}>+ Register a trademark</button></div>
      {msg && <div className="sc-alert warn"><span>{msg}</span><button type="button" onClick={() => setMsg('')}>OK</button></div>}
      <div className="sc-card">
        <h2 className="sc-h2">Trademarks</h2>
        <p className="sc-muted">Register the brands you sell under. Once NexTech approves a trademark you can pick it when adding products — branded products get a separate price assessment and better search matching. You can add a logo at any time.</p>
        <div className="sc-table-wrap">
          <table className="sc-table">
            <thead><tr><th>Logo</th><th>Trademark</th><th>Registration</th><th>Status</th><th></th></tr></thead>
            <tbody>
              {(trademarks ?? []).map((t) => (
                <tr key={t.id}>
                  <td>{t.logo_url ? <img className="ah-logo" src={mediaUrl(t.logo_url)} alt="" /> : <span className="sc-muted">—</span>}</td>
                  <td><b>{t.name}</b></td>
                  <td>{t.registration_number} <small className="sc-muted">{t.registration_country}</small></td>
                  <td><span className={`sc-pill ${STATUS[t.status]?.[1]}`}>{STATUS[t.status]?.[0]}</span>{t.note && <small className="sc-low">{t.note}</small>}</td>
                  <td className="sc-actions"><label className="sc-link bu-inline">{t.logo_url ? 'Change logo' : 'Add logo'}<input type="file" accept="image/*" onChange={(e) => { const f = e.target.files?.[0]; e.target.value = ''; uploadLogo(f, async (url) => { await send(headers, `/seller/trademarks/${t.id}`, 'PATCH', { logo_url: url }); load() }) }} /></label></td>
                </tr>
              ))}
              {trademarks?.length === 0 && <tr><td colSpan="5" className="sc-empty">No trademarks yet.</td></tr>}
            </tbody>
          </table>
        </div>
      </div>
      {form && (
        <div className="ss-overlay" role="presentation" onClick={() => setForm(null)}>
          <form className="ss-modal" onClick={(e) => e.stopPropagation()} onSubmit={submit}>
            <h2 className="sc-h2">Register a trademark</h2>
            <label>Trademark (brand name)<input required maxLength="120" value={form.name} onChange={(e) => setForm({ ...form, name: e.target.value })} /></label>
            <div className="ss-row">
              <label>Registration number<input required maxLength="60" value={form.registration_number} onChange={(e) => setForm({ ...form, registration_number: e.target.value })} /></label>
              <label>Registered in (country code)<input required maxLength="2" value={form.registration_country} onChange={(e) => setForm({ ...form, registration_country: e.target.value.toUpperCase() })} /></label>
            </div>
            <label>Registration certificate (PDF or image)<input type="file" accept=".pdf,.jpg,.jpeg,.png" onChange={async (e) => { const f = e.target.files?.[0]; e.target.value = ''; if (!f) return; try { const path = await uploadDoc(headers, 'trademark_certificate', f); setForm((x) => ({ ...x, certificate_path: path, certificate_name: f.name })) } catch (err) { setMsg(err.message) } }} /><small className="sc-muted">{form.certificate_name ? `Uploaded: ${form.certificate_name}` : 'Must show the trademark, the owner and the registration number.'}</small></label>
            <label>Logo (optional)<input type="file" accept="image/*" onChange={(e) => { const f = e.target.files?.[0]; e.target.value = ''; uploadLogo(f, (url) => setForm((x) => ({ ...x, logo_url: url }))) }} />{form.logo_url && <img className="ah-logo" src={mediaUrl(form.logo_url)} alt="" />}</label>
            <div className="ss-actions"><button type="button" className="seller-btn ghost" onClick={() => setForm(null)}>Cancel</button><button type="submit" className="sc-primary" disabled={busy || !form.certificate_path}>Submit for review</button></div>
          </form>
        </div>
      )}
    </>
  )
}
