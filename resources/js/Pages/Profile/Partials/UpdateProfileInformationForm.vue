<script setup>
import { onMounted, ref, watch } from 'vue';
import { Link, router, useForm } from '@inertiajs/vue3';
import axios from 'axios';
import ActionMessage from '@/Components/ActionMessage.vue';
import FormSection from '@/Components/FormSection.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import { setTheme } from '@/theme';

const props = defineProps({
    user: Object,
});

const form = useForm({
    _method: 'PUT',
    name: props.user.username,
    email: props.user.email,
    theme: props.user.theme || 'air',
    photo: null,
});

const themeOptions = [
    { key: 'air', label: 'Air', colors: ['#0ea5e9', '#10b981', '#f7fbff'] },
    { key: 'dark', label: 'Dark', colors: ['#0c1016', '#60a5fa', '#f4f7fb'] },
    { key: 'womanly', label: 'Womanly', colors: ['#be185d', '#f1cfe0', '#fff7fb'] },
    { key: 'champion', label: 'Champion', colors: ['#b45309', '#f2d89b', '#fffaf0'] },
    { key: 'sprint', label: 'Sprint', colors: ['#059669', '#bfe8d8', '#f5fff9'] },
    { key: 'arena', label: 'Arena', colors: ['#334155', '#cbd5e1', '#f8fafc'] },
    { key: 'pulse', label: 'Pulse', colors: ['#ea580c', '#fed7aa', '#fff7ed'] },
    { key: 'trail', label: 'Trail', colors: ['#4d7c0f', '#d6dfc6', '#f6f8f2'] },
    { key: 'bazaar', label: 'Bazaar', colors: ['#ff8a00', '#00a8c6', '#e7f8fb'] },
    { key: 'vital', label: 'Vital', colors: ['#6CC63A', '#262827', '#FFFFFF'] },
];

const selectTheme = (theme) => {
    form.theme = theme;
    setTheme(theme);
    persistTheme(theme);
};

const persistTheme = async (theme) => {
    try {
        await axios.post(route('user.theme.update'), { theme });
    } catch (error) {
        console.error('Theme konnte nicht gespeichert werden:', error);
    }
};

watch(() => form.theme, (theme) => {
    setTheme(theme || 'air');
});

onMounted(() => {
    setTheme(form.theme || 'air');
});

const verificationLinkSent = ref(null);
const photoPreview = ref(null);
const photoInput = ref(null);

const updateProfileInformation = () => {
    if (photoInput.value) {
        form.photo = photoInput.value.files[0];
    }

    form.post(route('user-profile-information.update'), {
        errorBag: 'updateProfileInformation',
        preserveScroll: true,
        onSuccess: () => clearPhotoFileInput(),
    });
};

const sendEmailVerification = () => {
    verificationLinkSent.value = true;
};

const selectNewPhoto = () => {
    photoInput.value.click();
};

const updatePhotoPreview = () => {
    const photo = photoInput.value.files[0];

    if (! photo) return;

    const reader = new FileReader();

    reader.onload = (e) => {
        photoPreview.value = e.target.result;
    };

    reader.readAsDataURL(photo);
};

const deletePhoto = () => {
    router.delete(route('current-user-photo.destroy'), {
        preserveScroll: true,
        onSuccess: () => {
            photoPreview.value = null;
            clearPhotoFileInput();
        },
    });
};

const clearPhotoFileInput = () => {
    if (photoInput.value?.value) {
        photoInput.value.value = null;
    }
};
</script>

