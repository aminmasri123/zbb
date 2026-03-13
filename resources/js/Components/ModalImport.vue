<template>
  <div v-if="show" class="fixed inset-0 flex items-center justify-center bg-black bg-opacity-50 z-50">
    <div class="bg-white p-6 rounded-lg w-1/2">

      <!-- Header -->
      <div class="flex justify-between mb-4">
        <h2 class="text-lg font-bold">{{ title }}</h2>
        <button @click="$emit('close')">✕</button>
      </div>

      <!-- Dropzone -->
      <form ref="dropzoneForm" class="dropzone border border-dashed p-6 rounded-lg"></form>

    </div>
  </div>
</template>

<script setup>
import { ref, watch, nextTick } from 'vue'
import Dropzone from 'dropzone'
import 'dropzone/dist/dropzone.css'

const props = defineProps({
  show: { type: Boolean, default: false },
  seite: { type: String, default: 'Importieren' }
})

const dropzoneForm = ref(null)
let dropzoneInstance = null

// Watch auf show
watch(() => props.show, async (newVal) => {
  if (newVal) {
    await nextTick() // wartet bis DOM gerendert
    initDropzone()
  } else {
    destroyDropzone()
  }
})

const initDropzone = () => {
  if (!dropzoneForm.value) return

  if (dropzoneInstance) {
    dropzoneInstance.destroy()
    dropzoneInstance = null
  }

  dropzoneInstance = new Dropzone(dropzoneForm.value, {
    url: '/import/' + props.seite.toLowerCase(), // Beispiel-URL, anpassen je nach Bedarf
     headers: {
        "X-CSRF-TOKEN": document.querySelector('meta[name="csrf-token"]').getAttribute("content")
    },
    maxFilesize: 5,
    acceptedFiles: '.csv,.xlsx,.xls',
    addRemoveLinks: true,
    dictDefaultMessage: 'Datei hierher ziehen oder klicken',
  })
}

const destroyDropzone = () => {
  if (dropzoneInstance) {
    dropzoneInstance.destroy()
    dropzoneInstance = null
  }
}
</script>
