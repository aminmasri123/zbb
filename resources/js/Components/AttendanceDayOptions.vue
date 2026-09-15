<script setup>
defineProps({ options: { type: Object, required: true }, loading: Boolean, error: String })
defineEmits(['retry'])
</script>
<template>
  <div class="space-y-2 text-sm text-gray-700">
    <p>Standard: Montag bis Freitag, ohne gesetzliche Feiertage im Saarland.</p>
    <div class="flex flex-wrap gap-3">
      <label v-for="[key, label] in [['includeSaturday', 'Samstag'], ['includeSunday', 'Sonntag'], ['includeHolidays', 'Feiertage']]" :key="key" class="inline-flex items-center gap-2">
        <input v-model="options[key]" type="checkbox" class="rounded border-gray-300 text-zbb" />
        {{ label }} einschließen
      </label>
    </div>
    <p v-if="loading" role="status">Feiertagskalender wird geladen …</p>
    <p v-if="error" role="alert" class="text-red-700">{{ error }} <button type="button" class="underline" @click="$emit('retry')">Erneut laden</button></p>
  </div>
</template>
