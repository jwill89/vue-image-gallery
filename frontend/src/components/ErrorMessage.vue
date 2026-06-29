<script setup lang="ts">
defineProps<{
  message: string
}>()
</script>

<template>
  <div class="notification is-danger">
    <p>{{ message }}</p>
  </div>
</template>
