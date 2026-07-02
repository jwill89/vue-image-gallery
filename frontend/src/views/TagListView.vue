<script setup lang="ts">
import { ref, onMounted, computed, watch } from 'vue'
import { useRouter } from 'vue-router'
import { useApi, getErrorMessage, hasAuthToken } from '../composables/useApi'
import { useGalleryStore } from '../stores/gallery'
import { useToastStore } from '../stores/toast'
import { endpoints } from '../api/endpoints'
import type { TagListItem } from '../types'
import { getTextClassByName, getCategoryClassByName } from '../constants/categories'
import LoadingSpinner from '../components/LoadingSpinner.vue'

const api = useApi()
const store = useGalleryStore()
const toastStore = useToastStore()
const router = useRouter()

const authenticated = ref(hasAuthToken())

const allDisplayTags = ref<TagListItem[]>([])
const loading = ref(false)
const searchQuery = ref('')
const sortKey = ref<'tag_name' | 'category_name' | 'media_count'>('tag_name')
const sortAsc = ref(true)
const currentPage = ref(1)
const pageSize = ref(25)

// Modal & Form state
const showFormModal = ref(false)
const formMode = ref<'new' | 'edit'>('new')
const formTagName = ref('')
const formCategoryId = ref<number | string>('')
const formEditId = ref<number | null>(null)
const formHelp = ref('')
const formHelpClass = ref('')

// Migrate modal state
const showMigrateModal = ref(false)
const migrateSourceTag = ref<TagListItem | null>(null)
const migrateTargetSearch = ref('')
const migrateTargetId = ref<number | null>(null)
const migrateHelp = ref('')
const migrateHelpClass = ref('')
const migrateLoading = ref(false)

// Delete modal state
const showDeleteModal = ref(false)
const deleteTargetTag = ref<TagListItem | null>(null)
const deleteMigrateFirst = ref(false)
const deleteMigrateTargetSearch = ref('')
const deleteMigrateTargetId = ref<number | null>(null)
const deleteHelp = ref('')
const deleteHelpClass = ref('')
const deleteLoading = ref(false)

const migrateTargetOptions = computed(() => {
  if (!migrateTargetSearch.value.trim()) return []
  const q = migrateTargetSearch.value.toLowerCase()
  return allDisplayTags.value
    .filter(
      (t) => t.tag_id !== migrateSourceTag.value?.tag_id && t.tag_name.toLowerCase().includes(q),
    )
    .slice(0, 10)
})

const deleteMigrateTargetOptions = computed(() => {
  if (!deleteMigrateTargetSearch.value.trim()) return []
  const q = deleteMigrateTargetSearch.value.toLowerCase()
  return allDisplayTags.value
    .filter(
      (t) => t.tag_id !== deleteTargetTag.value?.tag_id && t.tag_name.toLowerCase().includes(q),
    )
    .slice(0, 10)
})

const filteredTags = computed(() => {
  let filtered = allDisplayTags.value
  if (searchQuery.value.trim()) {
    const q = searchQuery.value.toLowerCase()
    filtered = filtered.filter(
      (t) =>
        t.tag_name.toLowerCase().includes(q) || (t.category_name ?? '').toLowerCase().includes(q),
    )
  }
  const key = sortKey.value
  filtered = [...filtered].sort((a, b) => {
    const va = a[key]
    const vb = b[key]
    if (typeof va === 'string' && typeof vb === 'string') {
      return sortAsc.value ? va.localeCompare(vb) : vb.localeCompare(va)
    }
    return sortAsc.value ? Number(va) - Number(vb) : Number(vb) - Number(va)
  })
  return filtered
})

const totalFilteredPages = computed(() =>
  Math.max(1, Math.ceil(filteredTags.value.length / pageSize.value)),
)
const pagedTags = computed(() => {
  const start = (currentPage.value - 1) * pageSize.value
  return filteredTags.value.slice(start, start + pageSize.value)
})

// Reset to page 1 when filtering causes current page to exceed total pages
watch(totalFilteredPages, (newTotal) => {
  if (currentPage.value > newTotal) {
    currentPage.value = 1
  }
})

function toggleSort(key: typeof sortKey.value) {
  if (sortKey.value === key) {
    sortAsc.value = !sortAsc.value
  } else {
    sortKey.value = key
    sortAsc.value = true
  }
}

function sortIcon(key: string) {
  if (sortKey.value !== key) return 'fa-sort'
  return sortAsc.value ? 'fa-sort-up' : 'fa-sort-down'
}

