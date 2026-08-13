
<template>
  <Modal v-if="visible" @close="emit('close')">
    <template #header>{{$t('Gruppe anlegen')}}</template>
    <template #body>
    <form >
        <!-- Gruppenname -->
        <div class="grid grid-cols-2 gap-4 mt-6 mb-4">
            <FloatLabel variant="on">
                <Select v-model="form.betreuer" :options="visibleBetreuer" optionValue="id" :optionLabel="(t) => `${t.vorname} ${t.nachname}`" filter class="w-full"/>
                <label>Betreuer / Anleiter</label>
            </FloatLabel>

            <FloatLabel variant="on">
                <Select v-model="form.bereich" :options="bereichOptions" optionValue="id" optionLabel="name" :disabled="!form.betreuer" class="w-full"/>
                <label>Bereiche</label>
            </FloatLabel>

            <label v-if="canUseVertretung" class="col-span-2 -mt-2 flex items-center gap-2 text-sm text-gray-600">
                <input v-model="showVertretung" type="checkbox" class="rounded border-gray-300 text-zbb focus:ring-zbb" />
                <span>Vertretung: alle aktiven Mitarbeiter auswÃ¤hlen</span>
            </label>
        </div>

        <FloatLabel variant="on" class="mb-5">
            <MultiSelect v-model="form.partner_ids" :options="props.projekt.partners || []" optionValue="id" optionLabel="name" filter display="chip" placeholder="Eine oder mehrere Schulen auswählen" class="w-full"/>
            <label>Bezug / Schulen</label>
        </FloatLabel>

        <!-- Gruppentyp -->
        <div class="mb-5">
            <div class="mb-4 grid grid-cols-2 gap-3">
                <label class="cursor-pointer">
                    <input type="radio" value="raum" v-model="form.ort_typ" class="sr-only" />
                    <div :class="['rounded-lg border-2 p-3 text-center text-sm font-medium', form.ort_typ === 'raum' ? 'border-zbb bg-orange-50 text-zbb' : 'border-gray-200 text-gray-700']">
                        Raum
                    </div>
                </label>
                <label class="cursor-pointer">
                    <input type="radio" value="extern" v-model="form.ort_typ" class="sr-only" />
                    <div :class="['rounded-lg border-2 p-3 text-center text-sm font-medium', form.ort_typ === 'extern' ? 'border-zbb bg-orange-50 text-zbb' : 'border-gray-200 text-gray-700']">
                        Extern
                    </div>
                </label>
            </div>

            <FloatLabel v-if="form.ort_typ === 'raum'" variant="on">
                <Select v-model="form.raum_id" :options="roomOptions" optionValue="id" :optionLabel="roomLabel" :disabled="!form.betreuer" class="w-full"/>
                <label>Raum</label>
            </FloatLabel>

            <div v-else>
                <label for="gruppe-create-externer-ort" class="mb-1 block text-sm text-gray-600">
                    Externer Ort / Ausflug
                </label>
                <input
                    id="gruppe-create-externer-ort"
                    v-model="form.externer_ort"
                    type="text"
                    placeholder="Ort oder Ausflugsziel eingeben"
                    class="w-full rounded-md border-gray-300 shadow-sm focus:border-zbb focus:ring-zbb"
                />
            </div>
            <p v-if="form.ort_typ === 'raum' && selectedStandortName" class="mt-2 text-xs text-gray-500">
                Standort: {{ selectedStandortName }}
            </p>
            <FloatLabel v-if="form.ort_typ === 'extern'" variant="on" class="mt-4">
                <Select v-model="form.standort_id" :options="standorte" optionValue="id" optionLabel="name" class="w-full"/>
                <label>Standort</label>
            </FloatLabel>
            <label for="groupType" class="block text-sm font-medium text-gray-700 mb-3" >
                Gruppentyp <span class="text-red-500">*</span>
            </label>
            <div class="grid grid-cols-2 gap-3">
                <template v-for="option in groupTypes" :key="option.value">
                    <label class="cursor-pointer">
                        <input type="radio" name="groupType" :value="option.value" v-model="form.groupType" class="sr-only" />
                        <div
                            :class="[
                                'p-4 border-2 rounded-lg transition-colors',
                                form.groupType === option.value
                                ? 'border-zbb bg-orange-50'
                                : 'border-gray-200 hover:border-zbb'
                            ]"
                            >
                            <div class="text-center">
                                <div class="text-2xl mb-2">{{ option.icon }}</div>
                                <div class="font-medium text-gray-900">
                                {{ option.label }}
                                </div>
                                <div class="text-xs text-gray-500">{{ option.desc }}</div>
                            </div>
                        </div>
                    </label>
                </template>
            </div>
        </div>

        <!-- Datum -->
        <div class="grid grid-cols-2 gap-4 mb-6">
          <div>
                <label
                for="startDate"
                class="block text-sm font-medium text-gray-700 mb-2"
                >
                Startdatum <span class="text-red-500">*</span>
                </label>
                <input v-model="form.startDate" type="date" id="startDate" name="startDate" required class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-1 focus:ring-zbb focus:border-zbb transition-colors" />
          </div>
          <div>
                <label
                for="endDate"
                class="block text-sm font-medium text-gray-700 mb-2"
                >
                Enddatum <span class="text-red-500">*</span>
                </label>
                <input v-model="form.endDate" type="date" id="endDate" name="endDate" required :disabled="form.groupType !== 'unlimited'" class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-1 focus:ring-zbb focus:border-zbb transition-colors disabled:bg-gray-100 disabled:cursor-not-allowed"/>
          </div>
        </div>

        <div v-if="form.startDate && form.endDate" class="mb-6 rounded-lg border border-gray-200 bg-gray-50 p-4">
            <div class="flex items-start justify-between gap-3">
                <div>
                    <p class="text-sm font-semibold text-gray-800">Wochenenden und Feiertage</p>
                    <p class="mt-1 text-xs text-gray-500">
                        Diese Tage bleiben im Gruppenzeitraum sichtbar, sind in der Anwesenheit aber gesperrt. Bestätige nur Tage, an denen tatsächlich gearbeitet wird.
                    </p>
                </div>
                <span v-if="datePreviewLoading" class="text-xs text-gray-500">Prüfung …</span>
            </div>

            <p v-if="datePreviewError" class="mt-3 rounded bg-red-50 px-3 py-2 text-xs text-red-700">
                {{ datePreviewError }}
            </p>
            <p v-else-if="!datePreviewLoading && nonWorkingDays.length === 0" class="mt-3 text-xs font-medium text-green-700">
                Der Zeitraum enthält nur reguläre Arbeitstage.
            </p>
            <div v-else class="mt-3 max-h-48 space-y-2 overflow-y-auto">
                <label
                    v-for="day in nonWorkingDays"
                    :key="day.date"
                    class="flex cursor-pointer items-start gap-3 rounded border border-gray-200 bg-white p-3"
                >
                    <input
                        v-model="form.non_working_dates"
                        type="checkbox"
                        :value="day.date"
                        class="mt-0.5 rounded border-gray-300 text-zbb focus:ring-zbb"
                    />
                    <span class="text-sm text-gray-700">
                        <span class="block font-semibold">{{ formatGermanDate(day.date) }} â€“ {{ day.label }}</span>
                        <span class="mt-0.5 block text-xs text-gray-500">Ja, an diesem Tag wird gearbeitet.</span>
                    </span>
                </label>
            </div>
        </div>

        <!-- Zeit -->
        <div class="grid grid-cols-2 gap-4 mb-6">
          <div>
                <label
                for="startZeit"
                class="block text-sm font-medium text-gray-700 mb-2"
                >
                Startzeit <span class="text-red-500">*</span>
                </label>
                <input v-model="form.startZeit" type="time" id="startZeit" name="startZeit" required class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-1 focus:ring-zbb focus:border-zbb transition-colors" />
          </div>
          <div>
                <label
                for="endZeit"
                class="block text-sm font-medium text-gray-700 mb-2"
                >
                Endzeit <span class="text-red-500">*</span>
                </label>
                <input v-model="form.endZeit" type="time" id="endZeit" name="endZeit" required  class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-1 focus:ring-zbb focus:border-zbb transition-colors disabled:bg-gray-100 disabled:cursor-not-allowed"/>
          </div>
        </div>
        <div class="mb-6">
            <label class="block text-sm font-medium text-gray-700 mb-2">Bemerkung</label>
            <textarea v-model="form.bemerkung" rows="3" class="w-full rounded-lg border-gray-300 focus:border-zbb focus:ring-zbb"></textarea>
        </div>
      </form>
    </template>
    <template #footer>
      <button @click="save()" class="bg-zbb text-white px-4 py-2 rounded">Speichern</button>
      <button @click="emit('close')" class="border px-4 py-2 rounded">Abbrechen</button>
    </template>
  </Modal>
