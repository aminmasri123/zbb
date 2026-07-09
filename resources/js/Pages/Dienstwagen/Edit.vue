<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import { router, Head } from '@inertiajs/vue3';
import { ref, computed } from 'vue';
import Select from 'primevue/select';
import FloatLabel from 'primevue/floatlabel';
import InputText from 'primevue/inputtext'; // ❗ Import vergessen
import Textarea from 'primevue/textarea';
import Swal from 'sweetalert2';
import DatePicker from 'primevue/datepicker';
import { formatDate, toLocalDateString } from '@/utils/dateFormat.js';

const props = defineProps({
  vehicle: Object,
  drivers: Array,
  locations: Array
});

// Listen
const fahrzeugtypen = ref([
  { label: 'PKW', value: 'PKW' },
  { label: 'LKW', value: 'LKW' },
  { label: 'Transporter', value: 'Transporter' },
  { label: 'Bus', value: 'Bus' },
  { label: 'Motorrad', value: 'Motorrad' },
  { label: 'Anhänger', value: 'Anhänger' }
]);

const kraftstoffarten = ref([
  { label: 'Benzin', value: 'Benzin' },
  { label: 'Diesel', value: 'Diesel' },
  { label: 'Elektro', value: 'Elektro' },
  { label: 'Hybrid', value: 'Hybrid' },
  { label: 'Wasserstoff', value: 'Wasserstoff' },
  { label: 'Gas (LPG/CNG)', value: 'Gas' }
]);

const statuswerte = ref([
  { label: 'verfügbar', value: 'verfügbar' },
  { label: 'in Nutzung', value: 'in Nutzung' },
  { label: 'Werkstatt', value: 'Werkstatt' },
  { label: 'außer Betrieb', value: 'außer Betrieb' }
]);

// Formular mit vorhandenen Fahrzeugdaten füllen
const form = ref({
  typ: props.vehicle.typ || "",
  kennzeichen: props.vehicle.kennzeichen || "",
  marke: props.vehicle.marke || "",
  modell: props.vehicle.modell || "",
  baujahr: props.vehicle.baujahr || "",
  kraftstoffart: props.vehicle.kraftstoffart || "",
  kilometerstand: props.vehicle.kilometerstand || "",
  standort_id: props.vehicle.standort_id || "",
  status: props.vehicle.status || "verfügbar",
  naechste_wartung: formatDate(props.vehicle.naechste_wartung) || "",
  bild: null,
  remove_image: false,
  fin: props.vehicle.fin || "",
  hsn_tsn: props.vehicle.hsn_tsn || "",
  tuev_bis: formatDate(props.vehicle.tuev_bis) || "",
  au_bis: formatDate(props.vehicle.au_bis) || "",
  oelwechsel_am: formatDate(props.vehicle.oelwechsel_am) || "",
  oelwechsel_km: props.vehicle.oelwechsel_km || "",
  versicherung_bis: formatDate(props.vehicle.versicherung_bis) || "",
  steuer_faellig_am: formatDate(props.vehicle.steuer_faellig_am) || "",
  inspektion_am: formatDate(props.vehicle.inspektion_am) || "",
  reifenwechsel_am: formatDate(props.vehicle.reifenwechsel_am) || "",
  leasing_bis: formatDate(props.vehicle.leasing_bis) || "",
  tankkarte: props.vehicle.tankkarte || "",
  notizen: props.vehicle.notizen || "",
  allowed_drivers: Array.isArray(props.vehicle.fahrer)
    ? props.vehicle.fahrer.map(d => typeof d === "object" ? d.id : d)
    : []
});


// Fahrerfilter
const fahrerSuche = ref("");
const gefilterteFahrer = computed(() => {
  return props.drivers.filter(d => {
    const fullName = `${d.vorname} ${d.nachname}`.toLowerCase();
    return fullName.includes(fahrerSuche.value.toLowerCase());
  });
});

// Update-Funktion
const dateFields = [
  'naechste_wartung',
  'tuev_bis',
  'au_bis',
  'oelwechsel_am',
  'versicherung_bis',
  'steuer_faellig_am',
  'inspektion_am',
  'reifenwechsel_am',
  'leasing_bis',
];

