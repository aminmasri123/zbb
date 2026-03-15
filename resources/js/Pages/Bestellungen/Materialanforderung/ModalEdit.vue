<script setup>
import { ref, watch } from 'vue'
import { router } from '@inertiajs/vue3'
import Dialog from 'primevue/dialog'
import InputText from 'primevue/inputtext'
import Dropdown from 'primevue/dropdown'
import Button from 'primevue/button'

defineProps({
    visible: Boolean,
    item: Object,
    user: Array,
    projekte: Array
})

defineEmits(['update:visible'])

const form = ref({
    projekt: '',
    kostenstelle: '',
    ersteller_id: null,
    bemerkungen: ''
})

watch(() => item, (newVal) => {
    if (newVal) {
        form.value.projekt = newVal.projekt
        form.value.kostenstelle = newVal.kostenstelle
        form.value.ersteller_id = newVal.ersteller_id
        form.value.bemerkungen = newVal.bemerkungen
    }
}, { immediate: true })

const submit = () => {
    router.put(route('materialanforderung.update', item.id), form.value, {
        onSuccess: () => emit('update:visible', false)
    })
}

const emitClose = () => emit('update:visible', false)
</script>

<template>
<Dialog header="Materialanforderung bearbeiten" :visible="visible" modal :closable="false" style="width: 500px">
    <div class="flex flex-col gap-3">
        <label>Projekt</label>
        <Dropdown :options="projekte" optionLabel="name" v-model="form.projekt" placeholder="Projekt wählen" />

        <label>Kostenstelle</label>
        <InputText v-model="form.kostenstelle" placeholder="Kostenstelle eingeben" />

        <label>Ersteller</label>
        <Dropdown :options="user" optionLabel="name" v-model="form.ersteller_id" placeholder="User wählen" />

        <label>Bemerkungen</label>
        <InputText v-model="form.bemerkungen" placeholder="Optional" />
    </div>

    <template #footer>
        <Button label="Abbrechen" class="p-button-text" @click="emitClose" />
        <Button label="Speichern" class="p-button-primary" @click="submit" />
    </template>
</Dialog>
</template>