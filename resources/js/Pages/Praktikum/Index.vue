<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import Pagination from '@/Components/Pagination.vue';
import InternshipCreateModal from './InternshipCreateModal.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { reactive, ref } from 'vue';

const props = defineProps({
    internships: { type: Object, required: true },
    filters: { type: Object, default: () => ({}) },
    stats: { type: Object, default: () => ({}) },
    hostProjects: { type: Array, default: () => [] },
    supervisors: { type: Array, default: () => [] },
    locations: { type: Array, default: () => [] },
    canCreate: { type: Boolean, default: false },
});

const showCreateModal = ref(false);

const filter = reactive({
    search: props.filters.search || '',
    placement_type: props.filters.placement_type || '',
    status: props.filters.status || '',
    host_project_id: props.filters.host_project_id || '',
    supervisor_person_id: props.filters.supervisor_person_id || '',
    follow_up: props.filters.follow_up || '',
});

const applyFilters = () => router.get(route('internships.index'), filter, { preserveState: true, replace: true });
const resetFilters = () => {
    Object.assign(filter, { search: '', placement_type: '', status: '', host_project_id: '', supervisor_person_id: '', follow_up: '' });
    applyFilters();
};
const formatDate = (value) => value ? new Date(`${value.slice(0, 10)}T12:00:00`).toLocaleDateString('de-DE') : '—';
const personName = (person) => person ? `${person.vorname} ${person.nachname}`.trim() : '—';
const statusClass = (status) => ({
    geplant: 'bg-blue-100 text-blue-700',
    laufend: 'bg-amber-100 text-amber-800',
    abgeschlossen: 'bg-green-100 text-green-700',
    abgebrochen: 'bg-red-100 text-red-700',
}[status] || 'bg-gray-100 text-gray-700');
const isOverdue = (item) => Boolean(item.next_follow_up_at && ['geplant', 'laufend'].includes(item.status) && new Date(`${item.next_follow_up_at.slice(0, 10)}T23:59:59`) < new Date());
</script>

