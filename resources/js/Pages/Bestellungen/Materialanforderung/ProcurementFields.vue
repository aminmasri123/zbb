<script setup>
import PriceInputMode from './PriceInputMode.vue'
defineProps({ form: Object, standorte: { type: Array, default: () => [] } })
</script>
<template>
    <section class="rounded-xl border border-gray-200 bg-white p-5">
        <h2 class="text-lg font-semibold">Standort und Bestelldaten</h2>
        <p class="mt-1 text-sm text-gray-600">Die Bestellnummer wird automatisch vergeben. Die Freigabegrenze gilt brutto inklusive Versand bzw. Nebenkosten.</p>
        <PriceInputMode :form="form" class="mt-4" />
        <div class="mt-4 grid gap-4 md:grid-cols-2">
            <label class="text-sm font-medium">Zuständiger Standort
                <select v-model="form.standort_id" class="mt-1 block w-full rounded-lg border-gray-300"><option value="">Bitte auswählen</option><option v-for="site in standorte" :key="site.id" :value="site.id">{{ site.name }}</option></select>
                <span class="text-red-700">{{ form.errors.standort_id }}</span>
            </label>
            <label class="text-sm font-medium">Lieferantenreferenz (optional)<input v-model="form.lieferantenreferenz" maxlength="100" class="mt-1 block w-full rounded-lg border-gray-300" /></label>
            <label class="text-sm font-medium md:col-span-2">Anschrift des Lieferanten / Dienstleisters<textarea v-model="form.lieferant_adresse" rows="3" maxlength="1000" class="mt-1 block w-full rounded-lg border-gray-300" placeholder="Straße und Hausnummer, PLZ und Ort" /><span class="mt-1 block text-xs font-normal text-gray-500">Empfängeranschrift für den Bestellschein. Im Entwurf optional, vor dem Bestellen erforderlich.</span></label>
            <label class="text-sm font-medium">{{ form.vergabe.lieferung_art === 'Dienstleistung' ? 'Nebenkosten' : 'Versand' }} {{ form.preisart }} (€){{ form.vergabe.lieferung_art === 'Dienstleistung' ? ', z. B. Anfahrt' : '' }}<input v-model.number="form.versand_netto" type="number" min="0" step="0.01" class="mt-1 block w-full rounded-lg border-gray-300" /></label>
            <label class="text-sm font-medium">{{ form.vergabe.lieferung_art === 'Dienstleistung' ? 'MwSt. auf Nebenkosten (%)' : 'MwSt. auf Versand (%)' }}<input v-model.number="form.versand_mwst" type="number" min="0" max="100" step="0.01" class="mt-1 block w-full rounded-lg border-gray-300" /></label>
        </div>
    </section>
</template>
