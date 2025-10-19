<script setup>
import { ref, watch } from 'vue'
import { router, usePage  } from '@inertiajs/vue3'
import Swal from 'sweetalert2'

// Props von der Elternkomponente
const props = defineProps({
  selected: { type: Array, default: () => [] },
  gruppen: { type: Array, default: () => [] }
})
const emit = defineEmits(['submitted'])

const selectedGroup = ref('')
const showModal = ref(false)
console.log(props.gruppen);

watch(
  () => props.selected,
  (newVal) => {
    console.log('Neue Auswahl:', newVal)
  },
  { deep: true, immediate: true }
);

 function submitForm() {
  if (!selectedGroup.value || props.selected.length === 0) {
    Swal.fire({
      title: 'Fehler',
      text: 'Bitte wähle mindestens eine Gruppe und einen Teilnehmer.',
      icon: 'error'
    })
    return
  } 

  router.post(
    route('gruppeHasTeilnehmer.store'),
    { gruppe_id: selectedGroup.value, teilnehmer: props.selected },
    {
      onSuccess: () => {
        const flash = usePage().props.flash
        console.log(props);
        if (flash.success) {
        Swal.fire({ icon: 'success', text: flash.success, timer: 2500 })
      } else if (flash.warning) {
        Swal.fire({ icon: 'warning', text: flash.warning, timer: 2500 })
      } else if (flash.info) {
        Swal.fire({ icon: 'info', text: flash.info, timer: 2500 })
      }
        emit('submitted')
        showModal.value = false
      }
    }
  )
}
</script>

<template>
  <div class="mt-4">
    <button
      type="button"
      v-if="selected.length > 0"
      @click="showModal = true"
      class="w-full bg-blue-600 text-white py-2 rounded-md hover:bg-blue-700 transition"
    >
      <i class="fa fa-plus mr-1"></i>
      {{ $t('zur Gruppe hinzufügen') }}
    </button>
  </div>

  <!-- Modal -->
  <div
    v-if="showModal"
    class="fixed inset-0 flex items-center justify-center bg-black/50 z-50"
  >
    <div class="bg-white p-6 rounded-lg shadow-xl w-full max-w-lg">
      <div class="flex justify-between items-center mb-4">
        <h2 class="text-lg font-semibold">
          {{ $t('zur gruppe hinzufügen') }}
        </h2>
        <button
          @click="showModal = false"
          class="text-gray-500 hover:text-gray-700 text-xl"
        >
          &times;
        </button>
      </div>

      <div class="mb-4">
        <label class="block mb-1">
          {{ $t('gruppe') }} <span class="text-red-500">*</span>
        </label>
        <select
          v-model="selectedGroup"
          class="w-full border-gray-300 rounded-md focus:ring-blue-500 focus:border-blue-500"
        >
          <option value="" disabled>
            {{ $t('Gruppe wählen') }}
          </option>
          <option
            v-for="gruppe in gruppen"
            :key="gruppe.id"
            :value="gruppe.id"
          >
            {{ gruppe.bereich.name }} - {{ gruppe.anfangsdatum }} -> {{ gruppe.enddatum }} {{$t('von') }} {{ gruppe.startzeit }} {{$t('bis') }} {{ gruppe.endzeit }}
          </option>
        </select>
      </div>

      <div class="text-right">
        <button
          @click="submitForm"
          class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700"
        >
          {{ $t('speichern') }}
        </button>
      </div>
    </div>
  </div>
</template>
