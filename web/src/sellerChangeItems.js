// Application items an admin can send back for fixing ("Request changes"),
// with the wizard step each lives on. Keys match AdminSellerController::CHANGE_ITEMS.
export const CHANGE_ITEMS = [
  ['business_type', 'Business type', 1],
  ['company_name', 'Company / registered name', 1],
  ['tax_id', 'Tax ID', 1],
  ['registered_address', 'Registered address', 1],
  ['contact_name', 'Contact / legal name', 2],
  ['id_details', 'ID type & number', 2],
  ['date_of_birth', 'Date of birth', 2],
  ['id_document', 'ID document', 2],
  ['shop_name', 'Shop name', 3],
  ['shop_logo', 'Shop logo', 3],
  ['shop_category', 'Primary category', 3],
  ['shop_description', 'Shop description', 3],
  ['pickup_address', 'Pickup address & phone', 3],
  ['business_document', 'Business document', 4],
  ['address_document', 'Proof of address', 4],
]

export const changeItemLabel = (key) => CHANGE_ITEMS.find(([k]) => k === key)?.[1] ?? key
export const changeItemStep = (key) => CHANGE_ITEMS.find(([k]) => k === key)?.[2] ?? 1
