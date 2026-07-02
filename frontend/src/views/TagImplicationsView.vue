<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import {
  useApi,
  getErrorMessage,
  getErrorCode,
  getErrorStatus,
  hasAuthToken,
} from '../composables/useApi'
import { useToastStore } from '../stores/toast'
import { endpoints } from '../api/endpoints'
import type { TagListItem, TagImplication } from '../types'
import { getTextClassByName, getCategoryClassByName } from '../constants/categories'
import LoadingSpinner from '../components/LoadingSpinner.vue'

const props = defineProps<{
  tagId: number
}>()

const api = useApi()
const toastStore = useToastStore()

const authenticated = ref(hasAuthToken())
const loading = ref(false)
const loadFailed = ref(false)

// Tag info
const tagInfo = ref<TagListItem | null>(null)

// All implications (filtered client-side for this tag)
const allImplications = ref<TagImplication[]>([])

// Implications where this tag is the trigger
const impliesOthers = computed(() => allImplications.value.filter((i) => i.tag_id === props.tagId))

// Implications where this tag is implied by others
const impliedByOthers = computed(() =>
  allImplications.value.filter((i) => i.implied_tag_id === props.tagId),
)

// All tags for the search dropdowns
const allTags = ref<TagListItem[]>([])

// Add implication modal state
const showModal = ref(false)
const impliedSearch = ref('')
const impliedTagId = ref<number | null>(null)
const implHelp = ref('')
const implHelpClass = ref('')
const implLoading = ref(false)

const impliedOptions = computed(() => {
  if (!impliedSearch.value.trim()) return []
  const q = impliedSearch.value.toLowerCase()
  return allTags.value
    .filter((t) => t.tag_id !== props.tagId && t.tag_name.toLowerCase().includes(q))
    .slice(0, 10)
})

async function loadData() {
  loading.value = true
  loadFailed.value = false
  try {
    const [tags, implications] = await Promise.all([
      api.get<TagListItem[]>(endpoints.tags.display),
      api.get<TagImplication[]>(endpoints.tagImplications.list),
    ])

    allTags.value = tags
    allImplications.value = implications
    tagInfo.value = tags.find((t) => t.tag_id === props.tagId) ?? null
  } catch (e) {
    toastStore.error(getErrorMessage(e, 'Failed to load tag data'))
    loadFailed.value = true
  } finally {
    loading.value = false
  }
}

function openModal() {
  impliedSearch.value = ''
  impliedTagId.value = null
  implHelp.value = ''
  implHelpClass.value = ''
  showModal.value = true
}

function closeModal() {
  showModal.value = false
}

function selectImplied(tag: TagListItem) {
  impliedTagId.value = tag.tag_id
  impliedSearch.value = tag.tag_name
}

async function submitImplication() {
  if (!impliedTagId.value) {
    implHelp.value = 'Please select an implied tag.'
    implHelpClass.value = 'is-danger'
    return
  }

  // Check for duplicate
  const exists = impliesOthers.value.some((i) => i.implied_tag_id === impliedTagId.value)
  if (exists) {
    implHelp.value = 'This implication already exists.'
    implHelpClass.value = 'is-warning'
    return
  }

  implLoading.value = true
  try {
    await api.post<TagImplication>(endpoints.tagImplications.list, {
      tag_id: props.tagId,
      implied_tag_id: impliedTagId.value,
    })
    allImplications.value = await api.get<TagImplication[]>(endpoints.tagImplications.list)
    closeModal()
    toastStore.success('Implication added.')
  } catch (e) {
    if (getErrorCode(e) === 'CycleDetected' || getErrorStatus(e) === 400) {
      implHelp.value = getErrorMessage(e, 'Cannot add this implication — it would create a cycle.')
      implHelpClass.value = 'is-danger'
    } else {
      implHelp.value = getErrorMessage(e, 'Error adding implication.')
      implHelpClass.value = 'is-danger'
    }
  } finally {
    implLoading.value = false
  }
}

async function removeImplication(tagId: number, impliedTagId: number) {
  if (!confirm('Remove this implication rule?')) return

  try {
    await api.del(endpoints.tagImplications.byPair(tagId, impliedTagId))
    allImplications.value = await api.get<TagImplication[]>(endpoints.tagImplications.list)
    toastStore.success('Implication removed.')
  } catch (e) {
    toastStore.error(getErrorMessage(e, 'Failed to remove implication'))
  }
}

onMounted(loadData)
</script>

