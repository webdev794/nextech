import { useEffect, useState } from 'react';
import { api } from './api';

// The store's name from Store settings (GET /config), so renaming the store in
// admin renames it in the app too. "NexTech" until it has loaded.
let cached = null;

export function useBrandName() {
  const [name, setName] = useState(cached ?? 'NexTech');
  useEffect(() => {
    if (cached) return;
    api.config()
      .then((d) => {
        const n = (d?.data?.branding?.store_name ?? '').trim();
        if (n) { cached = n; setName(n); }
      })
      .catch(() => {});
  }, []);
  return name;
}
