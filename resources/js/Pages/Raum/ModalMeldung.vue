<template>
  <Modal v-if="visible" @close="close">
    <template #header>Raummeldung</template>

    <template #body>
      <div class="grid grid-cols-1 gap-5">
        <div class="rounded border border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-700">
          <strong>{{ raum?.name }}</strong>
          <span v-if="raum?.standort?.name">, {{ raum.standort.name }}</span>
        </div>

        <FloatLabel variant="on">
          <InputText v-model="form.titel" class="w-full" />
          <label>Titel</label>
        </FloatLabel>

        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
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
              v-model="form.prioritaet"
              :options="prioritaeten"
              optionLabel="label"
              optionValue="value"
              class="w-full"
            />
            <label>Prioritaet</label>
          </FloatLabel>
        </div>

        <FloatLabel variant="on">
          <Textarea v-model="form.beschreibung" rows="5" class="w-full" />
          <label>Beschreibung</label>
        </FloatLabel>
      </div>
    </template>

    <template #footer>
      <button @click="save" class="bg-zbb text-white px-4 py-2 rounded">Melden</button>
      <button @click="close" class="border px-4 py-2 rounded">Abbrechen</button>
    </template>
  </Modal>
</template>

<script setup>
import { ref, watch } from 'vue';
import Swal from 'sweetalert2';
import Modal from '@/Components/ModalForm.vue';
import FloatLabel from 'primevue/floatlabel';
import InputText from 'primevue/inputtext';
import Dropdown from 'primevue/dropdown';
import Textarea from 'primevue/textarea';
import axios from 'axios';

const props = defineProps({
  visible: Boolean,
  raum: {
    type: Object,
    default: null,
  },
  projektId: {
    type: [Number, String],
    default: null,
  },
  gruppeId: {
    type: [Number, String],
    default: null,
  },
});

const emit = defineEmits(['close', 'added']);

const kategorien = [
  { label: 'Laptop / IT', value: 'laptop' },
  { label: 'Fenster', value: 'fenster' },
  { label: 'Heizung', value: 'heizung' },
  { label: 'Moebel', value: 'moebel' },
  { label: 'Strom', value: 'strom' },
  { label: 'Netzwerk', value: 'netzwerk' },
  { label: 'Sicherheit', value: 'sicherheit' },
  { label: 'Reinigung', value: 'reinigung' },
  { label: 'Sonstiges', value: 'sonstiges' },
];

const prioritaeten = [
  { label: 'Normal', value: 'normal' },
  { label: 'Hoch', value: 'hoch' },
  { label: 'Kritisch', value: 'kritisch' },
  { label: 'Niedrig', value: 'niedrig' },
];

const form = ref({
  titel: '',
  kategorie: 'sonstiges',
  prioritaet: 'normal',
  beschreibung: '',
});

const resetForm = () => {
  form.value = {
    titel: '',
    kategorie: 'sonstiges',
    prioritaet: 'normal',
    beschreibung: '',
  };
};

watch(
  () => props.visible,
  (visible) => {
    if (visible) {
      resetForm();
    }
  }
);

const close = () => emit('close');

const save = async () => {
  if (!props.raum?.id) {
    return;
  }

  try {
    const response = await axios.post(route('raeumlichkeiten.meldung.store', props.raum.id), {
      ...form.value,
      projekt_id: props.projektId,
      gruppe_id: props.gruppeId,
    });

    Swal.fire('Erfasst', 'Die Meldung wurde gespeichert.', 'success');
    emit('added', response.data.meldung);
    close();
  } catch (error) {
    Swal.fire('Fehler', error.response?.data?.message || 'Meldung konnte nicht gespeichert werden.', 'error');
  }
};
</script>
