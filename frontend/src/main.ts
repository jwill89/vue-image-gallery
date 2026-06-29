import { createApp } from 'vue'
import { createPinia } from 'pinia'
import router from './router'
import App from './App.vue'
import { useToastStore } from './stores/toast'

import 'bulma/css/bulma.min.css'
import './style.css'

const pinia = createPinia()
const app = createApp(App)
app.use(pinia)
app.use(router)

// Global error handler — logs and shows toast notification to user
app.config.errorHandler = (err, _instance, info) => {
  console.error('Unhandled error:', err)
  console.error('Info:', info)

  try {
    const toastStore = useToastStore()
    const message = err instanceof Error ? err.message : 'An unexpected error occurred'
    toastStore.error(message)
  } catch {
    // Fallback if toast store isn't available yet
  }
}

app.mount('#app')

// Register the service worker for thumbnail caching and prefetch
if ('serviceWorker' in navigator) {
  window.addEventListener('load', () => {
    navigator.serviceWorker.register('/sw.js').catch((err) => {
      console.warn('SW registration failed:', err)
    })
  })
}
