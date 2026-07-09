<template>
    <Modal v-if="visible" @close="emit('close')">
        <template #header>Raum bearbeiten</template>

        <template #body>
            <div class="grid grid-cols-1 gap-8">

                <!-- Name -->
                <div>
                    <FloatLabel>
                        <InputText v-model="form.name" class="w-full" />
                        <label>Bezeichnung</label>
                    </FloatLabel>
                </div>

                <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                    <FloatLabel>
                        <InputText v-model="form.raumnummer" class="w-full" />
                        <label>Raumnummer</label>
                    </FloatLabel>

                    <FloatLabel>
                        <InputText v-model="form.etage" class="w-full" />
                        <label>Etage / Bereich</label>
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

                <div>
                    <FloatLabel>
                        <Dropdown
                            v-model="form.status"
                            :options="statusOptionen"
                            optionLabel="label"
                            optionValue="value"
                            class="w-full"
                        />
                        <label>Status</label>
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
                        <Dropdown
                            v-model="form.verantwortliche_personen_id"
                            :options="personalOptionen"
                            optionLabel="label"
                            optionValue="id"
                            showClear
                            class="w-full"
                        />
                        <label>Verantwortliche Person</label>
                    </FloatLabel>
                </div>

                <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                    <FloatLabel>
                        <InputNumber
                            v-model="form.kapazitaet"
                            class="w-full"
                            :min="0"
                        />
                        <label>Kapazität</label>
                    </FloatLabel>

                    <FloatLabel>
                        <InputNumber
                            v-model="form.flaeche_qm"
                            class="w-full"
                            :min="0"
                            :minFractionDigits="0"
                            :maxFractionDigits="2"
                        />
                        <label>Fläche qm</label>
                    </FloatLabel>
                </div>

                <!-- Beschreibung -->
                <div>
                    <FloatLabel>
                        <Textarea v-model="form.beschreibung" class="w-full" />
                        <label>Beschreibung</label>
                    </FloatLabel>
                </div>

                <div class="grid grid-cols-1 gap-3 md:grid-cols-2">
                    <label class="inline-flex items-center gap-2 text-sm text-slate-700">
                        <input v-model="form.aktiv" type="checkbox" class="rounded border-slate-300 text-zbb focus:ring-zbb" />
                        Aktiv verfügbar
                    </label>

                    <label class="inline-flex items-center gap-2 text-sm text-slate-700">
                        <input v-model="form.buchbar" type="checkbox" class="rounded border-slate-300 text-zbb focus:ring-zbb" />
                        Buchbar
                    </label>
                </div>

            </div>
        </template>

        <template #footer>
            <button @click="update" class="bg-zbb text-white px-4 py-2 rounded">
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
  raum: Object,     // <-- der Raum, der bearbeitet wird
});

const emit = defineEmits(['close', 'updated']);

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

const statusOptionen = [
  { label: 'Verfügbar', value: 'verfuegbar' },
  { label: 'Eingeschränkt', value: 'eingeschraenkt' },
  { label: 'In Wartung', value: 'wartung' },
  { label: 'Gesperrt', value: 'gesperrt' },
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
    (standort.raeume || [])
      .filter((raum) => raum.id !== props.raum?.id)
      .map((raum) => ({
        id: raum.id,
        label: `${raum.name} (${standort.name})`,
      }))
  )
);

/* ---------------------------------------------------
   FORM -> wird automatisch mit Daten aus props.raum
   gefüllt, wenn das Modal geöffnet wird
---------------------------------------------------*/
const form = ref({
    id: null,
    name: '',
    raumnummer: '',
    etage: '',
    standort_id: null,
    parent_id: null,
    typ: null,
    belegungsart: 'frei',
    status: 'verfuegbar',
    standard_personen_id: null,
    verantwortliche_personen_id: null,
    beschreibung: '',
    kapazitaet: null,
    flaeche_qm: null,
    aktiv: true,
    buchbar: true,
});

// füllt die Formulardaten, wenn sich der zu bearbeitende Raum ändert
watch(
    () => props.raum,
    (raum) => {
        if (!raum) return;
        form.value = {
            ...raum,
            belegungsart: raum.belegungsart ?? 'frei',
            status: raum.status ?? 'verfuegbar',
            aktiv: raum.aktiv ?? true,
            buchbar: raum.buchbar ?? true,
        }; // komplette Kopie
    },
    { immediate: true }
);

watch(
    () => form.value.belegungsart,
    (belegungsart) => {
        if (!belegungsartenMitStandardPerson.includes(belegungsart)) {
            form.value.standard_personen_id = null;
        }
    }
);

/* ---------------------------------------------------
   UPDATE-Funktion
---------------------------------------------------*/
const update = async () => {
    try {
        if (standardPersonPflicht.value && !form.value.standard_personen_id) {
            Swal.fire('Fehler', 'Bitte eine Standard-Person waehlen.', 'warning');
            return;
        }

        const payload = {
            ...form.value,
            standard_personen_id: standardPersonSichtbar.value ? form.value.standard_personen_id : null,
        };

        const response = await axios.put(
            route('raeumlichkeiten.update', form.value.id),
            payload
        );

        Swal.fire('Erfolg!', 'Raum erfolgreich aktualisiert!', 'success');

        emit('updated', response.data.raum); // Daten an Index zurückgeben
        emit('close');

    } catch (error) {
        Swal.fire(
            'Fehler',
            error.response?.data?.message || error.response?.data?.error || 'Aktualisieren fehlgeschlagen',
            'error'
        );
    }
};
</script>
