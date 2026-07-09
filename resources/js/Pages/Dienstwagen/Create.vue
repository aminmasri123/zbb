<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import { router, Head } from '@inertiajs/vue3';
import { ref, computed  } from 'vue';
import Select from 'primevue/select';
import FloatLabel from 'primevue/floatlabel';
import DatePicker from 'primevue/datepicker';
import InputText from 'primevue/inputtext';
import Textarea from 'primevue/textarea';
import { toLocalDateString } from '@/utils/dateFormat.js';

const props = defineProps({
    drivers: Array,
    locations: Array
});
// Liste der möglichen Fahrzeugtypen
const fahrzeugtypen = ref([
  { label: 'PKW', value: 'PKW' },
  { label: 'LKW', value: 'LKW' },
  { label: 'Transporter', value: 'Transporter' },
  { label: 'Bus', value: 'Bus' },
  { label: 'Motorrad', value: 'Motorrad' },
  { label: 'Anhänger', value: 'Anhänger' }
]);
// Liste der möglichen Statuswerte
const statuswerte = ref([
  { label: 'verfügbar', value: 'verfügbar' },
  { label: 'in Nutzung', value: 'in Nutzung' },
  { label: 'Werkstatt', value: 'Werkstatt' },
  { label: 'außer Betrieb', value: 'außer Betrieb' },
]);

const form = ref({
    typ: "",
    kennzeichen: "",
    marke: "",
    modell: "",
    baujahr: "",
    kraftstoffart: "",
    kilometerstand: "",
    standort_id: "",
    status: "verfügbar",
    naechste_wartung: "",
    bild: null,
    fin: "",
    hsn_tsn: "",
    tuev_bis: "",
    au_bis: "",
    oelwechsel_am: "",
    oelwechsel_km: "",
    versicherung_bis: "",
    steuer_faellig_am: "",
    inspektion_am: "",
    reifenwechsel_am: "",
    leasing_bis: "",
    tankkarte: "",
    notizen: "",
    allowed_drivers: []
});

// Liste der Kraftstoffarten
const kraftstoffarten = ref([
  { label: 'Benzin', value: 'Benzin' },
  { label: 'Diesel', value: 'Diesel' },
  { label: 'Elektro', value: 'Elektro' },
  { label: 'Hybrid', value: 'Hybrid' },
  { label: 'Wasserstoff', value: 'Wasserstoff' },
  { label: 'Gas (LPG/CNG)', value: 'Gas' }
]);

const fahrerSuche = ref(""); // Eingabefeld für Fahrersuche

const gefilterteFahrer = computed(() => {
    return props.drivers.filter(d => {
        const fullName = `${d.vorname} ${d.nachname}`.toLowerCase();
        return fullName.includes(fahrerSuche.value.toLowerCase());
    });
});

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

function submit() {
    const payload = { ...form.value };

    dateFields.forEach((field) => {
        payload[field] = toLocalDateString(payload[field]);
    });

    router.post(route('dienstwagen.store'), payload, {
        forceFormData: true,
    });
}
</script>

<template>
          <Head title="Dienstwagen anlegen" />
    <AppLayout>

        <template #header>🚗  {{$t('Neues Fahrzeug hinzufügen')}}</template>

        <form @submit.prevent="submit" class="grid grid-cols-2 gap-6 bg-white dark:bg-gray-800 p-6 rounded shadow">

            <FloatLabel variant="on">
                <Select
                    v-model="form.typ" :options="fahrzeugtypen" optionLabel="label" optionValue="value" class="w-full"/>
                <label for="Fahrzeugtyp">Fahrzeugtyp wählen</label>
            </FloatLabel>
            <FloatLabel variant="on">
                <Select
                    v-model="form.kraftstoffart"
                    :options="kraftstoffarten"
                    optionLabel="label"
                    optionValue="value"
                    class="w-full"
                />
                <label for="kraftstoffart">Kraftstoffart wählen</label>
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
                    v-model="form.status" :options="statuswerte" optionLabel="label" optionValue="value" class="w-full"/>
                <label for="status">Fahrzeugtyp wählen</label>
            </FloatLabel>

	              <!-- Nächste Wartung -->
	            <FloatLabel variant="on" >
	                <DatePicker  v-model="form.naechste_wartung" dateFormat="dd-mm-yy" class="w-full" inputClass="w-full" showIcon iconDisplay="input" />
	                <label for="naechste_wartungs">Nächste Wartung</label>
	            </FloatLabel>

                <div class="col-span-2 grid grid-cols-2 gap-6 border-t pt-6">
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

	           <!-- Fahrerberechtigungen -->
	            <div class="col-span-2">
                <label class="block mb-2 font-semibold">Zugelassene Fahrer</label>

                <!-- Suchfeld -->
                <input
                    v-model="fahrerSuche"
                    type="text"
                    placeholder="🔍 Fahrer suchen..."
                    class="form-input mb-3"
                />

                <!-- Gefilterte Fahrer -->
                <div class="grid grid-cols-2 gap-2 overflow-y-auto p-2 border rounded-lg dark:border-gray-700">
                    <label v-for="d in gefilterteFahrer" :key="d.id" class="flex items-center gap-3">
                        <input
                            type="checkbox"
                            :value="d.id"
                            v-model="form.allowed_drivers"
                        />
                        <span>{{ d.geschlecht === 'w' ? 'Frau' : 'Herr' }} {{ d.nachname }}, {{ d.vorname }}</span>
                    </label>
                </div>
            </div>


            <div class="col-span-2 flex justify-end">
                <button
                    type="submit"
                    class="px-6 py-2 bg-blue-600 text-white rounded hover:bg-blue-700"
                >
                    Speichern
                </button>
            </div>

        </form>

    </AppLayout>
</template>

<style>
.form-input {
    @apply w-full px-3  rounded border dark:bg-gray-900 dark:border-gray-700 dark:text-white;
}
</style>
