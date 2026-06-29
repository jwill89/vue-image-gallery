<script setup lang="ts">
defineProps<{
  message?: string
}>()
</script>

<template>
  <div class="loading-container">
    <div class="has-text-centered">
      <span class="icon is-large">
        <i class="fas fa-spinner fa-pulse fa-3x"></i>
      </span>
      <p class="mt-3" v-if="message">{{ message }}</p>
      <p class="mt-3" v-else>Loading...</p>
    </div>
  </div>
</template>
