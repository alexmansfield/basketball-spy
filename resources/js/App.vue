<template>
  <div id="app">
    <Dashboard
      v-if="isAuthenticated"
      :user="user"
      :token="token"
      @logout="handleLogout"
    />
    <LoginForm
      v-else
      @login-success="handleLoginSuccess"
    />
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import LoginForm from './components/LoginForm.vue'
import Dashboard from './components/Dashboard.vue'

const isAuthenticated = ref(false)
const user = ref(null)
const token = ref('')

function handleLoginSuccess(data) {
  user.value = data.user
  token.value = data.token
  isAuthenticated.value = true
}

function handleLogout() {
  user.value = null
  token.value = ''
  isAuthenticated.value = false
}

onMounted(() => {
  // Check for existing session
  const storedToken = localStorage.getItem('auth_token')
  const storedUser = localStorage.getItem('user')

  if (storedToken && storedUser) {
    token.value = storedToken
    user.value = JSON.parse(storedUser)
    isAuthenticated.value = true
  }
})
</script>
