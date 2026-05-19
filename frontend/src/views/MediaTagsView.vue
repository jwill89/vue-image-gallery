<script setup lang="ts">
import { ref, computed, onMounted, watch } from 'vue'
import { useRouter } from 'vue-router'
import { useMediaTags } from '../composables/useMediaTags'
import { useGalleryStore, type Tag } from '../stores/gallery'
import { hasAuthToken } from '../composables/useApi'
import { getCategoryClassById } from '../constants/categories'
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

const showHelpModal = ref(false)
const authenticated = ref(hasAuthToken())
const mediaUrl = ref('')

// Tag add multi-select state
const inputText = ref('')
const selectedTagIds = ref<number[]>([])
const showDropdown = ref(false)
const highlightedIndex = ref(-1)
const inputRef = ref<HTMLInputElement | null>(null)

const filteredTags = computed(() => {
  const query = inputText.value.toLowerCase().trim()
  const alreadyApplied = new Set(tags.value.map(t => t.tag_id))
  const selected = new Set(selectedTagIds.value)

  let results = store.allTags.filter(tag => !alreadyApplied.has(tag.tag_id) && !selected.has(tag.tag_id))

  if (query.length > 0) {
    results = results.filter(tag => tag.tag_name.toLowerCase().includes(query))
  }

  return results.slice(0, 20)
})

const selectedTagObjects = computed(() => {
  return selectedTagIds.value
    .map(id => store.allTags.find(t => t.tag_id === id))
    .filter((t): t is Tag => t !== undefined)
})

const detailsTitle = computed(() =>
  props.mediaType === 'images' ? 'Image Details' : 'Video Details'
)

const formattedDate = computed(() => {
  if (!mediaItem.value?.file_time) return ''
  const date = new Date(mediaItem.value.file_time * 1000)
  return date.toLocaleString(undefined, {
    weekday: 'short', year: 'numeric', month: 'short', day: 'numeric',
    hour: '2-digit', minute: '2-digit', second: '2-digit', timeZoneName: 'short'
  })
})

const fullPath = computed(() => {
  if (!mediaItem.value) return ''
  const folder = props.mediaType === 'images' ? 'images/full' : 'videos/full'
  return `/${folder}/${mediaItem.value.file_name}`
})

onMounted(load)
watch(() => [props.mediaType, props.mediaId], load)

async function load() {
  await fetchMediaAndTags(props.mediaType, props.mediaId)
  if (mediaItem.value) {
    const folder = props.mediaType === 'images' ? 'images/full' : 'videos/full'
    mediaUrl.value = `/${folder}/${mediaItem.value.file_name}`
  }
}

function backToGallery() {
  router.back()
}

function selectTag(tag: Tag) {
  selectedTagIds.value = [...selectedTagIds.value, tag.tag_id]
  inputText.value = ''
  highlightedIndex.value = -1
  showDropdown.value = true
  inputRef.value?.focus()
}

function removeSelectedTag(tagId: number) {
  selectedTagIds.value = selectedTagIds.value.filter(id => id !== tagId)
}

async function onAddTags() {
  if (selectedTagIds.value.length === 0) return
  await addTags(props.mediaType, props.mediaId, [...selectedTagIds.value])
  selectedTagIds.value = []
}

async function onRemoveTag(tagId: number) {
  if (confirm('Are you sure you want to remove this tag?')) {
    await removeTag(props.mediaType, props.mediaId, tagId)
  }
}

function onInput() {
  showDropdown.value = true
  highlightedIndex.value = -1
}

function onFocus() {
  showDropdown.value = true
}

function onBlur() {
  setTimeout(() => { showDropdown.value = false }, 200)
}

