import { computed, onBeforeUnmount, onMounted, ref, type Ref } from 'vue'

/**
 * A ticking "server now" in seconds. The snapshot carries the server's clock,
 * so countdowns agree across clients whatever their local time says.
 */
export function usePokerClock(serverTime: Ref<number>) {
  const local = ref(Date.now())
  // Offset measured the moment a snapshot lands (re-measured on every snapshot).
  const offset = computed(() => serverTime.value - Date.now() / 1000)
  let timer: ReturnType<typeof setInterval> | undefined

  onMounted(() => {
    timer = setInterval(() => (local.value = Date.now()), 200)
  })
  onBeforeUnmount(() => clearInterval(timer))

  return computed(() => local.value / 1000 + offset.value)
}
