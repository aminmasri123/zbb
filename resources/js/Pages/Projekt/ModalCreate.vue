<script setup>
import { ref, watch } from 'vue';
import Swal from 'sweetalert2';
import axios from 'axios';
import Modal from '@/Components/ModalForm.vue';
import FloatLabel from 'primevue/floatlabel';
import InputText from 'primevue/inputtext';
import Select from 'primevue/select';
import BereichSelector from '@/Pages/Projekt/BereichSelector.vue';
import KostenstelleSelector from '@/Pages/Projekt/KostenstelleSelector.vue';

const props = defineProps({
  visible: Boolean,
  abteilungen: {
    type: Array,
    default: () => [],
  },
  bereiche: {
    type: Array,
    default: () => [],
  },
  kostenstellen: {
    type: Array,
    default: () => [],
  },
});

const emit = defineEmits(['close', 'added', 'bereich-created', 'kostenstelle-created']);

const saving = ref(false);
const form = ref({
  name: '',
  kostenstelle: '',
  kostenstellen: [],
  abteilung: null,
  antragsdatum: '',
  starttermin: '',
  anfangsdatum: '',
  endtermin: '',
  enddatum: '',
  bereiche: [],
});

const resetForm = () => {
  form.value = {
    name: '',
    kostenstelle: '',
    kostenstellen: [],
    abteilung: null,
    antragsdatum: '',
    starttermin: '',
    anfangsdatum: '',
    endtermin: '',
    enddatum: '',
    bereiche: [],
  };
};

watch(
  () => props.visible,
  (visible) => {
    if (visible) {
      resetForm();
    }
  }
);

const close = () => {
  resetForm();
  emit('close');
};

const save = async () => {
  const hasValidKostenstellen = form.value.kostenstellen.every((entry) =>
    entry.kostenstelle_id &&
    entry.gueltig_von &&
    entry.gueltig_bis &&
    entry.gueltig_von <= entry.gueltig_bis
  );

  if (!form.value.name || !form.value.abteilung || !form.value.kostenstellen.length || !hasValidKostenstellen) {
    Swal.fire('Fehler', 'Bitte Projektname, Abteilung und je Kostenstelle einen gueltigen Zeitraum ausfuellen.', 'error');
    return;
  }

  saving.value = true;

  try {
    const selectedKostenstelle = props.kostenstellen.find(
      (kostenstelle) => kostenstelle.id === form.value.kostenstellen[0]?.kostenstelle_id
    );
    const response = await axios.post(route('projekt.store'), {
      ...form.value,
      kostenstelle: selectedKostenstelle?.kostenstelle ?? '',
    });

    Swal.fire({
      title: 'Erfolg',
      text: 'Projekt erfolgreich angelegt.',
      icon: 'success',
      timer: 2200,
      timerProgressBar: true,
    });

    emit('added', response.data.projekt);
    close();
  } catch (error) {
    Swal.fire(
      'Fehler',
      error.response?.data?.message || error.response?.data?.error || 'Projekt konnte nicht angelegt werden.',
      'error'
    );
  } finally {
    saving.value = false;
  }
};

const handleBereichCreated = (bereich) => {
  emit('bereich-created', bereich);
};

const handleKostenstelleCreated = (kostenstelle) => {
  emit('kostenstelle-created', kostenstelle);
};
</script>

<template>
  <Modal v-if="visible" @close="close">
    <template #header>
      <h2 class="text-lg font-bold text-gray-600">Projekt anlegen</h2>
    </template>

    <template #body>
      <div class="max-h-[70vh] overflow-y-auto pr-2">
        <div class="mb-4">
          <FloatLabel variant="on">
            <InputText v-model="form.name" class="w-full" />
            <label>Projektname</label>
          </FloatLabel>
        </div>

        <div class="mb-4">
          <KostenstelleSelector
            v-model="form.kostenstellen"
            :kostenstellen="props.kostenstellen"
            @created="handleKostenstelleCreated"
          />
        </div>

        <div class="mb-4">
          <FloatLabel variant="on">
            <Select
              v-model="form.abteilung"
              :options="props.abteilungen"
              optionLabel="name"
              optionValue="id"
              class="w-full"
            />
            <label>Abteilung</label>
          </FloatLabel>
        </div>

        <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
          <label class="text-sm text-gray-600">
            Antragsdatum
            <input v-model="form.antragsdatum" type="date" class="mt-1 w-full rounded border-gray-300 text-sm" />
          </label>

          <label class="text-sm text-gray-600">
            Starttermin
            <input v-model="form.starttermin" type="date" class="mt-1 w-full rounded border-gray-300 text-sm" />
          </label>

          <label class="text-sm text-gray-600">
            Anfangsdatum
            <input v-model="form.anfangsdatum" type="date" class="mt-1 w-full rounded border-gray-300 text-sm" />
          </label>

          <label class="text-sm text-gray-600">
            Endtermin
            <input v-model="form.endtermin" type="date" class="mt-1 w-full rounded border-gray-300 text-sm" />
          </label>

          <label class="text-sm text-gray-600 sm:col-span-2">
            Enddatum
            <input v-model="form.enddatum" type="date" class="mt-1 w-full rounded border-gray-300 text-sm" />
          </label>
        </div>

        <div class="mt-5">
          <BereichSelector
            v-model="form.bereiche"
            :bereiche="props.bereiche"
            @created="handleBereichCreated"
          />
        </div>
      </div>
    </template>

    <template #footer>
      <button
        type="button"
        class="mx-2 bg-zbb text-white px-4 py-2 rounded disabled:opacity-60"
        :disabled="saving"
        @click="save"
      >
        <span v-if="saving">Speichert...</span>
        <span v-else>Speichern</span>
      </button>
      <button type="button" class="mx-2 border border-zbb text-zbb px-4 py-2 rounded" @click="close">
        Abbrechen
      </button>
    </template>
  </Modal>
</template>
