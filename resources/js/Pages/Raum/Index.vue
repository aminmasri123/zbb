<template>
    <Head title="Räumlichkeiten" />

    <app-layout>
        <template #header>Räumlichkeiten</template>

        <div class="min-h-screen bg-white rounded-lg shadow-2xl from-slate-50 to-slate-100 p-8">
            <div class="max-w-7xl mx-auto">
                <!-- Header -->
                <div class="flex justify-between items-center mb-8">
                    <!-- Suchfeld -->
                    <div class="w-5/6">
                        <input
                            v-model="search"
                            type="text"
                            placeholder="🔍 Räumlichkeiten durchsuchen..."
                            class="w-full px-5 py-3 bg-white border border-slate-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-zbb focus:border-transparent shadow-sm transition duration-300"
                        />
                    </div>
                    <div @click="openModalCreate" class="flex items-center text-center w-1/6 mx-4">
                            <i class="w-full la la-plus cursor-pointer bg-zbb hover:bg-zbb text-white font-semibold py-3 px-6 rounded-xl shadow-lg hover:shadow-xl transition-all duration-300"> Raum</i>
                    </div>
                </div>

                <!-- Keine Ergebnisse -->
                <div v-if="filteredRaeumeCount === 0" class="text-center py-12">
                    <p class="text-slate-500 text-lg">Keine Räumlichkeiten gefunden</p>
                </div>

                <!-- Standorte -->
                <div v-for="standort in localStandorte" :key="standort.id" class="mb-6">
                    <!-- Standort Header -->
                    <button
                        @click="toggleVisibility(standort.id)"
                        class="w-full flex flex-col items-start bg-white p-5 rounded-xl shadow-md hover:shadow-lg hover:bg-slate-50 transition-all duration-300"
                    >
                        <div class="flex items-center gap-3 w-full">
                            <span class="text-2xl ml-8 transition-transform duration-300" :class="isVisible(standort.id) ? 'rotate-90' : ''">
                                ▶
                            </span>
                            <h2 class="text-2xl font-bold text-slate-900 flex-1">{{ standort.name }}</h2>
                            <span class="bg-zbbTrp text-zbb px-3 py-1 rounded-full text-sm font-semibold">
                                {{ filteredRaeume(standort.raeume).length }} Räume
                            </span>
                        </div>

                        <!-- Adresse -->
                        <div class="ml-8 mt-2 text-slate-700">
                            <template v-if="normalizeAdresse(standort.adresse).length">
                                <div
                                    v-for="adresse in normalizeAdresse(standort.adresse)"
                                    :key="adresse.id ?? `${adresse.plz}-${adresse.stadt}`"
                                >
                                    {{ adresse.strasse }} {{ adresse.hausnummer }}, {{ adresse.plz }} {{ adresse.stadt }}
                                </div>
                            </template>
                            <template v-else>
                                <span class="text-red-600">Dem Standort wurde keine Adresse zugeordnet.</span>
                            </template>
                        </div>
                    </button>

                    <!-- Räume Liste -->
                    <transition name="expand">
                        <div v-if="isVisible(standort.id)" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4 mt-4">
                            <div
                                v-for="raum in filteredRaeume(standort.raeume)"
                                :key="raum.id"
                                class="bg-white p-6 rounded-xl shadow-md hover:shadow-lg hover:-translate-y-1 transition-all duration-300"
                            >
                                <h3 class="text-xl font-bold text-slate-900 mb-3">{{ raum.name }}</h3>

                                <div class="space-y-2 mb-4">
                                    <div class="flex items-center gap-2 text-slate-600">
                                        <span class="text-lg">👥</span>
                                        <span>Kapazität: <strong>{{ raum?.kapazitaet || '-'}}</strong> Personen</span>
                                    </div>

                                    <div class="flex items-center gap-2 text-slate-600">
                                        <span class="text-lg">{{getEmoji(raum?.typ)}}</span>
                                        <span>Typ: <strong>{{ raum?.typ || '-'}}</strong></span>
                                    </div>
                                    <div v-if="raum.parent" class="flex items-center gap-2 text-slate-600">
                                        <span class="text-lg">↳</span>
                                        <span>Innerhalb von: <strong>{{ raum.parent.name }}</strong></span>
                                    </div>
                                    <div class="flex items-center gap-2 text-slate-600">
                                        <span class="text-lg">⌂</span>
                                        <span>Belegung: <strong>{{ belegungsartLabel(raum.belegungsart) }}</strong></span>
                                    </div>
                                    <div v-if="belegungsartHatStandardPerson(raum.belegungsart) && raum.standard_person" class="flex items-center gap-2 text-slate-600">
                                        <span class="text-lg">★</span>
                                        <span>Standard: <strong>{{ raum.standard_person.vorname }} {{ raum.standard_person.nachname }}</strong></span>
                                    </div>
                                    <div class="flex items-center gap-2 text-slate-600">
                                        <span class="text-lg">!</span>
                                        <span>Offene Meldungen: <strong>{{ offeneMeldungen(raum).length }}</strong></span>
                                    </div>
                                    <p class="text-slate-600 text-sm line-clamp-2">{{ raum.beschreibung || 'Keine Beschreibung' }}</p>
                                </div>

                                <!-- Aktionen -->
                                <div class="flex gap-2 pt-4 border-t border-slate-200">
                                   <button
                                        @click="openEditModal(raum)"
                                        class="flex-1 text-center bg-zbbTrp hover:bg-orange-200 text-zbb font-semibold py-2 rounded-lg transition duration-300"
                                    >
                                        ✏️ Bearbeiten
                                    </button>

                                    <button
                                        @click="openMeldungModal(raum)"
                                        class="flex-1 bg-amber-50 hover:bg-amber-100 text-amber-700 font-semibold py-2 rounded-lg transition duration-300"
                                    >
                                        Melden
                                    </button>

                                    <button
                                        @click="confirmDelete(raum)"
                                        class="flex-1 bg-red-50 hover:bg-red-100 text-red-600 font-semibold py-2 rounded-lg transition duration-300"
                                    >
                                        🗑️ Löschen
                                    </button>
                                </div>
                            </div>
                        </div>
                    </transition>
                </div>
            </div>
        </div>

            <!-- Modals -->
        <ModalCreate :visible="isModalCreateOpen"
             :standorte="localStandorte"
             :personal="props.personal"
             @close="isModalCreateOpen = false"
             @added="addRaum"
        />
        <ModalEdit
    :visible="isModalEditOpen"
    :standorte="localStandorte"
    :personal="props.personal"
    :raum="raumToEdit"
    @close="isModalEditOpen = false"
    @updated="updateRaum"
