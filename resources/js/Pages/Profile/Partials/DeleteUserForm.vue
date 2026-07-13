<script setup>
import { computed } from 'vue';
import { useForm, usePage } from '@inertiajs/vue3';
import ActionSection from '@/Components/ActionSection.vue';
import InputError from '@/Components/InputError.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';

const page = usePage();
const form = useForm({
    request_details: '',
});

const deletionRequest = computed(() => page.props.accountDeletionRequest);
const hasOpenDeletionRequest = computed(() => Boolean(deletionRequest.value) || form.wasSuccessful);

const statusLabels = {
    submitted: 'Eingereicht',
    approved: 'Freigegeben',
};

const submittedAt = computed(() => {
    if (!deletionRequest.value?.created_at) {
        return null;
    }

    return new Date(deletionRequest.value.created_at).toLocaleString('de-DE');
});

function submitDeletionRequest() {
    if (hasOpenDeletionRequest.value || form.processing) {
        return;
    }

    form.post(route('account-deletion-requests.store'), {
        preserveScroll: true,
        onSuccess: () => form.reset('request_details'),
    });
}
</script>

<template>
    <ActionSection>
        <template #title>
            Konto löschen
        </template>

        <template #description>
            Löschung nur per Antrag.
        </template>

        <template #content>
            <div class="max-w-xl text-sm leading-6 text-gray-600">
                Mitarbeiterkonten können nicht direkt selbst gelöscht werden. Reichen Sie stattdessen einen
                Löschantrag ein; die Administration prüft den Antrag und führt die Löschung kontrolliert durch.
            </div>

            <div
                v-if="hasOpenDeletionRequest"
                class="mt-5 max-w-xl rounded border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900"
            >
                <p class="font-semibold">
                    Löschantrag {{ statusLabels[deletionRequest?.status] || 'eingereicht' }}
                </p>
                <p v-if="submittedAt" class="mt-1">
                    Eingereicht am {{ submittedAt }}.
                </p>
                <p class="mt-1">
                    Der direkte Konto-Löschbutton bleibt deaktiviert, bis die Administration den Antrag bearbeitet.
                </p>
            </div>

            <form v-else class="mt-5 max-w-xl space-y-4" @submit.prevent="submitDeletionRequest">
                <label class="block text-sm font-medium text-gray-700" for="account-deletion-details">
                    Begründung oder Hinweis für die Administration
                </label>
                <textarea
                    id="account-deletion-details"
                    v-model="form.request_details"
                    rows="4"
                    maxlength="5000"
                    class="block w-full rounded border-gray-300 text-sm shadow-sm focus:border-orange-500 focus:ring-orange-500"
                    placeholder="Optional"
                ></textarea>
                <InputError :message="form.errors.request_details" />

                <div>
                    <PrimaryButton :disabled="form.processing">
                        {{ form.processing ? 'Wird eingereicht...' : 'Löschantrag erstellen' }}
                    </PrimaryButton>
                </div>

                <p v-if="form.wasSuccessful" class="text-sm font-medium text-green-600">
                    Ihr Löschantrag wurde eingereicht.
                </p>
            </form>

            <div class="mt-5">
                <button
                    type="button"
                    class="cursor-not-allowed rounded bg-gray-300 px-4 py-2 text-xs font-semibold uppercase tracking-widest text-gray-600"
                    disabled
                >
                    Konto direkt löschen deaktiviert
                </button>
            </div>
        </template>
    </ActionSection>
</template>
