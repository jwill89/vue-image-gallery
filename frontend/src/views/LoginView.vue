<script setup lang="ts">
import { ref } from 'vue'
import { useRouter } from 'vue-router'
import { useApi, setAuthToken, hasAuthToken } from '../composables/useApi'

const router = useRouter()
const api = useApi()
const passwordInput = ref('')
const authError = ref<string | null>(null)
const authLoading = ref(false)

if (hasAuthToken()) {
  router.replace('/')
}

async function login() {
  authLoading.value = true
  authError.value = null
  try {
    const result = await api.post<{ token: string }>('/auth/login/', { password: passwordInput.value })
    setAuthToken(result.token)
    passwordInput.value = ''
    router.replace('/')
  } catch {
    authError.value = 'Invalid password'
  } finally {
    authLoading.value = false
  }
}
</script>

<template>
  <section class="section">
    <div class="container">
      <div class="columns is-centered">
        <div class="column is-4">
          <div class="box">
            <h2 class="title is-4 has-text-centered">Admin Login</h2>
            <div class="field">
              <label class="label">Password</label>
              <div class="control has-icons-left">
                <input
                  class="input"
                  type="password"
                  v-model="passwordInput"
                  placeholder="Enter admin password"
                  @keyup.enter="login"
                  autofocus
                />
                <span class="icon is-left">
                  <i class="fa-solid fa-lock"></i>
                </span>
              </div>
              <p v-if="authError" class="help is-danger">{{ authError }}</p>
            </div>
            <button class="button is-primary is-fullwidth" :class="{ 'is-loading': authLoading }" @click="login">
              Login
            </button>
          </div>
        </div>
      </div>
    </div>
  </section>
</template>
