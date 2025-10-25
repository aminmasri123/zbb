<script setup>
import Modal from '@/Components/ModalForm.vue';
import { ref, watch } from 'vue';
import Swal from 'sweetalert2';
import FloatLabel from 'primevue/floatlabel';
import Select from 'primevue/select';
import DatePicker from 'primevue/datepicker';
import axios from 'axios';

const props = defineProps({
  visible: Boolean,
  toEdit: Object,
  bereiche: Array,
  personal: Array
});
const emit = defineEmits(['close', 'updated']);

let form = ref({
  id: null,
  bereich: '',
  betreuer: '',
  anfangsdatum: null,
  enddatum: null,
});

// 🔹 synchronisieren & konvertieren von String -> Date
watch(
  () => props.toEdit,
  (val) => {
    if (val) {
      form.value = {
        id: val.id,
        bereich: val.bereich?.id || val.bereich,
        betreuer: val.betreuer?.id || val.betreuer,
        anfangsdatum: val.anfangsdatum ? new Date(val.anfangsdatum) : null,
        enddatum: val.enddatum ? new Date(val.enddatum) : null,
      };
    }
  },
  { immediate: true }
);

// 🔹 Hilfsfunktion: Date -> 'yyyy-MM-dd'
function formatToIso(date) {
  if (!(date instanceof Date) || isNaN(date)) return null;
  const year = date.getFullYear();
  const month = String(date.getMonth() + 1).padStart(2, '0');
  const day = String(date.getDate()).padStart(2, '0');
  return `${year}-${month}-${day}`;
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
      </div>
    </template>

    <template #footer>
      <button @click="save" class="bg-zbb text-white px-4 py-2 rounded">Speichern</button>
      <button @click="emit('close')" class="border px-4 py-2 rounded">Abbrechen</button>
    </template>
  </Modal>
</template>
