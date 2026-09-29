<script setup lang="ts">
import { useRouter } from 'vue-router'
import { useI18n } from 'vue-i18n'
import { useUiStore } from '../../stores/ui'
import AppButton from '../ui/AppButton.vue'
import AppIcon from '../ui/AppIcon.vue'
const props = defineProps<{ code: string }>()
const { t } = useI18n()
const ui = useUiStore()
const router = useRouter()
async function copy(link: boolean) {
  try {
    const text = link
      ? new URL(router.resolve(`/lobby/${props.code}`).href, window.location.origin).href
      : props.code
    await navigator.clipboard.writeText(text)
    ui.toast(link ? t('lobby.code.copiedLink') : t('lobby.code.copiedCode'))
  } catch {
    ui.toast(t('lobby.code.clipboardError'), 'danger')
  }
}
</script>
<template>
  <div class="lobby-code">
    <strong>{{ code }}</strong
    ><AppButton variant="ghost" @click="copy(false)"
      ><AppIcon name="copy" :size="16" />{{ t('lobby.code.copyCode') }}</AppButton
    ><AppButton variant="secondary" @click="copy(true)"
      ><AppIcon name="link" :size="16" />{{ t('lobby.code.inviteFriends') }}</AppButton
    >
  </div>
</template>
