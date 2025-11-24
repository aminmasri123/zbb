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
import { ref, watch } from 'vue';
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

/* ---------------------------------------------------
   FORM -> wird automatisch mit Daten aus props.raum
   gefüllt, wenn das Modal geöffnet wird
---------------------------------------------------*/
const form = ref({
    id: null,
    name: '',
    standort_id: null,
    typ: null,
    beschreibung: '',
    kapazitaet: null,
});

// füllt die Formulardaten, wenn sich der zu bearbeitende Raum ändert
watch(
    () => props.raum,
    (raum) => {
        if (!raum) return;
        form.value = { ...raum }; // komplette Kopie
    },
    { immediate: true }
);

/* ---------------------------------------------------
   UPDATE-Funktion
---------------------------------------------------*/
const update = async () => {
    try {
        const response = await axios.put(
            route('raeumlichkeiten.update', form.value.id),
            form.value
        );

        Swal.fire('Erfolg!', 'Raum erfolgreich aktualisiert!', 'success');

        emit('updated', response.data.raum); // Daten an Index zurückgeben
        emit('close');

    } catch (error) {
        Swal.fire(
            'Fehler',
            error.response?.data?.error || 'Aktualisieren fehlgeschlagen',
            'error'
        );
    }
};
</script>
