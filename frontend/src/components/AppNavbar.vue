<script setup lang="ts">
import { ref, computed } from 'vue'
import { useRouter, useRoute } from 'vue-router'
import { useGalleryStore } from '../stores/gallery'
import TagSearchInput from './TagSearchInput.vue'

const router = useRouter()
const route = useRoute()
const store = useGalleryStore()

const burgerActive = ref(false)
const selectedTags = ref<string[]>([])
const perPage = ref(40)

const isImagesActive = computed(() => {
  const name = route.name as string
  return name?.startsWith('images') ?? false
})
const isVideosActive = computed(() => {
  const name = route.name as string
  return name?.startsWith('videos') ?? false
})
const isTagsActive = computed(() => route.name === 'tags')
const isDupesActive = computed(() => route.name === 'duplicates')

function navigateImages() {
  selectedTags.value = []
  router.push({ name: 'images', params: { page: 1, perPage: perPage.value } })
  burgerActive.value = false
}

function navigateVideos() {
  selectedTags.value = []
  router.push({ name: 'videos', params: { page: 1, perPage: perPage.value } })
  burgerActive.value = false
}

function navigateTags() {
  selectedTags.value = []
  router.push({ name: 'tags' })
  burgerActive.value = false
}

function navigateDupes() {
  selectedTags.value = []
  router.push({ name: 'duplicates' })
  burgerActive.value = false
}

function searchWithTags() {
  if (selectedTags.value.length === 0) return
  const mediaType = isVideosActive.value ? 'videos' : 'images'
  router.push({
    name: `${mediaType}-with-tags`,
    params: { page: 1, perPage: perPage.value, tags: selectedTags.value.join(',') }
  })
}

function resetSearch() {
  selectedTags.value = []
  const mediaType = isVideosActive.value ? 'videos' : 'images'
  router.push({ name: mediaType, params: { page: 1, perPage: perPage.value } })
}

function onPerPageChange() {
  const mediaType = isVideosActive.value ? 'videos' : 'images'
  if (selectedTags.value.length > 0) {
    router.push({
      name: `${mediaType}-with-tags`,
      params: { page: 1, perPage: perPage.value, tags: selectedTags.value.join(',') }
    })
  } else {
    router.push({ name: mediaType, params: { page: 1, perPage: perPage.value } })
  }
}

// Sync from route on navigation
router.afterEach((to) => {
  if (to.params.tags) {
    const tagsParam = to.params.tags as string
    selectedTags.value = tagsParam.split(',').map(t => t.trim()).filter(Boolean)
  } else if (!to.name?.toString().includes('with-tags')) {
    selectedTags.value = []
  }
  if (to.params.perPage) {
    perPage.value = Number(to.params.perPage) || 40
  }
})
</script>

<template>
  <nav class="navbar has-background-black-ter is-fixed-top" role="navigation" aria-label="main-menu">
    <div class="navbar-brand">
      <span class="navbar-item">
        <strong><span>{{ store.pageTitle }}</span></strong>
      </span>
      <a role="button" class="navbar-burger" :class="{ 'is-active': burgerActive }" aria-label="menu"
        :aria-expanded="burgerActive" @click="burgerActive = !burgerActive">
        <span aria-hidden="true"></span>
        <span aria-hidden="true"></span>
        <span aria-hidden="true"></span>
        <span aria-hidden="true"></span>
      </a>
    </div>

    <div class="navbar-menu" :class="{ 'is-active': burgerActive }">
      <div class="navbar-start">
        <a class="navbar-item" :class="{ 'is-selected': isImagesActive }" @click="navigateImages">Images</a>
        <a class="navbar-item" :class="{ 'is-selected': isVideosActive }" @click="navigateVideos">Videos</a>
        <a class="navbar-item" :class="{ 'is-selected': isTagsActive }" @click="navigateTags">Tags</a>
        <a class="navbar-item" :class="{ 'is-selected': isDupesActive }" @click="navigateDupes">Duplicates</a>
      </div>

      <div class="navbar-end">
        <div class="navbar-item">
          <button class="button" :class="{ 'is-success': store.blurThumbnails }" @click="store.toggleBlur">
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
                </select>
              </div>
              <div class="icon is-left">
                <i class="fa-solid fa-table"></i>
              </div>
            </div>
          </div>
        </div>

        <div class="navbar-item">
          <TagSearchInput
            v-model="selectedTags"
            @search="searchWithTags"
            @reset="resetSearch"
          />
        </div>
      </div>
    </div>
  </nav>
</template>

