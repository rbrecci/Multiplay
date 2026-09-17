<script setup>
const PATHS = {
  check: 'M5 12.5l4.5 4.5L19 7.5',
  star: 'M12 2.8l2.8 5.9 6.4.8-4.7 4.4 1.2 6.4L12 17.2l-5.7 3.1 1.2-6.4L2.8 9.5l6.4-.8z',
  trophy: 'M7 4h10v5a5 5 0 0 1-10 0V4zM7 6H4v1.5A3.5 3.5 0 0 0 7.5 11M17 6h3v1.5A3.5 3.5 0 0 1 16.5 11M12 14v4M8 21h8M9.5 18h5',
  clock: 'M12 3.5a8.5 8.5 0 1 0 0 17 8.5 8.5 0 0 0 0-17zM12 7.5V12l3 2',
  heart:
    'M12 20.6l-1.3-1.2C5.6 14.8 2.3 11.8 2.3 8.1 2.3 5.1 4.6 2.8 7.6 2.8c1.7 0 3.3.8 4.4 2.1 1.1-1.3 2.7-2.1 4.4-2.1 3 0 5.3 2.3 5.3 5.3 0 3.7-3.3 6.7-8.4 11.3L12 20.6z',
  home: 'M3 11.5L12 4l9 7.5M5.5 10v10h13V10M10 20v-5h4v5',
  gamepad:
    'M7 7h10a5 5 0 0 1 5 5v1.5a3.5 3.5 0 0 1-6.3 2.1L14.5 14h-5l-1.2 1.6A3.5 3.5 0 0 1 2 13.5V12a5 5 0 0 1 5-5zM8 9.5v4M6 11.5h4M16 10.5h.01M18.5 12.5h.01',
  search: 'M10.5 4a6.5 6.5 0 1 0 0 13 6.5 6.5 0 0 0 0-13zM15.5 15.5L20 20',
}
const FILLED = new Set(['star', 'heart'])

defineProps({ name: { type: String, required: true } })
</script>

<template>
  <svg
    class="icon"
    viewBox="0 0 24 24"
    width="1em"
    height="1em"
    aria-hidden="true"
    focusable="false"
    :fill="FILLED.has(name) ? 'currentColor' : 'none'"
    :stroke="FILLED.has(name) ? 'none' : 'currentColor'"
    stroke-width="2.4"
    stroke-linecap="round"
    stroke-linejoin="round"
  >
    <path :d="PATHS[name]" />
  </svg>
</template>
