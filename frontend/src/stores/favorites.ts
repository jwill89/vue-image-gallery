import { defineStore } from 'pinia'
import { ref, computed, watch } from 'vue'

const STORAGE_KEY = 'gallery_favorites'

function loadFromStorage(): Set<number> {
  try {
    const raw = localStorage.getItem(STORAGE_KEY)
    if (raw) {
      const arr = JSON.parse(raw)
      if (Array.isArray(arr)) return new Set(arr.filter(Number.isFinite))
    }
  } catch {
    // Corrupted data — start fresh
  }
  return new Set()
}

function saveToStorage(ids: Set<number>): void {
  localStorage.setItem(STORAGE_KEY, JSON.stringify([...ids]))
}

export const useFavoritesStore = defineStore('favorites', () => {
  const ids = ref(loadFromStorage())

  // Persist on every change
  watch(ids, (v) => saveToStorage(v), { deep: true })

  const count = computed(() => ids.value.size)

  function isFavorite(mediaId: number): boolean {
    return ids.value.has(mediaId)
  }

  function toggle(mediaId: number): void {
    const next = new Set(ids.value)
    if (next.has(mediaId)) {
      next.delete(mediaId)
    } else {
      next.add(mediaId)
    }
    ids.value = next
  }

  function add(mediaId: number): void {
    if (!ids.value.has(mediaId)) {
      const next = new Set(ids.value)
      next.add(mediaId)
      ids.value = next
    }
  }

  function remove(mediaId: number): void {
    if (ids.value.has(mediaId)) {
      const next = new Set(ids.value)
      next.delete(mediaId)
      ids.value = next
    }
  }

  /** Remove IDs that no longer exist in the database (called after fetching). */
  function prune(validIds: Set<number>): void {
    const next = new Set<number>()
    for (const id of ids.value) {
      if (validIds.has(id)) next.add(id)
    }
    if (next.size !== ids.value.size) {
      ids.value = next
    }
  }

  function allIds(): number[] {
    return [...ids.value]
  }

  return { ids, count, isFavorite, toggle, add, remove, prune, allIds }
})
