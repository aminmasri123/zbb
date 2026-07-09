<template>
  <Modal v-if="visible" @close="close">
    <template #header>Meldung bearbeiten</template>

    <template #body>
      <div class="grid grid-cols-1 gap-6">
        <div class="rounded border border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-700">
          <div class="font-semibold text-slate-900">{{ meldung?.raum?.name || meldung?.raumName }}</div>
          <div>{{ meldung?.titel }}</div>
        </div>

        <FloatLabel variant="on">
          <InputText v-model="form.titel" class="w-full" />
          <label>Titel</label>
        </FloatLabel>

        <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
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
            <Dropdown
              v-model="form.prioritaet"
              :options="prioritaeten"
              optionLabel="label"
              optionValue="value"
              class="w-full"
            />
            <label>Priorität</label>
          </FloatLabel>
        </div>

        <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
          <FloatLabel variant="on">
            <Dropdown
              v-model="form.kategorie"
              :options="kategorien"
              optionLabel="label"
              optionValue="value"
              class="w-full"
            />
            <label>Kategorie</label>
          </FloatLabel>

          <FloatLabel variant="on">
            <Dropdown
              v-model="form.zugewiesen_an_personen_id"
              :options="personalOptionen"
              optionLabel="label"
              optionValue="id"
              showClear
              class="w-full"
            />
            <label>Zuständig</label>
          </FloatLabel>
        </div>

        <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
          <div>
            <label class="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-500">Fällig am</label>
            <input
              v-model="form.faellig_am"
              type="date"
              class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm focus:border-zbb focus:ring-zbb"
            />
          </div>

          <FloatLabel variant="on">
            <InputNumber
              v-model="form.kosten"
              class="w-full"
              :min="0"
              :minFractionDigits="0"
              :maxFractionDigits="2"
            />
            <label>Kosten</label>
          </FloatLabel>
        </div>

        <FloatLabel variant="on">
          <Textarea v-model="form.beschreibung" rows="4" class="w-full" />
          <label>Beschreibung</label>
        </FloatLabel>

        <FloatLabel variant="on">
          <Textarea v-model="form.massnahme" rows="4" class="w-full" />
          <label>Maßnahme / Behebung</label>
        </FloatLabel>

        <FloatLabel variant="on">
          <Textarea v-model="form.interne_notiz" rows="3" class="w-full" />
          <label>Interne Notiz</label>
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
  meldung: {
    type: Object,
    default: null,
  },
  personal: {
    type: Array,
    default: () => [],
  },
});

const emit = defineEmits(['close', 'updated']);

const statusOptionen = [
  { label: 'Offen', value: 'offen' },
  { label: 'In Bearbeitung', value: 'in_bearbeitung' },
  { label: 'Wartet auf extern', value: 'wartet_auf_extern' },
  { label: 'Behoben', value: 'behoben' },
  { label: 'Erledigt', value: 'erledigt' },
];

const prioritaeten = [
  { label: 'Niedrig', value: 'niedrig' },
  { label: 'Normal', value: 'normal' },
  { label: 'Hoch', value: 'hoch' },
  { label: 'Kritisch', value: 'kritisch' },
];

const kategorien = [
  { label: 'Laptop / IT', value: 'laptop' },
  { label: 'Fenster', value: 'fenster' },
  { label: 'Heizung', value: 'heizung' },
  { label: 'Möbel', value: 'moebel' },
  { label: 'Strom', value: 'strom' },
  { label: 'Netzwerk', value: 'netzwerk' },
  { label: 'Sicherheit', value: 'sicherheit' },
  { label: 'Reinigung', value: 'reinigung' },
  { label: 'Sonstiges', value: 'sonstiges' },
];

const personalOptionen = computed(() =>
  (props.personal || []).map((person) => ({
    ...person,
    label: `${person.vorname ?? ''} ${person.nachname ?? ''}`.trim(),
  }))
);

const form = ref(defaultForm());

watch(
  () => [props.visible, props.meldung],
  () => {
    if (!props.visible || !props.meldung) return;

    form.value = {
      titel: props.meldung.titel ?? '',
      status: props.meldung.status ?? 'offen',
      kategorie: props.meldung.kategorie ?? 'sonstiges',
      prioritaet: props.meldung.prioritaet ?? 'normal',
      zugewiesen_an_personen_id: props.meldung.zugewiesen_an_personen_id ?? null,
      faellig_am: toDateInput(props.meldung.faellig_am),
      beschreibung: props.meldung.beschreibung ?? '',
      massnahme: props.meldung.massnahme ?? '',
      kosten: props.meldung.kosten === null || props.meldung.kosten === undefined ? null : Number(props.meldung.kosten),
      interne_notiz: props.meldung.interne_notiz ?? '',
    };
  },
  { immediate: true }
);

const close = () => emit('close');

const save = async () => {
  if (!props.meldung?.id) return;

  try {
    const response = await axios.put(route('raeumlichkeiten.meldung.update', props.meldung.id), form.value);
    Swal.fire('Gespeichert', 'Die Meldung wurde aktualisiert.', 'success');
    emit('updated', response.data.meldung);
    close();
  } catch (error) {
    Swal.fire('Fehler', error.response?.data?.message || firstValidationError(error) || 'Meldung konnte nicht aktualisiert werden.', 'error');
  }
};

function defaultForm() {
  return {
    titel: '',
    status: 'offen',
    kategorie: 'sonstiges',
    prioritaet: 'normal',
    zugewiesen_an_personen_id: null,
    faellig_am: '',
    beschreibung: '',
    massnahme: '',
    kosten: null,
    interne_notiz: '',
  };
}

function toDateInput(value) {
  if (!value) return '';
  return String(value).slice(0, 10);
}

function firstValidationError(error) {
  const errors = error.response?.data?.errors;
  if (!errors) return null;
  const firstKey = Object.keys(errors)[0];
  return firstKey ? errors[firstKey]?.[0] : null;
}
</script>