/>

        <ModalMeldung
            :visible="isMeldungModalOpen"
            :raum="raumForMeldung"
            @close="isMeldungModalOpen = false"
            @added="addMeldung"
        />



        <ModalDestroy
            v-if="showModalLöschen"
            @delete="handleDelete"
            @close="showModalLöschen = false"
            :seite="seite"
            :toDelete="raumToDelete"
        />
    </app-layout>
</template>

<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import ModalDestroy from '@/Components/ModalDestroyForm.vue'
import { ref, computed } from 'vue'
import { Link, Head, router } from '@inertiajs/vue3'
import ModalCreate from '@/Pages/Raum/ModalCreate.vue';
import ModalEdit from '@/Pages/Raum/ModalEdit.vue'
import ModalMeldung from '@/Pages/Raum/ModalMeldung.vue'

const props = defineProps({
    standorte: { type: Array, default: () => [] },
    personal: { type: Array, default: () => [] },
})

let seite = 'raeumlichkeiten'

const search = ref('')
const visibleStandorte = ref(new Set())
const raumToDelete = ref(null)
const showModalLöschen = ref(false)
let isModalCreateOpen = ref(false);
const isModalEditOpen = ref(false)
const raumToEdit = ref(null)
const isMeldungModalOpen = ref(false)
const raumForMeldung = ref(null)

const openEditModal = (raum) => {
    raumToEdit.value = JSON.parse(JSON.stringify(raum)) // Kopie
    isModalEditOpen.value = true
}

const closeEditModal = () => {
    isModalEditOpen.value = false
}
const openModalCreate = () => { isModalCreateOpen.value = true; };
const closeModalCreate = () => { isModalCreateOpen.value = false; };
const openMeldungModal = (raum) => {
    raumForMeldung.value = raum
    isMeldungModalOpen.value = true
}
// ✅ Lokale Kopie der Standorte (damit wir Räume direkt entfernen können)
const localStandorte = ref(JSON.parse(JSON.stringify(props.standorte)))

// Sichtbarkeit toggeln
const toggleVisibility = (id) => {
    if (visibleStandorte.value.has(id)) {
        visibleStandorte.value.delete(id)
    } else {
        visibleStandorte.value.add(id)
    }
}

const isVisible = (id) => visibleStandorte.value.has(id)

