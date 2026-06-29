import { beforeEach, describe, expect, it } from 'vitest'
import { createPinia, setActivePinia } from 'pinia'
import { nextTick } from 'vue'
import { useFavoritesStore } from '../stores/favorites'

describe('favorites store', () => {
  beforeEach(() => {
    localStorage.clear()
    setActivePinia(createPinia())
  })

  it('starts empty', () => {
    const store = useFavoritesStore()
    expect(store.count).toBe(0)
    expect(store.allIds()).toEqual([])
  })

  it('adds favorites idempotently', () => {
    const store = useFavoritesStore()
    store.add(5)
    expect(store.isFavorite(5)).toBe(true)
    expect(store.count).toBe(1)
    store.add(5)
    expect(store.count).toBe(1)
  })

  it('removes favorites', () => {
    const store = useFavoritesStore()
    store.add(5)
    store.remove(5)
    expect(store.isFavorite(5)).toBe(false)
    expect(store.count).toBe(0)
  })

  it('toggles favorites on and off', () => {
    const store = useFavoritesStore()
    store.toggle(7)
    expect(store.isFavorite(7)).toBe(true)
    store.toggle(7)
    expect(store.isFavorite(7)).toBe(false)
  })

  it('prunes ids that are no longer valid', () => {
    const store = useFavoritesStore()
    store.add(1)
    store.add(2)
    store.add(3)
    store.prune(new Set([2, 3]))
    expect(store.allIds().sort((a, b) => a - b)).toEqual([2, 3])
  })

  it('persists to localStorage on change', async () => {
    const store = useFavoritesStore()
    store.add(42)
    await nextTick()
    expect(JSON.parse(localStorage.getItem('gallery_favorites')!)).toContain(42)
  })

  it('loads existing favorites from localStorage', () => {
    localStorage.setItem('gallery_favorites', JSON.stringify([9, 10]))
    setActivePinia(createPinia())
    const store = useFavoritesStore()
    expect(store.isFavorite(9)).toBe(true)
    expect(store.count).toBe(2)
  })

  it('ignores corrupted localStorage data', () => {
    localStorage.setItem('gallery_favorites', 'not json')
    setActivePinia(createPinia())
    const store = useFavoritesStore()
    expect(store.count).toBe(0)
  })
})
