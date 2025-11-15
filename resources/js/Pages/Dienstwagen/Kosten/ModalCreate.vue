<template>
  <Dialog
    v-model:visible="visible"
    modal
    :header="isEditing ? '💰 Kosten bearbeiten' : '➕ Neue Kosten'"
    :style="{ width: '40rem' }"
    class="modern-modal"
  >
    <form @submit.prevent="submit" class="grid grid-cols-2 gap-5 mt-2">
      <FloatLabel variant="on">
        <Select
          v-model="form.vehicle_id"
          :options="vehicles.map(v => ({
            label: `${v.license_plate} – ${v.brand} ${v.model}`,
            value: v.id
          }))"
          optionLabel="label"
          optionValue="value"
          class="w-full"
        />
        <label>Fahrzeug</label>
      </FloatLabel>

      <FloatLabel variant="on">
        <Select
          v-model="form.type"
          :options="[
            'Kraftstoff','Reparatur','Versicherung','Leasing','Steuern','Sonstiges'
          ].map(t => ({ label: t, value: t }))"
          optionLabel="label"
          optionValue="value"
          class="w-full"
        />
        <label>Art</label>
      </FloatLabel>

      <FloatLabel variant="on">
        <InputText v-model="form.date" type="date" class="w-full" />
        <label>Datum</label>
      </FloatLabel>

      <FloatLabel variant="on">
        <InputText v-model="form.amount" type="number" class="w-full" />
        <label>Betrag (€)</label>
      </FloatLabel>

      <FloatLabel variant="on" class="col-span-2">
        <Textarea v-model="form.description" class="w-full" />
        <label>Beschreibung</label>
      </FloatLabel>

      <div class="col-span-2 flex justify-end gap-3 mt-4">
        <button type="button" class="border border-gray-400 px-4 py-2 rounded-lg" @click="$emit('close')">Abbrechen</button>
        <button type="submit" class="bg-zbb text-white font-semibold px-5 py-2 rounded-lg">
          {{ isEditing ? '💾 Speichern' : '➕ Hinzufügen' }}
        </button>
      </div>
    </form>
  </Dialog>
</template>

<script setup>
import Dialog from 'primevue/dialog'
import InputText from 'primevue/inputtext'
import Textarea from 'primevue/textarea'
import FloatLabel from 'primevue/floatlabel'
import Select from 'primevue/select'
import Swal from 'sweetalert2'
import { router } from '@inertiajs/vue3'

const props = defineProps({
  visible: Boolean,
  isEditing: Boolean,
  form: Object,
  vehicles: Array,
  editId: Number,
})
const emit = defineEmits(['close', 'saved'])

function submit() {
  if (props.isEditing && props.editId) {
    router.put(route('fleet.costs.update', props.editId), props.form, {
      onSuccess: () => {
        Swal.fire({ icon: 'success', title: 'Aktualisiert!', timer: 1500, showConfirmButton: false })
        emit('saved', props.form)
        emit('close')
      }
    })
  } else {
    router.post(route('fleet.costs.store'), props.form, {
      onSuccess: () => {
        Swal.fire({ icon: 'success', title: 'Gespeichert!', timer: 1500, showConfirmButton: false })
        emit('saved', props.form)
        emit('close')
      }
    })
  }
}
</script>
