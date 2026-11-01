<script setup lang="ts">
import { computed } from 'vue'
import { assets } from '../../config/assets'

// Decorative: turns an amount into a few columns of chips (100 / 25 / 5),
// so a bet reads at a glance. The exact number is always shown next to it.
const props = withDefaults(
  defineProps<{ amount: number; chipSkin?: string; maxHeight?: number }>(),
  { chipSkin: 'classic', maxHeight: 5 },
)
const columns = computed(() => {
  let rest = Math.max(0, Math.round(props.amount))
  const result: { key: string; value: number; count: number }[] = []
  for (const value of [100, 25, 5]) {
    let count = Math.floor(rest / value)
    rest -= count * value
    // A tall column becomes several shorter ones, so big pots look wide, not like a tower.
    for (let i = 0; count > 0; i++) {
      const height = Math.min(count, props.maxHeight)
      result.push({ key: `${value}-${i}`, value, count: height })
      count -= height
    }
  }
  if (!result.length && props.amount > 0) result.push({ key: '5-0', value: 5, count: 1 })
  return result.slice(0, 4)
})
</script>
<template>
  <span class="chip-pile" aria-hidden="true">
    <span v-for="column in columns" :key="column.key" class="chip-column">
      <img
        v-for="n in column.count"
        :key="n"
        :src="assets.chip(column.value, chipSkin)"
        alt=""
        draggable="false"
        :style="{ bottom: `${(n - 1) * 3}px`, zIndex: n }"
      />
    </span>
  </span>
</template>
