<script setup>
import { computed, ref, watch } from 'vue'
import Modal from '@/Components/ModalForm.vue'
import axios from 'axios'

const props = defineProps({
  visible: Boolean,
  jahr: String,
  teil: String,
  partnerId: Number,
  klassen: { type: Array, default: () => [] },
  anzahlBereiche: Number,
  teilnehmerCount: { type: Number, default: 0 },
})

const emit = defineEmits(['close'])

const termin = ref('')
const selectedExport = ref('klasse')
const selectedKlasse = ref('')
const roomCount = ref(1)
const raumKapazitaeten = ref([''])
const raumNamen = ref([''])
const loading = ref(false)

const capacitySum = computed(() =>
  raumKapazitaeten.value.reduce((sum, value) => sum + (parseInt(value, 10) || 0), 0)
)

const missingCapacity = computed(() => Math.max(props.teilnehmerCount - capacitySum.value, 0))

const canExport = computed(() => {
  if (!termin.value) return false
  if (selectedExport.value === 'klasse') return Boolean(selectedKlasse.value)
  if (selectedExport.value === 'raeume') {
    return roomCount.value > 0
      && raumNamen.value.every((name) => String(name || '').trim())
      && raumKapazitaeten.value.every((capacity) => (parseInt(capacity, 10) || 0) > 0)
      && capacitySum.value >= props.teilnehmerCount
  }

  return true
})

watch(
  () => props.visible,
  (visible) => {
    if (!visible) return

    termin.value = ''
    selectedExport.value = 'klasse'
    selectedKlasse.value = props.klassen[0] || ''
    roomCount.value = 1
    raumKapazitaeten.value = ['']
    raumNamen.value = ['']
    loading.value = false
  },
  { immediate: true }
)

watch(roomCount, (value) => {
  const count = Math.max(parseInt(value, 10) || 0, 0)
  raumNamen.value = Array.from({ length: count }, (_, index) => raumNamen.value[index] || `Raum ${index + 1}`)
  raumKapazitaeten.value = Array.from({ length: count }, (_, index) => raumKapazitaeten.value[index] || '')
})

function exportUrl() {
  const params = new URLSearchParams({
    termin: termin.value,
    anzahlBereiche: props.anzahlBereiche || 0,
  })

  let klasse = 'exportAlleKlassenZip'

  if (selectedExport.value === 'klasse') {
    klasse = selectedKlasse.value
  }

  if (selectedExport.value === 'raeume') {
    klasse = 'exportAlleKlassen'
    params.set('anzahlRaeumlichkeiten', String(roomCount.value))
    raumNamen.value.forEach((name, index) => params.append(`raumNamen[${index}]`, name))
    raumKapazitaeten.value.forEach((capacity, index) => params.append(`kapazitaeten[${index}]`, capacity || 0))
  }

  return route('anwesenheitsliste.BoTag1.export', {
    partnerID: props.partnerId,
    schuljahr: props.jahr,
    teil: props.teil,
    klasse,
  }) + '?' + params.toString()
}

function filenameFromResponse(response) {
  const disposition = response.headers?.['content-disposition'] || ''
  const match = disposition.match(/filename="?([^"]+)"?/)

  if (match?.[1]) {
    return decodeURIComponent(match[1])
  }

  return selectedExport.value === 'klasse'
    ? `Rolltag_${selectedKlasse.value}.xlsx`
    : 'Rolltag.zip'
}

async function submit() {
  if (!canExport.value || loading.value) return

  loading.value = true

  try {
    const response = await axios.get(exportUrl(), { responseType: 'blob' })
    const url = window.URL.createObjectURL(new Blob([response.data]))
    const link = document.createElement('a')
    link.href = url
    link.download = filenameFromResponse(response)
    document.body.appendChild(link)
    link.click()
    link.remove()
    window.URL.revokeObjectURL(url)
    emit('close')
  } catch (error) {
    console.error('Rolltag-Export fehlgeschlagen:', error)
    alert(await exportErrorMessage(error))
  } finally {
    loading.value = false
  }
}

async function exportErrorMessage(error) {
  const fallback = 'Der Rolltag-Export ist fehlgeschlagen.'
  const data = error.response?.data

  if (data instanceof Blob) {
    const text = await data.text()

    try {
      const json = JSON.parse(text)
      return json.message || json.error || fallback
    } catch {
      return text || fallback
    }
  }

  return data?.message || data?.error || fallback
}
</script>

