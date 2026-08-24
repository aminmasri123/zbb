<script setup>
import { defineProps, defineEmits, watch } from 'vue';
import FloatLabel from 'primevue/floatlabel';
import InputText from 'primevue/inputtext';
import Password from 'primevue/password';
import MultiSelect from 'primevue/multiselect';
import Divider from 'primevue/divider';
import Modal from '@/Components/ModalForm.vue';   // <---- das fehlte
import {
  arbeitsbereichRoomsForProject,
  bueroRoomsForProject,
  emptyRoomAssignmentFields,
  ensureProjectRoomAssignmentFields,
  normalizeDefaultRoom,
  resetRoomAssignments,
  roomLabel,
  selectedRoomsForRow,
} from '@/utils/projectRoomAssignments';

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
      bereich_ids: [],
      default_bereich_id: null,
      ...emptyRoomAssignmentFields(),
    });
  }

  props.newUser.projekt_zuweisungen.forEach((row) => {
    row.bereich_ids = Array.isArray(row.bereich_ids) ? row.bereich_ids : [];
    row.default_bereich_id = row.default_bereich_id ?? null;
    ensureProjectRoomAssignmentFields(row);
  });
};

const addProjektRow = () => {
  ensureProjektZuweisungen();
  props.newUser.projekt_zuweisungen.push({
    projekt_id: null,
    standort_ids: [],
    bereich_ids: [],
    default_bereich_id: null,
    ...emptyRoomAssignmentFields(),
  });
};

const removeProjektRow = (index) => {
  props.newUser.projekt_zuweisungen.splice(index, 1);
};

const bereicheForProjekt = (projektId) => {
  return props.projekte.find((projekt) => Number(projekt.id) === Number(projektId))?.bereiche || [];
};

const selectedBereicheForRow = (row) => {
  const selectedIds = (row.bereich_ids || []).map((id) => Number(id));

  return bereicheForProjekt(row.projekt_id).filter((bereich) => selectedIds.includes(Number(bereich.id)));
};

const resetProjektBereiche = (row) => {
  row.bereich_ids = [];
  row.default_bereich_id = null;
};

const resetProjektAssignments = (row) => {
  resetProjektBereiche(row);
  resetRoomAssignments(row);
};

const normalizeDefaultBereich = (row) => {
  const selectedIds = (row.bereich_ids || []).map((id) => Number(id));

  if (selectedIds.length === 1) {
    row.default_bereich_id = selectedIds[0];
    return;
  }

  if (!selectedIds.includes(Number(row.default_bereich_id))) {
    row.default_bereich_id = null;
  }
};

const selectedBueroRaeumeForRow = (row) =>
  selectedRoomsForRow(props.projekte, row, 'buero_raum_ids', bueroRoomsForProject);

const selectedArbeitsbereichRaeumeForRow = (row) =>
  selectedRoomsForRow(props.projekte, row, 'arbeitsbereich_raum_ids', arbeitsbereichRoomsForProject);

watch(() => props.visible, (visible) => {
  if (visible) {
    ensureProjektZuweisungen();
  }
}, { immediate: true });

