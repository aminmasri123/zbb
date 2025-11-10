<template>
    <div v-if="activeTab === 'Sozialdaten'">
        <button @click="saveSozialdaten"
        class="bg-zbb text-white px-4  mb-6 mt-4 py-2 rounded-md text-sm hover:bg-zbb/80 transition w-full">
        ➕ Speichern
        </button>

        <div class="space-y-5  w-96 mx-auto">
            <Toggle v-model="drittstaatsangehoerig" label="Drittstaatsangehörige?" hint="Nicht-EU/EWR/Schweiz" />

            <Toggle v-model="behinderung" label="Liegt eine Behinderung vor?" hint="Nach §2 SGB IX" />

            <Toggle v-model="gefluechtet" label="Teilnehmer ist geflüchtet?" />

            <div class="flex items-center w-96   justify-between gap-4">
                <label class="block text-sm font-medium text-gray-800 leading-6">Leistungsbezug nach SGB</label>
                <select v-model="leistungsbezug_id" class="input w-64" >
                    <option :value="null" disabled>— auswählen —</option>
                    <option v-for="m in props.leistungsbezuege" :key="m.id" :value="m.id" >
                        {{ m.bezeichnung }}
                    </option>
                </select>
            </div>

            <Toggle v-model="migrationshintergrund" label="Liegt ein Migrationshintergrund vor?" />

            wohnsitz_stabil
            <Toggle v-model="wohnsitz_stabil" label="Wohnsitz stabil?" />
        </div>
    </div>
</template>

<script setup>
import { ref } from "vue";
import { router } from "@inertiajs/vue3";
import Swal from "sweetalert2";

const props = defineProps({
  teilnehmer: Object,
});

const sozialdaten = ref({
  familienstand: props.teilnehmer.familienstand || "",
  staatsangehoerigkeit: props.teilnehmer.staatsangehoerigkeit || "",
  muttersprache: props.teilnehmer.muttersprache || "",
  sv_nummer: props.teilnehmer.sv_nummer || "",
  kundennummer_ba: props.teilnehmer.kundennummer_ba || "",
  kindergeldberechtigt: props.teilnehmer.kindergeldberechtigt || "",
  bemerkungen: props.teilnehmer.sozial_bemerkungen || "",
});

const loadingSave = ref(false);

const saveSozialdaten = () => {
  loadingSave.value = true;

  router.patch(
    route("teilnehmer.updateSozialdaten", props.teilnehmer.id),
    sozialdaten.value,
    {
      preserveScroll: true,
      onSuccess: () => {
        Swal.fire({
          icon: "success",
          title: "Gespeichert!",
          text: "Die Sozialdaten wurden erfolgreich aktualisiert.",
          timer: 1500,
          showConfirmButton: false,
        });
      },
      onError: () => {
        Swal.fire({
          icon: "error",
          title: "Fehler",
          text: "Die Sozialdaten konnten nicht gespeichert werden.",
        });
      },
      onFinish: () => (loadingSave.value = false),
    }
  );
};
</script>

<style scoped>
.input {
  @apply mt-1 w-full border-gray-300 rounded-md shadow-sm focus:ring-zbbTrp focus:border-zbb text-sm;
}
</style>
