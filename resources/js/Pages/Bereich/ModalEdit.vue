<script setup>
import Modal from '@/Components/ModalForm.vue';
import { computed, ref, watch } from 'vue';
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
const globaleThemen = ref([]);
const themenstand = ref([]);
const bereichsstand = ref({});
const abweichungen = ref({});
const bearbeitungsbereich = ref(null);
const globaleThemenGeaendert = ref(false);
const bereichsthemenGeaendert = ref(false);
const themen = computed(() => Object.entries({
  ...Object.fromEntries(globaleThemen.value.map(({ key, label }) => [key, label])),
  ...abweichungen.value,
}).filter(([, label]) => label !== null).map(([key, label]) => ({ key, label })));
const bearbeiteteThemen = computed(() => bearbeitungsbereich.value === 'global' ? globaleThemen.value : themen.value);

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
    themenstand.value = props.unterweisungThemen.map((thema) => ({ ...thema }));
    globaleThemen.value = themenstand.value.map((thema) => ({ ...thema }));
    bereichsstand.value = { ...value.unterweisung_abweichungen };
    abweichungen.value = { ...bereichsstand.value };
    editBereich.value = {
      ...value,
      unterweisung_themen: [...(value.unterweisung_themen || [])],
    };
    bearbeitungsbereich.value = null;
    globaleThemenGeaendert.value = false;
    bereichsthemenGeaendert.value = false;
  }
}, { immediate: true });

const themaHinzufuegen = () => {
  const zufall = crypto.getRandomValues(new Uint32Array(4));
  const key = `eigenes_thema_${Array.from(zufall, (zahl) => zahl.toString(16).padStart(8, '0')).join('')}`;
  if (bearbeitungsbereich.value === 'global') {
    globaleThemen.value.push({ key, label: '' });
    globaleThemenGeaendert.value = true;
  } else {
    themaUmbenennen(key, '');
  }
};

const themaUmbenennen = (key, label) => {
  const globalesThema = globaleThemen.value.find((thema) => thema.key === key);
  if (bearbeitungsbereich.value === 'global') {
    globalesThema.label = label;
    globaleThemenGeaendert.value = true;
  } else {
    if (globalesThema?.label === label) {
      delete abweichungen.value[key];
    } else {
      abweichungen.value[key] = label;
    }
    bereichsthemenGeaendert.value = true;
  }
};

const themaEntfernen = (key) => {
  if (bearbeitungsbereich.value === 'global') {
    globaleThemen.value = globaleThemen.value.filter((thema) => thema.key !== key);
    globaleThemenGeaendert.value = true;
  } else {
    if (globaleThemen.value.some((thema) => thema.key === key)) {
      abweichungen.value[key] = null;
    } else {
      delete abweichungen.value[key];
    }
    bereichsthemenGeaendert.value = true;
  }
};

const gemeinsameThemenUebernehmen = () => {
  abweichungen.value = {};
  bereichsthemenGeaendert.value = true;
};

const close = () => {
  emit('close');
};

// Update an den Server
const save = async () => {
  if (globaleThemen.value.some(({ label }) => !label.trim())
    || Object.values(abweichungen.value).some((label) => label !== null && !label.trim())) {
    Swal.fire('Bezeichnung fehlt', 'Bitte gib jedem Thema eine Bezeichnung.', 'error');
    return;
  }
  const schluessel = new Set(themen.value.map((thema) => thema.key));
  try {
    const response = await axios.put(route('bereich.update', editBereich.value.id), {
      name: editBereich.value.name,
      code: editBereich.value.code,
      beschreibung: editBereich.value.beschreibung,
      unterweisung_themen: editBereich.value.unterweisung_themen.filter((key) => schluessel.has(key)),
      ...(globaleThemenGeaendert.value ? {
        unterweisung_themenliste: globaleThemen.value,
        unterweisung_themenstand: themenstand.value,
      } : {}),
      ...(bereichsthemenGeaendert.value ? {
        unterweisung_abweichungen: Object.entries(abweichungen.value).map(([key, label]) => ({ key, label })),
        unterweisung_bereichsstand: bereichsstand.value,
      } : {}),
    });

    Swal.fire({
      title: 'Erfolg!',
      text: 'Bereich erfolgreich aktualisiert!',
      icon: 'success',
      timer: 2000,
      timerProgressBar: true,
    });

    emit('updated', response.data.bereich, response.data.unterweisungThemen);
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
        <div class="mb-3 flex flex-wrap gap-x-4 gap-y-2 text-sm font-semibold text-zbb">
          <button type="button" class="underline" :aria-expanded="bearbeitungsbereich === 'global'"
            @click="bearbeitungsbereich = bearbeitungsbereich === 'global' ? null : 'global'">
            Themen für alle Bereiche bearbeiten
          </button>
          <button type="button" class="underline" :aria-expanded="bearbeitungsbereich === 'bereich'"
            @click="bearbeitungsbereich = bearbeitungsbereich === 'bereich' ? null : 'bereich'">
            Themen für diesen Bereich bearbeiten
          </button>
        </div>
        <div v-if="bearbeitungsbereich" class="space-y-2">
          <p class="text-sm text-gray-600">{{ bearbeitungsbereich === 'global'
            ? 'Du bearbeitest die gemeinsame Vorlage. Individuelle Anpassungen haben im jeweiligen Bereich Vorrang.'
            : 'Du bearbeitest die Themenliste dieses Bereichs.' }}</p>
          <div v-for="(thema, index) in bearbeiteteThemen" :key="thema.key" class="flex items-center gap-2">
            <InputText :modelValue="thema.label" :aria-label="`Bezeichnung für Thema ${index + 1}`"
              maxlength="120" class="min-w-0 flex-1" @update:modelValue="themaUmbenennen(thema.key, $event)" />
            <button type="button" class="text-sm text-red-700 underline" :aria-label="`Thema ${thema.label || index + 1} entfernen`"
              @click="themaEntfernen(thema.key)">Entfernen</button>
          </div>
          <button type="button" class="rounded border border-zbb px-3 py-1 text-sm text-zbb"
            :disabled="bearbeiteteThemen.length >= 100" @click="themaHinzufuegen">Thema hinzufügen</button>
          <button v-if="bearbeitungsbereich === 'bereich' && Object.keys(abweichungen).length"
            type="button" class="ml-3 text-sm text-zbb underline" @click="gemeinsameThemenUebernehmen">
            Gemeinsame Vorlage übernehmen
          </button>
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
