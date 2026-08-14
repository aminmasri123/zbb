<template>
    <div class="fixed inset-0 z-50 flex items-center justify-center bg-black bg-opacity-50 p-4 backdrop-blur-sm">
      <div class="max-h-[calc(100vh-2rem)] w-full max-w-md overflow-y-auto rounded-lg bg-white shadow-lg">
        <div class="flex justify-between items-center p-4">
            <div class="text-center w-full uppercase text-lg font-bold">
                <h3>{{ $t('Bestätigung der Löschung') }}</h3>
            </div>
            <slot name="header"></slot>
          <button @click="$emit('close')" class="text-gray-500 p-2 hover:text-gray-800"><i class="la la-lg la-times"></i></button>
        </div>
        <div class="border-b-4 w-full border-zbb"></div>
        <div class="mt-2 p-4">
            <div class="text-center">
                <p class="mb-4">{{ $t('Sind Sie sicher, die Löschung durchführen zu wollen?') }} :
                    <strong>
                        {{ props.toDelete.name }}

                    </strong>
                    <slot name="body"></slot>
                </p>
                <FloatLabel variant="on">
                    <InputText  v-model="deleteInput"  size="small"  class="w-full" />
                    <label for="abteilungDelete">delete*</label>
                </FloatLabel>
                <small id="username-help">Bitte geben Sie "delete" ein, um die Löschung zu bestätigen.</small>
            </div>
        </div>
        <div class="m-4 flex justify-end">
            <div class="flex w-full flex-col-reverse justify-center gap-2 sm:flex-row">
                <button @click="deleteItem" :disabled="deleting" class="rounded bg-zbb px-4 py-2 text-white disabled:cursor-wait disabled:opacity-60">{{ deleting ? $t('Wird gelöscht …') : $t('Löschen') }}</button>
                <button @click="$emit('close')" :disabled="deleting" class="rounded border border-zbb px-4 py-2 text-zbb disabled:cursor-wait disabled:opacity-60">{{ $t('Abbrechen') }}</button>
            </div>
            <slot name="footer"></slot>
        </div>
      </div>
    </div>
  </template>

  <script setup>
    import InputText from 'primevue/inputtext';
    import FloatLabel from 'primevue/floatlabel';
    import { ref, defineProps} from 'vue';
    import Swal from 'sweetalert2';

    let deleteInput = ref(''); // Speichert den Text des Eingabefelds für die Löschung
    const deleting = ref(false);
    // Define emit
    const emitDelete = defineEmits(['delete', 'close']);  // Define the event 'delete'

   // let localAbteilungen = ref([]); // Initialisiere mit einem leeren Array

    const props = defineProps({
        toDelete: {
            type: Object,
            required: true
        },
        // Dynamisiert den Link der Löschung
        seite:{
            type: String,
            required: true
        },
    });

    const deleteItem = async () => {
    if (deleteInput.value !== 'delete') {
        Swal.fire({
            title: 'Fehler!',
            text: 'Bitte geben Sie "delete" ein, um fortzufahren.',
            icon: 'error',
            timer: 3000,
            timerProgressBar: true,
        });
        return; // Stoppe die Funktion, wenn die Eingabe nicht stimmt
    }            
 
    deleting.value = true;
    let response;

    try {
        response = await axios.delete(route(props.seite + '.destroy', props.toDelete.id));
    } catch (error) {
        deleting.value = false;
        await Swal.fire({
            title: 'Nicht gelöscht',
            text: error.response?.data?.message || 'Der Eintrag konnte nicht gelöscht werden. Bitte versuchen Sie es erneut.',
            icon: 'error',
            confirmButtonText: 'OK',
        });
        return;
    }

    const deletedId = props.toDelete.id;
    const deletedName = props.toDelete.name || 'Der Eintrag';
    deleteInput.value = '';
    deleting.value = false;

    await Swal.fire({
        title: 'Erfolgreich gelöscht',
        text: response.data?.message || `${deletedName} wurde erfolgreich gelöscht.`,
        icon: 'success',
        timer: 3000,
        timerProgressBar: true,
    });

    // Erst nach bestätigter Serverantwort die lokale Liste und das Modal aktualisieren.
    // Fehler in einem übergeordneten Event-Handler dürfen keinen falschen Serverfehler auslösen.
    emitDelete('delete', deletedId);
    emitDelete('close');

};

  </script>
<script>
 export default {
        name: 'Modal',
    };

</script>
  <style scoped>
  /* Stil anpassen, wenn nötig */
  </style>
