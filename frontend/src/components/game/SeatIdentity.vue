<script setup lang="ts">
import AppAvatar from '../ui/AppAvatar.vue'

// The one name-plate shared by every game: avatar (with the turn ring), name,
// a secondary line (chips / card count) and an optional turn tag.
defineProps<{
  name: string
  avatar?: string | null
  /** Small "you" marker next to the name. */
  youLabel?: string
  /** Tag pinned to the bottom edge, e.g. "Your turn". */
  tag?: string
  /** Pulsing corner dot (the player to act). */
  dot?: boolean
}>()
</script>
<template>
  <div class="seat-identity">
    <AppAvatar :name="name" :src="avatar" />
    <div class="seat-name">
      <strong
        >{{ name }}<span v-if="youLabel" class="you-label">{{ youLabel }}</span></strong
      >
      <span><slot /></span>
    </div>
    <span v-if="tag" class="turn-label">{{ tag }}</span>
    <span v-if="dot" class="poker-turn-dot" aria-hidden="true" />
  </div>
</template>
