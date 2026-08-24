<script setup>
import { computed, ref } from 'vue';
import Swal from 'sweetalert2';
import axios from 'axios';
import FloatLabel from 'primevue/floatlabel';
import InputText from 'primevue/inputtext';
import MultiSelect from 'primevue/multiselect';
import Textarea from 'primevue/textarea';

const props = defineProps({
  modelValue: {
    type: Array,
    default: () => [],
  },
  bereiche: {
    type: Array,
    default: () => [],
  },
  manageAssigned: {
    type: Boolean,
    default: false,
  },
});

const emit = defineEmits(['update:modelValue', 'created', 'updated']);

const showCreateForm = ref(false);
const creating = ref(false);
const savingEdit = ref(false);
const editingBereichId = ref(null);
const newBereich = ref({
  name: '',
  code: '',
  beschreibung: '',
});
const editBereich = ref({
  name: '',
  code: '',
  beschreibung: '',
});

const selectedBereiche = computed({
  get: () => props.modelValue ?? [],
  set: (value) => emit('update:modelValue', value ?? []),
});

const assignedBereiche = computed(() => (selectedBereiche.value ?? [])
  .map((id) => props.bereiche.find((bereich) => bereich.id === id))
  .filter(Boolean));

const resetNewBereich = () => {
  newBereich.value = {
    name: '',
    code: '',
    beschreibung: '',
  };
};

const addSelectedId = (id) => {
  selectedBereiche.value = [...new Set([...(selectedBereiche.value ?? []), id])];
};

const removeSelectedId = (id) => {
  selectedBereiche.value = (selectedBereiche.value ?? []).filter((selectedId) => selectedId !== id);

  if (editingBereichId.value === id) {
    cancelEdit();
  }
};

const startEdit = (bereich) => {
  editingBereichId.value = bereich.id;
  editBereich.value = {
    name: bereich.name ?? '',
    code: bereich.code ?? '',
    beschreibung: bereich.beschreibung ?? '',
  };
};

const cancelEdit = () => {
  editingBereichId.value = null;
  editBereich.value = {
    name: '',
    code: '',
    beschreibung: '',
  };
};

const updateBereich = async () => {
  const name = editBereich.value.name.trim();

  if (!name) {
    Swal.fire('Fehler', 'Bitte Bereichsname eingeben.', 'error');
    return;
  }

  savingEdit.value = true;

  try {
    const response = await axios.put(route('bereich.update', editingBereichId.value), {
      name,
      code: editBereich.value.code.trim(),
      beschreibung: editBereich.value.beschreibung,
    });

    emit('updated', response.data.bereich);
    cancelEdit();

    Swal.fire({
      title: 'Erfolg',
      text: 'Bereich wurde aktualisiert.',
      icon: 'success',
      timer: 1800,
      timerProgressBar: true,
    });
  } catch (error) {
    Swal.fire(
      'Fehler',
      error.response?.data?.message || 'Bereich konnte nicht aktualisiert werden.',
      'error'
    );
  } finally {
    savingEdit.value = false;
  }
};

const saveBereich = async () => {
  const name = newBereich.value.name.trim();

  if (!name) {
    Swal.fire('Fehler', 'Bitte Bereichsname eingeben.', 'error');
    return;
  }

  const existingBereich = props.bereiche.find(
    (bereich) => bereich.name?.trim().toLowerCase() === name.toLowerCase()
  );

  if (existingBereich) {
    addSelectedId(existingBereich.id);
    resetNewBereich();
    showCreateForm.value = false;
    Swal.fire({
      title: 'Hinweis',
      text: 'Dieser Bereich existiert bereits und wurde zugeordnet.',
      icon: 'info',
      timer: 2200,
      timerProgressBar: true,
    });
    return;
  }

  creating.value = true;

  try {
    const response = await axios.post(route('bereich.store'), {
      name,
      code: newBereich.value.code.trim(),
      beschreibung: newBereich.value.beschreibung,
    });

    const bereich = response.data.bereich;
    emit('created', bereich);
    addSelectedId(bereich.id);
    resetNewBereich();
    showCreateForm.value = false;

    Swal.fire({
      title: 'Erfolg',
      text: 'Bereich wurde angelegt und zugeordnet.',
      icon: 'success',
      timer: 2200,
      timerProgressBar: true,
    });
  } catch (error) {
    Swal.fire(
      'Fehler',
      error.response?.data?.message || 'Bereich konnte nicht angelegt werden.',
      'error'
    );
  } finally {
    creating.value = false;
  }
};
</script>

