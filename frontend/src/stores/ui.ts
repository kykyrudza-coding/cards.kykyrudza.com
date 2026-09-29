import { defineStore } from 'pinia'
export type ToastTone = 'success' | 'danger' | 'info'
let nextId = 0
export const useUiStore = defineStore('ui', {
  state: () => ({ toasts: [] as { id: number; message: string; tone: ToastTone }[] }),
  actions: {
    toast(message: string, tone: ToastTone = 'success') {
      const id = ++nextId
      this.toasts.push({ id, message, tone })
      if (this.toasts.length > 4) this.toasts.shift()
      window.setTimeout(() => this.dismiss(id), 5000)
    },
    dismiss(id: number) {
      this.toasts = this.toasts.filter((item) => item.id !== id)
    },
  },
})
