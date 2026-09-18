export const colors = {
  bg: '#f6f6f2',
  surface: '#ffffff',
  ink: '#20291f',
  muted: '#7c857a',
  line: '#e4e7df',
  brand: '#141a12',
  accent: '#3f7d43',
  accentSoft: '#e4f3e4',
  danger: '#a23b28',
  dangerSoft: '#f6e3e0',
};

export const money = (cents) => `$${((cents ?? 0) / 100).toFixed(2)}`;

// A price is "on sale" when compareAt is set and higher than the current price.
export function saleInfo(priceCents, compareAtCents) {
  const onSale = compareAtCents != null && compareAtCents > priceCents;
  const pctOff = onSale ? Math.round((1 - priceCents / compareAtCents) * 100) : 0;
  return { onSale, pctOff };
}

export const STATUS_LABELS = {
  pending_payment: 'Awaiting payment',
  confirmed: 'Confirmed',
  packing: 'Packing',
  ready_for_delivery: 'Ready for delivery',
  out_for_delivery: 'Out for delivery',
  completed: 'Delivered',
  cancelled: 'Cancelled',
};

export const DELIVERY_STAGES = ['confirmed', 'packing', 'ready_for_delivery', 'out_for_delivery', 'completed'];
