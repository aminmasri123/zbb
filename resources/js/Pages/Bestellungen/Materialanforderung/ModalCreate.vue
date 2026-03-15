<script setup>
import { ref, computed } from 'vue'
import { router } from '@inertiajs/vue3'
import Dialog from 'primevue/dialog'
import InputText from 'primevue/inputtext'
import Dropdown from 'primevue/dropdown'
import Button from 'primevue/button'
import Modal from '@/Components/Modal.vue'
defineProps({
  visible: Boolean,
  user: Object,
  projekt: Object
})
defineEmits(['update:visible'])

const form = ref({
    projekt: '',
    kostenstelle: '',
    ersteller_id: '',
    bemerkungen: '',
    positionen: []
})

// Standard Position hinzufügen
const addPosition = () => {
    form.value.positionen.push({
        pos: form.value.positionen.length + 1,
        artikel: '',
        stueck: 1,
        art_nr: '',
        einzelpreis: 0,
        gesamtpreis: 0,
        mwst: 19
    })
}

// Position entfernen
const removePosition = (index) => {
    form.value.positionen.splice(index, 1)
    // Positionsnummern neu setzen
    form.value.positionen.forEach((p, i) => p.pos = i + 1)
}

// Gesamtpreis pro Position automatisch berechnen
const updateGesamtpreis = (position) => {
    position.gesamtpreis = parseFloat((position.stueck * position.einzelpreis).toFixed(2))
}

// Endsumme inkl. MwSt
const endsumme = computed(() => {
    return form.value.positionen.reduce((sum, p) => {
        return sum + (p.gesamtpreis * (1 + p.mwst / 100))
    }, 0).toFixed(2)
})

const submit = () => {
    router.post(route('materialanforderung.store'), form.value, {
        onSuccess: () => emit('update:visible', false)
    })
}

const emitClose = () => emit('update:visible', false)
</script>

<template>
<Modal v-if="visible" @close="emit('close')">

    <template #header>
        Materialanforderung anlegen
    </template>

    <template #body>
        <form>
            <div class="flex flex-col gap-3">
                <label>Projekt</label>
                <InputText v-model="form.projekt" :value="props.projekt.name" disabled/>


                <label>Kostenstelle</label>
                <InputText v-model="form.kostenstelle" placeholder="Kostenstelle eingeben" />

                <label>Ersteller</label>
                <InputText v-model="form.ersteller_id" :value="props.user.vorname + ' ' +  props.user.nachname" disabled/>


                <label>Bemerkungen</label>
                <InputText v-model="form.bemerkungen" placeholder="Optional" />

                <hr class="my-2" />

        <h3 class="font-semibold">Positionen</h3>
        <table class="min-w-full border divide-y divide-gray-200">
            <thead class="bg-gray-200">
                <tr>
                    <th>Pos</th>
                    <th>Artikel</th>
                    <th>Stück</th>
                    <th>Art.Nr</th>
                    <th>Einzelpreis</th>
                    <th>MwSt %</th>
                    <th>Gesamtpreis</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                <tr v-for="(p, index) in form.positionen" :key="index">
                    <td>{{ p.pos }}</td>
                    <td><InputText v-model="p.artikel" /></td>
                    <td><InputText type="number" v-model.number="p.stueck" @input="updateGesamtpreis(p)" /></td>
                    <td><InputText v-model="p.art_nr" /></td>
                    <td><InputText type="number" v-model.number="p.einzelpreis" @input="updateGesamtpreis(p)" /></td>
                    <td><InputText type="number" v-model.number="p.mwst" /></td>
                    <td>{{ p.gesamtpreis.toFixed(2) }}</td>
                    <td>
                        <Button label="-" icon="pi pi-times" class="p-button-danger p-button-sm" @click="removePosition(index)" />
                    </td>
                </tr>
            </tbody>
        </table>

        <Button label="+ Position hinzufügen" class="p-button-sm p-button-secondary mt-2" @click="addPosition" />

        <div class="mt-2 font-semibold text-right">
            Endsumme inkl. MwSt: {{ endsumme }}
        </div>
            </div>
        </form>
    </template>
    <template #footer>
        <button @click="saveAusgabe" class="bg-zbb text-white px-4 py-2 rounded" >Speichern</button>
        <button @click="emit('close')" class="border px-4 py-2 rounded" >Abbrechen</button>
    </template>
</Modal>
</template>