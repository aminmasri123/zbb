<script setup>
import { Link } from '@inertiajs/vue3';

const logoSrc = window.asset
    ? window.asset('img/logo/logoSimpliste.png')
    : `${window.assetBaseUrl || ''}/img/logo/logoSimpliste.png`;
</script>

<template>
    <div class="  py-5  ">
        <Link>
            <img :src="logoSrc" alt="Logo" width="200" class=" mx-auto">

        </Link>
    </div>
</template>
