<script setup>
import { usePage } from '@inertiajs/vue3'
import { ref, watch } from 'vue'
import Swal from 'sweetalert2'

const page = usePage()
const displayedSuccess = ref(null)
const displayedError = ref(null)
const displayedValidationErrors = ref(null)

watch(
    () => page.props.flash?.success || null,
    (message) => {
        if (!message) {
            displayedSuccess.value = null
            return
        }

        if (displayedSuccess.value === message) return
        displayedSuccess.value = message

        Swal.fire({
            icon: 'success',
            title: 'Erfolg',
            text: message,
            timer: 2500,
            showConfirmButton: false,
            toast: true,
            position: 'center',
        })
    },
    { immediate: true },
)

watch(
    () => page.props.flash?.error || null,
    (message) => {
        if (!message) {
            displayedError.value = null
            return
        }

        if (displayedError.value === message) return
        displayedError.value = message

        Swal.fire({
            icon: 'error',
            title: 'Fehler',
            text: message,
        })
    },
    { immediate: true },
)

watch(
    () => JSON.stringify(page.props.errors || {}),
    (fingerprint) => {
        const errors = JSON.parse(fingerprint)
        if (Object.keys(errors).length === 0) {
            displayedValidationErrors.value = null
            return
        }

        if (displayedValidationErrors.value === fingerprint) return
        displayedValidationErrors.value = fingerprint

        Swal.fire({
            icon: 'error',
            title: 'Validierungsfehler',
            html: Object.values(errors).join('<br>'),
        })
    },
    { immediate: true },
)
</script>

<template>
    <div></div>
</template>
