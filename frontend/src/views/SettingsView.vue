<script setup lang="ts">
import { useI18n } from 'vue-i18n'
import { useAuthStore } from '../stores/auth'
import { usePreferencesStore } from '../stores/preferences'
import AppInput from '../components/ui/AppInput.vue'
import AppSelect from '../components/ui/AppSelect.vue'
import AppButton from '../components/ui/AppButton.vue'
import AppBadge from '../components/ui/AppBadge.vue'
import PreferencesPanel from '../components/settings/PreferencesPanel.vue'
import LocaleSwitcher from '../components/ui/LocaleSwitcher.vue'
const { t } = useI18n()
const auth = useAuthStore()
const preferences = usePreferencesStore()
</script>
<template>
  <main class="page">
    <header class="page-heading">
      <div>
        <span class="eyebrow">{{ t('settings.eyebrow') }}</span>
        <h1>{{ t('settings.title') }}</h1>
        <p>{{ t('settings.subtitle') }}</p>
      </div>
      <AppBadge>{{ t('settings.savedBadge') }}</AppBadge>
    </header>
    <div class="settings-grid">
      <div class="settings-stack">
        <section class="panel settings-section">
          <h2>{{ t('settings.account.title') }}</h2>
          <AppInput
            :model-value="auth.user?.username ?? ''"
            :label="t('settings.account.username')"
            readonly
          /><AppInput
            :model-value="auth.user?.email ?? ''"
            :label="t('settings.account.email')"
            readonly
          /><AppButton disabled>{{ t('settings.account.uploadAvatar') }}</AppButton>
          <p class="muted small">{{ t('settings.account.note') }}</p>
        </section>
        <section class="panel settings-section">
          <h2>{{ t('settings.language.title') }}</h2>
          <p class="muted small">{{ t('settings.language.hint') }}</p>
          <LocaleSwitcher />
        </section>
        <section class="panel settings-section">
          <h2>{{ t('settings.appearance.title') }}</h2>
          <AppSelect
            v-model="preferences.theme"
            :label="t('settings.appearance.theme')"
            :options="[
              { value: 'dark', label: t('settings.appearance.themeDark') },
              { value: 'system', label: t('settings.appearance.themeSystem') },
            ]"
          /><AppSelect
            model-value="classic"
            :label="t('settings.appearance.cardBack')"
            :options="[{ value: 'classic', label: t('settings.appearance.classic') }]"
            disabled
          /><AppSelect
            model-value="classic"
            :label="t('settings.appearance.tableStyle')"
            :options="[{ value: 'classic', label: t('settings.appearance.classicGreen') }]"
            disabled
          /><AppSelect
            model-value="classic"
            :label="t('settings.appearance.chipStyle')"
            :options="[{ value: 'classic', label: t('settings.appearance.classic') }]"
            disabled
          />
        </section>
      </div>
      <section class="panel"><PreferencesPanel /></section>
    </div>
  </main>
</template>