const loadFailed = ref(false)

async function loadTags(showSpinner = true) {
  if (showSpinner) loading.value = true
  loadFailed.value = false
  try {
    allDisplayTags.value = await api.get<TagListItem[]>(endpoints.tags.display)
  } catch (e) {
    toastStore.error(getErrorMessage(e, 'Failed to load tags'))
    loadFailed.value = true
    allDisplayTags.value = []
  } finally {
    loading.value = false
  }
}

function openNewTagModal() {
  resetForm()
  showFormModal.value = true
}

function editTag(tag: TagListItem) {
  formMode.value = 'edit'
  formEditId.value = tag.tag_id
  formTagName.value = tag.tag_name
  formCategoryId.value = tag.category_id
  formHelp.value = `Editing: ${tag.tag_name}`
  formHelpClass.value = 'is-warning'
  showFormModal.value = true
}

function resetForm() {
  formMode.value = 'new'
  formEditId.value = null
  formTagName.value = ''
  formCategoryId.value = ''
  formHelp.value = ''
  formHelpClass.value = ''
}

function closeModal() {
  showFormModal.value = false
  resetForm()
}

async function submitForm() {
  // Strip leading hyphens — they conflict with the negative-tag search prefix convention
  formTagName.value = formTagName.value.replace(/^-+/, '')

  if (!formTagName.value.trim()) {
    formHelp.value = 'Tag name cannot be empty.'
    formHelpClass.value = 'is-danger'
    return
  }

  if (!formCategoryId.value) {
    formHelp.value = 'Please select a category.'
    formHelpClass.value = 'is-danger'
    return
  }

  try {
    if (formMode.value === 'edit' && formEditId.value !== null) {
      await api.put(endpoints.tags.byId(formEditId.value), {
        tag_name: formTagName.value,
        category_id: Number(formCategoryId.value),
      })
    } else {
      // Check if tag already exists
      const exists = allDisplayTags.value.some(
        (t) => t.tag_name.toLowerCase() === formTagName.value.trim().toLowerCase(),
      )
      if (exists) {
        formHelp.value = 'Tag already exists.'
        formHelpClass.value = 'is-danger'
        return
      }
      await api.post(endpoints.tags.create, {
        tag_name: formTagName.value,
        category_id: Number(formCategoryId.value),
      })
    }
    closeModal()
    await loadTags(false)
    void store.refreshTags()
  } catch {
    formHelp.value = 'Error saving tag.'
    formHelpClass.value = 'is-danger'
  }
}

function searchByTag(tagName: string) {
  void router.push({
    name: 'media-with-tags',
    params: { page: 1, perPage: 40, tags: tagName },
  })
}

function onTagNameInput() {
  if (formMode.value === 'edit') return
  // Debounce: clear any pending timer and start a new one
  clearTimeout(tagNameDebounceTimer)
  tagNameDebounceTimer = window.setTimeout(() => {
    if (!formTagName.value.trim()) {
      formHelp.value = ''
      formHelpClass.value = ''
      return
    }
    const exists = allDisplayTags.value.some(
      (t) => t.tag_name.toLowerCase() === formTagName.value.trim().toLowerCase(),
    )
    if (exists) {
      formHelp.value = 'Tag already exists.'
      formHelpClass.value = 'is-danger'
    } else {
      formHelp.value = 'Tag is available.'
      formHelpClass.value = 'is-success'
    }
  }, 150)
}

// Debounce timer handle
let tagNameDebounceTimer: number = 0

function openMigrateModal(tag: TagListItem) {
  migrateSourceTag.value = tag
  migrateTargetSearch.value = ''
  migrateTargetId.value = null
  migrateHelp.value = ''
  migrateHelpClass.value = ''
  showMigrateModal.value = true
}

function closeMigrateModal() {
  showMigrateModal.value = false
  migrateSourceTag.value = null
}

function selectMigrateTarget(tag: TagListItem) {
  migrateTargetId.value = tag.tag_id
  migrateTargetSearch.value = tag.tag_name
}

async function submitMigrate() {
  if (!migrateTargetId.value || !migrateSourceTag.value) {
    migrateHelp.value = 'Please select a target tag.'
    migrateHelpClass.value = 'is-danger'
    return
  }

  migrateLoading.value = true
  try {
    await api.post(endpoints.tags.migrate(migrateSourceTag.value.tag_id), {
      target_tag_id: migrateTargetId.value,
    })
    closeMigrateModal()
    await loadTags(false)
    void store.refreshTags()
  } catch {
    migrateHelp.value = 'Error migrating tag.'
    migrateHelpClass.value = 'is-danger'
  } finally {
    migrateLoading.value = false
  }
}

