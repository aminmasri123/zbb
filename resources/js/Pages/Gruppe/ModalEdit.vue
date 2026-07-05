<script setup>
import Modal from '@/Components/ModalForm.vue';
import { computed, ref, watch } from 'vue';
import Swal from 'sweetalert2';
import FloatLabel from 'primevue/floatlabel';
import Select from 'primevue/select';
import DatePicker from 'primevue/datepicker';
import axios from 'axios';

const props = defineProps({
  visible: Boolean,
  toEdit: Object,
  bereiche: Array,
  personal: Array,
  raeume: {
    type: Array,
    default: () => [],
  },
  standorte: {
    type: Array,
    default: () => [],
  },
});
const emit = defineEmits(['close', 'updated']);

let form = ref({
  id: null,
  bereich: '',
  betreuer: '',
  ort_typ: 'raum',
  raum_id: null,
  standort_id: null,
  externer_ort: '',
  anfangsdatum: null,
  enddatum: null,
  startzeit: '',
  endzeit: '',
  bemerkung: '',
});

const selectedRoom = computed(() =>
  (props.raeume || []).find((raum) => Number(raum.id) === Number(form.value.raum_id))
);

const selectedStandortName = computed(() =>
  selectedRoom.value?.standort?.name ||
  props.standorte.find((standort) => Number(standort.id) === Number(selectedRoom.value?.standort_id))?.name ||
  ''
);

const roomLabel = (raum) => {
  const standortName = raum?.standort?.name || props.standorte.find((standort) => Number(standort.id) === Number(raum?.standort_id))?.name;
  return standortName ? `${raum.name} (${standortName})` : raum.name;
};

// 🔹 synchronisieren & konvertieren von String -> Date
watch(
  () => props.toEdit,
  (val) => {
    if (val) {
      form.value = {
        id: val.id,
        bereich: val.bereich?.id || val.bereich,
        betreuer: val.betreuer?.id || val.betreuer,
        ort_typ: val.ort_typ || (val.raum_id ? 'raum' : 'extern'),
        raum_id: val.raum_id || val.raum?.id || null,
        standort_id: val.standort_id || val.standort?.id || val.raum?.standort_id || null,
        externer_ort: val.externer_ort || '',
        anfangsdatum: val.anfangsdatum ? new Date(val.anfangsdatum) : null,
        enddatum: val.enddatum ? new Date(val.enddatum) : null,
        startzeit: normalizeTime(val.startzeit),
        endzeit: normalizeTime(val.endzeit),
        bemerkung: val.bemerkung || '',
      };
    }
  },
  { immediate: true }
);

watch(
  () => form.value.ort_typ,
  (typ) => {
    if (typ === 'extern') {
      form.value.raum_id = null;
      form.value.standort_id = form.value.standort_id || props.standorte[0]?.id || null;
    } else {
      form.value.externer_ort = '';
      form.value.standort_id = selectedRoom.value?.standort_id || null;
    }
  }
);

watch(
  () => form.value.raum_id,
  () => {
    if (form.value.ort_typ === 'raum') {
      form.value.standort_id = selectedRoom.value?.standort_id || null;
    }
  }
);

// 🔹 Hilfsfunktion: Date -> 'yyyy-MM-dd'
function formatToIso(date) {
  if (!(date instanceof Date) || isNaN(date)) return null;
  const year = date.getFullYear();
  const month = String(date.getMonth() + 1).padStart(2, '0');
  const day = String(date.getDate()).padStart(2, '0');
  return `${year}-${month}-${day}`;
}

function normalizeTime(value) {
  if (!value) return '';
  return String(value).slice(0, 5);
}

// 🔹 Speichern
const save = async () => {
  try {
    const payload = {
      ...form.value,
      anfangsdatum: formatToIso(form.value.anfangsdatum),
      enddatum: formatToIso(form.value.enddatum),
    };

    const response = await axios.put(route('gruppe.update', form.value.id), payload);
    Swal.fire('Gespeichert!', 'Gruppe wurde aktualisiert!', 'success');
    emit('updated', response.data.projekt);
    emit('close');
  } catch (error) {
    Swal.fire('Fehler', error.response?.data?.message || 'Update fehlgeschlagen', 'error');
  }
};
</script>

<template>
  <Modal v-if="visible" @close="emit('close')">
    <template #header>Gruppe bearbeiten</template>
    <template #body>
      <div class="grid grid-cols-2 gap-4">
        <FloatLabel variant="on">
          <Select
            v-model="form.bereich"
            :options="bereiche"
            optionValue="id"
            optionLabel="name"
            class="w-full"
          />
          <label>Bereich</label>
        </FloatLabel>

        <FloatLabel variant="on">
          <Select
            v-model="form.betreuer"
            :options="personal"
            optionValue="id"
            :optionLabel="(p) => `${p.vorname} ${p.nachname}`"
            class="w-full"
          />
          <label>Betreuer</label>
        </FloatLabel>

        <div class="col-span-2 grid grid-cols-2 gap-3">
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

        <FloatLabel v-if="form.ort_typ === 'raum'" variant="on" class="col-span-2">
          <Select
            v-model="form.raum_id"
            :options="raeume"
            optionValue="id"
            :optionLabel="roomLabel"
            class="w-full"
          />
          <label>Raum</label>
        </FloatLabel>

        <FloatLabel v-else variant="on" class="col-span-2">
          <input v-model="form.externer_ort" type="text" class="w-full rounded-md border-gray-300 shadow-sm focus:border-zbb focus:ring-zbb" />
          <label>Externer Ort / Ausflug</label>
        </FloatLabel>

        <p v-if="form.ort_typ === 'raum' && selectedStandortName" class="col-span-2 text-xs text-gray-500">
          Standort: {{ selectedStandortName }}
        </p>

        <FloatLabel v-if="form.ort_typ === 'extern'" variant="on" class="col-span-2">
          <Select
            v-model="form.standort_id"
            :options="standorte"
            optionValue="id"
            optionLabel="name"
            class="w-full"
          />
          <label>Standort</label>
        </FloatLabel>

        <FloatLabel variant="on">
          <DatePicker
            v-model="form.anfangsdatum"
            dateFormat="dd.mm.yy"
            showIcon
            class="w-full"
          />
          <label>Anfangsdatum</label>
        </FloatLabel>

        <FloatLabel variant="on">
          <DatePicker
            v-model="form.enddatum"
            dateFormat="dd.mm.yy"
            showIcon
            class="w-full"
          />
          <label>Enddatum</label>
        </FloatLabel>

        <FloatLabel variant="on">
          <input v-model="form.startzeit" type="time" class="w-full rounded-md border-gray-300 shadow-sm focus:border-zbb focus:ring-zbb" />
          <label>Startzeit</label>
        </FloatLabel>

        <FloatLabel variant="on">
          <input v-model="form.endzeit" type="time" class="w-full rounded-md border-gray-300 shadow-sm focus:border-zbb focus:ring-zbb" />
          <label>Endzeit</label>
        </FloatLabel>

        <div class="col-span-2">
          <label class="mb-1 block text-sm text-gray-600">Bemerkung</label>
          <textarea v-model="form.bemerkung" rows="3" class="w-full rounded-md border-gray-300 shadow-sm focus:border-zbb focus:ring-zbb"></textarea>
        </div>
      </div>
    </template>

    <template #footer>
      <button @click="save" class="bg-zbb text-white px-4 py-2 rounded">Speichern</button>
      <button @click="emit('close')" class="border px-4 py-2 rounded">Abbrechen</button>
    </template>
  </Modal>
</template>
