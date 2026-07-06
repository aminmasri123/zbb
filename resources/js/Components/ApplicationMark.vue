<script setup>
const logoSrc = window.asset
    ? window.asset('img/logo/logoSimpliste.png')
    : `${window.assetBaseUrl || ''}/img/logo/logoSimpliste.png`;
</script>

<template>
    <img :src="logoSrc" alt="Logo">
</template>
