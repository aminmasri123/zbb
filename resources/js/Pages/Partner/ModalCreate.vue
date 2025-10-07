<script setup>
import Modal from '@/Components/ModalForm.vue';
import { ref } from 'vue';
import Swal from 'sweetalert2';
import FloatLabel from 'primevue/floatlabel';
import InputText from 'primevue/inputtext';
import Textarea from 'primevue/textarea';
import Dropdown from 'primevue/dropdown';

const props = defineProps({
  visible: Boolean,
});

const emit = defineEmits(['close', 'add-partner']);

let newPartner = ref({
  name: '',
  typ: '',
  beschreibung: '',
  ansprechpartner: [
    { vorname: '', nachname: '', geschlecht: '', typ: '' }
  ],
});

const resetForm = () => {
  newPartner.value = {
    name: '',
    typ: '',
    beschreibung: '',
    ansprechpartner: [{ vorname: '', nachname: '', geschlecht: '', typ: '' }],
  };
};

const addAnsprechpartner = () => {
  newPartner.value.ansprechpartner.push({ vorname: '', nachname: '', geschlecht: '', typ: '' });
};

const removeAnsprechpartner = (index) => {
  newPartner.value.ansprechpartner.splice(index, 1);
};

const save = () => {
  if (!newPartner.value.name) {
    Swal.fire('Fehler', 'Bitte Partnername eingeben!', 'error');
    return;
  }
  emit('add-partner', { ...newPartner.value });
  resetForm();
  emit('close');
};
</script>

<template>
  <!-- Modal mit Scroll für gesamten Inhalt -->
  <Modal v-if="visible" @close="emit('close')">
    <template #header>
      <h2 class="text-lg font-bold text-gray-600">Partner anlegen</h2>
    </template>

    <template #body>
      <!-- Gesamter Inhalt scrollbar -->
      <div class="max-h-[70vh] overflow-y-auto pr-3">
        <!-- Partnerfelder -->
        <div class="mt-1 mb-2">
          <FloatLabel variant="on">
            <InputText id="name" v-model="newPartner.name" class="w-full" />
            <label for="name">Partnername</label>
          </FloatLabel>
        </div>

        <div class="mb-2">
          <FloatLabel variant="on">
            <InputText id="typ" v-model="newPartner.typ" class="w-full" />
            <label for="typ">Typ (z. B. Lieferant, Kunde …)</label>
          </FloatLabel>
        </div>

        <div class="mb-2">
          <FloatLabel variant="on">
            <Textarea
              id="beschreibung"
              v-model="newPartner.beschreibung"
              rows="4"
              class="w-full"
              style="resize: none"
            />
            <label for="beschreibung">Beschreibung</label>
          </FloatLabel>
        </div>

        <hr class="my-4" />

        <!-- Ansprechpartner -->
        <div class="flex justify-between items-center mb-2">
          <h3 class="text-md font-semibold text-gray-600">Ansprechpartner</h3>
          <span class="text-sm text-gray-500">
            Anzahl: {{ newPartner.ansprechpartner.length }}
          </span>
        </div>

        <div v-for="(person, index) in newPartner.ansprechpartner" :key="index" class="border p-3 rounded mb-3 bg-gray-50">
          <h4 class="font-medium text-gray-700 mb-2">
            Ansprechpartner {{ index + 1 }}
          </h4>

          <div class="grid grid-cols-2 gap-2 mb-2">
            <FloatLabel variant="on">
              <InputText class="w-full" v-model="person.vorname" />
              <label>Vorname</label>
            </FloatLabel>

            <FloatLabel variant="on">
              <InputText class="w-full" v-model="person.nachname" />
              <label>Nachname</label>
            </FloatLabel>
          </div>

          <div class="grid grid-cols-2 gap-2">
            <FloatLabel variant="on">
              <Dropdown class="w-full "
                :options="['männlich', 'weiblich', 'divers']"
                v-model="person.geschlecht"
              />
              <label>Geschlecht</label>
            </FloatLabel>

            <FloatLabel variant="on">
              <InputText class="w-full" v-model="person.typ" />
              <label>Typ (z. B. Projektleiter... )</label>
            </FloatLabel>
          </div>

          <div class="flex justify-end mt-2">
            <button
              v-if="newPartner.ansprechpartner.length > 1"
              class="text-red-500 text-sm hover:underline"
              @click="removeAnsprechpartner(index)"
            >
              Ansprechpartner {{ index + 1 }} entfernen
            </button>
          </div>
        </div>

        <div class="flex justify-end">
          <button
            @click="addAnsprechpartner"
            class="bg-gray-100 border border-gray-300 text-gray-600 px-3 py-1 rounded text-sm hover:bg-gray-200"
          >
            + Ansprechpartner hinzufügen
          </button>
        </div>
      </div>
    </template>

    <template #footer>
      <button @click="save" class="mx-2 bg-zbb text-white px-4 py-2 rounded">Speichern</button>
      <button @click="emit('close')" class="mx-2 border border-zbb text-zbb px-4 py-2 rounded">Abbrechen</button>
    </template>
  </Modal>
</template>
