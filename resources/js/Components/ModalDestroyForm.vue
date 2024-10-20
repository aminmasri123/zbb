<template>
    <div class="fixed inset-0 z-50 flex items-center justify-center bg-black bg-opacity-50 backdrop-blur-sm">
      <div class="bg-white rounded-lg shadow-lg w-11/12 sm:w-1/3 ">
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
            <div class="w-full flex justify-center">
                <button @click="deleteItem" class="bg-zbb text-white mx-2 px-4 py-2 rounded">{{ $t('Löschen') }}</button>
                <button @click="$emit('close')" class="border mx-2 border-zbb text-zbb px-4 py-2 rounded">{{ $t('Abbrechen') }}</button>
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
    let toDelete = ref(null); // Speichert den Namen der User, die gelöscht werden soll
// Define emit
const emitDelete = defineEmits(['delete']);  // Define the event 'delete'
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


    const deleteItem = () => {
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
    axios.delete(route(props.seite + '.destroy', { id: props.toDelete.id }))
        .then(response => {


            emitDelete('delete', props.toDelete.id);
            deleteInput.value = '';

            Swal.fire({
                title: 'Erfolg!',
                text: 'Abteilung erfolgreich gelöscht!',
                icon: 'success',
                timer: 3000,
                timerProgressBar: true,
            });

            //Inertia.reload();
        })
        .catch(error => {
            Swal.fire({
                title: 'Error!',
                text: 'Beim Löschen der Abteilung ist ein Fehler aufgetreten. Bitte versuchen Sie es erneut.',
                icon: 'error',
                timer: 3000,
                timerProgressBar: true,
            });
        })

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
