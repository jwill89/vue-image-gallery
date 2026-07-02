<script setup lang="ts">
import { ref, computed, onMounted, onUnmounted, watch } from 'vue'
import { useRouter } from 'vue-router'
import { useMediaTags } from '../composables/useMediaTags'
import { useGalleryStore } from '../stores/gallery'
import { useToastStore } from '../stores/toast'
import { useApi, getErrorMessage, hasAuthToken } from '../composables/useApi'
import { useFavoritesStore } from '../stores/favorites'
import { endpoints } from '../api/endpoints'
import type { DanbooruFetchResult } from '../types'
import TagMultiSelect from '../components/TagMultiSelect.vue'
import TagBadge from '../components/TagBadge.vue'
import TagShortcodeModal from '../components/TagShortcodeModal.vue'
import LoadingSpinner from '../components/LoadingSpinner.vue'

const props = defineProps<{
  mediaId: number
}>()

const router = useRouter()
const store = useGalleryStore()
const toastStore = useToastStore()
const api = useApi()
const favorites = useFavoritesStore()
const { tags, mediaItem, loading, loadFailed, fetchMediaAndTags, addTags, removeTag } =
  useMediaTags()

const showHelpModal = ref(false)
const showDeleteModal = ref(false)
const deleting = ref(false)
const authenticated = ref(hasAuthToken())
const mediaUrl = ref('')
const mediaReady = ref(false)
const selectedTagIds = ref<number[]>([])

// Danbooru fetch modal
const showDanbooruModal = ref(false)
const danbooruMode = ref<'auto' | 'post_id'>('auto')
const danbooruPostId = ref('')
const danbooruFetching = ref(false)
const danbooruResult = ref<{ method: string; tags_applied: number; tags_created: number } | null>(
  null,
)
const danbooruError = ref('')

// Touch/swipe state
let touchStartX = 0
let touchStartY = 0
let touchStartTime = 0
const SWIPE_THRESHOLD = 50 // min px distance
const SWIPE_MAX_TIME = 400 // max ms for a swipe
const SWIPE_ANGLE_LIMIT = 30 // max degrees from horizontal

const appliedTagIds = computed(() => tags.value.map((t) => t.tag_id))

const formattedDate = computed(() => {
  if (!mediaItem.value?.file_time) return ''
  const date = new Date(mediaItem.value.file_time * 1000)
  return date.toLocaleString(undefined, {
    weekday: 'short',
    year: 'numeric',
    month: 'short',
    day: 'numeric',
    hour: '2-digit',
    minute: '2-digit',
    second: '2-digit',
    timeZoneName: 'short',
  })
})

const fullPath = computed(() => {
  if (!mediaItem.value) return ''
  return `/media/full/${mediaItem.value.file_name}`
})

const isVideoItem = computed(() => mediaItem.value?.media_type === 'video')

const dimensions = computed(() => {
  const w = mediaItem.value?.width ?? 0
  const h = mediaItem.value?.height ?? 0
  return w > 0 && h > 0 ? `${w} × ${h}` : ''
})

const formattedDuration = computed(() => {
  const secs = mediaItem.value?.duration ?? 0
  if (!secs || secs <= 0) return ''
  const total = Math.round(secs)
  const h = Math.floor(total / 3600)
  const m = Math.floor((total % 3600) / 60)
  const s = total % 60
  const pad = (n: number) => String(n).padStart(2, '0')
  return h > 0 ? `${h}:${pad(m)}:${pad(s)}` : `${m}:${pad(s)}`
})

const formattedFileSize = computed(() => {
  const bytes = mediaItem.value?.file_size ?? 0
  if (!bytes || bytes <= 0) return ''
  if (bytes < 1024) return `${bytes} B`
  if (bytes < 1024 * 1024) return `${(bytes / 1024).toFixed(1)} KB`
  if (bytes < 1024 * 1024 * 1024) return `${(bytes / (1024 * 1024)).toFixed(1)} MB`
  return `${(bytes / (1024 * 1024 * 1024)).toFixed(2)} GB`
})

onMounted(load)
watch(() => props.mediaId, load)

async function load() {
  mediaUrl.value = ''
  mediaReady.value = false
  await fetchMediaAndTags(props.mediaId)
  if (mediaItem.value) {
    mediaUrl.value = `/media/full/${mediaItem.value.file_name}`
  }
}

