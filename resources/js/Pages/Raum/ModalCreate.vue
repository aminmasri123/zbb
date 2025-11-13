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
import { ref } from 'vue';
import Swal from 'sweetalert2';
import FloatLabel from 'primevue/floatlabel';
import InputText from 'primevue/inputtext';
import InputNumber from 'primevue/inputnumber';
import Dropdown from 'primevue/dropdown';
import Textarea from 'primevue/textarea';
import axios from 'axios';

const props = defineProps({
  visible: Boolean,
  standorte: Array
});

const emit = defineEmits(['close', 'added']);

// ENUM-Typen
const raumtypen = [
  'Büro', 'Elektroraum', 'Unterrichtsraum', 'Seminarraum', 'Besprechungsraum',
  'Labor', 'Werkstatt', 'Lager', 'Küche', 'Aufenthaltsraum', 'Sanitärraum',
  'Empfang', 'Serverraum', 'Archiv', 'Aula', 'Bibliothek', 'Arbeitsplatz',
  'Copyroom', 'Technikraum', 'Hauswirtschaftsraum', 'Holzbereich', 'Metallbereich'
];

let form = ref({
  name: '',
  standort_id: null,
  typ: null,
  beschreibung: '',
  kapazitaet: null,
});

const resetForm = () => {
  form.value = {
    name: '',
    standort_id: null,
    typ: null,
    beschreibung: '',
    kapazitaet: null,
  };
};

const save = async () => {
  try {
    const response = await axios.post(route('raeumlichkeiten.store'), form.value);

    Swal.fire('Erfolg!', 'Raum erfolgreich angelegt!', 'success');
console.log("Antwort vom Server:", response.data);

    emit('added', response.data.raum);
    resetForm();
    emit('close');

  } catch (error) {
    console.log(error.response?.data)
    Swal.fire('Fehler', error.response?.data?.error || 'Speichern fehlgeschlagen', 'error');
  }
};
</script>
