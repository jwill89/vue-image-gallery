<script setup lang="ts">
import { useGalleryStore } from '../stores/gallery'

const store = useGalleryStore()
</script>

<template>
  <footer class="footer is-flex-align-items-flex-end mt-auto">
    <div class="content has-text-centered">
      <p>
        Programmed by <a href="https://www.mathdad.me" target="_blank">MathDad</a>. Built
        with <a href="https://vuejs.org" target="_blank">Vue</a> and
        <a href="https://bulma.io/" target="_blank">Bulma</a>.<br />
        Repository on <a href="https://github.com/jwill89/simple-image-gallery/">GitHub</a>.
        The source code is licensed under the
        <a href="https://opensource.org/licenses/mit-license.php" target="_blank">MIT</a> license.<br />
        All images &copy; their respective creators.<br />
        All characters in images &copy; their respective owners.
      </p>
      <div class="level">
        <div class="level-item has-text-centered">
          <div>
            <p class="heading mb-0">Media</p>
            <h3 class="m-0">{{ store.totalMedia }}</h3>
          </div>
        </div>
      </div>
    </div>
  </footer>
</template>
