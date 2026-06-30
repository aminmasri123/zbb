<script setup>
import { computed, ref } from 'vue';
import Swal from 'sweetalert2';
import axios from 'axios';
import FloatLabel from 'primevue/floatlabel';
import InputText from 'primevue/inputtext';
import MultiSelect from 'primevue/multiselect';

const props = defineProps({
  modelValue: {
    type: Array,
    default: () => [],
  },
  kostenstellen: {
    type: Array,
    default: () => [],
  },
});

const emit = defineEmits(['update:modelValue', 'created']);

const showCreateForm = ref(false);
const creating = ref(false);
const newKostenstelle = ref('');
const defaultYear = new Date().getFullYear();

const selectedKostenstelleIds = computed({
  get: () => (props.modelValue ?? []).map((entry) => entry.kostenstelle_id),
  set: (ids) => {
    const currentEntries = props.modelValue ?? [];
    const nextEntries = (ids ?? []).map((id) => {
      const existingEntry = currentEntries.find((entry) => entry.kostenstelle_id === id);

      return existingEntry ?? createKostenstelleEntry(id);
    });

    emit('update:modelValue', nextEntries);
  },
});

const createKostenstelleEntry = (id, year = defaultYear) => ({
  kostenstelle_id: id,
  gueltig_von: `${year}-01-01`,
  gueltig_bis: `${year}-12-31`,
});

const addSelectedId = (id) => {
  selectedKostenstelleIds.value = [...new Set([...(selectedKostenstelleIds.value ?? []), id])];
};

const updateEntry = (index, changes) => {
  const nextEntries = [...(props.modelValue ?? [])];
  nextEntries[index] = {
    ...nextEntries[index],
    ...changes,
  };
  emit('update:modelValue', nextEntries);
};

const removeEntry = (id) => {
  selectedKostenstelleIds.value = selectedKostenstelleIds.value.filter((selectedId) => selectedId !== id);
};

const getKostenstelleLabel = (id) => {
  return props.kostenstellen.find((item) => item.id === id)?.kostenstelle ?? `#${id}`;
};

const getYearFromEntry = (entry) => {
  return entry.gueltig_von?.slice(0, 4) ?? defaultYear;
};

const applyYear = (index, year) => {
  const normalizedYear = String(year || '').slice(0, 4);

  if (!/^\d{4}$/.test(normalizedYear)) {
    return;
  }

  updateEntry(index, {
    gueltig_von: `${normalizedYear}-01-01`,
    gueltig_bis: `${normalizedYear}-12-31`,
  });
};

const saveKostenstelle = async () => {
  const kostenstelle = newKostenstelle.value.trim();

  if (!kostenstelle) {
    Swal.fire('Fehler', 'Bitte Kostenstelle eingeben.', 'error');
    return;
  }

  const existingKostenstelle = props.kostenstellen.find(
    (item) => item.kostenstelle?.trim().toLowerCase() === kostenstelle.toLowerCase()
  );

  if (existingKostenstelle) {
    addSelectedId(existingKostenstelle.id);
    newKostenstelle.value = '';
    showCreateForm.value = false;
    Swal.fire({
      title: 'Hinweis',
      text: 'Diese Kostenstelle existiert bereits und wurde zugeordnet.',
      icon: 'info',
      timer: 2200,
      timerProgressBar: true,
    });
    return;
  }

  creating.value = true;

  try {
    const response = await axios.post(route('kostenstelle.store'), {
      kostenstelle,
    });

    const createdKostenstelle = response.data.kostenstelle;
    emit('created', createdKostenstelle);
    addSelectedId(createdKostenstelle.id);
    newKostenstelle.value = '';
    showCreateForm.value = false;

    Swal.fire({
      title: 'Erfolg',
      text: 'Kostenstelle wurde angelegt und zugeordnet.',
      icon: 'success',
      timer: 2200,
      timerProgressBar: true,
    });
  } catch (error) {
    Swal.fire(
      'Fehler',
      error.response?.data?.message || 'Kostenstelle konnte nicht angelegt werden.',
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
        v-model="selectedKostenstelleIds"
        :options="kostenstellen"
        optionLabel="kostenstelle"
        optionValue="id"
        display="chip"
        filter
        class="w-full"
      />
      <label>Kostenstellen zuordnen</label>
    </FloatLabel>

    <button
      type="button"
      class="inline-flex items-center gap-2 text-sm text-zbb hover:text-zbb/80"
      @click="showCreateForm = !showCreateForm"
    >
      <i class="la la-plus"></i>
      Kostenstelle direkt anlegen
    </button>

    <div v-if="props.modelValue.length" class="space-y-2">
      <div
        v-for="(entry, index) in props.modelValue"
        :key="entry.kostenstelle_id"
        class="rounded border border-gray-200 bg-white p-3"
      >
        <div class="mb-3 flex items-center justify-between gap-3">
          <span class="font-medium text-gray-700">{{ getKostenstelleLabel(entry.kostenstelle_id) }}</span>
          <button
            type="button"
            class="text-sm text-red-600 hover:text-red-700"
            @click="removeEntry(entry.kostenstelle_id)"
          >
            Entfernen
          </button>
        </div>

        <div class="grid grid-cols-1 gap-3 sm:grid-cols-3">
          <label class="text-sm text-gray-600">
            Jahr
            <input
              :value="getYearFromEntry(entry)"
              type="number"
              min="2000"
              max="2100"
              class="mt-1 w-full rounded border-gray-300 text-sm"
              @change="applyYear(index, $event.target.value)"
            />
          </label>

          <label class="text-sm text-gray-600">
            Gueltig von
            <input
              :value="entry.gueltig_von"
              type="date"
              class="mt-1 w-full rounded border-gray-300 text-sm"
              @input="updateEntry(index, { gueltig_von: $event.target.value })"
            />
          </label>

          <label class="text-sm text-gray-600">
            Gueltig bis
            <input
              :value="entry.gueltig_bis"
              type="date"
              class="mt-1 w-full rounded border-gray-300 text-sm"
              @input="updateEntry(index, { gueltig_bis: $event.target.value })"
            />
          </label>
        </div>
      </div>
    </div>

    <div v-if="showCreateForm" class="rounded border border-gray-200 bg-gray-50 p-3">
      <div class="mb-3">
        <FloatLabel variant="on">
          <InputText v-model="newKostenstelle" class="w-full" />
          <label>Kostenstelle</label>
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
          @click="saveKostenstelle"
        >
          <span v-if="creating">Speichert...</span>
          <span v-else>Anlegen</span>
        </button>
      </div>
    </div>
  </div>
</template>
