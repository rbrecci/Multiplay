<script setup>
import { useRoute } from 'vue-router'
import Icon from './Icon.vue'

const ITEMS = [
  { to: '/', label: 'Início', icon: 'home', names: ['home'] },
  { to: '/consoles', label: 'Consoles', icon: 'gamepad', names: ['consoles', 'console'] },
  { to: '/buscar', label: 'Buscar', icon: 'search', names: ['search'] },
]

const route = useRoute()

function isActive(item) {
  return item.names.includes(route.name)
}
</script>

<template>
  <nav class="bottom-nav" aria-label="Navegação principal">
    <div class="bottom-nav-inner">
      <RouterLink
        v-for="item in ITEMS"
        :key="item.to"
        class="nav-item"
        :class="{ active: isActive(item) }"
        :to="item.to"
        :aria-current="isActive(item) ? 'page' : null"
      >
        <Icon :name="item.icon" />
        <span>{{ item.label }}</span>
      </RouterLink>
    </div>
  </nav>
</template>
