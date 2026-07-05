
<template>
  <Modal v-if="visible" @close="emit('close')">
    <template #header>{{$t('Gruppe anlegen')}}</template>
    <template #body>
    <form >
        <!-- Gruppenname -->
        <div class="grid grid-cols-2 gap-4 mt-6 mb-4">
            <FloatLabel variant="on">
                <Select v-model="form.bereich" :options="props.projekt.bereiche" optionValue="id" optionLabel="name" class="w-full"/>
                <label>Bereiche</label>
            </FloatLabel>

           <FloatLabel variant="on">
                <Select v-model="form.betreuer" :options="props.betreuer" optionValue="id" :optionLabel="(t) => `${t.vorname} ${t.nachname}`" class="w-full"/>
                <label>Betreuer</label>
            </FloatLabel>

        </div>

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
                <Select v-model="form.raum_id" :options="props.projekt.raeume" optionValue="id" :optionLabel="roomLabel" class="w-full"/>
                <label>Raum</label>
            </FloatLabel>

            <FloatLabel v-else variant="on">
                <input v-model="form.externer_ort" type="text" class="w-full rounded-md border-gray-300 shadow-sm focus:border-zbb focus:ring-zbb" />
                <label>Externer Ort / Ausflug</label>
            </FloatLabel>
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
      <button @click="save" class="bg-zbb text-white px-4 py-2 rounded">Speichern</button>
      <button @click="emit('close')" class="border px-4 py-2 rounded">Abbrechen</button>
    </template>
  </Modal>
</template>



<script setup>
import { computed, watch } from 'vue'
import { useForm } from '@inertiajs/vue3'
import Modal from '@/Components/ModalForm.vue'
import Select from 'primevue/select'
import FloatLabel from 'primevue/floatlabel';
import Swal from 'sweetalert2';

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

// Formularlogik
const form = useForm({
  bereich: '',
  betreuer: '',
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
})


const groupTypes = [
  { value: '1-day', label: '1 Tag', desc: 'Eintägiges Event', icon: '📅' },
  { value: '2-day', label: '2 Tage', desc: 'Zweitägiges Event', icon: '📆' },
  { value: '3-day', label: '3 Tage', desc: 'Dreitägiges Event', icon: '🗓️' },
  { value: 'unlimited', label: 'Flexibel', desc: 'Beliebige Dauer', icon: '♾️' },
]

const standorte = computed(() => props.projekt?.standorte || [])

const selectedRoom = computed(() =>
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
  ([type, start]) => {
    if (!start) return

    const startDate = new Date(start)

    if (type === '1-day') {
      form.endDate = formatDate(startDate)
    } else if (type === '2-day') {
      form.endDate = formatDate(addDays(startDate, 1))
    } else if (type === '3-day') {
      form.endDate = formatDate(addDays(startDate, 2))
    } else if (type === 'unlimited') {
      form.endDate = ''
    }
  }
)

// 🔹 Hilfsfunktionen
function addDays(date, days) {
  const newDate = new Date(date)
  newDate.setDate(newDate.getDate() + days)
  return newDate
}

function formatDate(date) {
  return date.toISOString().split('T')[0]
}



const save = async () => {
  try {
    const response = await axios.post(route('gruppe.store'), form);
    Swal.fire('Erfolg!', 'Gruppe erfolgreich angelegt!', 'success');

    // 👇 hier korrekt das Backend-Objekt (mit ID) verwenden
    emit('added', response.data.gruppe);

    form.reset();
    emit('close');
  } catch (error) {
    Swal.fire('Fehler', error.response?.data?.message || 'Speichern fehlgeschlagen', 'error');
  }
};

</script>
