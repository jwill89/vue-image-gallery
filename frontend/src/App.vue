<script setup lang="ts">
import { ref, onMounted, onUnmounted } from 'vue'
import { useGalleryStore } from './stores/gallery'
import AppNavbar from './components/AppNavbar.vue'
import AppBreadcrumb from './components/AppBreadcrumb.vue'
import AppFooter from './components/AppFooter.vue'
import ToastContainer from './components/ToastContainer.vue'
import ErrorBoundary from './components/ErrorBoundary.vue'

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
    <AppBreadcrumb />
    <ErrorBoundary>
      <router-view v-slot="{ Component }">
        <Transition name="page-fade" mode="out-in">
          <component :is="Component" />
        </Transition>
      </router-view>
    </ErrorBoundary>
    <AppFooter />
    <ToastContainer />
  </div>
</template>
