import { ref } from 'vue'
import { useApi } from './useApi'
import type { MediaItem } from '../stores/gallery'


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
      const tagsSegment = tags ? `/with-tags/${encodeURIComponent(tags)}` : ''

      // Fetch items
      const itemsUrl = tags
        ? `/${mediaType}/with-tags/${encodeURIComponent(tags)}/${page}/${perPage}/`
        : `/${mediaType}/page/${page}/${perPage}/`

      // Fetch total pages
      const pagesUrl = `/pages/${mediaType}${tagsSegment}/${perPage}/`

      const [itemsData, pagesData] = await Promise.all([
        api.get<MediaItem[]>(itemsUrl),
        api.get<number>(pagesUrl)
      ])

      items.value = itemsData ?? []
      totalPages.value = pagesData ?? 1
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

