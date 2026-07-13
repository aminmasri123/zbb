<template>
  <Modal v-if="visible" @close="emit('close')">
    <template #header>📘 Neues Praktikum hinzufügen</template>

    <template #body>
      <div class="grid grid-cols-2 gap-6">

        <!-- TYPO -->
        <div>
          <label class="text-sm text-gray-600">Typ <span class="text-red-500">*</span></label>
          <Select
            v-model="neuesPraktikum.typ"
            :options="typen"
            optionLabel="label"
            optionValue="value"
            placeholder="-- auswählen --"
            class="w-full mt-1"
          />
        </div>

        <!-- TRÄGER -->
        <div>
          <label class="text-sm text-gray-600">Träger</label>
          <InputText
            v-model="neuesPraktikum.traeger"
            class="w-full mt-1"
            placeholder="z.B. Unternehmen, Schule..."
          />
        </div>

        <!-- STARTDATUM -->
        <div><label class="text-sm text-gray-600">Ansprechpartner</label><InputText v-model="neuesPraktikum.contact_name" class="w-full mt-1" /></div>
        <div><label class="text-sm text-gray-600">E-Mail</label><InputText v-model="neuesPraktikum.contact_email" type="email" class="w-full mt-1" /></div>
        <div><label class="text-sm text-gray-600">Telefon</label><InputText v-model="neuesPraktikum.contact_phone" class="w-full mt-1" /></div>
        <div><label class="text-sm text-gray-600">Wochenstunden</label><InputText v-model.number="neuesPraktikum.weekly_hours" type="number" min="1" max="168" class="w-full mt-1" /></div>

        <!-- STARTDATUM -->
        <div>
          <label class="text-sm text-gray-600">Startdatum <span class="text-red-500">*</span></label>
          <DatePicker
            v-model="neuesPraktikum.start"
            dateFormat="yy-mm-dd"
            class="w-full mt-1"
            inputClass="w-full"
          />
        </div>

        <!-- ENDDATUM -->
        <div>
          <label class="text-sm text-gray-600">Enddatum <span class="text-red-500">*</span></label>
          <DatePicker
            v-model="neuesPraktikum.end"
            dateFormat="yy-mm-dd"
            class="w-full mt-1"
            inputClass="w-full"
          />
        </div>

        <!-- STATUS -->
        <div>
          <label class="text-sm text-gray-600">Status</label>
          <Select
            v-model="neuesPraktikum.status"
            :options="statusOptionen"
            optionLabel="label"
            optionValue="value"
            class="w-full mt-1"
          />
        </div>

        <!-- BEMERKUNG -->
        <div><label class="text-sm text-gray-600">Nächste Nachverfolgung</label><DatePicker v-model="neuesPraktikum.next_follow_up_at" dateFormat="yy-mm-dd" class="w-full mt-1" inputClass="w-full" /></div>
        <div class="col-span-2"><label class="text-sm text-gray-600">Ziel</label><Textarea v-model="neuesPraktikum.objective" class="w-full mt-1" rows="3" /></div>
        <div v-if="['abgeschlossen','abgebrochen'].includes(neuesPraktikum.status)" class="col-span-2"><label class="text-sm text-gray-600">Ergebnis <span class="text-red-500">*</span></label><Textarea v-model="neuesPraktikum.result" class="w-full mt-1" rows="3" /></div>

        <!-- BEMERKUNG -->
        <div>
          <label class="text-sm text-gray-600">Bemerkung</label>
          <Textarea
            v-model="neuesPraktikum.bemerkung"
            class="w-full mt-1"
            rows="3"
          />
        </div>

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
import Textarea from 'primevue/textarea';
import DatePicker from 'primevue/datepicker';
import InputText from 'primevue/inputtext';
import Select from 'primevue/select';
import { router } from '@inertiajs/vue3';
import { formatDate } from '@/utils/dateFormat.js';

const props = defineProps({
  visible: Boolean,
  teilnehmer: { Object, required: true },
});

const emit = defineEmits(['close', 'added']);

let neuesPraktikum = ref({
  teilnehmer_id: props.teilnehmer?.id ?? null,
  typ: '',
  traeger: '',
  contact_name: '', contact_email: '', contact_phone: '', weekly_hours: null,
  start: '',
  end: '',
  bemerkung: '',
  objective: '', result: '', next_follow_up_at: '',
  status: 'geplant',
});

const resetForm = () => {
  neuesPraktikum.value = {
    teilnehmer_id: props.teilnehmer.id,
    typ: '',
    traeger: '',
    contact_name: '', contact_email: '', contact_phone: '', weekly_hours: null,
    start: '',
    end: '',
    bemerkung: '',
    objective: '', result: '', next_follow_up_at: '',
    status: 'geplant',
  };
};

const save = async () => {
  try {
    const payload = {
      teilnehmer_id: props.teilnehmer.id,
      typ: neuesPraktikum.value.typ,
      traeger: neuesPraktikum.value.traeger,
      contact_name: neuesPraktikum.value.contact_name,
      contact_email: neuesPraktikum.value.contact_email,
      contact_phone: neuesPraktikum.value.contact_phone,
      weekly_hours: neuesPraktikum.value.weekly_hours,
      start: formatDate(neuesPraktikum.value.start),
      end: formatDate(neuesPraktikum.value.end),
      bemerkung: neuesPraktikum.value.bemerkung,
      objective: neuesPraktikum.value.objective,
      result: neuesPraktikum.value.result,
      next_follow_up_at: neuesPraktikum.value.next_follow_up_at ? formatDate(neuesPraktikum.value.next_follow_up_at) : null,
      status: neuesPraktikum.value.status,
    };

    const response = await axios.post(route('teilnehmer.praktikum.store'), payload);

    // Erfolgsmeldung
    Swal.fire({
      icon: "success",
      title: "Erfolg!",
      text: "Eintrag erfolgreich gespeichert!",
      timer: 2500,
      showConfirmButton: false,
      toast: true,
      position: "center"
    });

    // Neuen Datensatz an Eltern-Komponente senden
    emit('added', response.data.data);
    console.log(response.data.data)
    resetForm();
    emit('close');

  } catch (error) {

    console.error("Axios Fehler:", error.response?.data ?? error);

    // Laravel-Validation Fehler (422)
    if (error.response?.status === 422) {
      Swal.fire({
        icon: "error",
        title: "Validierungsfehler",
        html: Object.values(error.response.data.errors).join("<br>"),
      });
      return;
    }

    // Allgemeiner Fehler
    Swal.fire({
      icon: "error",
      title: "Fehler",
      text: error.response?.data?.message || "Es ist ein unerwarteter Fehler aufgetreten.",
    });
  }
};


const typen = [
  { label: 'Praktikum', value: 'Praktikum' },
  { label: 'Fortbildung', value: 'Fortbildung' },
  { label: 'Schulung', value: 'Schulung' },
  { label: 'Weiterbildung', value: 'Weiterbildung' },
  { label: 'Sprachkurs', value: 'Sprachkurs' },
  { label: 'Integrationskurs', value: 'Integrationskurs' },
];

const statusOptionen = [
  { label: 'Geplant', value: 'geplant' },
  { label: 'Laufend', value: 'laufend' },
  { label: 'Abgeschlossen', value: 'abgeschlossen' },
  { label: 'Abgebrochen', value: 'abgebrochen' },
];
</script>
