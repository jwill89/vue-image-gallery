<script setup lang="ts">
import { ref, computed } from 'vue'
import { useGalleryStore, type Tag } from '../stores/gallery'
import { getCategoryClassById } from '../constants/categories'

const store = useGalleryStore()

const props = defineProps<{
  modelValue: string[]
}>()

const emit = defineEmits<{
  'update:modelValue': [tags: string[]]
  search: []
  reset: []
}>()

const inputText = ref('')
const showDropdown = ref(false)
const highlightedIndex = ref(-1)
const inputRef = ref<HTMLInputElement | null>(null)

// Filter tags based on current input text, excluding already-selected tags
const filteredTags = computed(() => {
  const query = inputText.value.toLowerCase().trim()
  const selected = new Set(props.modelValue.map(t => t.toLowerCase()))

  let results = store.allTags.filter(tag => !selected.has(tag.tag_name.toLowerCase()))

  if (query.length > 0) {
    results = results.filter(tag => tag.tag_name.toLowerCase().includes(query))
  }

  // Limit results to prevent overwhelming the UI
  return results.slice(0, 20)
})

const hasSelectedTags = computed(() => props.modelValue.length > 0)


function getCategoryForTag(tagName: string): number {
  const tag = store.allTags.find(t => t.tag_name.toLowerCase() === tagName.toLowerCase())
  return tag?.category_id ?? 1
}

function selectTag(tag: Tag) {
  const newTags = [...props.modelValue, tag.tag_name]
  emit('update:modelValue', newTags)
  inputText.value = ''
  highlightedIndex.value = -1
  // Keep dropdown open since input retains focus and there may be more tags to select
  showDropdown.value = true
  inputRef.value?.focus()
}

function removeTag(tagName: string) {
  const newTags = props.modelValue.filter(t => t !== tagName)
  emit('update:modelValue', newTags)
}

function onInput() {
  showDropdown.value = inputText.value.trim().length > 0 || filteredTags.value.length > 0
  highlightedIndex.value = -1
}

function onFocus() {
  if (inputText.value.trim().length > 0 || store.allTags.length > 0) {
    showDropdown.value = true
  }
}

function onBlur() {
  // Delay to allow click events on dropdown items
  setTimeout(() => {
    showDropdown.value = false
  }, 200)
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
    } else if (hasSelectedTags.value) {
      emit('search')
    }
  } else if (e.key === 'Backspace' && inputText.value === '' && props.modelValue.length > 0) {
    // Remove last tag on backspace when input is empty
    removeTag(props.modelValue[props.modelValue.length - 1])
  } else if (e.key === 'Escape') {
    showDropdown.value = false
    inputRef.value?.blur()
  }
}

function onSearchClick() {
  emit('search')
}

function onResetClick() {
  emit('reset')
}
</script>

<template>
  <div class="tag-search-container">
    <div class="field has-addons">
      <div class="control is-expanded">
        <div class="tag-input-wrapper" @click="inputRef?.focus()">
          <!-- Selected tags as colored badges -->
          <span
            v-for="tagName in modelValue"
            :key="tagName"
            class="tag"
            :class="getCategoryClassById(getCategoryForTag(tagName))"
          >
            {{ tagName }}
            <button class="delete is-small" @click.stop="removeTag(tagName)"></button>
          </span>

          <!-- Text input -->
          <input
            ref="inputRef"
            type="text"
            class="tag-search-input"
            v-model="inputText"
            placeholder="Search tags..."
            @input="onInput"
            @focus="onFocus"
            @blur="onBlur"
            @keydown="onKeydown"
          />
        </div>

        <!-- Dropdown -->
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

      <!-- Search / Reset button -->
      <div class="control" v-if="!hasSelectedTags">
        <button class="button" @click="onSearchClick" disabled>
          <span class="icon"><i class="fa-solid fa-magnifying-glass"></i></span>
        </button>
      </div>
      <div class="control" v-else>
        <button class="button is-primary" @click="onSearchClick">
          <span class="icon"><i class="fa-solid fa-magnifying-glass"></i></span>
          <span>Search</span>
        </button>
      </div>
      <div class="control" v-if="hasSelectedTags">
        <button class="button is-light" @click="onResetClick">
          <span class="icon"><i class="fa-solid fa-xmark"></i></span>
        </button>
      </div>
    </div>
  </div>
</template>

<style scoped>
.tag-search-container {
  position: relative;
  min-width: 280px;
  max-width: 400px;
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

.tag-search-input {
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

.tag-search-input::placeholder {
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