// Filter für Räume (Suchfeld)
const filteredRaeume = (raeume) => {
    const q = search.value.trim().toLowerCase()
    if (!q) return raeume || []
    return (raeume || []).filter(
        (raum) =>
            (raum.name || '').toLowerCase().includes(q) ||
            (raum.beschreibung || '').toLowerCase().includes(q)
    )
}

const filteredRaeumeCount = computed(() => {
    return localStandorte.value.reduce(
        (count, standort) => count + filteredRaeume(standort.raeume).length,
        0
    )
})

const belegungsartLabel = (value) => ({
    frei: 'frei vergebbar',
    standard: 'meist feste Belegung',
    teilweise: 'teilweise belegt',
    blockiert: 'blockiert',
}[value] || 'frei vergebbar')

const belegungsartHatStandardPerson = (value) => ['standard', 'teilweise'].includes(value)

const offeneMeldungen = (raum) => (raum.meldungen || []).filter((meldung) => meldung.status !== 'erledigt')

// MorphOne / MorphMany Normalisierung
const normalizeAdresse = (adresse) => {
    if (!adresse) return []
    if (Array.isArray(adresse)) return adresse.filter(Boolean)
    return [adresse]
}

const addRaum = (raum) => {
    const standort = localStandorte.value.find(s => s.id === raum.standort_id);
    if (!standort) return;
    if (!Array.isArray(standort.raeume)) {
        standort.raeume = [];
    }
    standort.raeume.push(raum);
};

const addMeldung = (meldung) => {
    localStandorte.value.forEach((standort) => {
        const raum = standort.raeume.find((r) => r.id === meldung.raum_id)
        if (!raum) return
        if (!Array.isArray(raum.meldungen)) {
            raum.meldungen = []
        }
        raum.meldungen.unshift(meldung)
    })
}


// 🗑️ Löschdialog öffnen
const confirmDelete = (raum) => {
    raumToDelete.value = { id: raum.id, name: raum.name }
    showModalLöschen.value = true
}

// 🗑️ Raum löschen und aus Liste entfernen
const handleDelete = async (raumId) => {
    try {
        // 🔹 Optional: Backend löschen (falls du Inertia-Routen hast)
        // await router.delete(`/raum/${raumId}`, { preserveScroll: true })

        // 🔹 Lokal aus der Liste entfernen
        localStandorte.value.forEach((standort) => {
            standort.raeume = standort.raeume.filter((r) => r.id !== raumId)
        })

        // Modal schließen
        showModalLöschen.value = false
    } catch (error) {
        console.error('Fehler beim Löschen:', error)
    }
}


const emojiMap = {
  "Büro": "🏢",
  "Elektroraum": "⚡",
  "Unterrichtsraum": "🏫",
  "Seminarraum": "👨‍🏫",
  "Besprechungsraum": "🗣️",
  "Labor": "🧪",
  "Werkstatt": "🛠️",
  "Lager": "📦",
  "Küche": "🍳",
  "Aufenthaltsraum": "☕",
  "Sanitärraum": "🚻",
  "Empfang": "👋",
  "Serverraum": "🖥️",
  "Archiv": "🗄️",
  "Aula": "🏛️",
  "Bibliothek": "📚",
  "Arbeitsplatz": "💼",
  "Copyroom": "🖨️",
  "Technikraum": "🔧",
  "Hauswirtschaftsraum": "🧹",
  "Holzbereich": "🪵",
  "Metallbereich": "⚙️",
}

function getEmoji(type) {
  return emojiMap[type] || "🏠";
}

const updateRaum = (updatedRaum) => {
    let found = false
    localStandorte.value.forEach((standort) => {
        const index = standort.raeume.findIndex(r => r.id === updatedRaum.id);
        if (index !== -1) {
            if (standort.id === updatedRaum.standort_id) {
                standort.raeume[index] = updatedRaum;
            } else {
                standort.raeume.splice(index, 1);
            }
            found = true
        }
    });

    const zielStandort = localStandorte.value.find((standort) => standort.id === updatedRaum.standort_id)
    if (zielStandort && !zielStandort.raeume.some((raum) => raum.id === updatedRaum.id)) {
        zielStandort.raeume.push(updatedRaum)
    } else if (!found && zielStandort) {
        zielStandort.raeume.push(updatedRaum)
    }
};


</script>


<style scoped>
.expand-enter-active,
.expand-leave-active {
    transition: all 0.3s ease;
}
.expand-enter-from,
.expand-leave-to {
    opacity: 0;
    transform: translateY(-10px);
}
</style>
