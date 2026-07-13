<script setup>
import { computed, ref } from 'vue';
import { Link, usePage } from '@inertiajs/vue3';

defineProps({ title: { type: String, required: true }, eyebrow: { type: String, default: 'Teilnehmerportal' }, subtitle: { type: String, default: '' } });
const page = usePage(); const mobileOpen = ref(false); const features = computed(() => page.props.participantPortalNavigation || {});
const items = computed(() => [
    { label: 'Übersicht', hint: 'Alles auf einen Blick', icon: '⌂', route: 'participant-portal.dashboard', show: true },
    { label: 'Jobs & Bewerbungen', hint: 'Suchen und bewerben', icon: '↗', route: 'participant-portal.jobs.index', show: features.value.jobs },
    { label: 'Kurse & Lernen', hint: 'Lerninhalte öffnen', icon: '◇', route: 'participant-portal.learning.index', show: features.value.learning },
    { label: 'Kurstermine', hint: 'Präsenz und online', icon: '◷', route: 'participant-portal.learning.sessions.index', show: features.value.learning },
    { label: 'Anwesenheit & Aufgaben', hint: 'Zeiten und Termine', icon: '✓', route: 'participant-portal.self-service.index', show: features.value.attendance },
    { label: 'Dokumente', hint: 'Unterlagen verwalten', icon: '▤', route: 'participant-portal.documents.index', show: features.value.profile },
    { label: 'Lebenslauf', hint: 'Profil und Erfahrungen', icon: '◎', route: 'participant-portal.resume.index', show: features.value.profile },
    { label: 'Nachrichten', hint: 'Mit dem Projektteam', icon: '✉', route: 'participant-portal.messages.index', show: features.value.messaging },
    { label: 'Einwilligungen', hint: 'Freigaben verwalten', icon: '◈', route: 'participant-portal.consents.index', show: features.value.consents },
    { label: 'Meine Daten', hint: 'Auskunft und Export', icon: '⌁', route: 'participant-portal.data-requests.index', show: true },
    { label: 'Hinweise', hint: 'Benachrichtigungen', icon: '◉', route: 'participant-portal.notification-preferences.index', show: true },
].filter((item) => item.show));
const active = (name) => route().current(name) || (name.includes('learning.index') && route().current('participant-portal.learning.*'));
</script>

<template>
    <div class="min-h-screen bg-[#f3f7fb] text-slate-900">
        <div class="pointer-events-none fixed inset-x-0 top-0 h-72 bg-gradient-to-br from-sky-100 via-cyan-50 to-orange-50"></div>
        <aside class="fixed inset-y-0 left-0 z-40 hidden w-72 border-r border-slate-200/80 bg-slate-950 text-white lg:flex lg:flex-col">
            <div class="border-b border-white/10 px-6 py-6"><p class="text-xs font-bold uppercase tracking-[.24em] text-cyan-300">Matrix</p><p class="mt-1 text-xl font-semibold">Mein Portal</p><p class="mt-2 text-xs leading-5 text-slate-400">Ihr persönlicher Bereich für Projekte, Bewerbungen und Lernen.</p></div>
            <nav class="flex-1 space-y-1 overflow-y-auto p-4"><Link v-for="item in items" :key="item.route" :href="route(item.route)" class="group flex items-center gap-3 rounded-xl px-3 py-2.5 transition" :class="active(item.route) ? 'bg-white text-slate-950 shadow-lg' : 'text-slate-300 hover:bg-white/10 hover:text-white'"><span class="grid h-9 w-9 place-items-center rounded-lg text-lg" :class="active(item.route) ? 'bg-cyan-100 text-cyan-800' : 'bg-white/10'">{{ item.icon }}</span><span><span class="block text-sm font-semibold">{{ item.label }}</span><span class="block text-[11px]" :class="active(item.route) ? 'text-slate-500' : 'text-slate-500 group-hover:text-slate-400'">{{ item.hint }}</span></span></Link></nav>
            <div class="border-t border-white/10 p-4"><Link :href="route('participant-portal.contact.index')" class="flex items-center gap-3 rounded-xl px-3 py-3 text-sm text-slate-300 hover:bg-white/10 hover:text-white"><span class="grid h-9 w-9 place-items-center rounded-lg bg-white/10">⚙</span>Konto & Kontakt</Link></div>
        </aside>

        <div class="relative lg:pl-72">
            <header class="sticky top-0 z-30 border-b border-slate-200/80 bg-white/85 px-4 py-3 backdrop-blur-xl sm:px-7 lg:hidden"><div class="flex items-center justify-between"><div><p class="text-xs font-bold uppercase tracking-[.2em] text-cyan-700">Matrix</p><p class="font-semibold">Mein Portal</p></div><button type="button" class="rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm font-semibold" @click="mobileOpen=!mobileOpen">{{ mobileOpen ? 'Schließen' : 'Menü' }}</button></div><nav v-if="mobileOpen" class="mt-3 grid gap-1 border-t pt-3"><Link v-for="item in items" :key="item.route" :href="route(item.route)" class="rounded-lg px-3 py-2 text-sm font-medium" :class="active(item.route)?'bg-slate-900 text-white':'text-slate-700 hover:bg-slate-100'">{{ item.label }}</Link></nav></header>
            <main class="relative mx-auto max-w-[1500px] px-4 py-6 sm:px-7 lg:px-10 lg:py-9">
                <header class="mb-7 flex flex-wrap items-end justify-between gap-4"><div><p class="text-xs font-bold uppercase tracking-[.2em] text-cyan-700">{{ eyebrow }}</p><h1 class="mt-1 text-2xl font-bold tracking-tight text-slate-950 sm:text-3xl">{{ title }}</h1><p v-if="subtitle" class="mt-2 max-w-3xl text-sm leading-6 text-slate-600">{{ subtitle }}</p></div><div class="flex flex-wrap gap-2"><slot name="actions" /></div></header>
                <slot />
            </main>
        </div>
    </div>
</template>
