<script setup lang="ts">
import { ref, computed, watch, onMounted, onUnmounted } from 'vue'
import { useRouter } from 'vue-router'
import { useGalleryData } from '../composables/useGalleryData'
import { useGalleryStore } from '../stores/gallery'
import { useApi } from '../composables/useApi'
import { endpoints } from '../api/endpoints'
import type { MediaItem, MediaPage } from '../types'
import GalleryCard from '../components/GalleryCard.vue'
import PaginationBar from '../components/PaginationBar.vue'
import LoadingSpinner from '../components/LoadingSpinner.vue'
import { prefetchThumbnails } from '../composables/usePrefetch'

/** Build the media-listing path for a page + optional tag filter. */
function listUrl(page: number, perPage: number, tags?: string): string {
  if (tags === 'untagged') return endpoints.media.untagged(page, perPage)
  if (tags) return endpoints.media.withTags(tags, page, perPage)
  return endpoints.media.page(page, perPage)
}

const props = defineProps<{
  page: number
  perPage: number
  tags?: string
}>()

const router = useRouter()
const store = useGalleryStore()
const api = useApi()
const { items, totalPages, loading, loadFailed, fetchPage } = useGalleryData()

const INFINITE_BATCH_SIZE = 40
const isInfiniteScroll = computed(() => props.perPage === 0)
const accumulatedItems = ref<MediaItem[]>([])
const currentBatchPage = ref(1)
const loadingMore = ref(false)
const allLoaded = ref(false)
const scrollSentinel = ref<HTMLElement | null>(null)
let observer: IntersectionObserver | null = null

const displayItems = computed(() => (isInfiniteScroll.value ? accumulatedItems.value : items.value))

function updateStoreItemIds() {
  store.lastViewedItemIds = displayItems.value
    .map((i) => i.media_id)
    .filter((id): id is number => id != null)
}

async function loadPage() {
  if (isInfiniteScroll.value) {
    accumulatedItems.value = []
    currentBatchPage.value = 1
    allLoaded.value = false
    loadingMore.value = false
    await fetchPage(1, INFINITE_BATCH_SIZE, props.tags)
    accumulatedItems.value = [...items.value]
    if (totalPages.value <= 1) {
      allLoaded.value = true
    } else {
      currentBatchPage.value = 2
    }
  } else {
    await fetchPage(props.page, props.perPage, props.tags)
    // Pre-cache thumbnails for the next page
    void prefetchAdjacentPage(props.page + 1, props.perPage, props.tags)
  }
  updateStoreItemIds()
}

async function prefetchAdjacentPage(page: number, perPage: number, tags?: string) {
  if (page < 1 || page > totalPages.value) return

  // Skip the extra round-trip on metered/slow connections — prefetching the
  // next page (to warm its thumbnails) isn't worth the data there.
  const conn = (
    navigator as unknown as { connection?: { saveData?: boolean; effectiveType?: string } }
  ).connection
  if (conn?.saveData || /(^|-)2g$/.test(conn?.effectiveType ?? '')) return

  try {
    const data = await api.get<MediaPage>(listUrl(page, perPage, tags))
    if (data?.items?.length) {
      prefetchThumbnails(data.items)
    }
  } catch {
    // Prefetch is best-effort — don't disrupt the user
  }
}

async function loadNextBatch() {
  if (loadingMore.value || allLoaded.value) return
  loadingMore.value = true
  try {
    const data = await api.get<MediaPage>(
      listUrl(currentBatchPage.value, INFINITE_BATCH_SIZE, props.tags),
    )
    const newItems = data?.items ?? []
    accumulatedItems.value = [...accumulatedItems.value, ...newItems]
    const maxPages = data?.total_pages ?? 1
    if (currentBatchPage.value >= maxPages || newItems.length === 0) {
      allLoaded.value = true
    } else {
      currentBatchPage.value++
    }
    updateStoreItemIds()
  } catch (e) {
    console.error('Failed to load more items:', e)
  } finally {
    loadingMore.value = false
  }
}

function setupObserver() {
  observer?.disconnect()
  const el = scrollSentinel.value
  if (!el) return
  observer = new IntersectionObserver(
    (entries) => {
      if (entries[0].isIntersecting) void loadNextBatch()
    },
    { rootMargin: '400px' },
  )
  observer.observe(el)
}

watch(scrollSentinel, setupObserver)

function onKeydown(e: KeyboardEvent) {
  if (isInfiniteScroll.value || loading.value) return
  if (
    e.target instanceof HTMLInputElement ||
    e.target instanceof HTMLTextAreaElement ||
    e.target instanceof HTMLSelectElement
  )
    return

  if (e.key === 'ArrowLeft' && props.page > 1) {
    onNavigate(props.page - 1)
  } else if (e.key === 'ArrowRight' && props.page < totalPages.value) {
    onNavigate(props.page + 1)
  }
}

onMounted(() => {
  void loadPage()
  window.addEventListener('keydown', onKeydown)
})
watch(() => [props.page, props.perPage, props.tags], loadPage)
onUnmounted(() => {
  observer?.disconnect()
  window.removeEventListener('keydown', onKeydown)
})

function onNavigate(page: number) {
  if (props.tags) {
    void router.push({
      name: 'media-with-tags',
      params: { page, perPage: props.perPage, tags: props.tags },
    })
  } else {
    void router.push({
      name: 'media',
      params: { page, perPage: props.perPage },
    })
  }
}

function onCardClick(id: number) {
  void router.push({
    name: 'media-tags',
    params: { id },
  })
}
</script>

<template>
  <section class="section">
    <div class="container">
      <LoadingSpinner v-if="loading" />

      <div v-else-if="loadFailed || displayItems.length === 0" class="has-text-centered py-6">
        <span class="icon is-large has-text-grey-light">
          <i class="fa-solid fa-images fa-3x" />
        </span>
        <p class="is-size-5 has-text-grey mt-4">
          {{ loadFailed ? 'Could not load the gallery. Please try again.' : 'No items found.' }}
        </p>
        <button v-if="loadFailed" class="button is-indigo mt-4" @click="loadPage">
          <span class="icon"><i class="fa-solid fa-rotate-right" /></span>
          <span>Retry</span>
        </button>
      </div>

      <div v-else>
        <PaginationBar
          v-if="!isInfiniteScroll"
          :current-page="page"
          :total-pages="totalPages"
          @navigate="onNavigate"
        />
        <hr v-if="!isInfiniteScroll" />

        <div style="min-height: 75vh">
          <div class="gallery-grid">
            <GalleryCard
              v-for="item in displayItems"
              :key="item.media_id"
              :item="item"
              @click="onCardClick"
            />
          </div>
        </div>

        <div
          v-if="isInfiniteScroll && !allLoaded"
          ref="scrollSentinel"
          class="has-text-centered py-5"
        >
          <span class="icon is-large has-text-grey"
            ><i class="fa-solid fa-spinner fa-spin fa-2x"
          /></span>
        </div>

        <p v-if="isInfiniteScroll && allLoaded" class="has-text-centered has-text-grey py-4">
          All items loaded
        </p>

        <hr v-if="!isInfiniteScroll" />
        <PaginationBar
          v-if="!isInfiniteScroll"
          :current-page="page"
          :total-pages="totalPages"
          @navigate="onNavigate"
        />
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
