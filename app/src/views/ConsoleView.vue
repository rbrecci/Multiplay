<script setup>
import { ref, computed, watch, onMounted, onBeforeUnmount } from 'vue'
import api, { errorMessage } from '../api'
import { brandClass } from '../brand'
import { STATUS_OPTIONS, statusOf, statusClass, normalizeGame } from '../status'
import { initials } from '../initials'
import Icon from '../components/Icon.vue'
import GameSheet from '../components/GameSheet.vue'

const props = defineProps({ id: { type: String, required: true } })

const FILTERS = [
  { value: 'all', label: 'Todos' },
  { value: 'zerado', label: 'Zerados' },
  { value: 'jogado', label: 'Jogados' },
  { value: 'interesse', label: 'Interesse' },
  { value: 'quero_jogar', label: 'Quero jogar' },
  { value: 'favorite', label: 'Favoritos', icon: 'heart' },
]

const consoleInfo = ref(null)
const games = ref([])
const loading = ref(true)
const error = ref('')

const query = ref('')
const results = ref([])
const searching = ref(false)
const searchError = ref('')
const searched = ref(false)
const adding = ref(new Set())

const filter = ref('all')
const sort = ref('name')
const openGame = ref(null)
let lastTrigger = null

let debounceTimer = null
let lastRequest = 0

const brand = computed(() => brandClass(consoleInfo.value?.manufacturer))

const visibleGames = computed(() => {
  let list = games.value
  if (filter.value === 'favorite') list = list.filter((g) => g.favorite)
  else if (filter.value !== 'all') list = list.filter((g) => g.status === filter.value)

  const sorted = [...list]
  if (sort.value === 'year') {
    sorted.sort(
      (a, b) =>
        (b.first_release_year || 0) - (a.first_release_year || 0) || a.name.localeCompare(b.name, 'pt-BR'),
    )
  } else if (sort.value === 'recent') {
    sorted.sort((a, b) => new Date(b.created_at) - new Date(a.created_at))
  } else {
    sorted.sort((a, b) => a.name.localeCompare(b.name, 'pt-BR'))
  }
  return sorted
})

async function load() {
  loading.value = true
  error.value = ''
  try {
    const { data } = await api.get(`/consoles/${props.id}/games`)
    consoleInfo.value = data.console
    games.value = data.games.map(normalizeGame)
  } catch (e) {
    error.value = errorMessage(e, 'Não foi possível carregar este console.')
  } finally {
    loading.value = false
  }
}

async function search(term) {
  const requestId = ++lastRequest
  searching.value = true
  searchError.value = ''
  try {
    const { data } = await api.get(`/consoles/${props.id}/igdb/search`, { params: { q: term } })
    if (requestId !== lastRequest) return
    results.value = data
    searched.value = true
  } catch (e) {
    if (requestId !== lastRequest) return
    results.value = []
    searched.value = true
    searchError.value =
      e.response?.status === 503
        ? 'IGDB não configurada no servidor'
        : errorMessage(e, 'Falha ao buscar na IGDB.')
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
    searchError.value = ''
    return
  }
  debounceTimer = setTimeout(() => search(term), 400)
})

function markBusy(set, id, busy) {
  if (busy) set.value.add(id)
  else set.value.delete(id)
  set.value = new Set(set.value)
}

async function addGame(item) {
  markBusy(adding, item.igdb_id, true)
  try {
    const { data } = await api.post(`/consoles/${props.id}/games`, {
      igdb_id: item.igdb_id,
      name: item.name,
      cover_url: item.cover_url,
      first_release_year: item.first_release_year,
    })
    item.game_id = data.id
    if (!games.value.some((g) => g.id === data.id)) {
      games.value = [...games.value, normalizeGame(data)]
    }
  } catch (e) {
    searchError.value = errorMessage(e, 'Não foi possível adicionar o jogo.')
  } finally {
    markBusy(adding, item.igdb_id, false)
  }
}

function openSheet(game, event) {
  lastTrigger = event.currentTarget
  openGame.value = game
}

function closeSheet() {
  openGame.value = null
  lastTrigger?.focus()
  lastTrigger = null
}

onMounted(load)
onBeforeUnmount(() => clearTimeout(debounceTimer))
</script>

