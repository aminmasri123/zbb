<script setup>
import { computed, ref } from 'vue';
import { Head } from '@inertiajs/vue3';
import Swal from 'sweetalert2';
import axios from 'axios';

const props = defineProps({
    context: Object,
    bereiche: Array,
    token: String,
});

const accessCode = ref('');
const checking = ref(false);
const saving = ref(false);
const teilnehmer = ref(null);
const choices = ref([]);
const logoSrc = window.asset
    ? window.asset('img/logo/logo.png')
    : `${window.assetBaseUrl || ''}/img/logo/logo.png`;

const normalizedCode = computed(() => accessCode.value.trim().toUpperCase());

const activeChoices = computed(() => (
    choices.value.slice(0, props.context.auswahl_anzahl)
));

const isComplete = computed(() => activeChoices.value.every(Boolean));

const hasDuplicates = computed(() => {
    const filled = activeChoices.value.filter(Boolean);
    return new Set(filled.map(Number)).size !== filled.length;
});

const isOptionDisabled = (bereichId, choiceIndex) => (
    activeChoices.value.some((choice, index) => index !== choiceIndex && Number(choice) === Number(bereichId))
);

const setChoice = (choiceIndex, bereichId) => {
    if (isOptionDisabled(bereichId, choiceIndex) || saving.value) return;

    choices.value[choiceIndex] = Number(bereichId);
};

const verifyCode = async () => {
    checking.value = true;

    try {
        const response = await axios.post(route('bereichsauswahl.self.verify', props.token), {
            access_code: normalizedCode.value,
        });

        teilnehmer.value = response.data.teilnehmer;
        choices.value = Array.from({ length: props.context.auswahl_anzahl }, (_, index) => (
            response.data.teilnehmer.choices[index] ?? ''
        ));
    } catch (error) {
        teilnehmer.value = null;
        Swal.fire({
            title: 'Code nicht gefunden',
            text: error.response?.data?.message || error.response?.data?.errors?.access_code?.[0] || 'Bitte pruefe den Code noch einmal.',
            icon: 'error',
        });
    } finally {
        checking.value = false;
    }
};

const saveChoices = async () => {
    if (!isComplete.value) {
        Swal.fire({
            title: 'Unvollständig',
            text: `Bitte ${props.context.auswahl_anzahl} Bereiche auswählen.`,
            icon: 'warning',
        });
        return;
    }

    if (hasDuplicates.value) {
        Swal.fire({
            title: 'Doppelte Auswahl',
            text: 'Jeder Bereich darf nur einmal ausgewaehlt werden.',
            icon: 'warning',
        });
        return;
    }

    saving.value = true;

    try {
        const response = await axios.post(route('bereichsauswahl.self.store', props.token), {
            access_code: normalizedCode.value,
            choices: activeChoices.value.map(Number),
        });

        window.location.href = response.data.redirect_url;
    } catch (error) {
        Swal.fire({
            title: 'Fehler',
            text: error.response?.data?.message || error.response?.data?.errors?.choices?.[0] || 'Die Auswahl konnte nicht gespeichert werden.',
            icon: 'error',
        });
    } finally {
        saving.value = false;
    }
};
</script>

<template>
    <Head title="Bereichsauswahl" />

    <main class="min-h-screen bg-gray-100 text-gray-900">
        <div class="mx-auto max-w-3xl px-4 py-6 sm:py-10">
            <div class="mb-6 flex items-center gap-3">
                <img :src="logoSrc" alt="ZBB" class="h-12 w-auto" />
                <div>
                    <h1 class="text-xl font-bold">Bereichsauswahl</h1>
                    <p class="text-sm text-gray-600">{{ context.schule }} · {{ context.schuljahr }} · Teil {{ context.teil }}</p>
                </div>
            </div>

            <section v-if="!teilnehmer" class="bg-white border border-gray-300 shadow-sm p-4 sm:p-6">
                <form class="space-y-4" @submit.prevent="verifyCode">
                    <div>
                        <label for="access-code" class="block text-sm font-semibold text-gray-700 mb-1">
                            Identifikationscode
                        </label>
                        <input
                            id="access-code"
                            v-model="accessCode"
                            type="text"
                            autocomplete="one-time-code"
                            class="w-full border border-gray-300 px-3 py-3 text-lg font-mono uppercase focus:ring-zbb focus:border-zbb"
                            placeholder="MA-LO-47-KI"
                        />
                    </div>

                    <button
                        type="submit"
                        :disabled="checking || !normalizedCode"
                        class="inline-flex w-full items-center justify-center gap-2 bg-zbb px-4 py-3 text-white hover:bg-orange-600 disabled:opacity-50 sm:w-auto"
                    >
                        <i class="las la-unlock"></i>
                        <span>{{ checking ? 'Prueft' : 'Weiter' }}</span>
                    </button>
                </form>
            </section>

            <section v-else class="bg-white border border-gray-300 shadow-sm p-4 sm:p-6">
                <div class="mb-5 border-b border-gray-200 pb-4">
                    <p class="text-xs uppercase text-gray-500 font-semibold">Teilnehmer</p>
                    <h2 class="text-lg font-bold text-gray-900">
                        {{ teilnehmer.nachname }}, {{ teilnehmer.vorname }}
                    </h2>
                    <p class="text-sm text-gray-600">Klasse {{ teilnehmer.klasse }}</p>
                </div>

                <div class="space-y-4">
                    <div
                        v-for="(_, index) in activeChoices"
                        :key="index"
                    >
                        <p class="block text-sm font-semibold text-gray-700 mb-2">
                            Bereich {{ index + 1 }}
                        </p>
                        <div class="grid gap-2 sm:grid-cols-2">
                            <label
                                v-for="bereich in bereiche"
                                :key="bereich.id"
                                class="flex cursor-pointer items-center gap-3 border px-3 py-3 text-sm font-semibold transition"
                                :class="[
                                    Number(choices[index]) === Number(bereich.id)
                                        ? 'border-zbb bg-zbb text-white'
                                        : 'border-gray-300 bg-white text-gray-800 hover:border-zbb hover:text-zbb',
                                    isOptionDisabled(bereich.id, index) || saving
                                        ? 'cursor-not-allowed opacity-40 hover:border-gray-300 hover:text-gray-800'
                                        : ''
                                ]"
                            >
                                <input
                                    type="radio"
                                    class="h-4 w-4 border-gray-300 text-zbb focus:ring-zbb"
                                    :name="`choice-${index}`"
                                    :value="bereich.id"
                                    :checked="Number(choices[index]) === Number(bereich.id)"
                                    :disabled="isOptionDisabled(bereich.id, index) || saving"
                                    @change="setChoice(index, bereich.id)"
                                />
                                <span>{{ bereich.name }}</span>
                            </label>
                        </div>
                    </div>

                    <button
                        type="button"
                        :disabled="saving"
                        class="inline-flex w-full items-center justify-center gap-2 bg-zbb px-4 py-3 text-white hover:bg-orange-600 disabled:opacity-50 sm:w-auto"
                        @click="saveChoices"
                    >
                        <i class="las la-save"></i>
                        <span>{{ saving ? 'Speichert' : 'Auswahl speichern' }}</span>
                    </button>
                </div>
            </section>
        </div>
    </main>
</template>
