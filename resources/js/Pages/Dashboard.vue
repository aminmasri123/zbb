<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import { Link, router, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import { useModules } from '@/utils/modules';

const props = defineProps({ dashboardCards: Object, hiddenCards: Array, roleLabel: String, apps: Object });
const page = usePage();
const { moduleEnabled } = useModules();
const editing = ref(false);
const saving = ref(false);
const hidden = ref([...(props.hiddenCards || [])]);
const permissionSet = computed(() => new Set(page.props.permissions || []));
const can = (...names) => names.some(name => permissionSet.value.has(name));

const cardMeta = {
    projects: { icon: 'la-project-diagram', color: 'bg-blue-100 text-blue-800' },
    participants: { icon: 'la-user-graduate', color: 'bg-green-100 text-green-800' },
    rooms: { icon: 'la-building', color: 'bg-yellow-100 text-yellow-800', module: 'room_management' },
    vehicles: { icon: 'la-car', color: 'bg-red-100 text-red-800', module: 'vehicle_management' },
    devices: { icon: 'la-laptop', color: 'bg-purple-100 text-purple-800', module: 'it_management' },
};
const allowedCards = computed(() => Object.entries(props.dashboardCards || {})
    .filter(([key, card]) => card.visible && (!cardMeta[key]?.module || moduleEnabled(cardMeta[key].module)))
    .map(([key, card]) => ({ key, ...card, ...cardMeta[key] })));
const shownCards = computed(() => allowedCards.value.filter(card => !hidden.value.includes(card.key)));
const toggleCard = key => hidden.value = hidden.value.includes(key) ? hidden.value.filter(item => item !== key) : [...hidden.value, key];
const save = () => {
    saving.value = true;
    router.put(route('dashboard.preferences.update'), { hidden_cards: hidden.value }, {
        preserveScroll: true,
        onSuccess: () => editing.value = false,
        onFinish: () => saving.value = false,
    });
};
const apps = computed(() => [
    { title: 'Kalender', text: 'Termine und Freigaben', route: 'apps.calendar', icon: 'la-calendar', count: 'events', allowed: can('apps.calendar') },
    { title: 'Kontakte', text: 'Ansprechpartner verwalten', route: 'apps.contacts', icon: 'la-address-book', count: 'contacts', allowed: can('apps.contacts') },
    { title: 'Dateimanager', text: 'Dateien und Ordner teilen', route: 'apps.files', icon: 'la-folder-open', count: 'files', allowed: can('apps.files') },
    { title: 'Teilnehmer', text: 'Teilnehmerliste verwalten', route: 'teilnehmer.index', icon: 'la-user-graduate', count: 'participants', allowed: can('teilnehmer.index') },
    { title: 'Taskmanager', text: 'Aufgaben steuern', route: 'apps.tasks', icon: 'la-tasks', count: 'tasks', allowed: can('apps.tasks') },
    { title: 'Popups', text: 'Hinweise anzeigen', route: 'apps.popups', icon: 'la-bullhorn', count: 'popups', allowed: can('apps.popups') },
].filter(app => app.allowed));
</script>

<template>
    <AppLayout title="Dashboard">
        <template #header>{{ $t('Dashboard') }}</template>
        <div class="min-h-screen bg-[var(--bg)] py-2 text-[var(--primary)]">
            <div class="mx-auto mb-8 max-w-7xl px-4">
                <div class="rounded-lg px-8 py-4 text-[var(--buttonTextPrimary)] shadow-lg" style="background: linear-gradient(90deg, var(--buttonPrimary), var(--borderHover));">
                    <div class="flex flex-wrap items-start justify-between gap-4">
                        <div><h1 class="mb-2 text-2xl font-bold">Willkommen im webbasierten ERP-System des ZBB</h1><p class="text-lg">Ihre Übersicht für {{ roleLabel || 'Ihre Aufgaben' }}</p></div>
                        <button type="button" class="rounded-md border border-white/60 bg-white/15 px-4 py-2 text-sm font-semibold hover:bg-white/25" @click="editing = !editing"><i class="la la-sliders-h mr-2"></i>Dashboard anpassen</button>
                    </div>
                </div>
            </div>

            <div v-if="editing" class="mx-auto mb-6 max-w-7xl px-4">
                <section class="rounded-lg border border-[var(--border)] bg-[var(--card)] p-5 shadow-sm">
                    <h2 class="font-semibold">Karten ein- oder ausblenden</h2><p class="mt-1 text-sm text-[var(--secondary)]">Es werden nur Karten angeboten, für die Sie berechtigt sind.</p>
                    <div class="mt-4 flex flex-wrap gap-3"><label v-for="card in allowedCards" :key="card.key" class="flex cursor-pointer items-center gap-2 rounded-md border border-[var(--border)] px-3 py-2"><input type="checkbox" :checked="!hidden.includes(card.key)" @change="toggleCard(card.key)"><span>{{ card.label }}</span></label></div>
                    <div class="mt-4 flex gap-3"><button type="button" class="rounded bg-[var(--buttonPrimary)] px-4 py-2 text-sm font-semibold text-[var(--buttonTextPrimary)] disabled:opacity-50" :disabled="saving" @click="save">{{ saving ? 'Speichert …' : 'Auswahl speichern' }}</button><button type="button" class="px-3 py-2 text-sm" @click="editing = false">Abbrechen</button></div>
                </section>
            </div>

            <div v-if="shownCards.length" class="mx-auto mb-10 grid max-w-7xl grid-cols-1 gap-6 px-4 sm:grid-cols-2" :class="shownCards.length >= 5 ? 'md:grid-cols-5' : 'lg:grid-cols-4'">
                <div v-for="card in shownCards" :key="card.key" :class="['flex items-center gap-4 rounded-lg p-6 shadow', card.color]">
                    <i :class="['la la-2x', card.icon]"></i><div><div class="text-2xl font-bold">{{ card.value }}</div><div class="text-sm font-semibold">{{ card.label }}</div><div class="mt-1 text-xs opacity-75">{{ card.scope }}</div></div>
                </div>
            </div>
            <p v-else class="mx-auto mb-10 max-w-7xl px-4 text-sm text-[var(--secondary)]">Sie haben alle verfügbaren Karten ausgeblendet. Über „Dashboard anpassen“ können Sie Karten wieder einblenden.</p>

            <div v-if="apps.length" class="mx-auto mb-10 max-w-7xl px-4">
                <div class="mb-3 flex items-center justify-between border-b border-gray-200 pb-2"><h2 class="text-lg font-semibold">Apps</h2><Link v-if="can('apps.index')" :href="route('apps.index')" class="text-sm font-medium text-[var(--buttonPrimary)]">Alle Apps</Link></div>
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-6"><Link v-for="app in apps" :key="app.title" :href="route(app.route)" class="rounded border border-[var(--border)] bg-[var(--card)] p-4 shadow-sm transition hover:border-[var(--borderHover)] hover:shadow"><div class="mb-3 flex items-center justify-between"><i :class="['la la-2x text-[var(--buttonPrimary)]', app.icon]"></i><span class="rounded bg-[var(--muted)] px-2 py-1 text-xs font-semibold">{{ props.apps?.[app.count] ?? 0 }}</span></div><h3 class="text-sm font-semibold">{{ app.title }}</h3><p class="mt-1 text-xs leading-5 text-[var(--secondary)]">{{ app.text }}</p></Link></div>
            </div>
        </div>
    </AppLayout>
</template>
