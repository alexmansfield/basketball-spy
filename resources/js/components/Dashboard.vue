<template>
  <div class="min-h-screen bg-gray-100">
    <!-- Header -->
    <header class="bg-white shadow">
      <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-4 flex justify-between items-center">
        <div class="flex items-center space-x-3">
          <div class="w-10 h-10 bg-orange-100 rounded-full flex items-center justify-center">
            <svg class="w-5 h-5 text-orange-600" fill="currentColor" viewBox="0 0 24 24">
              <circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="2" fill="none"/>
            </svg>
          </div>
          <h1 class="text-xl font-bold text-gray-900">Basketball Spy</h1>
        </div>
        <div class="flex items-center space-x-4">
          <div class="text-right">
            <p class="text-sm font-medium text-gray-900">{{ user?.name }}</p>
            <p class="text-xs text-gray-500">{{ formatRole(user?.role) }}</p>
          </div>
          <button
            @click="handleLogout"
            class="px-4 py-2 text-sm font-medium text-gray-700 bg-gray-100 hover:bg-gray-200 rounded-lg transition-colors"
          >
            Logout
          </button>
        </div>
      </div>
    </header>

    <!-- Main Content -->
    <main class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
      <!-- Welcome Card -->
      <div class="bg-white rounded-xl shadow-sm p-6 mb-8">
        <h2 class="text-2xl font-bold text-gray-900 mb-2">Welcome, {{ user?.name }}!</h2>
        <p class="text-gray-600">You're logged in as <span class="font-medium">{{ formatRole(user?.role) }}</span></p>
        <div v-if="user?.organization" class="mt-2 text-sm text-gray-500">
          Organization: <span class="font-medium">{{ user.organization.name }}</span>
        </div>
      </div>

      <!-- Stats Grid -->
      <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
        <div class="bg-white rounded-xl shadow-sm p-6">
          <div class="flex items-center">
            <div class="w-12 h-12 bg-blue-100 rounded-lg flex items-center justify-center">
              <svg class="w-6 h-6 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/>
              </svg>
            </div>
            <div class="ml-4">
              <p class="text-sm text-gray-500">Teams</p>
              <p class="text-2xl font-bold text-gray-900">{{ stats.teams }}</p>
            </div>
          </div>
        </div>

        <div class="bg-white rounded-xl shadow-sm p-6">
          <div class="flex items-center">
            <div class="w-12 h-12 bg-green-100 rounded-lg flex items-center justify-center">
              <svg class="w-6 h-6 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
              </svg>
            </div>
            <div class="ml-4">
              <p class="text-sm text-gray-500">Players</p>
              <p class="text-2xl font-bold text-gray-900">{{ stats.players }}</p>
            </div>
          </div>
        </div>

        <div class="bg-white rounded-xl shadow-sm p-6">
          <div class="flex items-center">
            <div class="w-12 h-12 bg-purple-100 rounded-lg flex items-center justify-center">
              <svg class="w-6 h-6 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
              </svg>
            </div>
            <div class="ml-4">
              <p class="text-sm text-gray-500">Your Reports</p>
              <p class="text-2xl font-bold text-gray-900">{{ stats.reports }}</p>
            </div>
          </div>
        </div>
      </div>

      <!-- API Token Section -->
      <div class="bg-white rounded-xl shadow-sm p-6">
        <h3 class="text-lg font-semibold text-gray-900 mb-4">API Token</h3>
        <p class="text-sm text-gray-600 mb-3">Use this token to authenticate API requests from the mobile app:</p>
        <div class="flex items-center space-x-2">
          <code class="flex-1 bg-gray-100 px-4 py-3 rounded-lg text-sm font-mono text-gray-800 overflow-x-auto">
            {{ token }}
          </code>
          <button
            @click="copyToken"
            class="px-4 py-3 bg-orange-600 hover:bg-orange-700 text-white rounded-lg transition-colors"
          >
            {{ copied ? 'Copied!' : 'Copy' }}
          </button>
        </div>
      </div>
    </main>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import axios from 'axios'

const props = defineProps({
  user: Object,
  token: String,
})

const emit = defineEmits(['logout'])

const stats = ref({
  teams: 0,
  players: 0,
  reports: 0,
})

const copied = ref(false)

function formatRole(role) {
  const roles = {
    super_admin: 'Super Admin',
    org_admin: 'Organization Admin',
    scout: 'Scout',
  }
  return roles[role] || role
}

async function fetchStats() {
  try {
    axios.defaults.headers.common['Authorization'] = `Bearer ${props.token}`

    const [teamsRes, playersRes, reportsRes] = await Promise.all([
      axios.get('/api/teams').catch(() => ({ data: { data: [] } })),
      axios.get('/api/players').catch(() => ({ data: { data: [] } })),
      axios.get('/api/reports').catch(() => ({ data: { data: [] } })),
    ])

    stats.value = {
      teams: teamsRes.data?.data?.length || teamsRes.data?.meta?.total || 0,
      players: playersRes.data?.data?.length || playersRes.data?.meta?.total || 0,
      reports: reportsRes.data?.data?.length || reportsRes.data?.meta?.total || 0,
    }
  } catch (err) {
    console.error('Failed to fetch stats:', err)
  }
}

function copyToken() {
  navigator.clipboard.writeText(props.token)
  copied.value = true
  setTimeout(() => {
    copied.value = false
  }, 2000)
}

async function handleLogout() {
  try {
    await axios.post('/api/logout')
  } catch (err) {
    // Ignore logout errors
  }

  localStorage.removeItem('auth_token')
  localStorage.removeItem('user')
  emit('logout')
}

onMounted(() => {
  fetchStats()
})
</script>
