import { ref } from 'vue'
import { useApi } from './useApi'
import { useGalleryStore, type Tag, type MediaItem } from '../stores/gallery'

export function useMediaTags() {
  const api = useApi()
  const store = useGalleryStore()
  const tags = ref<Tag[]>([])
  const mediaItem = ref<MediaItem | null>(null)
  const loading = ref(false)
  const error = ref<string | null>(null)

  async function fetchMediaAndTags(mediaType: 'images' | 'videos', mediaId: number) {
    loading.value = true
    error.value = null
    const singular = mediaType === 'images' ? 'image' : 'video'

    try {
      const [item, itemTags] = await Promise.all([
        api.get<MediaItem>(`/${mediaType}/${mediaId}/`),
        api.get<Tag[]>(`/tags/for/${singular}/${mediaId}/`)
      ])
      mediaItem.value = item
      tags.value = itemTags ?? []
    } catch (e: any) {
      error.value = e.message || 'Failed to load media'
    } finally {
      loading.value = false
    }
  }

  async function addTags(mediaType: 'images' | 'videos', mediaId: number, tagList: string) {
    const singular = mediaType === 'images' ? 'image' : 'video'
    try {
      // PATCH returns the updated tag list directly
      const updatedTags = await api.patch<Tag[]>(`/tags/${singular}/add/`, { item_id: mediaId, tag_list: tagList })
      tags.value = updatedTags ?? []
      store.refreshTags()
    } catch (e: any) {
      error.value = e.message || 'Failed to add tags'
    }
  }

  async function removeTag(mediaType: 'images' | 'videos', mediaId: number, tagId: number) {
    const singular = mediaType === 'images' ? 'image' : 'video'
    try {
      // PATCH returns the updated tag list directly
      const updatedTags = await api.patch<Tag[]>(`/tags/${singular}/remove/`, { item_id: mediaId, tag_id: tagId })
      tags.value = updatedTags ?? []
    } catch (e: any) {
      error.value = e.message || 'Failed to remove tag'
    }
  }

  return { tags, mediaItem, loading, error, fetchMediaAndTags, addTags, removeTag }
}
