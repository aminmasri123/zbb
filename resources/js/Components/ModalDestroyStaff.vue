<script setup>
import { computed, ref } from 'vue';
import axios from 'axios';

const props = defineProps({
    person: {
        type: Object,
        required: true,
    },
});

const emit = defineEmits(['close', 'deleted']);
const confirmation = ref('');
const errorMessage = ref('');
const deleting = ref(false);
const confirmed = computed(() => confirmation.value === 'delete');

const close = () => {
    if (!deleting.value) emit('close');
};

const destroyStaff = async () => {
    if (!confirmed.value || deleting.value) {
        errorMessage.value = 'Bitte geben Sie exakt „delete“ ein.';
        return;
    }

    deleting.value = true;
    errorMessage.value = '';

    try {
        const response = await axios.delete(route('user.staff.destroy', props.person.id), {
            data: { confirmation: confirmation.value },
        });

        emit('deleted', {
            personId: response.data.person_id,
            message: response.data.message,
        });
        emit('close');
    } catch (error) {
        errorMessage.value = error.response?.data?.message
            || error.response?.data?.errors?.confirmation?.[0]
            || 'Der Mitarbeiter konnte nicht vollständig gelöscht werden.';
    } finally {
        deleting.value = false;
    }
};
</script>

<template>
    <div
        class="fixed inset-0 z-50 flex items-center justify-center bg-black/55 p-4 backdrop-blur-sm"
        role="dialog"
        aria-modal="true"
        aria-labelledby="destroy-staff-title"
        @keydown.esc="close"
    >
        <div class="w-full max-w-lg overflow-hidden rounded-xl bg-white shadow-2xl">
            <div class="flex items-start justify-between border-b px-6 py-5">
                <div>
                    <h3 id="destroy-staff-title" class="text-xl font-bold text-gray-900">
                        Mitarbeiter vollständig löschen?
                    </h3>
                    <p class="mt-1 text-sm text-gray-500">{{ person.name }} · ID {{ person.id }}</p>
                </div>
                <button
                    type="button"
                    class="rounded p-2 text-gray-500 hover:bg-gray-100 hover:text-gray-800"
                    :disabled="deleting"
                    aria-label="Dialog schließen"
                    @click="close"
                >
                    <i class="la la-times text-xl"></i>
                </button>
            </div>

            <div class="space-y-5 px-6 py-5">
                <div class="rounded-lg border border-red-300 bg-red-50 p-4 text-sm text-red-900">
                    <div class="flex gap-3">
                        <i class="las la-exclamation-triangle mt-0.5 text-xl text-red-600"></i>
                        <div>
                            <p class="font-bold">Diese Aktion kann nicht rückgängig gemacht werden.</p>
                            <p class="mt-1">
                                Der Mitarbeiter-Stammsatz und ein eventuell vorhandenes Login-Konto werden dauerhaft gelöscht.
                                Geschützte verknüpfte Daten können die Löschung blockieren.
                            </p>
                        </div>
                    </div>
                </div>

                <div>
                    <label for="staff-delete-confirmation" class="mb-2 block text-sm font-medium text-gray-800">
                        Zur Bestätigung <strong>delete</strong> eingeben
                    </label>
                    <input
                        id="staff-delete-confirmation"
                        v-model="confirmation"
                        type="text"
                        autocomplete="off"
                        class="w-full rounded-lg border-gray-300 focus:border-red-500 focus:ring-red-500"
                        placeholder="delete"
                        :disabled="deleting"
                        @keyup.enter="destroyStaff"
                    />
                    <p v-if="errorMessage" class="mt-2 text-sm font-medium text-red-700" role="alert">
                        {{ errorMessage }}
                    </p>
                </div>
            </div>

            <div class="flex flex-col-reverse gap-2 border-t bg-gray-50 px-6 py-4 sm:flex-row sm:justify-end">
                <button
                    type="button"
                    class="rounded-lg border border-gray-300 bg-white px-4 py-2 font-medium text-gray-700 hover:bg-gray-100"
                    :disabled="deleting"
                    @click="close"
                >
                    Abbrechen
                </button>
                <button
                    type="button"
                    class="rounded-lg bg-red-600 px-4 py-2 font-semibold text-white hover:bg-red-700 disabled:cursor-not-allowed disabled:opacity-50"
                    :disabled="!confirmed || deleting"
                    @click="destroyStaff"
                >
                    <i v-if="deleting" class="las la-spinner la-spin mr-1"></i>
                    {{ deleting ? 'Wird gelöscht …' : 'Endgültig löschen' }}
                </button>
            </div>
        </div>
    </div>
</template>
