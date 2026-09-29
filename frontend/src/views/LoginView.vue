<script setup lang="ts">
import { ref } from 'vue'
import { RouterLink, useRoute, useRouter } from 'vue-router'
import { useI18n } from 'vue-i18n'
import { useAuthStore } from '../stores/auth'
import { ApiError } from '../services/api'
import AppInput from '../components/ui/AppInput.vue'
import AppButton from '../components/ui/AppButton.vue'
import AppIcon from '../components/ui/AppIcon.vue'
const { t } = useI18n()
const auth = useAuthStore()
const route = useRoute()
const router = useRouter()
const email = ref('')
const password = ref('')
const error = ref('')
const fieldErrors = ref<Record<string, string[]>>({})
async function submit() {
  error.value = ''
  fieldErrors.value = {}
  try {
    await auth.login({ email: email.value, password: password.value })
    const redirect =
      typeof route.query.redirect === 'string' &&
      route.query.redirect.startsWith('/') &&
      !route.query.redirect.startsWith('//')
        ? route.query.redirect
        : '/dashboard'
    await router.push(redirect)
  } catch (e) {
    error.value = e instanceof Error ? e.message : t('auth.login.genericError')
    if (e instanceof ApiError) fieldErrors.value = e.errors ?? {}
  }
}
</script>
<template>
  <main class="auth-page">
    <section class="auth-card panel">
      <div class="auth-mark"><AppIcon name="cards" :size="32" /></div>
      <span class="eyebrow">{{ t('auth.login.eyebrow') }}</span>
      <h1>{{ t('auth.login.title') }}</h1>
      <p class="muted">{{ t('auth.login.subtitle') }}</p>
      <form @submit.prevent="submit">
        <AppInput
          v-model="email"
          :label="t('auth.login.email')"
          type="email"
          autocomplete="email"
          required
          :error="fieldErrors.email?.[0]"
          placeholder="you@example.com"
        /><AppInput
          v-model="password"
          :label="t('auth.login.password')"
          type="password"
          autocomplete="current-password"
          required
          :error="fieldErrors.password?.[0]"
        />
        <p v-if="error" class="error" role="alert">{{ error }}</p>
        <AppButton type="submit" variant="primary" :loading="auth.loading"
          >{{ auth.loading ? t('auth.login.submitting') : t('auth.login.submit') }}<AppIcon
            name="arrow"
        /></AppButton>
      </form>
      <p class="auth-switch">
        {{ t('auth.login.noAccount') }}
        <RouterLink :to="{ path: '/register', query: route.query }">{{
          t('auth.login.createAccount')
        }}</RouterLink>
      </p>
    </section>
    <p class="auth-caption">{{ t('auth.login.caption') }}</p>
  </main>
</template>