watch(() => props.newUser.account_setup_method, (method) => {
  if (method === 'email_invitation') {
    props.newUser.password = '';
    props.newUser.password_confirmation = '';
  }
});
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

        <fieldset class="mb-5 mx-1">
          <legend class="mb-2 text-sm font-semibold text-gray-700">Zugang einrichten</legend>
          <div class="grid gap-3 sm:grid-cols-2">
            <label
              class="cursor-pointer rounded-lg border p-3"
              :class="newUser.account_setup_method === 'email_invitation' ? 'border-zbb bg-orange-50' : 'border-gray-200'"
            >
              <span class="flex items-start gap-2">
                <input v-model="newUser.account_setup_method" type="radio" value="email_invitation" class="mt-1 text-zbb" />
                <span>
                  <strong class="block text-sm">Einladung per E-Mail</strong>
                  <small class="text-gray-600">Empfohlen: Der Mitarbeiter legt sein Passwort über einen sicheren Einmal-Link selbst fest.</small>
                </span>
              </span>
            </label>
            <label
              class="cursor-pointer rounded-lg border p-3"
              :class="newUser.account_setup_method === 'manual' ? 'border-zbb bg-orange-50' : 'border-gray-200'"
            >
              <span class="flex items-start gap-2">
                <input v-model="newUser.account_setup_method" type="radio" value="manual" class="mt-1 text-zbb" />
                <span>
                  <strong class="block text-sm">Passwort selbst vergeben</strong>
                  <small class="text-gray-600">Sie bestimmen jetzt ein Startpasswort und übergeben es dem Mitarbeiter separat.</small>
                </span>
              </span>
            </label>
          </div>
        </fieldset>

        <div v-if="newUser.account_setup_method === 'manual'" class="flex flex-col sm:flex-row">
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
                    <li :class="{ 'text-green-500': newUser.password.length >= 10, 'text-red-500': newUser.password.length < 10 }">
                      <span v-if="newUser.password.length >= 10">✔️</span> Mindestens 10 Zeichen
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
                    <MultiSelect v-model="newUser.rollen" :options="rollen" optionLabel="name" optionValue="id" display="chip" filter filterPlaceholder="Rolle suchen …" class="w-full" />
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
                <select v-model="row.projekt_id" @change="resetProjektAssignments(row)" class="w-full rounded border p-2 text-sm">
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

              <div>
                <label class="mb-1 block text-xs font-semibold text-gray-600">Bereiche</label>
                <MultiSelect
                  v-model="row.bereich_ids"
                  :options="bereicheForProjekt(row.projekt_id)"
                  optionLabel="name"
                  optionValue="id"
                  display="chip"
                  filter
                  placeholder="Bereiche auswahlen"
                  class="w-full"
                  :disabled="!row.projekt_id"
                  @change="normalizeDefaultBereich(row)"
                />
              </div>

              <div>
                <label class="mb-1 block text-xs font-semibold text-gray-600">Standardbereich</label>
                <select
                  v-model="row.default_bereich_id"
                  class="w-full rounded border p-2 text-sm disabled:bg-gray-100"
                  :disabled="selectedBereicheForRow(row).length === 0"
                >
                  <option :value="null">Kein Standard</option>
                  <option
                    v-for="bereich in selectedBereicheForRow(row)"
                    :key="bereich.id"
                    :value="bereich.id"
                  >
                    {{ bereich.name }}
                  </option>
                </select>
              </div>

              <div>
                <label class="mb-1 block text-xs font-semibold text-gray-600">Büros</label>
                <MultiSelect
                  v-model="row.buero_raum_ids"
                  :options="bueroRoomsForProject(props.projekte, row.projekt_id)"
                  :optionLabel="roomLabel"
                  optionValue="id"
                  display="chip"
                  filter
                  placeholder="Büros auswählen"
                  class="w-full"
                  :disabled="!row.projekt_id"
                  @change="normalizeDefaultRoom(row, 'buero_raum_ids', 'default_buero_raum_id')"
                />
              </div>

              <div>
                <label class="mb-1 block text-xs font-semibold text-gray-600">Standardbüro</label>
                <select
                  v-model="row.default_buero_raum_id"
                  class="w-full rounded border p-2 text-sm disabled:bg-gray-100"
                  :disabled="selectedBueroRaeumeForRow(row).length === 0"
                >
                  <option :value="null">Kein Standard</option>
                  <option
                    v-for="raum in selectedBueroRaeumeForRow(row)"
                    :key="raum.id"
                    :value="raum.id"
                  >
                    {{ roomLabel(raum) }}
                  </option>
                </select>
              </div>

              <div>
                <label class="mb-1 block text-xs font-semibold text-gray-600">Arbeitsbereiche</label>
                <MultiSelect
                  v-model="row.arbeitsbereich_raum_ids"
                  :options="arbeitsbereichRoomsForProject(props.projekte, row.projekt_id)"
                  :optionLabel="roomLabel"
                  optionValue="id"
                  display="chip"
                  filter
                  placeholder="Arbeitsbereiche auswählen"
                  class="w-full"
                  :disabled="!row.projekt_id"
                  @change="normalizeDefaultRoom(row, 'arbeitsbereich_raum_ids', 'default_arbeitsbereich_raum_id')"
                />
              </div>

              <div>
                <label class="mb-1 block text-xs font-semibold text-gray-600">Standard-Arbeitsbereich</label>
                <select
                  v-model="row.default_arbeitsbereich_raum_id"
                  class="w-full rounded border p-2 text-sm disabled:bg-gray-100"
                  :disabled="selectedArbeitsbereichRaeumeForRow(row).length === 0"
                >
                  <option :value="null">Kein Standard</option>
                  <option
                    v-for="raum in selectedArbeitsbereichRaeumeForRow(row)"
                    :key="raum.id"
                    :value="raum.id"
                  >
                    {{ roomLabel(raum) }}
                  </option>
                </select>
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
