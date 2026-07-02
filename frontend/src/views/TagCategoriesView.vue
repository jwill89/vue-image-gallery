<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { useApi, getErrorMessage, hasAuthToken } from '../composables/useApi'
import { useGalleryStore } from '../stores/gallery'
import { useToastStore } from '../stores/toast'
import { endpoints } from '../api/endpoints'
import type { TagCategory } from '../types'
import { colorToTagClass, VALID_COLORS } from '../constants/categories'
import LoadingSpinner from '../components/LoadingSpinner.vue'

const api = useApi()
const store = useGalleryStore()
const toastStore = useToastStore()

const authenticated = ref(hasAuthToken())
const loading = ref(false)
const loadFailed = ref(false)
const categories = ref<TagCategory[]>([])

// Modal state
const showModal = ref(false)
const formMode = ref<'new' | 'edit'>('new')
const formId = ref(0)
const formName = ref('')
const formShort = ref('')
const formColor = ref('white')
const formDescription = ref('')
const formSortOrder = ref(0)
const formHelp = ref('')
const formHelpClass = ref('')
const formLoading = ref(false)

// Delete modal
const showDeleteModal = ref(false)
const deleteTarget = ref<TagCategory | null>(null)
const deleteLoading = ref(false)

async function loadCategories() {
  loading.value = true
  loadFailed.value = false
  try {
    categories.value = await api.get<TagCategory[]>(endpoints.tagCategories.list)
  } catch (e) {
    toastStore.error(getErrorMessage(e, 'Failed to load categories.'))
    loadFailed.value = true
  } finally {
    loading.value = false
  }
}

function openNewModal() {
  formMode.value = 'new'
  formId.value = 0
  formName.value = ''
  formShort.value = ''
  formColor.value = 'white'
  formDescription.value = ''
  formSortOrder.value =
    categories.value.length > 0 ? Math.max(...categories.value.map((c) => c.sort_order)) + 1 : 0
  formHelp.value = ''
  formHelpClass.value = ''
  showModal.value = true
}

function openEditModal(cat: TagCategory) {
  formMode.value = 'edit'
  formId.value = cat.category_id
  formName.value = cat.category_name
  formShort.value = cat.category_short
  formColor.value = cat.color
  formDescription.value = cat.description
  formSortOrder.value = cat.sort_order
  formHelp.value = ''
  formHelpClass.value = ''
  showModal.value = true
}

function closeModal() {
  showModal.value = false
}

async function submitForm() {
  if (!formName.value.trim()) {
    formHelp.value = 'Category name is required.'
    formHelpClass.value = 'is-danger'
    return
  }
  if (!formShort.value.trim()) {
    formHelp.value = 'Shortcode is required.'
    formHelpClass.value = 'is-danger'
    return
  }

  formLoading.value = true
  try {
    const payload = {
      category_name: formName.value.trim(),
      category_short: formShort.value.trim().toLowerCase(),
      color: formColor.value,
      description: formDescription.value.trim(),
      sort_order: formSortOrder.value,
    }

    if (formMode.value === 'edit') {
      await api.put<TagCategory>(endpoints.tagCategories.byId(formId.value), payload)
    } else {
      await api.post<TagCategory>(endpoints.tagCategories.list, payload)
    }
    await loadCategories()
    closeModal()
    void store.refreshTags()
    toastStore.success(formMode.value === 'edit' ? 'Category updated.' : 'Category created.')
  } catch (e) {
    formHelp.value = getErrorMessage(e, 'Error saving category.')
    formHelpClass.value = 'is-danger'
  } finally {
    formLoading.value = false
  }
}

function openDeleteConfirm(cat: TagCategory) {
  deleteTarget.value = cat
  showDeleteModal.value = true
}

