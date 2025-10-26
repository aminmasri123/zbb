<script setup>
import { ref, computed, onMounted } from 'vue'
import { Head } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import InputText from 'primevue/inputtext'
import FloatLabel from 'primevue/floatlabel';
import Select from 'primevue/select';
import MultiSelect from 'primevue/multiselect'
import Dialog from 'primevue/dialog'
import { router } from '@inertiajs/vue3'
import Swal from 'sweetalert2';
import { formatTime } from '@/utils/timeFormat'


// --- Statusarten ---
const statusArten = [
  { name: 'anwesend', color: 'bg-green-500' },
  { name: 'krank', color: 'bg-yellow-400' },
  { name: 'entschuldigt', color: 'bg-blue-400' },
  { name: 'unentschuldigt', color: 'bg-red-500' },
  { name: 'urlaub', color: 'bg-purple-500' },
  { name: 'feiertag', color: 'bg-gray-400' },
]

// --- Props ---
const props = defineProps({
  gruppe: { type: Object, required: true },
  teilnehmer: { type: Array, required:true},
})


// Modal-Steuerung + Auswahl
const showTeilnehmerModal = ref(false)
const selectedTeilnehmerIds = ref([])


// Funktion, um nach Klick auf „Übernehmen“ die ausgewählten Teilnehmer hinzuzufügen
const confirmTeilnehmer = async () => {
  if (selectedTeilnehmerIds.value.length === 0) {
    Swal.fire({
      icon: 'warning',
      title: 'Keine Auswahl',
      text: 'Bitte wähle mindestens einen Teilnehmer aus.',
    })
    return
  }

  try {
    const response = await axios.post('/gruppehasteilnehmer/anlegen', {
      gruppe_id: props.gruppe.id,
      teilnehmer: selectedTeilnehmerIds.value,
    })

    const data = response.data

    if (data.success) {
      // 🔹 Namen der bereits vorhandenen Teilnehmer formatieren
      let alreadyNames = ''
      if (data.already && data.already.length > 0) {
        alreadyNames = data.already.map(t => `${t.vorname} ${t.nachname}`).join(', ')
      }

      // 🔹 Namen der neu hinzugefügten Teilnehmer formatieren
      let addedNames = ''
      if (data.added && data.added.length > 0) {
        addedNames = data.added.map(t => `${t.vorname} ${t.nachname}`).join(', ')
      }

      // ✅ SweetAlert mit beiden Informationen
      let message = ''
      if (addedNames) message += `✅ Hinzugefügt: ${addedNames}\n`
      if (alreadyNames) message += `⚠️ Bereits vorhanden: ${alreadyNames}`

      Swal.fire({
        icon: 'success',
        title: 'Teilnehmer aktualisiert',
        text: message || data.message,
        confirmButtonText: 'OK',
      })

      // Modal schließen & Auswahl zurücksetzen
      showTeilnehmerModal.value = false
      selectedTeilnehmerIds.value = []

      // Lokale Tabelle aktualisieren
      if (data.added && Array.isArray(data.added)) {
        data.added.forEach(nt => {
          const existiert = teilnehmer.value.some(t => t.id === nt.id)
          if (!existiert) {
            teilnehmer.value.push({
              ...nt,
              anwesenheit: tage.value.map(() => 'unentschuldigt'),
            })
          }
        })
      }
    }
  } catch (error) {
    console.error(error)
    Swal.fire({
      icon: 'error',
      title: 'Fehler',
      text: error.response?.data?.message || 'Teilnehmer konnten nicht hinzugefügt werden.',
    })
  }
}




console.log(props.teilnehmer)
// --- Datumsbereich (inkl. Enddatum) ---
function generateDateRangeInclusive(start, end) {
  const result = []
  const startDate = new Date(start + 'T00:00:00')
  const endDate = new Date(end + 'T00:00:00')

  let current = new Date(startDate)
  let index = 1

  while (current <= endDate) {
    const weekday = current.toLocaleDateString('de-DE', { weekday: 'short' })
    const day = current.toLocaleDateString('de-DE', { day: '2-digit', month: '2-digit' })

    // Formatierung OHNE Zeitzonen-Verschiebung
    const localDate = [
      current.getFullYear(),
      String(current.getMonth() + 1).padStart(2, '0'),
      String(current.getDate()).padStart(2, '0'),
    ].join('-')

    result.push({
      label: `Tag ${index}`,
      datum: `${weekday}, ${day}.`,
      date: localDate, // garantiert lokales YYYY-MM-DD
    })

    current.setDate(current.getDate() + 1)
    index++
  }

  return result
}


const tage = computed(() =>
  props.gruppe?.anfangsdatum && props.gruppe?.enddatum
    ? generateDateRangeInclusive(props.gruppe.anfangsdatum, props.gruppe.enddatum)
    : []
)

// --- Teilnehmer vorbereiten ---
const teilnehmer = ref([])

