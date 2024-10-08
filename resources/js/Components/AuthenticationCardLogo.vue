<script setup>
import { Link } from '@inertiajs/vue3';
</script>

<template>
    <div class="border px-20 py-5 bg-white rounded-lg shadow-sm">
        <Link :href="'/'">
            <img :src="'/img/logo/logoSimpliste.png'" alt="Logo" width="200">
        </Link>
    </div>
</template>
