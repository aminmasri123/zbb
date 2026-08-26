<script setup>
import { computed } from 'vue';
import { Head, Link } from '@inertiajs/vue3';

const props = defineProps({ tasks: { type: Array, default: () => [] } });
const counts = computed(() => ({
    open: props.tasks.filter(task => task.status === 'open').length,
    progress: props.tasks.filter(task => task.status === 'progress').length,
    done: props.tasks.filter(task => task.status === 'done').length,
}));
const statusLabel = { open: 'Offen', progress: 'In Bearbeitung', done: 'Erledigt' };
const priorityLabel = { low: 'Niedrig', normal: 'Normal', high: 'Hoch' };
const statusClass = status => ({
    open: 'bg-amber-100 text-amber-800',
    progress: 'bg-sky-100 text-sky-800',
    done: 'bg-emerald-100 text-emerald-800',
}[status] || 'bg-slate-100 text-slate-700');
const priorityClass = priority => priority === 'high' ? 'text-red-700' : 'text-slate-500';
const formatDate = value => value ? new Date(value).toLocaleDateString('de-DE') : 'Keine Frist';
const isOverdue = task => task.status !== 'done' && task.due_at && new Date(task.due_at) < new Date(new Date().toDateString());
</script>

<template>
    <Head title="Meine Aufgaben" />
    <main class="min-h-screen bg-sky-50 px-5 py-8">
        <div class="mx-auto max-w-6xl space-y-6">
            <header class="flex flex-wrap items-center justify-between gap-4">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-widest text-zbb">Matrix Teilnehmerportal</p>
                    <h1 class="text-2xl font-bold">Meine Aufgaben</h1>
                    <p class="mt-1 text-sm text-slate-500">Alle für Sie freigegebenen Aufgaben aus Ihren aktiven Projekten.</p>
                </div>
                <Link :href="route('participant-portal.dashboard')" class="rounded border bg-white px-4 py-2 text-sm">Dashboard</Link>
            </header>

            <section class="grid gap-3 sm:grid-cols-3">
                <div class="rounded-xl bg-white p-4 shadow"><p class="text-xs text-gray-500">Offen</p><p class="text-2xl font-bold text-amber-700">{{ counts.open }}</p></div>
                <div class="rounded-xl bg-white p-4 shadow"><p class="text-xs text-gray-500">In Bearbeitung</p><p class="text-2xl font-bold text-sky-700">{{ counts.progress }}</p></div>
                <div class="rounded-xl bg-white p-4 shadow"><p class="text-xs text-gray-500">Erledigt</p><p class="text-2xl font-bold text-emerald-700">{{ counts.done }}</p></div>
            </section>

            <section class="rounded-2xl border bg-white p-5 shadow">
                <div v-if="tasks.length" class="space-y-3">
                    <article v-for="task in tasks" :key="task.id" class="rounded-xl border p-4" :class="task.status === 'done' ? 'bg-slate-50' : 'bg-white'">
                        <div class="flex flex-wrap items-start justify-between gap-3">
                            <div class="min-w-0">
                                <div class="flex flex-wrap items-center gap-2">
                                    <h2 class="font-semibold" :class="task.status === 'done' ? 'text-slate-500 line-through' : 'text-slate-900'">{{ task.title }}</h2>
                                    <span class="rounded-full px-2.5 py-1 text-xs font-semibold" :class="statusClass(task.status)">{{ statusLabel[task.status] || task.status }}</span>
                                </div>
                                <p v-if="task.description" class="mt-2 whitespace-pre-wrap text-sm leading-6 text-slate-600">{{ task.description }}</p>
                            </div>
                            <div class="text-right text-xs">
                                <p class="font-semibold" :class="isOverdue(task) ? 'text-red-700' : 'text-slate-700'">{{ isOverdue(task) ? 'Überfällig · ' : '' }}{{ formatDate(task.due_at) }}</p>
                                <p class="mt-1" :class="priorityClass(task.priority)">Priorität: {{ priorityLabel[task.priority] || task.priority }}</p>
                            </div>
                        </div>
                        <p class="mt-3 text-xs text-slate-500">Projekt: {{ task.participation?.projekt?.name || 'Nicht angegeben' }}</p>
                    </article>
                </div>
                <div v-else class="rounded-xl border border-dashed p-8 text-center">
                    <p class="font-semibold text-slate-700">Keine Aufgaben vorhanden</p>
                    <p class="mt-1 text-sm text-slate-500">Sobald Ihr Projektteam eine Aufgabe freigibt, erscheint sie hier.</p>
                </div>
            </section>
        </div>
    </main>
</template>
