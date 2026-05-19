<script setup lang="ts">
import { computed } from 'vue'
import { useGalleryStore, type MediaItem } from '../stores/gallery'

const props = defineProps<{
  item: MediaItem
  mediaType: 'images' | 'videos'
}>()

const emit = defineEmits<{
  click: [id: number]
}>()

const store = useGalleryStore()

const itemId = computed(() =>
  props.mediaType === 'images' ? props.item.image_id! : props.item.video_id!
)

const thumbnailPath = computed(() => {
  if (props.mediaType === 'images') {
    return `/images/thumbs/${props.item.file_name}`
  }
  const baseName = props.item.file_name.split('.').slice(0, -1).join('.')
  return `/videos/thumbs/${baseName}.jpg`
})
</script>

<template>
  <div class="gallery-card" @click="emit('click', itemId)">
    <div class="gallery-card-inner">
      <img
        :class="['gallery-card-img', { 'thumb-blur': store.blurThumbnails }]"
        :src="thumbnailPath"
        alt=""
      />
      <!-- Video badge -->
      <span v-if="mediaType === 'videos'" class="gallery-card-badge">
        <i class="fa-solid fa-film"></i>
      </span>
    </div>
  </div>
</template>

<style scoped>
.gallery-card {
  position: relative;
  border-radius: 6px;
  overflow: hidden;
  background: #1a1a1a;
  cursor: pointer;
}

.gallery-card-inner {
  position: relative;
  height: 200px;
  display: flex;
  align-items: center;
  justify-content: center;
}

.gallery-card-img {
  max-width: 100%;
  max-height: 100%;
  object-fit: contain;
  transition: transform 0.2s ease, filter 0.3s ease-in-out;
}

.gallery-card:hover .gallery-card-img:not(.thumb-blur) {
  transform: scale(1.05);
}

.gallery-card-badge {
  position: absolute;
  top: 6px;
  left: 6px;
  background: rgba(0, 0, 0, 0.7);
  color: #fff;
  font-size: 0.7rem;
  padding: 3px 6px;
  border-radius: 3px;
  pointer-events: none;
}
</style>
