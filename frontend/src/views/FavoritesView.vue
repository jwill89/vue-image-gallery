<script setup lang="ts">
import { ref, computed, onMounted, watch } from 'vue'
import { useRouter } from 'vue-router'
import { useApi, getErrorMessage } from '../composables/useApi'
import { useFavoritesStore } from '../stores/favorites'
import { useGalleryStore } from '../stores/gallery'
import { useToastStore } from '../stores/toast'
import { endpoints } from '../api/endpoints'
import type { MediaItem } from '../types'
import GalleryCard from '../components/GalleryCard.vue'
import LoadingSpinner from '../components/LoadingSpinner.vue'

const router = useRouter()
const api = useApi()
const favorites = useFavoritesStore()
const store = useGalleryStore()
const toastStore = useToastStore()

const items = ref<MediaItem[]>([])
const loading = ref(false)
const loadFailed = ref(false)

const isEmpty = computed(() => favorites.count === 0)

async function loadFavorites() {
  const ids = favorites.allIds()
  if (ids.length === 0) {
    items.value = []
    return
  }

  loading.value = true
  loadFailed.value = false

  try {
    const result = await api.post<MediaItem[]>(endpoints.media.byIds, { ids })
    items.value = result ?? []

    // Prune favorites that no longer exist in the database
    const validIds = new Set(items.value.map((i) => i.media_id))
    favorites.prune(validIds)

    // Update gallery context for arrow-key navigation
    store.lastViewedItemIds = items.value.map((i) => i.media_id)
  } catch (e) {
    toastStore.error(getErrorMessage(e, 'Failed to load favorites.'), 6000, 'Load Failed')
    loadFailed.value = true
  } finally {
    loading.value = false
  }
}

function onCardClick(id: number) {
  void router.push({ name: 'media-tags', params: { id } })
}

onMounted(loadFavorites)

// Reload when favorites change (e.g. user unfavorites from this page via the card heart)
watch(() => favorites.count, loadFavorites)
</script>

<template>
  <section class="section">
    <div class="container">
      <h1 class="title">
        <span class="icon mr-2"><i class="fa-solid fa-heart" /></span>
        Favorites
        <span v-if="!isEmpty" class="tag is-medium is-rounded ml-3">{{ favorites.count }}</span>
      </h1>

      <div class="notification is-warning is-light favorites-disclaimer">
        <span class="icon mr-2"><i class="fa-solid fa-circle-info" /></span>
        <span>
          Favorites are saved in your browser's local storage and are not stored on the server.
          Clearing your browser data, switching browsers, or using a private/incognito window will
          reset them.
        </span>
      </div>

      <LoadingSpinner v-if="loading && items.length === 0" />

      <div v-else-if="isEmpty" class="has-text-centered py-6">
        <span class="icon is-large has-text-grey-light">
          <i class="fa-regular fa-heart fa-3x" />
        </span>
        <p class="is-size-5 has-text-grey mt-4">No favorites yet.</p>
        <p class="has-text-grey-light mt-2">
          Click the <i class="fa-regular fa-heart" /> on any thumbnail or media page to add it here.
        </p>
      </div>

      <div v-else-if="loadFailed" class="has-text-centered py-6">
        <span class="icon is-large has-text-grey-light">
          <i class="fa-solid fa-circle-exclamation fa-3x" />
        </span>
        <p class="is-size-5 has-text-grey mt-4">Could not load favorites.</p>
        <button class="button is-indigo mt-4" @click="loadFavorites">
          <span class="icon"><i class="fa-solid fa-rotate-right" /></span>
          <span>Retry</span>
        </button>
      </div>

      <div v-else>
        <div class="gallery-grid">
          <GalleryCard
            v-for="item in items"
            :key="item.media_id"
            :item="item"
            @click="onCardClick"
          />
        </div>
      </div>
    </div>
  </section>
</template>

<style scoped>
.favorites-disclaimer {
  display: flex;
  align-items: center;
  padding: 0.75rem 1rem;
  font-size: 0.875rem;
  margin-bottom: 1.25rem;
}

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