</template>



<script setup>
import { computed, ref, watch } from 'vue'
import { useForm } from '@inertiajs/vue3'
import Modal from '@/Components/ModalForm.vue'
import Select from 'primevue/select'
import MultiSelect from 'primevue/multiselect'
import FloatLabel from 'primevue/floatlabel';
import Swal from 'sweetalert2';
import axios from 'axios'
import { confirmRoomOverlap, showRequestError } from './roomConflictDialog'

const props = defineProps({
    visible: Boolean,
    projekt: {
        type: Object,
        required: true,
    },
    betreuer: {
        type: [Array, Object],
            required: true,
        },
})

const emit = defineEmits(['close', 'added'])

const close = () => emit('close')
const showVertretung = ref(false)

// Formularlogik
const form = useForm({
  bereich: '',
  betreuer: '',
  partner_ids: [],
  groupType: '',
  startDate: '',
  endDate: '',
  startZeit: '',
  endZeit: '',
  ort_typ: 'raum',
  raum_id: '',
  standort_id: '',
  externer_ort: '',
  bemerkung: '',
  non_working_dates: [],
})

const nonWorkingDays = ref([])
const datePreviewLoading = ref(false)
const datePreviewError = ref('')
let datePreviewVersion = 0


const groupTypes = [
  { value: '1-day', label: '1 Tag', desc: 'Eintägiges Event', icon: '📅' },
  { value: '2-day', label: '2 Tage', desc: 'Zweitägiges Event', icon: '📆' },
  { value: '3-day', label: '3 Tage', desc: 'Dreitägiges Event', icon: '🗓️' },
  { value: 'unlimited', label: 'Flexibel', desc: 'Beliebige Dauer', icon: '♾️' },
]

