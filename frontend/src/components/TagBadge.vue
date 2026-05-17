<script setup lang="ts">
const CATEGORY_TAG_CLASS_MAP: Record<number, string> = {
  1: 'is-white',
  2: 'is-danger',
  3: 'is-success',
  4: 'is-warning',
  5: 'is-info'
}

defineProps<{
  tagId: number
  tagName: string
  categoryId: number
  removable?: boolean
}>()

const emit = defineEmits<{
  remove: [tagId: number]
}>()

function getCategoryClass(categoryId: number): string {
  return CATEGORY_TAG_CLASS_MAP[categoryId] || 'is-white'
}
</script>

<template>
  <span class="tag is-medium" :class="getCategoryClass(categoryId)">
    {{ tagName }}
    <button v-if="removable" class="delete" @click="emit('remove', tagId)" aria-label="delete"></button>
  </span>
</template>
