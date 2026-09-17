<script setup>
defineProps({ form: Object, name: { type: String, default: 'price-mode' } })
</script>
<template>
    <fieldset class="rounded-lg border border-blue-200 bg-blue-50 p-4">
        <legend class="px-1 text-sm font-semibold text-gray-900">Wie sind deine Preise angegeben?</legend>
        <div class="flex flex-wrap gap-x-6 gap-y-3 text-sm">
            <label class="flex items-center gap-2"><input v-model="form.preisart" :name="name" value="brutto" type="radio" class="text-blue-700" />Brutto – inkl. MwSt.</label>
            <label class="flex items-center gap-2"><input v-model="form.preisart" :name="name" value="netto" type="radio" class="text-blue-700" />Netto – zzgl. MwSt.</label>
        </div>
        <p class="mt-2 text-sm text-gray-700">{{ form.preisart === 'brutto' ? 'Die MwSt. ist im eingegebenen Preis enthalten und wird nicht nochmals aufgeschlagen.' : 'Die MwSt. wird zum eingegebenen Preis hinzugerechnet.' }}</p>
        <p class="mt-1 text-xs text-gray-600">Gilt für alle Einzelpreise sowie Versand und Nebenkosten. Beim Wechsel bleiben die eingegebenen Zahlen gleich.</p>
        <p v-if="form.errors?.preisart" role="alert" class="mt-1 text-sm text-red-700">{{ form.errors?.preisart }}</p>
    </fieldset>
</template>
