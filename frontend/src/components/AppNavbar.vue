<script setup lang="ts">
import { ref, computed } from 'vue'
import { useRouter, useRoute } from 'vue-router'
import { useGalleryStore } from '../stores/gallery'
import { useApi, hasAuthToken, clearAuthToken } from '../composables/useApi'
import { useFavoritesStore } from '../stores/favorites'
import { useToastStore } from '../stores/toast'
import { endpoints } from '../api/endpoints'
import type { Media } from '../types'
import TagSearchInput from './TagSearchInput.vue'

const router = useRouter()
const route = useRoute()
const store = useGalleryStore()
const api = useApi()
const favorites = useFavoritesStore()
const toastStore = useToastStore()

const burgerActive = ref(false)
const selectedTags = ref<string[]>([])
const perPage = ref(40)
const authenticated = ref(hasAuthToken())

const isMediaActive = computed(() => {
  const name = route.name as string
  return name === 'media' || name === 'media-with-tags' || name === 'media-tags'
})
const isTagsActive = computed(() => {
  const name = route.name as string
  return (
    name === 'tags' ||
    name === 'tag-categories' ||
    name === 'danbooru-rules' ||
    name === 'tag-implications'
  )
})
const isUploadActive = computed(() => route.name === 'upload')
const isFavoritesActive = computed(() => route.name === 'favorites')
const isDupesActive = computed(() => route.name === 'duplicates')
const isLoginActive = computed(() => route.name === 'login')

function navigateMedia() {
  selectedTags.value = []
  void router.push({ name: 'media', params: { page: 1, perPage: perPage.value } })
  burgerActive.value = false
}

function navigateTags() {
  selectedTags.value = []
  void router.push({ name: 'tags' })
  burgerActive.value = false
}

function navigateFavorites() {
  selectedTags.value = []
  void router.push({ name: 'favorites' })
  burgerActive.value = false
}

function navigateUpload() {
  selectedTags.value = []
  void router.push({ name: 'upload' })
  burgerActive.value = false
}

function navigateDupes() {
  selectedTags.value = []
  void router.push({ name: 'duplicates' })
  burgerActive.value = false
}

async function navigateRandom() {
  burgerActive.value = false
  try {
    if (store.totalMedia === 0) {
      toastStore.info('The gallery is empty. Upload some media first.', 4000, 'No Media')
      return
    }
    const item = await api.get<Media>(endpoints.media.random)

    // Clear gallery context so arrow keys are disabled for random access
    store.lastViewedItemIds = []
    void router.push({ name: 'media-tags', params: { id: item.media_id } })
  } catch {
    toastStore.error('Could not load a random media item. Please try again.', 6000, 'Random Failed')
  }
}

function navigateLogin() {
  void router.push({ name: 'login' })
  burgerActive.value = false
}

function logout() {
  clearAuthToken()
  authenticated.value = false
  burgerActive.value = false
}

function searchWithTags() {
  if (selectedTags.value.length === 0) return
  void router.push({
    name: 'media-with-tags',
    params: { page: 1, perPage: perPage.value, tags: selectedTags.value.join(',') },
  })
}

function resetSearch() {
  selectedTags.value = []
  void router.push({ name: 'media', params: { page: 1, perPage: perPage.value } })
}

function searchUntagged() {
  selectedTags.value = []
  void router.push({
    name: 'media-with-tags',
    params: { page: 1, perPage: perPage.value, tags: 'untagged' },
  })
  burgerActive.value = false
}

const isUntaggedActive = computed(() => {
  return route.params.tags === 'untagged'
})

function onPerPageChange() {
  if (isUntaggedActive.value) {
    void router.push({
      name: 'media-with-tags',
      params: { page: 1, perPage: perPage.value, tags: 'untagged' },
    })
  } else if (selectedTags.value.length > 0) {
    void router.push({
      name: 'media-with-tags',
      params: { page: 1, perPage: perPage.value, tags: selectedTags.value.join(',') },
    })
  } else {
    void router.push({ name: 'media', params: { page: 1, perPage: perPage.value } })
  }
}

// Sync auth state and route params on navigation
router.afterEach((to) => {
  authenticated.value = hasAuthToken()
  if (to.params.tags && to.params.tags !== 'untagged') {
    const tagsParam = to.params.tags as string
    selectedTags.value = tagsParam
      .split(',')
      .map((t) => t.trim())
      .filter(Boolean)
  } else if (!to.name?.toString().includes('with-tags') || to.params.tags === 'untagged') {
    selectedTags.value = []
  }
  if (to.params.perPage != null && to.params.perPage !== '') {
    const pp = Number(to.params.perPage)
    perPage.value = isNaN(pp) ? 40 : pp
  }
})
</script>

