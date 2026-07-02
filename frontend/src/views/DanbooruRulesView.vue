<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { useApi, getErrorMessage, hasAuthToken } from '../composables/useApi'
import { useGalleryStore } from '../stores/gallery'
import { useToastStore } from '../stores/toast'
import { endpoints } from '../api/endpoints'
import type { CategoryMapping, TagMapping } from '../types'
import { colorToTagClass } from '../constants/categories'
import LoadingSpinner from '../components/LoadingSpinner.vue'

const api = useApi()
const store = useGalleryStore()
const toastStore = useToastStore()

const authenticated = ref(hasAuthToken())
const loading = ref(false)
const loadFailed = ref(false)
const categoryMappings = ref<CategoryMapping[]>([])
const tagMappings = ref<TagMapping[]>([])

// ── Category Mapping Modal ─────────────────────────────────
const showCatModal = ref(false)
const catDanbooruId = ref<number | string>('')
const catDanbooruName = ref('')
const catGalleryCategoryId = ref<number | string>('')
const catHelp = ref('')
const catHelpClass = ref('')
const catLoading = ref(false)

// ── Tag Mapping Modal ──────────────────────────────────────
const showTagModal = ref(false)
const tagFormMode = ref<'new' | 'edit'>('new')
const tagFormId = ref(0)
const tagDanbooru = ref('')
const tagGallery = ref('')
const tagHelp = ref('')
const tagHelpClass = ref('')
const tagLoading = ref(false)

async function loadCategoryMappings() {
  categoryMappings.value = await api.get<CategoryMapping[]>(endpoints.danbooru.categoryMappings)
}

async function loadTagMappings() {
  tagMappings.value = await api.get<TagMapping[]>(endpoints.danbooru.tagMappings)
}

async function loadRules() {
  loading.value = true
  loadFailed.value = false
  try {
    await Promise.all([loadCategoryMappings(), loadTagMappings()])
  } catch (e) {
    toastStore.error(getErrorMessage(e, 'Failed to load Danbooru rules.'))
    loadFailed.value = true
  } finally {
    loading.value = false
  }
}

// ── Category Mapping CRUD ──────────────────────────────────

function openCatModal() {
  catDanbooruId.value = ''
  catDanbooruName.value = ''
  catGalleryCategoryId.value = ''
  catHelp.value = ''
  catHelpClass.value = ''
  showCatModal.value = true
}

async function submitCatMapping() {
  const dcid = Number(catDanbooruId.value)
  if (isNaN(dcid) || dcid < 0) {
    catHelp.value = 'Danbooru category ID must be a non-negative integer.'
    catHelpClass.value = 'is-danger'
    return
  }
  if (!catDanbooruName.value.trim()) {
    catHelp.value = 'Danbooru category name is required.'
    catHelpClass.value = 'is-danger'
    return
  }
  if (!catGalleryCategoryId.value) {
    catHelp.value = 'Select a gallery category.'
    catHelpClass.value = 'is-danger'
    return
  }

  catLoading.value = true
  try {
    await api.post<CategoryMapping>(endpoints.danbooru.categoryMappings, {
      danbooru_category_id: dcid,
      danbooru_category_name: catDanbooruName.value.trim(),
      gallery_category_id: Number(catGalleryCategoryId.value),
    })
    await loadCategoryMappings()
    showCatModal.value = false
    toastStore.success('Category mapping saved.')
  } catch (e) {
    catHelp.value = getErrorMessage(e, 'Error saving mapping.')
    catHelpClass.value = 'is-danger'
  } finally {
    catLoading.value = false
  }
}

async function deleteCatMapping(danbooruCategoryId: number) {
  if (!confirm('Remove this category mapping?')) return
  try {
    await api.del(endpoints.danbooru.categoryMapping(danbooruCategoryId))
    await loadCategoryMappings()
    toastStore.success('Category mapping removed.')
  } catch (e) {
    toastStore.error(getErrorMessage(e, 'Could not remove mapping.'))
  }
}

// ── Tag Name Mapping CRUD ──────────────────────────────────

function openNewTagMapping() {
  tagFormMode.value = 'new'
  tagFormId.value = 0
  tagDanbooru.value = ''
  tagGallery.value = ''
  tagHelp.value = ''
  tagHelpClass.value = ''
  showTagModal.value = true
}

function openEditTagMapping(m: TagMapping) {
  tagFormMode.value = 'edit'
  tagFormId.value = m.id
  tagDanbooru.value = m.danbooru_tag
  tagGallery.value = m.gallery_tag
  tagHelp.value = ''
  tagHelpClass.value = ''
  showTagModal.value = true
}

