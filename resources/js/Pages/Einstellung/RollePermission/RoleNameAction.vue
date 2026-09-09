<script setup>
import axios from 'axios';
import Swal from 'sweetalert2';
import { ref } from 'vue';
const props = defineProps({ role: { type: Object, required: true } });
const emit = defineEmits(['updated']);
const busy = ref(false);
async function edit() {
    const result = await Swal.fire({
        title: 'Rollenbezeichnung bearbeiten',
        text: 'Benutzerzuordnungen und Berechtigungen bleiben erhalten.',
        input: 'text', inputLabel: 'Bezeichnung', inputValue: props.role.display_name || props.role.name,
        inputAttributes: { maxlength: '255' },
        showCancelButton: true, confirmButtonText: 'Speichern', cancelButtonText: 'Abbrechen',
        showLoaderOnConfirm: true, allowOutsideClick: () => !Swal.isLoading(),
        preConfirm: async value => {
            const display_name = value.trim();
            if (!display_name) { Swal.showValidationMessage('Bitte eine Bezeichnung eingeben.'); return false; }
            busy.value = true;
            try { return (await axios.put(route('rolle.update', props.role.id), { display_name })).data.role; }
            catch (error) { Swal.showValidationMessage(error.response?.data?.errors?.display_name?.[0] || error.response?.data?.message || 'Speichern fehlgeschlagen.'); return false; }
            finally { busy.value = false; }
        },
    });
    if (result.isConfirmed && result.value) emit('updated', result.value);
}
</script>

<template>
    <button type="button" :disabled="busy" class="w-full px-3 py-2 text-left text-sm hover:bg-gray-100 disabled:opacity-50" @click.stop="edit">Bezeichnung bearbeiten</button>
</template>
