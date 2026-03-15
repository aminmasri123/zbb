<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import { ref, defineProps, computed } from 'vue'
import { router, Head } from '@inertiajs/vue3'
import InputText from 'primevue/inputtext'
import Button from 'primevue/button'
import FloatLabel from 'primevue/floatlabel';

const props = defineProps({
    user: Object,
    projekt: Object,
})

const form = ref({
    projekt: props.projekt.name,
    kostenstelle: '',
    ersteller_id: props.user.id,
    bemerkungen: '',
    positionen: []
})

/*
Position hinzufügen
*/
const addPosition = () => {
    form.value.positionen.push({
        pos: form.value.positionen.length + 1,
        artikel: '',
        stueck: 1,
        art_nr: '',
        einzelpreis: 0,
        mwst: 19,
        gesamtpreis: 0,
        link: '',
    })
}

/*
Position entfernen
*/
const removePosition = (index) => {
    form.value.positionen.splice(index, 1)

    form.value.positionen.forEach((p, i) => {
        p.pos = i + 1
    })
}

/*
Gesamtpreis berechnen
*/
const updateGesamtpreis = (position) => {
    position.gesamtpreis = parseFloat(
        (position.stueck * position.einzelpreis).toFixed(2)
    )
}

/*
Endsumme inkl MwSt
*/
const endsumme = computed(() => {
    return form.value.positionen
        .reduce((sum, p) => {
            return sum + (p.gesamtpreis * (1 + p.mwst / 100))
        }, 0)
        .toFixed(2)
})

/*
Speichern
*/
const submit = () => {
    router.post(route('materialanforderung.store'), form.value)
}
</script>

<template>

    <Head title="Materialanforderungen" />

    <AppLayout>

        <template #header>
            Materialanforderungen anlegen
        </template>

        <div class="flex flex-col gap-3 max-w-8xl bg-white shadow-lg p-8 rounded-lg">
            <div class="grid grid-cols-3 gap-4">
                <FloatLabel variant="on" class="w-full">
                    <InputText :value="props.projekt.name" disabled class="w-full" placeholder="" />
                    <label>Projekt</label>
                </FloatLabel>

                <FloatLabel variant="on" class="w-full">
                    <InputText :value="props.user.vorname + ' ' + props.user.nachname" disabled class="w-full" placeholder="" />
                    <label>Besteller</label>
                </FloatLabel>

                <FloatLabel variant="on" class="w-full">
                    <InputText v-model="form.kostenstelle" class="w-full" placeholder="" />
                    <label>Kostenstelle</label>
                </FloatLabel>

            </div>


            <div class="grid grid-cols-1 mt-2">

                <FloatLabel variant="on" class="w-full">
                    <InputText v-model="form.bemerkungen" class="w-full" placeholder="" />
                    <label>Bemerkungen</label>
                </FloatLabel>

            </div>

            <hr class="my-2" />
            <h3 class="font-semibold">Artikel</h3>
            <table class="min-w-full border divide-y divide-gray-200">
                <thead class="bg-gray-200">
                    <tr>
                        <th>Pos</th>
                        <th>Link</th>
                        <th>Artikel</th>
                        <th>Stück</th>
                        <th>Art.Nr</th>
                        <th>Einzelpreis</th>
                        <th>MwSt %</th>
                        <th>Gesamtpreis</th>
                        <th>*</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="(p, index) in form.positionen" :key="index">
                        <td>{{ p.pos }}</td>
                        <td><InputText v-model="p.link" /></td>
                        <td><InputText v-model="p.artikel" /></td>
                        <td><InputText type="number" v-model.number="p.stueck" @input="updateGesamtpreis(p)" /></td>
                        <td><InputText v-model="p.art_nr" /></td>
                        <td><InputText type="number" v-model.number="p.einzelpreis" @input="updateGesamtpreis(p)" /></td>
                        <td><InputText type="number" v-model.number="p.mwst" /></td>
                        <td>{{ p.gesamtpreis.toFixed(2) }}</td>
                        <td><Button severity="danger" @click="removePosition(index)"><i class="las la-trash"></i></Button></td>
                    </tr>
                </tbody>
            </table>

            <Button class="p-button-sm p-button-secondary mt-3" @click="addPosition"> + Artikel hinzufügen</Button>

            <div class="mt-4 font-semibold text-right text-lg">
                Endsumme inkl. MwSt: {{ endsumme }} €
            </div>

            <div class="flex gap-2 mt-4">
                <button @click="submit" class="bg-zbb text-white px-4 py-2 rounded" >Speichern</button>
                <button  class="border px-4 py-2 rounded" >Abbrechen</button>
            </div>
        </div>
    </AppLayout>
</template>