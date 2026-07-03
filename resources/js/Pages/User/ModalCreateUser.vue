<script setup>
import { defineProps, defineEmits, watch } from 'vue';
import FloatLabel from 'primevue/floatlabel';
import InputText from 'primevue/inputtext';
import Password from 'primevue/password';
import MultiSelect from 'primevue/multiselect';
import Divider from 'primevue/divider';
import Modal from '@/Components/ModalForm.vue';   // <---- das fehlte

// Props (Daten von außen übergeben)
const props = defineProps({
  visible: { type: Boolean, default: false },
  newUser: { type: Object, required: true },
  rollen: { type: Array, default: () => [] },
  projekte: { type: Array, default: () => [] },
  standorte: { type: Array, default: () => [] },
})

// Events an die Eltern-Komponente
const emit = defineEmits(['close', 'add-user'])

const ensureProjektZuweisungen = () => {
  if (!Array.isArray(props.newUser.projekt_zuweisungen)) {
    props.newUser.projekt_zuweisungen = [];
  }

  if (props.newUser.projekt_zuweisungen.length === 0) {
    props.newUser.projekt_zuweisungen.push({
      projekt_id: null,
      standort_ids: [],
    });
  }
};

const addProjektRow = () => {
  ensureProjektZuweisungen();
  props.newUser.projekt_zuweisungen.push({
    projekt_id: null,
    standort_ids: [],
  });
};

const removeProjektRow = (index) => {
  props.newUser.projekt_zuweisungen.splice(index, 1);
};

watch(() => props.visible, (visible) => {
  if (visible) {
    ensureProjektZuweisungen();
  }
}, { immediate: true });
</script>

<template>
  <Modal v-if="visible" @close="emit('close')">
    <!-- Header -->
    <template #header>{{$t('Benutzer anlegen')}}</template>

    <!-- Body -->
    <template #body>
      <form @submit.prevent="emit('add-user')">
        <div class="flex flex-col sm:flex-row">
          <div class="mb-4 w-full mx-1">
              <input type="hidden" name="_token" :value="$page.props.csrf_token">

            <FloatLabel variant="on">
              <InputText v-model="newUser.first_name" class="w-full" />
              <label>Vorname</label>
            </FloatLabel>
          </div>
          <div class="mb-4 w-full mx-1">
            <FloatLabel variant="on">
              <InputText v-model="newUser.last_name" class="w-full" />
              <label>Nachname</label>
            </FloatLabel>
          </div>
        </div>
        <div class="mb-4 w-full mx-1 pr-2">
            <FloatLabel variant="on">
              <InputText v-model="newUser.username" class="w-full" />
              <label>Benutzername</label>
            </FloatLabel>
        </div>
        <div class="mb-4 mx-1">
          <FloatLabel variant="on">
            <InputText v-model="newUser.email" class="w-full" />
            <label>E-Mail</label>
          </FloatLabel>
        </div>

        <div class="flex flex-col sm:flex-row">
          <div class="mb-4 w-full mx-1">
            <FloatLabel variant="on">
              <Password v-model="newUser.password" toggleMask class="w-full">
                <template #header>
                  <div class="font-semibold text-xm mb-4">Kennwort eingeben</div>
                </template>
                <template #footer>
                  <Divider />
                  <ul class="pl-2 ml-2 my-0 leading-normal">
                    <li :class="{ 'text-green-500': /[a-z]/.test(newUser.password), 'text-red-500': !/[a-z]/.test(newUser.password) }">
                      <span v-if="/[a-z]/.test(newUser.password)">✔️</span> Mindestens ein Kleinbuchstabe
                    </li>
                    <li :class="{ 'text-green-500': /[A-Z]/.test(newUser.password), 'text-red-500': !/[A-Z]/.test(newUser.password) }">
                      <span v-if="/[A-Z]/.test(newUser.password)">✔️</span> Mindestens ein Großbuchstabe
                    </li>
                    <li :class="{ 'text-green-500': /\d/.test(newUser.password), 'text-red-500': !/\d/.test(newUser.password) }">
                      <span v-if="/\d/.test(newUser.password)">✔️</span> Mindestens eine Ziffer
                    </li>
                    <li :class="{ 'text-green-500': newUser.password.length >= 8, 'text-red-500': newUser.password.length < 8 }">
                      <span v-if="newUser.password.length >= 8">✔️</span> Mindestens 8 Zeichen
                    </li>
                  </ul>
                </template>
              </Password>
              <label>Passwort</label>
            </FloatLabel>
          </div>
          <div class="mb-4 w-full mx-1">
            <FloatLabel variant="on">
              <Password v-model="newUser.password_confirmation" :feedback="false" toggleMask class="w-full" />
              <label>Passwort bestätigen</label>
            </FloatLabel>
          </div>
        </div>
        <div class="mb-4 w-full mx-1">
            <div class="field">
                <FloatLabel variant="on">
                    <MultiSelect v-model="newUser.rollen" :options="rollen" optionLabel="name" optionValue="id" display="chip" class="w-full" />
                    <label>Rollen</label>
                </FloatLabel>
          </div>
        </div>

        <div class="mt-6 border-t pt-4">
          <h3 class="mb-3 text-sm font-semibold text-gray-700">Projekte & Standorte</h3>

          <div
            v-for="(row, index) in newUser.projekt_zuweisungen"
            :key="index"
            class="mb-3 rounded border bg-gray-50 p-3"
          >
            <div class="grid grid-cols-1 gap-3 md:grid-cols-2">
              <div>
                <label class="mb-1 block text-xs font-semibold text-gray-600">Projekt</label>
                <select v-model="row.projekt_id" class="w-full rounded border p-2 text-sm">
                  <option :value="null">Projekt auswahlen</option>
                  <option v-for="projekt in projekte" :key="projekt.id" :value="projekt.id">
                    {{ projekt.name }}
                  </option>
                </select>
              </div>

              <div>
                <label class="mb-1 block text-xs font-semibold text-gray-600">Standorte</label>
                <MultiSelect
                  v-model="row.standort_ids"
                  :options="standorte"
                  optionLabel="name"
                  optionValue="id"
                  display="chip"
                  filter
                  placeholder="Standorte auswahlen"
                  class="w-full"
                />
              </div>
            </div>

            <button
              v-if="newUser.projekt_zuweisungen.length > 1"
              type="button"
              @click="removeProjektRow(index)"
              class="mt-2 text-sm text-red-600"
            >
              Projekt entfernen
            </button>
          </div>

          <button type="button" @click="addProjektRow" class="rounded bg-gray-200 px-3 py-1 text-sm">
            + Projekt hinzufugen
          </button>
        </div>
      </form>
    </template>

    <!-- Footer -->
    <template #footer>
      <button @click="emit('close')" class="mr-2 border border-zbb text-zbb px-4 py-2 rounded">Abbrechen</button>
      <button @click="emit('add-user')" class="bg-zbb text-white px-4 py-2 rounded">Hinzufügen</button>
    </template>
  </Modal>
</template>