async function submitTagMapping() {
  if (!tagDanbooru.value.trim()) {
    tagHelp.value = 'Danbooru tag name is required.'
    tagHelpClass.value = 'is-danger'
    return
  }
  if (!tagGallery.value.trim()) {
    tagHelp.value = 'Gallery tag name is required.'
    tagHelpClass.value = 'is-danger'
    return
  }

  tagLoading.value = true
  try {
    if (tagFormMode.value === 'edit') {
      await api.put<TagMapping>(endpoints.danbooru.tagMapping(tagFormId.value), {
        danbooru_tag: tagDanbooru.value.trim(),
        gallery_tag: tagGallery.value.trim(),
      })
    } else {
      await api.post<TagMapping>(endpoints.danbooru.tagMappings, {
        danbooru_tag: tagDanbooru.value.trim(),
        gallery_tag: tagGallery.value.trim(),
      })
    }
    await loadTagMappings()
    showTagModal.value = false
    toastStore.success(tagFormMode.value === 'edit' ? 'Tag mapping updated.' : 'Tag mapping added.')
  } catch (e) {
    tagHelp.value = getErrorMessage(e, 'Error saving tag mapping.')
    tagHelpClass.value = 'is-danger'
  } finally {
    tagLoading.value = false
  }
}

async function deleteTagMapping(id: number) {
  if (!confirm('Remove this tag name mapping?')) return
  try {
    await api.del(endpoints.danbooru.tagMapping(id))
    await loadTagMappings()
    toastStore.success('Tag mapping removed.')
  } catch (e) {
    toastStore.error(getErrorMessage(e, 'Could not remove mapping.'))
  }
}

onMounted(loadRules)
</script>

