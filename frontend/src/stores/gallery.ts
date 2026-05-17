import { defineStore } from 'pinia'
import { ref, computed } from 'vue'
import { useApi } from '../composables/useApi'

export interface Tag {
  tag_id: number
  tag_name: string
  category_id: number
  category_name?: string
  image_count?: number
  video_count?: number
}

export interface MediaItem {
  image_id?: number
  video_id?: number
  file_name: string
  file_time: number
  hash: string
  bits_fingerprint?: string
}

export const useGalleryStore = defineStore('gallery', () => {
  const api = useApi()

  // State
  const pageTitle = ref('Gallery')
  const allTags = ref<Tag[]>([])
  const totalImages = ref(0)
  const totalVideos = ref(0)
  const blurThumbnails = ref(false)
  const loading = ref(false)
  const error = ref<string | null>(null)

  // Getters
  const tagNames = computed(() => allTags.value.map(t => t.tag_name))

  // Actions
  async function initialize() {
    try {
      const [title, tags, imgTotal, vidTotal] = await Promise.all([
        api.get<string>('/config/title/'),
        api.get<Tag[]>('/tags/all/'),
        api.get<number>('/images/total/'),
        api.get<number>('/videos/total/')
      ])
      pageTitle.value = title
      allTags.value = tags ?? []
      totalImages.value = imgTotal ?? 0
      totalVideos.value = vidTotal ?? 0
    } catch (e) {
      console.error('Initialization error:', e)
      error.value = 'Failed to initialize gallery'
    }
  }

  async function refreshTags() {
    try {
      const tags = await api.get<Tag[]>('/tags/all/')
      allTags.value = tags ?? []
    } catch (e) {
      console.error('Error refreshing tags:', e)
    }
  }

  function toggleBlur() {
    blurThumbnails.value = !blurThumbnails.value
  }

  return {
    pageTitle,
    allTags,
    totalImages,
    totalVideos,
    blurThumbnails,
    loading,
    error,
    tagNames,
    initialize,
    refreshTags,
    toggleBlur
  }
})

