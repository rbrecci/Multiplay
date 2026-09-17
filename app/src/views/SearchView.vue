<script setup>
import { ref, watch, onMounted, onBeforeUnmount } from 'vue'
import api, { errorMessage } from '../api'
import { statusOf, statusClass } from '../status'
import { initials } from '../initials'
import Icon from '../components/Icon.vue'

const input = ref(null)
const query = ref('')
const results = ref([])
const searching = ref(false)
const searched = ref(false)
const error = ref('')

let debounceTimer = null
let lastRequest = 0

async function search(term) {
  const requestId = ++lastRequest
  searching.value = true
  error.value = ''
  try {
    const { data } = await api.get('/games/search', { params: { q: term } })
    if (requestId !== lastRequest) return
    results.value = data
    searched.value = true
  } catch (e) {
    if (requestId !== lastRequest) return
    results.value = []
    searched.value = true
    error.value = errorMessage(e, 'Falha ao buscar na sua coleção.')
  } finally {
    if (requestId === lastRequest) searching.value = false
  }
}

watch(query, (value) => {
  clearTimeout(debounceTimer)
  const term = value.trim()
  if (term.length < 2) {
    lastRequest++
    results.value = []
    searched.value = false
    searching.value = false
    error.value = ''
    return
  }
  debounceTimer = setTimeout(() => search(term), 300)
})

function ariaLabel(g) {
  const parts = [g.name, g.console.name]
  if (statusOf(g.status)) parts.push(statusOf(g.status).label)
  if (g.favorite) parts.push('favorito')
  return parts.join(', ')
}

onMounted(() => input.value?.focus())
onBeforeUnmount(() => clearTimeout(debounceTimer))
</script>

<template>
  <main class="page">
    <header class="topbar">
      <h1>Buscar</h1>
    </header>

    <label class="search">
      <span class="sr-only">Buscar na coleção</span>
      <input
        ref="input"
        v-model="query"
        type="search"
        placeholder="Nome do jogo"
        autocomplete="off"
        enterkeyhint="search"
        autofocus
      />
    </label>

    <section class="section">
      <p v-if="!searched && !searching" class="muted">
        Busca nos jogos que você já importou, em qualquer console.
      </p>
      <p v-else-if="searching" class="muted small">Buscando...</p>
      <p v-else-if="error" class="alert alert-error" role="alert">{{ error }}</p>
      <p v-else-if="results.length === 0" class="muted">
        Nenhum jogo da sua coleção com esse nome.
      </p>

      <ul v-if="results.length" class="list">
        <li v-for="g in results" :key="g.id">
          <RouterLink
            class="card game-card"
            :to="{ name: 'console', params: { id: g.console.id } }"
            :aria-label="ariaLabel(g)"
          >
            <span class="cover-wrap">
              <img v-if="g.cover_url" class="cover" :src="g.cover_url" alt="" loading="lazy" />
              <span v-else class="cover cover-empty cover-initials" aria-hidden="true">
                {{ initials(g.name) }}
              </span>
              <span v-if="g.favorite" class="fav-dot" aria-hidden="true"><Icon name="heart" /></span>
              <span
                v-if="statusOf(g.status)"
                class="status-dot"
                :class="statusClass(g.status)"
                aria-hidden="true"
              >
                <Icon :name="statusOf(g.status).icon" />
              </span>
            </span>
            <span class="grow">
              <strong>{{ g.name }}</strong>
              <span class="muted small">{{ g.console.name }} · {{ g.console.manufacturer }}</span>
            </span>
          </RouterLink>
        </li>
      </ul>
    </section>
  </main>
</template>
