<script setup>
import { computed } from 'vue'
import { useForm } from '@inertiajs/vue3'
import Dialog from 'primevue/dialog'
import Calendar from 'primevue/calendar'
import RadioButton from 'primevue/radiobutton'
import Button from 'primevue/button'
import Swal from 'sweetalert2'
import axios from 'axios'

const props = defineProps({
  visible: Boolean,
  partnerId: String,
  schuljahr: String,
  teil: String,
})

console.log(props.partnerId)
const emit = defineEmits(['update:visible', 'close'])

/**
 * v-model für Dialog sauber lösen
 */
const localVisible = computed({
  get: () => props.visible,
  set: (value) => emit('update:visible', value),
})

/**
 * Form (Inertia)
 */

const form = useForm({
  exportFormat: 'A3',
  termin1: null,
  termin2: null,
  termin3: null,
  termin4: null,
  termin5: null,
  termin6: null,
  termin7: null,
  termin8: null,
  termin9: null,
  termin10: null,
  termin11: null,
  schuleIdInputBibb: props.partnerId,
  schuljahrInputBibb: props.schuljahr,
  teilInputBibb: props.teil,
})

async function handleSubmit() {
 try {
        const response = await axios.post(route('anwesenheitsliste.POBO.bibb.export.word'), form.data(), {
            responseType: 'blob' // WICHTIG
        });

        // Erstelle einen Download-Link im Browser
        const url = window.URL.createObjectURL(new Blob([response.data]));
        const link = document.createElement('a');
        link.href = url;

        // Optional: Dateiname extrahieren oder festlegen
        link.setAttribute('download', 'Anwesenheitsliste.docx');

        document.body.appendChild(link);
        link.click();

        // Aufräumen
        link.remove();
        localVisible.value = false;
        resetForm();
    } catch (error) {
        Swal.fire('Fehler', 'Der Export ist fehlgeschlagen.', 'error');
    }
}
/**
 * Reset
 */
function resetForm() {
  form.reset()
  form.exportFormat = 'A3'
}

/**
 * Close
 */
function onHide() {
  emit('close')
}
</script>


<template>
  <Dialog
    v-model:visible="localVisible"
    :header="$t('Termine anlegen')"
    :modal="true"
    class="w-full md:w-2/3 lg:w-1/2"
    @hide="onHide"
  >
    <form @submit.prevent="handleSubmit" class="space-y-6">

      <!-- Export Format -->
      <div class="flex justify-center gap-8">
        <div class="flex items-center gap-2">
          <RadioButton v-model="form.exportFormat" inputId="a4" value="A4" />
          <label for="a4">A4</label>
        </div>
        <div class="flex items-center gap-2">
          <RadioButton v-model="form.exportFormat" inputId="a3" value="A3" />
          <label for="a3">A3</label>
        </div>
      </div>

      <!-- Termine -->
      <div class="grid grid-cols-2 gap-4">
        <div v-for="index in 10" :key="index">
          <label class="block text-sm font-medium mb-1">
            {{ $t('Termin') }} {{ index }}
            <span class="text-red-500">*</span>
          </label>

          <Calendar
            v-model="form[`termin${index}`]"
            show-icon
            date-format="dd.mm.yy"
            class="w-full"
          />

          <small v-if="form.errors[`termin${index}`]" class="text-red-500">
            {{ form.errors[`termin${index}`] }}
          </small>
        </div>

        <!-- Termin 11 -->
        <div class="col-span-2">
          <label class="block text-sm font-medium mb-1">
            {{ $t('Termin') }} 11:
            {{ $t('Feedbackgespräch') }}
          </label>

          <Calendar
            v-model="form.termin11"
            show-icon
            date-format="dd.mm.yy"
            class="w-full"
          />

          <small v-if="form.errors.termin11" class="text-red-500">
            {{ form.errors.termin11 }}
          </small>
        </div>
      </div>
    </form>

    <!-- Footer -->
    <template #footer>
      <Button
        :label="$t('Abbrechen')"
        icon="pi pi-times"
        class="p-button-text"
        @click="localVisible = false"
      />
      <Button
        :label="$t('Exportieren')"
        icon="pi pi-check"
        class="p-button-primary"
        @click="handleSubmit"
      />
    </template>
  </Dialog>
</template>


