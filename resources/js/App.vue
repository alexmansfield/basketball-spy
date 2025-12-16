<template>
  <div id="app">
    <template v-if="isAuthenticated">
      <PlayerManagement
        v-if="currentView === 'players'"
        :token="token"
        @back="currentView = 'dashboard'"
      />
      <Dashboard
        v-else
        :user="user"
        :token="token"
        @logout="handleLogout"
        @navigate="handleNavigate"
      />
    </template>
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
import PlayerManagement from './components/PlayerManagement.vue'

const isAuthenticated = ref(false)
const user = ref(null)
const token = ref('')
const currentView = ref('dashboard')

function handleLoginSuccess(data) {
  user.value = data.user
  token.value = data.token
  isAuthenticated.value = true
}

function handleLogout() {
  user.value = null
  token.value = ''
  isAuthenticated.value = false
  currentView.value = 'dashboard'
}

function handleNavigate(view) {
  currentView.value = view
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
