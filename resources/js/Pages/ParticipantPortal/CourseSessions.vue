<script setup>
import { Head, Link } from '@inertiajs/vue3';
defineProps({ sessions: { type: Array, default: () => [] } });
const dateTime = (value) => new Intl.DateTimeFormat('de-DE', { dateStyle: 'medium', timeStyle: 'short' }).format(new Date(value));
const modeLabel = { presence: 'Präsenz', online: 'Online', hybrid: 'Hybrid' };
const attendanceLabel = { planned: 'Geplant', attended: 'Teilgenommen', absent: 'Nicht teilgenommen', excused: 'Entschuldigt' };
</script>
<template>
    <Head title="Meine Kurstermine" />
    <main class="min-h-screen bg-slate-50 px-6 py-8"><div class="mx-auto max-w-5xl">
        <div class="flex items-center justify-between"><div><p class="text-xs font-semibold uppercase tracking-widest text-zbb">Teilnehmerportal</p><h1 class="text-2xl font-bold">Meine Kurstermine</h1></div><Link :href="route('participant-portal.dashboard')" class="rounded border bg-white px-4 py-2 text-sm">Zur Übersicht</Link></div>
        <div v-if="sessions.length" class="mt-6 space-y-4"><article v-for="session in sessions" :key="session.id" class="rounded-2xl border bg-white p-6 shadow-sm">
            <div class="flex flex-wrap items-start justify-between gap-4"><div><p class="text-sm font-semibold text-blue-700">{{ session.course?.title }}</p><h2 class="text-lg font-bold">{{ session.title }}</h2><p v-if="session.description" class="mt-2 text-sm text-slate-600">{{ session.description }}</p></div><span class="rounded-full bg-blue-50 px-3 py-1 text-sm font-semibold text-blue-700">{{ modeLabel[session.mode] }}</span></div>
            <dl class="mt-5 grid gap-3 text-sm sm:grid-cols-2"><div><dt class="text-slate-500">Beginn</dt><dd class="font-semibold">{{ dateTime(session.starts_at) }} Uhr</dd></div><div><dt class="text-slate-500">Ende</dt><dd class="font-semibold">{{ dateTime(session.ends_at) }} Uhr</dd></div><div v-if="session.location"><dt class="text-slate-500">Ort</dt><dd class="font-semibold">{{ session.location }}</dd></div><div><dt class="text-slate-500">Teilnahmestatus</dt><dd class="font-semibold">{{ attendanceLabel[session.attendance?.[0]?.status || 'planned'] }}</dd></div></dl>
            <a v-if="session.online_url" :href="session.online_url" target="_blank" rel="noopener noreferrer" class="mt-5 inline-flex rounded bg-blue-600 px-4 py-2 text-sm font-semibold text-white">Online-Termin öffnen</a>
        </article></div>
        <div v-else class="mt-6 rounded-2xl border bg-white p-10 text-center text-slate-500">Für Ihre eingeschriebenen Kurse sind noch keine veröffentlichten Termine vorhanden.</div>
    </div></main>
</template>
