import { createApp } from 'vue'
import { createPinia } from 'pinia'
import router from './router'
import App from './App.vue'
import { useToastStore } from './stores/toast'

import 'bulma/css/bulma.min.css'
import '@fortawesome/fontawesome-free/css/all.min.css'
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
