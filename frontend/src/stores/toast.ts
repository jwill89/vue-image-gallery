import { defineStore } from 'pinia'
import { ref } from 'vue'

export interface Toast {
  id: number
  title: string
  message: string
  type: 'success' | 'danger' | 'warning' | 'info'
  duration: number
}

const TYPE_TITLES: Record<Toast['type'], string> = {
  success: 'Success',
  danger: 'Error',
  warning: 'Warning',
  info: 'Notice',
}

let nextId = 0

export const useToastStore = defineStore('toast', () => {
  const toasts = ref<Toast[]>([])

  function add(message: string, type: Toast['type'] = 'danger', duration = 5000, title?: string) {
    const id = nextId++
    toasts.value.push({
      id,
      title: title ?? TYPE_TITLES[type],
      message,
      type,
      duration,
    })

    if (duration > 0) {
      setTimeout(() => remove(id), duration)
    }
  }

  function remove(id: number) {
    toasts.value = toasts.value.filter(t => t.id !== id)
  }

  function success(message: string, duration = 4000, title?: string) {
    add(message, 'success', duration, title)
  }

  function error(message: string, duration = 6000, title?: string) {
    add(message, 'danger', duration, title)
  }

  function warning(message: string, duration = 5000, title?: string) {
    add(message, 'warning', duration, title)
  }

  function info(message: string, duration = 4000, title?: string) {
    add(message, 'info', duration, title)
  }

  return { toasts, add, remove, success, error, warning, info }
})
