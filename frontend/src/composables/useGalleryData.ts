import { ref } from 'vue'
import { useApi } from './useApi'
import { useToastStore } from '../stores/toast'
import type { MediaItem } from '../stores/gallery'

/**
 * Response shape from the paginated media endpoints.
 * Items + pagination metadata in a single response.
 */
interface PaginatedResponse {
  items: MediaItem[]
  total_pages: number
  current_page: number
}

export function useGalleryData() {
  const api = useApi()
  const toastStore = useToastStore()
  const items = ref<MediaItem[]>([])
  const totalPages = ref(0)
  const loading = ref(false)
  const loadFailed = ref(false)

  async function fetchPage(page: number, perPage: number, tags?: string) {
    loading.value = true
    loadFailed.value = false

    try {
      let url: string
      if (tags === 'untagged') {
        url = `/media/untagged/${page}/${perPage}/`
      } else if (tags) {
        url = `/media/with-tags/${encodeURIComponent(tags)}/${page}/${perPage}/`
      } else {
        url = `/media/page/${page}/${perPage}/`
      }

      const data = await api.get<PaginatedResponse>(url)

      items.value = data?.items ?? []
      totalPages.value = data?.total_pages ?? 1
    } catch (e: any) {
      toastStore.error(e.message || 'Failed to load gallery')
      loadFailed.value = true
      items.value = []
      totalPages.value = 0
    } finally {
      loading.value = false
    }
  }

  return { items, totalPages, loading, loadFailed, fetchPage }
}
