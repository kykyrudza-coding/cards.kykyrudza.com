// Presentation only: rotate the server's seat order around the viewer, then
// distribute opponents along the two table edges. No game/turn rules belong here.
// Generic over any player shape with an id/seat — shared by Blackjack and Durak.
// With `clockwise`, turn order reads around the table: the next player after the
// viewer sits bottom-left, then up the left edge and down the right edge.
export function arrangeSeats<T extends { id: number; seat: number }>(
  players: T[],
  viewerId?: number,
  clockwise = false,
) {
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
    const rawRow = left ? index : index - leftCount
    const row = clockwise && left ? sideCount - 1 - rawRow : rawRow
    const top = sideCount === 1 ? 42 : 18 + (row * 64) / (sideCount - 1)
    return { player, style: { left: left ? '17%' : '83%', top: `${top}%` } }
  })
}
