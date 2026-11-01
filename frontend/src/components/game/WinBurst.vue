<script setup lang="ts">
// A short burst of gold sparks for a winning seat/hand. Pure decoration:
// the particle paths are fixed (no randomness) so renders stay stable.
const sparks = Array.from({ length: 18 }, (_, i) => {
  const angle = (i / 18) * Math.PI * 2 + (i % 2) * 0.17
  const distance = 52 + (i % 3) * 22
  return {
    x: Math.round(Math.cos(angle) * distance),
    y: Math.round(Math.sin(angle) * distance * 0.8),
    delay: (i % 6) * 0.05,
    size: 4 + (i % 3) * 2,
    hue: i % 3,
  }
})
</script>
<template>
  <span class="win-burst" aria-hidden="true">
    <i
      v-for="(spark, index) in sparks"
      :key="index"
      :data-hue="spark.hue"
      :style="{
        '--x': `${spark.x}px`,
        '--y': `${spark.y}px`,
        '--delay': `${spark.delay}s`,
        '--size': `${spark.size}px`,
      }"
    />
  </span>
</template>
