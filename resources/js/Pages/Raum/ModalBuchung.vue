<template>
  <Modal v-if="visible" @close="close">
    <template #header>{{ form.id ? 'Buchung bearbeiten' : 'Raum buchen' }}</template>

    <template #body>
      <div class="grid grid-cols-1 gap-6">
        <FloatLabel variant="on">
          <Dropdown
            v-model="form.raum_id"
            :options="raumOptionen"
            optionLabel="label"
            optionValue="id"
            class="w-full"
          />
          <label>Raum</label>
        </FloatLabel>

        <FloatLabel variant="on">
          <InputText v-model="form.titel" class="w-full" />
          <label>Titel</label>
        </FloatLabel>

        <div class="grid grid-cols-1 gap-4 md:grid-cols-3">
          <FloatLabel variant="on">
            <Dropdown
              v-model="form.typ"
              :options="typen"
              optionLabel="label"
              optionValue="value"
              class="w-full"
            />
            <label>Typ</label>
          </FloatLabel>

          <FloatLabel variant="on">
            <Dropdown
              v-model="form.status"
              :options="statusOptionen"
              optionLabel="label"
              optionValue="value"
              class="w-full"
            />
            <label>Status</label>
          </FloatLabel>

          <FloatLabel variant="on">
            <InputNumber v-model="form.teilnehmerzahl" class="w-full" :min="0" />
            <label>Personen</label>
          </FloatLabel>
        </div>

        <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
          <div>
            <label class="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-500">Beginn</label>
            <input
              v-model="form.start_at"
              type="datetime-local"
              class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm focus:border-zbb focus:ring-zbb"
            />
          </div>

          <div>
            <label class="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-500">Ende</label>
            <input
              v-model="form.end_at"
              type="datetime-local"
              class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm focus:border-zbb focus:ring-zbb"
            />
          </div>
        </div>

        <FloatLabel variant="on">
          <Textarea v-model="form.zweck" rows="3" class="w-full" />
          <label>Zweck</label>
        </FloatLabel>

        <FloatLabel variant="on">
          <Textarea v-model="form.bemerkung" rows="3" class="w-full" />
          <label>Bemerkung</label>
        </FloatLabel>
      </div>
    </template>

    <template #footer>
      <button @click="save" class="bg-zbb text-white px-4 py-2 rounded">
        Speichern
      </button>
      <button @click="close" class="border px-4 py-2 rounded">
        Abbrechen
      </button>
    </template>
  </Modal>
</template>

<script setup>
import { computed, ref, watch } from 'vue';
import Swal from 'sweetalert2';
import Modal from '@/Components/ModalForm.vue';
import FloatLabel from 'primevue/floatlabel';
import Dropdown from 'primevue/dropdown';
import InputText from 'primevue/inputtext';
import InputNumber from 'primevue/inputnumber';
import Textarea from 'primevue/textarea';
import axios from 'axios';

const props = defineProps({
  visible: Boolean,
  raeume: {
    type: Array,
    default: () => [],
  },
  buchung: {
    type: Object,
    default: null,
  },
  initialRaumId: {
    type: [Number, String],
    default: null,
  },
});

const emit = defineEmits(['close', 'saved']);

const typen = [
  { label: 'Buchung', value: 'buchung' },
  { label: 'Wartung', value: 'wartung' },
  { label: 'Sperrzeit', value: 'sperre' },
];

const statusOptionen = [
  { label: 'Reserviert', value: 'reserviert' },
  { label: 'Bestätigt', value: 'bestaetigt' },
  { label: 'Storniert', value: 'storniert' },
];

const emptyForm = () => ({
  id: null,
  raum_id: props.initialRaumId ? Number(props.initialRaumId) : null,
  titel: '',
  typ: 'buchung',
  start_at: '',
  end_at: '',
  teilnehmerzahl: null,
  status: 'reserviert',
  zweck: '',
  bemerkung: '',
});

const form = ref(emptyForm());

const raumOptionen = computed(() =>
  (props.raeume || []).map((raum) => ({
    id: raum.id,
    label: `${raum.name}${raum.standort?.name ? ` (${raum.standort.name})` : ''}`,
  }))
);

watch(
  () => [props.visible, props.buchung, props.initialRaumId],
  () => {
    if (!props.visible) return;

    if (props.buchung) {
      form.value = {
        id: props.buchung.id,
        raum_id: Number(props.buchung.raum_id),
        titel: props.buchung.titel ?? '',
        typ: props.buchung.typ ?? 'buchung',
        start_at: toInputDateTime(props.buchung.start_at),
        end_at: toInputDateTime(props.buchung.end_at),
        teilnehmerzahl: props.buchung.teilnehmerzahl ?? null,
        status: props.buchung.status ?? 'reserviert',
        zweck: props.buchung.zweck ?? '',
        bemerkung: props.buchung.bemerkung ?? '',
      };
      return;
    }

    form.value = emptyForm();
  },
  { immediate: true }
);

const close = () => emit('close');

const save = async () => {
  if (!form.value.raum_id || !form.value.titel || !form.value.start_at || !form.value.end_at) {
    Swal.fire('Fehler', 'Bitte Raum, Titel, Beginn und Ende ausfüllen.', 'warning');
    return;
  }

  try {
    const payload = { ...form.value };
    const response = form.value.id
      ? await axios.put(route('raeumlichkeiten.buchung.update', form.value.id), payload)
      : await axios.post(route('raeumlichkeiten.buchung.store'), payload);

    Swal.fire('Gespeichert', 'Die Raumbuchung wurde gespeichert.', 'success');
    emit('saved', response.data.buchung);
    close();
  } catch (error) {
    Swal.fire('Fehler', error.response?.data?.message || firstValidationError(error) || 'Buchung konnte nicht gespeichert werden.', 'error');
  }
};

function toInputDateTime(value) {
  if (!value) return '';
  const date = new Date(value);
  if (Number.isNaN(date.getTime())) return '';

  const pad = (number) => String(number).padStart(2, '0');
  return `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())}T${pad(date.getHours())}:${pad(date.getMinutes())}`;
}

function firstValidationError(error) {
  const errors = error.response?.data?.errors;
  if (!errors) return null;
  const firstKey = Object.keys(errors)[0];
  return firstKey ? errors[firstKey]?.[0] : null;
}
</script>
