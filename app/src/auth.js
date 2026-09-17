import { reactive } from 'vue'
import api, { TOKEN_KEY } from './api'

export const auth = reactive({
  user: null,
  token: localStorage.getItem(TOKEN_KEY),

  get isLogged() {
    return Boolean(this.token)
  },

  setSession({ token, user }) {
    this.token = token
    this.user = user
    localStorage.setItem(TOKEN_KEY, token)
  },

  async login(email, password) {
    const { data } = await api.post('/login', { email, password })
    this.setSession(data)
    return data.user
  },

  async register(name, email, password) {
    const { data } = await api.post('/register', { name, email, password })
    this.setSession(data)
    return data.user
  },

  async logout() {
    try {
      await api.post('/logout')
    } catch {
      // O token local cai de qualquer jeito; falha no servidor nao deve prender o usuario na tela.
    } finally {
      this.token = null
      this.user = null
      localStorage.removeItem(TOKEN_KEY)
    }
  },

  async loadMe() {
    if (!this.token) return null
    const { data } = await api.get('/me')
    this.user = data
    return data
  },
})
