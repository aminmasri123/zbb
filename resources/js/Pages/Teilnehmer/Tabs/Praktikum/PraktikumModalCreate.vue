<template>
  <Modal v-if="visible" @close="emit('close')">
    <template #header>Neues Praktikum oder neue Bildungsmaßnahme</template>

    <template #body>
      <div class="grid grid-cols-1 gap-5 md:grid-cols-2">
        <div>
          <label class="text-sm text-gray-600">Typ <span class="text-red-500">*</span></label>
          <Select v-model="form.typ" :options="typen" optionLabel="label" optionValue="value" class="mt-1 w-full" />
        </div>

        <div v-if="isInternship">
          <label class="text-sm text-gray-600">Praktikumsart <span class="text-red-500">*</span></label>
          <Select v-model="form.placement_type" :options="placementTypes" optionLabel="label" optionValue="value" class="mt-1 w-full" />
        </div>

        <template v-if="isInternship && form.placement_type === 'internal'">
          <div>
            <label class="text-sm text-gray-600">Internes Einsatzprojekt <span class="text-red-500">*</span></label>
            <Select v-model="form.host_project_id" :options="hostProjects" optionLabel="name" optionValue="id" filter class="mt-1 w-full" />
          </div>
          <div>
            <label class="text-sm text-gray-600">Fachliche Betreuung <span class="text-red-500">*</span></label>
            <Select v-model="form.supervisor_person_id" :options="availableSupervisors" optionLabel="label" optionValue="id" filter class="mt-1 w-full" />
          </div>
        </template>

        <template v-else>
          <div>
            <label class="text-sm text-gray-600">Träger / Praktikumsbetrieb <span v-if="isInternship" class="text-red-500">*</span></label>
            <InputText v-model="form.traeger" class="mt-1 w-full" placeholder="Unternehmen, Schule oder Bildungsträger" />
          </div>
          <div>
            <label class="text-sm text-gray-600">Ansprechpartner:in</label>
            <InputText v-model="form.contact_name" class="mt-1 w-full" />
          </div>
          <div>
            <label class="text-sm text-gray-600">E-Mail</label>
            <InputText v-model="form.contact_email" type="email" class="mt-1 w-full" />
          </div>
          <div>
            <label class="text-sm text-gray-600">Telefon</label>
            <InputText v-model="form.contact_phone" class="mt-1 w-full" />
          </div>
        </template>

        <template v-if="isInternship">
          <div>
            <label class="text-sm text-gray-600">Anschrift der Einsatzstelle</label>
            <InputText v-model="form.host_address" class="mt-1 w-full" placeholder="Straße, PLZ Ort" />
          </div>
          <div>
            <label class="text-sm text-gray-600">Einsatzbereich / Abteilung</label>
            <InputText v-model="form.department" class="mt-1 w-full" />
          </div>
          <div>
            <label class="text-sm text-gray-600">Beruf / Tätigkeitsbereich</label>
            <InputText v-model="form.occupation" class="mt-1 w-full" />
          </div>
          <div>
            <label class="text-sm text-gray-600">Art des Praktikums</label>
            <Select v-model="form.internship_kind" :options="internshipKinds" optionLabel="label" optionValue="value" showClear class="mt-1 w-full" />
          </div>
          <div>
            <label class="text-sm text-gray-600">Interner Betreuungs-/Beschulungstag</label>
            <InputText v-model="form.attendance_weekday" class="mt-1 w-full" placeholder="z. B. Mittwoch" />
          </div>
        </template>

        <div>
          <label class="text-sm text-gray-600">Wochenstunden</label>
          <InputText v-model.number="form.weekly_hours" type="number" min="1" max="168" class="mt-1 w-full" />
        </div>
        <div>
          <label class="text-sm text-gray-600">Startdatum <span class="text-red-500">*</span></label>
          <DatePicker v-model="form.start" dateFormat="dd.mm.yy" class="mt-1 w-full" inputClass="w-full" />
        </div>
        <div>
          <label class="text-sm text-gray-600">Enddatum <span class="text-red-500">*</span></label>
          <DatePicker v-model="form.end" dateFormat="dd.mm.yy" class="mt-1 w-full" inputClass="w-full" />
        </div>
        <div>
          <label class="text-sm text-gray-600">Status</label>
          <Select v-model="form.status" :options="statusOptions" optionLabel="label" optionValue="value" class="mt-1 w-full" />
        </div>
        <div>
          <label class="text-sm text-gray-600">Nächste Nachverfolgung</label>
          <DatePicker v-model="form.next_follow_up_at" dateFormat="dd.mm.yy" class="mt-1 w-full" inputClass="w-full" showClear />
        </div>

        <div class="md:col-span-2">
          <label class="text-sm text-gray-600">Ziel</label>
          <Textarea v-model="form.objective" class="mt-1 w-full" rows="3" />
        </div>
        <div v-if="isInternship" class="md:col-span-2">
          <label class="text-sm text-gray-600">Tätigkeiten für die Bescheinigung</label>
          <Textarea v-model="form.activities" class="mt-1 w-full" rows="4" placeholder="Eine Tätigkeit pro Zeile, maximal sechs Zeilen" />
        </div>
        <div v-if="isInternship" class="md:col-span-2">
          <label class="text-sm text-gray-600">Beurteilung und soziale Kompetenzen</label>
          <Textarea v-model="form.assessment" class="mt-1 w-full" rows="5" placeholder="Bis zu vier kurze Absätze, jeweils eine Zeile" />
        </div>
        <div v-if="['abgeschlossen','abgebrochen'].includes(form.status)" class="md:col-span-2">
          <label class="text-sm text-gray-600">Ergebnis <span class="text-red-500">*</span></label>
          <Textarea v-model="form.result" class="mt-1 w-full" rows="3" />
        </div>
        <div class="md:col-span-2">
          <label class="text-sm text-gray-600">Bemerkung / weiterführende Vereinbarung</label>
          <Textarea v-model="form.bemerkung" class="mt-1 w-full" rows="3" />
        </div>
      </div>
    </template>

    <template #footer>
      <button type="button" :disabled="saving" @click="save" class="rounded bg-zbb px-4 py-2 text-white transition hover:bg-zbb-dark disabled:opacity-50">
        {{ saving ? 'Speichert …' : 'Speichern' }}
      </button>
      <button type="button" :disabled="saving" @click="emit('close')" class="rounded border px-4 py-2 transition hover:bg-gray-100">Abbrechen</button>
    </template>
  </Modal>
