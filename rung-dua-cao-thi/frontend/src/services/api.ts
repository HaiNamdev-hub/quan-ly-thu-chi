import axios from 'axios'

export const api = axios.create({
  baseURL: import.meta.env.VITE_API_BASE_URL ?? 'http://localhost:8000',
  withCredentials: true,
  withXSRFToken: true,
  headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
})

export async function prepareCsrfCookie(): Promise<void> {
  await api.get('/sanctum/csrf-cookie')
}
