<script setup lang="ts">
import { useToastStore } from '../stores/toast'

const toastStore = useToastStore()
</script>

<template>
  <div class="toast-container">
    <transition-group name="toast">
      <div
        v-for="toast in toastStore.toasts"
        :key="toast.id"
        class="notification toast-notification"
        :class="`is-${toast.type}`"
      >
        <button class="delete" @click="toastStore.remove(toast.id)"></button>
        {{ toast.message }}
      </div>
    </transition-group>
  </div>
</template>

<style scoped>
.toast-container {
  position: fixed;
  top: 70px;
  right: 20px;
  z-index: 9999;
  display: flex;
  flex-direction: column;
  gap: 8px;
  max-width: 400px;
}

.toast-notification {
  box-shadow: 0 4px 12px rgba(0, 0, 0, 0.3);
  min-width: 280px;
}

/* Transition animations */
.toast-enter-active {
  transition: all 0.3s ease-out;
}

.toast-leave-active {
  transition: all 0.3s ease-in;
}

.toast-enter-from {
  opacity: 0;
  transform: translateX(80px);
}

.toast-leave-to {
  opacity: 0;
  transform: translateX(80px);
}
</style>

