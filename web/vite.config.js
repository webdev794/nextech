import process from 'node:process'
import react from '@vitejs/plugin-react'
import { defineConfig, loadEnv } from 'vite'

// https://vite.dev/config/
export default defineConfig(({ mode }) => {
  const env = loadEnv(mode, process.cwd(), '')

  return {
    // Set VITE_BASE (e.g. /gdp/) when the app is served from a sub-path.
    base: env.VITE_BASE || '/',
    plugins: [react()],
    // Dev only: live.json (the "something changed" marker) comes through Vite, so the
    // browser can read it from the page's own address (same-origin in production).
    server: {
      proxy: {
        '/live.json': (env.VITE_API_URL || 'http://127.0.0.1:8000/api').replace(/\/api\/?$/, ''),
      },
    },
    // Leaflet is only imported dynamically (map pickers), so Vite doesn't
    // pre-bundle it during startup — pre-declaring it here means the first map
    // open doesn't stall the page to optimize the dependency.
    optimizeDeps: {
      include: ['leaflet'],
    },
  }
})
