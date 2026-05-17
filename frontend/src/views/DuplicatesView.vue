<script setup lang="ts">
import { ref, onMounted, computed } from 'vue'
import { useApi, hasAuthToken, setAuthToken } from '../composables/useApi'
import { useGalleryStore } from '../stores/gallery'
import LoadingSpinner from '../components/LoadingSpinner.vue'
import ErrorMessage from '../components/ErrorMessage.vue'

interface DupeImage {
  image_id: number
  file_name: string
  hash: string
}

interface DupeMatch {
  image_1: DupeImage
  image_2: DupeImage
  distance: number | null
}

interface DupeReport {
  report_file: string
  generated_at: string | null
  images_compared: number | null
  duplicates_found: number
  matches: DupeMatch[]
}

const api = useApi()
const store = useGalleryStore()

// Auth state
const authenticated = ref(hasAuthToken())
const passwordInput = ref('')
const authError = ref<string | null>(null)
const authLoading = ref(false)

const report = ref<DupeReport | null>(null)
const loading = ref(false)
const scanning = ref(false)
const deleting = ref(false)
const error = ref<string | null>(null)
const successMessage = ref<string | null>(null)
const selectedImages = ref<Set<number>>(new Set())

const hasSelection = computed(() => selectedImages.value.size > 0)
const selectionCount = computed(() => selectedImages.value.size)

async function login() {
  authLoading.value = true
  authError.value = null
  try {
    const result = await api.post<{ token: string }>('/auth/login/', { password: passwordInput.value })
    setAuthToken(result.token)
    authenticated.value = true
    passwordInput.value = ''
    await loadReport()
  } catch (e: any) {
    authError.value = 'Invalid password'
  } finally {
    authLoading.value = false
  }
}

async function loadReport() {
  loading.value = true
  error.value = null
  successMessage.value = null

  try {
    report.value = await api.get<DupeReport>('/duplicates/report/')
  } catch (e: any) {
    if (e.message?.includes('401')) {
      authenticated.value = false
      return
    }
    if (e.message?.includes('404')) {
      error.value = 'No duplicate reports found. Run a scan to generate one.'
    } else {
      error.value = e.message || 'Failed to load report'
    }
    report.value = null
  } finally {
    loading.value = false
  }
}

async function runScan() {
  scanning.value = true
  error.value = null
  successMessage.value = null

  try {
    await api.post('/duplicates/scan/', {})
    successMessage.value = 'Scan completed! Loading new report...'
    selectedImages.value.clear()
    await loadReport()
  } catch (e: any) {
    error.value = e.message || 'Scan failed'
  } finally {
    scanning.value = false
  }
}

async function deleteSelected() {
  if (!hasSelection.value) return
  if (!confirm(`Are you sure you want to delete ${selectionCount.value} image(s) from the database?`)) return

  deleting.value = true
  error.value = null
  successMessage.value = null

  try {
    const ids = Array.from(selectedImages.value)
    const result = await api.del<{ deleted: number[], failed: number[], total_deleted: number }>('/duplicates/images/', { image_ids: ids })
    successMessage.value = `Deleted ${result.total_deleted} image(s) from the database.`
    selectedImages.value.clear()
    // Reload report to reflect deletions
    await loadReport()
    // Refresh totals in footer
    await store.refreshTotals()
  } catch (e: any) {
    error.value = e.message || 'Failed to delete images'
  } finally {
    deleting.value = false
  }
}

function toggleSelect(imageId: number) {
  const newSet = new Set(selectedImages.value)
  if (newSet.has(imageId)) {
    newSet.delete(imageId)
  } else {
    newSet.add(imageId)
  }
  selectedImages.value = newSet
}

function isSelected(imageId: number): boolean {
  return selectedImages.value.has(imageId)
}

function selectAll() {
  if (!report.value) return
  const newSet = new Set<number>()
  // Select the second image from each pair (likely the duplicate)
  for (const match of report.value.matches) {
    newSet.add(match.image_2.image_id)
  }
  selectedImages.value = newSet
}

function clearSelection() {
  selectedImages.value = new Set()
}

function thumbnailUrl(fileName: string): string {
  return `/images/thumbs/${fileName}`
}

function fullUrl(fileName: string): string {
  return `/images/full/${fileName}`
}

onMounted(() => {
  if (authenticated.value) {
    loadReport()
  }
})
</script>

