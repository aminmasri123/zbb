<script setup>
import Modal from '@/Components/ModalForm.vue';
import { ref, watch } from 'vue';
import Swal from 'sweetalert2';

import FloatLabel from 'primevue/floatlabel';
import InputText from 'primevue/inputtext';
import Textarea from 'primevue/textarea';
import MultiSelect from 'primevue/multiselect';
import Dropdown from 'primevue/dropdown';

const props = defineProps({
    visible: Boolean,
    partnerschaftstypen: Array,
    toEdit: Object,
    kontaktypens: Array,
});
const emit = defineEmits(['close', 'updated']);

const geschlechtOptions = [
    { label: 'Maennlich', value: 'm' },
    { label: 'Weiblich', value: 'w' },
    { label: 'Divers', value: 'd' },
];

let form = ref({
    name: '',
    beschreibung: '',
    typ: [],

    adresse: {
        strasse: '',
        hausnummer: '',
        plz: '',
        stadt: ''
    },

    kontakte: [],

    ansprechpartner: []
});

// 🔄 Daten laden, sobald Modal geöffnet wird
watch(
  () => props.toEdit,
  (val) => {
    if (!val) return;

    form.value = {
      name: val.name ?? '',
      beschreibung: val.beschreibung ?? '',
      typ: val.partnerschaftstypens
        ? [...new Set(val.partnerschaftstypens.map(t => t.id))]
        : [],

      ansprechpartner: val.ansprechpartners?.map(p => ({
        id: p.id ?? null,
        vorname: p.vorname ?? '',
        nachname: p.nachname ?? '',
        geschlecht: p.geschlecht ?? '',
        typ: p.pivot?.rolle ?? p.rolle ?? '',

        adresse: {
          strasse: p.adresses?.[0]?.strasse ?? '',
          hausnummer: p.adresses?.[0]?.hausnummer ?? '',
          plz: p.adresses?.[0]?.plz ?? '',
          stadt: p.adresses?.[0]?.stadt ?? ''
        },

        kontakte: p.kontaktes?.map(k => ({
          kontakttyp_id: k.kontakttyp_id ?? '',
          wert: k.wert ?? '',
          bemerkung: k.bemerkung ?? ''
        })) ?? []
      })) ?? []
    };
  },
  { immediate: true }
);
const addAnsprechpartner = () => {
  form.value.ansprechpartner.push({
    id: null,
    vorname: '',
    nachname: '',
    geschlecht: '',
    typ: '',
    adresse: { strasse:'', hausnummer:'', plz:'', stadt:'' },
    kontakte: []
  });
};

const removeAnsprechpartner = (i) => {
    form.value.ansprechpartner.splice(i, 1);
};

// Save-Funktion
const save = () => {
    // Alle leeren Felder initialisieren
    form.value.ansprechpartner = form.value.ansprechpartner.map(p => ({
        ...p,
        adresse: p.adresse ?? { strasse:'', hausnummer:'', plz:'', stadt:'' },
        kontakte: p.kontakte ?? []
    }));

    emit("updated", JSON.parse(JSON.stringify(form.value)));
};
</script>

<template>
  <Modal v-if="visible" @close="emit('close')">

    <template #header>
        <h2 class="text-lg font-bold text-gray-600">Partner bearbeiten</h2>
    </template>

    <template #body>

      <div class="max-h-[70vh] overflow-y-auto pr-3">

        <FloatLabel class="mb-3">
            <InputText v-model="form.name" class="w-full" />
            <label>Partnername</label>
        </FloatLabel>

        <FloatLabel class="mb-3">
            <MultiSelect
                v-model="form.typ"
                :options="partnerschaftstypen"
                optionLabel="bezeichnung"
                optionValue="id"
                display="chip"
                filter
                class="w-full"
            />
            <label>Partnerschaftstypen</label>
        </FloatLabel>

        <FloatLabel class="mb-3">
            <Textarea v-model="form.beschreibung" rows="4" class="w-full" />
            <label>Beschreibung</label>
        </FloatLabel>

        <hr class="my-4" />

        <h3 class="font-semibold text-gray-600 mb-2">Ansprechpartner</h3>

        <div
            v-for="(p, index) in form.ansprechpartner"
            :key="p.id ?? index"
            class="bg-white border rounded-lg shadow-sm p-4 mb-4"
            >