<template>
  <section class="section">
    <div class="container">
      <LoadingSpinner v-if="loading" />

      <div v-else-if="loadFailed || !tagInfo" class="has-text-centered py-6">
        <span class="icon is-large has-text-grey-light">
          <i class="fa-solid fa-link fa-3x" />
        </span>
        <p class="is-size-5 has-text-grey mt-4">
          {{ loadFailed ? 'Could not load tag data. Please try again.' : 'Tag not found.' }}
        </p>
        <button v-if="loadFailed" class="button is-indigo mt-4" @click="loadData">
          <span class="icon"><i class="fa-solid fa-rotate-right" /></span>
          <span>Retry</span>
        </button>
      </div>

      <template v-else>
        <!-- Header -->
        <div class="level mb-4">
          <div class="level-left">
            <div class="level-item">
              <h1 class="title is-4 mb-0">
                <span :class="getTextClassByName(tagInfo.category_name)">{{
                  tagInfo.tag_name
                }}</span>
                <span
                  class="tag is-medium ml-2"
                  :class="getCategoryClassByName(tagInfo.category_name)"
                >
                  {{ tagInfo.category_name }}
                </span>
              </h1>
            </div>
          </div>
          <div v-if="authenticated" class="level-right">
            <div class="level-item">
              <button class="button is-primary" @click="openModal">
                <span class="icon"><i class="fa-solid fa-plus" /></span>
                <span>Add Implication</span>
              </button>
            </div>
          </div>
        </div>

        <p class="mb-5 has-text-grey">
          When this tag is added to media, all implied tags below are automatically added too.
          Implications are transitive — if this tag implies <em>A</em> and <em>A</em> implies
          <em>B</em>, then adding this tag will also add both <em>A</em> and <em>B</em>.
        </p>

        <!-- This tag implies -->
        <h2 class="title is-5 mt-5">
          <span class="icon"><i class="fa-solid fa-arrow-right" /></span>
          <span>This tag implies ({{ impliesOthers.length }})</span>
        </h2>

        <table v-if="impliesOthers.length > 0" class="table is-striped is-hoverable is-fullwidth">
          <thead>
            <tr>
              <th>Implied Tag</th>
              <th v-if="authenticated" style="width: 80px">Actions</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="impl in impliesOthers" :key="impl.implied_tag_id">
              <td>
                <router-link
                  :to="{ name: 'tag-implications', params: { tagId: impl.implied_tag_id } }"
                >
                  <span class="tag is-medium is-white">{{ impl.implied_tag_name }}</span>
                </router-link>
              </td>
              <td v-if="authenticated">
                <button
                  class="button is-small is-danger is-outlined"
                  title="Remove"
                  @click="removeImplication(impl.tag_id, impl.implied_tag_id)"
                >
                  <span class="icon"><i class="fa-solid fa-trash" /></span>
                </button>
              </td>
            </tr>
          </tbody>
        </table>
        <p v-else class="has-text-centered has-text-grey py-4">
          This tag does not imply any other tags.
        </p>

        <hr />

        <!-- Implied by others -->
        <h2 class="title is-5">
          <span class="icon"><i class="fa-solid fa-arrow-left" /></span>
          <span>Implied by ({{ impliedByOthers.length }})</span>
        </h2>

        <table v-if="impliedByOthers.length > 0" class="table is-striped is-hoverable is-fullwidth">
          <thead>
            <tr>
              <th>Trigger Tag</th>
              <th v-if="authenticated" style="width: 80px">Actions</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="impl in impliedByOthers" :key="impl.tag_id">
              <td>
                <router-link :to="{ name: 'tag-implications', params: { tagId: impl.tag_id } }">
                  <span class="tag is-medium is-white">{{ impl.tag_name }}</span>
                </router-link>
              </td>
              <td v-if="authenticated">
                <button
                  class="button is-small is-danger is-outlined"
                  title="Remove"
                  @click="removeImplication(impl.tag_id, impl.implied_tag_id)"
                >
                  <span class="icon"><i class="fa-solid fa-trash" /></span>
                </button>
              </td>
            </tr>
          </tbody>
        </table>
        <p v-else class="has-text-centered has-text-grey py-4">No other tags imply this tag.</p>
      </template>

      <!-- Add Implication Modal -->
      <div class="modal" :class="{ 'is-active': showModal }">
        <div class="modal-background" @click="closeModal" />
        <div class="modal-card">
          <header class="modal-card-head">
            <p class="modal-card-title">
              <strong>Add Implication for {{ tagInfo?.tag_name }}</strong>
            </p>
            <button class="delete" aria-label="close" @click="closeModal" />
          </header>
          <section class="modal-card-body">
            <p class="mb-4">
              When <strong>{{ tagInfo?.tag_name }}</strong> is added to a media item, the selected
              tag will be automatically added too.
            </p>
            <div class="field">
              <label class="label">Implied Tag</label>
              <div class="control">
                <input
                  v-model="impliedSearch"
                  class="input"
                  type="text"
                  placeholder="Search for a tag..."
                />
              </div>
              <div v-if="impliedOptions.length > 0" class="dropdown-list">
                <a
                  v-for="t in impliedOptions"
                  :key="t.tag_id"
                  class="dropdown-item"
                  :class="{ 'is-active': impliedTagId === t.tag_id }"
                  @click="selectImplied(t)"
                >
                  <span :class="getTextClassByName(t.category_name)">{{ t.tag_name }}</span>
                  <span class="tag is-small ml-2" :class="getCategoryClassByName(t.category_name)">
                    {{ t.category_name }}
                  </span>
                </a>
              </div>
            </div>
            <p v-if="implHelp" class="help" :class="implHelpClass">
              {{ implHelp }}
            </p>
          </section>
          <footer class="modal-card-foot">
            <div class="buttons">
              <button
                class="button is-primary"
                :class="{ 'is-loading': implLoading }"
                @click="submitImplication"
              >
                Add Implication
              </button>
              <button class="button" @click="closeModal">Cancel</button>
            </div>
          </footer>
        </div>
      </div>
    </div>
  </section>
</template>

<style scoped>
.dropdown-list {
  border: 1px solid #dbdbdb;
  border-radius: 4px;
  max-height: 200px;
  overflow-y: auto;
  margin-top: 0.25rem;
}

.dropdown-item {
  display: flex;
  align-items: center;
  padding: 0.5rem 0.75rem;
  cursor: pointer;
}

.dropdown-item:hover,
.dropdown-item.is-active {
  background-color: #f5f5f5;
}
</style>
