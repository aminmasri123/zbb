<template>
  <div>
    <button
      @click="saveStammdaten"
      class="bg-zbb text-white px-4 mb-6 mt-4 py-2 rounded-md text-sm hover:bg-zbb/80 transition w-full"
    >
      ➕ Speichern
    </button>

    <div class="grid grid-cols-3 gap-4">
      <div>
        <label>Vorname</label>
        <input v-model="teilnehmer.vorname" class="input" />
      </div>

      <div>
        <label>Nachname</label>
        <input v-model="teilnehmer.nachname" class="input" />
      </div>

      <div>
        <label>Geschlecht</label>
        <select v-model="teilnehmer.geschlecht" class="input">
          <option value="m">m</option>
          <option value="w">w</option>
          <option value="d">divers</option>
        </select>
      </div>

      <div>
        <label>Geburtsdatum</label>
        <input type="date" v-model="form.geburtsdatum" class="input" />
      </div>

      <div>
        <label>Betreuer</label>
        <select v-model="form.betreuer" class="input">
          <option
            v-for="m in betreuer"
            :key="m.id"
            :value="m.id"
          >
            {{ m.nachname }} - {{ m.vorname }}
          </option>
        </select>
      </div>

      <div class="md:col-span-3">
        <label>Bemerkungen</label>
        <textarea v-model="teilnehmer.bemerkungen" rows="2" class="input"></textarea>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref } from "vue";
import { router } from "@inertiajs/vue3";
import Swal from "sweetalert2";

const props = defineProps({
  teilnehmer: Object,
  betreuer: Array,
});

const teilnehmer = ref(JSON.parse(JSON.stringify(props.teilnehmer)));

const form = ref({
  geburtsdatum: teilnehmer.value.geburtsdatum || "",
  betreuer: teilnehmer.value.betreuer_id || "",
});

const loadingSave = ref(false);

const saveStammdaten = () => {
  if (!teilnehmer.value.vorname || !teilnehmer.value.nachname) {
    Swal.fire({
      icon: "warning",
      title: "Pflichtfelder fehlen",
      text: "Bitte Vor- und Nachname eingeben.",
    });
    return;
  }

  loadingSave.value = true;

  const payload = {
    vorname: teilnehmer.value.vorname,
    nachname: teilnehmer.value.nachname,
    geschlecht: teilnehmer.value.geschlecht,
    geburtsdatum: form.value.geburtsdatum,
    betreuer: form.value.betreuer,
    bemerkungen: teilnehmer.value.bemerkungen,
  };

  router.patch(route("teilnehmer.update", teilnehmer.value.id), payload, {
    preserveScroll: true,
    onSuccess: () => {
      Swal.fire({
        icon: "success",
        title: "Gespeichert!",
        text: "Die Stammdaten wurden erfolgreich aktualisiert.",
        timer: 1500,
        showConfirmButton: false,
      });
    },
    onError: () => {
      Swal.fire({
        icon: "error",
        title: "Fehler",
        text: "Die Stammdaten konnten nicht gespeichert werden.",
      });
    },
    onFinish: () => (loadingSave.value = false),
  });
};
</script>

<style scoped>
.input {
  @apply mt-1 w-full border-gray-300 rounded-md shadow-sm focus:ring-zbbTrp focus:border-zbb text-sm;
}
</style>
