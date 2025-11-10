<template>
    <Modal v-if="visible" @close="emit('close')">
        <template #header>{{ $t('Fahrtart anlegen') }}</template>

        <template #body>
            <div class="grid grid-cols-1 md:grid-cols-1 gap-4">
                <div class="mb-4 w-full mx-1">
                    <FloatLabel variant="on">
                        <Select v-model="form.fahrtart_id" :options="props.fahrtarten" optionValue="id" optionLabel="name" class="w-full"/>
                        <label>Fahrtaren</label>
                    </FloatLabel>
                </div>
                <div class="mb-4 w-full mx-1">
                      <FloatLabel variant="on">
                        <Dropdown
                            v-model="form.rechentyp"
                            :options="items"
                            class="w-full"
                        />
                        <label>Fahrtarten</label>
                    </FloatLabel>
                </div>
                <div class="mb-4 w-full">
                    <FloatLabel>
                        <InputText v-model="form.satz" class="w-full" required />
                        <label>{{ $t('Satz/Prozent') }}</label>
                    </FloatLabel>
                </div>
                <div class="flex w-full gap-x-4">
                    <div class="mb-4 w-full">
                        <FloatLabel variant="on" class="w-full">
                            <DatePicker v-model="form.ab" dateFormat="dd-mm-yy" class="w-full" inputClass="w-full"/>
                            <label>Ab</label>
                        </FloatLabel>
                    </div>
                    <div class="mb-4 w-full ">
                        <FloatLabel variant="on">
                            <DatePicker v-model="form.bis" dateFormat="dd-mm-yy" class="w-full" inputClass="w-full"/>
                            <label>Bis</label>
                        </FloatLabel>
                    </div>
                </div>
                <div class="mb-4 w-full">
                    <FloatLabel>
                        <Textarea v-model="form.bemerkung" class="w-full" />
                        <label>{{ $t('Bemerkung') }}</label>
                    </FloatLabel>
                </div>
            </div>
        </template>

        <template #footer>
            <button @click="save" class="bg-zbb text-white px-4 py-2 rounded hover:bg-zbb-dark transition">
                Speichern
            </button>
            <button @click="emit('close')" class="border px-4 py-2 rounded hover:bg-gray-100 transition">
                Abbrechen
            </button>
        </template>
    </Modal>
</template>

<script setup>
import Modal from '@/Components/ModalForm.vue';
import { ref } from 'vue';
import Swal from 'sweetalert2';
import FloatLabel from 'primevue/floatlabel';
import InputText from 'primevue/inputtext';
import axios from 'axios';
import Textarea from 'primevue/textarea';
import Select from 'primevue/select';
import DatePicker from 'primevue/datepicker';
import AutoComplete from 'primevue/autocomplete'
import Dropdown from 'primevue/dropdown' // Wichtig: das ist die PrimeVue-Komponente
import { formatDate } from '@/utils/dateFormat.js';


const props = defineProps({
  visible: Boolean,
  fahrtarten: { type: Array, required: true }
});
const emit = defineEmits(['close', 'added']);
console.log(props.fahrtarten)
let form = ref({
    fahrtart_id: '',
    rechentyp: '',
    satz:'',
    bemerkung: '',
    ab: '',
    bis: '',
});

const resetForm = () => {
  form.value = { fahrtart_id: '', bemerkung: '' };
};
// deine Liste mit Vorschlägen:
const items = ['pro_km', 'pro_fahrt', 'pro_monat', 'prozent']

const save = async () => {
  try {
    const response = await axios.post(route('fahrtkosten.store'), form.value);

    const newKosten = response.data.fahrtkosten ?? response.data;

    Swal.fire('Erfolg!', 'Fahrtkosten erfolgreich angelegt!', 'success');

    emit('added', newKosten); // an Tabelle senden
    resetForm();
    emit('close'); // Modal schließen
  } catch (error) {
    Swal.fire('Fehler', error.response?.data?.message || 'Speichern fehlgeschlagen', 'error');
  }
};

</script>
