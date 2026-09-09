<script setup>
const props = defineProps({ items: Array, depth: { type: Number, default: 0 }, disabled: Boolean });
const key = () => `item_${Date.now()}_${Math.random().toString(36).slice(2,7)}`;
const add = () => props.items.push({ key: key(), label: 'Neue Aufgabe', max: 1 });
const children = item => { item.children = [{ key: key(), label: 'Neues Unterkriterium', max: item.max }]; delete item.choices; };
</script>
<template>
  <div class="space-y-2">
    <div v-for="(item,index) in items" :key="item.key" class="rounded border border-gray-200 p-3">
      <div class="flex flex-wrap items-end gap-2">
        <label class="min-w-48 flex-1 text-xs">Aufgabe<input v-model="item.label" :disabled="disabled" class="mt-1 w-full rounded border-gray-300 text-sm" /></label>
        <label class="w-24 text-xs">Max. Punkte<input v-model.number="item.max" type="number" min="0.5" step="0.5" :disabled="disabled" class="mt-1 w-full rounded border-gray-300 text-sm" /></label>
        <button v-if="!disabled && depth < 2 && !item.children" type="button" class="text-xs text-zbb underline" @click="children(item)">Unterkriterien</button>
        <button v-if="!disabled" type="button" class="text-xs text-red-700" @click="items.splice(index,1)">Entfernen</button>
      </div>
      <label v-if="!item.children" class="mt-2 block text-xs text-gray-600">Bewertungsstufen (optional, mit Semikolon trennen; leer = freie Punktzahl)
        <input :value="item.choices?.join('; ') || ''" :disabled="disabled" class="mt-1 w-full rounded border-gray-300 text-sm" @change="e => { const value=e.target.value.trim(); if(value) item.choices=value.split(';').map(x=>Number(x.trim().replace(',','.'))); else delete item.choices; }" />
      </label>
      <ProfileItems v-if="item.children" class="mt-3 ml-3" :items="item.children" :depth="depth+1" :disabled="disabled" />
    </div>
    <button v-if="!disabled" type="button" class="rounded border px-3 py-1 text-sm text-zbb" @click="add">Aufgabe hinzufügen</button>
  </div>
</template>
