import axios from 'axios'

export const TOKEN_KEY = 'multiplay_token'

const api = axios.create({
  baseURL: import.meta.env.VITE_API_URL || 'http://localhost:8000/api',
  headers: { Accept: 'application/json' },
})

api.interceptors.request.use((config) => {
  const token = localStorage.getItem(TOKEN_KEY)
  if (token) config.headers.Authorization = `Bearer ${token}`
  return config
})

api.interceptors.response.use(
  (response) => response,
  (error) => {
    const status = error.response?.status
    const isLoginCall = error.config?.url?.endsWith('/login')
    if (status === 401 && !isLoginCall) {
      localStorage.removeItem(TOKEN_KEY)
      if (window.location.pathname !== '/login') {
        window.location.assign('/login')
      }
    }
    return Promise.reject(error)
  },
)

export function errorMessage(error, fallback = 'Algo deu errado. Tente de novo.') {
  const data = error?.response?.data
  if (data?.errors) {
    return Object.values(data.errors).flat().join(' ')
  }
  if (data?.message) return data.message
  if (!error?.response) return 'Sem conexão com o servidor.'
  return fallback
}

export default api
