<template>
    <div class="fixed inset-0 z-50 flex items-center justify-center bg-black bg-opacity-50">
      <div class="bg-white rounded-lg shadow-lg w-11/12 md:w-1/3">
        <div class="flex justify-between items-center p-4">
          <slot name="header"></slot>
          <button @click="$emit('close')" class="text-gray-500 p-2 hover:text-gray-800">&times;</button>
        </div>
        <div class="border-b-4 w-full border-black"></div>
        <div class="mt-2 p-4">
          <slot name="body"></slot>
        </div>
        <div class="m-4 flex justify-end">
          <slot name="footer"></slot>
        </div>
      </div>
    </div>
  </template>

  <script>
  export default {
    name: 'Modal',
  };
  </script>

  <style scoped>
  /* Stil anpassen, wenn nötig */
  </style>
