<template>
    <Modal v-if="visible" @close="emit('close')">
        <template #header>{{ $t('Fahrtart anlegen') }}</template>

        <template #body>
            <div class="grid grid-cols-1 md:grid-cols-1 gap-4">
                <div class="mb-4 w-full">
                    <FloatLabel>
                        <InputText v-model="form.name" class="w-full" required />
                        <label>{{ $t('Bezeichnung') }}</label>
                    </FloatLabel>
                </div>
                <div class="mb-4 w-full">
                    <FloatLabel>
                        <Textarea v-model="form.beschreibung" class="w-full" />
                        <label>{{ $t('Beschreibung') }}</label>
                    </FloatLabel>
                </div>
            </div>
        </template>

        <template #footer>
            <button @click="save" class="bg-zbb text-white px-4 py-2 rounded hover:bg-zbb-dark transition">
                Speichern
            </button>
            <button @click="emit('close')" class="border px-4 py-2 rounded hover:bg-gray-100 transition">
                Abbrechen
            </button>
        </template>
    </Modal>
</template>

<script setup>
import Modal from '@/Components/ModalForm.vue';
import { ref } from 'vue';
import Swal from 'sweetalert2';
import FloatLabel from 'primevue/floatlabel';
import InputText from 'primevue/inputtext';
import axios from 'axios';
import Textarea from 'primevue/textarea';

const props = defineProps({
  visible: Boolean,
});
const emit = defineEmits(['close', 'added']);

let form = ref({
  name: '',
  beschreibung: '',
});

const resetForm = () => {
  form.value = { name: '', beschreibung: '' };
};

const save = async () => {
  try {
    const response = await axios.post(route('fahrtarten.store'), form.value);
console.log('Axios Response:', response.data);

    // 🔍 Sicherstellen, dass du wirklich das Fahrtart-Objekt bekommst
    const newFahrtart = response.data.fahrtart ?? response.data;

    Swal.fire('Erfolg!', 'Fahrtart erfolgreich angelegt!', 'success');

    // ✅ Neuen Eintrag an Parent-Komponente übergeben
    emit('added', newFahrtart);

    // ✅ Formular zurücksetzen und Modal schließen
    resetForm();
    emit('close');
  } catch (error) {
    Swal.fire('Fehler', error.response?.data?.message || 'Speichern fehlgeschlagen', 'error');
  }
};
</script>
