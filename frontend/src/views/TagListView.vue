<script setup lang="ts">
import { ref, onMounted, computed, watch } from 'vue'
import { useRouter } from 'vue-router'
import { useApi } from '../composables/useApi'
import { useGalleryStore, type Tag } from '../stores/gallery'
import LoadingSpinner from '../components/LoadingSpinner.vue'

const CATEGORY_TEXT_CLASS_MAP: Record<string, string> = {
  'General': 'has-text-white',
  'Artist': 'has-text-danger',
  'Character': 'has-text-success',
  'Source': 'has-text-warning',
  'Personal List': 'has-text-info'
}

const CATEGORY_NAME_CLASS_MAP: Record<string, string> = {
  'General': 'is-white',
  'Artist': 'is-danger',
  'Character': 'is-success',
  'Source': 'is-warning',
  'Personal List': 'is-info'
}

interface DisplayTag extends Tag {
  category_name: string
  image_count: number
  video_count: number
}

const api = useApi()
const store = useGalleryStore()
const router = useRouter()

const allDisplayTags = ref<DisplayTag[]>([])
const loading = ref(false)
const searchQuery = ref('')
const sortKey = ref<'tag_name' | 'category_name' | 'image_count' | 'video_count'>('tag_name')
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

const filteredTags = computed(() => {
  let filtered = allDisplayTags.value
  if (searchQuery.value.trim()) {
    const q = searchQuery.value.toLowerCase()
    filtered = filtered.filter(t =>
      t.tag_name.toLowerCase().includes(q) || t.category_name.toLowerCase().includes(q)
    )
  }
  const key = sortKey.value
  filtered = [...filtered].sort((a, b) => {
    const va = a[key]
    const vb = b[key]
    if (typeof va === 'string' && typeof vb === 'string') {
      return sortAsc.value ? va.localeCompare(vb) : vb.localeCompare(va)
    }
    return sortAsc.value ? (Number(va) - Number(vb)) : (Number(vb) - Number(va))
  })
  return filtered
})

const totalFilteredPages = computed(() => Math.max(1, Math.ceil(filteredTags.value.length / pageSize.value)))
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

async function loadTags() {
  loading.value = true
  try {
    allDisplayTags.value = await api.get<DisplayTag[]>('/tags/display/')
  } catch {
    allDisplayTags.value = []
  } finally {
    loading.value = false
  }
}

function openNewTagModal() {
  resetForm()
  showFormModal.value = true
}

function editTag(tag: DisplayTag) {
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
      await api.put(`/tags/edit/${formEditId.value}/`, {
        tag_name: formTagName.value,
        category_id: Number(formCategoryId.value)
      })
    } else {
      // Check if tag already exists
      const exists = allDisplayTags.value.some(
        t => t.tag_name.toLowerCase() === formTagName.value.trim().toLowerCase()
      )
      if (exists) {
        formHelp.value = 'Tag already exists.'
        formHelpClass.value = 'is-danger'
        return
      }
      await api.post('/tags/add/', {
        tag_name: formTagName.value,
        category_id: Number(formCategoryId.value)
      })
    }
    closeModal()
    await loadTags()
    store.refreshTags()
  } catch {
    formHelp.value = 'Error saving tag.'
    formHelpClass.value = 'is-danger'
  }
}

function searchByTag(tagName: string, mediaType: 'images' | 'videos') {
  router.push({
    name: `${mediaType}-with-tags`,
    params: { page: 1, perPage: 40, tags: tagName }
  })
}

function onTagNameInput() {
  if (formMode.value === 'edit') return
  if (!formTagName.value.trim()) {
    formHelp.value = ''
    formHelpClass.value = ''
    return
  }
  const exists = allDisplayTags.value.some(
    t => t.tag_name.toLowerCase() === formTagName.value.trim().toLowerCase()
  )
  if (exists) {
    formHelp.value = 'Tag already exists.'
    formHelpClass.value = 'is-danger'
  } else {
    formHelp.value = 'Tag is available.'
    formHelpClass.value = 'is-success'
  }
}

onMounted(() => {
  loadTags()
})
</script>

