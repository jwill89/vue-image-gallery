<script setup lang="ts">
import { computed } from 'vue'
import { useGalleryStore, type MediaItem } from '../stores/gallery'

const props = defineProps<{
  item: MediaItem
  mediaType: 'images' | 'videos'
  index: number
}>()

const emit = defineEmits<{
  viewTags: [id: number, url: string, hash: string]
  zoom: [index: number]
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

const fullPath = computed(() => {
  if (props.mediaType === 'images') {
    return `/images/full/${props.item.file_name}`
  }
  return `/videos/full/${props.item.file_name}`
})

function onViewTags() {
  emit('viewTags', itemId.value, fullPath.value, props.item.hash)
}
</script>

<template>
  <div class="is-flex is-align-self-flex-end">
    <div class="card child has-border-white">
      <div class="card-content has-text-centered has-background-grey-darker">
        <figure class="image">
          <img :class="['gallery-image', { 'thumb-blur': store.blurThumbnails }]" :src="thumbnailPath" alt="" />
        </figure>
      </div>
      <footer class="card-footer has-background-light">
        <a class="card-footer-item" @click.prevent="emit('zoom', props.index)">
          <span class="icon has-text-info-dark">
            <i class="fa-solid fa-magnifying-glass-plus" title="Zoom In"></i>
          </span>
        </a>
        <a class="card-footer-item" :href="fullPath" target="_blank">
          <span class="icon has-text-info-dark">
            <i class="fa-solid fa-up-right-from-square" title="View Full Size in New Tab"></i>
          </span>
        </a>
        <a class="card-footer-item" @click.prevent="onViewTags">
          <span class="icon has-text-info-dark">
            <i class="fa-solid fa-tags" title="Add/View Tags"></i>
          </span>
        </a>
      </footer>
    </div>
  </div>
</template>