async function confirmDelete() {
  if (!deleteTarget.value) return
  deleteLoading.value = true
  try {
    await api.del(endpoints.tagCategories.byId(deleteTarget.value.category_id))
    await loadCategories()
    showDeleteModal.value = false
    void store.refreshTags()
    toastStore.success('Category deleted.')
  } catch (e) {
    toastStore.error(getErrorMessage(e, 'Could not delete category.'))
    showDeleteModal.value = false
  } finally {
    deleteLoading.value = false
  }
}

onMounted(loadCategories)
</script>

<template>
  <section class="section">
    <div class="container">
      <LoadingSpinner v-if="loading" />

      <div v-else-if="loadFailed" class="has-text-centered py-6">
        <span class="icon is-large has-text-grey-light">
          <i class="fa-solid fa-palette fa-3x" />
        </span>
        <p class="is-size-5 has-text-grey mt-4">Could not load categories.</p>
        <button class="button is-indigo mt-4" @click="loadCategories">
          <span class="icon"><i class="fa-solid fa-rotate-right" /></span>
          <span>Retry</span>
        </button>
      </div>

      <template v-else>
        <!-- Header -->
        <div class="level mb-4">
          <div class="level-left">
            <div class="level-item">
              <h1 class="title is-4 mb-0">Tag Categories</h1>
            </div>
          </div>
          <div v-if="authenticated" class="level-right">
            <div class="level-item">
              <button class="button is-primary" @click="openNewModal">
                <span class="icon"><i class="fa-solid fa-plus" /></span>
                <span>New Category</span>
              </button>
            </div>
          </div>
        </div>

        <p class="mb-5 has-text-grey">
          Manage the tag categories used throughout the gallery. Each category has a unique
          shortcode (used when adding tags, e.g. <code>a:artist name</code>) and a display color.
        </p>

        <!-- Categories Table -->
        <table class="table is-striped is-hoverable is-fullwidth">
          <thead>
            <tr>
              <th>ID</th>
              <th>Order</th>
              <th>Name</th>
              <th>Shortcode</th>
              <th>Color</th>
              <th>Description</th>
              <th v-if="authenticated">Actions</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="cat in categories" :key="cat.category_id">
              <td>{{ cat.category_id }}</td>
              <td>{{ cat.sort_order }}</td>
              <td>
                <span class="tag is-medium" :class="colorToTagClass(cat.color)">{{
                  cat.category_name
                }}</span>
              </td>
              <td>
                <code>{{ cat.category_short }}:</code>
              </td>
              <td>
                <span class="tag is-small" :class="colorToTagClass(cat.color)">{{
                  cat.color
                }}</span>
              </td>
              <td>{{ cat.description }}</td>
              <td v-if="authenticated">
                <div class="buttons are-small">
                  <button
                    class="button is-cyan is-outlined"
                    title="Edit"
                    @click="openEditModal(cat)"
                  >
                    <span class="icon"><i class="fa-solid fa-pen" /></span>
                  </button>
                  <button
                    class="button is-danger is-outlined"
                    title="Delete"
                    @click="openDeleteConfirm(cat)"
                  >
                    <span class="icon"><i class="fa-solid fa-trash" /></span>
                  </button>
                </div>
              </td>
            </tr>
            <tr v-if="categories.length === 0">
              <td :colspan="authenticated ? 7 : 6" class="has-text-centered has-text-grey">
                No categories defined.
              </td>
            </tr>
          </tbody>
        </table>
      </template>

      <!-- New/Edit Category Modal -->
      <div class="modal" :class="{ 'is-active': showModal }">
        <div class="modal-background" @click="closeModal" />
        <div class="modal-card">
          <header class="modal-card-head">
            <p class="modal-card-title">
              <strong>{{ formMode === 'edit' ? 'Edit Category' : 'New Category' }}</strong>
            </p>
            <button class="delete" aria-label="close" @click="closeModal" />
          </header>
          <section class="modal-card-body">
            <div class="field">
              <label class="label">Category Name</label>
              <div class="control">
                <input
                  v-model="formName"
                  class="input"
                  type="text"
                  placeholder="e.g. Artist"
                  @keyup.enter="submitForm"
                />
              </div>
            </div>
            <div class="field">
              <label class="label">Shortcode</label>
              <div class="control">
                <input
                  v-model="formShort"
                  class="input"
                  type="text"
                  placeholder="e.g. a"
                  maxlength="5"
                  @keyup.enter="submitForm"
                />
              </div>
              <p class="help">
                A short prefix users type when adding tags (e.g. <code>a:artist name</code>).
              </p>
            </div>
            <div class="field">
              <label class="label">Display Color</label>
              <div class="color-picker">
                <button
                  v-for="c in VALID_COLORS"
                  :key="c"
                  type="button"
                  class="tag is-medium color-swatch"
                  :class="[colorToTagClass(c), { 'is-selected-swatch': formColor === c }]"
                  :title="c"
                  @click="formColor = c"
                >
                  {{ c }}
                </button>
              </div>
              <p class="help mt-2">
                Preview:
                <span class="tag is-medium" :class="colorToTagClass(formColor)">{{
                  formName || 'Sample'
                }}</span>
              </p>
            </div>
            <div class="field">
              <label class="label">Description</label>
              <div class="control">
                <textarea
                  v-model="formDescription"
                  class="textarea"
                  rows="2"
                  placeholder="Brief description shown in the tag help modal"
                />
              </div>
            </div>
            <div class="field">
              <label class="label">Sort Order</label>
              <div class="control">
                <input
                  v-model.number="formSortOrder"
                  class="input"
                  type="number"
                  min="0"
                  @keyup.enter="submitForm"
                />
              </div>
              <p class="help">
                Controls display order in the help modal and tag sorting. Lower numbers appear
                first.
              </p>
            </div>
            <p v-if="formHelp" class="help" :class="formHelpClass">
              {{ formHelp }}
            </p>
          </section>
          <footer class="modal-card-foot">
            <div class="buttons">
              <button
                class="button is-primary"
                :class="{ 'is-loading': formLoading }"
                @click="submitForm"
              >
                {{ formMode === 'edit' ? 'Save Changes' : 'Create Category' }}
              </button>
              <button class="button" @click="closeModal">Cancel</button>
            </div>
          </footer>
        </div>
      </div>

      <!-- Delete Confirmation Modal -->
      <div class="modal" :class="{ 'is-active': showDeleteModal }">
        <div class="modal-background" @click="showDeleteModal = false" />
        <div class="modal-card">
          <header class="modal-card-head">
            <p class="modal-card-title">
              <strong>Delete Category</strong>
            </p>
            <button class="delete" aria-label="close" @click="showDeleteModal = false" />
          </header>
          <section class="modal-card-body">
            <p>
              Are you sure you want to delete the
              <strong>{{ deleteTarget?.category_name }}</strong> category?
            </p>
            <p class="has-text-danger mt-2">
              <span class="icon"><i class="fa-solid fa-triangle-exclamation" /></span>
              This will only work if no tags are assigned to this category.
            </p>
          </section>
          <footer class="modal-card-foot">
            <div class="buttons">
              <button
                class="button is-danger"
                :class="{ 'is-loading': deleteLoading }"
                @click="confirmDelete"
              >
                Delete
              </button>
              <button class="button" @click="showDeleteModal = false">Cancel</button>
            </div>
          </footer>
        </div>
      </div>
    </div>
  </section>
</template>

<style scoped>
.color-picker {
  display: flex;
  flex-wrap: wrap;
  gap: 6px;
}

.color-swatch {
  cursor: pointer;
  border: 2px solid transparent;
  transition:
    border-color 0.15s,
    transform 0.15s;
  font-size: 0.7rem !important;
  text-transform: capitalize;
}

.color-swatch:hover {
  transform: scale(1.08);
}

.color-swatch.is-selected-swatch {
  border-color: #ffffff;
  box-shadow: 0 0 0 2px rgba(99, 102, 241, 0.5);
  transform: scale(1.08);
}
</style>
