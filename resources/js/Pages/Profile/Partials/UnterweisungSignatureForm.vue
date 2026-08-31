<script setup>
import ActionMessage from '@/Components/ActionMessage.vue';
import FormSection from '@/Components/FormSection.vue';
import InputError from '@/Components/InputError.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import { useForm } from '@inertiajs/vue3';
import { ref } from 'vue';

const input = ref(null);
const preview = ref(null);
const form = useForm({ unterschrift: null });
const deleteForm = useForm({});

const selectFile = () => input.value?.click();
const changed = (event) => {
    const file = event.target.files?.[0] || null;
    form.unterschrift = file;
    preview.value = file ? URL.createObjectURL(file) : null;
};
const save = () => form.post(route('profile.unterweisung-signature.update'), {
    preserveScroll: true,
    forceFormData: true,
    onSuccess: () => {
        form.reset();
        preview.value = null;
        if (input.value) input.value.value = '';
    },
});
const remove = () => deleteForm.delete(route('profile.unterweisung-signature.destroy'), {
    preserveScroll: true,
});
</script>

<template>
    <FormSection @submitted="save">
        <template #title>Unterschrift für Unterweisungsnachweise</template>
        <template #description>
            Ihre Unterschrift wird verschlüsselt gespeichert und beim PDF-Export Ihrer eigenen Gruppen automatisch eingesetzt.
        </template>
        <template #form>
            <div class="col-span-6 sm:col-span-4">
                <input ref="input" type="file" class="hidden" accept="image/png,image/jpeg" @change="changed">
                <div v-if="preview" class="mb-3 rounded border bg-white p-3">
                    <img :src="preview" alt="Vorschau der Unterschrift" class="h-20 max-w-full object-contain">
                </div>
                <div v-else-if="$page.props.auth.user.has_unterweisung_unterschrift" class="mb-3 rounded border border-green-200 bg-green-50 p-3 text-sm text-green-800">
                    Eine Unterschrift ist sicher hinterlegt.
                </div>
                <div v-else class="mb-3 rounded border border-amber-200 bg-amber-50 p-3 text-sm text-amber-800">
                    Noch keine Unterschrift hinterlegt. Ohne Unterschrift ist der Export nicht möglich.
                </div>
                <SecondaryButton type="button" @click="selectFile">Bild auswählen</SecondaryButton>
                <p class="mt-2 text-xs text-gray-500">PNG oder JPG, maximal 1 MB. Empfohlen: freigestellte PNG-Datei.</p>
                <InputError :message="form.errors.unterschrift" class="mt-2" />
            </div>
        </template>
        <template #actions>
            <button
                v-if="$page.props.auth.user.has_unterweisung_unterschrift"
                type="button"
                class="mr-auto text-sm font-medium text-red-600 hover:text-red-800 disabled:opacity-50"
                :disabled="deleteForm.processing"
                @click="remove"
            >Unterschrift löschen</button>
            <ActionMessage :on="form.recentlySuccessful" class="mr-3">Gespeichert.</ActionMessage>
            <PrimaryButton :disabled="form.processing || !form.unterschrift">Speichern</PrimaryButton>
        </template>
    </FormSection>
</template>