<template>
  <div class="space-y-3">
    <FloatLabel variant="on">
      <MultiSelect
        v-model="selectedBereiche"
        :options="bereiche"
        optionLabel="name"
        optionValue="id"
        display="chip"
        filter
        class="w-full"
      />
      <label>Bereiche zuordnen</label>
    </FloatLabel>

    <div v-if="manageAssigned" class="rounded border border-gray-200 bg-white">
      <div class="flex items-center justify-between gap-3 border-b border-gray-200 px-3 py-2">
        <div>
          <h4 class="text-sm font-semibold text-gray-700">Zugeordnete Bereiche</h4>
          <p class="text-xs text-gray-500">Entfernte Zuordnungen werden mit dem Projekt gespeichert.</p>
        </div>
        <span class="rounded-full bg-gray-100 px-2 py-1 text-xs font-medium text-gray-600">
          {{ assignedBereiche.length }}
        </span>
      </div>

      <div v-if="assignedBereiche.length" class="divide-y divide-gray-100">
        <div v-for="bereich in assignedBereiche" :key="bereich.id" class="p-3">
          <div v-if="editingBereichId !== bereich.id" class="flex items-start justify-between gap-3">
            <div class="min-w-0">
              <div class="flex flex-wrap items-center gap-2">
                <span class="font-medium text-gray-800">{{ bereich.name }}</span>
                <span v-if="bereich.code" class="rounded bg-gray-100 px-2 py-0.5 text-xs text-gray-600">
                  {{ bereich.code }}
                </span>
              </div>
              <p v-if="bereich.beschreibung" class="mt-1 text-xs text-gray-500">
                {{ bereich.beschreibung }}
              </p>
            </div>

            <div class="flex shrink-0 items-center gap-2">
              <button
                type="button"
                class="inline-flex items-center gap-1 rounded border border-gray-300 px-2 py-1 text-xs text-gray-700 hover:bg-gray-50"
                @click="startEdit(bereich)"
              >
                <i class="las la-edit"></i>
                Bearbeiten
              </button>
              <button
                type="button"
                class="inline-flex items-center gap-1 rounded border border-red-200 px-2 py-1 text-xs text-red-600 hover:bg-red-50"
                @click="removeSelectedId(bereich.id)"
              >
                <i class="las la-unlink"></i>
                Entfernen
              </button>
            </div>
          </div>

          <div v-else class="space-y-3 rounded bg-gray-50 p-3">
            <p class="text-xs text-amber-700">
              Hinweis: Die Stammdaten des Bereichs gelten auch in anderen Projekten.
            </p>
            <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
              <label class="text-xs text-gray-600">
                Bezeichnung
                <InputText v-model="editBereich.name" class="mt-1 w-full" />
              </label>
              <label class="text-xs text-gray-600">
                Abkürzung
                <InputText v-model="editBereich.code" maxlength="10" class="mt-1 w-full" />
              </label>
            </div>
            <label class="block text-xs text-gray-600">
              Beschreibung
              <Textarea v-model="editBereich.beschreibung" rows="2" class="mt-1 w-full" style="resize: none" />
            </label>
            <div class="flex justify-end gap-2">
              <button
                type="button"
                class="rounded border border-gray-300 px-3 py-1.5 text-xs text-gray-700"
                :disabled="savingEdit"
                @click="cancelEdit"
              >
                Abbrechen
              </button>
              <button
                type="button"
                class="rounded bg-zbb px-3 py-1.5 text-xs text-white disabled:opacity-60"
                :disabled="savingEdit"
                @click="updateBereich"
              >
                {{ savingEdit ? 'Speichert...' : 'Änderung speichern' }}
              </button>
            </div>
          </div>
        </div>
      </div>
      <p v-else class="px-3 py-4 text-sm text-gray-500">Noch keine Bereiche zugeordnet.</p>
    </div>

    <button
      type="button"
      class="inline-flex items-center gap-2 text-sm text-zbb hover:text-zbb/80"
      @click="showCreateForm = !showCreateForm"
    >
      <i class="la la-plus"></i>
      Bereich direkt anlegen
    </button>

    <div v-if="showCreateForm" class="rounded border border-gray-200 bg-gray-50 p-3">
      <div class="mb-3">
        <FloatLabel variant="on">
          <InputText v-model="newBereich.name" class="w-full" />
          <label>Bereichsname</label>
        </FloatLabel>
      </div>

      <div class="mb-3">
        <FloatLabel variant="on">
          <InputText v-model="newBereich.code" maxlength="10" class="w-full" />
          <label>Abkürzung</label>
        </FloatLabel>
      </div>

      <div class="mb-3">
        <FloatLabel variant="on">
          <Textarea
            v-model="newBereich.beschreibung"
            rows="3"
            class="w-full"
            style="resize: none"
          />
          <label>Beschreibung</label>
        </FloatLabel>
      </div>

      <div class="flex justify-end gap-2">
        <button
          type="button"
          class="border border-gray-300 px-3 py-2 text-sm rounded text-gray-700"
          @click="showCreateForm = false"
        >
          Abbrechen
        </button>
        <button
          type="button"
          class="bg-zbb px-3 py-2 text-sm rounded text-white disabled:opacity-60"
          :disabled="creating"
          @click="saveBereich"
        >
          <span v-if="creating">Speichert...</span>
          <span v-else>Anlegen</span>
        </button>
      </div>
    </div>
  </div>
</template>
