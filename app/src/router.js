import { createRouter, createWebHistory } from 'vue-router'
import { auth } from './auth'
import LoginView from './views/LoginView.vue'
import HomeView from './views/HomeView.vue'
import ConsolesView from './views/ConsolesView.vue'
import ConsoleView from './views/ConsoleView.vue'
import SearchView from './views/SearchView.vue'

const router = createRouter({
  history: createWebHistory(),
  routes: [
    { path: '/login', name: 'login', component: LoginView, meta: { public: true } },
    { path: '/', name: 'home', component: HomeView },
    { path: '/consoles', name: 'consoles', component: ConsolesView },
    { path: '/consoles/:id', name: 'console', component: ConsoleView, props: true },
    { path: '/buscar', name: 'search', component: SearchView },
    { path: '/:pathMatch(.*)*', redirect: '/' },
  ],
})

router.beforeEach((to) => {
  if (!to.meta.public && !auth.isLogged) {
    return { name: 'login', query: to.fullPath !== '/' ? { next: to.fullPath } : {} }
  }
  if (to.name === 'login' && auth.isLogged) {
    return { name: 'home' }
  }
})

export default router
