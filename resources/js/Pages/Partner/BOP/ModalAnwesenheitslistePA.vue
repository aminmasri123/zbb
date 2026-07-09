
<script setup>
import { ref, computed, watch } from 'vue'
import { useForm } from '@inertiajs/vue3'
import Modal from '@/Components/ModalForm.vue'
import Swal from 'sweetalert2'
import axios from 'axios'

const props = defineProps({
  visible: Boolean,
  partnerId: String,
  schuljahr: String,
  teil: String,
  klasse: String,
  klassen: { type: Array, default: () => [] },
})

const emit = defineEmits(['close'])
const close = () => emit('close')

// Formular initialisieren
const form = useForm({
  startDate: '',
  endDate: '',
  exportMode: 'alle',
  schuleId: props.partnerId,
  schuljahr: props.schuljahr,
  teil: props.teil,
  klasse: props.klasse || '',
})

// Watcher: Props bei Öffnen ins Formular übernehmen
watch(
  () => [props.visible, props.klasse],
  ([visible, klasse]) => {
    if (visible) {
      form.startDate = ''
      form.endDate = ''
      form.exportMode = klasse ? 'klasse' : 'alle'
      form.schuleId = props.partnerId
      form.schuljahr = props.schuljahr
      form.teil = props.teil
      form.klasse = klasse || ''
      form.clearErrors()
    }
  },
  { immediate: true }
)

const loading = ref(false)

// Validierung: nur Start- und Enddatum prüfen
const isValid = computed(() =>
  form.startDate && form.endDate && (form.exportMode === 'alle' || form.klasse)
)

// Speichern
const save = async () => {
  if (!isValid.value) return;
  loading.value = true;

  try {
    const response = await axios.post(route('anwesenheitsliste.PA.export.word'), form, {
      responseType: 'blob' // WICHTIG
    });

    const url = window.URL.createObjectURL(new Blob([response.data]));
    const link = document.createElement('a');
    link.href = url;
    link.setAttribute('download', form.exportMode === 'alle'
      ? 'Anwesenheitslisten_PA_alle_Klassen.zip'
      : `Anwesenheitsliste_PA_${form.klasse}.docx`
    );
    document.body.appendChild(link);
    link.click();
    link.remove();
    window.URL.revokeObjectURL(url);
    close();

  } catch (error) {
    Swal.fire('Fehler', await exportErrorMessage(error), 'error');
  } finally {
    loading.value = false;
  }
};

const exportErrorMessage = async (error) => {
  const fallback = 'Export fehlgeschlagen';
  const data = error.response?.data;

  if (data instanceof Blob) {
    const text = await data.text();

    try {
      const json = JSON.parse(text);
      return json.message || json.error || fallback;
    } catch {
      return text || fallback;
    }
  }

  return data?.message || data?.error || fallback;
};
</script>


<template>
  <Modal v-if="visible" @close="close">
    <!-- Header -->
    <template #header>{{ $t('Anwesenheitsliste für die PA exportieren') }}</template>

    <!-- Body -->
    <template #body>
      <form @submit.prevent="save" class="space-y-6">
        <div class="grid grid-cols-2 gap-4 mb-6">
          <label class="block rounded border border-gray-200 p-3">
            <input
              v-model="form.exportMode"
              type="radio"
              value="alle"
              class="mr-2 text-zbb focus:ring-zbb"
            />
            <span class="text-sm font-medium text-gray-700">Alle Klassen exportieren</span>
          </label>
          <label class="block rounded border border-gray-200 p-3">
            <input
              v-model="form.exportMode"
              type="radio"
              value="klasse"
              class="mr-2 text-zbb focus:ring-zbb"
            />
            <span class="text-sm font-medium text-gray-700">Einzelne Klasse exportieren</span>
          </label>
        </div>

        <div v-if="form.exportMode === 'klasse'" class="mb-6">
          <label class="block text-sm font-medium text-gray-700 mb-2">
            Klasse <span class="text-red-500">*</span>
          </label>
          <select
            v-model="form.klasse"
            required
            class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-1 focus:ring-zbb focus:border-zbb transition-colors"
          >
            <option value="" disabled>Klasse auswählen</option>
            <option v-for="klasseOption in klassen" :key="klasseOption" :value="klasseOption">
              {{ klasseOption }}
            </option>
          </select>
        </div>

        <!-- Datum -->
        <div class="grid grid-cols-2 gap-4 mb-6">
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-2">
              {{ $t('Startdatum') }} <span class="text-red-500">*</span>
            </label>
            <input
              type="date"
              v-model="form.startDate"
              required
              class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-1 focus:ring-zbb focus:border-zbb transition-colors"
            />
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-2">
              {{ $t('Enddatum') }} <span class="text-red-500">*</span>
            </label>
            <input
              type="date"
              v-model="form.endDate"
              required
              class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-1 focus:ring-zbb focus:border-zbb transition-colors"
            />
          </div>
        </div>
      </form>
    </template>

    <!-- Footer -->
    <template #footer>
      <button
        @click="save"
        :disabled="loading || !isValid"
        class="bg-zbb text-white px-4 py-2 rounded disabled:opacity-50"
      >
        {{ loading ? $t('Speichern...') : $t('Speichern') }}
      </button>
      <button @click="close" class="border px-4 py-2 rounded">
        {{ $t('Abbrechen') }}
      </button>
    </template>
  </Modal>
</template>
