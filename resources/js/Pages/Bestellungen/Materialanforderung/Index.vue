<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import { computed, ref } from 'vue'
import { Head, Link, router } from '@inertiajs/vue3'

const props = defineProps({
    anforderungen: { type: Array, default: () => [] },
    filters: { type: Object, default: () => ({}) },
    canCreateRequest: { type: Boolean, default: false },
    canOpenRequest: { type: Boolean, default: false },
    hasActiveProject: { type: Boolean, default: false },
})

const search = ref(props.filters.search || '')
const statusFilter = ref('alle')
const statusMeta = {
    entwurf: ['Entwurf', 'bg-gray-100 text-gray-700'],
    eingereicht: ['Eingereicht', 'bg-blue-100 text-blue-700'],
    sachlich_genehmigt: ['Sachlich genehmigt', 'bg-violet-100 text-violet-700'],
    kaufmaennisch_genehmigt: ['Kaufmännisch genehmigt', 'bg-emerald-100 text-emerald-700'],
    bestellt: ['Bestellt', 'bg-cyan-100 text-cyan-700'],
    teilweise_geliefert: ['Teilweise geliefert', 'bg-amber-100 text-amber-800'],
    geliefert: ['Geliefert', 'bg-green-100 text-green-800'],
    zur_ueberarbeitung: ['Zur Überarbeitung', 'bg-orange-100 text-orange-800'],
    storniert: ['Storniert', 'bg-red-100 text-red-700'],
}

const filtered = computed(() => statusFilter.value === 'alle'
    ? props.anforderungen
    : props.anforderungen.filter((item) => item.status === statusFilter.value))
const openCount = computed(() => props.anforderungen.filter((item) => !['geliefert', 'storniert'].includes(item.status)).length)
const approvalCount = computed(() => props.anforderungen.filter((item) => ['eingereicht', 'sachlich_genehmigt'].includes(item.status)).length)
const total = computed(() => props.anforderungen.reduce((sum, item) => sum + Number(item.endsumme || 0), 0))

function runSearch() {
    router.get(route('materialanforderung.index'), { search: search.value }, { preserveState: true, replace: true })
}
function euro(value) {
    return Number(value || 0).toLocaleString('de-DE', { style: 'currency', currency: 'EUR' })
}
function date(value) {
    return value ? new Intl.DateTimeFormat('de-DE').format(new Date(value)) : '–'
}
</script>

