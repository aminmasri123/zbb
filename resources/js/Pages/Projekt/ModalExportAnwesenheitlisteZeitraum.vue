<script setup>
import Modal from '@/Components/ModalForm.vue';
import { ref } from 'vue';
import DatePicker from 'primevue/datepicker';
import FloatLabel from 'primevue/floatlabel';
import Swal from 'sweetalert2';

const props = defineProps({
    visible: Boolean,
    projekt: Object
});
const emit = defineEmits(['close']);

let monat = ref(null); // enthält z. B. 2025-11
let jahr = ref(new Date()); // setzt heute → aktuelles Jahr wird angezeigt

const exportListe = () => {
    if (!monat.value || !jahr.value) {
        Swal.fire("Fehler", "Bitte Monat und Jahr wählen!", "error");
        return;
    }

    // Monat & Jahr aus DatePicker korrekt auslesen
    const month = monat.value.getMonth() + 1; // 1–12
    const year  = jahr.value.getFullYear();

    const start = `${year}-${String(month).padStart(2, '0')}-01`;

    const endDate = new Date(year, month, 0); // letzter Tag im Monat
    const ende = endDate.toISOString().slice(0, 10);

    const url =
        route('export.projekt.anwesenheit.periode', props.projekt.id)
        + '?monat=' + month
        + '&jahr=' + year;

    window.location.href = url;
};

</script>

<template>
    <Modal v-if="visible" @close="emit('close')">
        <template #header>Anwesenheitsliste exportieren</template>

        <template #body>
            <div class="grid grid-cols-2 gap-4">

                <FloatLabel variant="on">
                    <DatePicker
                        v-model="monat"
                        view="month"
                        dateFormat="mm"
                        class="w-full"
                    />
                    <label>Monat</label>
                </FloatLabel>

                <FloatLabel variant="on">
                    <DatePicker
                        v-model="jahr"
                        view="year"
                        dateFormat="yy"
                        class="w-full"
                    />
                    <label>Jahr</label>
                </FloatLabel>

            </div>
        </template>

        <template #footer>
            <button @click="exportListe" class="bg-zbb text-white px-4 py-2 rounded">
                Exportieren
            </button>
            <button @click="emit('close')" class="border px-4 py-2 rounded">
                Abbrechen
            </button>
        </template>
    </Modal>
</template>
