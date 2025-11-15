<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import { router, Head } from '@inertiajs/vue3';
import { ref, computed  } from 'vue';
import Select from 'primevue/select';
import FloatLabel from 'primevue/floatlabel';
import DatePicker from 'primevue/datepicker';

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
    Kennzeichen: "",
    marke: "",
    modell: "",
    baujahr: "",
    kraftstoffart: "",
    kilometerstand: "",
    standort_id: "",
    status: "verfügbar",
    naechste_wartung: "",
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

function submit() {
    router.post(route('dienstwagen.store'), form.value);
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
