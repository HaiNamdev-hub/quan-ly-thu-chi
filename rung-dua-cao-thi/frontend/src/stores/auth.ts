import { defineStore } from 'pinia'
import { ref } from 'vue'
import { api, prepareCsrfCookie } from '../services/api'
import type { AuthUser } from '../types/auth'

interface LoginPayload {
  login: string
  password: string
  remember: boolean
}

export const useAuthStore = defineStore('auth', () => {
  const user = ref<AuthUser | null>(null)
  const initialized = ref(false)

  async function fetchUser(): Promise<AuthUser | null> {
    try {
      const response = await api.get<{ user: AuthUser }>('/api/me')
      user.value = response.data.user
    } catch {
      user.value = null
    } finally {
      initialized.value = true
    }
    return user.value
  }

  async function login(payload: LoginPayload): Promise<AuthUser> {
    await prepareCsrfCookie()
    const response = await api.post<{ user: AuthUser }>('/api/login', payload)
    user.value = response.data.user
    initialized.value = true
    return user.value
  }

  async function logout(): Promise<void> {
    try {
      await api.post('/api/logout')
    } catch {
      // Clear the in-memory user even if the server session has already expired.
    } finally {
      user.value = null
    }
  }

  return { user, initialized, fetchUser, login, logout }
})
