<template>
    <Modal v-show="visible" @close="emit('close')">
        <template #header>{{$t('Rolle anlegen')}}</template>

        <template #body>
            <div class="grid grid-cols-1 md:grid-cols-1 gap-4">
                <div class="mb-4 w-full">
                    <FloatLabel variant="on">
                        <InputText v-model="form.name" class="w-full" required />
                        <label>{{ $t('Rolle') }}</label>
                    </FloatLabel>
                </div>
                <div class="mb-4 w-full">
                    <FloatLabel variant="on">
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
    const response = await axios.post(route('rolle.store'), form.value);
    //const response = form.post(route('rolle.store'), form.value);
    Swal.fire('Erfolg!', 'Standort erfolgreich angelegt!', 'success');
    emit('added', response.data.role);
    resetForm();
    emit('close');
  } catch (error) {
    Swal.fire('Fehler', error.response?.data?.message || 'Speichern fehlgeschlagen', 'error');
  }
};
</script>
