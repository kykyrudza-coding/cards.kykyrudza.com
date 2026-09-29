import { createApp } from 'vue'
import { createPinia } from 'pinia'
import './style.css'
import App from './App.vue'
import router from './router'
import { useAuthStore } from './stores/auth'
import { i18n } from './i18n'

const app = createApp(App)

app.use(createPinia())
app.use(router)
app.use(i18n)
document.documentElement.lang = i18n.global.locale.value

const auth = useAuthStore()
if (auth.isAuthenticated) {
  auth.fetchMe().catch(() => undefined)
}

app.mount('#app')
