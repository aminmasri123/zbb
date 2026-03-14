<script setup>
import { useForm } from '@inertiajs/vue3' // Wir brauchen keinen extra router import, wenn wir form.post nutzen
import InputText from 'primevue/inputtext'
import MultiSelect from 'primevue/multiselect'
import Modal from '@/Components/ModalForm.vue'
import { defineProps, defineEmits } from 'vue'

const props = defineProps({
    ausgabe: Object,
    visible: Boolean,
    nichtAusgegebeneGeraete: Array
})

// WICHTIG: Emits registrieren
const emit = defineEmits(['close', 'added'])

const form = useForm({
    ausgabeschein_nr: props.ausgabe.ausgabescheinNr,
    ausleiher: props.ausgabe.ausleiher.vorname + ' ' + props.ausgabe.ausleiher.nachname,
    projekt: props.ausgabe.projekte.name,
    sn: [],
    ausleihdatum: props.ausgabe.ausgabe
})

const saveAusgabe = () => {
    // Einfache Client-Check vorab
    if (form.sn.length === 0) {
        form.setError('sn', 'Mindestens ein Gerät auswählen');
        return;
    }

    // Nutze form.post statt router.post
    form.post(route('geraet.ausgabe.store.add'), {
        preserveScroll: true,
        onSuccess: () => {
            emit('added', { ...form.data() });
            emit('close');
        },
        onError: (err) => {
            console.log("Server Fehler:", err);
        }
    });
}
</script>

<template>
    <Modal v-if="visible" @close="emit('close')">
        <template #header>Ausgabe hinzufügen</template>
        
        <template #body>
            <form @submit.prevent="saveAusgabe"> <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium">Ausgabeschein Nr *</label>
                        <InputText v-model="form.ausgabeschein_nr" disabled class="w-full" />
                    </div>

                    <div>
                        <label class="block text-sm font-medium">Ausleiher *</label>
                        <InputText v-model="form.ausleiher" disabled class="w-full" />
                    </div>

                    <div>
                        <label class="block text-sm font-medium">Projekt *</label>
                        <InputText v-model="form.projekt" disabled class="w-full" />
                    </div>

                    <div>
                        <label class="block text-sm font-medium">SN *</label>
                        <MultiSelect 
                            v-model="form.sn" 
                            :options="nichtAusgegebeneGeraete" 
                            optionLabel="productID"
                            optionValue="sn" 
                            placeholder="SN wählen" 
                            display="chip" 
                            filter 
                            class="w-full" 
                        />
                        <small v-if="form.errors.sn" class="text-red-500">{{ form.errors.sn }}</small>
                    </div>

                    <div>
                        <label class="block text-sm font-medium">Ausleihdatum *</label>
                        <InputText v-model="form.ausleihdatum" readonly class="w-full" />
                    </div>
                </div>
            </form>
        </template>

        <template #footer>
            <button 
                @click="saveAusgabe" 
                :disabled="form.processing"
                class="bg-zbb text-white px-4 py-2 rounded disabled:opacity-50"
            >
                {{ form.processing ? 'Speichert...' : 'Speichern' }}
            </button>
            <button @click="emit('close')" class="border px-4 py-2 rounded ml-2">
                Abbrechen
            </button>
        </template>
    </Modal>
</template>