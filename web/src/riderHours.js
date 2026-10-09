// Riders' working hours: day names and a short "Mon, Tue · 10:00–19:00" label.
export const DAYS = [[1, 'Mon'], [2, 'Tue'], [3, 'Wed'], [4, 'Thu'], [5, 'Fri'], [6, 'Sat'], [7, 'Sun']]

export function hoursLabel(h) {
  if (!h) return ''
  const names = DAYS.filter(([d]) => h.days.includes(d)).map(([, n]) => n)
  return `${names.join(', ')} · ${h.start}–${h.end} · ${h.days_off_per_month ?? 4} day${(h.days_off_per_month ?? 4) === 1 ? '' : 's'} off a month`
}
