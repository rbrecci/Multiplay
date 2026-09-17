const RULES = [
  ['nintendo', ['nintendo']],
  ['playstation', ['sony', 'playstation']],
  ['xbox', ['microsoft', 'xbox']],
  ['pc', ['pc', 'valve', 'windows']],
]

export function brandOf(manufacturer) {
  const text = String(manufacturer || '').toLowerCase()
  const hit = RULES.find(([, words]) => words.some((w) => text.includes(w)))
  return hit ? hit[0] : 'default'
}

export function brandClass(manufacturer) {
  return `brand-${brandOf(manufacturer)}`
}
