<script setup lang="ts">
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
            <tr>
              <td><span class="tag is-white">General</span></td>
              <td><code>g:</code></td>
              <td>General terms that describe features. Default if no shortcode is used.</td>
            </tr>
            <tr>
              <td><span class="tag is-danger">Artist</span></td>
              <td><code>a:</code></td>
              <td>The artist who created this specific piece.</td>
            </tr>
            <tr>
              <td><span class="tag is-success">Character</span></td>
              <td><code>c:</code></td>
              <td>Character name. Use "First Last" format, e.g., <code>c:ichigo kurosaki</code>.</td>
            </tr>
            <tr>
              <td><span class="tag is-warning">Source</span></td>
              <td><code>s:</code></td>
              <td>Source material (movie, game, anime, etc.), e.g., <code>s:dragonball super</code>.</td>
            </tr>
            <tr>
              <td><span class="tag is-info">Personal List</span></td>
              <td><code>p:</code></td>
              <td>Personal lists for individuals to record favorites.</td>
            </tr>
          </tbody>
        </table>
      </div>
    </section>
  </div>
</template>