<template>
  <section class="section">
    <div class="container">
      <!-- Login Gate -->
      <template v-if="!authenticated">
        <div class="columns is-centered">
          <div class="column is-4">
            <div class="box">
              <h2 class="title is-4 has-text-centered">
                <span class="icon"><i class="fa-solid fa-lock"></i></span>
                Authentication Required
              </h2>
              <p class="has-text-centered mb-4">Enter the admin password to access the duplicates page.</p>
              <div class="field">
                <div class="control has-icons-left">
                  <input
                    class="input"
                    :class="{ 'is-danger': authError }"
                    type="password"
                    placeholder="Password"
                    v-model="passwordInput"
                    @keyup.enter="login"
                  />
                  <span class="icon is-left"><i class="fa-solid fa-key"></i></span>
                </div>
                <p v-if="authError" class="help is-danger">{{ authError }}</p>
              </div>
              <button
                class="button is-primary is-fullwidth"
                :class="{ 'is-loading': authLoading }"
                :disabled="authLoading || !passwordInput"
                @click="login"
              >
                Login
              </button>
            </div>
          </div>
        </div>
      </template>

      <!-- Authenticated Content -->
      <template v-else>
        <!-- Header -->
        <div class="level">
          <div class="level-left">
            <div class="level-item">
              <h1 class="title">Duplicate Images</h1>
            </div>
          </div>
          <div class="level-right">
            <div class="level-item">
              <button class="button is-info" :class="{ 'is-loading': scanning }" :disabled="scanning" @click="runScan">
                <span class="icon"><i class="fa-solid fa-magnifying-glass"></i></span>
                <span>Run New Scan</span>
              </button>
            </div>
            <div class="level-item" v-if="hasSelection">
              <button class="button is-danger" :class="{ 'is-loading': deleting }" :disabled="deleting" @click="deleteSelected">
                <span class="icon"><i class="fa-solid fa-trash"></i></span>
                <span>Delete Selected ({{ selectionCount }})</span>
              </button>
            </div>
          </div>
        </div>

        <!-- Messages -->
        <div class="notification is-success is-light" v-if="successMessage">
          <button class="delete" @click="successMessage = null"></button>
          {{ successMessage }}
        </div>
        <ErrorMessage v-if="error" :message="error" />

        <!-- Loading -->
        <LoadingSpinner v-if="loading" message="Loading duplicates report..." />

        <!-- Report Content -->
        <template v-else-if="report">
          <!-- Report metadata -->
          <div class="box mb-5">
            <div class="columns">
              <div class="column has-text-centered">
                <p class="heading">Report File</p>
                <p class="title is-6">{{ report.report_file }}</p>
              </div>
              <div class="column has-text-centered">
                <p class="heading">Generated</p>
                <p class="title is-6">{{ report.generated_at || 'Unknown' }}</p>
              </div>
              <div class="column has-text-centered">
                <p class="heading">Images Compared</p>
                <p class="title is-6">{{ report.images_compared?.toLocaleString() || '—' }}</p>
              </div>
              <div class="column has-text-centered">
                <p class="heading">Duplicates Found</p>
                <p class="title is-6">{{ report.duplicates_found }}</p>
              </div>
            </div>
          </div>

          <!-- Selection Controls -->
          <div class="buttons mb-4" v-if="report.matches.length > 0">
            <button class="button is-small is-warning" @click="selectAll">
              <span class="icon"><i class="fa-solid fa-check-double"></i></span>
              <span>Select All Duplicates (Right Column)</span>
            </button>
            <button class="button is-small" @click="clearSelection" v-if="hasSelection">
              <span class="icon"><i class="fa-solid fa-xmark"></i></span>
              <span>Clear Selection</span>
            </button>
          </div>

          <!-- No duplicates -->
          <div class="notification is-info is-light" v-if="report.matches.length === 0">
            No duplicate pairs found in the latest report.
          </div>

          <!-- Duplicate Pairs -->
          <div v-for="(match, index) in report.matches" :key="index" class="box mb-4">
            <div class="columns is-vcentered">
              <!-- Image 1 -->
              <div class="column is-5">
                <div class="card" :class="{ 'has-background-danger-light': isSelected(match.image_1.image_id) }">
                  <div class="card-content has-text-centered has-background-grey-darker">
                    <figure class="image">
                      <a :href="fullUrl(match.image_1.file_name)" target="_blank">
                        <img :src="thumbnailUrl(match.image_1.file_name)" alt=""
                          :class="['gallery-image', { 'thumb-blur': store.blurThumbnails }]" />
                      </a>
                    </figure>
                  </div>
                  <footer class="card-footer">
                    <div class="card-footer-item">
                      <label class="checkbox">
                        <input type="checkbox" :checked="isSelected(match.image_1.image_id)"
                          @change="toggleSelect(match.image_1.image_id)" />
                        &nbsp;Select
                      </label>
                    </div>
                    <div class="card-footer-item">
                      <span class="is-size-7">ID: {{ match.image_1.image_id }}</span>
                    </div>
                    <a class="card-footer-item" :href="fullUrl(match.image_1.file_name)" target="_blank">
                      <span class="icon has-text-info-dark"><i class="fa-solid fa-up-right-from-square"></i></span>
                    </a>
                  </footer>
                </div>
              </div>

              <!-- Distance indicator -->
              <div class="column is-2 has-text-centered">
                <span class="icon is-large has-text-warning">
                  <i class="fa-solid fa-arrows-left-right fa-2x"></i>
                </span>
                <p class="is-size-7 mt-2" v-if="match.distance !== null">
                  Distance: {{ match.distance }}
                </p>
              </div>

              <!-- Image 2 -->
              <div class="column is-5">
                <div class="card" :class="{ 'has-background-danger-light': isSelected(match.image_2.image_id) }">
                  <div class="card-content has-text-centered has-background-grey-darker">
                    <figure class="image">
                      <a :href="fullUrl(match.image_2.file_name)" target="_blank">
                        <img :src="thumbnailUrl(match.image_2.file_name)" alt=""
                          :class="['gallery-image', { 'thumb-blur': store.blurThumbnails }]" />
                      </a>
                    </figure>
                  </div>
                  <footer class="card-footer">
                    <div class="card-footer-item">
                      <label class="checkbox">
                        <input type="checkbox" :checked="isSelected(match.image_2.image_id)"
                          @change="toggleSelect(match.image_2.image_id)" />
                        &nbsp;Select
                      </label>
                    </div>
                    <div class="card-footer-item">
                      <span class="is-size-7">ID: {{ match.image_2.image_id }}</span>
                    </div>
                    <a class="card-footer-item" :href="fullUrl(match.image_2.file_name)" target="_blank">
                      <span class="icon has-text-info-dark"><i class="fa-solid fa-up-right-from-square"></i></span>
                    </a>
                  </footer>
                </div>
              </div>
            </div>
          </div>
        </template>
      </template>
    </div>
  </section>
</template>


