<template>
    <Modal v-if="visible" @close="emit('close')">
        <template #header>{{$t('Standort bearbeiten')}}</template>

        <template #body>
            <form @submit.prevent="save">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div class="mb-4 w-full">
                        <FloatLabel>
                            <InputText v-model="form.name" class="w-full" required />
                            <label>Bezeichnung</label>
                        </FloatLabel>
                    </div>
                    <div class="mb-4 w-full">
                        <FloatLabel>
                            <InputText v-model="form.beschreibung" class="w-full" />
                            <label>Beschreibung</label>
                        </FloatLabel>
                    </div>
                </div>
            </form>
        </template>

        <template #footer>
            <div class="flex justify-end gap-2">
                <button @click="save" class="bg-zbb text-white px-4 py-2 rounded hover:bg-zbb-dark transition" type="button">
                    Speichern
                </button>
                <button @click="emit('close')" class="border px-4 py-2 rounded hover:bg-gray-100 transition" type="button">
                    Abbrechen
                </button>
            </div>
        </template>
    </Modal>
</template>

<script setup>
import Modal from '@/Components/ModalForm.vue';
import { ref, watch } from 'vue';
import Swal from 'sweetalert2';
import FloatLabel from 'primevue/floatlabel';
import InputText from 'primevue/inputtext';
import axios from 'axios';
import { router } from '@inertiajs/vue3'

const props = defineProps({
  visible: Boolean,
  toEdit: Object,
});
const emit = defineEmits(['close', 'updated']);

let form = ref({
  id: null,
  name: '',
  beschreibung: '',
});

watch(() => props.toEdit, (val) => {
  if (val) {
    Object.assign(form.value, {
      id: val.id,
      name: val.name,
      beschreibung: val.beschreibung || '',
    });
  }
}, { immediate: true });

const save = async () => {
  try {
    const response = await axios.put(route('standort.update', form.value.id), {
      name: form.value.name,
      beschreibung: form.value.beschreibung
    });

    Swal.fire('Gespeichert!', 'Standort wurde aktualisiert!', 'success');

    emit('updated', response.data.standort ?? null);
    emit('close');

  } catch (error) {
    Swal.fire('Fehler', error.response?.data?.message || 'Update fehlgeschlagen', 'error');
  }
};

</script>
