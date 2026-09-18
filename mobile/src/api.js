import AsyncStorage from '@react-native-async-storage/async-storage';
import { API_URL } from './config';

const TOKEN_KEY = 'nextech_token';

let memoryToken = null;

export async function loadToken() {
  if (memoryToken) return memoryToken;
  memoryToken = await AsyncStorage.getItem(TOKEN_KEY);
  return memoryToken;
}

export async function setToken(token) {
  memoryToken = token;
  if (token) await AsyncStorage.setItem(TOKEN_KEY, token);
  else await AsyncStorage.removeItem(TOKEN_KEY);
}

async function request(path, { method = 'GET', body, auth = false } = {}) {
  const headers = { Accept: 'application/json' };
  if (body !== undefined) headers['Content-Type'] = 'application/json';
  if (auth) {
    const token = await loadToken();
    if (token) headers.Authorization = `Bearer ${token}`;
  }

  let response;
  try {
    response = await fetch(`${API_URL}${path}`, {
      method,
      headers,
      body: body !== undefined ? JSON.stringify(body) : undefined,
    });
  } catch (error) {
    throw new Error(`Cannot reach the API at ${API_URL}. Is Laravel running?`);
  }

  const text = await response.text();
  const data = text ? JSON.parse(text) : {};

  if (!response.ok) {
    const message =
      data.message ||
      Object.values(data.errors || {})[0]?.[0] ||
      `Request failed (${response.status}).`;
    const err = new Error(message);
    err.status = response.status;
    err.data = data;
    throw err;
  }

  return data;
}

export const api = {
  config: () => request('/config'),

  register: (payload) => request('/auth/register', { method: 'POST', body: payload }),
  login: (payload) => request('/auth/login', { method: 'POST', body: payload }),
  verifyOtp: (payload) => request('/auth/verify-otp', { method: 'POST', body: payload }),
  resendOtp: (payload) => request('/auth/resend-otp', { method: 'POST', body: payload }),
  me: () => request('/user', { auth: true }),
  updateProfile: (payload) => request('/profile', { method: 'PATCH', body: payload, auth: true }),
  logout: () => request('/auth/logout', { method: 'POST', auth: true }),

  categories: ({ lat, lng } = {}) => {
    const params = new URLSearchParams();
    if (lat != null && lng != null) { params.set('lat', lat); params.set('lng', lng); }
    const query = params.toString();
    return request(`/categories${query ? `?${query}` : ''}`);
  },
  products: ({ search, category, lat, lng } = {}) => {
    const params = new URLSearchParams();
    if (search) params.set('search', search);
    if (category) params.set('category', category);
    // Location scopes the catalog to the store that serves the customer, so a
    // product the nearest store doesn't stock is left out (see backend).
    if (lat != null && lng != null) { params.set('lat', lat); params.set('lng', lng); }
    const query = params.toString();
    return request(`/products${query ? `?${query}` : ''}`);
  },
  product: (slug, { lat, lng } = {}) => {
    const params = new URLSearchParams();
    if (lat != null && lng != null) { params.set('lat', lat); params.set('lng', lng); }
    const query = params.toString();
    return request(`/products/${slug}${query ? `?${query}` : ''}`);
  },

  addCartItem: (product_id, quantity, product_variant_id, coords = {}) =>
    request('/cart/items', {
      method: 'POST',
      body: { product_id, quantity, product_variant_id: product_variant_id ?? undefined, ...coords },
      auth: true,
    }),
  clearCart: () => request('/cart', { method: 'DELETE', auth: true }),

  addresses: () => request('/addresses', { auth: true }),
  addAddress: (payload) => request('/addresses', { method: 'POST', body: payload, auth: true }),

  checkout: (payload) => request('/checkout', { method: 'POST', body: payload, auth: true }),
  paymentIntent: (orderId) =>
    request(`/orders/${orderId}/payment-intent`, { method: 'POST', auth: true }),
  orders: () => request('/orders', { auth: true }),
  order: (id) => request(`/orders/${id}`, { auth: true }),
  cancelOrder: (id) => request(`/orders/${id}/cancel`, { method: 'POST', auth: true }),

  supportThreads: () => request('/support/threads', { auth: true }),
  supportThread: (id) => request(`/support/threads/${id}`, { auth: true }),
  createSupportThread: (payload) =>
    request('/support/threads', { method: 'POST', body: payload, auth: true }),
  supportReply: (id, body) =>
    request(`/support/threads/${id}/messages`, { method: 'POST', body: { body }, auth: true }),
  rateSupportThread: (id, body) =>
    request(`/support/threads/${id}/rating`, { method: 'POST', body, auth: true }),

  riderOrders: () => request('/rider/orders', { auth: true }),
  riderStats: () => request('/rider/stats', { auth: true }),
  rateRider: (orderId, body) =>
    request(`/orders/${orderId}/rider-review`, { method: 'POST', body, auth: true }),
  riderLocation: (lat, lng) =>
    request('/rider/location', { method: 'POST', body: { lat, lng }, auth: true }),
  claimOrder: (id) => request(`/rider/orders/${id}/claim`, { method: 'POST', auth: true }),
  riderStatus: (id, status) =>
    request(`/rider/orders/${id}/status`, { method: 'POST', body: { status }, auth: true }),
  riderCashCollected: (id) =>
    request(`/rider/orders/${id}/cash-collected`, { method: 'POST', auth: true }),
  sendDeliveryOtp: (id) =>
    request(`/rider/orders/${id}/delivery-otp`, { method: 'POST', auth: true }),
  deliverOrder: (id, body) =>
    request(`/rider/orders/${id}/deliver`, { method: 'POST', body, auth: true }),
};

export const ISSUE_TYPES = [
  ['item_missing', 'Item missing'],
  ['item_damaged', 'Item damaged'],
  ['wrong_item', 'Wrong item'],
  ['not_delivered', "Didn't receive order"],
  ['payment_issue', 'Payment issue'],
  ['other', 'Something else'],
];