function onKeydown(e: KeyboardEvent) {
  if (e.key === 'ArrowDown') {
    e.preventDefault()
    highlightedIndex.value = Math.min(highlightedIndex.value + 1, filteredTags.value.length - 1)
  } else if (e.key === 'ArrowUp') {
    e.preventDefault()
    highlightedIndex.value = Math.max(highlightedIndex.value - 1, -1)
  } else if (e.key === 'Enter') {
    e.preventDefault()
    if (highlightedIndex.value >= 0 && highlightedIndex.value < filteredTags.value.length) {
      selectTag(filteredTags.value[highlightedIndex.value])
    } else if (inputText.value.trim() && filteredTags.value.length > 0) {
      selectTag(filteredTags.value[0])
    } else if (selectedTagIds.value.length > 0) {
      onAddTags()
    }
  } else if (e.key === 'Backspace' && inputText.value === '' && selectedTagIds.value.length > 0) {
    selectedTagIds.value = selectedTagIds.value.slice(0, -1)
  } else if (e.key === 'Escape') {
    showDropdown.value = false
    inputRef.value?.blur()
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
              <video v-if="mediaUrl && isVideo(mediaUrl)" controls :src="mediaUrl" :class="{ 'thumb-blur': store.blurThumbnails }" />
              <img v-else-if="mediaUrl" :src="mediaUrl" alt="" :class="{ 'thumb-blur': store.blurThumbnails }" />
            </figure>
          </div>

          <div class="column">
            <button class="button is-link mb-4" @click="backToGallery">
              <span class="icon"><i class="fa-solid fa-backward"></i></span>
              <span>Back to Gallery</span>
            </button>

            <!-- Media Details -->
            <h2 class="title is-5">{{ detailsTitle }}</h2>
            <table class="table is-narrow is-fullwidth">
              <tbody>
                <tr>
                  <th>Date Added</th>
                  <td>{{ formattedDate }}</td>
                </tr>
                <tr>
                  <th>MD5 Hash</th>
                  <td><code>{{ mediaItem?.hash }}</code></td>
                </tr>
                <tr>
                  <th>Full {{ mediaType === 'images' ? 'Image' : 'Video' }}</th>
                  <td><a :href="fullPath" target="_blank">View Full {{ mediaType === 'images' ? 'Image' : 'Video' }} <i class="fa-solid fa-up-right-from-square fa-xs"></i></a></td>
                </tr>
              </tbody>
            </table>

            <hr />

            <!-- Add Tags -->
            <h3 class="title is-6">Add Tags</h3>
            <div class="add-tags-container">
              <div class="field has-addons">
                <div class="control is-expanded">
                  <div class="tag-input-wrapper" @click="inputRef?.focus()">
                    <span
                      v-for="tag in selectedTagObjects"
                      :key="tag.tag_id"
                      class="tag"
                      :class="getCategoryClassById(tag.category_id)"
                    >
                      {{ tag.tag_name }}
                      <button class="delete is-small" @click.stop="removeSelectedTag(tag.tag_id)"></button>
                    </span>
                    <input
                      ref="inputRef"
                      type="text"
                      class="tag-add-input"
                      v-model="inputText"
                      placeholder="Search tags to add..."
                      @input="onInput"
                      @focus="onFocus"
                      @blur="onBlur"
                      @keydown="onKeydown"
                    />
                  </div>

                  <div class="tag-dropdown" v-show="showDropdown && filteredTags.length > 0">
                    <div
                      v-for="(tag, idx) in filteredTags"
                      :key="tag.tag_id"
                      class="tag-dropdown-item"
                      :class="{ 'is-highlighted': idx === highlightedIndex }"
                      @mousedown.prevent="selectTag(tag)"
                    >
                      <span class="tag is-small" :class="getCategoryClassById(tag.category_id)">
                        {{ tag.tag_name }}
                      </span>
                    </div>
                    <div v-if="filteredTags.length === 20" class="tag-dropdown-footer">
                      <span class="is-size-7 has-text-grey">Type to filter more results...</span>
                    </div>
                  </div>
                </div>
                <div class="control">
                  <button class="button is-primary" @click="onAddTags" :disabled="selectedTagIds.length === 0">
                    Add Tags
                  </button>
                </div>
              </div>
              <p class="help">
                Add tags. Multiple tags are allowed.
                <a @click.prevent="showHelpModal = true" style="cursor:pointer">Click here</a>
                to read more about tag categories, differentiated by colors.
              </p>
            </div>

            <hr />

            <!-- Current Tags -->
            <h3 class="title is-6">Current Tags</h3>
            <div class="tags are-medium">
              <TagBadge
                v-for="tag in tags"
                :key="tag.tag_id"
                :tag-id="tag.tag_id"
                :tag-name="tag.tag_name"
                :category-id="tag.category_id"
                :removable="authenticated"
                @remove="onRemoveTag"
              />
              <span v-if="tags.length === 0" class="has-text-grey">No tags applied yet.</span>
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

<style scoped>
.add-tags-container {
  position: relative;
}

.tag-input-wrapper {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 4px;
  padding: 4px 8px;
  border: 1px solid #14161a;
  border-radius: 4px;
  background: #14161a;
  min-height: 40px;
  max-height: 120px;
  overflow-y: auto;
  cursor: text;
}

.tag-input-wrapper:focus-within {
  border-color: #485fc7;
  box-shadow: 0 0 0 0.125em rgba(72, 95, 199, 0.25);
}

.tag-add-input {
  flex: 1;
  min-width: 120px;
  border: none;
  outline: none;
  font-family: BlinkMacSystemFont, -apple-system, "Segoe UI", "Roboto", "Oxygen", "Ubuntu", "Cantarell", "Fira Sans", "Droid Sans", "Helvetica Neue", "Helvetica", "Arial", sans-serif;
  font-size: 1rem;
  padding: 4px;
  background: transparent;
  color: #f5f5f5;
}

.tag-add-input::placeholder {
  color: #7a7a7a;
}

.tag-dropdown {
  position: absolute;
  top: 100%;
  left: 0;
  right: 0;
  z-index: 100;
  background: #14161a;
  border: 1px solid #363636;
  border-top: none;
  border-radius: 0 0 4px 4px;
  box-shadow: 0 4px 6px rgba(0, 0, 0, 0.3);
  max-height: 250px;
  overflow-y: auto;
}

.tag-dropdown-item {
  padding: 8px 12px;
  cursor: pointer;
  transition: background 0.1s;
}

.tag-dropdown-item:hover,
.tag-dropdown-item.is-highlighted {
  background: #242424;
}

.tag-dropdown-footer {
  padding: 6px 12px;
  border-top: 1px solid #363636;
  text-align: center;
}
</style>
