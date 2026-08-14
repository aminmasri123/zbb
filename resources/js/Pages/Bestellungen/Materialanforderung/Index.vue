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
const projectFilter = ref('alle')
const yearFilter = ref('alle')
const costCenterFilter = ref('alle')
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

const projectSummaries = computed(() => {
    const summaries = new Map()

    props.anforderungen.forEach((item) => {
        const id = item.projekt?.id ?? item.projekt_id
        if (!id) return

        if (!summaries.has(id)) {
            summaries.set(id, {
                id,
                name: item.projekt?.name || `Projekt ${id}`,
                count: 0,
            })
        }

        const summary = summaries.get(id)
        summary.count += 1
    })

    return [...summaries.values()].sort((left, right) => left.name.localeCompare(right.name, 'de'))
})

const yearOptions = computed(() => [...new Set(
    props.anforderungen
        .map((item) => yearOf(item.created_at))
        .filter(Boolean)
)].sort((left, right) => right - left))

const costCenterOptions = computed(() => [...new Set(
    props.anforderungen
        .map((item) => String(item.kostenstelle || '').trim())
        .filter(Boolean)
)].sort((left, right) => left.localeCompare(right, 'de', { numeric: true })))

const contextRequests = computed(() => {
    return props.anforderungen.filter((item) => {
        const matchesProject = projectFilter.value === 'alle'
            || String(item.projekt?.id ?? item.projekt_id) === projectFilter.value
        const matchesYear = yearFilter.value === 'alle'
            || String(yearOf(item.created_at)) === yearFilter.value
        const matchesCostCenter = costCenterFilter.value === 'alle'
            || String(item.kostenstelle || '').trim() === costCenterFilter.value

        return matchesProject && matchesYear && matchesCostCenter
    })
})

const filtered = computed(() => statusFilter.value === 'alle'
    ? contextRequests.value
    : contextRequests.value.filter((item) => item.status === statusFilter.value))
const selectedProject = computed(() => projectSummaries.value.find((project) => String(project.id) === projectFilter.value))
const activeFilterCount = computed(() => [projectFilter.value, yearFilter.value, costCenterFilter.value]
    .filter((value) => value !== 'alle').length)
const contextLabel = computed(() => {
    const labels = []
    if (selectedProject.value) labels.push(selectedProject.value.name)
    if (yearFilter.value !== 'alle') labels.push(`Jahr ${yearFilter.value}`)
    if (costCenterFilter.value !== 'alle') labels.push(`Kostenstelle ${costCenterFilter.value}`)
    return labels.join(' · ') || 'Alle Anforderungen'
})
const openCount = computed(() => contextRequests.value.filter((item) => !['geliefert', 'storniert'].includes(item.status)).length)
const approvalCount = computed(() => contextRequests.value.filter((item) => ['eingereicht', 'sachlich_genehmigt'].includes(item.status)).length)
const total = computed(() => contextRequests.value.reduce((sum, item) => sum + Number(item.endsumme || 0), 0))

function resetContextFilters() {
    projectFilter.value = 'alle'
    yearFilter.value = 'alle'
    costCenterFilter.value = 'alle'
}

function runSearch() {
    router.get(route('materialanforderung.index'), { search: search.value }, { preserveState: true, replace: true })
}

function euro(value) {
    return Number(value || 0).toLocaleString('de-DE', { style: 'currency', currency: 'EUR' })
}

function date(value) {
    return value ? new Intl.DateTimeFormat('de-DE').format(new Date(value)) : '–'
}

function yearOf(value) {
    if (!value) return null
    const parsed = new Date(value)
    return Number.isNaN(parsed.getTime()) ? null : parsed.getFullYear()
}
</script>

