import { defineStore } from 'pinia'
import { ref, computed, watch } from 'vue'
import { useApi } from '../composables/useApi'
import { endpoints } from '../api/endpoints'
import type { Tag, TagCategory, Count } from '../types'

// Domain types are generated from the OpenAPI spec — re-export the ones used
// across the app so existing `from '../stores/gallery'` imports keep working.
export type { Tag, TagCategory, Media, MediaItem } from '../types'

export const useGalleryStore = defineStore('gallery', () => {
  const api = useApi()

  // State
  const pageTitle = ref('Gallery')
  const allTags = ref<Tag[]>([])
  const categories = ref<TagCategory[]>([])
  const totalMedia = ref(0)
  const blurThumbnails = ref(localStorage.getItem('blurThumbnails') === 'true')
  const loading = ref(false)
  const error = ref<string | null>(null)
  const initialized = ref(false)
  const lastViewedItemIds = ref<number[]>([])

  // Persist blur preference
  watch(blurThumbnails, (val) => {
    localStorage.setItem('blurThumbnails', String(val))
  })

  // Getters
  const tagNames = computed(() => allTags.value.map((t) => t.tag_name))

  // Actions
  async function initialize() {
    if (initialized.value) return
    initialized.value = true

    try {
      const [tags, total, cats] = await Promise.all([
        api.get<Tag[]>(endpoints.tags.list),
        api.get<Count>(endpoints.media.count),
        api.get<TagCategory[]>(endpoints.tagCategories.list),
      ])
      allTags.value = tags ?? []
      totalMedia.value = total?.count ?? 0
      categories.value = cats ?? []
    } catch (e) {
      console.error('Initialization error:', e)
      error.value = 'Failed to initialize gallery'
      initialized.value = false
    }
  }

  async function refreshTags() {
    try {
      const [tags, cats] = await Promise.all([
        api.get<Tag[]>(endpoints.tags.list),
        api.get<TagCategory[]>(endpoints.tagCategories.list),
      ])
      allTags.value = tags ?? []
      categories.value = cats ?? []
    } catch (e) {
      console.error('Error refreshing tags:', e)
    }
  }

  async function refreshTotals() {
    try {
      const total = await api.get<Count>(endpoints.media.count)
      totalMedia.value = total?.count ?? 0
    } catch (e) {
      console.error('Error refreshing totals:', e)
    }
  }

  function toggleBlur() {
    blurThumbnails.value = !blurThumbnails.value
  }

  return {
    pageTitle,
    allTags,
    categories,
    totalMedia,
    blurThumbnails,
    loading,
    error,
    lastViewedItemIds,
    tagNames,
    initialize,
    refreshTags,
    refreshTotals,
    toggleBlur,
  }
})