<template>
  <Modal v-if="visible" @close="$emit('close')">
    <template #header>Rolltag exportieren</template>

    <template #body>
      <div class="space-y-5">
        <div class="rounded border border-orange-200 bg-orange-50 p-4">
          <p class="text-sm text-gray-600">Gesamtzahl Schueler fuer diese Schule und diesen Teil</p>
          <p class="text-2xl font-bold text-zbb">{{ teilnehmerCount }}</p>
        </div>

        <label class="block">
          <span class="mb-2 block text-sm font-medium text-gray-700">Termin <span class="text-red-500">*</span></span>
          <input v-model="termin" type="date" required class="w-full rounded border border-gray-300 p-2 focus:border-zbb focus:ring-zbb" />
        </label>

        <div class="grid gap-3 md:grid-cols-3">
          <label class="cursor-pointer rounded border p-4" :class="selectedExport === 'klasse' ? 'border-zbb bg-orange-50' : 'border-gray-200'">
            <input v-model="selectedExport" type="radio" value="klasse" class="mr-2 text-zbb focus:ring-zbb" />
            <span class="font-semibold text-gray-800">Einzelne Klasse</span>
            <p class="mt-1 text-xs text-gray-500">Eine Excel-Datei fuer die ausgewaehlte Klasse.</p>
          </label>

          <label class="cursor-pointer rounded border p-4" :class="selectedExport === 'klassenZip' ? 'border-zbb bg-orange-50' : 'border-gray-200'">
            <input v-model="selectedExport" type="radio" value="klassenZip" class="mr-2 text-zbb focus:ring-zbb" />
            <span class="font-semibold text-gray-800">Alle Klassen</span>
            <p class="mt-1 text-xs text-gray-500">ZIP-Datei mit einer Liste pro Klasse.</p>
          </label>

          <label class="cursor-pointer rounded border p-4" :class="selectedExport === 'raeume' ? 'border-zbb bg-orange-50' : 'border-gray-200'">
            <input v-model="selectedExport" type="radio" value="raeume" class="mr-2 text-zbb focus:ring-zbb" />
            <span class="font-semibold text-gray-800">Nach Raeumen</span>
            <p class="mt-1 text-xs text-gray-500">Schueler nach Kapazitaeten auf Raeume verteilen.</p>
          </label>
        </div>

        <div v-if="selectedExport === 'klasse'">
          <label class="block">
            <span class="mb-2 block text-sm font-medium text-gray-700">Klasse <span class="text-red-500">*</span></span>
            <select v-model="selectedKlasse" class="w-full rounded border border-gray-300 p-2 focus:border-zbb focus:ring-zbb">
              <option v-for="klasse in klassen" :key="klasse" :value="klasse">{{ klasse }}</option>
            </select>
          </label>
        </div>

        <div v-if="selectedExport === 'raeume'" class="space-y-3 rounded border border-gray-200 p-4">
          <label class="block">
            <span class="mb-2 block text-sm font-medium text-gray-700">Anzahl Raeume</span>
            <input v-model.number="roomCount" type="number" min="1" class="w-full rounded border border-gray-300 p-2 focus:border-zbb focus:ring-zbb" />
          </label>

          <div class="grid gap-2">
            <div v-for="(_, index) in raumKapazitaeten" :key="index" class="grid gap-2 md:grid-cols-2">
              <input v-model="raumNamen[index]" type="text" class="rounded border border-gray-300 p-2" :placeholder="`Raum ${index + 1}`" />
              <input v-model="raumKapazitaeten[index]" type="number" min="1" class="rounded border border-gray-300 p-2" :placeholder="`Kapazitaet Raum ${index + 1}`" />
            </div>
          </div>

          <div class="rounded bg-gray-50 p-3 text-sm text-gray-700">
            <p>Gesamtkapazitaet: <strong>{{ capacitySum }}</strong></p>
            <p v-if="missingCapacity > 0" class="text-red-600">
              Es fehlen noch {{ missingCapacity }} Plaetze fuer alle Schueler.
            </p>
            <p v-else class="text-green-700">Die Kapazitaeten reichen fuer alle Schueler.</p>
          </div>
        </div>

        <div class="flex justify-end gap-2 border-t pt-4">
          <button type="button" class="rounded border px-4 py-2" @click="$emit('close')">Abbrechen</button>
          <button
            type="button"
            :disabled="!canExport || loading"
            class="rounded bg-zbb px-4 py-2 font-semibold text-white disabled:opacity-50"
            @click="submit"
          >
            {{ loading ? 'Exportiere...' : 'Export starten' }}
          </button>
        </div>
      </div>
    </template>
  </Modal>
</template>
