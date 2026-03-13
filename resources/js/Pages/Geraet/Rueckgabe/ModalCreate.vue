<script setup>
import { ref, defineProps, defineEmits, watch } from 'vue'
import { router } from '@inertiajs/vue3'

import InputText from 'primevue/inputtext'
import Dropdown from 'primevue/dropdown'
import MultiSelect from 'primevue/multiselect'
import DatePicker from 'primevue/datepicker'

import Modal from '@/Components/ModalForm.vue'

const props = defineProps({
  visible: Boolean,
  rueckgeber: Array,
  ausgaben: Array,
  geraete: Array,
  ablageorte: Array
})

const emit = defineEmits(['close', 'added'])
const geraeteAusgabe = ref([])
const form = ref({
  rueckgabescheinNr: Date.now(),
  ausgabeschein_nr: null,
  sn: [],
  ausleiher: null,
  rueckgabedatum: null,
  ablageort: null
})

const errors = ref({})

watch(() => props.visible, (val) => {
  if (!val) {
    form.value = {
      rueckgabescheinNr: Date.now(),
      ausgabeschein_nr: null,
      sn: [],
      ausleiher: null,
      rueckgabedatum: null,
      ablageort: null
    }

    errors.value = {}
  }
})

watch(() => form.value.ausgabeschein_nr, async (id) => {

  form.value.sn = []
  geraeteAusgabe.value = []

  if (!id) return

  const response = await axios.get(route('geraet.ausgabe.geraete', id))

  geraeteAusgabe.value = response.data.geraete

})
const saveRueckgabe = () => {

  router.post(route('geraet.rueckgabe.store'), form.value, {

    onError: (e) => errors.value = e,

    onSuccess: () => {
      emit('added', { ...form.value })
      emit('close')
    }

  })
}
</script>

<template>

<Modal v-if="visible" @close="emit('close')">

<template #header>
Rückgabe anlegen
</template>


<template #body>

<form>

<div class="grid grid-cols-2 gap-4">

<!-- Rückgabeschein -->

<div>
<label class="block font-medium mb-1">
Rückgabeschein
</label>

<InputText
v-model="form.rueckgabescheinNr"
class="w-full bg-gray-100"

disabled
/>

</div>


<!-- Ausgabeschein -->

<div>

<label class="block font-medium mb-1">
Ausgabeschein
<span class="text-red-600">*</span>
</label>

<Dropdown
v-model="form.ausgabeschein_nr"
:options="props.ausgaben"
optionLabel="ausgabescheinNr"
optionValue="id"
placeholder="Ausgabeschein wählen"
class="w-full"
/>

<small v-if="errors.ausgabeschein_nr" class="text-red-600">
{{ errors.ausgabeschein_nr }}
</small>

</div>


<!-- Geräte -->

<div>

<label class="block font-medium mb-1">
Geräte
<span class="text-red-600">*</span>
</label>

<MultiSelect
v-model="form.sn"
:options="geraeteAusgabe"
optionLabel="productID"
optionValue="id"
placeholder="Geräte wählen"
display="chip"
filter
class="w-full"
:disabled="!form.ausgabeschein_nr"
/>
<small v-if="errors.sn" class="text-red-600">
{{ errors.sn }}
</small>

</div>


<!-- Rückgeber -->

<div>

<label class="block font-medium mb-1">
Rückgeber
<span class="text-red-600">*</span>
</label>

<Dropdown
v-model="form.ausleiher"
:options="props.rueckgeber"
optionLabel="nachname"
optionValue="id"
placeholder="Rückgeber wählen"
class="w-full"
filter
>

<template #option="slotProps">
{{ slotProps.option.vorname }} {{ slotProps.option.nachname }}
</template>

</Dropdown>

<small v-if="errors.ausleiher" class="text-red-600">
{{ errors.ausleiher }}
</small>

</div>


<!-- Rückgabedatum -->

<div>

<label class="block font-medium mb-1">
Rückgabedatum
<span class="text-red-600">*</span>
</label>

<DatePicker
v-model="form.rueckgabedatum"
dateFormat="dd.mm.yy"
showIcon
class="w-full"
/>

<small v-if="errors.rueckgabedatum" class="text-red-600">
{{ errors.rueckgabedatum }}
</small>

</div>


<!-- Ablageort -->

<div>

<label class="block font-medium mb-1">
Ablageort
</label>

<Dropdown
v-model="form.ablageort"
:options="props.ablageorte"
placeholder="Ablageort wählen"
class="w-full"
/>

<small v-if="errors.ablageort" class="text-red-600">
{{ errors.ablageort }}
</small>

</div>

</div>

</form>

</template>


<template #footer>

<button
@click="saveRueckgabe"
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
