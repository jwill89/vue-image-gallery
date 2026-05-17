<script setup lang="ts">
import { ref, computed, watch, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { useGalleryData } from '../composables/useGalleryData'
import GalleryCard from '../components/GalleryCard.vue'
import PaginationBar from '../components/PaginationBar.vue'
import LoadingSpinner from '../components/LoadingSpinner.vue'
import ErrorMessage from '../components/ErrorMessage.vue'
import VueEasyLightbox from 'vue-easy-lightbox'

const props = defineProps<{
  mediaType: 'images' | 'videos'
  page: number
  perPage: number
  tags?: string
}>()

const router = useRouter()
const { items, totalPages, loading, error, fetchPage } = useGalleryData()

// Lightbox state
const lightboxVisible = ref(false)
const lightboxIndex = ref(0)

// Video extensions for detection
const VIDEO_EXTENSIONS = ['mp4', 'webm', 'mov', 'avi', 'mkv', 'gif']

function isVideoFile(fileName: string): boolean {
  const ext = fileName.split('.').pop()?.toLowerCase() ?? ''
  return VIDEO_EXTENSIONS.includes(ext)
}

const lightboxImages = computed(() =>
  items.value.map(item => {
    const folder = props.mediaType === 'images' ? 'images/full' : 'videos/full'
    const url = `/${folder}/${item.file_name}`

    // vue-easy-lightbox supports video via object format
    if (props.mediaType === 'videos' || isVideoFile(item.file_name)) {
      return { src: url, mediaType: 'video' }
    }
    return url
  })
)

function openLightbox(index: number) {
  lightboxIndex.value = index
  lightboxVisible.value = true
}

function onLightboxHide() {
  lightboxVisible.value = false
}

function loadPage() {
  fetchPage(props.mediaType, props.page, props.perPage, props.tags)
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
      <LoadingSpinner v-if="loading" />
      <ErrorMessage v-else-if="error" :message="error" />
      <template v-else>
        <PaginationBar :current-page="page" :total-pages="totalPages" @navigate="onNavigate" />
        <hr />

        <div class="columns is-flex-direction-column" style="min-height: 75vh">
          <div class="column">
            <div class="column is-full is-align-content-end">
              <div class="parent">
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
        <vue-easy-lightbox
          :visible="lightboxVisible"
          :imgs="lightboxImages"
          :index="lightboxIndex"
          @hide="onLightboxHide"
        />
      </template>
    </div>
  </section>
</template>