function openDeleteModal(tag: TagListItem) {
  deleteTargetTag.value = tag
  deleteMigrateFirst.value = false
  deleteMigrateTargetSearch.value = ''
  deleteMigrateTargetId.value = null
  deleteHelp.value = ''
  deleteHelpClass.value = ''
  showDeleteModal.value = true
}

function closeDeleteModal() {
  showDeleteModal.value = false
  deleteTargetTag.value = null
}

function selectDeleteMigrateTarget(tag: TagListItem) {
  deleteMigrateTargetId.value = tag.tag_id
  deleteMigrateTargetSearch.value = tag.tag_name
}

async function submitDelete() {
  if (!deleteTargetTag.value) return

  if (deleteMigrateFirst.value && !deleteMigrateTargetId.value) {
    deleteHelp.value = 'Please select a tag to migrate to, or uncheck the migrate option.'
    deleteHelpClass.value = 'is-danger'
    return
  }

  deleteLoading.value = true
  try {
    const migrateToId = deleteMigrateFirst.value ? deleteMigrateTargetId.value : 0
    await api.del(
      migrateToId
        ? endpoints.tags.deleteMigrateTo(deleteTargetTag.value.tag_id, migrateToId)
        : endpoints.tags.byId(deleteTargetTag.value.tag_id),
    )
    closeDeleteModal()
    await loadTags(false)
    void store.refreshTags()
  } catch {
    deleteHelp.value = 'Error deleting tag.'
    deleteHelpClass.value = 'is-danger'
  } finally {
    deleteLoading.value = false
  }
}

onMounted(() => {
  void loadTags()
})
</script>