<template>
  <section class="section">
    <div class="container">
      <LoadingSpinner v-if="loading" />
      <template v-else>
        <!-- Header with New Tag button -->
        <div class="level mb-4">
          <div class="level-left">
            <div class="level-item">
              <h1 class="title is-4">Tags</h1>
            </div>
          </div>
          <div class="level-right">
            <div class="level-item">
              <button class="button is-primary" @click="openNewTagModal">
                <span class="icon"><i class="fa-solid fa-plus"></i></span>
                <span>New Tag</span>
              </button>
            </div>
          </div>
        </div>

        <!-- Search and page size -->
        <div class="field is-grouped mb-4">
          <div class="control is-expanded">
            <input class="input" type="text" v-model="searchQuery" placeholder="Search tags..." />
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
              <th @click="toggleSort('tag_name')" style="cursor:pointer">
                Tag <i class="fas" :class="sortIcon('tag_name')"></i>
              </th>
              <th @click="toggleSort('category_name')" style="cursor:pointer">
                Category <i class="fas" :class="sortIcon('category_name')"></i>
              </th>
              <th @click="toggleSort('image_count')" style="cursor:pointer">
                Images <i class="fas" :class="sortIcon('image_count')"></i>
              </th>
              <th @click="toggleSort('video_count')" style="cursor:pointer">
                Videos <i class="fas" :class="sortIcon('video_count')"></i>
              </th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="tag in pagedTags" :key="tag.tag_id" @dblclick="editTag(tag)" style="cursor:pointer"
              title="Double-click to edit">
              <td>
                <span :class="CATEGORY_TEXT_CLASS_MAP[tag.category_name] || 'has-text-white'">
                  {{ tag.tag_name }}
                </span>
              </td>
              <td>
                <span class="tag is-medium" :class="CATEGORY_NAME_CLASS_MAP[tag.category_name] || 'is-white'">
                  {{ tag.category_name }}
                </span>
              </td>
              <td>
                <a v-if="tag.image_count > 0" class="has-text-link" @click="searchByTag(tag.tag_name, 'images')">
                  {{ tag.image_count }}
                </a>
                <span v-else>0</span>
              </td>
              <td>
                <a v-if="tag.video_count > 0" class="has-text-link" @click="searchByTag(tag.tag_name, 'videos')">
                  {{ tag.video_count }}
                </a>
                <span v-else>0</span>
              </td>
            </tr>
            <tr v-if="pagedTags.length === 0">
              <td colspan="4" class="has-text-centered">No tags found.</td>
            </tr>
          </tbody>
        </table>

        <!-- Pagination -->
        <nav class="pagination is-centered is-small" v-if="totalFilteredPages > 1">
          <a class="pagination-previous" :class="{ 'is-disabled': currentPage <= 1 }"
            @click.prevent="currentPage > 1 && currentPage--">Previous</a>
          <a class="pagination-next" :class="{ 'is-disabled': currentPage >= totalFilteredPages }"
            @click.prevent="currentPage < totalFilteredPages && currentPage++">Next</a>
          <ul class="pagination-list">
            <li><span class="pagination-link is-current">Page {{ currentPage }} of {{ totalFilteredPages }}</span></li>
          </ul>
        </nav>
      </template>

      <!-- New/Edit Tag Modal -->
      <div class="modal" :class="{ 'is-active': showFormModal }">
        <div class="modal-background" @click="closeModal"></div>
        <div class="modal-card">
          <header class="modal-card-head">
            <p class="modal-card-title">
              <strong>{{ formMode === 'edit' ? 'Edit Tag' : 'New Tag' }}</strong>
            </p>
            <button class="delete" aria-label="close" @click="closeModal"></button>
          </header>
          <section class="modal-card-body">
            <div class="field">
              <label class="label">Tag Name</label>
              <div class="control has-icons-left">
                <input class="input" :class="formHelpClass" type="text" placeholder="Enter tag name"
                  v-model="formTagName" @input="onTagNameInput" @keyup.enter="submitForm" />
                <span class="icon is-left">
                  <i class="fa-solid fa-tag"></i>
                </span>
              </div>
              <p v-if="formHelp" class="help" :class="formHelpClass">{{ formHelp }}</p>
            </div>
            <div class="field">
              <label class="label">Category</label>
              <div class="control">
                <div class="select is-fullwidth">
                  <select v-model="formCategoryId">
                    <option value="">Select a Category</option>
                    <option :value="1">General</option>
                    <option :value="2">Artist</option>
                    <option :value="3">Character</option>
                    <option :value="4">Source</option>
                    <option :value="5">Personal</option>
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
    </div>
  </section>
</template>
