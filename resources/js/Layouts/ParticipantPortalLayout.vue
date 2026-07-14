<script setup>
import { computed, ref } from 'vue';
import { Link, usePage } from '@inertiajs/vue3';
import axios from 'axios';
import { setTheme } from '@/theme';

defineProps({ title: { type: String, default: '' }, eyebrow: { type: String, default: 'Teilnehmerportal' }, subtitle: { type: String, default: '' }, showHeader: { type: Boolean, default: true } });
const page = usePage(); const mobileOpen = ref(false); const paletteOpen = ref(false);
const features = computed(() => page.props.participantPortalNavigation || {});
const currentTheme = ref(document.documentElement.dataset.theme || localStorage.getItem('theme') || 'air');
const themeOptions = [
    { key: 'air', label: 'Air', colors: ['#0ea5e9','#10b981','#f7fbff'] }, { key: 'dark', label: 'Dark', colors: ['#0c1016','#60a5fa','#f4f7fb'] },
    { key: 'womanly', label: 'Womanly', colors: ['#be185d','#f1cfe0','#fff7fb'] }, { key: 'champion', label: 'Champion', colors: ['#b45309','#f2d89b','#fffaf0'] },
    { key: 'sprint', label: 'Sprint', colors: ['#059669','#bfe8d8','#f5fff9'] }, { key: 'arena', label: 'Arena', colors: ['#334155','#cbd5e1','#f8fafc'] },
    { key: 'pulse', label: 'Pulse', colors: ['#ea580c','#fed7aa','#fff7ed'] }, { key: 'trail', label: 'Trail', colors: ['#4d7c0f','#d6dfc6','#f6f8f2'] },
    { key: 'bazaar', label: 'Bazaar', colors: ['#ff8a00','#00a8c6','#e7f8fb'] }, { key: 'vital', label: 'Vital', colors: ['#6CC63A','#262827','#FFFFFF'] },
];
const items = computed(() => [
    {label:'Übersicht',hint:'Alles auf einen Blick',icon:'⌂',route:'participant-portal.dashboard',show:true},
    {label:'Jobs & Bewerbungen',hint:'Suchen und bewerben',icon:'↗',route:'participant-portal.jobs.index',show:features.value.jobs},
    {label:'Kurse & Lernen',hint:'Lerninhalte öffnen',icon:'◇',route:'participant-portal.learning.index',show:features.value.learning},
    {label:'Kurstermine',hint:'Präsenz und online',icon:'▷',route:'participant-portal.learning.sessions.index',show:features.value.learning},
    {label:'Anwesenheit & Aufgaben',hint:'Zeiten und Termine',icon:'✓',route:'participant-portal.self-service.index',show:features.value.attendance},
    {label:'Dokumente',hint:'Unterlagen verwalten',icon:'▤',route:'participant-portal.documents.index',show:features.value.profile},
    {label:'Lebenslauf',hint:'Profil und Erfahrungen',icon:'◎',route:'participant-portal.resume.index',show:features.value.profile},
    {label:'Bewerbungsstudio',hint:'CV, Deckblatt & Anschreiben',icon:'✦',route:'participant-portal.career-studio.index',show:features.value.profile},
    {label:'Nachrichten',hint:'Mit dem Projektteam',icon:'✉',route:'participant-portal.messages.index',show:features.value.messaging},
    {label:'Einwilligungen',hint:'Freigaben verwalten',icon:'◈',route:'participant-portal.consents.index',show:features.value.consents},
    {label:'Meine Daten',hint:'Auskunft und Export',icon:'⌁',route:'participant-portal.data-requests.index',show:true},
    {label:'Hinweise',hint:'Benachrichtigungen',icon:'◉',route:'participant-portal.notification-preferences.index',show:true},
].filter(item=>item.show));
const active = name => route().current(name) || (name.includes('learning.index') && route().current('participant-portal.learning.*'));
const selectTheme = async theme => { const previous=currentTheme.value; currentTheme.value=theme; setTheme(theme); try{await axios.post(route('participant-portal.theme.update'),{theme})}catch(e){currentTheme.value=previous;setTheme(previous);console.error('Farbpalette konnte nicht gespeichert werden.',e)} };
</script>
<template><div class="min-h-screen bg-[var(--bg)] text-[var(--primary)]"><div class="pointer-events-none fixed inset-x-0 top-0 h-72 bg-gradient-to-br from-[var(--headerBg)] via-[var(--surfaceTint)] to-[var(--bg)]"></div>
<aside class="fixed inset-y-0 left-0 z-40 hidden w-72 border-r border-white/10 bg-[var(--sidebarBg)] text-white lg:flex lg:flex-col"><div class="border-b border-white/10 px-6 py-6"><p class="text-xs font-bold uppercase tracking-[.24em] text-cyan-300">Matrix</p><p class="mt-1 text-xl font-semibold">Mein Portal</p><p class="mt-2 text-xs leading-5 text-slate-400">Ihr persönlicher Bereich für Projekte, Bewerbungen und Lernen.</p></div>
<nav class="flex-1 space-y-1 overflow-y-auto p-4"><Link v-for="item in items" :key="item.route" :href="route(item.route)" class="group flex items-center gap-3 rounded-xl px-3 py-2.5 transition" :class="active(item.route)?'bg-white text-slate-950 shadow-lg':'text-slate-300 hover:bg-white/10 hover:text-white'"><span class="grid h-9 w-9 place-items-center rounded-lg text-lg" :class="active(item.route)?'bg-cyan-100 text-cyan-800':'bg-white/10'">{{item.icon}}</span><span><span class="block text-sm font-semibold">{{item.label}}</span><span class="block text-[11px] text-slate-500">{{item.hint}}</span></span></Link></nav>
<div class="border-t border-white/10 p-4"><button type="button" class="flex w-full items-center gap-3 rounded-xl px-3 py-3 text-left text-sm text-slate-300 hover:bg-white/10 hover:text-white" @click="paletteOpen=!paletteOpen"><span class="grid h-9 w-9 place-items-center rounded-lg bg-white/10">◉</span><span class="flex-1">Farbpalette</span><span>{{paletteOpen?'−':'+'}}</span></button><div v-if="paletteOpen" class="grid grid-cols-2 gap-2 px-1 pb-3"><button v-for="theme in themeOptions" :key="theme.key" type="button" class="rounded-lg border p-2 text-left text-[11px]" :class="currentTheme===theme.key?'border-white bg-white/20':'border-white/10 hover:bg-white/10'" @click="selectTheme(theme.key)"><span class="mb-1 flex gap-0.5"><span v-for="color in theme.colors" :key="color" class="h-3 flex-1 rounded-sm" :style="{backgroundColor:color}"></span></span>{{theme.label}}</button></div><Link :href="route('participant-portal.contact.index')" class="flex items-center gap-3 rounded-xl px-3 py-3 text-sm text-slate-300 hover:bg-white/10 hover:text-white"><span class="grid h-9 w-9 place-items-center rounded-lg bg-white/10">⚙</span>Konto & Kontakt</Link></div></aside>
<div class="relative lg:pl-72"><header class="sticky top-0 z-30 border-b border-[var(--border)] bg-[var(--card)]/90 px-4 py-3 backdrop-blur-xl sm:px-7 lg:hidden"><div class="flex items-center justify-between"><div><p class="text-xs font-bold uppercase tracking-[.2em] text-[var(--buttonPrimary)]">Matrix</p><p class="font-semibold">Mein Portal</p></div><button type="button" class="rounded-xl border border-[var(--border)] bg-[var(--card)] px-3 py-2 text-sm font-semibold" @click="mobileOpen=!mobileOpen">{{mobileOpen?'Schließen':'Menü'}}</button></div><nav v-if="mobileOpen" class="mt-3 grid gap-1 border-t pt-3"><Link v-for="item in items" :key="item.route" :href="route(item.route)" class="rounded-lg px-3 py-2 text-sm font-medium" :class="active(item.route)?'bg-[var(--buttonPrimary)] text-[var(--buttonTextPrimary)]':'hover:bg-[var(--surfaceTint)]'">{{item.label}}</Link><button type="button" class="rounded-lg px-3 py-2 text-left text-sm font-medium hover:bg-[var(--surfaceTint)]" @click="paletteOpen=!paletteOpen">Farbpalette</button><div v-if="paletteOpen" class="grid grid-cols-2 gap-2"><button v-for="theme in themeOptions" :key="theme.key" class="rounded border border-[var(--border)] p-2 text-left text-xs" @click="selectTheme(theme.key)">{{theme.label}}</button></div></nav></header>
<main class="relative mx-auto max-w-[1500px] px-4 py-6 sm:px-7 lg:px-10 lg:py-9"><header v-if="showHeader" class="mb-7 flex flex-wrap items-end justify-between gap-4"><div><p class="text-xs font-bold uppercase tracking-[.2em] text-[var(--buttonPrimary)]">{{eyebrow}}</p><h1 class="mt-1 text-2xl font-bold sm:text-3xl">{{title}}</h1><p v-if="subtitle" class="mt-2 max-w-3xl text-sm leading-6 text-[var(--secondary)]">{{subtitle}}</p></div><div class="flex flex-wrap gap-2"><slot name="actions"/></div></header><slot/></main></div></div></template>
