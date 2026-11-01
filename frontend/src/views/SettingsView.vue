<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { useAuthStore } from '../stores/auth'
import { usePreferencesStore } from '../stores/preferences'
import { profileService } from '../services/profile'
import { ApiError } from '../services/api'
import { AvatarImageError, fileToAvatarDataUrl } from '../utils/avatarImage'
import AppAvatar from '../components/ui/AppAvatar.vue'
import AppInput from '../components/ui/AppInput.vue'
import AppSelect from '../components/ui/AppSelect.vue'
import AppButton from '../components/ui/AppButton.vue'
import AppBadge from '../components/ui/AppBadge.vue'
import PreferencesPanel from '../components/settings/PreferencesPanel.vue'
import LocaleSwitcher from '../components/ui/LocaleSwitcher.vue'
const { t } = useI18n()
const auth = useAuthStore()
const preferences = usePreferencesStore()
const username = ref(auth.user?.username ?? '')
watch(
  () => auth.user?.username,
  (value) => {
    if (value !== undefined) username.value = value
  },
)
const savingUsername = ref(false)
const usernameError = ref('')
const usernameSaved = ref(false)
watch(username, () => {
  usernameSaved.value = false
  usernameError.value = ''
})
const usernameChanged = computed(
  () => username.value.trim().length >= 3 && username.value.trim() !== (auth.user?.username ?? ''),
)
async function saveUsername() {
  if (!usernameChanged.value || savingUsername.value) return
  savingUsername.value = true
  usernameError.value = ''
  usernameSaved.value = false
  try {
    auth.user = await profileService.update(username.value.trim())
    usernameSaved.value = true
  } catch (e) {
    usernameError.value = e instanceof ApiError ? e.message : t('errors.actionFailed')
  } finally {
    savingUsername.value = false
  }
}
const avatarInput = ref<HTMLInputElement>()
const avatarBusy = ref(false)
const avatarError = ref('')
async function pickAvatar(event: Event) {
  const input = event.target as HTMLInputElement
  const file = input.files?.[0]
  input.value = '' // allow picking the same file again
  if (!file || avatarBusy.value) return
  avatarBusy.value = true
  avatarError.value = ''
  try {
    auth.user = await profileService.setAvatar(await fileToAvatarDataUrl(file))
  } catch (e) {
    avatarError.value =
      e instanceof AvatarImageError
        ? t(`settings.account.avatarErrors.${e.reason}`)
        : e instanceof ApiError
          ? e.message
          : t('errors.actionFailed')
  } finally {
    avatarBusy.value = false
  }
}
async function removeAvatar() {
  if (avatarBusy.value) return
  avatarBusy.value = true
  avatarError.value = ''
  try {
    auth.user = await profileService.removeAvatar()
  } catch (e) {
    avatarError.value = e instanceof ApiError ? e.message : t('errors.actionFailed')
  } finally {
    avatarBusy.value = false
  }
}
</script>
<template>
  <main class="page">
    <header class="page-heading">
      <div>
        <span class="eyebrow">{{ t('settings.eyebrow') }}</span>
        <h1>{{ t('settings.title') }}</h1>
        <p>{{ t('settings.subtitle') }}</p>
      </div>
    </header>
    <div class="settings-grid">
      <div class="settings-stack">
        <section class="panel settings-section">
          <h2>{{ t('settings.account.title') }}</h2>
          <AppInput
            v-model="username"
            :label="t('settings.account.username')"
            minlength="3"
            maxlength="24"
            :error="usernameError"
          /><AppButton :disabled="!usernameChanged" :loading="savingUsername" @click="saveUsername">{{
            t('settings.account.save')
          }}</AppButton
          ><AppBadge v-if="usernameSaved" tone="success">{{ t('settings.account.saved') }}</AppBadge>
          <AppInput
            :model-value="auth.user?.email ?? ''"
            :label="t('settings.account.email')"
            readonly
          />
          <div class="avatar-editor">
            <AppAvatar
              :name="auth.user?.username ?? t('common.player')"
              :src="auth.user?.avatar"
            />
            <div class="avatar-editor-actions">
              <AppButton :loading="avatarBusy" @click="avatarInput?.click()">{{
                auth.user?.avatar ? t('settings.account.changeAvatar') : t('settings.account.uploadAvatar')
              }}</AppButton
              ><AppButton
                v-if="auth.user?.avatar"
                variant="ghost"
                :disabled="avatarBusy"
                @click="removeAvatar"
                >{{ t('settings.account.removeAvatar') }}</AppButton
              >
              <input
                ref="avatarInput"
                type="file"
                accept="image/jpeg,image/png,image/webp,image/gif"
                class="visually-hidden"
                tabindex="-1"
                @change="pickAvatar"
              />
            </div>
            <p v-if="avatarError" class="error" role="alert">{{ avatarError }}</p>
          </div>
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
          />
        </section>
      </div>
      <section class="panel"><PreferencesPanel /></section>
    </div>
  </main>
</template>
