<script setup lang="ts">
import { ref, onMounted, watch } from 'vue'
import { useRouter } from 'vue-router'
import { useMediaTags } from '../composables/useMediaTags'
import { useGalleryStore } from '../stores/gallery'
import TagBadge from '../components/TagBadge.vue'
import TagShortcodeModal from '../components/TagShortcodeModal.vue'
import LoadingSpinner from '../components/LoadingSpinner.vue'
import ErrorMessage from '../components/ErrorMessage.vue'

const props = defineProps<{
  mediaType: 'images' | 'videos'
  mediaId: number
}>()

const router = useRouter()
const store = useGalleryStore()
const { tags, mediaItem, loading, error, fetchMediaAndTags, addTags, removeTag } = useMediaTags()

const newTagInput = ref('')
const showHelpModal = ref(false)

const mediaUrl = ref('')

onMounted(load)
watch(() => [props.mediaType, props.mediaId], load)

async function load() {
  await fetchMediaAndTags(props.mediaType, props.mediaId)
  if (mediaItem.value) {
    const folder = props.mediaType === 'images' ? 'images/full' : 'videos/full'
    mediaUrl.value = `/${folder}/${mediaItem.value.file_name}`
  }
  document.title = `Gallery - ${props.mediaType === 'images' ? 'Image' : 'Video'} Tags`
}

function backToGallery() {
  router.push({ name: props.mediaType, params: { page: 1, perPage: 40 } })
}

async function onAddTags() {
  if (!newTagInput.value.trim()) return
  await addTags(props.mediaType, props.mediaId, newTagInput.value.trim())
  newTagInput.value = ''
}

async function onRemoveTag(tagId: number) {
  if (confirm('Are you sure you want to remove this tag?')) {
    await removeTag(props.mediaType, props.mediaId, tagId)
  }
}

const isVideo = (url: string) => {
  const ext = url.split('.').pop()?.toLowerCase()
  return ext && ['mp4', 'webm', 'mov', 'avi', 'mkv'].includes(ext)
}
</script>

<template>
  <section class="section">
    <div class="container">
      <LoadingSpinner v-if="loading" />
      <ErrorMessage v-else-if="error" :message="error" />
      <template v-else>
        <div class="columns">
          <div class="column is-three-fifths">
            <figure class="image tags-page-img">
              <video v-if="mediaUrl && isVideo(mediaUrl)" controls :src="mediaUrl" />
              <img v-else-if="mediaUrl" :src="mediaUrl" alt="" />
            </figure>
            <p v-if="mediaItem?.hash" class="help mt-2">MD5 Hash: {{ mediaItem.hash }}</p>
          </div>

          <div class="column">
            <div class="content">
              <button class="button is-link" @click="backToGallery">
                <span class="icon"><i class="fa-solid fa-backward"></i></span>
                <span>Back to Gallery</span>
              </button>

              <h2>Add More Tags</h2>
              <label class="label">Tags</label>
              <div class="field has-addons">
                <div class="control is-expanded has-icons-left">
                  <input class="input" type="text" v-model="newTagInput" placeholder="Add tags..."
                    list="add-list-tags" @keyup.enter="onAddTags" />
                  <span class="icon is-left"><i class="fa-solid fa-tags"></i></span>
                  <datalist id="add-list-tags">
                    <option v-for="tag in store.allTags" :key="tag.tag_id" :value="tag.tag_name" />
                  </datalist>
                </div>
                <div class="control">
                  <button class="button is-primary" @click="onAddTags">Add Tags</button>
                </div>
              </div>
              <p class="help">
                Add tags. Multiple tags should be separated by a comma. Use the appropriate
                <a @click.prevent="showHelpModal = true" style="cursor:pointer">shortcodes</a>
                before tags for proper categories.
              </p>
            </div>

            <div class="tags are-medium">
              <TagBadge
                v-for="tag in tags"
                :key="tag.tag_id"
                :tag-id="tag.tag_id"
                :tag-name="tag.tag_name"
                :category-id="tag.category_id"
                :removable="true"
                @remove="onRemoveTag"
              />
            </div>
          </div>
        </div>
      </template>

      <!-- Shortcode Help Modal -->
      <div class="modal" :class="{ 'is-active': showHelpModal }">
        <div class="modal-background" @click="showHelpModal = false"></div>
        <TagShortcodeModal @close="showHelpModal = false" />
      </div>
    </div>
  </section>
</template>

