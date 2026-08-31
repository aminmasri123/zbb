<script setup>
import Modal from '@/Components/ModalForm.vue';
import { ref, watch } from 'vue';
import Swal from 'sweetalert2';
import FloatLabel from 'primevue/floatlabel';
import InputText from 'primevue/inputtext';
import Textarea from 'primevue/textarea';

const props = defineProps({
  visible: Boolean,
  toEdit: { type: Object, default: null },
  unterweisungThemen: { type: Array, default: () => [] },
});

const emit = defineEmits(['close', 'updated']);

let editBereich = ref({
  id: null,
  name: '',
  code: '',
  beschreibung: '',
  unterweisung_themen: [],
});

// Wenn sich toEdit ändert → Formular befüllen
watch(() => props.toEdit, (value) => {
  if (value) {
    editBereich.value = { ...value, unterweisung_themen: [...(value.unterweisung_themen || [])] };
  }
}, { immediate: true });

const close = () => {
  emit('close');
};

// Update an den Server
const save = async () => {
  try {
    const response = await axios.put(route('bereich.update', editBereich.value.id), {
      name: editBereich.value.name,
      code: editBereich.value.code,
      beschreibung: editBereich.value.beschreibung,
      unterweisung_themen: editBereich.value.unterweisung_themen,
    });

    Swal.fire({
      title: 'Erfolg!',
      text: 'Bereich erfolgreich aktualisiert!',
      icon: 'success',
      timer: 2000,
      timerProgressBar: true,
    });

    emit('updated', response.data.bereich); // an Parent zurückgeben
    close();

  } catch (error) {
    console.error(error);
    Swal.fire('Fehler', error.response?.data?.message || 'Update fehlgeschlagen', 'error');
  }
};
</script>

<template>
  <Modal v-if="visible" @close="close">
    <template #header>
      <h2 class="text-lg font-bold text-gray-500">Bereich bearbeiten</h2>
    </template>

    <template #body>
      <div class="mb-4">
        <FloatLabel variant="on">
          <InputText id="name" v-model="editBereich.name" class="w-full" />
          <label for="name">Bezeichnung</label>
        </FloatLabel>
      </div>
      <div class="mb-4">
        <FloatLabel variant="on">
          <InputText id="edit-code" v-model="editBereich.code" maxlength="10" class="w-full" />
          <label for="edit-code">Abkürzung</label>
        </FloatLabel>
      </div>
      <fieldset class="rounded border border-gray-200 p-3">
        <legend class="px-1 text-sm font-semibold text-gray-700">Standardkreuze Unterweisungsnachweis</legend>
        <p class="mb-3 text-xs text-gray-500">Diese Auswahl wird für jede Gruppe dieses Bereichs übernommen – unabhängig vom Projekt.</p>
        <div class="grid gap-2 sm:grid-cols-2">
          <label v-for="thema in props.unterweisungThemen" :key="thema.key" class="flex items-start gap-2 text-sm text-gray-700">
            <input v-model="editBereich.unterweisung_themen" type="checkbox" :value="thema.key" class="mt-0.5 rounded border-gray-300 text-zbb focus:ring-zbb">
            <span>{{ thema.label }}</span>
          </label>
        </div>
      </fieldset>
      <div class="mb-4">
        <FloatLabel variant="on">
          <Textarea id="beschreibung" v-model="editBereich.beschreibung" rows="4" class="w-full" />
          <label for="beschreibung">Beschreibung</label>
        </FloatLabel>
      </div>
    </template>

    <template #footer>
      <button @click="save" class="mx-2 bg-zbb text-white px-4 py-2 rounded">Speichern</button>
      <button @click="close" class="mx-2 border border-zbb text-zbb px-4 py-2 rounded">Abbrechen</button>
    </template>
  </Modal>
</template>
