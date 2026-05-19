<script setup lang="ts">
import { watch, onMounted } from 'vue'
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

async function loadPage() {
  await fetchPage(props.mediaType, props.page, props.perPage, props.tags)
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

function onCardClick(id: number) {
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

      <ErrorMessage v-if="error && !loading" :message="error" />

      <div v-if="!loading && !error && items.length > 0">
        <PaginationBar :current-page="page" :total-pages="totalPages" @navigate="onNavigate" />
        <hr />

        <div style="min-height: 75vh">
          <div class="gallery-grid">
            <GalleryCard
              v-for="item in items"
              :key="mediaType === 'images' ? item.image_id : item.video_id"
              :item="item"
              :media-type="mediaType"
              @click="onCardClick"
            />
          </div>
        </div>

        <hr />
        <PaginationBar :current-page="page" :total-pages="totalPages" @navigate="onNavigate" />
      </div>
    </div>
  </section>
</template>

<style scoped>
.gallery-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(150px, 200px));
  gap: 8px;
  justify-content: center;
}

@media (min-width: 769px) {
  .gallery-grid {
    grid-template-columns: repeat(auto-fill, minmax(160px, 200px));
  }
}

@media (min-width: 1200px) {
  .gallery-grid {
    grid-template-columns: repeat(auto-fill, minmax(170px, 200px));
  }
}
</style>
