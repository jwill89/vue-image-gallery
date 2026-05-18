<script setup lang="ts">
import { TAG_CATEGORIES } from '../constants/categories'
</script>

<template>
  <div class="modal-card">
    <header class="modal-card-head">
      <p class="modal-card-title"><strong>Tag Help</strong></p>
      <button class="delete is-large" aria-label="close" @click="$emit('close')"></button>
    </header>
    <section class="modal-card-body">
      <div class="content">
        <h4>Tag Categories</h4>
        <p>Tags have five categories they belong to, each having a different color. When you add
          a tag, you can add a shortcode followed by a colon before the tag to ensure it is added
          to the appropriate category. Tags are converted to lowercase. The shortcodes are:</p>
        <table class="table is-hoverable is-fullwidth">
          <thead>
            <tr>
              <th>Tag</th>
              <th>Shortcode</th>
              <th>Description</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="cat in TAG_CATEGORIES" :key="cat.id">
              <td><span class="tag" :class="cat.tagClass">{{ cat.name }}</span></td>
              <td><code>{{ cat.shortcode }}:</code></td>
              <td>{{ cat.description }}</td>
            </tr>
          </tbody>
        </table>
      </div>
    </section>
  </div>
</template>
