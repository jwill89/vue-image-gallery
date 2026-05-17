<script setup lang="ts">
import { computed } from 'vue'

const props = defineProps<{
  currentPage: number
  totalPages: number
}>()

const emit = defineEmits<{
  navigate: [page: number]
}>()

const previousPage = computed(() => props.currentPage - 1)
const nextPage = computed(() => props.currentPage + 1)
const hasPrevious = computed(() => props.currentPage > 1)
const hasNext = computed(() => props.currentPage < props.totalPages)

function go(page: number) {
  if (page >= 1 && page <= props.totalPages) {
    emit('navigate', page)
  }
}
</script>

<template>
  <nav class="pagination is-centered" role="navigation" aria-label="pagination">
    <a class="pagination-previous" :class="{ 'is-disabled': !hasPrevious }" @click.prevent="go(previousPage)">Previous</a>
    <a class="pagination-next" :class="{ 'is-disabled': !hasNext }" @click.prevent="go(nextPage)">Next</a>
    <ul class="pagination-list">
      <!-- First page + ellipsis -->
      <li v-if="currentPage >= 3">
        <a class="pagination-link" @click.prevent="go(1)">1</a>
      </li>
      <li v-if="currentPage >= 3">
        <span class="pagination-ellipsis">&hellip;</span>
      </li>

      <!-- Previous page -->
      <li v-if="currentPage >= 2">
        <a class="pagination-link" @click.prevent="go(previousPage)">{{ previousPage }}</a>
      </li>

      <!-- Current page -->
      <li>
        <a class="pagination-link is-current" aria-current="page">{{ currentPage }}</a>
      </li>

      <!-- Next page -->
      <li v-if="hasNext">
        <a class="pagination-link" @click.prevent="go(nextPage)">{{ nextPage }}</a>
      </li>

      <!-- Ellipsis + last page -->
      <li v-if="currentPage <= totalPages - 2">
        <span class="pagination-ellipsis">&hellip;</span>
      </li>
      <li v-if="currentPage <= totalPages - 2">
        <a class="pagination-link" @click.prevent="go(totalPages)">{{ totalPages }}</a>
      </li>
    </ul>
  </nav>
</template>
