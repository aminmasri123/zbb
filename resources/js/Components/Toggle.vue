<template>
  <div class="flex w-full items-center justify-between gap-4">
    <label :for="id" class="min-w-0">
      <span class="block text-sm font-medium text-gray-800 leading-6">
        {{ label }}
      </span>
      <span v-if="hint" class="block text-xs text-gray-500">
        {{ hint }}
      </span>
    </label>

    <button
      type="button"
      :id="id"
      role="switch"
      :aria-checked="modelValue ? 'true' : 'false'"
      @click="$emit('update:modelValue', !modelValue)"
      class="relative inline-flex h-8 w-20 flex-shrink-0 cursor-pointer rounded-full transition-colors duration-300 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-zbb"
      :class="modelValue ? 'bg-green-600' : 'bg-gray-300'"
    >
      <span
        class="pointer-events-none absolute left-1 top-1 inline-flex h-6 w-6 items-center justify-center rounded-full bg-white shadow transition-transform duration-300"
        :class="modelValue ? 'translate-x-12' : ''"
      >
        <span v-if="!modelValue">❌</span>
        <span v-else>✅</span>
      </span>
      <span class="sr-only">{{ label }}</span>
    </button>
  </div>
</template>




<script setup>
import { computed } from 'vue';

const props = defineProps({
  modelValue: { type: Boolean, default: false },
  label: { type: String, required: true },
  hint: { type: String, default: '' },
  id: { type: String, default: () => `tgl-${Math.random().toString(36).slice(2, 9)}` }
});

defineEmits(['update:modelValue']);
</script>
