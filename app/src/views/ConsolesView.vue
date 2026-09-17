<script setup>
import { ref, onMounted } from 'vue'
import api, { errorMessage } from '../api'
import { brandClass } from '../brand'
import ConsoleForm from '../components/ConsoleForm.vue'

const consoles = ref([])
const loading = ref(true)
const error = ref('')
const formOpen = ref(false)
let lastTrigger = null

async function load() {
  loading.value = true
  error.value = ''
  try {
    const { data } = await api.get('/consoles')
    consoles.value = data
  } catch (e) {
    error.value = errorMessage(e, 'Não foi possível carregar os consoles.')
  } finally {
    loading.value = false
  }
}

function statsOf(c) {
  const total = c.games_count || 0
  if (total === 0) return null
  const done = c.completed_count || 0
  return {
    done,
    total,
    doneLabel: done === 1 ? 'zerado' : 'zerados',
    totalLabel: total === 1 ? 'jogo' : 'jogos',
  }
}

function openForm(event) {
  lastTrigger = event.currentTarget
  formOpen.value = true
}

function closeForm() {
  formOpen.value = false
  lastTrigger?.focus()
  lastTrigger = null
}

function onCreated() {
  closeForm()
  load()
}

onMounted(load)
</script>

<template>
  <main class="page">
    <header class="topbar">
      <h1>Consoles</h1>
      <button class="btn btn-primary btn-sm" type="button" @click="openForm">Adicionar console</button>
    </header>

    <p v-if="loading" class="muted">Carregando consoles...</p>

    <div v-else-if="error" class="alert alert-error">
      {{ error }}
      <button class="btn btn-ghost" type="button" @click="load">Tentar de novo</button>
    </div>

    <p v-else-if="consoles.length === 0" class="muted">Nenhum console cadastrado.</p>

    <ul v-else class="list">
      <li v-for="c in consoles" :key="c.id">
        <RouterLink
          class="card console-card"
          :class="brandClass(c.manufacturer)"
          :to="{ name: 'console', params: { id: c.id } }"
        >
          <div class="console-head">
            <div class="cover-strip" aria-hidden="true">
              <template v-if="c.covers && c.covers.length">
                <img v-for="(url, i) in c.covers.slice(0, 4)" :key="i" :src="url" alt="" loading="lazy" />
              </template>
              <div v-else class="cover-mini"></div>
            </div>
            <div class="grow">
              <span class="console-name">{{ c.name }}</span>
              <span class="muted small">{{ c.manufacturer }} · {{ c.release_year }}</span>
            </div>
            <span v-if="c.user_id" class="badge badge-mine" title="Console cadastrado por você">Meu</span>
          </div>
          <p class="console-stats">
            <template v-if="statsOf(c)">
              <strong>{{ statsOf(c).done }}</strong> {{ statsOf(c).doneLabel }} de
              <strong>{{ statsOf(c).total }}</strong> {{ statsOf(c).totalLabel }}
            </template>
            <template v-else>Nenhum jogo ainda</template>
          </p>
        </RouterLink>
      </li>
    </ul>

    <Teleport to="body">
      <Transition name="sheet">
        <ConsoleForm v-if="formOpen" @close="closeForm" @created="onCreated" />
      </Transition>
    </Teleport>
  </main>
</template>
