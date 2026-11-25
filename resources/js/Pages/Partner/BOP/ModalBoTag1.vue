<script setup>
import { computed, ref, watch } from 'vue'
import Modal from '@/Components/ModalForm.vue'
import axios from 'axios'

const props = defineProps({
  visible: Boolean,
  jahr: String,
  teil: String,
  partnerId: Number,
})
const emit = defineEmits(['close'])

const configuration = ref(null)
const preparing = ref(true)
const errorMessage = ref('')
const termin = ref('')
const selectedExport = ref('klasse')
const selectedKlasse = ref('')
const roomCount = ref(1)
const raumKapazitaeten = ref([''])
const raumNamen = ref(['Raum 1'])
const loading = ref(false)

const klassen = computed(() => configuration.value?.classes || [])
const plannedGroups = computed(() => Boolean(configuration.value?.has_groups))
const plannedDates = computed(() => {
  const dates = configuration.value?.dates || []
  const assigned = configuration.value?.class_dates?.[selectedKlasse.value]
  return selectedExport.value === 'klasse' && assigned
    ? dates.filter((date) => assigned.includes(date))
    : dates
})
const teilnehmerCount = computed(() =>
  configuration.value?.counts_by_date?.[termin.value] ?? configuration.value?.count ?? 0
)
const capacitySum = computed(() =>
  raumKapazitaeten.value.reduce((sum, value) => sum + (Number(value) || 0), 0)
)
const missingCapacity = computed(() => Math.max(teilnehmerCount.value - capacitySum.value, 0))

const canExport = computed(() => {
  if (preparing.value || !configuration.value || !termin.value || !teilnehmerCount.value) return false
  if (configuration.value.has_schedule && !plannedDates.value.includes(termin.value)) return false
  if (selectedExport.value === 'klasse') return Boolean(selectedKlasse.value)
  if (selectedExport.value !== 'raeume' || plannedGroups.value) return true

  const names = raumNamen.value.map((name) => name.trim().toLowerCase())
  return Number.isInteger(roomCount.value) && roomCount.value >= 1 && roomCount.value <= 50
    && names.length === roomCount.value && names.every(Boolean) && new Set(names).size === names.length
    && raumKapazitaeten.value.every((capacity) => Number.isInteger(capacity) && capacity > 0)
    && missingCapacity.value === 0
})

function endpoint(klasse) {
  return route('anwesenheitsliste.BoTag1.export', {
    partnerID: props.partnerId,
    schuljahr: props.jahr,
    teil: props.teil,
    klasse,
  })
}

watch(plannedDates, (dates) => {
  if (configuration.value?.has_schedule && !dates.includes(termin.value)) {
    termin.value = dates[0] || ''
  }
})

watch(() => [props.visible, props.partnerId, props.jahr, props.teil], async ([visible], _, onCleanup) => {
  if (!visible) return
  const controller = new AbortController()
  onCleanup(() => controller.abort())
  preparing.value = true
  configuration.value = null
  errorMessage.value = ''
  termin.value = ''
  roomCount.value = 1
  raumNamen.value = ['Raum 1']
  raumKapazitaeten.value = ['']

  try {
    const { data } = await axios.get(endpoint('exportAlleKlassenZip'), {
      params: { dialog: 1, preview: 1 },
      headers: { Accept: 'application/json' },
      signal: controller.signal,
    })
    if (!Array.isArray(data?.classes) || !Array.isArray(data?.dates)) throw new Error('Ungültige Exportdaten')
    configuration.value = data
    selectedKlasse.value = data.classes[0] || ''
    selectedExport.value = selectedKlasse.value ? 'klasse' : 'klassenZip'
    termin.value = plannedDates.value[0] || ''
    if (!data.count) errorMessage.value = 'Für diese Auswahl wurden keine Teilnehmer gefunden.'
  } catch (error) {
    if (!controller.signal.aborted) {
      errorMessage.value = error.response?.data?.message || 'Die Rolltag-Daten konnten nicht geladen werden.'
    }
  } finally {
    if (!controller.signal.aborted) preparing.value = false
  }
}, { immediate: true })

watch(roomCount, (value) => {
  const count = Math.min(50, Math.max(Math.floor(value) || 0, 0))
  raumNamen.value = Array.from({ length: count }, (_, index) => raumNamen.value[index] ?? `Raum ${index + 1}`)
  raumKapazitaeten.value = Array.from({ length: count }, (_, index) => raumKapazitaeten.value[index] ?? '')
})

async function submit() {
  if (!canExport.value || loading.value) return
  loading.value = true
  errorMessage.value = ''
  try {
    const mode = selectedExport.value
    let klasse = 'exportAlleKlassenZip'
    const params = new URLSearchParams({ dialog: '1', termin: termin.value })
    if (mode === 'klasse') klasse = selectedKlasse.value
    if (mode === 'raeume') {
      klasse = 'exportAlleKlassen'
      if (!plannedGroups.value) {
        params.set('anzahlRaeumlichkeiten', String(roomCount.value))
        raumNamen.value.forEach((name, index) => params.append(`raumNamen[${index}]`, name.trim()))
        raumKapazitaeten.value.forEach((capacity, index) => params.append(`kapazitaeten[${index}]`, capacity))
      }
    }

    const response = await axios.get(endpoint(klasse), {
      params,
      responseType: 'blob',
      headers: { Accept: 'application/json' },
    })
    const disposition = response.headers?.['content-disposition'] || ''
    const signature = new Uint8Array(await response.data.slice(0, 4).arrayBuffer())
    if (!/^attachment\b/i.test(disposition) || signature.join(',') !== '80,75,3,4') {
      throw new Error('Ungültige Exportdatei')
    }
    const url = URL.createObjectURL(response.data)
    const link = document.createElement('a')
    link.href = url
    link.download = disposition.match(/filename="?([^";]+)"?/i)?.[1] ||
      (mode === 'klasse' ? 'Rolltag.xlsx' : 'Rolltag.zip')
    document.body.appendChild(link)
    link.click()
    link.remove()
    window.setTimeout(() => URL.revokeObjectURL(url), 1000)
    emit('close')
  } catch (error) {
    const data = error.response?.data
    let message = data?.message
    if (data instanceof Blob) {
      try {
        const body = JSON.parse(await data.text())
        message = Object.values(body.errors || {}).flat()[0] || body.message
      } catch {
        message = null
      }
    }
    errorMessage.value = message || 'Der Rolltag-Export ist fehlgeschlagen. Es wurde keine gültige Exportdatei empfangen.'
  } finally {
    loading.value = false
  }
}
</script>