function update() {
  const payload = { ...form.value };

  dateFields.forEach((field) => {
    payload[field] = toLocalDateString(payload[field]);
  });

  router.post(route('dienstwagen.update', props.vehicle.id), {
    ...payload,
    _method: 'put',
  }, {
    forceFormData: true,
    onSuccess: () => {
      Swal.fire({
        title: '✅ Erfolgreich!',
        text: 'Die Fahrzeugdaten wurden gespeichert.',
        icon: 'success',
        confirmButtonText: 'OK',
        timer: 2500,
        timerProgressBar: true
      });
    },
    onError: () => {
      Swal.fire({
        title: '❌ Fehler',
        text: 'Beim Speichern ist ein Problem aufgetreten.',
        icon: 'error',
        confirmButtonText: 'OK'
      });
    }
  });
}
</script>

<template>
  <Head title="Dienstwagen bearbeiten" />
  <AppLayout>
    <template #header>✏️ {{ $t('Fahrzeug bearbeiten') }}</template>

    <form
      @submit.prevent="update"
      class="grid grid-cols-2 gap-6 bg-white dark:bg-gray-800 p-6 rounded shadow"
    >
      <!-- Fahrzeugtyp -->
      <FloatLabel variant="on">
        <Select
          v-model="form.typ"
          :options="fahrzeugtypen"
          optionLabel="label"
          optionValue="value"
          class="w-full"
        />
        <label>Fahrzeugtyp wählen</label>
      </FloatLabel>

      <!-- Kraftstoffart -->
      <FloatLabel variant="on">
        <Select
          v-model="form.kraftstoffart"
          :options="kraftstoffarten"
          optionLabel="label"
          optionValue="value"
          class="w-full"
        />
        <label>Kraftstoffart wählen</label>
      </FloatLabel>

      <!-- Kennzeichen -->
      <FloatLabel variant="on">
        <InputText v-model="form.kennzeichen" class="w-full" />
        <label>Kennzeichen*</label>
      </FloatLabel>

      <!-- Marke -->
      <FloatLabel variant="on">
        <InputText v-model="form.marke" class="w-full" />
        <label>Marke*</label>
      </FloatLabel>

      <!-- Modell -->
      <FloatLabel variant="on">
        <InputText v-model="form.modell" class="w-full" />
        <label>Modell*</label>
      </FloatLabel>

      <!-- Baujahr -->
      <FloatLabel variant="on">
        <InputText v-model="form.baujahr" type="number" class="w-full" />
        <label>Baujahr*</label>
      </FloatLabel>

      <!-- Kilometerstand -->
      <FloatLabel variant="on">
        <InputText v-model="form.kilometerstand" type="number" class="w-full" />
        <label>Kilometerstand*</label>
      </FloatLabel>

      <!-- Standort -->
      <FloatLabel variant="on">
        <Select
          v-model="form.standort_id"
          :options="locations.map(l => ({ label: l.name, value: l.id }))"
          optionLabel="label"
          optionValue="value"
          class="w-full"
        />
        <label>Standort wählen</label>
      </FloatLabel>

      <!-- Status -->
      <FloatLabel variant="on">
        <Select
          v-model="form.status"
          :options="statuswerte"
          optionLabel="label"
          optionValue="value"
          class="w-full"
        />
        <label>Status wählen</label>
      </FloatLabel>

      <!-- Nächste Wartung -->
      <FloatLabel variant="on">
        <DatePicker  v-model="form.naechste_wartung" dateFormat="dd.mm.yy" class="w-full" inputClass="w-full" showIcon iconDisplay="input" />
        <label>Nächste Wartung</label>
      </FloatLabel>

      <div class="col-span-2 grid grid-cols-2 gap-6 border-t pt-6">
        <div v-if="props.vehicle.bild_url" class="col-span-2 flex items-center gap-4 rounded border p-3">
          <img :src="props.vehicle.bild_url" class="h-24 w-36 rounded object-cover" alt="Fahrzeugbild" />
          <label class="flex items-center gap-2 text-sm">
            <input type="checkbox" v-model="form.remove_image" />
            Bild entfernen
          </label>
        </div>

        <FloatLabel variant="on">
          <InputText v-model="form.fin" class="w-full" />
          <label>FIN / Fahrgestellnummer</label>
        </FloatLabel>

        <FloatLabel variant="on">
          <InputText v-model="form.hsn_tsn" class="w-full" />
          <label>HSN / TSN</label>
        </FloatLabel>

        <FloatLabel variant="on">
          <DatePicker v-model="form.tuev_bis" dateFormat="dd.mm.yy" class="w-full" inputClass="w-full" showIcon iconDisplay="input" />
          <label>TÜV bis</label>
        </FloatLabel>

        <FloatLabel variant="on">
          <DatePicker v-model="form.au_bis" dateFormat="dd.mm.yy" class="w-full" inputClass="w-full" showIcon iconDisplay="input" />
          <label>AU bis</label>
        </FloatLabel>

        <FloatLabel variant="on">
          <DatePicker v-model="form.oelwechsel_am" dateFormat="dd.mm.yy" class="w-full" inputClass="w-full" showIcon iconDisplay="input" />
          <label>Ölwechsel am</label>
        </FloatLabel>

        <FloatLabel variant="on">
          <InputText v-model="form.oelwechsel_km" type="number" class="w-full" />
          <label>Ölwechsel bei km</label>
        </FloatLabel>

        <FloatLabel variant="on">
          <DatePicker v-model="form.versicherung_bis" dateFormat="dd.mm.yy" class="w-full" inputClass="w-full" showIcon iconDisplay="input" />
          <label>Versicherung bis</label>
        </FloatLabel>

        <FloatLabel variant="on">
          <DatePicker v-model="form.steuer_faellig_am" dateFormat="dd.mm.yy" class="w-full" inputClass="w-full" showIcon iconDisplay="input" />
          <label>Steuer fällig am</label>
        </FloatLabel>

        <FloatLabel variant="on">
          <DatePicker v-model="form.inspektion_am" dateFormat="dd.mm.yy" class="w-full" inputClass="w-full" showIcon iconDisplay="input" />
          <label>Inspektionstermin</label>
        </FloatLabel>

        <FloatLabel variant="on">
          <DatePicker v-model="form.reifenwechsel_am" dateFormat="dd.mm.yy" class="w-full" inputClass="w-full" showIcon iconDisplay="input" />
          <label>Reifenwechsel am</label>
        </FloatLabel>

        <FloatLabel variant="on">
          <DatePicker v-model="form.leasing_bis" dateFormat="dd.mm.yy" class="w-full" inputClass="w-full" showIcon iconDisplay="input" />
          <label>Leasing bis</label>
        </FloatLabel>

        <FloatLabel variant="on">
          <InputText v-model="form.tankkarte" class="w-full" />
          <label>Tankkarte / Ladekarte</label>
        </FloatLabel>

        <div class="col-span-2">
          <label class="block mb-2 font-semibold">Fahrzeugbild</label>
          <input type="file" accept="image/*" class="form-input" @change="form.bild = $event.target.files[0] || null" />
        </div>

        <FloatLabel variant="on" class="col-span-2">
          <Textarea v-model="form.notizen" rows="4" class="w-full" />
          <label>Interne Notizen</label>
        </FloatLabel>
      </div>

      <!-- Fahrer -->
      <div class="col-span-2">
        <label class="block mb-2 font-semibold">Zugelassene Fahrer</label>
        <input
          v-model="fahrerSuche"
          type="text"
          placeholder="🔍 Fahrer suchen..."
          class="form-input mb-3"
        />
        <div
          class="grid grid-cols-2 gap-2 overflow-y-auto p-2 border rounded-lg dark:border-gray-700 max-h-60"
        >
          <label
            v-for="d in gefilterteFahrer"
            :key="d.id"
            class="flex items-center gap-3"
          >
            <input type="checkbox" :value="d.id" v-model="form.allowed_drivers" />
            <span>{{ d.geschlecht === 'w' ? 'Frau' : 'Herr' }} {{ d.nachname }}, {{ d.vorname }}</span>
          </label>
        </div>
      </div>

      <!-- Speichern -->
      <div class="col-span-2 flex justify-end">
        <button
          type="submit"
          class="px-6 py-2 bg-blue-600 text-white rounded hover:bg-blue-700"
        >
          Änderungen speichern
        </button>
      </div>
    </form>
  </AppLayout>
</template>

<style>
.form-input {
  @apply w-full px-3 py-2 rounded border dark:bg-gray-900 dark:border-gray-700 dark:text-white;
}
</style>
