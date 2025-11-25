<script setup>
import Modal from '@/Components/ModalForm.vue';
import { ref, watch } from 'vue';
import Swal from 'sweetalert2';

import FloatLabel from 'primevue/floatlabel';
import InputText from 'primevue/inputtext';
import Textarea from 'primevue/textarea';
import Dropdown from 'primevue/dropdown';
import MultiSelect from 'primevue/multiselect';

const props = defineProps({
    visible: Boolean,
    partnerschaftstypen: Array,
    toEdit: Object,
});

const emit = defineEmits(['close', 'updated']);

let form = ref({
    name: '',
    beschreibung: '',
    typ: [],
    ansprechpartner: [],
});

// 🔄 Daten laden, sobald Modal geöffnet wird
watch(
    () => props.toEdit,
    (val) => {
        if (!val) return;

        form.value = {
            name: val.name,
            beschreibung: val.beschreibung,
            typ: val.partnerschaftstypens.map(t => t.id), // IDs extrahieren
            ansprechpartner: val.partnerschaftstypenZuordnung?.map(x => ({
                vorname: x.ansprechpartner?.vorname ?? '',
                nachname: x.ansprechpartner?.nachname ?? '',
                geschlecht: x.ansprechpartner?.geschlecht ?? '',
                typ: x.rolle ?? ''
            })) || []
        };
    },
    { immediate: true }
);

const addAnsprechpartner = () => {
    form.value.ansprechpartner.push({
        vorname: '',
        nachname: '',
        geschlecht: '',
        typ: ''
    });
};

const removeAnsprechpartner = (i) => {
    form.value.ansprechpartner.splice(i, 1);
};

const save = () => {
    emit("updated", JSON.parse(JSON.stringify(form.value)));
};
</script>

<template>
  <Modal v-if="visible" @close="emit('close')">

    <template #header>
        <h2 class="text-lg font-bold text-gray-600">Partner bearbeiten</h2>
    </template>

    <template #body>

      <div class="max-h-[70vh] overflow-y-auto pr-3">

        <FloatLabel class="mb-3">
            <InputText v-model="form.name" class="w-full" />
            <label>Partnername</label>
        </FloatLabel>

        <FloatLabel class="mb-3">
            <MultiSelect
                v-model="form.typ"
                :options="partnerschaftstypen"
                optionLabel="bezeichnung"
                optionValue="id"
                display="chip"
                filter
                class="w-full"
            />
            <label>Partnerschaftstypen</label>
        </FloatLabel>

        <FloatLabel class="mb-3">
            <Textarea v-model="form.beschreibung" rows="4" class="w-full" />
            <label>Beschreibung</label>
        </FloatLabel>

        <hr class="my-4" />

        <h3 class="font-semibold text-gray-600 mb-2">Ansprechpartner</h3>

        <div
          v-for="(p, index) in form.ansprechpartner"
          :key="index"
          class="border p-3 rounded bg-gray-50 mb-3"
        >
            <h4 class="font-bold text-gray-600 mb-2">Ansprechpartner {{ index + 1 }}</h4>

            <div class="grid grid-cols-2 gap-2 mb-2">
                <FloatLabel>
                    <InputText v-model="p.vorname" class="w-full"/>
                    <label>Vorname</label>
                </FloatLabel>

                <FloatLabel>
                    <InputText v-model="p.nachname" class="w-full"/>
                    <label>Nachname</label>
                </FloatLabel>
            </div>

            <div class="grid grid-cols-2 gap-2 mb-2">
                <FloatLabel>
                    <Dropdown
                        v-model="p.geschlecht"
                        :options="['männlich','weiblich','divers']"
                        class="w-full"
                    />
                    <label>Geschlecht</label>
                </FloatLabel>

                <FloatLabel>
                    <InputText v-model="p.typ" class="w-full"/>
                    <label>Rolle / Funktion</label>
                </FloatLabel>
            </div>

            <button
              v-if="form.ansprechpartner.length > 1"
              @click="removeAnsprechpartner(index)"
              class="text-red-500 text-sm"
            >
              Ansprechpartner entfernen
            </button>
        </div>

        <button
          class="bg-gray-200 px-3 py-1 rounded text-sm"
          @click="addAnsprechpartner"
        >
          + Ansprechpartner hinzufügen
        </button>

      </div>

    </template>

    <template #footer>
        <button @click="save" class="bg-zbb text-white px-4 py-2 rounded">Speichern</button>
        <button @click="emit('close')" class="px-4 py-2 rounded border">Abbrechen</button>
    </template>

  </Modal>
</template>
