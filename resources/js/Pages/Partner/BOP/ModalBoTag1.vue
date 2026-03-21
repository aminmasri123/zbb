<script setup>
import { ref, watch } from 'vue'
import Modal from '@/Components/ModalForm.vue'
import axios from 'axios'

const props = defineProps({
  visible: Boolean,
  jahr: String,
  teil: String,
  partnerId: Number,
  klassen: Array,
  anzahlBereiche: Number
})

const emit = defineEmits(['close', 'submit'])

const mode = ref(null)
const customValue = ref('') // Anzahl Räume
const termin = ref('')
const anzahlBereiche = ref(props.anzahlBereiche)

// Arrays als ref
const raumKapazitaeten = ref([])
const raumNamen = ref([])

// Watcher: sobald customValue geändert wird, Arrays vorbereiten
watch(customValue, (newVal) => {
    const n = parseInt(newVal)
    if (!isNaN(n) && n > 0) {
        // komplette Arrays neu zuweisen – niemals push oder length setzen
        raumKapazitaeten.value = Array.from({ length: n }, () => '')
        raumNamen.value = Array.from({ length: n }, () => '')
    } else {
        raumKapazitaeten.value = []
        raumNamen.value = []
    }
})

const submit = async () => {
    try {
        // Query-Parameter manuell erstellen
        const params = new URLSearchParams();
        params.append('anzahlRaeumlichkeiten', parseInt(customValue.value) || 0);
        params.append('anzahlBereiche', anzahlBereiche.value);
        params.append('termin', termin.value);

        raumNamen.value.forEach((name, i) => params.append(`raumNamen[${i}]`, name));
        raumKapazitaeten.value.forEach((kap, i) => params.append(`kapazitaeten[${i}]`, kap || 0));

        // Ziggy-Route mit Pflichtparametern
        const url = route('anwesenheitsliste.BoTag1.export', {
            partnerID: props.partnerId,
            schuljahr: props.jahr,
            teil: props.teil,
            klasse: 'exportAlleKlassen'
        }) + '?' + params.toString();

        const response = await axios.get(url, { responseType: 'blob' });

        // Datei herunterladen
        const blob = new Blob([response.data], { type: response.data.type });
        const link = document.createElement('a');
        link.href = window.URL.createObjectURL(blob);
        link.download = 'Anwesenheitsliste.zip';
        link.click();
        window.URL.revokeObjectURL(link.href);

    } catch (error) {
        console.error('Export fehlgeschlagen:', error);
        alert('Der Export ist fehlgeschlagen!');
    }
}
</script>

<template>
  <Modal v-if="visible" @close="$emit('close')">
    <template #header>BO Tag1 - Typ der Anwesenheitsliste auswählen</template>

    <template #body>
        <div class="space-y-2">
            <div class="  gap-2 mt-2">
                <div class="w-full p-4 border">
                    <p>Termin:</p>
                    <input v-model="termin" type="date" required class="border p-2 w-full" />
                </div>
            </div>

        <template v-if="termin">
            <!-- Anzahl Bereiche -->
                <div class="flex gap-2 mt-2">
                    <div class="w-full p-4 border">
                        <p>Bitte geben Sie die Anzahl der vorgestellten Bereiche ein:</p>
                        <input
                            v-model="anzahlBereiche"
                            type="number"
                            class="border p-2 w-full"
                            placeholder="Zahl eingeben"
                        />
                    </div>
                </div>

                <!-- Klasse -->
                <div class="w-full p-4 border">
                    <p>Nach Klassen exportieren:</p>
                    <div class="flex space-x-2 flex-wrap">
                        <div v-for="klasse in klassen" :key="klasse">
                        <a :href="route('anwesenheitsliste.BoTag1.export', { partnerID: props.partnerId, schuljahr: props.jahr, teil: props.teil, klasse: klasse, anzahlBereiche: anzahlBereiche, termin: termin})" rel="noopener noreferrer">
                            <span class="flex py-1 px-2 bg-zbb rounded text-center text-white">{{ klasse }}</span>
                        </a>
                        </div>
                        <a :href="route('anwesenheitsliste.BoTag1.export', {
                            anzahlBereiche: anzahlBereiche,
                            termin: termin,
                            partnerID: props.partnerId,
                            schuljahr: props.jahr,
                            teil: props.teil,
                            klasse: 'exportAlleKlassenZip'
                        })" rel="noopener noreferrer">
                            <span class="flex py-1 px-2 bg-zbb rounded text-center text-white">ZIP</span>
                    </a>
                    </div>
                </div>

                <!-- Alle nach Nachname -->
                <a :href="route('anwesenheitsliste.BoTag1.export', {
                        anzahlBereiche: anzahlBereiche,
                        partnerID: props.partnerId,
                        schuljahr: props.jahr,
                        teil: props.teil,
                        termin:termin,
                        klasse: 'exportAlleKlassen'
                    })"
                    class="w-full border p-2 text-left block">
                    Alle nach Nachname sortiert exportieren
                </a>

                <!-- Custom Räumlichkeiten -->
                <div>
                    <button @click="mode = 'custom'" class="w-full border p-2 text-left">
                        Anzahl der verwendeten Räumlichkeiten
                    </button>

                    <div v-if="mode === 'custom'" class="space-y-2 mt-2">
                        <input
                            v-model="customValue"
                            type="number"
                            class="border p-2 w-full"
                            placeholder="Zahl der Räume eingeben"
                        />

                        <div v-for="(kapazitaet, index) in raumKapazitaeten" :key="index" class="flex gap-2 items-center">
                            <input
                                v-model="raumNamen[index]"
                                type="text"
                                class="border p-2 w-1/2"
                                placeholder="Name Raum {{ index + 1 }}"
                            />
                            <input
                                v-model="raumKapazitaeten[index]"
                                type="number"
                                class="border p-2 w-1/2"
                                placeholder="Kapazität Raum {{ index + 1 }}"
                            />
                        </div>

                        <button
                            @click="submit"
                            class="bg-zbb text-white px-4 py-2 rounded mt-2"
                        >
                            Senden
                        </button>
                    </div>
                </div>
        </template>
      </div>
    </template>
  </Modal>
</template>
