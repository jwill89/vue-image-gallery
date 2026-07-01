import { defineConfig } from 'vitest/config'
import vue from '@vitejs/plugin-vue'
import { createRequire } from 'node:module'

// Read the app version from package.json so the footer can display it without a
// runtime request. createRequire avoids JSON import-assertion syntax churn.
const pkg = createRequire(import.meta.url)('./package.json') as { version: string }

// https://vite.dev/config/
export default defineConfig({
  plugins: [vue()],
  define: {
    __APP_VERSION__: JSON.stringify(pkg.version),
  },
  server: {
    proxy: {
      '/api': {
        target: 'http://localhost',
        changeOrigin: true,
      },
      '/media': {
        target: 'http://localhost',
        changeOrigin: true,
      },
    },
  },
  build: {
    outDir: 'dist',
    emptyOutDir: true,
  },
  test: {
    environment: 'happy-dom',
    setupFiles: ['./vitest.setup.ts'],
    include: ['src/**/*.spec.ts'],
    restoreMocks: true,
    unstubGlobals: true,
  },
})
