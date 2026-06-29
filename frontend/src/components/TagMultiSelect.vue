<script setup lang="ts">
import { ref, computed } from 'vue'
import { useGalleryStore, type Tag } from '../stores/gallery'
import { getCategoryClassById } from '../constants/categories'

const props = withDefaults(defineProps<{
  modelValue: number[]
  excludeTagIds?: number[]
  placeholder?: string
}>(), {
  excludeTagIds: () => [],
  placeholder: 'Search tags...'
})

const emit = defineEmits<{
  'update:modelValue': [ids: number[]]
  submit: []
}>()

const store = useGalleryStore()

const inputText = ref('')
const showDropdown = ref(false)
const highlightedIndex = ref(-1)
const inputRef = ref<HTMLInputElement | null>(null)

const filteredTags = computed(() => {
  let query = inputText.value.toLowerCase().trim()
  // Strip leading '-' for dropdown matching so negation prefix doesn't break search
  if (query.startsWith('-')) query = query.substring(1).trim()
  const usedIds = new Set([...props.excludeTagIds, ...props.modelValue.map(id => Math.abs(id))])

  let results = store.allTags.filter(tag => !usedIds.has(tag.tag_id))

  if (query.length > 0) {
    results = results
      .filter(tag => tag.tag_name.toLowerCase().includes(query))
      .sort((a, b) => {
        const aName = a.tag_name.toLowerCase()
        const bName = b.tag_name.toLowerCase()
        const aExact = aName === query
        const bExact = bName === query
        if (aExact !== bExact) return aExact ? -1 : 1
        const aPrefix = aName.startsWith(query)
        const bPrefix = bName.startsWith(query)
        if (aPrefix !== bPrefix) return aPrefix ? -1 : 1
        return aName.localeCompare(bName)
      })
  }

  return results.slice(0, 20)
})

/** Represents a selected tag, either included or excluded. */
interface SelectedTag extends Tag {
  negated: boolean
}

const selectedTagObjects = computed<SelectedTag[]>(() => {
  return props.modelValue
    .map(id => {
      const negated = id < 0
      const tag = store.allTags.find(t => t.tag_id === Math.abs(id))
      return tag ? { ...tag, negated } : undefined
    })
    .filter((t): t is SelectedTag => t !== undefined)
})

function selectTag(tag: Tag) {
  // If the user typed a '-' prefix, add as negated (negative ID)
  const negate = inputText.value.trimStart().startsWith('-')
  const id = negate ? -tag.tag_id : tag.tag_id
  emit('update:modelValue', [...props.modelValue, id])
  inputText.value = ''
  highlightedIndex.value = -1
  showDropdown.value = true
  inputRef.value?.focus()
}

function removeTag(signedId: number) {
  emit('update:modelValue', props.modelValue.filter(id => id !== signedId))
}

function toggleNegate(signedId: number) {
  emit('update:modelValue', props.modelValue.map(id => id === signedId ? -id : id))
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
    } else if (props.modelValue.length > 0) {
      emit('submit')
    }
  } else if (e.key === 'Backspace' && inputText.value === '' && props.modelValue.length > 0) {
    emit('update:modelValue', props.modelValue.slice(0, -1))
  } else if (e.key === 'Escape') {
    showDropdown.value = false
    inputRef.value?.blur()
  }
}

defineExpose({ focus: () => inputRef.value?.focus() })
</script>

<template>
  <div class="tag-multiselect">
    <div class="field has-addons">
      <div class="control is-expanded">
        <div class="tag-input-wrapper" @click="inputRef?.focus()">
          <span
            v-for="tag in selectedTagObjects"
            :key="tag.tag_id"
            class="tag"
            :class="tag.negated ? 'is-danger is-light tag-negated' : getCategoryClassById(tag.category_id)"
            :title="tag.negated ? `Excluding: ${tag.tag_name} (right-click to include)` : `Including: ${tag.tag_name} (right-click to exclude)`"
            @contextmenu.prevent="toggleNegate(tag.negated ? -tag.tag_id : tag.tag_id)"
          >
            <template v-if="tag.negated">-</template>{{ tag.tag_name }}
            <button class="delete is-small" @click.stop="removeTag(tag.negated ? -tag.tag_id : tag.tag_id)"></button>
          </span>
          <input
            ref="inputRef"
            type="text"
            class="tag-multiselect-input"
            v-model="inputText"
            :placeholder="placeholder"
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
      <slot name="actions" :selected-count="modelValue.length" />
    </div>
  </div>
</template>

<style scoped>
.tag-multiselect {
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

.tag-multiselect-input {
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

.tag-multiselect-input::placeholder {
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

.tag-negated {
  text-decoration: line-through;
}
</style>
