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
  toEdit: {
    type: Object,
    default: null,
  },
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

const emit = defineEmits(['close', 'updated', 'bereich-created', 'kostenstelle-created']);

const saving = ref(false);
const form = ref({
  id: null,
  name: '',
  kostenstelle: '',
  kostenstellen: [],
  abteilung: null,
  klassenbuch_aktiv: false,
  zeitraume: [],
  bereiche: [],
});

const toDateInput = (value) => {
  if (!value) {
    return '';
  }

  if (typeof value === 'string') {
    return value.slice(0, 10);
  }

  const date = new Date(value);
  return Number.isNaN(date.getTime()) ? '' : date.toISOString().slice(0, 10);
};

const toBoolean = (value) => value === true || value === 1 || value === '1';

const fillForm = (projekt) => {
  if (!projekt) {
    return;
  }

  const zeitraume = projekt.zeitraume?.length
    ? projekt.zeitraume
    : (projekt.projektzeitraume ?? []);

  form.value = {
    id: projekt.id,
    name: projekt.name ?? '',
    kostenstelle: projekt.kostenstelle ?? projekt.kostenstellen?.[0]?.kostenstelle ?? '',
    kostenstellen: projekt.kostenstellen?.map((kostenstelle) => ({
      kostenstelle_id: kostenstelle.id,
      gueltig_von: toDateInput(kostenstelle.pivot?.gueltig_von),
      gueltig_bis: toDateInput(kostenstelle.pivot?.gueltig_bis),
    })) ?? [],
    abteilung: projekt.abteilung_id ?? projekt.abteilung?.id ?? null,
    klassenbuch_aktiv: toBoolean(projekt.klassenbuch_aktiv),
    zeitraume: zeitraume.map((zeitraum) => ({
      id: zeitraum.id ?? null,
      antragsdatum: toDateInput(zeitraum.antragsdatum),
      starttermin: toDateInput(zeitraum.starttermin),
      anfangsdatum: toDateInput(zeitraum.anfangsdatum),
      endtermin: toDateInput(zeitraum.endtermin),
      enddatum: toDateInput(zeitraum.enddatum),
    })),
    bereiche: projekt.bereiche?.map((bereich) => bereich.id) ?? [],
  };

  if (!form.value.zeitraume.length) {
    addZeitraum();
  }
};

watch(
  () => props.toEdit,
  (projekt) => fillForm(projekt),
  { immediate: true }
);

watch(
  () => props.visible,
  (visible) => {
    if (visible) {
      fillForm(props.toEdit);
    }
  }
);

const close = () => {
  emit('close');
};

function addZeitraum() {
  form.value.zeitraume.push({
    id: null,
    antragsdatum: '',
    starttermin: '',
    anfangsdatum: '',
    endtermin: '',
    enddatum: '',
  });
}

const removeNewZeitraum = (index) => {
  if (form.value.zeitraume[index]?.id) {
    return;
  }

  form.value.zeitraume.splice(index, 1);
};

const save = async () => {
  if (!form.value.id) {
    return;
  }

  const hasValidKostenstellen = form.value.kostenstellen.every((entry) =>
    entry.kostenstelle_id &&
    entry.gueltig_von &&
    entry.gueltig_bis &&
    entry.gueltig_von <= entry.gueltig_bis
  );
  const hasValidZeitraume = form.value.zeitraume.length && form.value.zeitraume.every((zeitraum) =>
    zeitraum.antragsdatum &&
    zeitraum.starttermin &&
    zeitraum.anfangsdatum &&
    zeitraum.endtermin &&
    zeitraum.enddatum &&
    zeitraum.anfangsdatum <= zeitraum.enddatum
  );

  if (!form.value.name || !form.value.abteilung || !form.value.kostenstellen.length || !hasValidKostenstellen || !hasValidZeitraume) {
    Swal.fire('Fehler', 'Bitte Projektname, Abteilung, Kostenstellen und den Antragsverlauf vollstaendig ausfuellen.', 'error');
    return;
  }

  saving.value = true;

  try {
    const selectedKostenstelle = props.kostenstellen.find(
      (kostenstelle) => kostenstelle.id === form.value.kostenstellen[0]?.kostenstelle_id
    );
    const response = await axios.put(route('projekt.update', form.value.id), {
      ...form.value,
      kostenstelle: selectedKostenstelle?.kostenstelle ?? form.value.kostenstelle,
    });

    Swal.fire({
      title: 'Erfolg',
      text: 'Projekt erfolgreich aktualisiert.',
      icon: 'success',
      timer: 2200,
      timerProgressBar: true,
    });

    emit('updated', response.data.projekt);
    close();
  } catch (error) {
    Swal.fire(
      'Fehler',
      error.response?.data?.message || error.response?.data?.error || 'Projekt konnte nicht aktualisiert werden.',
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
      <h2 class="text-lg font-bold text-gray-600">Projekt bearbeiten</h2>
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

        <label class="mb-4 flex items-center gap-3 rounded border border-gray-200 bg-gray-50 px-3 py-3 text-sm text-gray-700">
          <input v-model="form.klassenbuch_aktiv" type="checkbox" class="rounded border-gray-300 text-zbb focus:ring-zbb" />
          <span class="font-medium">Klassenbuch aktiv</span>
        </label>

        <div class="mb-4">
          <div class="mb-3 flex items-center justify-between gap-3">
            <h3 class="text-sm font-semibold text-gray-700">Antragsverlauf</h3>
            <button
              type="button"
              class="inline-flex items-center gap-2 text-sm text-zbb hover:text-zbb/80"
              @click="addZeitraum"
            >
              <i class="la la-plus"></i>
              Antrag hinzufuegen
            </button>
          </div>

          <div class="space-y-3">
            <div
              v-for="(zeitraum, index) in form.zeitraume"
              :key="zeitraum.id ?? `neu-${index}`"
              class="rounded border border-gray-200 bg-gray-50 p-3"
            >
              <div class="mb-3 flex items-center justify-between gap-3">
                <span class="font-medium text-gray-700">
                  Antrag {{ index + 1 }}
                  <span v-if="!zeitraum.id" class="text-xs text-zbb">(neu)</span>
                </span>
                <button
                  v-if="!zeitraum.id && form.zeitraume.length > 1"
                  type="button"
                  class="text-sm text-red-600 hover:text-red-700"
                  @click="removeNewZeitraum(index)"
                >
                  Entfernen
                </button>
              </div>

              <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                <label class="text-sm text-gray-600">
                  Antragsdatum
                  <input v-model="zeitraum.antragsdatum" type="date" class="mt-1 w-full rounded border-gray-300 text-sm" />
                </label>

                <label class="text-sm text-gray-600">
                  Starttermin
                  <input v-model="zeitraum.starttermin" type="date" class="mt-1 w-full rounded border-gray-300 text-sm" />
                </label>

                <label class="text-sm text-gray-600">
                  Anfangsdatum
                  <input v-model="zeitraum.anfangsdatum" type="date" class="mt-1 w-full rounded border-gray-300 text-sm" />
                </label>

                <label class="text-sm text-gray-600">
                  Endtermin
                  <input v-model="zeitraum.endtermin" type="date" class="mt-1 w-full rounded border-gray-300 text-sm" />
                </label>

                <label class="text-sm text-gray-600 sm:col-span-2">
                  Enddatum
                  <input v-model="zeitraum.enddatum" type="date" class="mt-1 w-full rounded border-gray-300 text-sm" />
                </label>
              </div>
            </div>
          </div>
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
