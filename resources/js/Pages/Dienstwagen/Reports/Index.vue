<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import { Head, router } from '@inertiajs/vue3';
import { ref, computed } from 'vue';

/* --------------------------------------------------------
   PROPS
-------------------------------------------------------- */
const props = defineProps({
    vehicles: Array,
    currentMonth: String, // kommt aus dem Controller
});

/* --------------------------------------------------------
   MONATSFILTER
-------------------------------------------------------- */
const selectedMonth = ref(props.currentMonth);

// Backend neu laden, wenn Monat geändert wird
function reloadMonth() {
    router.get(
        '/ressourcen/dienstwagen/reports',
        { monat: selectedMonth.value },
        {
            preserveScroll: true,
            preserveState: true,
        }
    );
}

/* --------------------------------------------------------
   FLOTTENÜBERSICHT (Gesamtkosten & Gesamtkm)
-------------------------------------------------------- */
const totalFleetCost = computed(() =>
    props.vehicles.reduce((sum, v) => {
        return sum + v.kostanaufzeichnungen.reduce(
            (s, c) => s + parseFloat(c.betrag),
            0
        );
    }, 0).toFixed(2)
);

const totalFleetKm = computed(() =>
    props.vehicles.reduce((sum, v) => {
        return sum + v.fahrten.reduce(
            (s, f) => s + (f.end_km - f.start_km),
            0
        );
    }, 0)
);

/* --------------------------------------------------------
   BERECHNUNGEN PRO FAHRZEUG
-------------------------------------------------------- */
function vehicleKm(vehicle) {
    return vehicle.fahrten.reduce(
        (sum, f) => sum + (f.end_km - f.start_km),
        0
    );
}

function vehicleCost(vehicle) {
    return vehicle.kostanaufzeichnungen.reduce(
        (sum, c) => sum + parseFloat(c.betrag),
        0
    );
}

function costPer100(vehicle) {
    const km = vehicleKm(vehicle);
    const cost = vehicleCost(vehicle);

    return km > 0 ? ((cost / km) * 100).toFixed(2) : "–";
}
</script>

