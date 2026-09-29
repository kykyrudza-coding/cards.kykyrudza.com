<script setup lang="ts">
import { ref, watch, onBeforeUnmount, useId } from 'vue'
import { useI18n } from 'vue-i18n'
import AppIcon from './AppIcon.vue'
const { t } = useI18n()
const props = defineProps<{ open: boolean; title: string; drawer?: boolean }>()
const emit = defineEmits<{ close: [] }>()
const dialog = ref<HTMLDialogElement>()
const id = useId()
let returnFocus: HTMLElement | null = null
watch(
  [() => props.open, dialog],
  ([open]) => {
    if (!dialog.value) return
    if (open && !dialog.value.open) {
      returnFocus = document.activeElement as HTMLElement
      dialog.value.showModal()
    } else if (!open && dialog.value.open) {
      dialog.value.close()
      returnFocus?.focus()
    }
  },
  { flush: 'post', immediate: true },
)
onBeforeUnmount(() => {
  dialog.value?.close()
  returnFocus?.focus()
})
function trapFocus(event: KeyboardEvent) {
  if (event.key !== 'Tab' || !dialog.value) return
  const focusable = [
    ...dialog.value.querySelectorAll<HTMLElement>(
      'button:not(:disabled),a[href],input:not(:disabled),select:not(:disabled),textarea:not(:disabled),[tabindex="0"]',
    ),
  ].filter((element) => element.getClientRects().length > 0)
  const first = focusable[0]
  const last = focusable.at(-1)
  if (!first || !last) {
    event.preventDefault()
    return
  }
  if (event.shiftKey && document.activeElement === first) {
    event.preventDefault()
    last.focus()
  } else if (!event.shiftKey && document.activeElement === last) {
    event.preventDefault()
    first.focus()
  }
}
</script>
<template>
  <Teleport to="body"
    ><dialog
      ref="dialog"
      class="modal"
      :class="{ drawer }"
      :aria-labelledby="id"
      @keydown="trapFocus"
      @cancel.prevent="emit('close')"
      @click="
        (event) => {
          if (event.target === dialog) emit('close')
        }
      "
    >
      <div class="modal-content">
        <header class="modal-header">
          <h2 :id="id">{{ title }}</h2>
          <button
            type="button"
            class="icon-button"
            :aria-label="t('ui.closeDialog')"
            @click="emit('close')"
          >
            <AppIcon name="close" />
          </button>
        </header>
        <slot />
      </div></dialog
  ></Teleport>
</template>
