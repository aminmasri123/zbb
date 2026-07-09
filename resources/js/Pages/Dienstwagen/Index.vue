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
    'außer Betrieb': '⛔',
    'passiv': '·'
    };

    const deadlineFields = [
        { key: 'tuev_bis', label: 'TÜV' },
        { key: 'au_bis', label: 'AU' },
        { key: 'versicherung_bis', label: 'Versicherung' },
        { key: 'steuer_faellig_am', label: 'Steuer' },
        { key: 'inspektion_am', label: 'Inspektion' },
        { key: 'reifenwechsel_am', label: 'Reifen' },
        { key: 'naechste_wartung', label: 'Wartung' },
    ];

    const daysUntil = (value) => {
        if (!value) return null;
        const due = new Date(value);
        if (Number.isNaN(due.getTime())) return null;
        const today = new Date();
        today.setHours(0, 0, 0, 0);
        due.setHours(0, 0, 0, 0);
        return Math.ceil((due - today) / 86400000);
    };

    const deadlineClass = (days) => {
        if (days === null) return '';
        if (days < 0) return 'bg-red-100 text-red-700';
        if (days <= 14) return 'bg-orange-100 text-orange-700';
        if (days <= 30) return 'bg-yellow-100 text-yellow-700';
        return 'bg-green-100 text-green-700';
    };

    const deadlineText = (days) => {
        if (days < 0) return `${Math.abs(days)} Tage überfällig`;
        if (days === 0) return 'heute fällig';
        return `in ${days} Tagen`;
    };

    const vehicleDeadlines = (vehicle) => deadlineFields
        .map((field) => ({ ...field, days: daysUntil(vehicle[field.key]) }))
        .filter((field) => field.days !== null && field.days <= 30)
        .sort((a, b) => a.days - b.days);

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
                    <Link :href="route('dienstwagen.buchungen.index')" class="inline-flex items-center gap-2 bg-white border border-gray-300 text-gray-800 font-semibold py-3 px-5 rounded-xl hover:bg-gray-50">
                        <i class="la la-calendar"></i> Buchungen
                    </Link>
                    <Link :href="route('dienstwagen.meldungen.index')" class="inline-flex items-center gap-2 bg-white border border-gray-300 text-gray-800 font-semibold py-3 px-5 rounded-xl hover:bg-gray-50">
                        <i class="la la-exclamation-circle"></i> Meldungen
                    </Link>
	            </div>

            <!-- Fahrzeuge -->
            <div v-if="filteredVehicles.length" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-6">
                <div
                    v-for="v in filteredVehicles"
                    :key="v.id"
                    class="group bg-white dark:bg-gray-800 rounded-2xl shadow-md hover:shadow-xl hover:-translate-y-1 transition-all duration-300 overflow-hidden"
	                >
                    <div v-if="v.bild_url" class="h-40 overflow-hidden bg-gray-100">
                        <img :src="v.bild_url" class="h-full w-full object-cover" :alt="v.kennzeichen" />
                    </div>
                    <div v-else class="h-40 bg-gray-100 dark:bg-gray-900 flex items-center justify-center text-gray-400">
                        <i class="la la-car text-5xl"></i>
                    </div>
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
                            <div class="flex flex-wrap gap-2 pt-2">
                                <span v-if="v.offene_meldungen_count" class="rounded bg-red-100 px-2 py-1 text-xs font-semibold text-red-700">
                                    {{ v.offene_meldungen_count }} offene Meldung(en)
                                </span>
                                <span v-if="v.aktive_buchungen_count" class="rounded bg-blue-100 px-2 py-1 text-xs font-semibold text-blue-700">
                                    {{ v.aktive_buchungen_count }} aktive Buchung(en)
                                </span>
                                <span
                                    v-for="deadline in vehicleDeadlines(v)"
                                    :key="`${v.id}-${deadline.key}`"
                                    class="rounded px-2 py-1 text-xs font-semibold"
                                    :class="deadlineClass(deadline.days)"
                                >
                                    {{ deadline.label }} {{ deadlineText(deadline.days) }}
                                </span>
                            </div>
	                    </div>

                    <!-- Aktionen -->
                    <div class="grid grid-cols-2 gap-3 border-t border-gray-100 bg-gray-50 p-5 text-sm font-semibold dark:border-gray-700 dark:bg-gray-900/40">
                        <Link
                            :href="route('dienstwagen.edit', v.id)"
                            class="text-blue-600 transition-colors hover:text-blue-800"
                        >
	                            ✏️ Bearbeiten
	                        </Link>
                            <Link
                                :href="route('dienstwagen.fahrtenbuch.index', { dienstwagen_id: v.id })"
                                class="text-green-700 transition-colors hover:text-green-900"
                            >
                                Fahrtenbuch
                            </Link>
                            <Link
                                :href="route('dienstwagen.verlauf.index', v.id)"
                                class="text-gray-600 transition-colors hover:text-gray-900"
                            >
                                Verlauf
                            </Link>
                            <Link
                                :href="route('dienstwagen.fahrtenbuch.code', v.id)"
                                class="text-indigo-700 transition-colors hover:text-indigo-900"
                            >
                                QR drucken
                            </Link>
	                        <span class="col-span-2 flex cursor-pointer items-center justify-center rounded border border-red-200 px-4 py-2 text-red-700 hover:bg-red-50"  @click="confirmDelete(v)">
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
