<script setup lang="ts">
import { useGalleryStore } from '../stores/gallery'
import { colorToTagClass } from '../constants/categories'

defineEmits<{
  close: []
}>()

const store = useGalleryStore()
</script>

<template>
  <article class="message is-info">
    <div class="message-header">
      <p>
        <span class="icon"><i class="fa-solid fa-circle-info"></i></span>
        <span>Tag Help</span>
      </p>
      <button class="delete" aria-label="close" @click="$emit('close')"></button>
    </div>
    <div class="message-body">
      <p class="mb-3">
        Tags have categories they belong to, each with a different color. When adding
        a tag, prefix it with a shortcode and colon to assign it to the appropriate
        category (e.g. <code>a:artist name</code>). Tags are converted to lowercase.
      </p>
      <table class="table is-hoverable is-fullwidth is-narrow">
        <thead>
          <tr>
            <th>Category</th>
            <th>Shortcode</th>
            <th>Description</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="cat in store.categories" :key="cat.category_id">
            <td><span class="tag" :class="colorToTagClass(cat.color)">{{ cat.category_name }}</span></td>
            <td><code>{{ cat.category_short }}:</code></td>
            <td>{{ cat.description }}</td>
          </tr>
        </tbody>
      </table>
    </div>
  </article>
</template>
