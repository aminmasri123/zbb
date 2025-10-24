
<script setup>
    import { switchTheme } from '../../theme';
    import { Link } from '@inertiajs/vue3';
    defineProps({
        canLogin: Boolean,
        canRegister: Boolean,
    });
</script>
<template>

    <div v-if="canLogin" class="absolute top-0 right-0 p-6 text-right">
        <button
            @click="switchTheme"
            class="pr-3 text-sm font-medium rounded-md text-gray-900 hover:text-gray-900 dark:text-gray-400 dark:hover:text-gray-100 focus:outline-none transition ease-in-out duration-150">
                <i class="las la-adjust text-lg"></i>
        </button>
        <Link v-if="$page.props.auth.user" :href="route('dashboard')" class="font-semibold text-gray-600 hover:text-gray-900 dark:text-gray-400 dark:hover:text-white">Dashboard</Link>


        <template v-else>
            <Link :href="route('login')" class="font-semibold text-gray-600 hover:text-gray-900 dark:text-gray-400 dark:hover:text-white">Log in</Link>
            <Link v-if="canRegister" :href="route('register')" class="ml-4 font-semibold text-gray-600 hover:text-gray-900 dark:text-gray-400 dark:hover:text-white">Register</Link>
        </template>
    </div>

</template>
