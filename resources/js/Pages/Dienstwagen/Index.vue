<script setup>
    import AppLayout from '@/Layouts/AppLayout.vue';
    import { Link, router, Head } from '@inertiajs/vue3';
    import { ref, computed } from 'vue';
    import ModalDestroy from '@/Components/ModalDestroyForm.vue';

    const props = defineProps({
        vehicles: Array,
        standorte: Array
    });

    // Lokale, bearbeitbare Kopie der Fahrzeuge
    const localVehicles = ref([...props.vehicles]);

    // Variablen
    let seite = 'dienstwagen';
    const search = ref("");
    const locationFilter = ref("");
    let dienstwagenToDelete = ref(null);
    let showModalLöschen = ref(false);

    // Emojis für Status
    const statusIcons = {
    'verfügbar': '✅',
    'in Nutzung': '🚗',
    'Werkstatt': '🛠️',
    'außer Betrieb': '⛔'
    };

    // Gefilterte Liste
    const filteredVehicles = computed(() => {
        return localVehicles.value.filter(v => {
            return (
                (!search.value ||
                v.kennzeichen?.toLowerCase().includes(search.value.toLowerCase()) ||
                v.marke?.toLowerCase().includes(search.value.toLowerCase()) ||
                v.modell?.toLowerCase().includes(search.value.toLowerCase())) &&
                (!locationFilter.value || v.standort_id === parseInt(locationFilter.value))
            );
        });
    });

    // Bestätigung vor Löschung
    const confirmDelete = (dienstwagen) => {
        dienstwagenToDelete.value = {
            name: dienstwagen.kennzeichen,
            id: dienstwagen.id
        };
        showModalLöschen.value = true;
    };

    // Nach erfolgreicher Löschung → Wagen lokal entfernen
    const handleDelete = (id) => {
        localVehicles.value = localVehicles.value.filter(v => v.id !== id);
        showModalLöschen.value = false;
    };

    // Tatsächliche Löschung via Backend
    function deleteVehicle(id) {
        router.delete(route('dienstwagen.destroy', id), {
            onSuccess: () => {
                localVehicles.value = localVehicles.value.filter(v => v.id !== id);
            }
        });
    }
</script>


<template>
      <Head title="Dienstwagenverwaltung" />

    <AppLayout>
        <template #header>🚗  {{$t('Dienstwagenverwaltung')}}</template>

        <div class="space-y-8">
            <!-- Filterbereich -->
            <div class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 p-5 rounded-2xl shadow-sm flex flex-col md:flex-row gap-4">
                <input
                    v-model="search"
                    type="text"
                    placeholder="🔍 Suche nach Marke, Modell oder Kennzeichen..."
                    class="flex-1 px-4 py-3 rounded-xl border border-gray-300 dark:border-gray-700 bg-gray-50 dark:bg-gray-900 focus:ring-2 focus:ring-blue-500 focus:border-transparent outline-none transition-all"
                />

                <select
                    v-model="locationFilter"
                    class="px-5 w-1/6 py-3 rounded-xl border border-gray-300 dark:border-gray-700 bg-gray-50 dark:bg-gray-900 focus:ring-2 focus:ring-blue-500 focus:border-transparent outline-none transition-all"
                >
                    <option value="" >🌍 Alle Standorte</option>
                    <option v-for="loc in standorte" :key="loc.id" :value="loc.id">
                        {{ loc.name }}
                    </option>
                </select>
                   <Link
                    :href="route('dienstwagen.create')"
                    class="inline-flex items-center gap-2 bg-gradient-to-r from-blue-600 to-indigo-600 text-white font-semibold py-3 px-5 rounded-xl shadow-md hover:shadow-lg hover:scale-105 transition-all"
                >
                    <span class="text-xl">＋</span> Neues Fahrzeug
                </Link>
            </div>

            <!-- Fahrzeuge -->
            <div v-if="filteredVehicles.length" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-6">
                <div
                    v-for="v in filteredVehicles"
                    :key="v.id"
                    class="group bg-white dark:bg-gray-800 rounded-2xl shadow-md hover:shadow-xl hover:-translate-y-1 transition-all duration-300 overflow-hidden"
                >
                    <!-- Header -->
                    <div class="p-5 flex justify-between items-start border-b border-gray-100 dark:border-gray-700">
                        <div>
                            <h2 class="text-2xl font-bold text-gray-900 dark:text-white">{{ v.kennzeichen }}</h2>
                            <p class="text-gray-500 dark:text-gray-400">{{ v.marke }} {{ v.modell }}</p>
                            <p class="text-sm text-gray-400">📍 {{ v.standort?.name || 'Kein Standort' }}</p>
                        </div>
                        <span
                            class="px-3 py-1 text-xs font-semibold rounded-full"
                            :class="{
                                'bg-green-100 text-green-700': v.status === 'verfügbar',
                                'bg-blue-100 text-blue-700': v.status === 'in-nutzung',
                                'bg-yellow-100 text-yellow-700': v.status === 'werkstatt',
                                'bg-red-100 text-red-700': v.status === 'außer-betrieb'
                            }"
                        >
                            <span
                                class="flex items-center gap-1 px-3 py-1 text-xs font-semibold rounded-full"
                                :class="{
                                    'bg-green-100 text-green-700': v.status === 'verfügbar',
                                    'bg-blue-100 text-blue-700' : v.status === 'in Nutzung',
                                    'bg-yellow-100 text-yellow-700': v.status === 'Werkstatt',
                                    'bg-red-100 text-red-700': v.status === 'außer Betrieb'
                                }"
                                >
                                <span>{{ statusIcons[v.status] }}</span>
                                {{ v.status }}
                            </span>

                        </span>
                    </div>

                    <!-- Inhalt -->
                    <div class="p-5 text-sm text-gray-600 dark:text-gray-300 space-y-2">
                        <p>⛽ <b>Kraftstoff:</b> {{ v.kraftstoffart }}</p>
                        <p>🛞 <b>Kilometerstand:</b> {{ v.kilometerstand.toLocaleString() }} km</p>
                        <p>📅 <b>Baujahr:</b> {{ v.baujahr }}</p>
                    </div>

                    <!-- Aktionen -->
                    <div class="flex justify-between items-center border-t border-gray-100 dark:border-gray-700 p-5 bg-gray-50 dark:bg-gray-900/40">
                        <Link
                            :href="route('dienstwagen.edit', v.id)"
                            class="text-blue-600 hover:text-blue-800 font-semibold transition-colors"
                        >
                            ✏️ Bearbeiten
                        </Link>
                        <span class="flex justify-between cursor-pointer py-1 px-6 items-center hover:bg-gray-100"  @click="confirmDelete(v)">
                            🗑️ {{ $t('Löschen') }}
                        </span>

                    </div>
                </div>
            </div>

            <!-- Keine Ergebnisse -->
            <div v-else class="text-center py-20 text-gray-400 dark:text-gray-500">
                <p class="text-2xl mb-2">😕 Keine Fahrzeuge gefunden</p>
                <p>Versuche, den Suchbegriff oder Filter zu ändern.</p>
            </div>
        </div>


        <ModalDestroy v-if="showModalLöschen" @delete="handleDelete" @close="showModalLöschen = false" :seite="seite"  :toDelete="dienstwagenToDelete"></ModalDestroy>

    </AppLayout>
</template>
