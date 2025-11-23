<script setup>
import { ref } from "vue";
import Swal from "sweetalert2";
import axios from "axios";
import MultiSelect from 'primevue/multiselect';
import { router, Link } from '@inertiajs/vue3';
import Select from 'primevue/select';
import FloatLabel from 'primevue/floatlabel';

const props = defineProps({
    visible: Boolean,
    userId: Number,
    projekte: Array,     // enthält standorte: [{id, name}]
    standorte: Array,
});

const emit = defineEmits(["close", "saved"]);

// → Array aller Zuweisungen
const zuweisungen = ref([
    {
        projekt_id: null,
        standort_id: null,
    }
]);

// 🔹 neue leere Zeile hinzufügen
const addRow = (projektId = null) => {
    zuweisungen.value.push({
        projekt_id: projektId,
        standort_id: null,
    });
};

// 🔹 Zeile entfernen
const removeRow = (index) => {
    zuweisungen.value.splice(index, 1);
};



// 🔥 Speichern
const save = () => {
    // Validierung
    for (const row of zuweisungen.value) {
        if (!row.projekt_id || !row.standort_id) {
            Swal.fire("Fehler", "Bitte Projekt und Standort auswählen.", "error");
            return;
        }
    }

    axios.post(route("projekthaspersonen.store"), {
        user_id: props.userId,
        zuweisungen: zuweisungen.value
    })
    .then(() => {
        Swal.fire("Gespeichert!", "Zuweisungen erfolgreich erstellt.", "success");
        emit("saved");
        emit("close");
    })
    .catch(err => {
        console.error(err);
        Swal.fire("Fehler", "Speichern fehlgeschlagen.", "error");
    });
};
</script>
<template>
<div v-if="visible" class="fixed inset-0 z-50 flex items-center justify-center bg-black bg-opacity-30">

    <div class="bg-white p-6 rounded-lg w-[600px] shadow-xl">

        <h2 class="text-xl font-bold mb-4">Projekte zuweisen</h2>

        <div class="max-h-[400px] overflow-y-auto">

            <div v-for="(row, i) in zuweisungen" :key="i"
                 class="border p-3 mb-3 rounded">

                <div class="flex gap-2">
                    <FloatLabel variant="on">
                        <Select v-model="row.projekt_id"  inputId="id" optionValue="id"  :options="projekte" optionLabel="name" class="w-full"  />

                        <label for="abteilungsleiter">Projekt wählen</label>
                    </FloatLabel>

                    <!-- Standort -->
                    <MultiSelect
                        v-model="row.standort_id"
                        inputId="id"
                        optionLabel="name"
                        :options="props.standorte"
                        optionValue="id"
                        filter
                        placeholder="Standorte wählen*"
                        :maxSelectedLabels="3"
                        class="w-full">
                    </MultiSelect>
                    <!-- Entfernen -->
                    <button v-if="zuweisungen.length > 1"
                        @click="removeRow(i)"
                        class="text-red-500 ml-2">
                        X
                    </button>
                </div>

                <!-- + Hinzufügen -->
                <button
                    @click="addRow(row.projekt_id)"
                    class="mt-2 text-zbb"
                >
                    + weitere Zuweisung hinzufügen
                </button>
            </div>

        </div>

        <div class="flex justify-between mt-4">
            <button @click="emit('close')" class="px-4 py-2 border rounded">
                Abbrechen
            </button>
            <button @click="save" class="px-4 py-2 bg-zbb text-white rounded">
                Speichern
            </button>
        </div>

    </div>
</div>
</template>