</template>

<script setup>
import Modal from '@/Components/ModalForm.vue';
import { computed, ref, watch } from 'vue';
import Swal from 'sweetalert2';
import Textarea from 'primevue/textarea';
import DatePicker from 'primevue/datepicker';
import InputText from 'primevue/inputtext';
import Select from 'primevue/select';
import { formatDate } from '@/utils/dateFormat.js';

const props = defineProps({
  visible: Boolean,
  teilnehmer: { type: Object, required: true },
  hostProjects: { type: Array, default: () => [] },
});
const emit = defineEmits(['close', 'added']);
const saving = ref(false);

const emptyForm = () => ({
  teilnehmer_id: props.teilnehmer?.id ?? null,
  typ: 'Praktikum',
  placement_type: 'external',
  traeger: '',
  host_project_id: null,
  supervisor_person_id: null,
  host_address: '',
  department: '',
  internship_kind: null,
  occupation: '',
  attendance_weekday: '',
  contact_name: '',
  contact_email: '',
  contact_phone: '',
  weekly_hours: null,
  start: '',
  end: '',
  next_follow_up_at: '',
  objective: '',
  activities: '',
  assessment: '',
  result: '',
  bemerkung: '',
  status: 'geplant',
});
const form = ref(emptyForm());
const isInternship = computed(() => form.value.typ === 'Praktikum');
const selectedHostProject = computed(() => props.hostProjects.find((project) => project.id === form.value.host_project_id));
const availableSupervisors = computed(() => (selectedHostProject.value?.mitarbeiter || []).map((person) => ({
  ...person,
  label: `${person.vorname} ${person.nachname}`.trim(),
})));

watch(() => form.value.host_project_id, () => {
  if (!availableSupervisors.value.some((person) => person.id === form.value.supervisor_person_id)) {
    form.value.supervisor_person_id = null;
  }
});

watch(() => form.value.typ, (type) => {
  if (type !== 'Praktikum') {
    form.value.placement_type = null;
    form.value.host_project_id = null;
    form.value.supervisor_person_id = null;
  } else if (!form.value.placement_type) {
    form.value.placement_type = 'external';
  }
});

const save = async () => {
  saving.value = true;
  try {
    const payload = {
      ...form.value,
      teilnehmer_id: props.teilnehmer.id,
      start: formatDate(form.value.start),
      end: formatDate(form.value.end),
      next_follow_up_at: form.value.next_follow_up_at ? formatDate(form.value.next_follow_up_at) : null,
    };
    const response = await axios.post(route('teilnehmer.praktikum.store'), payload);
    emit('added', response.data.data);
    form.value = emptyForm();
    emit('close');
    Swal.fire({ icon: 'success', title: 'Gespeichert', text: response.data.message, timer: 2200, showConfirmButton: false, toast: true, position: 'top-end' });
  } catch (error) {
    const errors = error.response?.data?.errors;
    Swal.fire({
      icon: 'error',
      title: 'Speichern nicht möglich',
      html: errors ? Object.values(errors).flat().join('<br>') : (error.response?.data?.message || 'Es ist ein unerwarteter Fehler aufgetreten.'),
    });
  } finally {
    saving.value = false;
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
const placementTypes = [
  { label: 'Externes Praktikum', value: 'external' },
  { label: 'Internes Praktikum', value: 'internal' },
];
const internshipKinds = [
  { label: 'Orientierungspraktikum', value: 'orientation' },
  { label: 'Qualifizierungspraktikum', value: 'qualification' },
  { label: 'Eingliederungspraktikum', value: 'integration' },
];
const statusOptions = [
  { label: 'Geplant', value: 'geplant' },
  { label: 'Laufend', value: 'laufend' },
  { label: 'Abgeschlossen', value: 'abgeschlossen' },
  { label: 'Abgebrochen', value: 'abgebrochen' },
];
</script>
