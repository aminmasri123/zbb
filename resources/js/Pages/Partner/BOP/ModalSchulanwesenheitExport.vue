<script setup>
import Modal from '@/Components/ModalForm.vue';
import { computed, reactive } from 'vue';
import Swal from 'sweetalert2';

const props = defineProps({
  visible: Boolean,
  partnerId: Number,
  schuljahr: [String, Number],
  teil: [String, Number],
});
const emit = defineEmits(['close']);
const today = new Date().toISOString().slice(0, 10);
const form = reactive({ umfang: 'tag', von: today, bis: today });
const isDay = computed(() => form.umfang === 'tag');

function exportieren() {
  const bis = isDay.value ? form.von : form.bis;
  if (!form.von || !bis || bis < form.von) {
    Swal.fire('Zeitraum prüfen', 'Bitte einen gültigen Tag oder Zeitraum auswählen.', 'warning');
    return;
  }
  const element = document.createElement('form');
  element.method = 'POST';
  element.action = route('export.schulanwesenheit.excel', {
    schulId: props.partnerId,
    schuljahr: props.schuljahr,
    teil: props.teil,
  });
  const fields = {
    _token: document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
    von: form.von,
    bis,
  };
  Object.entries(fields).forEach(([name, value]) => {
    const input = document.createElement('input');
    input.type = 'hidden';
    input.name = name;
    input.value = value;
    element.appendChild(input);
  });
  document.body.appendChild(element);
  element.submit();
  element.remove();
  emit('close');
}
</script>

<template>
  <Modal v-if="visible" @close="emit('close')">
    <template #header>Anwesenheit der Schule exportieren</template>
    <template #body>
      <div class="space-y-4">
        <p class="text-sm text-gray-600">Der Export übernimmt die tatsächlich in den Gruppen gespeicherten Anwesenheiten, Abwesenheiten, Zeiten, Verspätungen und Bemerkungen.</p>
        <div>
          <label class="mb-1 block text-sm font-semibold text-gray-700">Umfang</label>
          <select v-model="form.umfang" class="w-full rounded-md border-gray-300 text-sm">
            <option value="tag">Einzelner Tag</option>
            <option value="zeitraum">Zeitraum von–bis</option>
          </select>
        </div>
        <div :class="isDay ? '' : 'grid grid-cols-2 gap-3'">
          <div>
            <label class="mb-1 block text-sm font-semibold text-gray-700">{{ isDay ? 'Tag' : 'Von' }}</label>
            <input v-model="form.von" type="date" class="w-full rounded-md border-gray-300 text-sm" />
          </div>
          <div v-if="!isDay">
            <label class="mb-1 block text-sm font-semibold text-gray-700">Bis</label>
            <input v-model="form.bis" type="date" :min="form.von" class="w-full rounded-md border-gray-300 text-sm" />
          </div>
        </div>
      </div>
    </template>
    <template #footer>
      <button type="button" class="rounded bg-zbb px-5 py-2 text-sm font-semibold text-white" @click="exportieren">Excel exportieren</button>
      <button type="button" class="rounded border px-5 py-2 text-sm" @click="emit('close')">Abbrechen</button>
    </template>
  </Modal>
</template>
