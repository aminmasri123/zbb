<script setup>
import { ref, defineProps, watch, defineEmits } from 'vue'
import { router } from '@inertiajs/vue3'

import InputText from 'primevue/inputtext'
import Dropdown from 'primevue/dropdown'
import MultiSelect from 'primevue/multiselect'
import DatePicker from 'primevue/datepicker'

import Modal from '@/Components/ModalForm.vue'

const props = defineProps({
  visible: Boolean,
  ausleiher: Array,
  projekte: Array,
  geraete: Array
})

const emit = defineEmits(['close','added'])

const form = ref({
  ausgabeschein_nr: Date.now(),
  ausleiher: null,
  projekt: null,
  sn: [],
  ausleihdatum: null
})

const errors = ref({})

watch(() => props.visible, (val) => {

  if (!val) {

    form.value = {
      ausgabeschein_nr: Date.now(),
      ausleiher: null,
      projekt: null,
      sn: [],
      ausleihdatum: null
    }

    errors.value = {}

  }

})

const saveAusgabe = () => {

     if (!form.value.ausleiher)
    errors.value.ausleiher = "Ausleiher ist erforderlich"

  if (!form.value.projekt)
    errors.value.projekt = "Projekt ist erforderlich"

  if (!form.value.sn || form.value.sn.length === 0)
    errors.value.sn = "Mindestens ein Gerät auswählen"

  if (!form.value.ausleihdatum)
    errors.value.ausleihdatum = "Datum ist erforderlich"

  if (Object.keys(errors.value).length > 0)
    return
  router.post(route('geraet.ausgabe.store'), form.value, {

    onError: (e) => errors.value = e,

    onSuccess: () => {

      emit('added', {...form.value})
      emit('close')

    }

  })

}
</script>


<template>

<Modal v-if="visible" @close="emit('close')">

<template #header>
Ausgabe anlegen
</template>

<template #body>

<form>

<div class="grid grid-cols-2 gap-4">

<!-- Ausgabeschein -->

<div>

<label class="block font-medium mb-1">
Ausgabeschein Nr
</label>

<InputText
v-model="form.ausgabeschein_nr"
readonly
class="w-full bg-gray-100"
/>

</div>


<!-- Ausleiher -->

<div>

<label class="block font-medium mb-1">
Ausleiher <span class="text-red-600">*</span>
</label>

<Dropdown
v-model="form.ausleiher"
filter
:options="ausleiher"
:optionLabel="(option) => option.vorname + ' ' + option.nachname"
optionValue="id"
placeholder="Ausleiher wählen"
class="w-full"
:class="{ 'p-invalid': errors.ausleiher }"

/>

<small v-if="errors.ausleiher" class="text-red-600">
{{ errors.ausleiher }}
</small>

</div>


<!-- Projekt -->

<div>

<label class="block font-medium mb-1">
Projekt <span class="text-red-600">*</span>
</label>

<Dropdown
v-model="form.projekt"
filter
:options="projekte"
optionLabel="name"
optionValue="id"
placeholder="Projekt wählen"
class="w-full"
:class="{ 'p-invalid': errors.projekt }"

/>

<small v-if="errors.projekt" class="text-red-600">
{{ errors.projekt }}
</small>

</div>


<!-- Geräte -->

<div>

<label class="block font-medium mb-1">
Geräte auswählen
</label>

<MultiSelect
display="chip"
filter
v-model="form.sn"
:options="geraete"
optionLabel="productID"
optionValue="sn"
placeholder="Geräte auswählen"
class="w-full"
:class="{ 'p-invalid': errors.sn }"
/>

<small v-if="errors.sn" class="text-red-600">
{{ errors.sn }}
</small>

</div>


<!-- Ausleihdatum -->

<div>

<label class="block font-medium mb-1">
Ausleihdatum
</label>

<DatePicker
v-model="form.ausleihdatum"
dateFormat="dd.mm.yy"
showIcon
class="w-full"
:class="{ 'p-invalid': errors.ausleihdatum }"
/>

<small v-if="errors.ausleihdatum" class="text-red-600">
{{ errors.ausleihdatum }}
</small>

</div>

</div>

</form>

</template>


<template #footer>

<button
@click="saveAusgabe"
class="bg-zbb text-white px-4 py-2 rounded"
>

Speichern

</button>

<button
@click="emit('close')"
class="border px-4 py-2 rounded"
>

Abbrechen

</button>

</template>

</Modal>

</template>
