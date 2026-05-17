<script setup lang="ts">
import { ref, onMounted, onUnmounted } from 'vue'
import { useGalleryStore } from './stores/gallery'
import AppNavbar from './components/AppNavbar.vue'
import AppFooter from './components/AppFooter.vue'

const store = useGalleryStore()
const navbarHeight = ref(52)
let resizeObserver: ResizeObserver | null = null

onMounted(() => {
  store.initialize()

  // Observe navbar height changes
  const navEl = document.querySelector('nav.navbar')
  if (navEl) {
    resizeObserver = new ResizeObserver((entries) => {
      for (const entry of entries) {
        navbarHeight.value = entry.contentRect.height
      }
    })
    resizeObserver.observe(navEl)
    navbarHeight.value = navEl.getBoundingClientRect().height
  }
})

onUnmounted(() => {
  resizeObserver?.disconnect()
})
</script>

<template>
  <div class="sticky-footer has-navbar-fixed-top" :style="{ paddingTop: navbarHeight + 'px' }">
    <AppNavbar />
    <router-view />
    <AppFooter />
  </div>
</template>