const standorte = computed(() => props.projekt?.standorte || [])
const allBetreuer = computed(() => Array.isArray(props.betreuer) ? props.betreuer : Object.values(props.betreuer || {}))
const projectBetreuer = computed(() => allBetreuer.value.filter((person) => person.is_project_member !== false))
const canUseVertretung = computed(() => allBetreuer.value.some((person) => person.is_project_member === false))
const visibleBetreuer = computed(() => {
  if (showVertretung.value || projectBetreuer.value.length === 0) {
    return allBetreuer.value
  }

  return projectBetreuer.value
})
const selectedBetreuer = computed(() =>
  allBetreuer.value.find((person) => Number(person.id) === Number(form.betreuer))
)
const selectedBetreuerBereiche = computed(() => selectedBetreuer.value?.bereiche || [])
const bereichOptions = computed(() =>
  selectedBetreuerBereiche.value.length
    ? selectedBetreuerBereiche.value
    : (props.projekt?.bereiche || [])
)
const selectedBetreuerArbeitsraeume = computed(() => selectedBetreuer.value?.raeume?.arbeitsbereich || [])
const roomOptions = computed(() =>
  selectedBetreuerArbeitsraeume.value.length
    ? selectedBetreuerArbeitsraeume.value
    : (props.projekt?.raeume || [])
)

const selectedRoom = computed(() =>
  roomOptions.value.find((raum) => Number(raum.id) === Number(form.raum_id)) ||
  (props.projekt?.raeume || []).find((raum) => Number(raum.id) === Number(form.raum_id))
)

const selectedStandortName = computed(() =>
  selectedRoom.value?.standort?.name ||
  standorte.value.find((standort) => Number(standort.id) === Number(selectedRoom.value?.standort_id))?.name ||
  ''
)

const roomLabel = (raum) => {
  const standortName = raum?.standort?.name || standorte.value.find((standort) => Number(standort.id) === Number(raum?.standort_id))?.name
  return standortName ? `${raum.name} (${standortName})` : raum.name
}

const applyBetreuerBereich = () => {
  const optionIds = bereichOptions.value.map((bereich) => Number(bereich.id))

  if (!selectedBetreuer.value || selectedBetreuerBereiche.value.length === 0) {
    if (form.bereich !== '' && !optionIds.includes(Number(form.bereich))) {
      form.bereich = ''
    }

    return
  }

  const defaultBereichId = Number(selectedBetreuer.value.default_bereich_id || 0)

  if (defaultBereichId && optionIds.includes(defaultBereichId)) {
    form.bereich = defaultBereichId
    return
  }

  if (selectedBetreuerBereiche.value.length === 1) {
    form.bereich = selectedBetreuerBereiche.value[0].id
    return
  }

  if (!optionIds.includes(Number(form.bereich))) {
    form.bereich = ''
  }
}

const applyBetreuerRaum = () => {
  if (form.ort_typ !== 'raum') {
    return
  }

  const optionIds = roomOptions.value.map((raum) => Number(raum.id))

  if (!selectedBetreuer.value || selectedBetreuerArbeitsraeume.value.length === 0) {
    if (form.raum_id !== '' && !optionIds.includes(Number(form.raum_id))) {
      form.raum_id = ''
    }

    return
  }

  const defaultRaumId = Number(selectedBetreuer.value.default_arbeitsbereich_raum_id || 0)

  if (defaultRaumId && optionIds.includes(defaultRaumId)) {
    form.raum_id = defaultRaumId
    return
  }

  if (selectedBetreuerArbeitsraeume.value.length === 1) {
    form.raum_id = selectedBetreuerArbeitsraeume.value[0].id
    return
  }

  if (!optionIds.includes(Number(form.raum_id))) {
    form.raum_id = ''
  }
}