<template>
  <Modal v-if="visible" @close="$emit('close')">
    <template #header>Rolltag exportieren</template>

    <template #body>
      <div class="space-y-5">
        <p v-if="preparing" role="status">Rolltag-Daten werden geladen …</p>
        <p v-if="errorMessage" role="alert" class="rounded border border-red-200 bg-red-50 p-3 text-sm text-red-800">{{ errorMessage }}</p>
        <div v-if="configuration" class="rounded border border-orange-200 bg-orange-50 p-4">
          <p class="text-sm text-gray-600">{{ plannedDates.length ? 'Schüler am gewählten Termin (alle Klassen)' : 'Schüler für diese Schule und diesen Teil' }}</p>
          <p class="text-2xl font-bold text-zbb">{{ teilnehmerCount }}</p>
        </div>

        <label class="block">
          <span class="mb-2 block text-sm font-medium text-gray-700">Termin <span class="text-red-500">*</span></span>
          <select v-if="plannedDates.length" v-model="termin" class="w-full rounded border-gray-300">
            <option v-for="date in plannedDates" :key="date" :value="date">{{ date.split('-').reverse().join('.') }}</option>
          </select>
          <p v-else-if="configuration?.has_schedule" class="text-sm text-gray-600">Für diese Auswahl ist kein Rolltag-Termin geplant.</p>
          <input v-else v-model="termin" type="date" required :disabled="preparing" class="w-full rounded border border-gray-300 p-2 focus:border-zbb focus:ring-zbb" />
        </label>

        <div class="grid gap-3 md:grid-cols-3">
          <label class="cursor-pointer rounded border p-4" :class="selectedExport === 'klasse' ? 'border-zbb bg-orange-50' : 'border-gray-200'">
            <input v-model="selectedExport" type="radio" value="klasse" :disabled="!klassen.length" class="mr-2 text-zbb focus:ring-zbb" />
            <span class="font-semibold text-gray-800">Einzelne Klasse</span>
            <p class="mt-1 text-xs text-gray-500">Eine Excel-Datei für die ausgewählte Klasse.</p>
          </label>

          <label class="cursor-pointer rounded border p-4" :class="selectedExport === 'klassenZip' ? 'border-zbb bg-orange-50' : 'border-gray-200'">
            <input v-model="selectedExport" type="radio" value="klassenZip" class="mr-2 text-zbb focus:ring-zbb" />
            <span class="font-semibold text-gray-800">Alle Klassen</span>
            <p class="mt-1 text-xs text-gray-500">ZIP-Datei mit einer Liste pro Klasse.</p>
          </label>

          <label class="cursor-pointer rounded border p-4" :class="selectedExport === 'raeume' ? 'border-zbb bg-orange-50' : 'border-gray-200'">
            <input v-model="selectedExport" type="radio" value="raeume" class="mr-2 text-zbb focus:ring-zbb" />
            <span class="font-semibold text-gray-800">{{ plannedGroups ? 'Geplante Gruppen' : 'Nach Räumen' }}</span>
            <p class="mt-1 text-xs text-gray-500">ZIP-Datei mit Anwesenheitslisten und Einteilung.</p>
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

        <div v-if="selectedExport === 'raeume' && !plannedGroups" class="space-y-3 rounded border border-gray-200 p-4">
          <label class="block">
            <span class="mb-2 block text-sm font-medium text-gray-700">Anzahl Räume</span>
            <input v-model.number="roomCount" type="number" min="1" max="50" class="w-full rounded border border-gray-300 p-2 focus:border-zbb focus:ring-zbb" />
          </label>

          <div class="grid gap-2">
            <div v-for="(_, index) in raumKapazitaeten" :key="index" class="grid gap-2 md:grid-cols-2">
              <input v-model="raumNamen[index]" type="text" class="rounded border border-gray-300 p-2" :placeholder="`Raum ${index + 1}`" />
              <input v-model.number="raumKapazitaeten[index]" type="number" min="1" class="rounded border border-gray-300 p-2" :placeholder="`Kapazität Raum ${index + 1}`" />
            </div>
          </div>

          <div class="rounded bg-gray-50 p-3 text-sm text-gray-700">
            <p>Gesamtkapazität: <strong>{{ capacitySum }}</strong></p>
            <p v-if="missingCapacity > 0" class="text-red-600">
              Es fehlen noch {{ missingCapacity }} Plätze für alle Schüler.
            </p>
            <p v-else class="text-green-700">Die Kapazitäten reichen für alle Schüler.</p>
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