<template>
    <Head title="Materialanforderungen" />
    <AppLayout>
        <template #header>Materialanforderungen</template>

        <div class="space-y-5">
            <div v-if="!hasActiveProject" class="rounded-xl border border-blue-200 bg-blue-50 px-4 py-3 text-sm text-blue-800">
                Sie arbeiten ohne Projektzuweisung. Die Übersicht und Ihre Verwaltungsfunktionen stehen trotzdem zur Verfügung. Eine neue Materialanforderung kann nur innerhalb eines Projekts angelegt werden.
            </div>
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm"><p class="text-sm text-gray-500">Offene Vorgänge</p><p class="mt-1 text-2xl font-bold text-gray-900">{{ openCount }}</p></div>
                <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm"><p class="text-sm text-gray-500">In Freigabe</p><p class="mt-1 text-2xl font-bold text-violet-700">{{ approvalCount }}</p></div>
                <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm"><p class="text-sm text-gray-500">Angezeigtes Volumen</p><p class="mt-1 text-2xl font-bold text-orange-600">{{ euro(total) }}</p></div>
            </div>

            <section class="rounded-xl border border-gray-200 bg-white shadow-sm">
                <div class="flex flex-col gap-3 border-b border-gray-200 p-4 md:flex-row md:items-center md:justify-between">
                    <form class="flex min-w-0 flex-1 gap-2" @submit.prevent="runSearch">
                        <div class="relative min-w-0 flex-1"><i class="las la-search absolute left-3 top-3 text-gray-400"></i><input v-model="search" type="search" class="w-full rounded-lg border-gray-300 pl-10" placeholder="Nummer, Bemerkung oder Artikel suchen" /></div>
                        <button type="submit" class="rounded-lg border border-gray-300 px-4 py-2 text-sm font-semibold">Suchen</button>
                    </form>
                    <div class="flex gap-2">
                        <select v-model="statusFilter" class="min-w-0 flex-1 rounded-lg border-gray-300 text-sm md:w-52"><option value="alle">Alle Status</option><option v-for="(meta, status) in statusMeta" :key="status" :value="status">{{ meta[0] }}</option></select>
                        <Link v-if="canCreateRequest" :href="route('materialanforderung.create')" class="inline-flex shrink-0 items-center gap-2 rounded-lg bg-orange-600 px-4 py-2 text-sm font-semibold text-white"><i class="las la-plus"></i><span class="hidden sm:inline">Neue Anforderung</span></Link>
                    </div>
                </div>

                <div class="hidden overflow-x-auto md:block">
                    <table class="min-w-full text-sm">
                        <thead class="bg-gray-50 text-left text-xs uppercase tracking-wide text-gray-500"><tr><th class="px-4 py-3">Nr.</th><th class="px-4 py-3">Erstellt</th><th class="px-4 py-3">Antragsteller</th><th class="px-4 py-3">Status</th><th class="px-4 py-3">Benötigt am</th><th class="px-4 py-3">Kostenstelle</th><th class="px-4 py-3 text-right">Endsumme</th><th class="px-4 py-3"></th></tr></thead>
                        <tbody class="divide-y divide-gray-100"><tr v-for="item in filtered" :key="item.id" class="hover:bg-gray-50"><td class="px-4 py-3 font-semibold">#{{ item.id }}</td><td class="px-4 py-3">{{ date(item.created_at) }}</td><td class="px-4 py-3">{{ item.besteller?.name || '–' }}</td><td class="px-4 py-3"><div class="flex flex-wrap items-center gap-1.5"><span class="rounded-full px-2.5 py-1 text-xs font-semibold" :class="statusMeta[item.status]?.[1]">{{ statusMeta[item.status]?.[0] || item.status }}</span><span v-if="item.von_mir_bearbeitet" class="rounded-full bg-slate-100 px-2 py-1 text-xs font-medium text-slate-700">Von mir bearbeitet</span></div></td><td class="px-4 py-3"><span v-if="item.prioritaet === 'dringend'" class="mr-1 text-red-600" title="Dringend">●</span>{{ date(item.benoetigt_am) }}</td><td class="px-4 py-3">{{ item.kostenstelle }}</td><td class="px-4 py-3 text-right font-semibold">{{ euro(item.endsumme) }}</td><td class="px-4 py-3 text-right"><Link v-if="canOpenRequest" :href="route('materialanforderung.show', item.id)" class="rounded-lg border border-gray-300 px-3 py-1.5 font-semibold">Öffnen</Link></td></tr><tr v-if="filtered.length === 0"><td colspan="8" class="px-4 py-10 text-center text-gray-500">Keine Materialanforderungen gefunden.</td></tr></tbody>
                    </table>
                </div>

                <div class="divide-y divide-gray-100 md:hidden">
                    <article v-for="item in filtered" :key="item.id" class="p-4">
                        <div class="flex items-start justify-between gap-3"><div><p class="font-bold text-gray-900">Materialanforderung #{{ item.id }}</p><p class="text-sm text-gray-500">{{ item.besteller?.name || '–' }} · {{ date(item.created_at) }}</p><span v-if="item.von_mir_bearbeitet" class="mt-1 inline-block rounded-full bg-slate-100 px-2 py-1 text-xs font-medium text-slate-700">Von mir bearbeitet</span></div><span class="shrink-0 rounded-full px-2 py-1 text-xs font-semibold" :class="statusMeta[item.status]?.[1]">{{ statusMeta[item.status]?.[0] || item.status }}</span></div>
                        <div class="mt-3 grid grid-cols-2 gap-3 text-sm"><div><span class="block text-xs text-gray-500">Kostenstelle</span>{{ item.kostenstelle }}</div><div><span class="block text-xs text-gray-500">Benötigt am</span>{{ date(item.benoetigt_am) }}</div><div><span class="block text-xs text-gray-500">Endsumme</span><strong>{{ euro(item.endsumme) }}</strong></div><div class="self-end text-right"><Link v-if="canOpenRequest" :href="route('materialanforderung.show', item.id)" class="inline-block rounded-lg bg-gray-900 px-3 py-2 font-semibold text-white">Öffnen</Link></div></div>
                    </article>
                    <p v-if="filtered.length === 0" class="p-8 text-center text-sm text-gray-500">Keine Materialanforderungen gefunden.</p>
                </div>
            </section>
        </div>
    </AppLayout>
</template>
