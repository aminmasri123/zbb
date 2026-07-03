<template>
    <Modal v-if="visible" @close="emit('close')">
        <template #header>Raum anlegen</template>

        <template #body>
            <div class="grid grid-cols-1 gap-8">

                <!-- Name -->
                <div>
                    <FloatLabel>
                        <InputText v-model="form.name" class="w-full" />
                        <label>Bezeichnung</label>
                    </FloatLabel>
                </div>

                <!-- Standort -->
                <div>
                    <FloatLabel>
                        <Dropdown
                            v-model="form.standort_id"
                            :options="standorte"
                            optionLabel="name"
                            optionValue="id"
                            class="w-full"
                        />
                        <label>Standort wählen</label>
                    </FloatLabel>
                </div>

                <div>
                    <FloatLabel>
                        <Dropdown
                            v-model="form.parent_id"
                            :options="raumOptionen"
                            optionLabel="label"
                            optionValue="id"
                            showClear
                            class="w-full"
                        />
                        <label>Innerhalb von Raum</label>
                    </FloatLabel>
                </div>

                <!-- Raumtyp -->
                <div>
                    <FloatLabel>
                        <Dropdown
                            v-model="form.typ"
                            :options="raumtypen"
                            class="w-full"
                        />
                        <label>Raumtyp wählen</label>
                    </FloatLabel>
                </div>

                <!-- Kapazität -->
                <div>
                    <FloatLabel>
                        <Dropdown
                            v-model="form.belegungsart"
                            :options="belegungsarten"
                            optionLabel="label"
                            optionValue="value"
                            class="w-full"
                        />
                        <label>Belegungsart</label>
                    </FloatLabel>
                </div>

                <div v-if="standardPersonSichtbar">
                    <FloatLabel>
                        <Dropdown
                            v-model="form.standard_personen_id"
                            :options="personalOptionen"
                            optionLabel="label"
                            optionValue="id"
                            showClear
                            class="w-full"
                        />
                        <label>Standard-Person</label>
                    </FloatLabel>
                </div>

                <div>
                    <FloatLabel>
                        <InputNumber
                            v-model="form.kapazitaet"
                            class="w-full"
                            :min="0"
                        />
                        <label>Kapazität</label>
                    </FloatLabel>
                </div>

                <!-- Beschreibung -->
                <div>
                    <FloatLabel>
                        <Textarea v-model="form.beschreibung" class="w-full" />
                        <label>Beschreibung</label>
                    </FloatLabel>
                </div>

                <label class="inline-flex items-center gap-2 text-sm text-slate-700">
                    <input v-model="form.aktiv" type="checkbox" class="rounded border-slate-300 text-zbb focus:ring-zbb" />
                    Aktiv verfuegbar
                </label>

            </div>
        </template>

        <template #footer>
            <button @click="save" class="bg-zbb text-white px-4 py-2 rounded">
                Speichern
            </button>
            <button @click="emit('close')" class="border px-4 py-2 rounded">
                Abbrechen
            </button>
        </template>
    </Modal>
</template>

<script setup>
import Modal from '@/Components/ModalForm.vue';
import { computed, ref, watch } from 'vue';
import Swal from 'sweetalert2';
import FloatLabel from 'primevue/floatlabel';
import InputText from 'primevue/inputtext';
import InputNumber from 'primevue/inputnumber';
import Dropdown from 'primevue/dropdown';
import Textarea from 'primevue/textarea';
import axios from 'axios';

const props = defineProps({
  visible: Boolean,
  standorte: Array,
  personal: {
    type: Array,
    default: () => [],
  },
});

const emit = defineEmits(['close', 'added']);

// ENUM-Typen
const raumtypen = [
  'Büro', 'Elektroraum', 'Unterrichtsraum', 'Seminarraum', 'Besprechungsraum',
  'Labor', 'Werkstatt', 'Lager', 'Küche', 'Aufenthaltsraum', 'Sanitärraum',
  'Empfang', 'Serverraum', 'Archiv', 'Aula', 'Bibliothek', 'Arbeitsplatz',
  'Copyroom', 'Technikraum', 'Hauswirtschaftsraum', 'Holzbereich', 'Metallbereich'
];

const belegungsarten = [
  { label: 'Frei vergebbar', value: 'frei' },
  { label: 'Meist feste Belegung', value: 'standard' },
  { label: 'Teilweise belegt', value: 'teilweise' },
  { label: 'Blockiert', value: 'blockiert' },
];

const belegungsartenMitStandardPerson = ['standard', 'teilweise'];

const standardPersonSichtbar = computed(() =>
  belegungsartenMitStandardPerson.includes(form.value.belegungsart)
);

const standardPersonPflicht = computed(() => form.value.belegungsart === 'standard');

const personalOptionen = computed(() =>
  (props.personal || []).map((person) => ({
    ...person,
    label: `${person.vorname ?? ''} ${person.nachname ?? ''}`.trim(),
  }))
);

const raumOptionen = computed(() =>
  (props.standorte || []).flatMap((standort) =>
    (standort.raeume || []).map((raum) => ({
      id: raum.id,
      label: `${raum.name} (${standort.name})`,
    }))
  )
);

let form = ref({
  name: '',
  standort_id: null,
  parent_id: null,
  typ: null,
  belegungsart: 'frei',
  standard_personen_id: null,
  beschreibung: '',
  kapazitaet: null,
  aktiv: true,
});

watch(
  () => form.value.belegungsart,
  (belegungsart) => {
    if (!belegungsartenMitStandardPerson.includes(belegungsart)) {
      form.value.standard_personen_id = null;
    }
  }
);

const resetForm = () => {
  form.value = {
    name: '',
    standort_id: null,
    parent_id: null,
    typ: null,
    belegungsart: 'frei',
    standard_personen_id: null,
    beschreibung: '',
    kapazitaet: null,
    aktiv: true,
  };
};

const save = async () => {
  try {
    if (standardPersonPflicht.value && !form.value.standard_personen_id) {
      Swal.fire('Fehler', 'Bitte eine Standard-Person waehlen.', 'warning');
      return;
    }

    const payload = {
      ...form.value,
      standard_personen_id: standardPersonSichtbar.value ? form.value.standard_personen_id : null,
    };

    const response = await axios.post(route('raeumlichkeiten.store'), payload);

    Swal.fire('Erfolg!', 'Raum erfolgreich angelegt!', 'success');

    emit('added', response.data.raum);
    resetForm();
    emit('close');

  } catch (error) {
    Swal.fire('Fehler', error.response?.data?.message || error.response?.data?.error || 'Speichern fehlgeschlagen', 'error');
  }
};
</script>
