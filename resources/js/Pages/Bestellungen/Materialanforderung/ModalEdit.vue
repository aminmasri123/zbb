<script setup>
import { ref, watch } from 'vue'
import { router } from '@inertiajs/vue3'
import Dialog from 'primevue/dialog'
import InputText from 'primevue/inputtext'
import Button from 'primevue/button'

const props = defineProps({
    visible: Boolean,
    item: Object,
})

const emit = defineEmits(['update:visible'])

const form = ref({
    kostenstelle: '',
    bemerkungen: '',
    artikeln: [],
})

watch(() => props.item, (newVal) => {
    if (newVal) {
        form.value.kostenstelle = newVal.kostenstelle
        form.value.bemerkungen = newVal.bemerkungen
        form.value.artikeln = (newVal.artikeln || []).map((artikel) => ({ ...artikel }))
    }
}, { immediate: true })

const submit = () => {
    if (!props.item) return

    router.put(route('materialanforderung.update'), {
        ...form.value,
        id: props.item.id,
    }, {
        onSuccess: () => emit('update:visible', false)
    })
}

const emitClose = () => emit('update:visible', false)
</script>

<template>
<Dialog header="Materialanforderung bearbeiten" :visible="visible" modal :closable="false" style="width: 500px">
    <div class="flex flex-col gap-3">
        <label>Kostenstelle</label>
        <InputText v-model="form.kostenstelle" placeholder="Kostenstelle eingeben" />

        <label>Bemerkungen</label>
        <InputText v-model="form.bemerkungen" placeholder="Optional" />
    </div>

    <template #footer>
        <Button label="Abbrechen" class="p-button-text" @click="emitClose" />
        <Button label="Speichern" class="p-button-primary" @click="submit" />
    </template>
</Dialog>
</template>
