<script setup>
import { RouterView, useRoute } from 'vue-router'
import { auth } from './auth'
import BottomNav from './components/BottomNav.vue'

const route = useRoute()
</script>

<template>
  <RouterView />
  <BottomNav v-if="auth.isLogged && !route.meta.public" />
</template>
