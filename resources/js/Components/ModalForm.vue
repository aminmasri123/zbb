<template>
    <div class="fixed inset-0 z-50 flex items-center justify-center bg-black bg-opacity-50 backdrop-blur-sm">
      <div class="bg-white rounded-lg shadow-lg w-11/12 sm:w-1/3 px-8 py-4">
        <div class="flex justify-between items-center ">
          <slot name="header"></slot>
          <button @click="$emit('close')" class="text-gray-500 p-2 hover:text-gray-800"><i class="la la-lg la-times"></i></button>
        </div>
        <div class="border-b-4 w-full border-zbb mb-10"></div>
        <div class="mt-2">
          <slot name="body"></slot>
        </div>
        <div class="m-4 flex justify-center space-x-16">
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
