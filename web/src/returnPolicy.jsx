import './returnPolicy.css'

// A product's return conditions (keys match App\Support\ReturnPolicy): which
// cases the seller accepts back, which they don't, and a note. Filled in the
// seller / admin product forms; shown to buyers on the product page.

const RETURN_ACCEPTS = [
  ['defective', 'Stopped working or defective in normal use'],
  ['damaged_in_transit', 'Arrived damaged'],
  ['wrong_item', 'Wrong item, or not as described'],
  ['missing_parts', 'Missing parts or accessories'],
  ['changed_mind', 'Changed mind (unused, in original packaging)'],
]

const RETURN_EXCLUDES = [
  ['physical_damage', 'Physical damage after delivery (dropped, cracked, bent)'],
  ['liquid_damage', 'Liquid or water damage'],
  ['mishandling', 'Misuse, tampering or repair by someone else'],
  ['used_or_opened', 'Used or opened (for change-of-mind returns)'],
  ['missing_packaging', 'Without the original box, accessories or bill'],
]

const toggle = (list, key, on) => (on ? [...new Set([...(list ?? []), key])] : (list ?? []).filter((k) => k !== key))

export function ReturnPolicyFields({ value, onChange, className = '' }) {
  const policy = value ?? {}
  const set = (patch) => onChange({ accepts: [], excludes: [], notes: '', ...policy, ...patch })
  return (
    <fieldset className={`return-policy-fields ${className}`.trim()}>
      <legend>Return conditions</legend>
      <p className="return-policy-hint">Tell buyers when they can return this item. Shown on the product page and linked from their order.</p>
      <div className="return-policy-cols">
        <div>
          <b>Returns accepted when</b>
          {RETURN_ACCEPTS.map(([key, label]) => <label key={key} className="return-policy-check"><input type="checkbox" checked={(policy.accepts ?? []).includes(key)} onChange={(e) => set({ accepts: toggle(policy.accepts, key, e.target.checked) })} /> {label}</label>)}
        </div>
        <div>
          <b>Not accepted</b>
          {RETURN_EXCLUDES.map(([key, label]) => <label key={key} className="return-policy-check"><input type="checkbox" checked={(policy.excludes ?? []).includes(key)} onChange={(e) => set({ excludes: toggle(policy.excludes, key, e.target.checked) })} /> {label}</label>)}
        </div>
      </div>
      <label className="return-policy-notes">Other conditions <small>optional</small><textarea rows={2} maxLength={1000} value={policy.notes ?? ''} placeholder="e.g. Report damage within 48 hours of delivery with photos." onChange={(e) => set({ notes: e.target.value })} /></label>
    </fieldset>
  )
}

const labelOf = (list, key) => list.find(([k]) => k === key)?.[1] ?? key

/** Buyer-facing: the return window, conditions and warranty terms. Renders nothing when there's nothing to say. */
export function ReturnPolicyView({ days, policy, warranty, warrantyTerms, id }) {
  const accepts = policy?.accepts ?? []
  const excludes = policy?.excludes ?? []
  const hasWarranty = warranty && warranty !== 'No warranty'
  if (!accepts.length && !excludes.length && !policy?.notes && !hasWarranty) return null
  return (
    <section className="return-policy-view" id={id}>
      <h3>Returns &amp; warranty</h3>
      {days != null && <p>{Number(days) === 0 ? 'This item can’t be returned.' : `Returns accepted within ${days} days of delivery.`}</p>}
      {accepts.length > 0 && <><b>Returns accepted when</b><ul>{accepts.map((k) => <li key={k}>✓ {labelOf(RETURN_ACCEPTS, k)}</li>)}</ul></>}
      {excludes.length > 0 && <><b>Not accepted</b><ul className="return-policy-no">{excludes.map((k) => <li key={k}>✕ {labelOf(RETURN_EXCLUDES, k)}</li>)}</ul></>}
      {policy?.notes && <p>{policy.notes}</p>}
      {hasWarranty && <><b>Warranty: {warranty}</b>{warrantyTerms && <p>{warrantyTerms}</p>}</>}
    </section>
  )
}
