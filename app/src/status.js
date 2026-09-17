export const STATUS_OPTIONS = [
  { value: 'jogado', label: 'Já joguei', short: 'Jogados', icon: 'check' },
  { value: 'interesse', label: 'Tenho interesse', short: 'Interesse', icon: 'star' },
  { value: 'zerado', label: 'Já zerei', short: 'Zerados', icon: 'trophy' },
  { value: 'quero_jogar', label: 'Quero jogar', short: 'Quero jogar', icon: 'clock' },
]

export function statusOf(value) {
  return STATUS_OPTIONS.find((s) => s.value === value) || null
}

export function statusClass(value) {
  return value ? `status-${value}` : ''
}

export function normalizeGame(game) {
  return {
    status: null,
    notes: null,
    rating: null,
    favorite: false,
    created_at: new Date().toISOString(),
    ...game,
    favorite: Boolean(game.favorite),
  }
}
