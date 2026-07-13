<script setup>
import { Head, Link } from '@inertiajs/vue3';
import ApplicationMark from '../Components/ApplicationMark.vue';
import LoginRegister from '../Components/Header/LoginRegister.vue';

defineProps({
    canLogin: Boolean,
    canRegister: Boolean,
    laravelVersion: String,
    phpVersion: String,
});
</script>

<template>
    <Head title="Matrix" />

    <div class="relative flex justify-center items-center min-h-screen bg-dots-darker bg-center bg-gray-100 dark:bg-dots-lighter dark:bg-gray-900 selection:bg-red-500 selection:text-white">

        <LoginRegister :can-login="canLogin" :can-register="canRegister" />

        <div class="bg-white dark:bg-gray-100 p-8 max-w-2xl w-full border border-zbb dark:border-none rounded-lg shadow-lg text-center">
                    <ApplicationMark class="block h-20 w-auto mb-6 mx-auto" />

            <p class="md:text-xl sm:text-sm font-semibold text-gray-900">
                Willkommen bei der ZBB - Matrix - Anwendung
            </p>
            <p class="md:text-lg sm:text-sm text-gray-600">Ihre digitale Lösung zur effizienten Verwaltung und zur Überwindung von Kommunikationsbarrieren.</p>
        </div>
    </div>

</template>

<style>
.bg-dots-darker {
    background-image: url("data:image/svg+xml,%3Csvg width='30' height='30' viewBox='0 0 30 30' fill='none' xmlns='http://www.w3.org/2000/svg'%3E%3Cpath d='M1.22676 0C1.91374 0 2.45351 0.539773 2.45351 1.22676C2.45351 1.91374 1.91374 2.45351 1.22676 2.45351C0.539773 2.45351 0 1.91374 0 1.22676C0 0.539773 0.539773 0 1.22676 0Z' fill='rgba(0,0,0,0.07)'/%3E%3C/svg%3E");
}
@media (prefers-color-scheme: dark) {
    .dark\:bg-dots-lighter {
        background-image: url("data:image/svg+xml,%3Csvg width='30' height='30' viewBox='0 0 30 30' fill='none' xmlns='http://www.w3.org/2000/svg'%3E%3Cpath d='M1.22676 0C1.91374 0 2.45351 0.539773 2.45351 1.22676C2.45351 1.91374 1.91374 2.45351 1.22676 2.45351C0.539773 2.45351 0 1.91374 0 1.22676C0 0.539773 0.539773 0 1.22676 0Z' fill='rgba(255,255,255,0.07)'/%3E%3C/svg%3E");
    }
}
</style>
