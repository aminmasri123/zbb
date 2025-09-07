<script setup>
import { nextTick, ref } from 'vue';
import { Head, useForm } from '@inertiajs/vue3';
import AuthenticationCard from '@/Components/AuthenticationCard.vue';
import AuthenticationCardLogo from '@/Components/AuthenticationCardLogo.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';

const recovery = ref(false);

const form = useForm({
    code: '',
    recovery_code: '',
});

const recoveryCodeInput = ref(null);
const codeInput = ref(null);

const toggleRecovery = async () => {
    recovery.value ^= true;

    await nextTick();

    if (recovery.value) {
        recoveryCodeInput.value.focus();
        form.code = '';
    } else {
        codeInput.value.focus();
        form.recovery_code = '';
    }
};

const submit = () => {
    form.post(route('two-factor.login'));
};
</script>

<template>
    <Head title="{{$t('Zwei-Faktor-Bestätigung')}}" />

    <AuthenticationCard>
        <template #logo>
        </template>

        <div class="mb-4 text-sm text-gray-600">
            <AuthenticationCardLogo />

            <template v-if="! recovery">
                {{ $t('Bitte_bestätigen_Sie_den_Zugriff_auf_Ihr_Konto_indem_Sie_den_Authentifizierungscode_eingeben_der_von_Ihrer_Authentifizierungsanwendung_bereitgestellt_wurde.') }}
            </template>

            <template v-else>
                {{ $t('Bitte_bestätigen_Sie_den_Zugriff_auf_Ihr_Konto_indem_Sie_einen_Ihrer_Notfallwiederherstellungscodes_eingeben.') }}
            </template>
        </div>

        <form @submit.prevent="submit">

            <div v-if="! recovery">
                <InputLabel for="code" value="{{$t('Code')}}" />
                <TextInput
                    id="code"
                    ref="codeInput"
                    v-model="form.code"
                    type="text"
                    inputmode="numeric"
                    class="mt-1 block w-full"
                    autofocus
                    autocomplete="one-time-code"
                />
                <InputError class="mt-2" :message="form.errors.code" />
            </div>

            <div v-else>
                <InputLabel for="recovery_code" value="{{$t('Wiederherstellungscode')}}" />
                <TextInput
                    id="recovery_code"
                    ref="recoveryCodeInput"
                    v-model="form.recovery_code"
                    type="text"
                    class="mt-1 block w-full"
                    autocomplete="one-time-code"
                />
                <InputError class="mt-2" :message="form.errors.recovery_code" />
            </div>

            <div class="flex items-center justify-end mt-4">
                <button type="button" class="text-sm text-gray-600 hover:text-gray-900 underline cursor-pointer" @click.prevent="toggleRecovery">
                    <template v-if="! recovery">
                        {{$t('Wiederherstellungscode')}}
                    </template>

                    <template v-else>
                        {{$t('Wiederherstellungscode_verwenden')}}
                    </template>
                </button>

                <PrimaryButton class="ml-4" :class="{ 'opacity-25': form.processing }" :disabled="form.processing">
                    Log in
                </PrimaryButton>
            </div>
        </form>
    </AuthenticationCard>
</template>
