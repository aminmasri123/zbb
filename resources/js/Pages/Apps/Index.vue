<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import { Link } from '@inertiajs/vue3';

const props = defineProps({
    stats: Object,
});

const apps = [
    { title: 'Kalender', text: 'Termine mit Projekt-, Team- und Personenfreigaben.', route: 'apps.calendar', icon: 'la-calendar', count: 'events' },
    { title: 'Kontakte', text: 'Gemeinsame und private Ansprechpartner sauber pflegen.', route: 'apps.contacts', icon: 'la-address-book', count: 'contacts' },
    { title: 'Dateimanager', text: 'Dateien und Ordner privat, im Team, projektbezogen oder offen teilen.', route: 'apps.files', icon: 'la-folder-open', count: 'files' },
    { title: 'Teilnehmer', text: 'Teilnehmerliste, Import und Projektzuweisungen verwalten.', route: 'teilnehmer.index', icon: 'la-user-graduate', count: 'participants' },
    { title: 'Taskmanager', text: 'Aufgaben nach Status, Priorität und Verantwortlichen steuern.', route: 'apps.tasks', icon: 'la-tasks', count: 'tasks' },
    { title: 'Popups', text: 'Hinweise gezielt für Personen, Teams oder Projekte ausspielen.', route: 'apps.popups', icon: 'la-bullhorn', count: 'popups' },
];
</script>

<template>
    <AppLayout title="Apps">
        <template #header>Apps</template>

        <div class="min-h-screen bg-gray-50 py-6">
            <div class="mx-auto max-w-7xl px-4">
                <div class="mb-6 border-b border-gray-200 pb-4">
                    <h1 class="text-2xl font-semibold text-gray-900">Apps</h1>
                    <p class="mt-1 text-sm text-gray-600">Kalender, Kontakte, Dateien, Aufgaben und Popups an einem Ort.</p>
                </div>

                <div class="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-6">
                    <Link
                        v-for="app in apps"
                        :key="app.title"
                        :href="route(app.route)"
                        class="rounded border border-gray-200 bg-white p-4 shadow-sm transition hover:border-orange-400 hover:shadow"
                    >
                        <div class="mb-4 flex items-center justify-between">
                            <i :class="['la la-2x text-orange-500', app.icon]"></i>
                            <span class="rounded bg-gray-100 px-2 py-1 text-xs font-semibold text-gray-700">{{ props.stats?.[app.count] ?? 0 }}</span>
                        </div>
                        <h2 class="text-base font-semibold text-gray-900">{{ app.title }}</h2>
                        <p class="mt-2 text-sm leading-5 text-gray-600">{{ app.text }}</p>
                    </Link>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
