<script setup>
import { ref, watch, computed, nextTick, onMounted, onBeforeUnmount } from 'vue'
import api, { errorMessage } from '../api'
import { STATUS_OPTIONS, statusClass } from '../status'
import { initials as initialsOf } from '../initials'
import Icon from './Icon.vue'

const props = defineProps({ game: { type: Object, required: true } })
const emit = defineEmits(['close'])

const panel = ref(null)
const error = ref('')
const busy = ref(new Set())

const noteText = ref(props.game.notes || '')
const noteState = ref('idle')

const initials = computed(() => initialsOf(props.game.name))

const titleId = `sheet-title-${props.game.id}`

function setBusy(key, on) {
  if (on) busy.value.add(key)
  else busy.value.delete(key)
  busy.value = new Set(busy.value)
}

async function setStatus(status) {
  const previous = props.game.status
  const next = status === previous ? null : status
  props.game.status = next
  setBusy('status', true)
  error.value = ''
  try {
    const { data } = await api.post(`/games/${props.game.id}/status`, { status: next })
    props.game.status = data.status
  } catch (e) {
    props.game.status = previous
    error.value = errorMessage(e, 'Não foi possível salvar o status.')
  } finally {
    setBusy('status', false)
  }
}

async function patchDetail(field, value) {
  const previous = props.game[field]
  props.game[field] = value
  setBusy(field, true)
  error.value = ''
  try {
    const { data } = await api.patch(`/games/${props.game.id}/detail`, { [field]: value })
    props.game[field] = data[field]
    return true
  } catch (e) {
    props.game[field] = previous
    error.value = errorMessage(e, 'Não foi possível salvar.')
    return false
  } finally {
    setBusy(field, false)
  }
}

function setRating(n) {
  patchDetail('rating', n === props.game.rating ? null : n)
}

function toggleFavorite() {
  patchDetail('favorite', !props.game.favorite)
}

watch(noteText, (v) => {
  if (v !== (props.game.notes || '')) noteState.value = 'dirty'
  else if (noteState.value === 'dirty') noteState.value = 'idle'
})

async function saveNote() {
  if (noteState.value !== 'dirty') return
  noteState.value = 'saving'
  const value = noteText.value.trim() || null
  const ok = await patchDetail('notes', value)
  if (ok) {
    noteText.value = props.game.notes || ''
    noteState.value = 'saved'
  } else {
    noteState.value = 'dirty'
  }
}

const noteLabel = computed(() => {
  if (noteState.value === 'saving') return 'Salvando...'
  if (noteState.value === 'saved') return 'Salvo'
  return 'Salvar nota'
})

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
  panel.value?.focus()
})

onBeforeUnmount(() => {
  document.removeEventListener('keydown', onKey)
  document.body.style.overflow = ''
})
</script>

<template>
  <div class="sheet-backdrop" @click="onBackdrop">
    <div
      ref="panel"
      class="sheet"
      role="dialog"
      aria-modal="true"
      :aria-labelledby="titleId"
      tabindex="-1"
    >
      <div class="sheet-handle" aria-hidden="true"></div>

      <div class="sheet-head">
        <div class="sheet-cover">
          <img v-if="game.cover_url" :src="game.cover_url" alt="" />
          <div v-else class="tile-initials" aria-hidden="true">{{ initials }}</div>
        </div>
        <div class="grow">
          <h2 :id="titleId" class="sheet-title">{{ game.name }}</h2>
          <span class="muted small">{{ game.first_release_year || 'Ano desconhecido' }}</span>
          <div class="sheet-actions">
            <button
              class="btn btn-sm btn-fav"
              type="button"
              :aria-pressed="game.favorite"
              :aria-label="game.favorite ? 'Remover dos favoritos' : 'Marcar como favorito'"
              :disabled="busy.has('favorite')"
              @click="toggleFavorite"
            >
              <Icon name="heart" />
              {{ game.favorite ? 'Favorito' : 'Favoritar' }}
            </button>
          </div>
        </div>
      </div>

      <p v-if="error" class="alert alert-error" role="alert">{{ error }}</p>

      <div>
        <p class="field-label">Status</p>
        <div class="chips" role="group" aria-label="Status do jogo">
          <button
            v-for="opt in STATUS_OPTIONS"
            :key="opt.value"
            class="chip chip-icon"
            :class="[statusClass(opt.value), { active: game.status === opt.value }]"
            type="button"
            :aria-pressed="game.status === opt.value"
            :disabled="busy.has('status')"
            @click="setStatus(opt.value)"
          >
            <Icon :name="opt.icon" />
            {{ opt.label }}
          </button>
        </div>
      </div>

      <div>
        <p class="field-label" id="rating-label">Sua nota</p>
        <div class="stars" role="group" aria-labelledby="rating-label">
          <button
            v-for="n in 5"
            :key="n"
            class="star"
            :class="{ lit: game.rating != null && n <= game.rating }"
            type="button"
            :aria-label="n === 1 ? '1 estrela' : `${n} estrelas`"
            :aria-pressed="game.rating === n"
            :disabled="busy.has('rating')"
            @click="setRating(n)"
          >
            <Icon name="star" />
          </button>
        </div>
      </div>

      <div>
        <label>
          <span class="field-label">Nota pessoal</span>
          <textarea
            v-model="noteText"
            rows="3"
            placeholder="Onde parou, o que achou, dicas..."
            @blur="saveNote"
          ></textarea>
        </label>
        <div class="note-foot">
          <button
            class="btn btn-sm"
            type="button"
            :disabled="noteState !== 'dirty'"
            @click="saveNote"
          >
            {{ noteLabel }}
          </button>
        </div>
      </div>

      <button class="btn" type="button" @click="emit('close')">Fechar</button>
    </div>
  </div>
</template>