<template>
  <section class="section">
    <div class="container">
      <LoadingSpinner v-if="loading" />

      <div v-else-if="loadFailed" class="has-text-centered py-6">
        <span class="icon is-large has-text-grey-light">
          <i class="fa-solid fa-file-import fa-3x" />
        </span>
        <p class="is-size-5 has-text-grey mt-4">Could not load Danbooru import rules.</p>
        <button class="button is-indigo mt-4" @click="loadRules">
          <span class="icon"><i class="fa-solid fa-rotate-right" /></span>
          <span>Retry</span>
        </button>
      </div>

      <template v-else>
        <!-- Header -->
        <div class="level mb-4">
          <div class="level-left">
            <div class="level-item">
              <h1 class="title is-4 mb-0">Danbooru Import Rules</h1>
            </div>
          </div>
        </div>

        <p class="mb-5 has-text-grey">
          Configure how tags are imported from Danbooru. Category mappings control which Danbooru
          category maps to which gallery category. Tag name mappings rename specific Danbooru tags
          to more readable gallery names (unmapped tags have underscores replaced with spaces
          automatically).
        </p>

        <!-- ═══════ Category Mappings ═══════ -->
        <div class="level mb-3">
          <div class="level-left">
            <h2 class="title is-5 mb-0">
              <span class="icon"><i class="fa-solid fa-layer-group" /></span>
              <span>Category Mappings</span>
            </h2>
          </div>
          <div v-if="authenticated" class="level-right">
            <button class="button is-primary is-small" @click="openCatModal">
              <span class="icon"><i class="fa-solid fa-plus" /></span>
              <span>Add Mapping</span>
            </button>
          </div>
        </div>

        <table class="table is-striped is-hoverable is-fullwidth mb-6">
          <thead>
            <tr>
              <th>Danbooru ID</th>
              <th>Danbooru Category</th>
              <th />
              <th>Gallery Category</th>
              <th v-if="authenticated" style="width: 80px">Actions</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="m in categoryMappings" :key="m.danbooru_category_id">
              <td>
                <code>{{ m.danbooru_category_id }}</code>
              </td>
              <td>{{ m.danbooru_category_name }}</td>
              <td class="has-text-centered has-text-grey">
                <i class="fa-solid fa-arrow-right" />
              </td>
              <td>
                <span
                  v-if="m.gallery_category_name"
                  class="tag is-medium"
                  :class="
                    colorToTagClass(
                      store.categories.find((c) => c.category_id === m.gallery_category_id)
                        ?.color || 'white',
                    )
                  "
                >
                  {{ m.gallery_category_name }}
                </span>
                <span v-else class="has-text-danger">Missing (ID {{ m.gallery_category_id }})</span>
              </td>
              <td v-if="authenticated">
                <button
                  class="button is-small is-danger is-outlined"
                  title="Remove"
                  @click="deleteCatMapping(m.danbooru_category_id)"
                >
                  <span class="icon"><i class="fa-solid fa-trash" /></span>
                </button>
              </td>
            </tr>
            <tr v-if="categoryMappings.length === 0">
              <td :colspan="authenticated ? 5 : 4" class="has-text-centered has-text-grey">
                No category mappings configured. Danbooru imports will skip all tags.
              </td>
            </tr>
          </tbody>
        </table>

        <hr />

        <!-- ═══════ Tag Name Mappings ═══════ -->
        <div class="level mb-3">
          <div class="level-left">
            <h2 class="title is-5 mb-0">
              <span class="icon"><i class="fa-solid fa-right-left" /></span>
              <span>Tag Name Mappings</span>
            </h2>
          </div>
          <div v-if="authenticated" class="level-right">
            <button class="button is-primary is-small" @click="openNewTagMapping">
              <span class="icon"><i class="fa-solid fa-plus" /></span>
              <span>Add Mapping</span>
            </button>
          </div>
        </div>

        <table class="table is-striped is-hoverable is-fullwidth">
          <thead>
            <tr>
              <th>Danbooru Tag</th>
              <th />
              <th>Gallery Tag</th>
              <th v-if="authenticated" style="width: 120px">Actions</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="m in tagMappings" :key="m.id">
              <td>
                <code>{{ m.danbooru_tag }}</code>
              </td>
              <td class="has-text-centered has-text-grey">
                <i class="fa-solid fa-arrow-right" />
              </td>
              <td>{{ m.gallery_tag }}</td>
              <td v-if="authenticated">
                <div class="buttons are-small">
                  <button
                    class="button is-cyan is-outlined"
                    title="Edit"
                    @click="openEditTagMapping(m)"
                  >
                    <span class="icon"><i class="fa-solid fa-pen" /></span>
                  </button>
                  <button
                    class="button is-danger is-outlined"
                    title="Remove"
                    @click="deleteTagMapping(m.id)"
                  >
                    <span class="icon"><i class="fa-solid fa-trash" /></span>
                  </button>
                </div>
              </td>
            </tr>
            <tr v-if="tagMappings.length === 0">
              <td :colspan="authenticated ? 4 : 3" class="has-text-centered has-text-grey">
                No tag name mappings configured. Danbooru tags will be imported with underscores
                replaced by spaces.
              </td>
            </tr>
          </tbody>
        </table>
      </template>

      <!-- Add Category Mapping Modal -->
      <div class="modal" :class="{ 'is-active': showCatModal }">
        <div class="modal-background" @click="showCatModal = false" />
        <div class="modal-card">
          <header class="modal-card-head">
            <p class="modal-card-title">
              <strong>Add Category Mapping</strong>
            </p>
            <button class="delete" aria-label="close" @click="showCatModal = false" />
          </header>
          <section class="modal-card-body">
            <div class="field">
              <label class="label">Danbooru Category ID</label>
              <div class="control">
                <input
                  v-model="catDanbooruId"
                  class="input"
                  type="number"
                  min="0"
                  placeholder="e.g. 0"
                />
              </div>
              <p class="help">
                The numeric category ID used by the Danbooru API (0=General, 1=Artist, 3=Copyright,
                4=Character, 5=Meta).
              </p>
            </div>
            <div class="field">
              <label class="label">Danbooru Category Name</label>
              <div class="control">
                <input
                  v-model="catDanbooruName"
                  class="input"
                  type="text"
                  placeholder="e.g. General"
                />
              </div>
              <p class="help">A label for this mapping (for your reference only).</p>
            </div>
            <div class="field">
              <label class="label">Gallery Category</label>
              <div class="control">
                <div class="select is-fullwidth">
                  <select v-model="catGalleryCategoryId">
                    <option value="">Select a category</option>
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
            <p v-if="catHelp" class="help" :class="catHelpClass">
              {{ catHelp }}
            </p>
          </section>
          <footer class="modal-card-foot">
            <div class="buttons">
              <button
                class="button is-primary"
                :class="{ 'is-loading': catLoading }"
                @click="submitCatMapping"
              >
                Save Mapping
              </button>
              <button class="button" @click="showCatModal = false">Cancel</button>
            </div>
          </footer>
        </div>
      </div>

      <!-- Add/Edit Tag Name Mapping Modal -->
      <div class="modal" :class="{ 'is-active': showTagModal }">
        <div class="modal-background" @click="showTagModal = false" />
        <div class="modal-card">
          <header class="modal-card-head">
            <p class="modal-card-title">
              <strong>{{ tagFormMode === 'edit' ? 'Edit Tag Mapping' : 'Add Tag Mapping' }}</strong>
            </p>
            <button class="delete" aria-label="close" @click="showTagModal = false" />
          </header>
          <section class="modal-card-body">
            <div class="field">
              <label class="label">Danbooru Tag</label>
              <div class="control">
                <input
                  v-model="tagDanbooru"
                  class="input"
                  type="text"
                  placeholder="e.g. 1girl"
                  @keyup.enter="submitTagMapping"
                />
              </div>
              <p class="help">The exact tag name as it appears on Danbooru.</p>
            </div>
            <div class="field">
              <label class="label">Gallery Tag</label>
              <div class="control">
                <input
                  v-model="tagGallery"
                  class="input"
                  type="text"
                  placeholder="e.g. one woman"
                  @keyup.enter="submitTagMapping"
                />
              </div>
              <p class="help">The name this tag should appear as in your gallery.</p>
            </div>
            <p v-if="tagHelp" class="help" :class="tagHelpClass">
              {{ tagHelp }}
            </p>
          </section>
          <footer class="modal-card-foot">
            <div class="buttons">
              <button
                class="button is-primary"
                :class="{ 'is-loading': tagLoading }"
                @click="submitTagMapping"
              >
                {{ tagFormMode === 'edit' ? 'Save Changes' : 'Add Mapping' }}
              </button>
              <button class="button" @click="showTagModal = false">Cancel</button>
            </div>
          </footer>
        </div>
      </div>
    </div>
  </section>
</template>
