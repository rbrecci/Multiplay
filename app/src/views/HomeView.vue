<script setup>
import { ref, computed, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import api, { errorMessage } from '../api'
import { auth } from '../auth'
import { statusOf, statusClass } from '../status'
import { initials } from '../initials'
import Icon from '../components/Icon.vue'

const STAT_ORDER = ['zerado', 'jogado', 'interesse', 'quero_jogar'].map(statusOf)

const router = useRouter()
const stats = ref(null)
const loading = ref(true)
const error = ref('')

const isEmpty = computed(() => {
  const s = stats.value
  if (!s) return false
  const counts = Object.values(s.counts || {}).reduce((sum, n) => sum + (n || 0), 0)
  return counts === 0 && !s.favorites_count && !(s.recent || []).length
})

async function load() {
  loading.value = true
  error.value = ''
  try {
    const { data } = await api.get('/me/stats')
    stats.value = data
  } catch (e) {
    error.value = errorMessage(e, 'Não foi possível carregar o resumo.')
  } finally {
    loading.value = false
  }
}

function relativeDate(iso) {
  const date = new Date(iso)
  if (Number.isNaN(date.getTime())) return ''
  const today = new Date()
  const startOf = (d) => new Date(d.getFullYear(), d.getMonth(), d.getDate())
  const days = Math.round((startOf(today) - startOf(date)) / 86400000)
  if (days <= 0) return 'hoje'
  if (days === 1) return 'ontem'
  if (days < 7) return `há ${days} dias`
  return date.toLocaleDateString('pt-BR', { day: '2-digit', month: '2-digit' })
}

function statusLabel(status) {
  return statusOf(status)?.label || 'Sem status'
}

async function logout() {
  await auth.logout()
  router.replace('/login')
}

onMounted(load)
</script>

<template>
  <main class="page">
    <header class="topbar">
      <h1 class="brand">MultiPlay</h1>
      <button class="btn btn-ghost" type="button" @click="logout">Sair</button>
    </header>

    <p v-if="loading" class="muted">Carregando...</p>

    <div v-else-if="error" class="alert alert-error">
      {{ error }}
      <button class="btn btn-ghost" type="button" @click="load">Tentar de novo</button>
    </div>

    <div v-else-if="isEmpty" class="card welcome">
      <h2>Bem-vindo ao MultiPlay</h2>
      <p class="muted">
        Sua coleção ainda está vazia. Escolha um console, importe jogos da IGDB e marque o que
        você já jogou, zerou ou quer jogar.
      </p>
      <RouterLink class="btn btn-primary" to="/consoles">Ver consoles</RouterLink>
    </div>

    <template v-else>
      <ul class="stats" aria-label="Resumo da coleção">
        <li v-for="s in STAT_ORDER" :key="s.value" class="card stat" :class="statusClass(s.value)">
          <span class="stat-value">{{ stats.counts[s.value] || 0 }}</span>
          <span class="stat-label"><Icon :name="s.icon" /> {{ s.short }}</span>
        </li>
      </ul>

      <section class="section">
        <h2>Favoritos</h2>
        <p v-if="!stats.favorites_count" class="muted">
          Marque jogos com o coração pra eles aparecerem aqui.
        </p>
        <ul v-else class="grid grid-sm">
          <li v-for="g in stats.favorites" :key="g.game_id">
            <RouterLink
              class="tile"
              :to="{ name: 'console', params: { id: g.console.id } }"
              :aria-label="`${g.name}, ${g.console.name}`"
            >
              <span class="tile-cover">
                <img v-if="g.cover_url" :src="g.cover_url" alt="" loading="lazy" />
                <span v-else class="tile-initials" aria-hidden="true">{{ initials(g.name) }}</span>
              </span>
              <span class="tile-name">{{ g.name }}</span>
            </RouterLink>
          </li>
        </ul>
      </section>

      <section class="section">
        <h2>Atualizado recentemente</h2>
        <p v-if="!stats.recent.length" class="muted">Nada por aqui ainda.</p>
        <ul v-else class="list">
          <li v-for="g in stats.recent" :key="g.game_id">
            <RouterLink
              class="card row-link"
              :to="{ name: 'console', params: { id: g.console.id } }"
            >
              <img v-if="g.cover_url" class="cover cover-sm" :src="g.cover_url" alt="" loading="lazy" />
              <span v-else class="cover cover-sm cover-empty" aria-hidden="true"></span>
              <span class="grow">
                <strong>{{ g.name }}</strong>
                <span class="muted small">{{ g.console.name }} · {{ statusLabel(g.status) }}</span>
              </span>
              <span class="muted small recent-date">{{ relativeDate(g.updated_at) }}</span>
            </RouterLink>
          </li>
        </ul>
      </section>
    </template>
  </main>
</template>
