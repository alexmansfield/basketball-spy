<template>
  <div class="min-h-screen flex items-center justify-center bg-gradient-to-br from-orange-500 to-orange-700 p-4">
    <div class="bg-white rounded-2xl shadow-2xl w-full max-w-md p-8">
      <!-- Logo/Header -->
      <div class="text-center mb-8">
        <div class="inline-flex items-center justify-center w-16 h-16 bg-orange-100 rounded-full mb-4">
          <svg class="w-8 h-8 text-orange-600" fill="currentColor" viewBox="0 0 24 24">
            <circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="2" fill="none"/>
            <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm0 18c-4.41 0-8-3.59-8-8s3.59-8 8-8 8 3.59 8 8-3.59 8-8 8z"/>
            <path d="M12 2v20M2 12h20" stroke="currentColor" stroke-width="1.5"/>
            <path d="M4.93 4.93c4.02 4.02 10.12 4.02 14.14 0M4.93 19.07c4.02-4.02 10.12-4.02 14.14 0" stroke="currentColor" stroke-width="1.5" fill="none"/>
          </svg>
        </div>
        <h1 class="text-2xl font-bold text-gray-900">Basketball Spy</h1>
        <p class="text-gray-500 mt-1">Scout Login</p>
      </div>

      <!-- Error Message -->
      <div v-if="error" class="mb-4 p-3 bg-red-50 border border-red-200 rounded-lg text-red-700 text-sm">
        {{ error }}
      </div>

      <!-- Login Form -->
      <form @submit.prevent="handleLogin" class="space-y-5">
        <div>
          <label for="email" class="block text-sm font-medium text-gray-700 mb-1">Email</label>
          <input
            id="email"
            v-model="email"
            type="email"
            required
            class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-orange-500 focus:border-orange-500 transition-colors"
            placeholder="you@example.com"
          />
        </div>

        <div>
          <label for="password" class="block text-sm font-medium text-gray-700 mb-1">Password</label>
          <input
            id="password"
            v-model="password"
            type="password"
            required
            class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-orange-500 focus:border-orange-500 transition-colors"
            placeholder="Enter your password"
          />
        </div>

        <button
          type="submit"
          :disabled="loading"
          class="w-full bg-orange-600 hover:bg-orange-700 text-white font-semibold py-3 px-4 rounded-lg transition-colors disabled:opacity-50 disabled:cursor-not-allowed flex items-center justify-center"
        >
          <svg v-if="loading" class="animate-spin -ml-1 mr-2 h-5 w-5 text-white" fill="none" viewBox="0 0 24 24">
            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
          </svg>
          {{ loading ? 'Signing in...' : 'Sign In' }}
        </button>
      </form>

      <!-- Test Accounts -->
      <div class="mt-8 pt-6 border-t border-gray-200">
        <p class="text-xs text-gray-500 text-center mb-3">Test Accounts (click to fill)</p>
        <div class="space-y-2">
          <button
            @click="fillTestAccount('superadmin@basketballspy.com')"
            class="w-full text-left px-3 py-2 text-sm bg-gray-50 hover:bg-gray-100 rounded-lg transition-colors"
          >
            <span class="font-medium text-gray-700">Super Admin</span>
            <span class="text-gray-500 ml-2">superadmin@basketballspy.com</span>
          </button>
          <button
            @click="fillTestAccount('admin@demo-agency.com')"
            class="w-full text-left px-3 py-2 text-sm bg-gray-50 hover:bg-gray-100 rounded-lg transition-colors"
          >
            <span class="font-medium text-gray-700">Org Admin</span>
            <span class="text-gray-500 ml-2">admin@demo-agency.com</span>
          </button>
          <button
            @click="fillTestAccount('scout1@demo-agency.com')"
            class="w-full text-left px-3 py-2 text-sm bg-gray-50 hover:bg-gray-100 rounded-lg transition-colors"
          >
            <span class="font-medium text-gray-700">Scout</span>
            <span class="text-gray-500 ml-2">scout1@demo-agency.com</span>
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref } from 'vue'
import axios from 'axios'

const emit = defineEmits(['login-success'])

const email = ref('')
const password = ref('password')
const error = ref('')
const loading = ref(false)

function fillTestAccount(testEmail) {
  email.value = testEmail
  password.value = 'password'
  error.value = ''
}

async function handleLogin() {
  error.value = ''
  loading.value = true

  try {
    const response = await axios.post('/api/login', {
      email: email.value,
      password: password.value,
    })

    // Store token
    localStorage.setItem('auth_token', response.data.token)
    localStorage.setItem('user', JSON.stringify(response.data.user))

    // Emit success event
    emit('login-success', response.data)
  } catch (err) {
    if (err.response?.status === 401) {
      error.value = 'Invalid email or password'
    } else if (err.response?.data?.message) {
      error.value = err.response.data.message
    } else {
      error.value = 'Login failed. Please try again.'
    }
  } finally {
    loading.value = false
  }
}
</script>
