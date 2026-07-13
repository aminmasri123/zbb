<script setup>
import { defineProps, defineEmits, ref, watch } from 'vue';
import FloatLabel from 'primevue/floatlabel';
import InputText from 'primevue/inputtext';
import Select from 'primevue/select';
import Modal from '@/Components/ModalForm.vue';   // <---- das fehlte

// Props (Daten von außen übergeben)
const props = defineProps({
  visible: { type: Boolean, default: false },
  activeProject: { type: Object, default: null },
  standorte: Array,
  defaultProjekt: { type: Number, default: null },
})


// Events an die Eltern-Komponente
const emit = defineEmits(["close", "add-teilnehmer"]);

let form = ref({
  vorname: "",
  nachname: "",
  geschlecht: "",
  geburtsdatum: "",
  projekt: props.defaultProjekt,
  standort: "",
});
const submitForm = () => {
  emit("add-teilnehmer", form.value); // sende die Daten zurück an Parent
  resetForm();
};

const close = () => {
  emit("close");
  resetForm();
};

const resetForm = () => {
  form.value = {
    vorname: "",
    nachname: "",
    geschlecht: "",
    geburtsdatum: "",
    projekt: props.defaultProjekt,
    standort: "",
  };
};

watch(() => props.defaultProjekt, () => resetForm());
</script>

<template>
  <Modal v-if="visible" @close="close">
    <!-- Header -->
    <template #header>{{$t('Benutzer anlegen')}}</template>

    <!-- Body -->
    <template #body>
      <form @submit.prevent="submitForm">
        <div class="flex flex-col sm:flex-row">
          <div class="mb-4 w-full mx-1">
              <input type="hidden" name="_token" :value="$page.props.csrf_token">

            <FloatLabel variant="on">
              <InputText v-model="form.vorname" class="w-full" />
              <label>Vorname</label>
            </FloatLabel>
          </div>
          <div class="mb-4 w-full mx-1">
            <FloatLabel variant="on">
              <InputText v-model="form.nachname" class="w-full" />
              <label>Nachname</label>
            </FloatLabel>
          </div>
        </div>

           <div class="mb-4 w-full mx-1">
            <FloatLabel variant="on">

                <Select
                    v-model="form.geschlecht"
                    :options="[
                        { label: 'Weiblich', value: 'w' },
                        { label: 'Männlich', value: 'm' },
                        { label: 'Divers', value: 'd' }
                    ]"
                    optionLabel="label"
                    optionValue="value"
                    class="w-full"
                    placeholder="Geschlecht"
                />
                <label>Geschlecht</label>
            </FloatLabel>
          </div>
          <div class="mb-4 w-full mx-1">
            <label class="block text-sm text-gray-600">
              Geburtsdatum
              <span v-if="activeProject?.rules?.participant_birthdate_required" class="text-red-600">*</span>
              <input
                v-model="form.geburtsdatum"
                type="date"
                class="mt-1 w-full rounded border-gray-300"
                :required="Boolean(activeProject?.rules?.participant_birthdate_required)"
              />
              <span
                v-if="activeProject?.rules?.participant_min_age !== null || activeProject?.rules?.participant_max_age !== null"
                class="mt-1 block text-xs text-gray-500"
              >
                Zulässiges Alter:
                {{ activeProject?.rules?.participant_min_age ?? '0' }}
                bis
                {{ activeProject?.rules?.participant_max_age ?? 'unbegrenzt' }}
                Jahre
              </span>
            </label>
          </div>
        <div class="mb-4 w-full mx-1">
            <FloatLabel variant="on">
                <Select
                    v-model="form.standort"
                    :options="props.standorte"
                    optionLabel="name"
                    optionValue="id"
                    class="w-full"
                />
                <label>Standort wählen</label>
            </FloatLabel>
          </div>

          <div class="mb-4 w-full mx-1 rounded border border-gray-200 bg-gray-50 px-3 py-2 text-sm text-gray-700">
            <span class="block text-xs text-gray-500">Aktives Projekt</span>
            <span class="font-medium">{{ activeProject?.name || 'Kein Projekt gewählt' }}</span>
          </div>

      </form>
    </template>

    <!-- Footer -->
    <template #footer>
      <button @click="close" class="mr-2 border border-zbb text-zbb px-4 py-2 rounded">Abbrechen</button>
      <button @click="submitForm" class="bg-zbb text-white px-4 py-2 rounded">Hinzufügen</button>
    </template>
  </Modal>
</template>
