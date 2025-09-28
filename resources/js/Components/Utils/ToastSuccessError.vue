<template>
  <transition name="fade">
    <div
      v-if="toast"
      class="fixed bottom-4 right-4 bg-green-500 text-white px-4 py-2 rounded shadow-lg"
    >
      {{ toast }}
    </div>
  </transition>
</template>

<script setup>
import { ref, watch } from 'vue';
import { usePage } from '@inertiajs/vue3';

const toast = ref(null);

// Page Props von Inertia abrufen
const page = usePage();
const flash = page.props?.flash || {};

// Watch auf flash.success
watch(
  () => flash.success,
  (newValue) => {
    if (newValue) {
      toast.value = newValue;

      // Nach 3 Sekunden ausblenden
      setTimeout(() => {
        toast.value = null;
      }, 3000);
    }
  },
  { immediate: true }
);
</script>

<style>
.fade-enter-active,
.fade-leave-active {
  transition: opacity 0.3s;
}
.fade-enter-from,
.fade-leave-to {
  opacity: 0;
}
</style>
