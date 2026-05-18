<script setup lang="ts">
import { ref, computed, watch, onMounted, onUnmounted, nextTick } from 'vue'
import { useRouter } from 'vue-router'
import { useGalleryData } from '../composables/useGalleryData'
import GalleryCard from '../components/GalleryCard.vue'
import PaginationBar from '../components/PaginationBar.vue'
import LoadingSpinner from '../components/LoadingSpinner.vue'
import ErrorMessage from '../components/ErrorMessage.vue'

const props = defineProps<{
  mediaType: 'images' | 'videos'
  page: number
  perPage: number
  tags?: string
}>()

const router = useRouter()
const { items, totalPages, loading, error, fetchPage } = useGalleryData()

// Track whether all thumbnail images have finished loading
const imagesReady = ref(false)

// Reference to the gallery grid container
const galleryGrid = ref<HTMLElement | null>(null)

// Lightbox state
const lightboxVisible = ref(false)
const lightboxIndex = ref(0)

// Video extensions for detection
const VIDEO_EXTENSIONS = ['mp4', 'webm', 'mov', 'avi', 'mkv', 'gif']

function isVideoFile(fileName: string): boolean {
  const ext = fileName.split('.').pop()?.toLowerCase() ?? ''
  return VIDEO_EXTENSIONS.includes(ext)
}

const lightboxItems = computed(() =>
  items.value.map(item => {
    const folder = props.mediaType === 'images' ? 'images/full' : 'videos/full'
    const src = `/${folder}/${item.file_name}`
    const isVideo = props.mediaType === 'videos' || isVideoFile(item.file_name)
    return { src, isVideo }
  })
)

const currentLightboxItem = computed(() => lightboxItems.value[lightboxIndex.value] ?? null)

function openLightbox(index: number) {
  lightboxIndex.value = index
  lightboxVisible.value = true
}

function closeLightbox() {
  lightboxVisible.value = false
}

function lightboxPrev() {
  if (lightboxIndex.value > 0) lightboxIndex.value--
}

function lightboxNext() {
  if (lightboxIndex.value < lightboxItems.value.length - 1) lightboxIndex.value++
}

function onLightboxKeydown(e: KeyboardEvent) {
  if (!lightboxVisible.value) return
  if (e.key === 'Escape') closeLightbox()
  else if (e.key === 'ArrowLeft') lightboxPrev()
  else if (e.key === 'ArrowRight') lightboxNext()
}

onMounted(() => window.addEventListener('keydown', onLightboxKeydown))
onUnmounted(() => window.removeEventListener('keydown', onLightboxKeydown))

/**
 * Wait for all thumbnail <img> elements within the gallery grid to finish loading.
 * The grid is rendered but invisible (opacity: 0) so images can load in the background.
 */
async function waitForThumbnails() {
  await nextTick()
  const container = galleryGrid.value
  if (!container) {
    imagesReady.value = true
    return
  }

  const imgs = Array.from(container.querySelectorAll('img'))
  if (imgs.length === 0) {
    imagesReady.value = true
    return
  }

  await Promise.all(
    imgs.map(img =>
      img.complete
        ? Promise.resolve()
        : new Promise<void>(resolve => {
            img.addEventListener('load', () => resolve(), { once: true })
            img.addEventListener('error', () => resolve(), { once: true })
          })
    )
  )
  imagesReady.value = true
}

async function loadPage() {
  imagesReady.value = false
  await fetchPage(props.mediaType, props.page, props.perPage, props.tags)
  // After data arrives and DOM updates, wait for thumbnail images to load
  await waitForThumbnails()
}

onMounted(loadPage)
watch(() => [props.mediaType, props.page, props.perPage, props.tags], loadPage)

function onNavigate(page: number) {
  if (props.tags) {
    router.push({
      name: `${props.mediaType}-with-tags`,
      params: { page, perPage: props.perPage, tags: props.tags }
    })
  } else {
    router.push({
      name: props.mediaType,
      params: { page, perPage: props.perPage }
    })
  }
}

