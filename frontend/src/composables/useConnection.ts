import { onUnmounted, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { connectEcho } from '../realtime/echo'
import { useUiStore } from '../stores/ui'
export function useConnection(refresh: () => Promise<unknown>) {
  const status = ref('Connecting')
  const ui = useUiStore()
  const { t } = useI18n()
  let cleanup: (() => void) | undefined
  let wasConnected = false
  let interrupted = false
  function monitor() {
    cleanup?.()
    const connection = connectEcho().connector.pusher.connection
    const update = ({ current }: { current: string }) => {
      if (current === 'connected') {
        status.value = 'Connected'
        if (wasConnected && interrupted) {
          ui.toast(t('errors.reconnected'))
          refresh().catch(() => ui.toast(t('errors.couldNotRefresh'), 'danger'))
        }
        wasConnected = true
        interrupted = false
      } else {
        status.value = wasConnected ? 'Reconnecting' : 'Connecting'
        if (wasConnected && !interrupted) {
          ui.toast(t('errors.connectionLost'), 'info')
          interrupted = true
        }
        if (current === 'unavailable' || current === 'failed') status.value = 'Reconnecting'
      }
    }
    connection.bind('state_change', update)
    update({ current: connection.state })
    cleanup = () => connection.unbind('state_change', update)
  }
  onUnmounted(() => cleanup?.())
  return { status, monitor }
}
