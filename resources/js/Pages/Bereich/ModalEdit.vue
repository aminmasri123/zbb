<script setup>
import Modal from '@/Components/ModalForm.vue';
import { ref, watch } from 'vue';
import axios from 'axios';
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
const themen = ref([]);
const themenBearbeiten = ref(false);
const themenGeaendert = ref(false);
let naechsterThemenSchluessel = 1;

let editBereich = ref({
  id: null,
  name: '',
  code: '',
  beschreibung: '',
  unterweisung_themen: [],
});

// Wenn sich toEdit ändert → Formular befüllen
watch(() => [props.visible, props.toEdit], ([visible, value]) => {
  if (visible && value) {
    editBereich.value = { ...value, unterweisung_themen: [...(value.unterweisung_themen || [])] };
    themen.value = value.unterweisung_themenliste == null
      ? props.unterweisungThemen.map((thema) => ({ ...thema }))
      : Object.entries(value.unterweisung_themenliste).map(([key, label]) => ({ key, label }));
    themenBearbeiten.value = false;
    themenGeaendert.value = false;
    naechsterThemenSchluessel = 1;
  }
}, { immediate: true });

const themaHinzufuegen = () => {
  let key = `eigenes_thema_${naechsterThemenSchluessel++}`;
  while (themen.value.some((thema) => thema.key === key)) {
    key = `eigenes_thema_${naechsterThemenSchluessel++}`;
  }
  themen.value.push({ key, label: '' });
  themenGeaendert.value = true;
};

const themaEntfernen = (key) => {
  themen.value = themen.value.filter((thema) => thema.key !== key);
  editBereich.value.unterweisung_themen = editBereich.value.unterweisung_themen.filter((auswahl) => auswahl !== key);
  themenGeaendert.value = true;
};

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
      ...(themenGeaendert.value ? { unterweisung_themenliste: themen.value } : {}),
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
        <button type="button" class="mb-3 text-sm font-semibold text-zbb underline" :aria-expanded="themenBearbeiten"
          @click="themenBearbeiten = !themenBearbeiten">
          {{ themenBearbeiten ? 'Themenbearbeitung schließen' : 'Themen bearbeiten' }}
        </button>
        <div v-if="themenBearbeiten" class="space-y-2">
          <div v-for="(thema, index) in themen" :key="thema.key" class="flex items-center gap-2">
            <InputText v-model="thema.label" :aria-label="`Bezeichnung für Thema ${index + 1}`"
              maxlength="120" class="min-w-0 flex-1" @update:modelValue="themenGeaendert = true" />
            <button type="button" class="text-sm text-red-700 underline" :aria-label="`Thema ${thema.label || index + 1} entfernen`"
              @click="themaEntfernen(thema.key)">Entfernen</button>
          </div>
          <button type="button" class="rounded border border-zbb px-3 py-1 text-sm text-zbb"
            :disabled="themen.length >= 100" @click="themaHinzufuegen">Thema hinzufügen</button>
        </div>
        <div v-else class="grid gap-2 sm:grid-cols-2">
          <p v-if="!themen.length" class="text-sm text-gray-600">Für diesen Bereich sind keine Themen hinterlegt.</p>
          <label v-for="thema in themen" :key="thema.key" class="flex items-start gap-2 text-sm text-gray-700">
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