<template>
  <section class="section">
    <div class="container">
      <LoadingSpinner v-if="loading" />
      <div v-else-if="loadFailed" class="has-text-centered py-6">
        <span class="icon is-large has-text-grey-light">
          <i class="fa-solid fa-tags fa-3x" />
        </span>
        <p class="is-size-5 has-text-grey mt-4">Could not load tags. Please try again.</p>
        <button class="button is-indigo mt-4" @click="loadTags()">
          <span class="icon"><i class="fa-solid fa-rotate-right" /></span>
          <span>Retry</span>
        </button>
      </div>
      <template v-else>
        <!-- Header with New Tag button -->
        <div class="level mb-4">
          <div class="level-left">
            <div class="level-item">
              <h1 class="title is-4">Tags</h1>
            </div>
          </div>
          <div v-if="authenticated" class="level-right">
            <div class="level-item">
              <div class="buttons">
                <button class="button is-primary" @click="openNewTagModal">
                  <span class="icon"><i class="fa-solid fa-plus" /></span>
                  <span>New Tag</span>
                </button>
                <router-link class="button is-purple is-outlined" :to="{ name: 'tag-categories' }">
                  <span class="icon"><i class="fa-solid fa-palette" /></span>
                  <span>Categories</span>
                </router-link>
                <router-link class="button is-cyan is-outlined" :to="{ name: 'danbooru-rules' }">
                  <span class="icon"><i class="fa-solid fa-file-import" /></span>
                  <span>Import Rules</span>
                </router-link>
              </div>
            </div>
          </div>
        </div>

        <!-- Search and page size -->
        <div class="field is-grouped mb-4">
          <div class="control is-expanded">
            <input v-model="searchQuery" class="input" type="text" placeholder="Search tags..." />
          </div>
          <div class="control">
            <div class="select">
              <select v-model.number="pageSize">
                <option :value="10">10</option>
                <option :value="25">25</option>
                <option :value="50">50</option>
                <option :value="100">100</option>
              </select>
            </div>
          </div>
        </div>

        <!-- Tag Table -->
        <table class="table is-striped is-hoverable is-fullwidth">
          <thead>
            <tr>
              <th style="cursor: pointer" @click="toggleSort('tag_name')">
                Tag <i class="fas" :class="sortIcon('tag_name')" />
              </th>
              <th style="cursor: pointer" @click="toggleSort('category_name')">
                Category <i class="fas" :class="sortIcon('category_name')" />
              </th>
              <th style="cursor: pointer" @click="toggleSort('media_count')">
                Media <i class="fas" :class="sortIcon('media_count')" />
              </th>
              <th>Implies</th>
              <th v-if="authenticated">Actions</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="tag in pagedTags" :key="tag.tag_id">
              <td>
                <span :class="getTextClassByName(tag.category_name)">
                  {{ tag.tag_name }}
                </span>
              </td>
              <td>
                <span class="tag is-medium" :class="getCategoryClassByName(tag.category_name)">
                  {{ tag.category_name }}
                </span>
              </td>
              <td>
                <a
                  v-if="tag.media_count > 0"
                  class="has-text-link"
                  @click="searchByTag(tag.tag_name)"
                >
                  {{ tag.media_count }}
                </a>
                <span v-else>0</span>
              </td>
              <td>
                <router-link
                  v-if="tag.implication_count > 0"
                  :to="{ name: 'tag-implications', params: { tagId: tag.tag_id } }"
                  class="has-text-link"
                >
                  {{ tag.implication_count }}
                </router-link>
                <router-link
                  v-else
                  :to="{ name: 'tag-implications', params: { tagId: tag.tag_id } }"
                  class="has-text-grey-light"
                >
                  0
                </router-link>
              </td>
              <td v-if="authenticated">
                <div class="buttons are-small">
                  <button class="button is-cyan is-outlined" title="Edit" @click="editTag(tag)">
                    <span class="icon"><i class="fa-solid fa-pen" /></span>
                  </button>
                  <button
                    class="button is-amber is-outlined"
                    title="Migrate"
                    @click="openMigrateModal(tag)"
                  >
                    <span class="icon"><i class="fa-solid fa-arrow-right-arrow-left" /></span>
                  </button>
                  <button
                    class="button is-danger is-outlined"
                    title="Delete"
                    @click="openDeleteModal(tag)"
                  >
                    <span class="icon"><i class="fa-solid fa-trash" /></span>
                  </button>
                </div>
              </td>
            </tr>
            <tr v-if="pagedTags.length === 0">
              <td :colspan="authenticated ? 5 : 4" class="has-text-centered">No tags found.</td>
            </tr>
          </tbody>
        </table>

        <!-- Pagination -->
        <nav v-if="totalFilteredPages > 1" class="pagination is-centered is-small">
          <a
            class="pagination-previous"
            :class="{ 'is-disabled': currentPage <= 1 }"
            @click.prevent="currentPage > 1 && currentPage--"
            >Previous</a
          >
          <a
            class="pagination-next"
            :class="{ 'is-disabled': currentPage >= totalFilteredPages }"
            @click.prevent="currentPage < totalFilteredPages && currentPage++"
            >Next</a
          >
          <ul class="pagination-list">
            <li>
              <span class="pagination-link is-current"
                >Page {{ currentPage }} of {{ totalFilteredPages }}</span
              >
            </li>
          </ul>
        </nav>
      </template>

      <!-- New/Edit Tag Modal -->
      <div class="modal" :class="{ 'is-active': showFormModal }">
        <div class="modal-background" @click="closeModal" />
        <div class="modal-card">
          <header class="modal-card-head">
            <p class="modal-card-title">
              <strong>{{ formMode === 'edit' ? 'Edit Tag' : 'New Tag' }}</strong>
            </p>
            <button class="delete" aria-label="close" @click="closeModal" />
          </header>
          <section class="modal-card-body">
            <div class="field">
              <label class="label">Tag Name</label>
              <div class="control has-icons-left">
                <input
                  v-model="formTagName"
                  class="input"
                  :class="formHelpClass"
                  type="text"
                  placeholder="Enter tag name"
                  @input="onTagNameInput"
                  @keyup.enter="submitForm"
                />
                <span class="icon is-left">
                  <i class="fa-solid fa-tag" />
                </span>
              </div>
              <p v-if="formHelp" class="help" :class="formHelpClass">
                {{ formHelp }}
              </p>
            </div>
            <div class="field">
              <label class="label">Category</label>
              <div class="control">
                <div class="select is-fullwidth">
                  <select v-model="formCategoryId">
                    <option value="">Select a Category</option>
                    <option
                      v-for="cat in store.categories"
                      :key="cat.category_id"
                      :value="cat.category_id"
                    >
                      {{ cat.category_name }}
                    </option>
                  </select>
                </div>
              </div>
            </div>
          </section>
          <footer class="modal-card-foot">
            <div class="buttons">
              <button class="button is-primary" @click="submitForm">
                {{ formMode === 'edit' ? 'Save Changes' : 'Create Tag' }}
              </button>
              <button class="button" @click="closeModal">Cancel</button>
            </div>
          </footer>
        </div>
      </div>

      <!-- Migrate Tag Modal -->
      <div class="modal" :class="{ 'is-active': showMigrateModal }">
        <div class="modal-background" @click="closeMigrateModal" />
        <div class="modal-card">
          <header class="modal-card-head">
            <p class="modal-card-title">
              <strong>Migrate Tag</strong>
            </p>
            <button class="delete" aria-label="close" @click="closeMigrateModal" />
          </header>
          <section class="modal-card-body">
            <p class="mb-4">
              Migrate all items tagged with
              <strong>{{ migrateSourceTag?.tag_name }}</strong>
              to another tag. Items that already have the target tag will simply have the source tag
              removed.
            </p>
            <div class="field">
              <label class="label">Target Tag</label>
              <div class="control">
                <input
                  v-model="migrateTargetSearch"
                  class="input"
                  type="text"
                  placeholder="Search for target tag..."
                />
              </div>
              <div v-if="migrateTargetOptions.length > 0" class="dropdown-list">
                <a
                  v-for="t in migrateTargetOptions"
                  :key="t.tag_id"
                  class="dropdown-item"
                  :class="{ 'is-active': migrateTargetId === t.tag_id }"
                  @click="selectMigrateTarget(t)"
                >
                  <span :class="getTextClassByName(t.category_name)">{{ t.tag_name }}</span>
                  <span
                    class="tag is-small ml-2"
                    :class="getCategoryClassByName(t.category_name)"
                    >{{ t.category_name }}</span
                  >
                </a>
              </div>
            </div>
            <p v-if="migrateHelp" class="help" :class="migrateHelpClass">
              {{ migrateHelp }}
            </p>
          </section>
          <footer class="modal-card-foot">
            <div class="buttons">
              <button
                class="button is-warning"
                :class="{ 'is-loading': migrateLoading }"
                @click="submitMigrate"
              >
                Migrate
              </button>
              <button class="button" @click="closeMigrateModal">Cancel</button>
            </div>
          </footer>
        </div>
      </div>

      <!-- Delete Tag Modal -->
      <div class="modal" :class="{ 'is-active': showDeleteModal }">
        <div class="modal-background" @click="closeDeleteModal" />
        <div class="modal-card">
          <header class="modal-card-head">
            <p class="modal-card-title">
              <strong>Delete Tag</strong>
            </p>
            <button class="delete" aria-label="close" @click="closeDeleteModal" />
          </header>
          <section class="modal-card-body">
            <p class="mb-4">
              Are you sure you want to delete
              <strong>{{ deleteTargetTag?.tag_name }}</strong
              >? This will remove the tag from all items.
            </p>
            <div class="field">
              <label class="checkbox">
                <input v-model="deleteMigrateFirst" type="checkbox" />
                Migrate items to another tag before deleting
              </label>
            </div>
            <div v-if="deleteMigrateFirst" class="field">
              <label class="label">Migrate To</label>
              <div class="control">
                <input
                  v-model="deleteMigrateTargetSearch"
                  class="input"
                  type="text"
                  placeholder="Search for target tag..."
                />
              </div>
              <div v-if="deleteMigrateTargetOptions.length > 0" class="dropdown-list">
                <a
                  v-for="t in deleteMigrateTargetOptions"
                  :key="t.tag_id"
                  class="dropdown-item"
                  :class="{ 'is-active': deleteMigrateTargetId === t.tag_id }"
                  @click="selectDeleteMigrateTarget(t)"
                >
                  <span :class="getTextClassByName(t.category_name)">{{ t.tag_name }}</span>
                  <span
                    class="tag is-small ml-2"
                    :class="getCategoryClassByName(t.category_name)"
                    >{{ t.category_name }}</span
                  >
                </a>
              </div>
            </div>
            <p v-if="deleteHelp" class="help" :class="deleteHelpClass">
              {{ deleteHelp }}
            </p>
          </section>
          <footer class="modal-card-foot">
            <div class="buttons">
              <button
                class="button is-danger"
                :class="{ 'is-loading': deleteLoading }"
                @click="submitDelete"
              >
                Delete
              </button>
              <button class="button" @click="closeDeleteModal">Cancel</button>
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
