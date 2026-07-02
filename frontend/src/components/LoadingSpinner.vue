<script setup lang="ts">
defineProps<{
  message?: string
}>()
</script>

<template>
  <div class="loading-container">
    <div class="has-text-centered">
      <span class="icon is-large">
        <i class="fas fa-spinner fa-pulse fa-3x" />
      </span>
      <p v-if="message" class="mt-3">
        {{ message }}
      </p>
      <p v-else class="mt-3">Loading...</p>
    </div>
  </div>
</template>