onMounted(() => {
  teilnehmer.value = props.gruppe.teilnehmer.map(t => ({
    ...t,
    // Für jeden Tag prüfen, ob Anwesenheit vorhanden ist, sonst "unentschuldigt"
    anwesenheit: tage.value.map(tag => {
      const eintrag = t.anwesenheiten?.find(a => a.datum === tag.date)
      return eintrag ? eintrag.status : 'unentschuldigt'
    }),
  }))
})

const neuerName = ref('')

// --- Methoden ---


const statusFarbe = (status) => {
  const s = statusArten.find(s => s.name === status)
  return s ? s.color : 'bg-gray-300'
}

const nextStatus = (current) => {
  const idx = statusArten.findIndex(s => s.name === current)
  return statusArten[(idx + 1) % statusArten.length].name
}

// --- Klick auf Status (optional später: axios speichern)
const toggleStatus = (tIndex, dayIndex) => {
  const current = teilnehmer.value[tIndex].anwesenheit[dayIndex]
  teilnehmer.value[tIndex].anwesenheit[dayIndex] = nextStatus(current)
}
</script>

<template>
  <Head title="Teilnehmer verwalten" />

  <AppLayout>
    <template #header>
      Gruppe – Teilnehmer verwalten
      ({{ new Date(props.gruppe.anfangsdatum).toLocaleDateString('de-DE') }}
      –
      {{ new Date(props.gruppe.enddatum).toLocaleDateString('de-DE') }}) von {{  formatTime(props.gruppe.startzeit) }} bis {{  formatTime(props.gruppe.endzeit) }}
    </template>

    <div class="p-6 space-y-8 bg-white rounded-lg shadow-sm">
      <!-- Teilnehmer hinzufügen -->
      <div class="bg-gray-50 rounded-lg p-4 border shadow-sm">
        <h3 class="font-semibold mb-3 text-gray-700">Teilnehmer hinzufügen</h3>

        <Button
            label="➕ Teilnehmer hinzufügen"
            icon="pi pi-users"
            class="w-full !bg-orange-500 hover:!bg-orange-600 border-none"
            @click="showTeilnehmerModal = true"
        />

        <Dialog
            v-model:visible="showTeilnehmerModal"
            modal
            header="➕ Teilnehmer hinzufügen"
            :style="{ width: '700px', maxWidth: '95vw' }"
            :draggable="false"
            appendTo="body"
            dismissableMask
        >
            <div class="space-y-4">
            <FloatLabel variant="on">
                <MultiSelect
                v-model="selectedTeilnehmerIds"
                :options="props.teilnehmer"
                :filter="true"
                display="chip"
                optionValue="id"
                :optionLabel="(t) => `${t.vorname} ${t.nachname}`"
                placeholder="Teilnehmer auswählen"
                class="w-full"
                appendTo="body"
                panelClass="z-[9999]"
                />
            </FloatLabel>

            <!-- Actions -->
            <div class="flex justify-end gap-2 pt-2">
                <Button label="Abbrechen" class="p-button-text hover:!bg-zbbTrp !text-zbb" @click="showTeilnehmerModal = false" />
                <Button label="Übernehmen" icon="pi pi-check" class="!bg-zbb hover:!bg-zbb/80 border-none" @click="confirmTeilnehmer" />
            </div>
            </div>
        </Dialog>



      </div>

      <!-- Anwesenheit -->
      <div class="space-y-4">
        <h3 class="font-semibold text-gray-700">Anwesenheit verwalten</h3>

        <!-- Legende -->
        <div class="flex items-center gap-6 bg-zbbTrp border p-3 rounded">
          <div
            v-for="s in statusArten"
            :key="s.name"
            class="flex items-center gap-2 text-sm"
          >
            <span :class="['w-3 h-3 rounded-full', s.color]"></span>
            {{ s.name }}
          </div>
        </div>

        <!-- Tabelle -->
        <div class="overflow-x-auto">
          <table class="w-full text-sm border-collapse border shadow-sm">
            <thead class="bg-gray-100 text-gray-700">
              <tr>
                <th class="border px-3 py-2 text-left">Teilnehmer</th>
                <th
                  v-for="tag in tage"
                  :key="tag.label"
                  class="border px-3 py-2 text-center"
                >
                  <div class="flex flex-col items-center">
                    <span class="font-semibold">{{ tag.label }}</span>
                    <span class="text-xs text-gray-500">{{ tag.datum }}</span>
                  </div>
                </th>
              </tr>
            </thead>

            <tbody>
              <tr
                v-for="(t, tIndex) in teilnehmer"
                :key="t.id"
                class="hover:bg-gray-50"
              >
                <td class="border px-4 py-3 font-medium text-gray-800">
                  {{ t.vorname }} {{ t.nachname }}
                </td>

                <td
                  v-for="(tag, dayIndex) in tage"
                  :key="dayIndex"
                  class="border px-4 py-3 text-center cursor-pointer select-none"
                  @click="toggleStatus(tIndex, dayIndex)"
                >
                  <div
                    class="text-sm font-semibold mb-2 px-3 py-1 rounded-full text-white inline-block"
                    :class="statusFarbe(t.anwesenheit[dayIndex])"
                  >
                    {{ t.anwesenheit[dayIndex] }}
                  </div>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </AppLayout>
</template>
