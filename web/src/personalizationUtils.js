// Is the buyer's input enough to add a personalized product to the cart?
export function personalizationReady(settings, value) {
  if (!settings?.enabled) return true
  return !settings.required || (value?.photos ?? []).length > 0
}
