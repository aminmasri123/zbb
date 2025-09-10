<script setup>
import { usePage } from "@inertiajs/vue3";
import { watch } from "vue";
import Swal from "sweetalert2";

const page = usePage();

watch(
  () => page.props.flash,
  (flash) => {
    if (flash.success) {
      Swal.fire({
        icon: "success",
        title: "Erfolg",
        text: flash.success,
        timer: 3000,
        showConfirmButton: false,
      });
    }

    if (flash.error) {
      Swal.fire({
        icon: "error",
        title: "Fehler",
        text: flash.error,
      });
    }
  },
  { deep: true }
);
</script>

<template>
  <!-- Diese Komponente rendert nichts, sie kümmert sich nur um Popups -->
  <div></div>
</template>
