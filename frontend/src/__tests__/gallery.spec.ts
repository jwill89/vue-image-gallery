import { beforeEach, describe, expect, it } from 'vitest'
import { createPinia, setActivePinia } from 'pinia'
import { nextTick } from 'vue'
import { useGalleryStore } from '../stores/gallery'

describe('gallery store', () => {
  beforeEach(() => {
    localStorage.clear()
    setActivePinia(createPinia())
  })

  it('toggles blur and persists the preference', async () => {
    const store = useGalleryStore()
    expect(store.blurThumbnails).toBe(false)

    store.toggleBlur()
    expect(store.blurThumbnails).toBe(true)
    await nextTick()
    expect(localStorage.getItem('blurThumbnails')).toBe('true')
  })

  it('reads the initial blur preference from localStorage', () => {
    localStorage.setItem('blurThumbnails', 'true')
    setActivePinia(createPinia())

    expect(useGalleryStore().blurThumbnails).toBe(true)
  })

  it('derives tagNames from allTags', () => {
    const store = useGalleryStore()
    store.allTags = [
      { tag_id: 1, tag_name: 'alpha', category_id: 1 },
      { tag_id: 2, tag_name: 'beta', category_id: 1 },
    ]

    expect(store.tagNames).toEqual(['alpha', 'beta'])
  })
})
