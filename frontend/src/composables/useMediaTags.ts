import { ref } from 'vue'
import { useApi } from './useApi'
import { useToastStore } from '../stores/toast'
import { useGalleryStore, type Tag, type MediaItem } from '../stores/gallery'

export function useMediaTags() {
  const api = useApi()
  const toastStore = useToastStore()
  const store = useGalleryStore()
  const tags = ref<Tag[]>([])
  const mediaItem = ref<MediaItem | null>(null)
  const loading = ref(false)
  const loadFailed = ref(false)

  async function fetchMediaAndTags(mediaId: number) {
    loading.value = true
    loadFailed.value = false

    try {
      const [item, itemTags] = await Promise.all([
        api.get<MediaItem>(`/media/${mediaId}/`),
        api.get<Tag[]>(`/tags/for/media/${mediaId}/`)
      ])
      mediaItem.value = item
      tags.value = itemTags ?? []
    } catch (e: any) {
      toastStore.error(e.message || 'Failed to load media')
      loadFailed.value = true
    } finally {
      loading.value = false
    }
  }

  async function addTags(mediaId: number, tagIds: number[]) {
    try {
      const updatedTags = await api.patch<Tag[]>(`/tags/media/add/`, { item_id: mediaId, tag_ids: tagIds })
      tags.value = updatedTags ?? []
      await store.refreshTags()
    } catch (e: any) {
      toastStore.error(e.message || 'Failed to add tags')
    }
  }

  async function removeTag(mediaId: number, tagId: number) {
    try {
      const updatedTags = await api.patch<Tag[]>(`/tags/media/remove/`, { item_id: mediaId, tag_id: tagId })
      tags.value = updatedTags ?? []
    } catch (e: any) {
      toastStore.error(e.message || 'Failed to remove tag')
    }
  }

  return { tags, mediaItem, loading, loadFailed, fetchMediaAndTags, addTags, removeTag }
}
