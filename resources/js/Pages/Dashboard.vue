<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import Welcome from '@/Components/Welcome.vue';
import { Link } from '@inertiajs/vue3';

const props = defineProps({
    projekte: Number,
    dienstwagen: Number,
    raeume: Number,
    geraete: Number,
    teilnehmer: Number,
    apps: Object,
});

const stats = [
    { label: 'Projekte', value: props.projekte || 0, icon: 'la-project-diagram', color: 'bg-blue-100 text-blue-800' },
    { label: 'Teilnehmer', value: props.teilnehmer || 0, icon: 'la-user-graduate', color: 'bg-green-100 text-green-800' },
    { label: 'Raeumlichkeiten', value: props.raeume || 0, icon: 'la-building', color: 'bg-yellow-100 text-yellow-800' },
    { label: 'Dienstwagen', value: props.dienstwagen || 0, icon: 'la-car', color: 'bg-red-100 text-red-800' },
    { label: 'Geraete', value: props.geraete || 0, icon: 'la-laptop', color: 'bg-purple-100 text-purple-800' },
];

const apps = [
    { title: 'Kalender', text: 'Termine und Freigaben', route: 'apps.calendar', icon: 'la-calendar', count: 'events' },
    { title: 'Kontakte', text: 'Ansprechpartner verwalten', route: 'apps.contacts', icon: 'la-address-book', count: 'contacts' },
    { title: 'Dateimanager', text: 'Dateien und Ordner teilen', route: 'apps.files', icon: 'la-folder-open', count: 'files' },
    { title: 'Teilnehmer', text: 'Teilnehmerliste verwalten', route: 'teilnehmer.index', icon: 'la-user-graduate', count: 'participants' },
    { title: 'Taskmanager', text: 'Aufgaben steuern', route: 'apps.tasks', icon: 'la-tasks', count: 'tasks' },
    { title: 'Popups', text: 'Hinweise anzeigen', route: 'apps.popups', icon: 'la-bullhorn', count: 'popups' },
];
</script>

<template>
    <AppLayout title="Dashboard">
        <template #header>{{ $t('Dashboard') }}</template>

        <div class="min-h-screen bg-[var(--bg)] py-2 text-[var(--primary)]">
            <div class="mx-auto mb-8 max-w-7xl px-4">
                <div class="rounded-lg px-8 py-4 text-[var(--buttonTextPrimary)] shadow-lg" style="background: linear-gradient(90deg, var(--buttonPrimary), var(--borderHover));">
                    <h1 class="mb-2 text-2xl font-bold">Willkommen im webbasierten ERP-System des ZBB</h1>
                    <p class="text-lg">Das webbasierte Verwaltungssystem des Zentrums fuer Bildung und Beruf Saar.</p>
                </div>
            </div>

            <div class="mx-auto mb-10 grid max-w-7xl grid-cols-1 gap-6 px-4 sm:grid-cols-2 md:grid-cols-5">
                <div v-for="stat in stats" :key="stat.label" :class="['flex items-center gap-4 rounded-lg p-6 shadow', stat.color]">
                    <i :class="['la la-2x', stat.icon]"></i>
                    <div>
                        <div class="text-2xl font-bold">{{ stat.value }}</div>
                        <div class="text-sm">{{ stat.label }}</div>
                    </div>
                </div>
            </div>

            <div class="mx-auto mb-10 max-w-7xl px-4">
                <div class="mb-3 flex items-center justify-between border-b border-gray-200 pb-2">
                    <h2 class="text-lg font-semibold text-gray-900">Apps</h2>
                    <Link :href="route('apps.index')" class="text-sm font-medium text-[var(--buttonPrimary)] hover:text-[var(--buttonPrimaryHover)]">Alle Apps</Link>
                </div>

                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-6">
                    <Link
                        v-for="app in apps"
                        :key="app.title"
                        :href="route(app.route)"
                        class="rounded border border-[var(--border)] bg-[var(--card)] p-4 shadow-sm transition hover:border-[var(--borderHover)] hover:shadow"
                    >
                        <div class="mb-3 flex items-center justify-between">
                            <i :class="['la la-2x text-[var(--buttonPrimary)]', app.icon]"></i>
                            <span class="rounded bg-[var(--muted)] px-2 py-1 text-xs font-semibold text-[var(--primary)]">{{ props.apps?.[app.count] ?? 0 }}</span>
                        </div>
                        <h3 class="text-sm font-semibold text-[var(--primary)]">{{ app.title }}</h3>
                        <p class="mt-1 text-xs leading-5 text-[var(--secondary)]">{{ app.text }}</p>
                    </Link>
                </div>
            </div>
        </div>

        <div class="py-12">
            <div class="mx-auto max-w-7xl sm:px-6 lg:px-8">
                <div class="overflow-hidden bg-[var(--card)] shadow-xl sm:rounded-lg">
                    <Welcome />
                </div>
            </div>
        </div>
    </AppLayout>
</template>
