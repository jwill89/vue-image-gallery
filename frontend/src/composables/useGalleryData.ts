import { ref } from 'vue'
import { useApi } from './useApi'
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
  const items = ref<MediaItem[]>([])
  const totalPages = ref(0)
  const loading = ref(false)
  const error = ref<string | null>(null)

  async function fetchPage(mediaType: 'images' | 'videos', page: number, perPage: number, tags?: string) {
    loading.value = true
    error.value = null

    try {
      // Single API call returns items + pagination metadata
      const url = tags
        ? `/${mediaType}/with-tags/${encodeURIComponent(tags)}/${page}/${perPage}/`
        : `/${mediaType}/page/${page}/${perPage}/`

      const data = await api.get<PaginatedResponse>(url)

      items.value = data?.items ?? []
      totalPages.value = data?.total_pages ?? 1
    } catch (e: any) {
      error.value = e.message || 'Failed to load gallery'
      items.value = []
      totalPages.value = 0
    } finally {
      loading.value = false
    }
  }

  return { items, totalPages, loading, error, fetchPage }
}
