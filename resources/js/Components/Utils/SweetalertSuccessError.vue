<script setup>
import { usePage } from "@inertiajs/vue3";
import { watchEffect } from "vue";
import Swal from "sweetalert2";

const page = usePage();

watchEffect(() => {
    const flash = page.props.flash || {};
    const errors = page.props.errors || {};

    // ✅ Erfolgsmeldung
    if (flash.success) {
        Swal.fire({
            icon: "success",
            title: "Erfolg",
            text: flash.success,
            timer: 2500,
            showConfirmButton: false,
            toast: true,
            position: "center",
        });
    }

    // ✅ Eigene Fehlermeldung (flash.error)
    if (flash.error) {
        Swal.fire({
            icon: "error",
            title: "Fehler",
            text: flash.error,
        });
    }

    // ✅ Laravel Validator-Fehler anzeigen
    if (Object.keys(errors).length > 0) {
        Swal.fire({
            icon: "error",
            title: "Validierungsfehler",
            html: Object.values(errors).join("<br>"),
        });
    }
});
</script>


<template>
  <!-- Diese Komponente zeigt selbst nichts an -->
  <div></div>
</template>
