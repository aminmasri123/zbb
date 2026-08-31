<script setup>
import Modal from '@/Components/ModalForm.vue';
import { ref } from 'vue';
import Swal from 'sweetalert2';
import FloatLabel from 'primevue/floatlabel';
import InputText from 'primevue/inputtext';
import Textarea from 'primevue/textarea';

const props = defineProps({
  visible: Boolean,
  unterweisungThemen: { type: Array, default: () => [] },
});

const emit = defineEmits(['close', 'add-bereich']);

let newBereich = ref({
  name: '',
  code: '',
  beschreibung: '',
  unterweisung_themen: [],
});

const resetForm = () => {
  newBereich.value = { name: '', code: '', beschreibung: '', unterweisung_themen: [] };
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
          <InputText id="code" v-model="newBereich.code" maxlength="10" class="w-full" />
          <label for="code">Abkürzung</label>
        </FloatLabel>
      </div>
      <fieldset class="rounded border border-gray-200 p-3">
        <legend class="px-1 text-sm font-semibold text-gray-700">Standardkreuze Unterweisungsnachweis</legend>
        <p class="mb-3 text-xs text-gray-500">Diese Auswahl gilt projektübergreifend für alle Gruppen dieses Bereichs.</p>
        <div class="grid gap-2 sm:grid-cols-2">
          <label v-for="thema in props.unterweisungThemen" :key="thema.key" class="flex items-start gap-2 text-sm text-gray-700">
            <input v-model="newBereich.unterweisung_themen" type="checkbox" :value="thema.key" class="mt-0.5 rounded border-gray-300 text-zbb focus:ring-zbb">
            <span>{{ thema.label }}</span>
          </label>
        </div>
      </fieldset>
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
