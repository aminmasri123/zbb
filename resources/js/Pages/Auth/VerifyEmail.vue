<script setup>
import { computed } from 'vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import AuthenticationCard from '@/Components/AuthenticationCard.vue';
import AuthenticationCardLogo from '@/Components/AuthenticationCardLogo.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';

const props = defineProps({
    status: String,
});

const form = useForm({});

const submit = () => {
    form.post(route('verification.send'));
};

const verificationLinkSent = computed(() => props.status === 'verification-link-sent');
</script>

<template>
    <Head :title="$t('E-Mail-Bestätigung')" />

    <AuthenticationCard>
            <AuthenticationCardLogo/>
        <div class="my-4  text-sm text-gray-600 text-justify">
            {{ $t('Bevor_Sie_fortfahren_könnten_Sie_bitte_Ihre_E-Mail-Adresse_bestätigen_indem_Sie_auf_den_Link_klicken_den_wir_Ihnen_gerade_per_E-Mail_gesendet_haben._Wenn_Sie_die_E-Mail_nicht_erhalten_haben_senden_wir_Ihnen_gerne_eine_neue.') }}
        </div>

        <div v-if="verificationLinkSent" class="mb-4 font-medium text-sm text-green-600">
            {{ $t('Ein_neuer_Bestätigungslink_wurde_an_die_E-Mail-Adresse_gesendet_die_Sie_in_Ihren_Profil-Einstellungen_bereitgestellt_haben.') }}
        </div>

        <form @submit.prevent="submit">
            <div class="mt-4 block text-center ">
                <PrimaryButton :class="{ 'opacity-25': form.processing}" class="text-sm" :disabled="form.processing">

                    {{ $t('Bestätigungslink erneut senden') }}
                </PrimaryButton>

                <div class="flex items-center text-center justify-center mt-2">
                    <Link
                        :href="route('profile.show')"
                        class="underline text-sm mx-4 text-gray-600 hover:text-gray-900 rounded-md focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500"
                    >
                        {{ $t('Profil_bearbeiten') }}</Link>

                    <Link
                        :href="route('logout')"
                        method="post"
                        as="button"
                        class="underline text-sm mx-4 text-gray-600 hover:text-gray-900 rounded-md focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 ml-2"
                    >
                        {{ $t('Abmelden') }}
                    </Link>
                </div>
            </div>
        </form>
    </AuthenticationCard>
</template>
