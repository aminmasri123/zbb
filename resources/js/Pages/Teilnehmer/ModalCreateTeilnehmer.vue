<script setup>
import { computed, defineProps, defineEmits, ref, watch } from 'vue';
import FloatLabel from 'primevue/floatlabel';
import InputText from 'primevue/inputtext';
import Select from 'primevue/select';
import Modal from '@/Components/ModalForm.vue';   // <---- das fehlte

// Props (Daten von außen übergeben)
const props = defineProps({
  visible: { type: Boolean, default: false },
  activeProject: { type: Object, default: null },
  standorte: Array,
  schools: { type: Array, default: () => [] },
  defaultProjekt: { type: Number, default: null },
})


// Events an die Eltern-Komponente
const emit = defineEmits(["close", "add-teilnehmer"]);
const validationMessage = ref("");
const isBopProject = computed(() => String(props.activeProject?.name || '').toUpperCase().includes('BOP'));
const schoolContextEnabled = computed(() => isBopProject.value || props.activeProject?.rules?.participant_parts_enabled);

let form = ref({
  vorname: "",
  nachname: "",
  geschlecht: "",
  geburtsdatum: "",
  projekt: props.defaultProjekt,
  standort: "",
  adresse: {
    strasse: "",
    hausnummer: "",
    plz: "",
    stadt: "",
    land: "Deutschland",
    zusatzinfo: "",
  },
  schulzuordnung: {
    schule_id: null,
    schuljahr: "",
    klasse: "",
    teil: "1",
  },
});
const submitForm = () => {
  validationMessage.value = "";

  if (!form.value.vorname || !form.value.nachname || !form.value.geschlecht || !form.value.standort) {
    validationMessage.value = "Bitte füllen Sie alle Pflichtfelder aus.";
    return;
  }

  if (
    props.activeProject?.rules?.participant_address_enabled
    && (!form.value.adresse.strasse
      || !form.value.adresse.hausnummer
      || !form.value.adresse.plz
      || !form.value.adresse.stadt)
  ) {
    validationMessage.value = "Bitte füllen Sie Straße, Hausnummer, PLZ und Ort aus.";
    return;
  }

  if (
    schoolContextEnabled.value
    && (!form.value.schulzuordnung.schule_id
      || !form.value.schulzuordnung.schuljahr
      || !form.value.schulzuordnung.klasse)
  ) {
    validationMessage.value = "Bitte wählen Sie eine Schule und geben Sie Schuljahr und Klasse an.";
    return;
  }

  if (props.activeProject?.rules?.participant_parts_enabled && !form.value.schulzuordnung.teil) {
    validationMessage.value = "Bitte geben Sie an, an welchem Teil der Teilnehmer teilnimmt.";
    return;
  }

  emit("add-teilnehmer", JSON.parse(JSON.stringify(form.value)));
};

const close = () => {
  emit("close");
  resetForm();
};

const resetForm = () => {
  validationMessage.value = "";
  form.value = {
    vorname: "",
    nachname: "",
    geschlecht: "",
    geburtsdatum: "",
    projekt: props.defaultProjekt,
    standort: "",
    adresse: {
      strasse: "",
      hausnummer: "",
      plz: "",
      stadt: "",
      land: "Deutschland",
      zusatzinfo: "",
    },
    schulzuordnung: {
      schule_id: null,
      schuljahr: "",
      klasse: "",
      teil: "1",
    },
  };
};

watch(() => props.defaultProjekt, () => resetForm());
watch(() => props.visible, (visible) => {
  if (!visible) resetForm();
});
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

          <fieldset
            v-if="activeProject?.rules?.participant_address_enabled"
            class="mb-4 w-full rounded border border-gray-200 p-4"
          >
            <legend class="px-2 font-semibold text-gray-700">Adresse</legend>
            <div class="grid gap-4 sm:grid-cols-3">
              <FloatLabel variant="on" class="sm:col-span-2">
                <InputText v-model="form.adresse.strasse" class="w-full" required />
                <label>Straße *</label>
              </FloatLabel>
              <FloatLabel variant="on">
                <InputText v-model="form.adresse.hausnummer" class="w-full" required />
                <label>Hausnummer *</label>
              </FloatLabel>
              <FloatLabel variant="on">
                <InputText v-model="form.adresse.plz" class="w-full" required />
                <label>PLZ *</label>
              </FloatLabel>
              <FloatLabel variant="on" class="sm:col-span-2">
                <InputText v-model="form.adresse.stadt" class="w-full" required />
                <label>Ort *</label>
              </FloatLabel>
              <FloatLabel variant="on" class="sm:col-span-3">
                <InputText v-model="form.adresse.land" class="w-full" />
                <label>Land</label>
              </FloatLabel>
              <FloatLabel variant="on" class="sm:col-span-3">
                <InputText v-model="form.adresse.zusatzinfo" class="w-full" />
                <label>Adresszusatz (optional)</label>
              </FloatLabel>
            </div>
          </fieldset>

          <fieldset
            v-if="schoolContextEnabled"
            class="mb-4 w-full rounded border border-gray-200 p-4"
          >
            <legend class="px-2 font-semibold text-gray-700">Schulzuordnung</legend>
            <p class="mb-4 text-xs text-gray-500">Die Zuordnung wird für diesen Teilnehmer und dieses Schuljahr gespeichert.</p>
            <div class="grid gap-4 sm:grid-cols-2">
              <FloatLabel v-if="activeProject?.rules?.participant_parts_enabled" variant="on" class="sm:col-span-2">
                <Select
                  v-model="form.schulzuordnung.schule_id"
                  :options="props.schools"
                  optionLabel="name"
                  optionValue="id"
                  class="w-full"
                />
                <label>Schule *</label>
              </FloatLabel>
              <FloatLabel variant="on">
                <InputText v-model="form.schulzuordnung.schuljahr" class="w-full" placeholder="z. B. 2026/2027" />
                <label>Schuljahr *</label>
              </FloatLabel>
              <FloatLabel variant="on">
                <InputText v-model="form.schulzuordnung.klasse" class="w-full" placeholder="z. B. 8.1" />
                <label>Klasse *</label>
              </FloatLabel>
              <FloatLabel variant="on" class="sm:col-span-2">
                <InputText v-model="form.schulzuordnung.teil" class="w-full" placeholder="z. B. 1 oder 2" />
                <label>Teil des Teilnehmers *</label>
              </FloatLabel>
            </div>
          </fieldset>

          <p v-if="validationMessage" class="mb-2 text-sm text-red-600">
            {{ validationMessage }}
          </p>

      </form>
    </template>

    <!-- Footer -->
    <template #footer>
      <button @click="close" class="mr-2 border border-zbb text-zbb px-4 py-2 rounded">Abbrechen</button>
      <button @click="submitForm" class="bg-zbb text-white px-4 py-2 rounded">Hinzufügen</button>
    </template>
  </Modal>
</template>
