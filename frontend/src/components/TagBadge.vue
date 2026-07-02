<script setup lang="ts">
import { getCategoryClassById } from '../constants/categories'

defineProps<{
  tagId: number
  tagName: string
  categoryId: number
  removable?: boolean
}>()

const emit = defineEmits<{
  remove: [tagId: number]
}>()
</script>

<template>
  <span class="tag is-medium" :class="getCategoryClassById(categoryId)">
    {{ tagName }}
    <button v-if="removable" class="delete" aria-label="delete" @click="emit('remove', tagId)" />
  </span>
</template>
