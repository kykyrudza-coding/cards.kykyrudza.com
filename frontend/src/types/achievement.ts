// Display text (name/description) is resolved client-side from
// achievements.catalog.<key> in the locale files — the backend only tracks
// which stable keys exist and when the current user unlocked each.
export interface Achievement {
  key: string
  icon: string
  unlocked_at: string | null
}
