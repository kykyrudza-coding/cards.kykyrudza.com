import { assets } from '../config/assets'
import { usePreferencesStore } from '../stores/preferences'
const images = new Map<string, Promise<void>>()
export function prepareCardImage(src: string): Promise<void> {
  const existing = images.get(src)
  if (existing) return existing
  const image = new Image()
  image.src = src
  const promise = image.decode().catch(() => {
    images.delete(src)
  })
  images.set(src, promise)
  return promise
}
export function preloadCardImages(theme = usePreferencesStore().cardTheme) {
  return Promise.all(
    ['C', 'D', 'H', 'S']
      .flatMap((suit) =>
        ['A', '2', '3', '4', '5', '6', '7', '8', '9', '10', 'J', 'Q', 'K'].map((rank) =>
          prepareCardImage(assets.card(rank, suit, theme)),
        ),
      )
      .concat(prepareCardImage(assets.back(theme))),
  )
}
