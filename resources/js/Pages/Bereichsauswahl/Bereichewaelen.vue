<script setup>
import { ref, defineProps } from 'vue';
import Swal from 'sweetalert2';
import axios from 'axios';

const props = defineProps({
    alle_teilnehmer: Array,
    alle_bereiche: Array,
});
// Reactive Variablen für Radios
const wahl = ref({});

// Funktion zum Senden der Wahl
const updateWahl = async (teilnehmerId, wahlNummer, bereichId) => {
    try {
        const response = await axios.post(route('bereichsauswahl.bop.radio.update'), {
            _token: document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
            teilnehmer_id: teilnehmerId,
            wahl: `bereich_id${wahlNummer}`,
            orientierung: bereichId,
        });

        Swal.fire({
            title: "Erfolg!",
            text: "Die Einteilung wurde erfolgreich erstellt.",
            icon: "success",
            timer: 800,
            timerProgressBar: true,
        });
    } catch (error) {
        Swal.fire({
            title: "Fehler!",
            text: "Die Speicherung ist fehlgeschlagen:"  + error.response.data.error || error.response.data.message || 'Unbekannter Fehler',
            icon: "error",
            timer: 4000,
            timerProgressBar: true,
        });
    }
};

// Funktion um zu prüfen, ob Radio ausgewählt sein soll
const isChecked = (teilnehmer, wahlNummer, bereichId) => {
    if (!teilnehmer.bereichsauswahl) return false;
    return teilnehmer.bereichsauswahl[`bereich_id${wahlNummer}`] === bereichId;
};
</script>

<template>
<div class="overflow-x-auto">
    <table class="min-w-full table-auto text-left border text-gray-700">
        <thead class="bg-gray-200">
            <tr>
                <th class="px-4 py-2">#</th>
                <th class="px-4 py-2">Vorname</th>
                <th class="px-4 py-2">Nachname</th>
                <th class="px-4 py-2">Klasse</th>
                <th class="px-4 py-2">Bereich 1</th>
                <th class="px-4 py-2">Bereich 2</th>
                <th class="px-4 py-2">Bereich 3</th>
                <th class="px-4 py-2">Bereich 4</th>
            </tr>
        </thead>
        <tbody>
            <tr v-for="teilnehmer in alle_teilnehmer" :key="teilnehmer.id" class="bg-white border-b">
                <td class="px-4 py-2">{{teilnehmer.id}}</td>
                <td class="px-4 py-2">{{teilnehmer.person.vorname}}</td>
                <td class="px-4 py-2">{{teilnehmer.person.nachname}}</td>
                <td class="px-4 py-2">{{teilnehmer.klasse}}</td>

                <!-- 4 Bereichsauswahlen -->
                <td v-for="i in 4" :key="i" class="px-4 py-2">
                    <div class="flex flex-col space-y-1">
                        <label v-for="bereich in alle_bereiche" :key="bereich.id" class="flex items-center space-x-2">
                            <input
                                type="radio"
                                :name="`wahl${i}_${teilnehmer.id}`"
                                :value="bereich.id"
                                :checked="isChecked(teilnehmer, i, bereich.id)"
                                @change="updateWahl(teilnehmer.id, i, bereich.id)"
                                class="form-radio text-blue-600"
                            />
                            <span>{{bereich.name}}</span>
                        </label>
                    </div>
                </td>
            </tr>
        </tbody>
    </table>
</div>
</template>

<style scoped>
.form-radio {
    accent-color: #3b82f6; /* Tailwind blau */
}
</style>