<template>
    <Head title="Dienstwagen Berichte" />

    <AppLayout>
        <template #header>



            <!-- 🔵 MONATSFILTER -->
            <div class="flex justify-between items-center gap-4 mb-4">
                📊 Dienstwagen Berichte & Auswertungen
                <input
                    type="month"
                    v-model="selectedMonth"
                    @change="reloadMonth"
                    class="border px-3 py-2 rounded-lg dark:bg-gray-700 dark:text-white"
                />
            </div>
        </template>

        <div class="space-y-10">



            <!-- 🟧 FLOTTENÜBERSICHT -->
            <div class="bg-white dark:bg-gray-800 rounded-xl shadow p-6 border dark:border-gray-700">
                <h2 class="text-2xl font-semibold mb-4">Flottenübersicht ({{ selectedMonth }})</h2>

                <div class="grid md:grid-cols-3 gap-6">

                    <div class="p-4 rounded-lg bg-gray-50 dark:bg-gray-900 shadow">
                        <p class="text-gray-600 dark:text-gray-400">Gesamtkosten</p>
                        <p class="text-3xl font-bold text-blue-600 dark:text-blue-400">
                            {{ totalFleetCost }} €
                        </p>
                    </div>

                    <div class="p-4 rounded-lg bg-gray-50 dark:bg-gray-900 shadow">
                        <p class="text-gray-600 dark:text-gray-400">Gesamt Kilometer</p>
                        <p class="text-3xl font-bold text-green-600 dark:text-green-400">
                            {{ totalFleetKm.toLocaleString() }} km
                        </p>
                    </div>

                    <div class="p-4 rounded-lg bg-gray-50 dark:bg-gray-900 shadow">
                        <p class="text-gray-600 dark:text-gray-400">Anzahl Fahrzeuge</p>
                        <p class="text-3xl font-bold">
                            {{ props.vehicles.length }}
                        </p>
                    </div>

                </div>
            </div>

            <!-- 🟦 ALLE FAHRZEUGE -->
            <div class="grid lg:grid-cols-2 gap-8">

                <div
                    v-for="v in props.vehicles"
                    :key="v.id"
                    class="bg-white dark:bg-gray-800 rounded-xl shadow p-6 border dark:border-gray-700"
                >
                    <h3 class="text-xl font-bold mb-2">
                        🚗 {{ v.kennzeichen }} — {{ v.marke }} {{ v.modell }}
                    </h3>

                    <p class="text-sm text-gray-500 mb-4">
                        Standort: {{ v.standort?.name ?? '—' }}
                        | Baujahr: {{ v.baujahr }}
                        | Monat: <b>{{ selectedMonth }}</b>
                    </p>

                    <div class="space-y-1 text-gray-700 dark:text-gray-300">
                        <p>Gesamtkilometer (Monat):
                            <b>{{ vehicleKm(v).toLocaleString() }} km</b>
                        </p>

                        <p>Gesamtkosten (Monat):
                            <b>{{ vehicleCost(v).toFixed(2) }} €</b>
                        </p>

                        <p>Kosten je 100 km:
                            <b>{{ costPer100(v) }} €</b>
                        </p>
                    </div>

                    <!-- 🟦 Fahrtenbuch -->
                    <details class="mt-4">
                        <summary class="cursor-pointer text-blue-600">
                            Fahrtenbuch ({{ v.fahrten.length }} Einträge)
                        </summary>

                        <table class="w-full mt-3 border-collapse text-center">
                            <thead class="bg-gray-100 dark:bg-gray-700">
                                <tr>
                                    <th class="table-head">Datum</th>
                                    <th class="table-head">Strecke</th>
                                    <th class="table-head">Zweck</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="f in v.fahrten" :key="f.id" class="border-b dark:border-gray-700">
                                    <td class="table-cell">{{ f.date }}</td>
                                    <td class="table-cell">{{ f.end_km - f.start_km }} km</td>
                                    <td class="table-cell">{{ f.zweck }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </details>

                    <!-- 🟦 Kosten -->
                    <details class="mt-4">
                        <summary class="cursor-pointer text-red-600">
                            Kosten ({{ v.kostanaufzeichnungen.length }} Einträge)
                        </summary>

                        <table class="w-full mt-3 border-collapse">
                            <thead class="bg-gray-100 dark:bg-gray-700">
                                <tr>
                                    <th class="table-head">Datum</th>
                                    <th class="table-head">Art</th>
                                    <th class="table-head">Betrag</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="k in v.kostanaufzeichnungen" :key="k.id" class="border-b dark:border-gray-700">
                                    <td class="table-cell">{{ k.datum }}</td>
                                    <td class="table-cell">{{ k.art }}</td>
                                    <td class="table-cell">{{ k.betrag }} €</td>
                                </tr>
                            </tbody>
                        </table>
                    </details>

                    <!-- 🟦 Wartungen -->
                    <details class="mt-4">
                        <summary class="cursor-pointer text-yellow-600">
                            Wartungen ({{ v.wartungen.length }} Einträge)
                        </summary>

                        <table class="w-full mt-3 border-collapse">
                            <thead class="bg-gray-100 dark:bg-gray-700">
                                <tr>
                                    <th class="table-head">Datum</th>
                                    <th class="table-head">Art</th>
                                    <th class="table-head">Kosten</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="w in v.wartungen" :key="w.id" class="border-b dark:border-gray-700">
                                    <td class="table-cell">{{ w.datum }}</td>
                                    <td class="table-cell">{{ w.art }}</td>
                                    <td class="table-cell">{{ w.kosten }} €</td>
                                </tr>
                            </tbody>
                        </table>
                    </details>

                </div>
            </div>

        </div>
    </AppLayout>
</template>

<style scoped>
.table-head {
    @apply px-3 py-2 text-sm font-semibold;
}
.table-cell {
    @apply px-3 py-2 text-sm;
}
</style>