<template>
    <Head title="Praktikant:innen" />
    <AppLayout>
        <template #header>Praktikant:innen</template>

        <div class="space-y-5">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h1 class="text-2xl font-semibold text-gray-900">Praktikumsverwaltung</h1>
                    <p class="text-sm text-gray-500">Interne und externe Praktika des aktiven Projekts gemeinsam verwalten.</p>
                </div>
                <div class="flex flex-wrap gap-2">
                    <button v-if="canCreate" type="button" class="rounded-lg bg-zbb px-4 py-2 text-sm font-semibold text-white hover:bg-zbb-dark" @click="showCreateModal = true">Praktikant:in anlegen</button>
                    <Link :href="route('teilnehmer.index')" class="rounded-lg border bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:border-zbb hover:text-zbb">Zur Teilnehmerübersicht</Link>
                </div>
            </div>

            <section class="grid gap-3 sm:grid-cols-2 xl:grid-cols-5" aria-label="Praktikumskennzahlen">
                <div class="rounded-xl border bg-white p-4 shadow-sm"><p class="text-xs font-semibold uppercase text-gray-500">Gesamt</p><p class="mt-1 text-2xl font-bold">{{ stats.total || 0 }}</p></div>
                <div class="rounded-xl border border-orange-200 bg-orange-50 p-4"><p class="text-xs font-semibold uppercase text-orange-700">Intern</p><p class="mt-1 text-2xl font-bold text-orange-900">{{ stats.internal || 0 }}</p></div>
                <div class="rounded-xl border border-sky-200 bg-sky-50 p-4"><p class="text-xs font-semibold uppercase text-sky-700">Extern</p><p class="mt-1 text-2xl font-bold text-sky-900">{{ stats.external || 0 }}</p></div>
                <div class="rounded-xl border border-amber-200 bg-amber-50 p-4"><p class="text-xs font-semibold uppercase text-amber-700">Laufend</p><p class="mt-1 text-2xl font-bold text-amber-900">{{ stats.running || 0 }}</p></div>
                <button type="button" class="rounded-xl border border-red-200 bg-red-50 p-4 text-left" @click="filter.follow_up = filter.follow_up === 'overdue' ? '' : 'overdue'; applyFilters()"><p class="text-xs font-semibold uppercase text-red-700">Nachverfolgung fällig</p><p class="mt-1 text-2xl font-bold text-red-900">{{ stats.overdue || 0 }}</p></button>
            </section>

            <form class="grid gap-3 rounded-xl border bg-white p-4 shadow-sm md:grid-cols-2 xl:grid-cols-6" @submit.prevent="applyFilters">
                <input v-model="filter.search" type="search" placeholder="Name suchen" class="rounded-lg border-gray-300 text-sm xl:col-span-2" />
                <select v-model="filter.placement_type" class="rounded-lg border-gray-300 text-sm"><option value="">Intern und extern</option><option value="internal">Intern</option><option value="external">Extern</option></select>
                <select v-model="filter.status" class="rounded-lg border-gray-300 text-sm"><option value="">Alle Status</option><option value="geplant">Geplant</option><option value="laufend">Laufend</option><option value="abgeschlossen">Abgeschlossen</option><option value="abgebrochen">Abgebrochen</option></select>
                <select v-model="filter.host_project_id" class="rounded-lg border-gray-300 text-sm"><option value="">Alle Einsatzprojekte</option><option v-for="project in hostProjects" :key="project.id" :value="project.id">{{ project.name }}</option></select>
                <select v-model="filter.supervisor_person_id" class="rounded-lg border-gray-300 text-sm"><option value="">Alle Betreuungen</option><option v-for="person in supervisors" :key="person.id" :value="person.id">{{ person.nachname }}, {{ person.vorname }}</option></select>
                <div class="flex gap-2 xl:col-span-6"><button type="submit" class="rounded-lg bg-zbb px-4 py-2 text-sm font-medium text-white">Filtern</button><button type="button" class="rounded-lg border px-4 py-2 text-sm text-gray-700" @click="resetFilters">Zurücksetzen</button></div>
            </form>

            <div class="overflow-hidden rounded-xl border bg-white shadow-sm">
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 text-sm">
                        <thead class="bg-gray-50 text-left text-xs uppercase text-gray-500"><tr><th class="px-4 py-3">Praktikant:in</th><th class="px-4 py-3">Einsatzstelle</th><th class="px-4 py-3">Betreuung</th><th class="px-4 py-3">Zeitraum</th><th class="px-4 py-3">Status</th><th class="px-4 py-3">Nachverfolgung</th><th class="px-4 py-3 text-right">Aktionen</th></tr></thead>
                        <tbody class="divide-y divide-gray-100">
                            <tr v-for="item in internships.data" :key="item.id" class="align-top hover:bg-gray-50">
                                <td class="px-4 py-4"><Link :href="route('teilnehmer.edit', item.participant.id)" class="font-semibold text-zbb hover:underline">{{ personName(item.participant) }}</Link><p class="mt-1 text-xs text-gray-500">{{ item.projekt_teilnahme?.projekt?.name }}</p></td>
                                <td class="px-4 py-4"><span class="mb-1 inline-flex rounded-full px-2 py-1 text-xs font-semibold" :class="item.placement_type === 'internal' ? 'bg-orange-100 text-orange-800' : 'bg-sky-100 text-sky-800'">{{ item.placement_type === 'internal' ? 'Intern' : 'Extern' }}</span><p class="font-medium text-gray-800">{{ item.host_project?.name || item.traeger || '—' }}</p><p v-if="item.department" class="text-xs text-gray-500">{{ item.department }}</p></td>
                                <td class="px-4 py-4 text-gray-700">{{ item.supervisor ? personName(item.supervisor) : (item.contact_name || '—') }}</td>
                                <td class="whitespace-nowrap px-4 py-4 text-gray-700">{{ formatDate(item.start) }}<br />bis {{ formatDate(item.end) }}</td>
                                <td class="px-4 py-4"><span class="rounded-full px-2 py-1 text-xs font-semibold" :class="statusClass(item.status)">{{ item.status }}</span></td>
                                <td class="px-4 py-4" :class="isOverdue(item) ? 'font-semibold text-red-600' : 'text-gray-600'">{{ formatDate(item.next_follow_up_at) }}</td>
                                <td class="px-4 py-4"><div class="flex justify-end gap-2"><a :href="route('teilnehmer.praktikum.contract', item.id)" class="rounded border px-3 py-1.5 text-xs hover:border-zbb hover:text-zbb">Vertrag</a><a v-if="item.status === 'abgeschlossen'" :href="route('teilnehmer.praktikum.certificate', item.id)" class="rounded border px-3 py-1.5 text-xs hover:border-zbb hover:text-zbb">Bescheinigung</a></div></td>
                            </tr>
                            <tr v-if="!internships.data.length"><td colspan="7" class="px-4 py-12 text-center text-gray-500">Keine Praktika entsprechen den gewählten Filtern.</td></tr>
                        </tbody>
                    </table>
                </div>
                <div v-if="internships.links?.length > 3" class="border-t p-4"><Pagination :links="internships.links" /></div>
            </div>

            <InternshipCreateModal
                :visible="showCreateModal"
                :locations="locations"
                :host-projects="hostProjects"
                @close="showCreateModal = false"
            />
        </div>
    </AppLayout>
</template>
