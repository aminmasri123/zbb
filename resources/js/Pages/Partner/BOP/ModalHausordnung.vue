<script setup>
import { ref, defineProps, defineEmits, computed } from 'vue';
import Swal from 'sweetalert2';
import Modal from '@/Components/ModalForm.vue';

const props = defineProps({
    visible: Boolean,
    partnerId: Number,
    jahr: [String, Number],
    teil: String
});

const emits = defineEmits(['close']);
const loading = ref(false);

// Form-Refs
const datum = ref('');
const sortBy = ref('');

// Validierung
const isValid = computed(() => datum.value && sortBy.value);

// Export Funktion
const save = () => {
    if (!isValid.value) {
        Swal.fire('Fehler', 'Bitte Datum und Sortierung auswählen!', 'error');
        return;
    }

    loading.value = true;

    const url = route('hausordnung.export.schule.pdf', {
        partnerId: props.partnerId,
        schuljahr: props.jahr,
        teil: props.teil,
        termin: datum.value,
        sortBy: sortBy.value
    });

    window.open(url, '_blank');

    closeModal();
};

const closeModal = () => {
    datum.value = '';
    sortBy.value = '';
    loading.value = false;
    emits('close');
};
</script>

<template>
    <Modal v-if="visible" @close="closeModal">
        <template #header>
            Hausordnung exportieren
        </template>

        <template #body>
            <!-- Datum -->
            <label class="block mb-2">Datum</label>
            <input type="date" v-model="datum" class="w-full border p-2 mb-4" />

            <!-- Sortierung -->
            <label class="block mb-2">Sortierung</label>
            <select v-model="sortBy" class="w-full border p-2 mb-4">
                <option disabled value="">Bitte wählen</option>
                <option value="klasse">Nach Klasse</option>
                <option value="nachname">Nach Nachname</option>
            </select>
        </template>

        <template #footer>
            <button
                @click="save"
                :disabled="loading || !isValid"
                class="bg-zbb text-white px-4 py-2 rounded disabled:opacity-50"
            >
                {{ loading ? 'Speichern...' : 'Speichern' }}
            </button>
            <button @click="closeModal" class="border px-4 py-2 rounded">
                Abbrechen
            </button>
        </template>
    </Modal>
</template>