<template>
    <Head title="Materialanforderungen" />
    <AppLayout>
        <template #header>Materialanforderungen</template>

        <div class="space-y-5">
            <div v-if="!hasActiveProject" class="rounded border border-blue-200 bg-blue-50 px-4 py-3 text-sm text-blue-800">
                Sie arbeiten ohne Projektzuweisung. Die Übersicht und Ihre Verwaltungsfunktionen stehen trotzdem zur Verfügung. Eine neue Materialanforderung kann nur innerhalb eines Projekts angelegt werden.
            </div>

            <section class="overflow-hidden rounded border border-gray-200 bg-white shadow-sm">
                <div class="flex flex-col gap-3 border-b border-gray-200 px-4 py-4 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <h2 class="flex items-center gap-2 text-lg font-semibold text-gray-900"><i class="las la-filter text-orange-500"></i>Filter und Auswertung</h2>
                        <p class="mt-1 text-sm text-gray-600">Materialanforderungen nach Projekt, Erstellungsjahr und Kostenstelle eingrenzen.</p>
                    </div>
                    <button v-if="activeFilterCount" type="button" class="inline-flex items-center gap-1.5 self-start rounded border border-gray-300 bg-white px-3 py-2 text-sm font-medium text-gray-700 hover:border-orange-300 hover:text-orange-700" @click="resetContextFilters">
                        <i class="las la-undo"></i> Auswahl zurücksetzen
                    </button>
                </div>

                <div class="grid grid-cols-1 gap-4 p-4 md:grid-cols-3">
                    <label class="block">
                        <span class="mb-1.5 block text-sm font-medium text-gray-700">Projekt</span>
                        <select v-model="projectFilter" class="w-full rounded border-gray-300 text-sm focus:border-orange-500 focus:ring-orange-500">
                            <option value="alle">Alle Projekte ({{ anforderungen.length }})</option>
                            <option v-for="project in projectSummaries" :key="project.id" :value="String(project.id)">{{ project.name }} ({{ project.count }})</option>
                        </select>
                    </label>

                    <label class="block">
                        <span class="mb-1.5 block text-sm font-medium text-gray-700">Erstellungsjahr</span>
                        <select v-model="yearFilter" class="w-full rounded border-gray-300 text-sm focus:border-orange-500 focus:ring-orange-500">
                            <option value="alle">Alle Jahre</option>
                            <option v-for="year in yearOptions" :key="year" :value="String(year)">{{ year }}</option>
                        </select>
                    </label>

                    <label class="block">
                        <span class="mb-1.5 block text-sm font-medium text-gray-700">Kostenstelle</span>
                        <select v-model="costCenterFilter" class="w-full rounded border-gray-300 text-sm focus:border-orange-500 focus:ring-orange-500">
                            <option value="alle">Alle Kostenstellen</option>
                            <option v-for="costCenter in costCenterOptions" :key="costCenter" :value="costCenter">{{ costCenter }}</option>
                        </select>
                    </label>
                </div>

                <div class="flex flex-wrap items-center justify-between gap-2 border-t border-gray-200 bg-gray-50 px-4 py-3 text-sm">
                    <span class="min-w-0 truncate text-gray-600"><i class="las la-list-alt mr-1 text-orange-500"></i>{{ contextLabel }}</span>
                    <span class="shrink-0 font-semibold text-gray-900">{{ contextRequests.length }} Anforderungen</span>
                </div>
            </section>

            <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                <div class="rounded border border-gray-200 bg-white p-4 shadow-sm">
                    <p class="text-sm text-gray-500">Offene Vorgänge</p>
                    <p class="mt-1 text-2xl font-bold text-gray-900">{{ openCount }}</p>
                    <p class="mt-1 truncate text-xs text-gray-400">{{ contextLabel }}</p>
                </div>
                <div class="rounded border border-gray-200 bg-white p-4 shadow-sm">
                    <p class="text-sm text-gray-500">In Freigabe</p>
                    <p class="mt-1 text-2xl font-bold text-violet-700">{{ approvalCount }}</p>
                    <p class="mt-1 truncate text-xs text-gray-400">{{ contextLabel }}</p>
                </div>
                <div class="rounded border border-gray-200 bg-white p-4 shadow-sm">
                    <p class="text-sm text-gray-500">Bestellvolumen</p>
                    <p class="mt-1 text-2xl font-bold text-orange-600">{{ euro(total) }}</p>
                    <p class="mt-1 truncate text-xs text-gray-400">{{ contextLabel }}</p>
                </div>
            </div>

            <section class="overflow-hidden rounded border border-gray-200 bg-white shadow-sm">
                <div class="flex flex-col gap-3 border-b border-gray-200 p-4 lg:flex-row lg:items-center lg:justify-between">
                    <form class="flex min-w-0 flex-1 gap-2" @submit.prevent="runSearch">
                        <div class="relative min-w-0 flex-1">
                            <i class="las la-search absolute left-3 top-3 text-gray-400"></i>
                            <input v-model="search" type="search" class="w-full rounded-lg border-gray-300 pl-10 text-sm focus:border-orange-500 focus:ring-orange-500" placeholder="Nummer, Projekt, Bemerkung oder Artikel suchen" />
                        </div>
                        <button type="submit" class="rounded-lg border border-gray-300 px-4 py-2 text-sm font-semibold transition hover:bg-gray-50">Suchen</button>
                    </form>
                    <div class="flex gap-2">
                        <select v-model="statusFilter" class="min-w-0 flex-1 rounded-lg border-gray-300 text-sm focus:border-orange-500 focus:ring-orange-500 lg:w-52">
                            <option value="alle">Alle Status</option>
                            <option v-for="(meta, status) in statusMeta" :key="status" :value="status">{{ meta[0] }}</option>
                        </select>
                        <Link v-if="canCreateRequest" :href="route('materialanforderung.create')" class="inline-flex shrink-0 items-center gap-2 rounded-lg bg-orange-600 px-4 py-2 text-sm font-semibold text-white transition hover:bg-orange-700">
                            <i class="las la-plus"></i><span class="hidden sm:inline">Neue Anforderung</span>
                        </Link>
                    </div>
                </div>

                <div class="flex flex-wrap items-center justify-between gap-2 bg-gray-50/80 px-4 py-2.5 text-xs text-gray-500">
                    <span><strong class="text-gray-700">{{ filtered.length }}</strong> von {{ contextRequests.length }} Vorgängen angezeigt</span>
                    <button v-if="statusFilter !== 'alle'" type="button" class="font-semibold text-orange-700 hover:text-orange-800" @click="statusFilter = 'alle'">Statusfilter aufheben</button>
                </div>

                <div class="hidden overflow-x-auto md:block">
                    <table class="min-w-full text-sm">
                        <thead class="bg-gray-50 text-left text-xs uppercase tracking-wide text-gray-500">
                            <tr><th class="px-4 py-3">Nr.</th><th class="px-4 py-3">Projekt</th><th class="px-4 py-3">Erstellt</th><th class="px-4 py-3">Antragsteller</th><th class="px-4 py-3">Status</th><th class="px-4 py-3">Benötigt am</th><th class="px-4 py-3">Kostenstelle</th><th class="px-4 py-3 text-right">Endsumme</th><th class="px-4 py-3"></th></tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            <tr v-for="item in filtered" :key="item.id" class="transition hover:bg-orange-50/40">
                                <td class="px-4 py-3 font-semibold">#{{ item.id }}</td>
                                <td class="px-4 py-3"><span class="inline-flex max-w-[220px] items-center gap-1.5 rounded-full bg-slate-100 px-2.5 py-1 text-xs font-semibold text-slate-700"><i class="las la-folder text-orange-500"></i><span class="truncate">{{ item.projekt?.name || 'Ohne Projekt' }}</span></span></td>
                                <td class="px-4 py-3">{{ date(item.created_at) }}</td>
                                <td class="px-4 py-3">{{ item.besteller?.name || '–' }}</td>
                                <td class="px-4 py-3"><div class="flex flex-wrap items-center gap-1.5"><span class="rounded-full px-2.5 py-1 text-xs font-semibold" :class="statusMeta[item.status]?.[1]">{{ statusMeta[item.status]?.[0] || item.status }}</span><span v-if="item.von_mir_bearbeitet" class="rounded-full bg-slate-100 px-2 py-1 text-xs font-medium text-slate-700">Von mir bearbeitet</span></div></td>
                                <td class="px-4 py-3"><span v-if="item.prioritaet === 'dringend'" class="mr-1 text-red-600" title="Dringend">●</span>{{ date(item.benoetigt_am) }}</td>
                                <td class="px-4 py-3">{{ item.kostenstelle }}</td>
                                <td class="px-4 py-3 text-right font-semibold">{{ euro(item.endsumme) }}</td>
                                <td class="px-4 py-3 text-right"><Link v-if="canOpenRequest" :href="route('materialanforderung.show', item.id)" class="rounded-lg border border-gray-300 px-3 py-1.5 font-semibold transition hover:border-orange-300 hover:bg-orange-50 hover:text-orange-700">Öffnen</Link></td>
                            </tr>
                            <tr v-if="filtered.length === 0"><td colspan="9" class="px-4 py-12 text-center"><i class="las la-inbox mb-2 block text-3xl text-gray-300"></i><span class="text-gray-500">Für diese Auswahl wurden keine Materialanforderungen gefunden.</span></td></tr>
                        </tbody>
                    </table>
                </div>

                <div class="divide-y divide-gray-100 md:hidden">
                    <article v-for="item in filtered" :key="item.id" class="p-4">
                        <div class="mb-3 flex items-center gap-1.5 text-xs font-semibold text-slate-600"><i class="las la-folder text-orange-500"></i>{{ item.projekt?.name || 'Ohne Projekt' }}</div>
                        <div class="flex items-start justify-between gap-3">
                            <div><p class="font-bold text-gray-900">Materialanforderung #{{ item.id }}</p><p class="text-sm text-gray-500">{{ item.besteller?.name || '–' }} · {{ date(item.created_at) }}</p><span v-if="item.von_mir_bearbeitet" class="mt-1 inline-block rounded-full bg-slate-100 px-2 py-1 text-xs font-medium text-slate-700">Von mir bearbeitet</span></div>
                            <span class="shrink-0 rounded-full px-2 py-1 text-xs font-semibold" :class="statusMeta[item.status]?.[1]">{{ statusMeta[item.status]?.[0] || item.status }}</span>
                        </div>
                        <div class="mt-3 grid grid-cols-2 gap-3 text-sm"><div><span class="block text-xs text-gray-500">Kostenstelle</span>{{ item.kostenstelle }}</div><div><span class="block text-xs text-gray-500">Benötigt am</span>{{ date(item.benoetigt_am) }}</div><div><span class="block text-xs text-gray-500">Endsumme</span><strong>{{ euro(item.endsumme) }}</strong></div><div class="self-end text-right"><Link v-if="canOpenRequest" :href="route('materialanforderung.show', item.id)" class="inline-block rounded-lg bg-gray-900 px-3 py-2 font-semibold text-white">Öffnen</Link></div></div>
                    </article>
                    <p v-if="filtered.length === 0" class="p-10 text-center text-sm text-gray-500">Für diese Auswahl wurden keine Materialanforderungen gefunden.</p>
                </div>
            </section>
        </div>
    </AppLayout>
</template>
