<script setup>
import { router } from '@inertiajs/vue3';
defineProps({ report: Object });
const selectReport = type => router.get(route('partner.index'), { bericht_typ: type }, { preserveScroll: true, preserveState: true });
</script>

<template>
    <section v-if="report" class="my-6 rounded-lg border border-[var(--border)] bg-[var(--card)] p-5">
        <h2 class="mb-4 text-lg font-semibold">Anzahl der Teilnehmenden pro Bereich</h2>
        <div class="mb-5 flex gap-2">
            <button v-for="option in [{ type: 'zwischen', label: 'Zwischenbericht' }, { type: 'ende', label: 'Endbericht' }]" :key="option.type" type="button" class="rounded px-4 py-2 text-white" :class="report.type === option.type ? 'bg-zbb' : 'bg-gray-500'" @click="selectReport(option.type)">{{ option.label }}</button>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full border-collapse text-sm">
                <thead>
                    <tr><th rowspan="2" class="border p-3">ID</th><th class="border p-3">Bereiche</th><th v-for="area in report.areas" :key="area.id" :colspan="Math.max(1, report.years.length)" class="border p-3" :title="area.name">{{ area.code || area.name }}</th></tr>
                    <tr><th class="border p-3 text-left">Schule / Antragsrunde</th><template v-for="area in report.areas" :key="area.id"><th v-for="year in report.years" :key="year" class="border p-3">{{ year - 1 }}</th><th v-if="!report.years.length" class="border p-3">–</th></template></tr>
                </thead>
                <tbody><tr v-for="school in report.schools" :key="school.id"><td class="border p-3">{{ school.id }}</td><td class="min-w-40 border p-3">{{ school.name }}</td><template v-for="area in report.areas" :key="area.id"><td v-for="year in report.years" :key="year" class="border p-3 text-center">{{ school.counts[area.id]?.[year] || 0 }}</td></template></tr></tbody>
            </table>
            <p v-if="!report.schools.length" class="p-4 text-sm">Keine Teilnahmen im gewählten Berichtszeitraum.</p>
        </div>
    </section>
</template>
