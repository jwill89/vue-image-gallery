import { defineStore } from 'pinia'
import { ref, computed, watch } from 'vue'
import { useApi } from '../composables/useApi'

export interface Tag {
  tag_id: number
  tag_name: string
  category_id: number
  category_name?: string
  media_count?: number
}

export interface TagCategory {
  category_id: number
  category_name: string
  category_short: string
  color: string
  description: string
  sort_order: number
}

export interface MediaItem {
  media_id: number
  media_type: 'image' | 'video'
  file_name: string
  file_time: number
  hash: string
  bits_fingerprint?: string
  width?: number
  height?: number
  duration?: number
  file_size?: number
}

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
  const tagNames = computed(() => allTags.value.map(t => t.tag_name))

  // Actions
  async function initialize() {
    if (initialized.value) return
    initialized.value = true

    try {
      const [tags, total, cats] = await Promise.all([
        api.get<Tag[]>('/tags/all/'),
        api.get<number>('/media/total/'),
        api.get<TagCategory[]>('/tags/categories/')
      ])
      allTags.value = tags ?? []
      totalMedia.value = total ?? 0
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
        api.get<Tag[]>('/tags/all/'),
        api.get<TagCategory[]>('/tags/categories/')
      ])
      allTags.value = tags ?? []
      categories.value = cats ?? []
    } catch (e) {
      console.error('Error refreshing tags:', e)
    }
  }

  async function refreshTotals() {
    try {
      const total = await api.get<number>('/media/total/')
      totalMedia.value = total ?? 0
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
    toggleBlur
  }
})
