<script setup>
import { computed, ref } from 'vue'
import Swal from 'sweetalert2'
import axios from 'axios'
import { formatTime } from '@/utils/timeFormat'

// Props von der Elternkomponente
const props = defineProps({
  selected: { type: Array, default: () => [] },
  gruppen: { type: Array, default: () => [] },
  showButton: { type: Boolean, default: true }
})
const emit = defineEmits(['submitted'])

const selectedGroup = ref('')
const showModal = ref(false)
const isSubmitting = ref(false)

const selectedGroupData = computed(() =>
  props.gruppen.find(gruppe => String(gruppe.id) === String(selectedGroup.value))
)

const open = () => {
  showModal.value = true
}

defineExpose({ open })

 async function submitForm() {
  if (isSubmitting.value) {
    return
  }

  if (!selectedGroup.value || props.selected.length === 0) {
    Swal.fire({
      title: 'Fehler',
      text: 'Bitte wähle mindestens eine Gruppe und einen Teilnehmer.',
      icon: 'error'
    })
    return
  } 

  isSubmitting.value = true

  try {
    const response = await axios.post(route('gruppeHasTeilnehmer.store'), {
      gruppe_id: selectedGroup.value,
      teilnehmer: props.selected,
      startzeit: formatTime(selectedGroupData.value?.startzeit),
      endzeit: formatTime(selectedGroupData.value?.endzeit),
      startdatum: selectedGroupData.value?.anfangsdatum,
      enddatum: selectedGroupData.value?.enddatum,
    })

    Swal.fire({
      icon: 'success',
      text: response.data.message || 'Teilnehmer wurden zur Gruppe hinzugefuegt.',
      timer: 2500
    })
    emit('submitted')
    showModal.value = false
  } catch (error) {
    Swal.fire({
      title: 'Fehler',
      text: error.response?.data?.message || 'Teilnehmer konnten nicht zur Gruppe hinzugefuegt werden.',
      icon: 'error'
    })
  } finally {
    isSubmitting.value = false
  }
}
</script>

<template>
  <div class="mt-4">
    <button
      type="button"
      v-if="showButton && selected.length > 0"
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
          {{ $t('zur Gruppe hinzufügen') }}
        </h2>
        <button
          @click="showModal = false"
          :disabled="isSubmitting"
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
          :disabled="isSubmitting"
          class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700 disabled:cursor-not-allowed disabled:opacity-60"
        >
          {{ isSubmitting ? $t('speichern') + ' ...' : $t('speichern') }}
        </button>
      </div>
    </div>
  </div>
</template>
