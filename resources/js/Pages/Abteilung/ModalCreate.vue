<script setup>
    import AppLayout from '@/Layouts/AppLayout.vue';
    import { ref, defineProps  } from 'vue';
    import Swal from 'sweetalert2';
    import axios from 'axios';
    import Modal from '@/Components/ModalForm.vue';
    import MultiSelect from 'primevue/multiselect';
    import InputText from 'primevue/inputtext';
    import FloatLabel from 'primevue/floatlabel';
    import Select from 'primevue/select';

        // Props (Daten von außen übergeben)
        const props = defineProps({
        visible: { type: Boolean, default: false },
        users: { type: Object, },

        })

        // Events an die Eltern-Komponente
        const emit = defineEmits(["close", "add-abteilung"]);

    // Lokale Kopie der Abteilungen erstellen
    let localAbteilungen = ref([]); // Initialisiere mit einem leeren Array

    const resetForm = () => {
    newAbteilung.value = {
        name: '',
        abteilungsleiter:'',
        assistenten: [],
        color: '',
    };
};

    const close = () => {
    emit("close");
    form.value = { name: "", abteilungsleiter: "", assistenten: "" }; // reset beim Schließen
    };

    // Benutzer hinzufügen
    const addAbteilung = async () => {

    // Überprüfe, ob alle erforderlichen Felder ausgefüllt sind
    if (!newAbteilung.value.name || !newAbteilung.value.abteilungsleiter || !newAbteilung.value.assistenten) {
        Swal.fire({
            title: 'Error!',
            text: 'Bitte achten Sie darauf, alle erforderlichen Felder auszufüllen.',
            icon: 'error',
            timer: 3000,
            timerProgressBar: true,
        });
        return;
    }

    try {
        // Sende die POST-Anfrage an den Server
        const response = await axios.post(route('abteilung.store'), newAbteilung.value);

        // Logge die Antwort des Servers
        console.log(response.data);

        // Zeige eine Erfolgsnachricht an
        Swal.fire({
            title: 'Erfolg!',
            text: 'Abteilung erfolgreich angelegt!',
            icon: 'success',
            timer: 3000,
            timerProgressBar: true,
        });
        localAbteilungen.value.unshift(response.data.abteilung);

        // Optional: Formular zurücksetzen und Modal schließen
        resetForm();
        closeModal();
    } catch (error) {
        // Fehlerbehandlung hier
        console.error(error);
        Swal.fire({
            title: 'Error!',
            text: error.response.data.message || 'Beim Erstellen der Abteilung ist ein Fehler aufgetreten. Bitte versuchen Sie es erneut.',
            icon: 'error',
            timer: 3000,
            timerProgressBar: true,
        });
    }
};

// Neuen Benutzer
let newAbteilung = ref({
    name: '',
    abteilungsleiter:'',
    assistenten: [],
});

</script>


<template>
    <!-- Modal für neue Abteilung -->
    <Modal v-if="visible" @close="close">
        <template #header>
            <div class="text-center w-full uppercase text-lg font-bold">
                <h2 class="text-lg font-bold text-gray-500 ">{{ $t('abteilung anlegen') }}</h2>
            </div>
        </template>
        <template #body>
            <form @submit.prevent="addAbteilung">
                <div class="mb-4 w-full mx-1">
                    <FloatLabel variant="on">
                        <InputText id="name" v-model="newAbteilung.name" class="w-full" />
                        <label for="name">Bezeichnung</label>
                    </FloatLabel>
                </div>


                <div class="mb-4 w-full mx-1">
                    <FloatLabel variant="on">
                        <Select v-model="newAbteilung.abteilungsleiter"  inputId="id" optionValue="id"  :options="users" optionLabel="full_name" class="w-full" />

                        <label for="abteilungsleiter">Abteilungsleitung wählen</label>
                    </FloatLabel>
                </div>
                <div class="mb-4 w-full mx-1">
                    <MultiSelect
                        v-model="newAbteilung.assistenten"
                        inputId="id"
                        display="chip"
                        optionLabel="full_name"
                        :options="users"
                        optionValue="id"
                        filter
                        placeholder="Assistenten wählen*"
                        :maxSelectedLabels="3"
                        class="w-full">
                    </MultiSelect>
                </div>
            </form>
        </template>
        <template #footer>
            <div class="w-full flex justify-center">
                <button @click="addAbteilung" class=" mx-2 bg-zbb text-white px-4 py-2 rounded">Hinzufügen</button>
                <button @click="close" class="mx-2 border border-zbb text-zbb px-4 py-2 rounded">Abbrechen</button>
            </div>
        </template>
    </Modal>
</template>
