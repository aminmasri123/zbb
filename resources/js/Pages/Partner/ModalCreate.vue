<script setup>
import Modal from '@/Components/ModalForm.vue';
import { ref } from 'vue';
import Swal from 'sweetalert2';
import FloatLabel from 'primevue/floatlabel';
import InputText from 'primevue/inputtext';
import Textarea from 'primevue/textarea';
import Dropdown from 'primevue/dropdown';
import MultiSelect from 'primevue/multiselect';

const props = defineProps({
    visible: Boolean,
    partnerschaftstypen: Array,
    projektName: String,
});

const emit = defineEmits(['close', 'add-partner']);

let form = ref({
    name: '',
    beschreibung: '',
    typ: [],
    ansprechpartner: [
        {
            vorname: '',
            nachname: '',
            geschlecht: '',
            typ: '',
            adresse: {
                strasse: '',
                hausnummer: '',
                plz: '',
                stadt: '',
                land: 'Deutschland',
                zusatzinfo: ''
            },
            email: '',
            tel: '',
            handy: ''
        }
    ]
});

const addKontakt = (index) => {
    form.value.ansprechpartner[index].kontakte.push({ kontakttyp_id: null, wert: '', bemerkung: '' });
};

const removeKontakt = (aIndex, kIndex) => {
    form.value.ansprechpartner[aIndex].kontakte.splice(kIndex, 1);
};

const addAnsprechpartner = () => {
    form.value.ansprechpartner.push({
        vorname: '',
        nachname: '',
        geschlecht: '',
        typ: '',
        adresse: {
            strasse: '',
            hausnummer: '',
            plz: '',
            stadt: '',
            land: 'Deutschland',
            zusatzinfo: ''
        }
    });
};

const removeAnsprechpartner = (i) => {
    form.value.ansprechpartner.splice(i, 1);
};

const resetForm = () => {
    form.value = {
        name: '',
        beschreibung: '',
        typ: [],
        ansprechpartner: [
            {
                vorname: '',
                nachname: '',
                geschlecht: '',
                typ: '',
                adresse: { strasse: '', hausnummer: '', plz: '', stadt: '', land: 'Deutschland', zusatzinfo: '' }
            }
        ]
    };
};

const save = () => {
    if (!form.value.name) {
        Swal.fire("Fehler", "Bitte Partnername eingeben!", "error");
        return;
    }

    emit("add-partner", { ...form.value });
    resetForm();
    emit("close");
};
</script>

<template>
 <Modal v-if="visible" @close="emit('close')">

    <template #header>
        <h2 class="text-lg font-bold text-gray-600">Partner anlegen</h2>
    </template>

    <template #body>

      <div class="max-h-[70vh] overflow-y-auto pr-3">

        <!-- Partnername -->
         <FloatLabel class="mb-3 ">
            <InputText :value="`Projekt: ${props.projektName}`"  class="w-full" disabled />
        </FloatLabel>
        <FloatLabel class="mb-3">
            <InputText v-model="form.name" class="w-full" />
            <label>Partnername <span class="text-red-500">*</span></label>
        </FloatLabel>

        <!-- Typen -->
        <FloatLabel class="mb-3">
            <MultiSelect
                v-model="form.typ"
                :options="props.partnerschaftstypen"
                optionLabel="bezeichnung"
                optionValue="id"
                display="chip"
                filter
                class="w-full"
            />
            <label>Partnerschaftstypen wählen <span class="text-red-500">*</span></label>
        </FloatLabel>

        <!-- Beschreibung -->
        <FloatLabel class="mb-3">
            <Textarea v-model="form.beschreibung" class="w-full" rows="4" />
            <label>Beschreibung</label>
        </FloatLabel>

        <hr class="my-4" />

        <!-- Ansprechpartner -->
        <h3 class="text-md font-semibold mb-2">Ansprechpartner</h3>

        <div
          v-for="(p, index) in form.ansprechpartner"
          :key="index"
          class="border p-3 rounded bg-gray-50 mb-3"
        >
            <h4 class="font-bold text-gray-600 mb-2">Ansprechpartner {{ index + 1 }}</h4>

            <div class="grid grid-cols-2 gap-2 mb-2">
                <FloatLabel variant="in">
                    <InputText v-model="p.vorname" class="w-full"/>
                    <label>Vorname <span class="text-red-500">*</span></label>
                </FloatLabel>

                <FloatLabel variant="in">
                    <InputText v-model="p.nachname" class="w-full"/>
                    <label>Nachname <span class="text-red-500">*</span></label>
                </FloatLabel>
            </div>

            <div class="grid grid-cols-2 gap-2 mb-2">
                <FloatLabel variant="in">
                    <Dropdown
                        v-model="p.geschlecht"
                        :options="['männlich','weiblich','divers']"
                        class="w-full"
                    />
                    <label>Geschlecht</label>
                </FloatLabel>

                <FloatLabel variant="in">
                    <InputText v-model="p.typ" class="w-full"/>
                    <label>Rolle / Funktion</label>
                </FloatLabel>

                <FloatLabel variant="in">
                    <InputText v-model="p.email" class="w-full"/>
                    <label>E-Mail</label>
                </FloatLabel>

                <FloatLabel variant="in">
                    <InputText v-model="p.tel" class="w-full"/>
                    <label>Telefon</label>
                </FloatLabel>
                <FloatLabel variant="in">
                    <InputText v-model="p.handy" class="w-full"/>
                    <label>Handy</label>
                </FloatLabel>
                <FloatLabel variant="in">
                    <InputText v-model="p.adresse.land" class="w-full"/>
                    <label>Land</label>
                </FloatLabel>

                 <FloatLabel variant="in">
                    <InputText v-model="p.adresse.strasse" class="w-full"/>
                    <label>Straße</label>
                </FloatLabel>

                <FloatLabel variant="in">
                    <InputText v-model="p.adresse.hausnummer" class="w-full"/>
                    <label>Hausnummer</label>
                </FloatLabel>

                <FloatLabel variant="in">
                    <InputText v-model="p.adresse.plz" class="w-full"/>
                    <label>PLZ</label>
                </FloatLabel>

                <FloatLabel variant="in">
                    <InputText v-model="p.adresse.stadt" class="w-full"/>
                    <label>Stadt</label>
                </FloatLabel>



                <FloatLabel variant="in">
                    <InputText v-model="p.adresse.zusatzinfo" class="w-full"/>
                    <label>Zusatzinfo</label>
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