<template>
    <FormSection @submitted="updateProfileInformation">
        <template #title>
            {{$t('profilinformationen')}}
        </template>

        <template #description>
            {{$t('aktualisieren_sie_die_profilinformationen_und_die_a-Mail-adresse_ihres_Kontos.')}}
        </template>

        <template #form>
            <!-- Profile Photo -->
            <div v-if="$page.props.jetstream.managesProfilePhotos" class="col-span-6 sm:col-span-4">
                <!-- Profile Photo File Input -->
                <input
                    ref="photoInput"
                    type="file"
                    class="hidden"
                    @change="updatePhotoPreview"
                >

                <InputLabel for="photo" value="Photo" />

                <!-- Current Profile Photo -->
                <div v-show="! photoPreview" class="mt-2">
                    <img :src="`/storage/${$page.props.auth.user.profile_photo_path}`" :alt="user.name" class="rounded-full h-20 w-20 object-cover">
                </div>

                <!-- New Profile Photo Preview -->
                <div v-show="photoPreview" class="mt-2">
                    <span
                        class="block rounded-full w-20 h-20 bg-cover bg-no-repeat bg-center"
                        :style="'background-image: url(\'' + photoPreview + '\');'"
                    />
                </div>

                <SecondaryButton class="mt-2 mr-2" type="button" @click.prevent="selectNewPhoto">
                    {{ $t('Wählen_Sie_ein_neues_Foto_aus') }}
                </SecondaryButton>

                <SecondaryButton
                    v-if="user.profile_photo_path"
                    type="button"
                    class="mt-2"
                    @click.prevent="deletePhoto"
                >
                    {{ $t('Foto_entfernen') }}

                </SecondaryButton>

                <InputError :message="form.errors.photo" class="mt-2" />
            </div>

            <!-- Name -->
            <div class="col-span-6 sm:col-span-4">
                <InputLabel for="name" :value="$t('Benutzername')" />
                <TextInput
                    id="name"
                    v-model="form.name"
                    type="text"
                    class="mt-1 block w-full"
                    autocomplete="name"
                />
                <InputError :message="form.errors.name" class="mt-2" />
            </div>

            <!-- Email -->
            <div class="col-span-6 sm:col-span-4">
                <InputLabel for="email" :value="$t('Email')" />
                <TextInput
                    id="email"
                    v-model="form.email"
                    type="email"
                    class="mt-1 block w-full"
                    autocomplete="username"
                />
                <InputError :message="form.errors.email" class="mt-2" />

                <div v-if="$page.props.jetstream.hasEmailVerification && user.email_verified_at === null">
                    <p class="text-sm mt-2">
                        {{ $t('Ihre_E-Mail-Adresse_ist_nicht_verifiziert.') }}

                        <Link
                            :href="route('verification.send')"
                            method="post"
                            as="button"
                            class="underline text-sm text-gray-600 hover:text-gray-900 rounded-md focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500"
                            @click.prevent="sendEmailVerification"
                        >
                            {{ $t('Klicken_Sie_hier_um_die_Bestätigungs-E-Mail_erneut_zu_senden.') }}
                        </Link>
                    </p>

                    <div v-show="verificationLinkSent" class="mt-2 font-medium text-sm text-green-600">
                        {{ $t('Ein_neuer_Bestätigungslink_wurde_an_die_E-Mail-Adresse_gesendet_die_Sie_in_Ihren_Profil-Einstellungen_bereitgestellt_haben.') }}
                    </div>
                </div>
            </div>

            <div class="col-span-6">
                <InputLabel value="Theme" />
                <div class="mt-2 grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-5">
                    <button
                        v-for="themeOption in themeOptions"
                        :key="themeOption.key"
                        type="button"
                        class="rounded-md border p-3 text-left transition hover:border-[var(--borderHover)] hover:bg-[var(--surfaceTint)]"
                        :class="form.theme === themeOption.key ? 'border-[var(--buttonPrimary)] bg-[var(--surfaceTint)] ring-2 ring-[var(--buttonPrimary)]/20' : 'border-[var(--border)] bg-[var(--card)]'"
                        @click="selectTheme(themeOption.key)"
                    >
                        <span class="flex gap-1">
                            <span
                                v-for="color in themeOption.colors"
                                :key="color"
                                class="h-5 flex-1 rounded-sm border border-black/10"
                                :style="{ backgroundColor: color }"
                            />
                        </span>
                        <span class="mt-2 block text-sm font-semibold text-[var(--primary)]">{{ themeOption.label }}</span>
                    </button>
                </div>
                <InputError :message="form.errors.theme" class="mt-2" />
            </div>
        </template>

        <template #actions>
            <ActionMessage :on="form.recentlySuccessful" class="mr-3">
                {{ $t('Gespeichert.') }}
            </ActionMessage>

            <PrimaryButton :class="{ 'opacity-25': form.processing }" :disabled="form.processing">
                {{ $t('Speichern') }}
            </PrimaryButton>
        </template>
    </FormSection>
</template>
