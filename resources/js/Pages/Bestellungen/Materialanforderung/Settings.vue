<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import { ref } from 'vue'
import { Head, Link, useForm } from '@inertiajs/vue3'
const props = defineProps({ current: Object, versions: Array, standorte: Array, users: Array })
const immediately = ref(true)
const localNow = new Date(Date.now() - new Date().getTimezoneOffset() * 60000).toISOString().slice(0, 16)
const form = useForm({ approval_limit: props.current.approval_limit_cents / 100, quote_limit: props.current.quote_limit_cents / 100,
    quote_count: props.current.quote_count, location_ids: [...props.current.location_ids], approver_ids: [...props.current.approver_ids],
    manual_referral: props.current.manual_referral, effective_at: localNow })
const save = () => form.transform(data => ({ ...data,
    effective_at: (immediately.value ? new Date() : new Date(data.effective_at)).toISOString(),
})).post(route('materialanforderung.settings.store'), { preserveScroll: true })
const euro = n => Number(n / 100).toLocaleString('de-DE', { style: 'currency', currency: 'EUR' })
</script>
<template>
    <Head title="Bestellwesen – Freigaberegeln" />
    <AppLayout>
        <template #header>Bestellwesen · Freigaberegeln</template>
        <div class="mx-auto max-w-5xl space-y-5">
            <Link :href="route('materialanforderung.index')" class="text-sm text-blue-700">← Zur Bestellübersicht</Link>
            <form class="space-y-5" @submit.prevent="save">
                <section class="rounded-xl border bg-white p-6">
                    <h1 class="text-xl font-bold">Freigaben konfigurieren</h1>
                    <p class="mt-2 text-sm text-gray-600">Alle Beträge gelten für die gesamte Bestellung brutto inklusive Versand. Die Geschäftsführungsfreigabe ist abschließend; die kaufmännische Leitung wird informiert.</p>
                    <ul v-if="Object.keys(form.errors).length" role="alert" class="mt-3 list-inside list-disc text-sm text-red-700"><li v-for="(error, key) in form.errors" :key="key">{{ error }}</li></ul>
                    <div class="mt-5 grid gap-4 md:grid-cols-2">
                        <label class="text-sm font-medium">Geschäftsführung erforderlich über (€)<input v-model.number="form.approval_limit" type="number" min="0" step="0.01" required class="mt-1 block w-full rounded-lg border-gray-300" /></label>
                        <label class="text-sm font-medium">Vergleichsangebote erforderlich über (€)<input v-model.number="form.quote_limit" type="number" min="0" step="0.01" required class="mt-1 block w-full rounded-lg border-gray-300" /></label>
                        <label class="text-sm font-medium">Anzahl unterschiedlicher Anbieter<input v-model.number="form.quote_count" type="number" min="1" max="10" required class="mt-1 block w-full rounded-lg border-gray-300" /></label>
                        <div class="text-sm font-medium"><label class="flex items-center gap-2"><input v-model="immediately" type="checkbox" class="rounded" />Ab sofort gültig</label><label v-if="!immediately" class="mt-2 block">Gültig ab (Ortszeit)<input v-model="form.effective_at" type="datetime-local" required class="mt-1 block w-full rounded-lg border-gray-300" /></label></div>
                    </div>
                    <label class="mt-5 flex gap-2 text-sm"><input v-model="form.manual_referral" type="checkbox" class="rounded" />Kaufmännische Leitung darf an die Geschäftsführung weiterleiten</label>
                </section>
                <section class="grid gap-5 md:grid-cols-2">
                    <fieldset class="rounded-xl border bg-white p-6"><legend class="font-semibold">Standorte mit GF-Pflicht</legend><p class="mb-3 text-sm text-gray-600">Direkt nach der sachlichen Genehmigung, unabhängig vom Betrag.</p><label v-for="site in standorte" :key="site.id" class="mb-2 flex gap-2 text-sm"><input v-model="form.location_ids" type="checkbox" :value="site.id" class="rounded" />{{ site.name }}</label><p v-if="!standorte.length" class="text-sm">Noch keine Standorte vorhanden.</p></fieldset>
                    <fieldset class="rounded-xl border bg-white p-6"><legend class="font-semibold">Geschäftsführung und Vertretung</legend><p class="mb-3 text-sm text-gray-600">Ausgewählte Personen dürfen die GF-Freigabe erteilen und erhalten die betreffenden Vorgänge.</p><div class="max-h-72 overflow-auto"><label v-for="user in users" :key="user.id" class="mb-2 flex gap-2 text-sm"><input v-model="form.approver_ids" type="checkbox" :value="user.id" class="rounded" />{{ user.name }}</label></div><p v-if="!form.approver_ids.length" class="mt-3 text-sm text-amber-800">Noch niemand ausgewählt. Alternativ gilt das Rollenrecht „GF-Freigabe“.</p></fieldset>
                </section>
                <div class="flex items-center justify-between gap-4"><p class="text-sm text-gray-600">Betrags- und Standortregeln bereits eingereichter Vorgänge bleiben erhalten. Zuständigkeiten folgen der aktuell gültigen Konfiguration.</p><button :disabled="form.processing" class="shrink-0 rounded-lg bg-orange-600 px-5 py-3 font-semibold text-white disabled:opacity-50">Regeln speichern</button></div>
            </form>
            <section class="rounded-xl border bg-white p-6"><h2 class="font-semibold">Regelverlauf</h2><div class="mt-3 overflow-auto"><table class="w-full text-left text-sm"><thead><tr><th class="py-2">Gültig ab</th><th>GF-Grenze</th><th>Angebotsgrenze</th><th>Anzahl</th></tr></thead><tbody><tr v-for="version in versions" :key="version.id" class="border-t"><td class="py-2">{{ new Date(version.effective_at).toLocaleString('de-DE') }}</td><td>{{ euro(version.approval_limit_cents) }}</td><td>{{ euro(version.quote_limit_cents) }}</td><td>{{ version.quote_count }}</td></tr></tbody></table></div></section>
        </div>
    </AppLayout>
</template>