function backToGallery() {
  router.back()
}

async function onAddTags() {
  if (selectedTagIds.value.length === 0) return
  await addTags(props.mediaId, [...selectedTagIds.value])
  selectedTagIds.value = []
}

async function onRemoveTag(tagId: number) {
  if (confirm('Are you sure you want to remove this tag?')) {
    await removeTag(props.mediaId, tagId)
  }
}

const isVideo = (url: string) => {
  const ext = url.split('.').pop()?.toLowerCase()
  return ext && ['mp4', 'webm', 'mov', 'avi', 'mkv'].includes(ext)
}

const hasGalleryContext = computed(
  () => store.lastViewedItemIds.length > 0 && currentIndex.value >= 0,
)
const currentIndex = computed(() => {
  return store.lastViewedItemIds.indexOf(props.mediaId)
})
const prevId = computed(() => {
  const idx = currentIndex.value
  return idx > 0 ? store.lastViewedItemIds[idx - 1] : null
})
const nextId = computed(() => {
  const idx = currentIndex.value
  return idx >= 0 && idx < store.lastViewedItemIds.length - 1
    ? store.lastViewedItemIds[idx + 1]
    : null
})

function navigatePrev() {
  if (prevId.value != null) {
    void router.replace({ name: 'media-tags', params: { id: prevId.value } })
  }
}

function navigateNext() {
  if (nextId.value != null) {
    void router.replace({ name: 'media-tags', params: { id: nextId.value } })
  }
}

async function deleteMedia() {
  deleting.value = true
  try {
    await api.del(endpoints.media.byId(props.mediaId))
    toastStore.success('Media deleted successfully.')
    showDeleteModal.value = false

    // Remove from the gallery navigation list so arrow keys don't land on a dead item
    const idx = store.lastViewedItemIds.indexOf(props.mediaId)
    if (idx >= 0) {
      store.lastViewedItemIds.splice(idx, 1)
    }

    // Refresh totals since we removed an item
    await store.refreshTotals()

    // Navigate to the next item if available, otherwise go back
    if (nextId.value != null) {
      void router.replace({ name: 'media-tags', params: { id: nextId.value } })
    } else if (prevId.value != null) {
      void router.replace({ name: 'media-tags', params: { id: prevId.value } })
    } else {
      router.back()
    }
  } catch (e) {
    toastStore.error(getErrorMessage(e, 'Failed to delete media.'))
  } finally {
    deleting.value = false
  }
}

function openDanbooruModal() {
  danbooruMode.value = 'auto'
  danbooruPostId.value = ''
  danbooruResult.value = null
  danbooruError.value = ''
  showDanbooruModal.value = true
}

async function fetchDanbooruTags() {
  danbooruFetching.value = true
  danbooruResult.value = null
  danbooruError.value = ''

  try {
    const payload: Record<string, number> = {}
    if (danbooruMode.value === 'post_id') {
      const id = parseInt(danbooruPostId.value.trim(), 10)
      if (!id || id <= 0) {
        danbooruError.value = 'Please enter a valid Danbooru post ID.'
        danbooruFetching.value = false
        return
      }
      payload.danbooru_post_id = id
    }

    const data = await api.post<DanbooruFetchResult>(
      endpoints.media.danbooruTags(props.mediaId),
      payload,
    )

    tags.value = data.tags ?? []
    store.allTags = data.all_tags ?? store.allTags
    danbooruResult.value = {
      method: data.method,
      tags_applied: data.tags_applied,
      tags_created: data.tags_created,
    }
    toastStore.success(`Imported ${data.tags_applied} tags from Danbooru (via ${data.method}).`)
  } catch (e) {
    danbooruError.value = getErrorMessage(e, 'Failed to fetch tags from Danbooru.')
  } finally {
    danbooruFetching.value = false
  }
}

// ── Keyboard navigation ────────────────────────────────────

function onGlobalKeydown(e: KeyboardEvent) {
  if (e.target instanceof HTMLInputElement || e.target instanceof HTMLTextAreaElement) return
  if (e.key === 'ArrowLeft') navigatePrev()
  else if (e.key === 'ArrowRight') navigateNext()
}

onMounted(() => window.addEventListener('keydown', onGlobalKeydown))
onUnmounted(() => window.removeEventListener('keydown', onGlobalKeydown))

