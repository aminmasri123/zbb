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
const groupFilter = ref('')
const showModal = ref(false)
const isSubmitting = ref(false)

const formatDate = (value) => {
  if (!value) {
    return '-'
  }

  const [datePart] = String(value).split('T')
  const parts = datePart.split('-')

  if (parts.length === 3) {
    return `${parts[2]}.${parts[1]}.${parts[0]}`
  }

  return value
}

const formatGroupOption = (gruppe) => {
  const bereich = gruppe.bereich?.name || '-'
  const startdatum = formatDate(gruppe.anfangsdatum)
  const enddatum = formatDate(gruppe.enddatum)
  const startzeit = formatTime(gruppe.startzeit)
  const endzeit = formatTime(gruppe.endzeit)
  const betreuer = groupSupervisorName(gruppe) || 'nicht zugewiesen'

  return `${bereich} ${startdatum} bis ${enddatum} von ${startzeit} bis ${endzeit} · Betreuer/Anleiter: ${betreuer}`
}

const groupSupervisorName = (gruppe) =>
  [gruppe.betreuer?.vorname, gruppe.betreuer?.nachname]
    .filter(Boolean)
    .join(' ')

const sortedGruppen = computed(() => {
  return [...props.gruppen].sort((a, b) => {
    const dateA = new Date(a.anfangsdatum || a.created_at || 0).getTime()
    const dateB = new Date(b.anfangsdatum || b.created_at || 0).getTime()

    if (dateA !== dateB) {
      return dateB - dateA
    }

    return Number(b.id || 0) - Number(a.id || 0)
  })
})

const filteredGruppen = computed(() => {
  const search = groupFilter.value.trim().toLocaleLowerCase('de-DE')

  if (!search) {
    return sortedGruppen.value
  }

  return sortedGruppen.value.filter((gruppe) => [
    gruppe.bereich?.name,
    groupSupervisorName(gruppe),
    formatDate(gruppe.anfangsdatum),
    formatDate(gruppe.enddatum),
    formatTime(gruppe.startzeit),
    formatTime(gruppe.endzeit),
  ].filter(Boolean).join(' ').toLocaleLowerCase('de-DE').includes(search))
})

const selectedGroupData = computed(() =>
  props.gruppen.find(gruppe => String(gruppe.id) === String(selectedGroup.value))
)

const open = () => {
  groupFilter.value = ''
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
      @click="open"
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
    <div class="bg-white p-6 rounded-lg shadow-xl w-full max-w-3xl">
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
        <label for="group-filter" class="block mb-1 text-sm font-medium text-gray-700">
          Gruppen filtern
        </label>
        <div class="relative mb-3">
          <i class="fa fa-search pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-gray-400"></i>
          <input
            id="group-filter"
            v-model="groupFilter"
            type="search"
            placeholder="Gruppe, Datum oder Betreuer/Anleiter suchen"
            class="w-full rounded-md border-gray-300 pl-9 focus:border-blue-500 focus:ring-blue-500"
          >
        </div>

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
            v-for="gruppe in filteredGruppen"
            :key="gruppe.id"
            :value="gruppe.id"
          >
            {{ formatGroupOption(gruppe) }}
          </option>
          <option v-if="filteredGruppen.length === 0" value="" disabled>
            Keine passende Gruppe gefunden
          </option>
        </select>

        <div
          v-if="selectedGroupData"
          class="mt-3 rounded-md border border-blue-100 bg-blue-50 px-3 py-2 text-sm"
        >
          <span class="text-gray-600">Betreuer/Anleiter:</span>
          <strong class="ml-1 text-gray-900">
            {{ groupSupervisorName(selectedGroupData) || 'Nicht zugewiesen' }}
          </strong>
        </div>
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