<template>
  <nav
    class="navbar has-background-black-ter is-fixed-top"
    role="navigation"
    aria-label="main-menu"
  >
    <div class="navbar-brand">
      <a
        role="button"
        class="navbar-burger"
        :class="{ 'is-active': burgerActive }"
        aria-label="menu"
        :aria-expanded="burgerActive"
        @click="burgerActive = !burgerActive"
      >
        <span aria-hidden="true" />
        <span aria-hidden="true" />
        <span aria-hidden="true" />
        <span aria-hidden="true" />
      </a>
    </div>

    <div class="navbar-menu" :class="{ 'is-active': burgerActive }">
      <div class="navbar-start">
        <!-- Media -->
        <a class="navbar-item" :class="{ 'is-selected': isMediaActive }" @click="navigateMedia">
          <span class="icon"><i class="fa-solid fa-images" /></span>
          <span>Media</span>
        </a>

        <!-- Random -->
        <a class="navbar-item" @click="navigateRandom">
          <span class="icon"><i class="fa-solid fa-shuffle" /></span>
          <span>Random</span>
        </a>

        <!-- Tags -->
        <a class="navbar-item" :class="{ 'is-selected': isTagsActive }" @click="navigateTags">
          <span class="icon"><i class="fa-solid fa-tags" /></span>
          <span>Tags</span>
        </a>

        <!-- Favorites -->
        <a
          class="navbar-item"
          :class="{ 'is-selected': isFavoritesActive }"
          @click="navigateFavorites"
        >
          <span class="icon"><i class="fa-solid fa-heart" /></span>
          <span>Favorites</span>
          <span v-if="favorites.count > 0" class="tag is-rounded is-small ml-1">{{
            favorites.count
          }}</span>
        </a>

        <!-- Admin items -->
        <template v-if="authenticated">
          <a class="navbar-item" :class="{ 'is-selected': isUploadActive }" @click="navigateUpload">
            <span class="icon"><i class="fa-solid fa-cloud-arrow-up" /></span>
            <span>Upload</span>
          </a>
          <a class="navbar-item" :class="{ 'is-selected': isDupesActive }" @click="navigateDupes">
            <span class="icon"><i class="fa-solid fa-clone" /></span>
            <span>Duplicates</span>
          </a>
        </template>
      </div>

      <div class="navbar-end">
        <div class="navbar-item">
          <button
            class="button"
            :class="{ 'is-success': store.blurThumbnails }"
            @click="store.toggleBlur"
          >
            Blur: {{ store.blurThumbnails ? 'On' : 'Off' }}
          </button>
        </div>

        <div class="navbar-item">
          <div class="field">
            <div class="control has-icons-left">
              <div class="select">
                <select v-model.number="perPage" title="Items Per-Page" @change="onPerPageChange">
                  <option :value="15">15 Items Per-Page</option>
                  <option :value="30">30 Items Per-Page</option>
                  <option :value="40">40 Items Per-Page</option>
                  <option :value="60">60 Items Per-Page</option>
                  <option :value="100">100 Items Per-Page</option>
                  <option :value="0">Infinite Scroll</option>
                </select>
              </div>
              <div class="icon is-left">
                <i class="fa-solid fa-table" />
              </div>
            </div>
          </div>
        </div>

        <div class="navbar-item">
          <div class="field has-addons">
            <div class="control">
              <TagSearchInput
                v-model="selectedTags"
                @search="searchWithTags"
                @reset="resetSearch"
              />
            </div>
            <div class="control">
              <button
                class="button"
                :class="{ 'is-warning': isUntaggedActive }"
                title="Show untagged media"
                @click="searchUntagged"
              >
                <span class="icon"><i class="fa-solid fa-ban" /></span>
              </button>
            </div>
          </div>
        </div>

        <div class="navbar-item">
          <button v-if="authenticated" class="button is-danger is-outlined" @click="logout">
            <span class="icon"><i class="fa-solid fa-right-from-bracket" /></span>
            <span>Logout</span>
          </button>
          <button
            v-else
            class="button is-primary is-outlined"
            :class="{ 'is-selected': isLoginActive }"
            @click="navigateLogin"
          >
            <span class="icon"><i class="fa-solid fa-right-to-bracket" /></span>
            <span>Admin Login</span>
          </button>
        </div>
      </div>
    </div>
  </nav>
</template>