// ── Touch/swipe navigation for mobile ──────────────────────

function onTouchStart(e: TouchEvent) {
  if (e.touches.length !== 1) return
  touchStartX = e.touches[0].clientX
  touchStartY = e.touches[0].clientY
  touchStartTime = Date.now()
}

function onTouchEnd(e: TouchEvent) {
  if (e.changedTouches.length !== 1) return

  const dx = e.changedTouches[0].clientX - touchStartX
  const dy = e.changedTouches[0].clientY - touchStartY
  const elapsed = Date.now() - touchStartTime

  // Must be fast enough and far enough horizontally
  if (elapsed > SWIPE_MAX_TIME || Math.abs(dx) < SWIPE_THRESHOLD) return

  // Must be mostly horizontal (not a scroll gesture)
  const angle = Math.abs(Math.atan2(dy, dx) * (180 / Math.PI))
  if (angle > SWIPE_ANGLE_LIMIT && angle < 180 - SWIPE_ANGLE_LIMIT) return

  if (dx < 0) {
    navigateNext() // swipe left → next
  } else {
    navigatePrev() // swipe right → prev
  }
}
</script>

<template>
  <section class="section">
    <div class="container">
      <LoadingSpinner v-if="loading && !mediaItem" />
      <div v-else-if="loadFailed && !mediaItem" class="has-text-centered py-6">
        <span class="icon is-large has-text-grey-light">
          <i class="fa-solid fa-circle-exclamation fa-3x" />
        </span>
        <p class="is-size-5 has-text-grey mt-4">Could not load media details.</p>
        <div class="buttons is-centered mt-4">
          <button class="button is-indigo" @click="load">
            <span class="icon"><i class="fa-solid fa-rotate-right" /></span>
            <span>Retry</span>
          </button>
          <button class="button is-indigo is-outlined" @click="backToGallery">
            <span class="icon"><i class="fa-solid fa-backward" /></span>
            <span>Back to Gallery</span>
          </button>
        </div>
      </div>
      <template v-else>
        <div class="columns">
          <div class="column is-three-fifths">
            <figure
              ref="mediaContainer"
              class="image tags-page-img"
              @touchstart.passive="onTouchStart"
              @touchend.passive="onTouchEnd"
            >
              <div v-if="!mediaReady" class="media-placeholder">
                <span class="icon is-large has-text-grey">
                  <i class="fa-solid fa-spinner fa-spin fa-2x" />
                </span>
              </div>
              <video
                v-if="mediaUrl && isVideo(mediaUrl)"
                controls
                :src="mediaUrl"
                :class="[
                  'media-fade',
                  { 'is-loaded': mediaReady, 'thumb-blur': store.blurThumbnails },
                ]"
                @loadeddata="mediaReady = true"
              />
              <img
                v-else-if="mediaUrl"
                :src="mediaUrl"
                alt=""
                :class="[
                  'media-fade',
                  { 'is-loaded': mediaReady, 'thumb-blur': store.blurThumbnails },
                ]"
                @load="mediaReady = true"
              />
            </figure>
          </div>

          <div class="column">
            <!-- Toolbar: navigation on the left, actions on the right -->
            <div class="media-toolbar">
              <div class="toolbar-nav">
                <button class="button is-indigo" @click="backToGallery">
                  <span class="icon"><i class="fa-solid fa-backward" /></span>
                  <span>Back</span>
                </button>
                <div v-if="hasGalleryContext" class="field has-addons mb-0">
                  <div class="control">
                    <button
                      class="button is-indigo is-outlined"
                      :disabled="prevId == null"
                      title="Previous (← or swipe right)"
                      @click="navigatePrev"
                    >
                      <span class="icon"><i class="fa-solid fa-arrow-left" /></span>
                    </button>
                  </div>
                  <div class="control">
                    <button
                      class="button is-indigo is-outlined"
                      :disabled="nextId == null"
                      title="Next (→ or swipe left)"
                      @click="navigateNext"
                    >
                      <span class="icon"><i class="fa-solid fa-arrow-right" /></span>
                    </button>
                  </div>
                </div>
              </div>
              <div class="toolbar-actions">
                <button
                  class="button"
                  :class="favorites.isFavorite(mediaId) ? 'is-pink' : 'is-pink is-outlined'"
                  :title="
                    favorites.isFavorite(mediaId) ? 'Remove from favorites' : 'Add to favorites'
                  "
                  @click="favorites.toggle(mediaId)"
                >
                  <span class="icon">
                    <i
                      :class="
                        favorites.isFavorite(mediaId) ? 'fa-solid fa-heart' : 'fa-regular fa-heart'
                      "
                    />
                  </span>
                </button>
                <button
                  v-if="authenticated"
                  class="button is-cyan"
                  title="Fetch tags from Danbooru"
                  @click="openDanbooruModal"
                >
                  <span class="icon"><i class="fa-solid fa-download" /></span>
                </button>
                <button
                  v-if="authenticated"
                  class="button is-danger is-outlined"
                  title="Delete this media"
                  @click="showDeleteModal = true"
                >
                  <span class="icon"><i class="fa-solid fa-trash" /></span>
                </button>
              </div>
            </div>

            <!-- Media Details -->
            <h2 class="title is-5">Media Details</h2>
            <table class="table is-narrow is-fullwidth">
              <tbody>
                <tr>
                  <th>Date Added</th>
                  <td>{{ formattedDate }}</td>
                </tr>
                <tr v-if="dimensions">
                  <th>Dimensions</th>
                  <td>{{ dimensions }}</td>
                </tr>
                <tr v-if="formattedDuration">
                  <th>Duration</th>
                  <td>{{ formattedDuration }}</td>
                </tr>
                <tr v-if="formattedFileSize">
                  <th>File Size</th>
                  <td>{{ formattedFileSize }}</td>
                </tr>
                <tr>
                  <th>MD5 Hash</th>
                  <td>
                    <code>{{ mediaItem?.hash }}</code>
                  </td>
                </tr>
                <tr>
                  <th>Full Media</th>
                  <td>
                    <a :href="fullPath" target="_blank"
                      >View Full {{ isVideoItem ? 'Video' : 'Image' }}
                      <i class="fa-solid fa-up-right-from-square fa-xs"
                    /></a>
                  </td>
                </tr>
              </tbody>
            </table>

            <hr />

            <!-- Add Tags -->
            <h3 class="title is-6">Add Tags</h3>
            <TagMultiSelect
              v-model="selectedTagIds"
              :exclude-tag-ids="appliedTagIds"
              placeholder="Search tags to add..."
              @submit="onAddTags"
            >
              <template #actions>
                <div class="control">
                  <button
                    class="button is-primary"
                    :disabled="selectedTagIds.length === 0"
                    @click="onAddTags"
                  >
                    Add Tags
                  </button>
                </div>
              </template>
            </TagMultiSelect>
            <p class="help">
              Add tags. Multiple tags are allowed.
              <a style="cursor: pointer" @click.prevent="showHelpModal = !showHelpModal">
                {{ showHelpModal ? 'Hide tag help' : 'Show tag help' }}
              </a>
              to read more about tag categories, differentiated by colors.
            </p>

            <TagShortcodeModal v-if="showHelpModal" class="mt-3" @close="showHelpModal = false" />

            <hr />

            <!-- Current Tags -->
            <h3 class="title is-6">Current Tags</h3>
            <div class="tags are-medium">
              <TagBadge
                v-for="tag in tags"
                :key="tag.tag_id"
                :tag-id="tag.tag_id"
                :tag-name="tag.tag_name"
                :category-id="tag.category_id"
                :removable="authenticated"
                @remove="onRemoveTag"
              />
              <span v-if="tags.length === 0" class="has-text-grey">No tags applied yet.</span>
            </div>
          </div>
        </div>
      </template>

      <!-- Delete Confirmation Modal -->
      <div class="modal" :class="{ 'is-active': showDeleteModal }">
        <div class="modal-background" @click="showDeleteModal = false" />
        <div class="modal-card">
          <header class="modal-card-head">
            <p class="modal-card-title">Confirm Deletion</p>
            <button class="delete" aria-label="close" @click="showDeleteModal = false" />
          </header>
          <section class="modal-card-body">
            <p>Are you sure you want to permanently delete this media item?</p>
            <p class="has-text-danger mt-2">
              <span class="icon"><i class="fa-solid fa-triangle-exclamation" /></span>
              This action cannot be undone.
            </p>
          </section>
          <footer class="modal-card-foot">
            <button
              class="button is-danger"
              :class="{ 'is-loading': deleting }"
              :disabled="deleting"
              @click="deleteMedia"
            >
              <span class="icon"><i class="fa-solid fa-trash" /></span>
              <span>Delete</span>
            </button>
            <button class="button" :disabled="deleting" @click="showDeleteModal = false">
              Cancel
            </button>
          </footer>
        </div>
      </div>
      <!-- Danbooru Fetch Modal -->
      <div class="modal" :class="{ 'is-active': showDanbooruModal }">
        <div class="modal-background" @click="showDanbooruModal = false" />
        <div class="modal-card">
          <header class="modal-card-head">
            <p class="modal-card-title">
              <strong>Fetch Danbooru Tags</strong>
            </p>
            <button class="delete" aria-label="close" @click="showDanbooruModal = false" />
          </header>
          <section class="modal-card-body">
            <div class="field">
              <label class="label">Lookup Method</label>
              <div class="control">
                <label class="radio mr-4">
                  <input
                    v-model="danbooruMode"
                    type="radio"
                    value="auto"
                    :disabled="danbooruFetching"
                  />
                  Auto (MD5 + IQDB)
                </label>
                <label class="radio">
                  <input
                    v-model="danbooruMode"
                    type="radio"
                    value="post_id"
                    :disabled="danbooruFetching"
                  />
                  Danbooru Post ID
                </label>
              </div>
            </div>

            <div v-if="danbooruMode === 'post_id'" class="field">
              <label class="label">Post ID</label>
              <div class="control">
                <input
                  v-model="danbooruPostId"
                  class="input"
                  type="text"
                  placeholder="e.g. 1234567"
                  :disabled="danbooruFetching"
                  @keyup.enter="fetchDanbooruTags"
                />
              </div>
              <p class="help">
                Enter the numeric post ID from a Danbooru URL (e.g.
                <code>danbooru.donmai.us/posts/<strong>1234567</strong></code
                >).
              </p>
            </div>

            <div v-if="danbooruMode === 'auto'" class="content">
              <p class="has-text-grey is-size-7">
                Will search Danbooru by this media's MD5 hash first. If no match is found, it will
                try IQDB visual similarity as a fallback.
              </p>
            </div>

            <div v-if="danbooruResult" class="notification is-success is-light mt-4">
              <p>
                <span class="icon"><i class="fa-solid fa-check" /></span>
                Found via <strong>{{ danbooruResult.method }}</strong> — applied
                <strong>{{ danbooruResult.tags_applied }}</strong> tag(s)
                <template v-if="danbooruResult.tags_created > 0">
                  (<strong>{{ danbooruResult.tags_created }}</strong> new)
                </template>
              </p>
            </div>

            <div v-if="danbooruError" class="notification is-danger is-light mt-4">
              <p>
                <span class="icon"><i class="fa-solid fa-triangle-exclamation" /></span>
                {{ danbooruError }}
              </p>
            </div>
          </section>
          <footer class="modal-card-foot">
            <div class="buttons">
              <button
                class="button is-cyan"
                :class="{ 'is-loading': danbooruFetching }"
                :disabled="danbooruFetching"
                @click="fetchDanbooruTags"
              >
                <span class="icon"><i class="fa-solid fa-download" /></span>
                <span>Fetch Tags</span>
              </button>
              <button
                class="button"
                :disabled="danbooruFetching"
                @click="showDanbooruModal = false"
              >
                Close
              </button>
            </div>
          </footer>
        </div>
      </div>
    </div>
  </section>
</template>

<style scoped>
.media-placeholder {
  display: flex;
  align-items: center;
  justify-content: center;
  min-height: 300px;
  background: rgba(255, 255, 255, 0.03);
  border-radius: 6px;
}

.media-fade {
  opacity: 0;
  transition: opacity 0.3s ease;
}

.media-fade.is-loaded {
  opacity: 1;
}

/* ── Toolbar layout ───────────────────────────────────────── */

.media-toolbar {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 0.75rem;
  margin-bottom: 1.25rem;
  flex-wrap: wrap;
}

.toolbar-nav {
  display: flex;
  align-items: center;
  gap: 0.5rem;
}

.toolbar-actions {
  display: flex;
  align-items: center;
  gap: 0.5rem;
}

/* On narrow screens, let the toolbar stack but keep groups intact */
@media (max-width: 480px) {
  .media-toolbar {
    justify-content: center;
  }
}
</style>
