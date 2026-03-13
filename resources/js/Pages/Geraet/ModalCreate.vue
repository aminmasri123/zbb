<script setup>
import { ref, defineProps, watch, defineEmits } from 'vue';
import { router } from '@inertiajs/vue3';
import InputText from 'primevue/inputtext';
import Dropdown from 'primevue/dropdown';
import Button from 'primevue/button';
import Dialog from 'primevue/dialog';
import DatePicker from 'primevue/datepicker';
import Modal from '@/Components/ModalForm.vue'

const props = defineProps({
  visible: Boolean,
  herstellerListe: Array,
  //ablageorteListe: Array, // <-- neu hinzugefügt
});

const emit = defineEmits(['close', 'added']);

const form = ref({
  sn: '',
  produkt_id: '',
  zustand: '',
  geraet: '',
  imLager: '',
  hersteller: '',
  modell: '',
  baujahr: null,
  garantiefrist: null,
});

const errors = ref({});

watch(() => props.visible, (val) => {
  if (!val) {
    Object.keys(form.value).forEach(k => form.value[k] = '');
    errors.value = {};
  }
});

const saveGeraet = () => {
  router.post(route('geraet.store'), form.value, {
    onError: (e) => errors.value = e,
    onSuccess: () => {
     emit('added', {
        ...form.value,
        productID: form.value.produkt_id,
        verfuegbarkeit: true
    });
      emit('close');
    }
  });
};
const ablageorteListe = [
  { label: 'Brandneu', value: 'Brandneu' },
  { label: 'Neuwertig', value: 'Neuwertig' },
  { label: 'Leichte Gebrauchsspuren', value: 'Leichte Gebrauchsspuren' },
  { label: 'Starke Gebrauchsspuren', value: 'Starke Gebrauchsspuren' },
  { label: 'Reparaturbedürftig', value: 'Reparaturbedürftig' },
  { label: 'Defekt', value: 'Defekt' },
];

const zustandOptions = [
  { label: 'Brandneu', value: 'Brandneu' },
  { label: 'Neuwertig', value: 'Neuwertig' },
  { label: 'Leichte Gebrauchsspuren', value: 'Leichte Gebrauchsspuren' },
  { label: 'Starke Gebrauchsspuren', value: 'Starke Gebrauchsspuren' },
  { label: 'Reparaturbedürftig', value: 'Reparaturbedürftig' },
  { label: 'Defekt', value: 'Defekt' },
];

const geraetOptions = [
  { label: 'Laptop', value: 'Laptop' },
  { label: 'Drucker', value: 'Drucker' },
  { label: 'Telefon', value: 'Telefon' },
  { label: 'Handy', value: 'Handy' },
  { label: 'Monitor', value: 'Monitor' },
];
</script>

<template>
<Modal v-if="visible" @close="emit('close')">
 <template #header>{{$t('Gruppe anlegen')}}</template>
    <template #body>
    <form >
    <div class="grid grid-cols-2 gap-4">
      <!-- Seriennummer -->
      <div>
        <label class="block font-medium mb-1">SN <span class="text-red-600">*</span></label>
        <InputText v-model="form.sn" class="w-full" />
        <small v-if="errors.sn" class="text-red-600">{{ errors.sn }}</small>
      </div>

      <!-- Produkt ID -->
      <div>
        <label class="block font-medium mb-1">Produkt ID <span class="text-red-600">*</span></label>
        <InputText v-model="form.produkt_id" class="w-full bg-gray-100" />
        <small v-if="errors.produkt_id" class="text-red-600">{{ errors.produkt_id }}</small>
      </div>

      <!-- Zustand -->
      <div>
        <label class="block font-medium mb-1">Zustand <span class="text-red-600">*</span></label>
        <Dropdown v-model="form.zustand" :options="zustandOptions" optionLabel="label" optionValue="value" placeholder="Zustand wählen" class="w-full"
  />
        <small v-if="errors.zustand" class="text-red-600">{{ errors.zustand }}</small>
      </div>

      <!-- Gerät -->
      <div>
        <label class="block font-medium mb-1">Gerät <span class="text-red-600">*</span></label>
        <Dropdown v-model="form.geraet" :options="geraetOptions" optionLabel="label" optionValue="value"  placeholder="Gerät wählen" class="w-full" />
        <small v-if="errors.geraet" class="text-red-600">{{ errors.geraet }}</small>
      </div>

      <!-- Ablageort -->
      <!-- <div>
        <label class="block font-medium mb-1">Ablageort</label>
        <Dropdown
          v-model="form.imLager"
          :options="props.ablageorteListe.map(a => ({ label: a, value: a }))"
          placeholder="Ablageort wählen"
          class="w-full"
        />
        <small v-if="errors.imLager" class="text-red-600">{{ errors.imLager }}</small>
      </div> -->

      <!-- Hersteller -->
      <div>
        <label class="block font-medium mb-1">Hersteller <span class="text-red-600">*</span></label>
        <InputText v-model="form.hersteller" class="w-full bg-gray-100" />

        <small v-if="errors.hersteller" class="text-red-600">{{ errors.hersteller }}</small>
      </div>

      <!-- Modell -->
      <div>
        <label class="block font-medium mb-1">Modell</label>
        <InputText v-model="form.modell" class="w-full" />
        <small v-if="errors.modell" class="text-red-600">{{ errors.modell }}</small>
      </div>

      <!-- Baujahr -->
      <div>
        <label class="block font-medium mb-1">Baujahr</label>
        <DatePicker v-model="form.baujahr" dateFormat="dd.mm.yy" showIcon class="w-full" />
        <small v-if="errors.baujahr" class="text-red-600">{{ errors.baujahr }}</small>
      </div>

      <!-- Garantiefrist -->
      <div>
        <label class="block font-medium mb-1">Garantiefrist</label>
        <DatePicker v-model="form.garantiefrist" dateFormat="dd-mm-yy" class="w-full" inputClass="w-full" showIcon iconDisplay="input" />

        <small v-if="errors.garantiefrist" class="text-red-600">{{ errors.garantiefrist }}</small>
      </div>
    </div>
  </form>
    </template>
    <template #footer>
      <button @click="saveGeraet" class="bg-zbb text-white px-4 py-2 rounded">Speichern</button>
      <button @click="emit('close')" class="border px-4 py-2 rounded">Abbrechen</button>
    </template>
  </Modal>
</template>


