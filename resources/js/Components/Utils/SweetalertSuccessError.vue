<script setup>
import { usePage } from "@inertiajs/vue3";
import { watchEffect } from "vue";
import Swal from "sweetalert2";

const page = usePage();

// 🔹 Automatisch auf Flash-Änderungen reagieren
watchEffect(() => {
  const flash = page.props.flash;

  if (flash?.success) {
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

  if (flash?.error) {
    Swal.fire({
      icon: "error",
      title: "Fehler",
      text: flash.error,
      toast: true,
      position: "center",
    });
  }
});
</script>

<template>
  <!-- Diese Komponente zeigt selbst nichts an -->
  <div></div>
</template>
