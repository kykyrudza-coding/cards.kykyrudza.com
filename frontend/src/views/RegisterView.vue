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
const router = useRouter()
const route = useRoute()
const username = ref('')
const email = ref('')
const password = ref('')
const confirmation = ref('')
const error = ref('')
const fieldErrors = ref<Record<string, string[]>>({})
async function submit() {
  error.value = ''
  fieldErrors.value = {}
  if (password.value !== confirmation.value) {
    fieldErrors.value.password_confirmation = [t('auth.register.passwordMismatch')]
    return
  }
  try {
    await auth.register({
      username: username.value,
      email: email.value,
      password: password.value,
      password_confirmation: confirmation.value,
    })
    const redirect =
      typeof route.query.redirect === 'string' &&
      route.query.redirect.startsWith('/') &&
      !route.query.redirect.startsWith('//')
        ? route.query.redirect
        : '/dashboard'
    await router.push(redirect)
  } catch (e) {
    error.value = e instanceof Error ? e.message : t('auth.register.genericError')
    if (e instanceof ApiError) fieldErrors.value = e.errors ?? {}
  }
}
</script>
<template>
  <main class="auth-page">
    <section class="auth-card panel">
      <span class="eyebrow">{{ t('auth.register.eyebrow') }}</span>
      <h1>{{ t('auth.register.title') }}</h1>
      <p class="muted">{{ t('auth.register.subtitle') }}</p>
      <form @submit.prevent="submit">
        <AppInput
          v-model="username"
          :label="t('auth.register.username')"
          autocomplete="username"
          required
          minlength="3"
          maxlength="24"
          pattern="[A-Za-z0-9_\-]+"
          :hint="t('auth.register.usernameHint')"
          :error="fieldErrors.username?.[0]"
        /><AppInput
          v-model="email"
          :label="t('auth.register.email')"
          type="email"
          autocomplete="email"
          required
          :error="fieldErrors.email?.[0]"
        /><AppInput
          v-model="password"
          :label="t('auth.register.password')"
          type="password"
          autocomplete="new-password"
          minlength="8"
          required
          :hint="t('auth.register.passwordHint')"
          :error="fieldErrors.password?.[0]"
        /><AppInput
          v-model="confirmation"
          :label="t('auth.register.confirmPassword')"
          type="password"
          autocomplete="new-password"
          required
          :error="fieldErrors.password_confirmation?.[0]"
        />
        <p v-if="error" class="error" role="alert">{{ error }}</p>
        <AppButton type="submit" variant="primary" :loading="auth.loading"
          >{{ t('auth.register.submit') }}<AppIcon name="arrow"
        /></AppButton>
      </form>
      <p class="auth-switch">
        {{ t('auth.register.haveAccount') }}
        <RouterLink :to="{ path: '/login', query: route.query }">{{
          t('auth.register.signIn')
        }}</RouterLink>
      </p>
    </section>
  </main>
</template>
