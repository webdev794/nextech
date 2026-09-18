// Section types an admin can add to a CMS page. Kept in a plain module so both
// the storefront renderer and the admin editor can share it.
export const SECTION_TYPES = [
  ['rich_text', 'Rich text'],
  ['hero', 'Banner + text'],
  ['media_text', 'Image + text'],
  ['feature_grid', 'Feature grid'],
  ['stats', 'Stat counters'],
  ['steps', 'Numbered steps'],
  ['faq', 'FAQ accordion'],
  ['quote', 'Pull quote'],
  ['cta', 'Call to action'],
]

export const blankSection = (type) => ({
  rich_text: { type: 'rich_text', markdown: '' },
  hero: { type: 'hero', image_url: '', heading: '', text: '', button_label: '', button_url: '' },
  media_text: { type: 'media_text', image_url: '', image_side: 'left', heading: '', markdown: '' },
  feature_grid: { type: 'feature_grid', heading: '', items: [{ image_url: '', title: '', text: '', link_url: '' }] },
  stats: { type: 'stats', heading: '', items: [{ title: '', text: '' }] },
  steps: { type: 'steps', heading: '', items: [{ title: '', text: '' }] },
  faq: { type: 'faq', heading: '', items: [{ title: '', text: '' }] },
  quote: { type: 'quote', text: '', author: '' },
  cta: { type: 'cta', heading: '', text: '', button_label: '', button_url: '' },
}[type])
