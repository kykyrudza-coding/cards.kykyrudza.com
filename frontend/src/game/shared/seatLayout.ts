import type { BlackjackPlayerView } from '../../types/match'
// Presentation only: rotate the server's seat order around the viewer, then
// distribute opponents along the two table edges. No game/turn rules belong here.
export function arrangeSeats(players: BlackjackPlayerView[], viewerId?: number) {
  const ordered = [...players].sort((a, b) => a.seat - b.seat)
  const viewerIndex = ordered.findIndex((player) => player.id === viewerId)
  const rotated =
    viewerIndex < 0
      ? ordered
      : [...ordered.slice(viewerIndex + 1), ...ordered.slice(0, viewerIndex)]
  return rotated.map((player, index) => {
    const leftCount = Math.ceil(rotated.length / 2)
    const left = index < leftCount
    const sideCount = left ? leftCount : rotated.length - leftCount
    const row = left ? index : index - leftCount
    const top = sideCount === 1 ? 42 : 18 + (row * 64) / (sideCount - 1)
    return { player, style: { left: left ? '17%' : '83%', top: `${top}%` } }
  })
}