const applyBetreuerDefaults = () => {
  applyBetreuerBereich()
  applyBetreuerRaum()
}

watch(
  () => form.betreuer,
  () => applyBetreuerDefaults()
)

watch(showVertretung, (enabled) => {
  if (!enabled && selectedBetreuer.value?.is_project_member === false) {
    form.betreuer = ''
    form.bereich = ''
    form.raum_id = ''
  }
})

// 🔹 Validierung
const isValid = computed(() => {
  const hasOrt = form.ort_typ === 'extern' ? form.externer_ort !== '' && form.standort_id !== '' : form.raum_id !== ''

  return (
    form.groupType !== '' &&
    form.startDate !== '' &&
    form.endDate !== '' &&
    form.startZeit !== '' &&
    form.endZeit !== '' &&
    form.bereich !== '' &&
    form.betreuer !== '' &&
    hasOrt
  )
})

watch(
  () => form.ort_typ,
  (typ) => {
    if (typ === 'extern') {
      form.raum_id = ''
      form.standort_id = form.standort_id || standorte.value[0]?.id || ''
    } else {
      form.externer_ort = ''
      form.standort_id = selectedRoom.value?.standort_id || ''
      applyBetreuerRaum()
    }
  }
)

watch(
  () => form.raum_id,
  () => {
    if (form.ort_typ === 'raum') {
      form.standort_id = selectedRoom.value?.standort_id || ''
    }
  }
)

// 🔹 Reaktive Logik: Enddatum automatisch berechnen
watch(
  () => [form.groupType, form.startDate],
  ([type], [oldType]) => {
    if (type === 'unlimited' && oldType !== 'unlimited') {
      form.endDate = ''
      nonWorkingDays.value = []
      form.non_working_dates = []
      return
    }

    refreshWorkdayPreview()
  }
)

// 🔹 Hilfsfunktionen
async function refreshWorkdayPreview() {
  const version = ++datePreviewVersion
  datePreviewError.value = ''

  if (!form.groupType || !form.startDate || (form.groupType === 'unlimited' && !form.endDate)) {
    nonWorkingDays.value = []
    form.non_working_dates = []
    return
  }

  datePreviewLoading.value = true

  try {
    const response = await axios.post(route('gruppe.workday-preview'), {
      groupType: form.groupType,
      startDate: form.startDate,
      endDate: form.groupType === 'unlimited' ? form.endDate : null,
    })

    if (version !== datePreviewVersion) return

    form.endDate = response.data.endDate
    nonWorkingDays.value = response.data.nonWorkingDays || []
    const availableDates = new Set(nonWorkingDays.value.map((day) => day.date))
    form.non_working_dates = form.non_working_dates.filter((date) => availableDates.has(date))
  } catch (error) {
    if (version !== datePreviewVersion) return
    nonWorkingDays.value = []
    form.non_working_dates = []
    datePreviewError.value = error.response?.data?.message || 'Arbeitstage und Feiertage konnten nicht geprüft werden.'
  } finally {
    if (version === datePreviewVersion) {
      datePreviewLoading.value = false
    }
  }
}

watch(
  () => form.endDate,
  () => {
    if (form.groupType === 'unlimited') {
      refreshWorkdayPreview()
    }
  }
)

const formatGermanDate = (value) => new Date(`${value}T12:00:00`).toLocaleDateString('de-DE', {
  weekday: 'short',
  day: '2-digit',
  month: '2-digit',
  year: 'numeric',
})



const save = async (allowRoomOverlap = false) => {
  try {
    const response = await axios.post(route('gruppe.store'), {
      ...form.data(),
      allow_room_overlap: allowRoomOverlap === true,
    });
    Swal.fire('Erfolg!', 'Gruppe erfolgreich angelegt!', 'success');

    // 👇 hier korrekt das Backend-Objekt (mit ID) verwenden
    emit('added', response.data.gruppe);

    form.reset();
    emit('close');
  } catch (error) {
    if (error.response?.data?.code === 'room_conflict') {
      const confirmed = await confirmRoomOverlap(error.response.data)

      if (confirmed) {
        await save(true)
      }

      return
    }

    await showRequestError(error, 'Die Gruppe konnte nicht gespeichert werden.')
  }
};

</script>
