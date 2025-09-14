<script setup>
import Modal from '@/Components/ModalForm.vue';
import { ref } from 'vue';
import Swal from 'sweetalert2';
import FloatLabel from 'primevue/floatlabel';
import InputText from 'primevue/inputtext';
import Select from 'primevue/select';
import DatePicker from 'primevue/datepicker';
import axios from 'axios';

const props = defineProps({
  visible: Boolean,
  abteilungen: { type: Array, required: true }
});
const emit = defineEmits(['close', 'added']);

let form = ref({
  name: '',
  kostenstelle: '',
  abteilung: '',
  antragsdatum: '',
  starttermin: '',
  anfangsdatum: '',
  endtermin: '',
  enddatum: '',
});

const resetForm = () => {
  form.value = {
    name: '',
    kostenstelle: '',
    abteilung: '',
    antragsdatum: '',
    starttermin: '',
    anfangsdatum: '',
    endtermin: '',
    enddatum: '',
  };
};

const save = async () => {
  try {
    const response = await axios.post(route('projekt.store'), form.value);
    Swal.fire('Erfolg!', 'Projekt erfolgreich angelegt!', 'success');
    emit('added', response.data.projekt);
    resetForm();
    emit('close');
  } catch (error) {
    Swal.fire('Fehler', error.response?.data?.message || 'Speichern fehlgeschlagen', 'error');
  }
};
</script>

<template>
  <Modal v-if="visible" @close="emit('close')">
    <template #header><h2>Projekt anlegen</h2></template>
    <template #body>
        <div class="grid grid-cols-2 gap-4">
            <div class="mb-4 w-full mx-1">
                <FloatLabel variant="on">
                    <InputText v-model="form.name" class="w-full"/>
                        <label>Bezeichnung</label>
                    </FloatLabel>
            </div>
            <div class="mb-4 w-full mx-1">
                <FloatLabel variant="on">
                    <Select v-model="form.abteilung" :options="abteilungen" optionValue="id" optionLabel="name" class="w-full"/>
                        <label>Abteilung</label>
                    </FloatLabel>
            </div>
            <div class="mb-4 w-full mx-1">
                <FloatLabel variant="on">
                    <InputText v-model="form.kostenstelle" class="w-full"/>
                    <label>Kostenstelle</label>
                </FloatLabel>
            </div>
            <div class="mb-4 w-full mx-1">
                <FloatLabel variant="on">
                    <DatePicker v-model="form.antragsdatum" dateFormat="yy-mm-dd" class="w-full"/>
                    <label>Antragsdatum</label>
                </FloatLabel>
            </div>
            <div class="mb-4 w-full mx-1">
                <FloatLabel variant="on">
                    <DatePicker v-model="form.starttermin" dateFormat="yy-mm-dd" class="w-full"/>
                    <label>Starttermin</label>
                </FloatLabel>
            </div>
            <div class="mb-4 w-full mx-1">
                <FloatLabel variant="on">
                    <DatePicker v-model="form.anfangsdatum" dateFormat="yy-mm-dd" class="w-full"/>
                    <label>Anfangsdatum</label>
                </FloatLabel>
            </div>
            <div class="mb-4 w-full mx-1">
                <FloatLabel variant="on">
                    <DatePicker v-model="form.endtermin" dateFormat="yy-mm-dd" class="w-full"/>
                    <label>Endtermin</label>
                </FloatLabel>
            </div>
            <div class="mb-4 w-full mx-1">
                <FloatLabel variant="on">
                    <DatePicker v-model="form.enddatum" dateFormat="yy-mm-dd" class="w-full"/>
                    <label>Enddatum</label>
                </FloatLabel>
            </div>
        </div>
    </template>
    <template #footer>
      <button @click="save" class="bg-zbb text-white px-4 py-2 rounded">Speichern</button>
      <button @click="emit('close')" class="border px-4 py-2 rounded">Abbrechen</button>
    </template>
  </Modal>
</template>