function onViewTags(id: number) {
  router.push({
    name: props.mediaType === 'images' ? 'image-tags' : 'video-tags',
    params: { id }
  })
}
</script>

<template>
  <section class="section">
    <div class="container">
      <!-- Loading spinner: show while API loading OR images still loading -->
      <LoadingSpinner v-if="loading || (!imagesReady && !error)" />

      <ErrorMessage v-if="error && !loading" :message="error" />

      <!--
        Gallery content: always rendered when items exist so images can load in background.
        Hidden with opacity until all thumbnails are ready, then fades in.
      -->
      <div v-if="!loading && !error && items.length > 0"
           class="gallery-content"
           :class="{ 'gallery-visible': imagesReady }">
        <PaginationBar :current-page="page" :total-pages="totalPages" @navigate="onNavigate" />
        <hr />

        <div class="columns is-flex-direction-column" style="min-height: 75vh">
          <div class="column">
            <div class="column is-full is-align-content-end">
              <div ref="galleryGrid" class="parent">
                <GalleryCard
                  v-for="(item, idx) in items"
                  :key="mediaType === 'images' ? item.image_id : item.video_id"
                  :item="item"
                  :media-type="mediaType"
                  :index="idx"
                  @view-tags="(id) => onViewTags(id)"
                  @zoom="openLightbox"
                />
              </div>
            </div>
          </div>
        </div>

        <hr />
        <PaginationBar :current-page="page" :total-pages="totalPages" @navigate="onNavigate" />

        <!-- Lightbox -->
        <Teleport to="body">
          <div v-if="lightboxVisible" class="lightbox-overlay" @click.self="closeLightbox">
            <button class="lightbox-close" @click="closeLightbox">&times;</button>
            <button v-if="lightboxIndex > 0" class="lightbox-nav lightbox-prev" @click="lightboxPrev">&#8249;</button>
            <button v-if="lightboxIndex < lightboxItems.length - 1" class="lightbox-nav lightbox-next" @click="lightboxNext">&#8250;</button>

            <div class="lightbox-content">
              <video
                v-if="currentLightboxItem?.isVideo"
                :key="currentLightboxItem.src"
                :src="currentLightboxItem.src"
                controls
                autoplay
                class="lightbox-media"
              />
              <img
                v-else-if="currentLightboxItem"
                :src="currentLightboxItem.src"
                class="lightbox-media"
              />
            </div>
          </div>
        </Teleport>
      </div>
    </div>
  </section>
</template>

<style scoped>
.lightbox-overlay {
  position: fixed;
  inset: 0;
  z-index: 9999;
  background: rgba(0, 0, 0, 0.92);
  display: flex;
  align-items: center;
  justify-content: center;
}
.lightbox-content {
  display: flex;
  align-items: center;
  justify-content: center;
  max-width: 92vw;
  max-height: 90vh;
}
.lightbox-media {
  max-width: 90vw;
  max-height: 85vh;
  object-fit: contain;
  border-radius: 4px;
  outline: none;
}
.lightbox-close {
  position: absolute;
  top: 12px;
  right: 20px;
  background: none;
  border: none;
  color: #fff;
  font-size: 2.5rem;
  cursor: pointer;
  z-index: 10001;
  line-height: 1;
  opacity: 0.8;
}
.lightbox-close:hover { opacity: 1; }
.lightbox-nav {
  position: absolute;
  top: 50%;
  transform: translateY(-50%);
  background: none;
  border: none;
  color: #fff;
  font-size: 3rem;
  cursor: pointer;
  z-index: 10001;
  padding: 0 16px;
  opacity: 0.7;
}
.lightbox-nav:hover { opacity: 1; }
.lightbox-prev { left: 8px; }
.lightbox-next { right: 8px; }
</style>
