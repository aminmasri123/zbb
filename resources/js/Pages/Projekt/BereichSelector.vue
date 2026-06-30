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
});

const emit = defineEmits(['update:modelValue', 'created']);

const showCreateForm = ref(false);
const creating = ref(false);
const newBereich = ref({
  name: '',
  beschreibung: '',
});

const selectedBereiche = computed({
  get: () => props.modelValue ?? [],
  set: (value) => emit('update:modelValue', value ?? []),
});

const resetNewBereich = () => {
  newBereich.value = {
    name: '',
    beschreibung: '',
  };
};

const addSelectedId = (id) => {
  selectedBereiche.value = [...new Set([...(selectedBereiche.value ?? []), id])];
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
