<script setup lang="ts">
import { useGalleryStore } from '../stores/gallery'

const store = useGalleryStore()
</script>

<template>
  <footer class="footer is-flex-align-items-flex-end mt-auto">
    <div class="content has-text-centered">
      <p>
        Programmed by <a href="https://www.mathdad.me" target="_blank">MathDad</a>. Utilizes
        the <a href="https://bulma.io/" target="_blank">Bulma</a> CSS Framework.<br />
        Repository on <a href="https://github.com/jwill89/simple-image-gallery/">GitHub</a>.
        The source code is licensed under the
        <a href="https://opensource.org/licenses/mit-license.php" target="_blank">MIT</a> license.<br />
        All images &copy; their respective creators.<br />
        All characters in images &copy; their respective owners.
      </p>
      <div class="level">
        <div class="level-item has-text-centered">
          <div>
            <p class="heading mb-0">Images</p>
            <h3 class="m-0">{{ store.totalImages }}</h3>
          </div>
        </div>
        <div class="level-item has-text-centered">
          <div>
            <p class="heading mb-0">Videos</p>
            <h3 class="m-0">{{ store.totalVideos }}</h3>
          </div>
        </div>
      </div>
    </div>
  </footer>
</template>
