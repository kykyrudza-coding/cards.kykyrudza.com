const base = `${import.meta.env.BASE_URL}blackjack/`
const suitCodes: Record<string, string> = { clubs: 'C', diamonds: 'D', hearts: 'H', spades: 'S' }
// One resolver is the boundary for future skins and a different public asset root.
export const assets = {
  card: (rank: string, suit: string, theme = 'classic') =>
    `${base}cards/${theme}/faces/${rank}${suitCodes[suit] ?? suit}.svg`,
  back: (theme = 'classic') => `${base}cards/${theme}/back/back.svg`,
  table: (skin = 'classic') => `${base}tables/${skin}/table.svg`,
  chip: (value = 25, skin = 'classic') => `${base}chips/${skin}/chip-${value}.svg`,
  audio: (path: string) => `${base}audio/${path}`,
}
