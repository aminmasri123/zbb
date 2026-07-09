<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import { Head, Link } from '@inertiajs/vue3';
import { formatDateTime } from '@/utils/dateFormat.js';

defineProps({
    vehicle: Object,
    entries: Array,
});

function actor(entry) {
    if (entry.person) {
        return `${entry.person.nachname || ''} ${entry.person.vorname || ''}`.trim();
    }

    return entry.user?.name || 'System';
}

function changeRows(entry) {
    return Object.entries(entry.changes_json || {}).map(([field, value]) => ({
        field,
        oldValue: Array.isArray(value.old) ? value.old.join(', ') : value.old,
        newValue: Array.isArray(value.new) ? value.new.join(', ') : value.new,
    }));
}
</script>

<template>
    <Head title="Dienstwagen Verlauf" />

    <AppLayout>
        <template #header>Verlauf {{ vehicle.kennzeichen }}</template>

        <div class="space-y-6">
            <div class="flex items-center justify-between rounded border bg-white p-4 shadow-sm">
                <div>
                    <h2 class="text-xl font-semibold text-gray-950">{{ vehicle.kennzeichen }} - {{ vehicle.marke }} {{ vehicle.modell }}</h2>
                    <p class="text-sm text-gray-500">{{ vehicle.standort?.name || 'Kein Standort' }}</p>
                </div>
                <Link :href="route('dienstwagen.index')" class="rounded border px-4 py-2 text-sm font-semibold hover:bg-gray-50">
                    Zur Übersicht
                </Link>
            </div>

            <div class="rounded border bg-white shadow-sm">
                <div v-if="entries.length === 0" class="p-8 text-center text-gray-500">
                    Noch keine Verlaufseinträge vorhanden.
                </div>

                <div v-for="entry in entries" :key="entry.id" class="border-b p-5 last:border-b-0">
                    <div class="flex items-start justify-between gap-6">
                        <div>
                            <div class="text-sm text-gray-500">{{ formatDateTime(entry.created_at) }} · {{ actor(entry) }}</div>
                            <h3 class="mt-1 font-semibold text-gray-950">{{ entry.titel }}</h3>
                            <p class="mt-1 text-sm text-gray-700">{{ entry.beschreibung || entry.aktion }}</p>
                        </div>
                        <span class="rounded bg-gray-100 px-2 py-1 text-xs font-semibold text-gray-700">{{ entry.aktion }}</span>
                    </div>

                    <table v-if="changeRows(entry).length" class="mt-4 w-full text-left text-xs">
                        <thead class="bg-gray-50 text-gray-500">
                            <tr>
                                <th class="px-3 py-2">Feld</th>
                                <th class="px-3 py-2">Vorher</th>
                                <th class="px-3 py-2">Nachher</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="change in changeRows(entry)" :key="change.field" class="border-t">
                                <td class="px-3 py-2 font-semibold">{{ change.field }}</td>
                                <td class="px-3 py-2 text-gray-600">{{ change.oldValue ?? '-' }}</td>
                                <td class="px-3 py-2 text-gray-900">{{ change.newValue ?? '-' }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
