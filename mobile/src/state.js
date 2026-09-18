import React, { createContext, useContext, useEffect, useMemo, useState } from 'react';
import AsyncStorage from '@react-native-async-storage/async-storage';
import { api, loadToken, setToken } from './api';

const CART_KEY = 'nextech_cart';
const LOCATION_KEY = 'nextech_location';

const AppContext = createContext(null);

export function AppProvider({ children }) {
  const [booting, setBooting] = useState(true);
  const [user, setUser] = useState(null);
  const [cart, setCart] = useState([]);
  const [config, setConfig] = useState(null);
  // { lat, lng } once the customer shares their location — scopes the catalog to
  // the store that serves them and feeds the checkout delivery check.
  const [deliveryLocation, setDeliveryLocation] = useState(null);

  useEffect(() => {
    (async () => {
      try {
        const stored = await AsyncStorage.getItem(CART_KEY);
        if (stored) setCart(JSON.parse(stored));
      } catch {}
      try {
        const loc = await AsyncStorage.getItem(LOCATION_KEY);
        if (loc) setDeliveryLocation(JSON.parse(loc));
      } catch {}
      try {
        const { data } = await api.config();
        setConfig(data);
      } catch {}
      const token = await loadToken();
      if (token) {
        try {
          setUser(await api.me());
        } catch {
          await setToken(null);
        }
      }
      setBooting(false);
    })();
  }, []);

  useEffect(() => {
    AsyncStorage.setItem(CART_KEY, JSON.stringify(cart)).catch(() => {});
  }, [cart]);

  const value = useMemo(() => {
    const cartCount = cart.reduce((sum, item) => sum + item.quantity, 0);
    const cartTotal = cart.reduce((sum, item) => sum + item.price_cents * item.quantity, 0);

    return {
      booting,
      user,
      config,
      cart,
      cartCount,
      cartTotal,
      deliveryLocation,
      setDeliveryLocation(loc) {
        // loc is { lat, lng } or null to forget it.
        setDeliveryLocation(loc);
        if (loc) AsyncStorage.setItem(LOCATION_KEY, JSON.stringify(loc)).catch(() => {});
        else AsyncStorage.removeItem(LOCATION_KEY).catch(() => {});
      },
      async signIn(token) {
        await setToken(token);
        setUser(await api.me());
      },
      async signOut() {
        try {
          await api.logout();
        } catch {}
        await setToken(null);
        setUser(null);
      },
      async refreshUser() {
        try {
          setUser(await api.me());
        } catch {}
      },
      // variant is optional — omit it (or pass null) to add the base product.
      // Cart lines are keyed by product+variant so different options of the
      // same product sit in the cart as separate lines.
      addToCart(product, variant) {
        const key = `${product.id}:${variant?.id ?? ''}`;
        setCart((current) => {
          const found = current.find((item) => item.key === key);
          if (found) {
            return current.map((item) =>
              item.key === key ? { ...item, quantity: item.quantity + 1 } : item
            );
          }
          return [
            ...current,
            {
              key,
              id: product.id,
              variantId: variant?.id ?? null,
              variantLabel: variant?.label ?? null,
              name: product.name,
              price_cents: variant ? variant.price_cents : product.price_cents,
              compare_at_price_cents: (variant ? variant.compare_at_price_cents : product.compare_at_price_cents) ?? null,
              quantity: 1,
            },
          ];
        });
      },
      changeQuantity(key, delta) {
        setCart((current) =>
          current.flatMap((item) => {
            if (item.key !== key) return [item];
            const quantity = item.quantity + delta;
            return quantity > 0 ? [{ ...item, quantity }] : [];
          })
        );
      },
      clearCart() {
        setCart([]);
      },
    };
  }, [booting, user, config, cart, deliveryLocation]);

  return <AppContext.Provider value={value}>{children}</AppContext.Provider>;
}

export function useApp() {
  const context = useContext(AppContext);
  if (!context) throw new Error('useApp must be used inside AppProvider');
  return context;
}
