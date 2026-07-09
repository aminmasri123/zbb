<template>
    <div class="fixed inset-0 z-50 flex items-center justify-center bg-black bg-opacity-50 p-4 backdrop-blur-sm">
      <div class="max-h-[calc(100vh-2rem)] w-full max-w-2xl overflow-y-auto rounded-lg bg-white px-4 py-4 shadow-lg sm:px-8 lg:w-1/3">
        <div class="flex justify-between items-center ">
          <slot name="header"></slot>
          <button @click="$emit('close')" class="text-gray-500 p-2 hover:text-gray-800"><i class="la la-lg la-times"></i></button>
        </div>
        <div class="border-b-4 w-full border-zbb mb-10"></div>
        <div class="mt-2">
          <slot name="body"></slot>
        </div>
        <div class="m-4 flex flex-wrap justify-center gap-3">
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
