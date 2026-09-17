<script setup>
import { ref, computed } from 'vue'
import { useRouter, useRoute } from 'vue-router'
import { auth } from '../auth'
import { errorMessage } from '../api'

const router = useRouter()
const route = useRoute()

const mode = ref('login')
const name = ref('')
const email = ref('')
const password = ref('')
const loading = ref(false)
const error = ref('')

const isRegister = computed(() => mode.value === 'register')

function toggleMode() {
  mode.value = isRegister.value ? 'login' : 'register'
  error.value = ''
}

async function submit() {
  error.value = ''
  loading.value = true
  try {
    if (isRegister.value) {
      await auth.register(name.value, email.value, password.value)
    } else {
      await auth.login(email.value, password.value)
    }
    router.replace(route.query.next || '/')
  } catch (e) {
    error.value = errorMessage(e, 'Não foi possível entrar.')
  } finally {
    loading.value = false
  }
}
</script>

<template>
  <main class="page page-center">
    <h1 class="brand">MultiPlay</h1>

    <form class="card form" @submit.prevent="submit">
      <h2>{{ isRegister ? 'Criar conta' : 'Entrar' }}</h2>

      <label v-if="isRegister">
        <span>Nome</span>
        <input v-model="name" type="text" autocomplete="name" required />
      </label>

      <label>
        <span>E-mail</span>
        <input v-model="email" type="email" autocomplete="email" inputmode="email" required />
      </label>

      <label>
        <span>Senha</span>
        <input
          v-model="password"
          type="password"
          :autocomplete="isRegister ? 'new-password' : 'current-password'"
          minlength="6"
          required
        />
      </label>

      <p v-if="error" class="alert alert-error" role="alert">{{ error }}</p>

      <button class="btn btn-primary" type="submit" :disabled="loading">
        {{ loading ? 'Aguarde...' : isRegister ? 'Criar conta' : 'Entrar' }}
      </button>

      <button class="btn btn-link" type="button" @click="toggleMode">
        {{ isRegister ? 'Já tenho conta' : 'Criar conta' }}
      </button>
    </form>

    <p class="hint">Demo: demo@multiplay.local / multiplay</p>
  </main>
</template>
