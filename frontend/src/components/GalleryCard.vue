<script setup lang="ts">
import { computed } from 'vue'
import { useGalleryStore, type MediaItem } from '../stores/gallery'
import { useFavoritesStore } from '../stores/favorites'

const props = defineProps<{
  item: MediaItem
}>()

const emit = defineEmits<{
  click: [id: number]
}>()

const store = useGalleryStore()
const favorites = useFavoritesStore()

const baseName = computed(() => props.item.file_name.split('.').slice(0, -1).join('.'))
const thumbDir = '/media/thumbs'
const thumbnailPath = computed(() => `${thumbDir}/${baseName.value}.webp`)
const thumbnail2xPath = computed(() => `${thumbDir}/${baseName.value}@2x.webp`)

const isAnimated = computed(() => {
  if (props.item.media_type === 'video') return true
  const ext = props.item.file_name.split('.').pop()?.toLowerCase()
  return ext === 'gif'
})

const isFav = computed(() => favorites.isFavorite(props.item.media_id))

function toggleFavorite(e: Event) {
  e.stopPropagation()
  favorites.toggle(props.item.media_id)
}
</script>

<template>
  <div class="gallery-card" @click="emit('click', item.media_id)">
    <div class="gallery-card-inner">
      <div class="gallery-card-thumb">
        <img
          :class="['gallery-card-img', { 'thumb-blur': store.blurThumbnails }]"
          :src="thumbnailPath"
          :srcset="`${thumbnail2xPath} 2x`"
          alt=""
        />
        <!-- Animated badge (videos and GIFs) -->
        <span v-if="isAnimated" class="gallery-card-badge">
          <i :class="item.media_type === 'video' ? 'fa-solid fa-film' : 'fa-solid fa-play'"></i>
        </span>
        <!-- Favorite heart -->
        <button
          class="gallery-card-heart"
          :class="{ 'is-favorited': isFav }"
          @click="toggleFavorite"
          :title="isFav ? 'Remove from favorites' : 'Add to favorites'"
        >
          <i :class="isFav ? 'fa-solid fa-heart' : 'fa-regular fa-heart'"></i>
        </button>
      </div>
    </div>
  </div>
</template>

<style scoped>
.gallery-card {
  position: relative;
  border-radius: 6px;
  cursor: pointer;
  /* Skip rendering/layout for off-screen cards. Keeps paint & layout cost
     roughly constant as the infinite-scroll list grows. The intrinsic size
     hint matches the 200px card height so the scrollbar stays stable. */
  content-visibility: auto;
  contain-intrinsic-size: 200px 200px;
}

.gallery-card-inner {
  height: 200px;
  display: flex;
  align-items: center;
  justify-content: center;
}

.gallery-card-thumb {
  position: relative;
  display: inline-block;
  line-height: 0;
}

.gallery-card-img {
  max-width: 100%;
  max-height: 200px;
  object-fit: contain;
  border-radius: 6px;
  transition: filter 0.2s ease;
}

.gallery-card:hover .gallery-card-img:not(.thumb-blur) {
  filter: brightness(1.2);
}

.gallery-card-badge {
  position: absolute;
  top: 4px;
  left: 4px;
  background: rgba(0, 0, 0, 0.7);
  color: #fff;
  font-size: 0.7rem;
  padding: 3px 6px;
  border-radius: 3px;
  pointer-events: none;
}

/* ── Heart button ──────────────────────────────────────── */

.gallery-card-heart {
  position: absolute;
  top: 4px;
  right: 4px;
  background: rgba(0, 0, 0, 0.6);
  border: none;
  color: #aaa;
  font-size: 0.85rem;
  padding: 4px 6px;
  border-radius: 4px;
  cursor: pointer;
  opacity: 0;
  transition: opacity 0.15s, color 0.15s, transform 0.15s;
  line-height: 1;
}

.gallery-card:hover .gallery-card-heart,
.gallery-card-heart.is-favorited {
  opacity: 1;
}

.gallery-card-heart.is-favorited {
  color: #f14668;
}

.gallery-card-heart:hover {
  color: #f14668;
  transform: scale(1.15);
}

.gallery-card-heart:active {
  transform: scale(0.9);
}
</style>
