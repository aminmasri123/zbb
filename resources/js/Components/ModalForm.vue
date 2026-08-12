<template>
    <div class="fixed inset-0 z-50 flex items-center justify-center bg-black bg-opacity-50 p-2 backdrop-blur-sm sm:p-4">
      <div
        class="w-full rounded-lg bg-white shadow-lg"
        :class="scrollableLayout
          ? 'flex max-h-[calc(100dvh-1rem)] max-w-3xl flex-col overflow-hidden sm:max-h-[calc(100dvh-2rem)]'
          : 'max-h-[calc(100vh-2rem)] max-w-2xl overflow-y-auto px-4 py-4 sm:px-8 lg:w-1/3'"
      >
        <div class="flex items-center justify-between" :class="scrollableLayout ? 'shrink-0 px-4 pt-3 sm:px-6 sm:pt-4' : ''">
          <slot name="header"></slot>
          <button type="button" @click="$emit('close')" class="rounded p-2 text-gray-500 hover:bg-gray-100 hover:text-gray-800" aria-label="Modal schließen"><i class="la la-lg la-times"></i></button>
        </div>
        <div class="w-full border-b-4 border-zbb" :class="scrollableLayout ? 'mt-1 shrink-0' : 'mb-10'"></div>
        <div :class="scrollableLayout ? 'min-h-0 flex-1 overflow-y-auto overscroll-contain px-4 py-4 sm:px-6' : 'mt-2'">
          <slot name="body"></slot>
        </div>
        <div
          class="flex flex-wrap justify-center gap-3"
          :class="scrollableLayout ? 'shrink-0 border-t border-gray-200 bg-white px-4 py-3 sm:justify-end sm:px-6' : 'm-4'"
        >
          <slot name="footer"></slot>
        </div>
      </div>
    </div>
  </template>

  <script>
  export default {
    name: 'Modal',
    props: {
      scrollableLayout: {
        type: Boolean,
        default: false,
      },
    },
  };
  </script>

  <style scoped>
  /* Stil anpassen, wenn nötig */
  </style>
