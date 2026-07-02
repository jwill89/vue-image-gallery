<script setup lang="ts">
import { ref } from 'vue'
import { useApi, setAuthToken } from '../composables/useApi'
import { endpoints } from '../api/endpoints'
import type { LoginResponse } from '../types'

const emit = defineEmits<{
  (e: 'authenticated'): void
}>()

const api = useApi()
const passwordInput = ref('')
const authError = ref<string | null>(null)
const authLoading = ref(false)

async function login() {
  authLoading.value = true
  authError.value = null
  try {
    const result = await api.post<LoginResponse>(endpoints.auth.login, {
      password: passwordInput.value,
    })
    setAuthToken(result.token)
    passwordInput.value = ''
    emit('authenticated')
  } catch {
    authError.value = 'Invalid password'
  } finally {
    authLoading.value = false
  }
}
</script>

<template>
  <div class="box">
    <h2 class="title is-5">Authentication Required</h2>
    <p class="mb-4">Please enter the admin password to manage tags.</p>
    <div class="field">
      <div class="control">
        <input
          v-model="passwordInput"
          class="input"
          type="password"
          placeholder="Password"
          @keyup.enter="login"
        />
      </div>
      <p v-if="authError" class="help is-danger">
        {{ authError }}
      </p>
    </div>
    <button class="button is-primary" :class="{ 'is-loading': authLoading }" @click="login">
      Login
    </button>
  </div>
</template>
