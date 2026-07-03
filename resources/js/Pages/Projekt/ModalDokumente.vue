<script setup>
import { computed, ref, watch } from 'vue';
import axios from 'axios';
import Swal from 'sweetalert2';
import Modal from '@/Components/ModalForm.vue';

const props = defineProps({
  visible: Boolean,
  projekt: {
    type: Object,
    default: null,
  },
  dokumente: {
    type: Array,
    default: () => [],
  },
});

const emit = defineEmits(['close', 'updated']);

const saving = ref(false);
const suche = ref('');
const state = ref({});

const fillState = () => {
  const assigned = new Map(
    (props.projekt?.dokumente || []).map((dokument) => [dokument.id, dokument])
  );

  state.value = {};
  (props.dokumente || []).forEach((dokument) => {
    const projektDokument = assigned.get(dokument.id);
    state.value[dokument.id] = {
      selected: Boolean(projektDokument),
      gruppen_export: Boolean(projektDokument?.pivot?.gruppen_export ?? true),
      serienbrief: Boolean(projektDokument?.pivot?.serienbrief ?? false),
    };
  });
};

watch(() => props.projekt, fillState, { immediate: true });
watch(() => props.visible, (visible) => {
  if (visible) {
    fillState();
  }
});

const gefilterteDokumente = computed(() => {
  const term = suche.value.trim().toLowerCase();
  if (!term) {
    return props.dokumente || [];
  }

  return (props.dokumente || []).filter((dokument) =>
    [dokument.name, dokument.typ, dokument.version, dokument.beschreibung]
      .filter(Boolean)
      .some((value) => String(value).toLowerCase().includes(term))
  );
});

const setSelected = (dokumentId, selected) => {
  state.value[dokumentId].selected = selected;

  if (!selected) {
    state.value[dokumentId].gruppen_export = false;
    state.value[dokumentId].serienbrief = false;
  }
};

const setSerienbrief = (dokumentId, serienbrief) => {
  state.value[dokumentId].serienbrief = serienbrief;

  if (serienbrief) {
    state.value[dokumentId].selected = true;
    state.value[dokumentId].gruppen_export = true;
  }
};

const save = async () => {
  if (!props.projekt?.id) {
    return;
  }

  saving.value = true;

  try {
    const dokumente = Object.entries(state.value)
      .filter(([, value]) => value.selected)
      .map(([id, value]) => ({
        id: Number(id),
        gruppen_export: Boolean(value.gruppen_export || value.serienbrief),
        serienbrief: Boolean(value.serienbrief),
      }));

    const response = await axios.put(route('projekt.dokumente.update', props.projekt.id), {
      dokumente,
    });

    Swal.fire('Erfolg', 'Export-Vorlagen wurden gespeichert.', 'success');
    emit('updated', response.data.projekt);
    emit('close');
  } catch (error) {
    Swal.fire(
      'Fehler',
      error.response?.data?.message || error.response?.data?.error || 'Vorlagen konnten nicht gespeichert werden.',
      'error'
    );
  } finally {
    saving.value = false;
  }
};
</script>

<template>
  <Modal v-if="visible" @close="emit('close')">
    <template #header>
      <h2 class="text-lg font-bold text-gray-600">Export-Vorlagen</h2>
    </template>

    <template #body>
      <div class="space-y-4">
        <div>
          <div class="text-sm font-semibold text-gray-700">{{ projekt?.name }}</div>
          <input
            v-model="suche"
            type="text"
            class="mt-2 w-full rounded border-gray-300 text-sm"
            placeholder="Dokument suchen..."
          />
        </div>

        <div class="max-h-[60vh] overflow-y-auto rounded border border-gray-200">
          <table class="w-full text-sm">
            <thead class="sticky top-0 bg-gray-100 text-left text-gray-600">
              <tr>
                <th class="px-3 py-2">Dokument</th>
                <th class="px-3 py-2 text-center">Projekt</th>
                <th class="px-3 py-2 text-center">Gruppen-Export</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="dokument in gefilterteDokumente" :key="dokument.id" class="border-t">
                <td class="px-3 py-2">
                  <div class="font-medium text-gray-800">{{ dokument.name }}</div>
                  <div class="text-xs text-gray-500">
                    {{ dokument.typ }} <span v-if="dokument.kontext">| {{ dokument.kontext }}</span> <span v-if="dokument.version">| {{ dokument.version }}</span>
                  </div>
                </td>
                <td class="px-3 py-2 text-center">
                  <input
                    type="checkbox"
                    class="rounded border-gray-300 text-zbb focus:ring-zbb"
                    :checked="state[dokument.id]?.selected"
                    @change="setSelected(dokument.id, $event.target.checked)"
                  />
                </td>
                <td class="px-3 py-2 text-center">
                  <input
                    type="checkbox"
                    class="rounded border-gray-300 text-zbb focus:ring-zbb disabled:opacity-40"
                    :checked="state[dokument.id]?.serienbrief"
                    @change="setSerienbrief(dokument.id, $event.target.checked)"
                  />
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </template>

    <template #footer>
      <button
        type="button"
        class="mx-2 rounded bg-zbb px-4 py-2 text-white disabled:opacity-60"
        :disabled="saving"
        @click="save"
      >
        {{ saving ? 'Speichert...' : 'Speichern' }}
      </button>
      <button type="button" class="mx-2 rounded border border-zbb px-4 py-2 text-zbb" @click="emit('close')">
        Abbrechen
      </button>
    </template>
  </Modal>
</template>
