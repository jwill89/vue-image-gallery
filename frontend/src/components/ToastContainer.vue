<script setup lang="ts">
import { useToastStore } from '../stores/toast'

const toastStore = useToastStore()

const iconMap: Record<string, string> = {
  success: 'fa-solid fa-circle-check',
  danger: 'fa-solid fa-circle-exclamation',
  warning: 'fa-solid fa-triangle-exclamation',
  info: 'fa-solid fa-circle-info',
}
</script>

<template>
  <div class="toast-container">
    <transition-group name="toast">
      <div
        v-for="toast in toastStore.toasts"
        :key="toast.id"
        class="toast-notification"
        :class="`toast-${toast.type}`"
      >
        <div class="toast-icon">
          <i :class="iconMap[toast.type]"></i>
        </div>
        <div class="toast-body">
          <div class="toast-title">{{ toast.title }}</div>
          <div class="toast-message">{{ toast.message }}</div>
        </div>
        <button class="toast-close" @click="toastStore.remove(toast.id)" aria-label="Dismiss">
          <i class="fa-solid fa-xmark"></i>
        </button>
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
  gap: 10px;
  max-width: 420px;
  width: calc(100vw - 40px);
}

.toast-notification {
  display: flex;
  align-items: flex-start;
  gap: 12px;
  padding: 14px 16px;
  border-radius: 8px;
  box-shadow: 0 6px 20px rgba(0, 0, 0, 0.35);
  border-left: 4px solid;
  background: #2b2b2b;
  color: #e8e8e8;
  min-width: 0;
  word-break: break-word;
}

/* ── Type-specific colours ─────────────────────────────── */

.toast-success {
  border-left-color: #48c78e;
}
.toast-success .toast-icon {
  color: #48c78e;
}

.toast-danger {
  border-left-color: #f14668;
}
.toast-danger .toast-icon {
  color: #f14668;
}

.toast-warning {
  border-left-color: #ffe08a;
}
.toast-warning .toast-icon {
  color: #ffe08a;
}

.toast-info {
  border-left-color: #3e8ed0;
}
.toast-info .toast-icon {
  color: #3e8ed0;
}

/* ── Icon ──────────────────────────────────────────────── */

.toast-icon {
  flex-shrink: 0;
  font-size: 1.25rem;
  line-height: 1.5;
  margin-top: 1px;
}

/* ── Body ──────────────────────────────────────────────── */

.toast-body {
  flex: 1;
  min-width: 0;
}

.toast-title {
  font-weight: 600;
  font-size: 0.9rem;
  line-height: 1.5;
  color: #fff;
}

.toast-message {
  font-size: 0.85rem;
  line-height: 1.45;
  color: #b5b5b5;
  margin-top: 2px;
}

/* ── Close button ──────────────────────────────────────── */

.toast-close {
  flex-shrink: 0;
  background: none;
  border: none;
  color: #888;
  cursor: pointer;
  padding: 2px 4px;
  font-size: 0.85rem;
  line-height: 1;
  border-radius: 4px;
  transition: color 0.15s, background 0.15s;
  margin-top: 1px;
}

.toast-close:hover {
  color: #fff;
  background: rgba(255, 255, 255, 0.1);
}

/* ── Transition animations ─────────────────────────────── */

.toast-enter-active {
  transition: all 0.3s cubic-bezier(0.21, 1.02, 0.73, 1);
}

.toast-leave-active {
  transition: all 0.25s ease-in;
}

.toast-enter-from {
  opacity: 0;
  transform: translateX(60px) scale(0.95);
}

.toast-leave-to {
  opacity: 0;
  transform: translateX(60px) scale(0.95);
}

/* ── Mobile ────────────────────────────────────────────── */

@media (max-width: 480px) {
  .toast-container {
    right: 10px;
    left: 10px;
    max-width: none;
    width: auto;
  }
}
</style>
