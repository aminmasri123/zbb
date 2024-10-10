<script setup>
import { Link } from '@inertiajs/vue3';
</script>

<template>
    <div class="border px-20 py-5  bg-white rounded-lg shadow-2xl">
        <Link :href="'/'" class="">
            <img :src="'img/logo/logoSimpliste.png'" alt="Logo" width="200" class=" mx-auto">

        </Link>
    </div>
</template>
