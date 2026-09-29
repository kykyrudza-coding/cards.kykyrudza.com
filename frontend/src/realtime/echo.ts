import Echo from 'laravel-echo'
import Pusher from 'pusher-js'
import { getToken } from '../services/api'

declare global {
  interface Window {
    Pusher: typeof Pusher
  }
}

window.Pusher = Pusher

const API_URL = import.meta.env.VITE_API_URL as string
const WS_HOST = import.meta.env.VITE_WS_HOST as string
const WS_PORT = Number(import.meta.env.VITE_WS_PORT)
const WS_SCHEME = (import.meta.env.VITE_WS_SCHEME as string | undefined) ?? 'http'
const FORCE_TLS = WS_SCHEME === 'https'
const REVERB_APP_KEY = import.meta.env.VITE_REVERB_APP_KEY as string

let echo: Echo<'reverb'> | null = null

export function connectEcho(): Echo<'reverb'> {
  if (echo) {
    return echo
  }

  echo = new Echo({
    broadcaster: 'reverb',
    key: REVERB_APP_KEY,
    wsHost: WS_HOST,
    wsPort: WS_PORT,
    wssPort: WS_PORT,
    forceTLS: FORCE_TLS,
    enabledTransports: ['ws', 'wss'],
    authEndpoint: `${API_URL}/broadcasting/auth`,
    bearerToken: getToken(),
  })

  return echo
}

export function disconnectEcho(): void {
  echo?.disconnect()
  echo = null
}
