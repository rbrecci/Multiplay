<script setup>
import { ref, watch, nextTick, onMounted, onBeforeUnmount } from 'vue'
import api, { errorMessage } from '../api'

const emit = defineEmits(['close', 'created'])

const MANUFACTURERS = ['Nintendo', 'Sony', 'Microsoft', 'Sega', 'PC']

const nameInput = ref(null)
const name = ref('')
const manufacturer = ref('')
const releaseYear = ref('')
const saving = ref(false)
const error = ref('')

const platformQuery = ref('')
const platforms = ref([])
const platformSearching = ref(false)
const platformSearched = ref(false)
const platformError = ref('')
const selected = ref(null)

let debounceTimer = null
let lastRequest = 0

async function searchPlatforms(term) {
  const requestId = ++lastRequest
  platformSearching.value = true
  platformError.value = ''
  try {
    const { data } = await api.get('/igdb/platforms/search', { params: { q: term } })
    if (requestId !== lastRequest) return
    platforms.value = data.slice(0, 20)
    platformSearched.value = true
  } catch (e) {
    if (requestId !== lastRequest) return
    platforms.value = []
    platformSearched.value = true
    platformError.value =
      e.response?.status === 503
        ? 'IGDB não configurada no servidor'
        : errorMessage(e, 'Falha ao buscar plataformas.')
  } finally {
    if (requestId === lastRequest) platformSearching.value = false
  }
}

watch(platformQuery, (value) => {
  clearTimeout(debounceTimer)
  const term = value.trim()
  if (term.length < 2) {
    lastRequest++
    platforms.value = []
    platformSearched.value = false
    platformSearching.value = false
    platformError.value = ''
    return
  }
  debounceTimer = setTimeout(() => searchPlatforms(term), 300)
})

function pickPlatform(p) {
  selected.value = p
  platformQuery.value = ''
}

function clearPlatform() {
  selected.value = null
}

async function submit() {
  error.value = ''
  saving.value = true
  try {
    const { data } = await api.post('/consoles', {
      name: name.value.trim(),
      manufacturer: manufacturer.value.trim(),
      release_year: Number(releaseYear.value),
      igdb_platform_id: selected.value ? selected.value.id : null,
    })
    emit('created', data)
  } catch (e) {
    error.value = errorMessage(e, 'Não foi possível salvar o console.')
  } finally {
    saving.value = false
  }
}

function onKey(e) {
  if (e.key === 'Escape') emit('close')
}

function onBackdrop(e) {
  if (e.target === e.currentTarget) emit('close')
}

onMounted(async () => {
  document.addEventListener('keydown', onKey)
  document.body.style.overflow = 'hidden'
  await nextTick()
  nameInput.value?.focus()
})

onBeforeUnmount(() => {
  clearTimeout(debounceTimer)
  document.removeEventListener('keydown', onKey)
  document.body.style.overflow = ''
})
</script>

<template>
  <div class="sheet-backdrop" @click="onBackdrop">
    <form
      class="sheet"
      role="dialog"
      aria-modal="true"
      aria-labelledby="console-form-title"
      @submit.prevent="submit"
    >
      <div class="sheet-handle" aria-hidden="true"></div>

      <h2 id="console-form-title" class="sheet-title">Adicionar console</h2>

      <label>
        <span>Nome</span>
        <input ref="nameInput" v-model="name" type="text" maxlength="100" autocomplete="off" required />
      </label>

      <label>
        <span>Fabricante</span>
        <input
          v-model="manufacturer"
          type="text"
          maxlength="60"
          list="manufacturer-options"
          autocomplete="off"
          required
        />
        <datalist id="manufacturer-options">
          <option v-for="m in MANUFACTURERS" :key="m" :value="m"></option>
        </datalist>
      </label>

      <label>
        <span>Ano de lançamento</span>
        <input
          v-model="releaseYear"
          type="number"
          inputmode="numeric"
          min="1970"
          max="2100"
          step="1"
          required
        />
      </label>

      <div>
        <label>
          <span>Plataforma na IGDB (opcional)</span>
          <input
            v-model="platformQuery"
            type="search"
            placeholder="Buscar plataforma"
            autocomplete="off"
            enterkeyhint="search"
            :disabled="Boolean(selected)"
          />
        </label>
        <p class="muted small help">
          Sem plataforma, a busca de jogos da IGDB não funciona nesse console; dá pra deixar em
          branco e cadastrar só o console.
        </p>

        <div v-if="selected" class="selected-row">
          <span>Selecionada: <strong>{{ selected.name }}</strong> (#{{ selected.id }})</span>
          <button class="btn btn-ghost btn-sm" type="button" @click="clearPlatform">Limpar</button>
        </div>

        <template v-else>
          <p v-if="platformSearching" class="muted small">Buscando...</p>
          <p v-else-if="platformError" class="alert alert-error" role="alert">{{ platformError }}</p>
          <p v-else-if="platformSearched && platforms.length === 0" class="muted small">
            Nenhuma plataforma encontrada.
          </p>
          <div
            v-if="platforms.length"
            class="pick-list"
            role="group"
            aria-label="Plataformas encontradas"
          >
            <button
              v-for="p in platforms"
              :key="p.id"
              class="btn pick"
              type="button"
              @click="pickPlatform(p)"
            >
              <span class="grow">{{ p.name }}</span>
              <span v-if="p.abbreviation" class="badge">{{ p.abbreviation }}</span>
            </button>
          </div>
        </template>
      </div>

      <p v-if="error" class="alert alert-error" role="alert">{{ error }}</p>

      <button class="btn btn-primary" type="submit" :disabled="saving">
        {{ saving ? 'Salvando...' : 'Salvar console' }}
      </button>
      <button class="btn" type="button" @click="emit('close')">Fechar</button>
    </form>
  </div>
</template>