<h4 class="font-semibold text-gray-700 mb-3">
    Ansprechpartner {{ index + 1 }}
</h4>

<!-- PERSON -->
<div class="grid grid-cols-2 gap-3 mb-4">

    <FloatLabel variant="in">
        <InputText v-model="p.vorname" class="w-full"/>
        <label>Vorname</label>
    </FloatLabel>

    <FloatLabel variant="in">
        <InputText v-model="p.nachname" class="w-full"/>
        <label>Nachname</label>
    </FloatLabel>

    <FloatLabel variant="in">
        <Dropdown
            v-model="p.geschlecht"
            :options="geschlechtOptions"
            optionLabel="label"
            optionValue="value"
            class="w-full"
        />
        <label>Geschlecht</label>
    </FloatLabel>

    <FloatLabel variant="in">
        <InputText v-model="p.typ" class="w-full"/>
        <label>Rolle / Funktion</label>
    </FloatLabel>

</div>


<!-- ADRESSE -->
<div class="border-t pt-4 mb-4">
  <h5 class="text-gray-600 font-medium mb-2">Adresse</h5>

  <div class="grid grid-cols-2 gap-3">
    <FloatLabel variant="in">
      <InputText v-model="p.adresse.strasse" class="w-full"/>
      <label>Straße</label>
    </FloatLabel>

    <FloatLabel variant="in">
      <InputText v-model="p.adresse.hausnummer" class="w-full"/>
      <label>Hausnummer</label>
    </FloatLabel>

    <FloatLabel variant="in">
      <InputText v-model="p.adresse.plz" class="w-full"/>
      <label>PLZ</label>
    </FloatLabel>

    <FloatLabel variant="in">
      <InputText v-model="p.adresse.stadt" class="w-full"/>
      <label>Stadt</label>
    </FloatLabel>
  </div>
</div>

<!-- KONTAKTE -->
<div class="border-t pt-4">
  <h5 class="text-gray-600 font-medium mb-2">Kontakte</h5>

  <div v-for="(k, i) in p.kontakte" :key="i" class="grid grid-cols-3 gap-3 mb-2">
    <FloatLabel variant="in">
      <Dropdown v-model="k.kontakttyp_id" :options="kontaktypens" optionLabel="name" optionValue="id" class="w-full" placeholder="Typ"/>
      <label>Typ</label>
    </FloatLabel>

    <FloatLabel variant="in">
      <InputText v-model="k.wert" class="w-full"/>
      <label>Kontakt</label>
    </FloatLabel>

    <FloatLabel variant="in">
      <InputText v-model="k.bemerkung" class="w-full"/>
      <label>Bemerkung</label>
    </FloatLabel>
  </div>

  <button type="button" class="text-sm bg-gray-100 px-3 py-1 rounded hover:bg-gray-200" 
          @click="p.kontakte.push({kontakttyp_id:'',wert:'',bemerkung:''})">
    + Kontakt hinzufügen
  </button>
</div>


<!-- DELETE PERSON -->
<button
  v-if="form.ansprechpartner.length > 1"
  @click="removeAnsprechpartner(index)"
  class="text-red-500 text-sm mt-3"
>
Ansprechpartner entfernen
</button>

</div>

        <button
          class="bg-gray-200 px-3 py-1 rounded text-sm"
          @click="addAnsprechpartner"
        >
          + Ansprechpartner hinzufügen
        </button>

      </div>

    </template>

    <template #footer>
        <button @click="save" class="bg-zbb text-white px-4 py-2 rounded">Speichern</button>
        <button @click="emit('close')" class="px-4 py-2 rounded border">Abbrechen</button>
    </template>

  </Modal>
</template>