<template>
  <main class="page" :class="brand">
    <header class="topbar">
      <RouterLink class="btn btn-ghost" to="/consoles" aria-label="Voltar para consoles">&lsaquo; Consoles</RouterLink>
    </header>

    <p v-if="loading" class="muted">Carregando...</p>

    <div v-else-if="error && !consoleInfo" class="alert alert-error">
      {{ error }}
      <button class="btn btn-ghost" type="button" @click="load">Tentar de novo</button>
    </div>

    <template v-else>
      <h1 class="title-console">{{ consoleInfo.name }}</h1>
      <p class="muted small">{{ consoleInfo.manufacturer }} · {{ consoleInfo.release_year }}</p>

      <section class="section">
        <label class="search">
          <span class="sr-only">Buscar na IGDB</span>
          <input
            v-model="query"
            type="search"
            placeholder="Buscar na IGDB"
            autocomplete="off"
            enterkeyhint="search"
          />
        </label>

        <p v-if="searching" class="muted small">Buscando...</p>
        <p v-else-if="searchError" class="alert alert-error">{{ searchError }}</p>
        <p v-else-if="searched && results.length === 0" class="muted small">Nada encontrado na IGDB.</p>

        <ul v-if="results.length" class="list">
          <li v-for="r in results" :key="r.igdb_id" class="card game-card">
            <img v-if="r.cover_url" class="cover" :src="r.cover_url" alt="" loading="lazy" />
            <div v-else class="cover cover-empty" aria-hidden="true"></div>
            <div class="grow">
              <strong>{{ r.name }}</strong>
              <span class="muted small">{{ r.first_release_year || 'Ano desconhecido' }}</span>
            </div>
            <span v-if="r.game_id" class="badge">Já no catálogo</span>
            <button
              v-else
              class="btn btn-brand btn-sm"
              type="button"
              :disabled="adding.has(r.igdb_id)"
              @click="addGame(r)"
            >
              {{ adding.has(r.igdb_id) ? '...' : 'Adicionar' }}
            </button>
          </li>
        </ul>
      </section>

      <section class="section">
        <h2>Meus jogos neste console</h2>

        <p v-if="error" class="alert alert-error">{{ error }}</p>

        <p v-if="games.length === 0" class="muted">
          Nenhum jogo ainda. Use a busca acima para trazer jogos da IGDB.
        </p>

        <template v-else>
          <div class="toolbar">
            <div class="filter-row" role="group" aria-label="Filtrar por status">
              <button
                v-for="f in FILTERS"
                :key="f.value"
                class="chip chip-icon"
                :class="[statusClass(f.value === 'all' ? null : f.value), { active: filter === f.value }]"
                type="button"
                :aria-pressed="filter === f.value"
                @click="filter = f.value"
              >
                <Icon v-if="f.icon" :name="f.icon" />
                {{ f.label }}<template v-if="f.value === 'all'"> · {{ games.length }}</template>
              </button>
            </div>
            <label class="sort-row">
              <span>Ordenar por</span>
              <select v-model="sort">
                <option value="name">Nome A-Z</option>
                <option value="year">Ano</option>
                <option value="recent">Adicionado recentemente</option>
              </select>
            </label>
          </div>

          <p v-if="visibleGames.length === 0" class="muted">Nenhum jogo neste filtro.</p>

          <ul v-else class="grid">
            <li v-for="g in visibleGames" :key="g.id">
              <button
                class="tile"
                type="button"
                :aria-label="`${g.name}${statusOf(g.status) ? ', ' + statusOf(g.status).label : ''}${g.favorite ? ', favorito' : ''}`"
                @click="openSheet(g, $event)"
              >
                <span class="tile-cover">
                  <img v-if="g.cover_url" :src="g.cover_url" alt="" loading="lazy" />
                  <span v-else class="tile-initials" aria-hidden="true">{{ initials(g.name) }}</span>
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
                <span class="tile-name">{{ g.name }}</span>
              </button>
            </li>
          </ul>
        </template>
      </section>
    </template>

    <Teleport to="body">
      <Transition name="sheet">
        <GameSheet v-if="openGame" :game="openGame" @close="closeSheet" />
      </Transition>
    </Teleport>
  </main>
</template>
