<script setup>
import { Head, useForm } from '@inertiajs/vue3'
import AuthenticationCard from '@/Components/AuthenticationCard.vue'
import AuthenticationCardLogo from '@/Components/AuthenticationCardLogo.vue'
import InputError from '@/Components/InputError.vue'
import InputLabel from '@/Components/InputLabel.vue'
import PrimaryButton from '@/Components/PrimaryButton.vue'
import TextInput from '@/Components/TextInput.vue'

const props = defineProps({
    token: String,
    email: String,
    employeeName: String,
    expiresAt: String,
})

const form = useForm({
    password: '',
    password_confirmation: '',
})

const submit = () => {
    form.post(route('staff-invitation.accept', props.token), {
        onFinish: () => form.reset('password', 'password_confirmation'),
    })
}
</script>

<template>
    <Head title="Mitarbeiterkonto aktivieren" />

    <AuthenticationCard>
        <AuthenticationCardLogo />

        <form @submit.prevent="submit">
            <h1 class="text-center text-xl font-bold text-gray-900">Willkommen, {{ employeeName }}</h1>
            <p class="mt-2 text-center text-sm text-gray-600">
                Legen Sie Ihr persönliches Passwort für <strong>{{ email }}</strong> fest.
                Der Einladungslink kann nur einmal verwendet werden.
            </p>

            <div class="mt-6">
                <InputLabel for="password" value="Passwort" />
                <TextInput
                    id="password"
                    v-model="form.password"
                    type="password"
                    class="mt-1 block w-full"
                    required
                    autofocus
                    autocomplete="new-password"
                />
                <p class="mt-1 text-xs text-gray-500">Mindestens 10 Zeichen mit Groß- und Kleinbuchstaben sowie einer Zahl.</p>
                <InputError class="mt-2" :message="form.errors.password" />
            </div>

            <div class="mt-4">
                <InputLabel for="password_confirmation" value="Passwort wiederholen" />
                <TextInput
                    id="password_confirmation"
                    v-model="form.password_confirmation"
                    type="password"
                    class="mt-1 block w-full"
                    required
                    autocomplete="new-password"
                />
                <InputError class="mt-2" :message="form.errors.password_confirmation" />
            </div>

            <PrimaryButton class="mt-6 w-full justify-center" :disabled="form.processing" :class="{ 'opacity-50': form.processing }">
                {{ form.processing ? 'Konto wird aktiviert …' : 'Konto aktivieren' }}
            </PrimaryButton>
        </form>
    </AuthenticationCard>
</template>
