<template>
  <Modal v-if="visible" @close="emit('close')" >
    <template #header>📘 LuV anlegen</template>

    <template #body>
      <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
        <!-- Zeitraum -->
        <div>
          <FloatLabel variant="on">
            <DatePicker v-model="formLuV.von" dateFormat="dd.mm.yy" class="w-full" inputClass="w-full" :manualInput="true" showIcon iconDisplay="input" />
            <label>Von</label>
          </FloatLabel>
        </div>
        <div>
          <FloatLabel variant="on">
            <DatePicker v-model="formLuV.bis" dateFormat="dd.mm.yy" class="w-full" inputClass="w-full" />
            <label>Bis</label>
          </FloatLabel>
        </div>

        <div>
            <FloatLabel variant="on" class="w-full">
                <Select
                    v-model="formLuV.typ"
                    :options="luvTypen"
                    optionLabel="label"
                    optionValue="value"
                    class="w-full"
                />
                <label>LuV-Typ wählen</label>
            </FloatLabel>
        </div>
      </div>

      <!-- Abschnitt: Ausgangssituation -->
      <div class="mt-4">
        <FloatLabel variant="on">
          <Textarea
            v-model="formLuV.ausgangssituation"
            class="w-full"
            rows="8"
            cols="150"
            style="resize: none"
          />
          <label>Darstellung der individuellen Ausgangssituation</label>
        </FloatLabel>
      </div>

      <!-- Abschnitt: Zielvereinbarung -->
      <div class="mt-4">
        <FloatLabel variant="on">
          <Textarea
            v-model="formLuV.zielvereinbarung"
            class="w-full"
            rows="6"
             cols="150"
            style="resize: none"
          />
          <label>Schritte zur Zielvereinbarung</label>
        </FloatLabel>
      </div>

      <!-- Abschnitt: Qualifikationen -->
      <div class="mt-4">
        <FloatLabel variant="on">
          <Textarea
            v-model="formLuV.qualifikationen"
            class="w-full"
            rows="6"
            cols="150"
            style="resize: none"
          />
          <label>Im Berichtszeitraum erworbene Qualifikationen</label>
        </FloatLabel>
      </div>
    </template>

    <template #footer>
      <button
        @click="save"
        class="bg-zbb text-white px-4 py-2 rounded hover:bg-zbb-dark transition"
      >
        Speichern
      </button>
      <button
        @click="emit('close')"
        class="border px-4 py-2 rounded hover:bg-gray-100 transition"
      >
        Abbrechen
      </button>
    </template>
  </Modal>
</template>

<script setup>
import Modal from '@/Components/ModalForm.vue';
import { ref } from 'vue';
import Swal from 'sweetalert2';
import FloatLabel from 'primevue/floatlabel';
import Textarea from 'primevue/textarea';
import DatePicker from 'primevue/datepicker';
import axios from 'axios';
import Select from 'primevue/select';
import { router } from '@inertiajs/vue3';

const props = defineProps({
  visible: Boolean,
  teilnehmer: { Object, required: true },
});
console.log(props.teilnehmer);
const emit = defineEmits(['close', 'added']);

let formLuV = ref({
  teilnehmer_id: props.teilnehmer.id,
  typ: '',
  von: '',
  bis: '',
  ausgangssituation: '',
  zielvereinbarung: '',
  qualifikationen: '',
});

const resetForm = () => {
  formLuV.value = {
    teilnehmer_id: props.teilnehmer.id,
    typ: '',
    von: '',
    bis: '',
    ausgangssituation: '',
    zielvereinbarung: '',
    qualifikationen: '',
  };
};
const luvTypen = [
  { label: 'Start', value: 'Start' },
  { label: 'Verlauf', value: 'Verlauf' },
  { label: 'Abschluss', value: 'Abschluss' },
];

const save = async () => {
  try {
    const response = await axios.post(route('projekthasteilnehmer.luv.store'), formLuV.value);

    if (response.data.success) {

      // ✅ Erfolgsmeldung
      Swal.fire({
        icon: "success",
        title: "Erfolg!",
        text: response.data.message || "Eintrag erfolgreich gespeichert!",
        timer: 2500,
        showConfirmButton: false,
        toast: true,
        position: "center"
      });

      emit('added', response.data.luv);
      resetForm();
      emit('close');  // ← Modal schließt jetzt zuverlässig!
    }

  } catch (error) {

    // ❌ Validierungsfehler
    if (error.response?.status === 422) {
      Swal.fire({
        icon: "error",
        title: "Validierungsfehler",
        html: Object.values(error.response.data.errors).join("<br>"),
      });
      return;
    }

    // ❌ sonstige Fehler
    Swal.fire({
      icon: "error",
      title: "Fehler",
      text: "Es ist ein unerwarteter Fehler aufgetreten.",
    });
  }
};

</script>
