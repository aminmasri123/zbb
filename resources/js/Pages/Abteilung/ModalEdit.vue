<script setup>
import { ref, watch, defineProps, defineEmits } from 'vue';
import Swal from 'sweetalert2';
import axios from 'axios';
import Modal from '@/Components/ModalForm.vue';
import MultiSelect from 'primevue/multiselect';
import InputText from 'primevue/inputtext';
import FloatLabel from 'primevue/floatlabel';
import Select from 'primevue/select';

// Props
const props = defineProps({
  visible: { type: Boolean, default: false },
  toEdit: { type: Object, default: null },
  users: { type: Array, default: () => [] },
});

// Events
const emit = defineEmits(["close", "updated"]);
const close = () => {
  editAbteilung.value = {
    name: '',
    abteilungsleiter: '',
    assistenten: []
  };
  emit("close");
};


// Lokales Formular
let editAbteilung = ref({
  name: '',
  abteilungsleiter: '',
  assistenten: []
});

// Wenn sich toEdit ändert → Formular befüllen
watch(() => props.toEdit, (value) => {
  if (value) {
    editAbteilung.value = {
      name: value.name || '',
      abteilungsleiter: value.user?.id || '',
      assistenten: value.abteilungsassistente?.map(a => a.user.id) || []
    }
  }
}, { immediate: true });


// Speichern (Update)
const updateAbteilung = async () => {
  try {
    const response = await axios.put(
      route('abteilung.update', props.toEdit.id),
      editAbteilung.value
    );

    Swal.fire({
      title: 'Erfolg!',
      text: 'Abteilung erfolgreich aktualisiert!',
      icon: 'success',
      timer: 3000,
      timerProgressBar: true,
    });

    emit("updated", response.data.abteilung);
    close();

  } catch (error) {
    console.error(error);
    Swal.fire({
      title: 'Error!',
      text: error.response?.data?.message || 'Fehler beim Aktualisieren der Abteilung.',
      icon: 'error',
      timer: 3000,
      timerProgressBar: true,
    });
  }
};
</script>

<template>
  <Modal v-if="visible" @close="close">
    <template #header>
      <div class="text-center w-full uppercase text-lg font-bold">
        <h2 class="text-lg font-bold text-gray-500 ">
          {{ $t('Abteilung bearbeiten') }}
        </h2>
      </div>
    </template>

    <template #body>
      <form @submit.prevent="updateAbteilung">
        <div class="mb-4 w-full mx-1">
          <FloatLabel variant="on">
            <InputText id="name" v-model="editAbteilung.name" class="w-full" />
            <label for="name">Bezeichnung</label>
          </FloatLabel>
        </div>

        <div class="mb-4 w-full mx-1">
          <FloatLabel variant="on">
            <Select
              v-model="editAbteilung.abteilungsleiter"
              optionValue="id"
              :options="users"
              optionLabel="full_name"
              class="w-full"
            />
            <label for="abteilungsleiter">Abteilungsleitung wählen</label>
          </FloatLabel>
        </div>

        <div class="mb-4 w-full mx-1">
          <MultiSelect
            v-model="editAbteilung.assistenten"
            display="chip"
            optionLabel="full_name"
            :options="users"
            optionValue="id"
            filter
            placeholder="Assistenten wählen*"
            :maxSelectedLabels="3"
            class="w-full"
          />
        </div>
      </form>
    </template>

    <template #footer>
      <div class="w-full flex justify-center">
        <button @click="updateAbteilung" class="mx-2 bg-zbb text-white px-4 py-2 rounded">
          Speichern
        </button>
        <button @click="close" class="mx-2 border border-zbb text-zbb px-4 py-2 rounded">
          Abbrechen
        </button>
      </div>
    </template>
  </Modal>
</template>
