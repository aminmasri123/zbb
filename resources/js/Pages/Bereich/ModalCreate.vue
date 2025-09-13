<script setup>
import Modal from '@/Components/ModalForm.vue';
import { ref } from 'vue';
import Swal from 'sweetalert2';
import FloatLabel from 'primevue/floatlabel';
import InputText from 'primevue/inputtext';
import Textarea from 'primevue/textarea';

const props = defineProps({
  visible: Boolean,
});

const emit = defineEmits(['close', 'add-bereich']);

let newBereich = ref({
  name: '',
  beschreibung: '',
});

const resetForm = () => {
  newBereich.value = { name: '', beschreibung: '' };
};

const save = () => {
  if (!newBereich.value.name) {
    Swal.fire('Fehler', 'Bitte Bezeichnung eingeben!', 'error');
    return;
  }
  emit('add-bereich', { ...newBereich.value });
  resetForm();
  emit('close'); // nach Speichern auch schließen
};
const close = () => {
  resetForm();
  emit('close');
};
</script>

<template>
<Modal v-if="visible" @close="emit('close')">
    <template #header>
      <h2 class="text-lg font-bold text-gray-500">Bereich anlegen</h2>
    </template>

    <template #body>
      <div class="mb-4">
        <FloatLabel variant="on">
          <InputText id="name" v-model="newBereich.name" class="w-full" />
          <label for="name">Bezeichnung</label>
        </FloatLabel>
      </div>
      <div class="mb-4">
        <FloatLabel variant="on">
          <Textarea id="beschreibung" v-model="newBereich.beschreibung" rows="4" class="w-full" style="resize: none"/>
          <label for="beschreibung">Beschreibung</label>
        </FloatLabel>
      </div>
    </template>

    <template #footer>
      <button @click="save" class="mx-2 bg-zbb text-white px-4 py-2 rounded">Speichern</button>
      <button @click="emit('close')" class="mx-2 border border-zbb text-zbb px-4 py-2 rounded">Abbrechen</button>
    </template>
  </Modal>
</template>
