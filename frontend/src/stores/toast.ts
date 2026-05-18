import { defineStore } from 'pinia'
import { ref } from 'vue'

export interface Toast {
  id: number
  message: string
  type: 'success' | 'danger' | 'warning' | 'info'
  duration: number
}

let nextId = 0

export const useToastStore = defineStore('toast', () => {
  const toasts = ref<Toast[]>([])

  function add(message: string, type: Toast['type'] = 'danger', duration = 5000) {
    const id = nextId++
    toasts.value.push({ id, message, type, duration })

    if (duration > 0) {
      setTimeout(() => remove(id), duration)
    }
  }

  function remove(id: number) {
    toasts.value = toasts.value.filter(t => t.id !== id)
  }

  function success(message: string, duration = 4000) {
    add(message, 'success', duration)
  }

  function error(message: string, duration = 6000) {
    add(message, 'danger', duration)
  }

  function warning(message: string, duration = 5000) {
    add(message, 'warning', duration)
  }

  function info(message: string, duration = 4000) {
    add(message, 'info', duration)
  }

  return { toasts, add, remove, success, error, warning, info }
})

