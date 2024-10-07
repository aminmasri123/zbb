<script>
import AppLayout from '@/Layouts/AppLayout.vue';
import { reactive } from 'vue'
import { router } from '@inertiajs/vue3'


const form = reactive({
  first_name: null,
  last_name: null,
  email: null,
})

function submitUser() {
  router.post('/users-store', form)
}
export default {
    // Komponente referenzieren
    components: {
        AppLayout,
    },
};
</script>



<template>
    <app-layout>
        <!-- Header Slot -->

        <template #header>
            <div class="page-header">
                <div class="flex items-center">
                    <div class="flex-grow">
                        <h3 class="text-xl font-semibold">Benutzer
                            <i class="las la-building text-xl ml-2"></i>
                        </h3>
                        <ul class="flex pl-0  text-gray-600 text-sm">
                            <li><a :href="route('dashboard')" class="text-gray-500 no-underline">Dashboard/</a></li>
                            <li class="text-gray-500">Benutzer anlegen</li>
                        </ul>
                    </div>
                    <div class="ml-auto">
                        <a href="#" class="btna no-underline flex items-center space-x-2 bg-orange-500 px-4 py-2 rounded shadow hover:bg-white hover:border hover:border-orange-500 text-white">
                            <i class="las la-list-ul"></i>
                            <span>Benutzerliste</span>
                        </a>
                    </div>
                </div>
            </div>

        </template>
        <!-- Page Content -->
        <div class="content container m-4 mx-auto">
            <!-- Page Header -->


            <div class="bg-white rounded-lg shadow-lg p-6 pt-3">
                <div class="items-center">
                    <h5 class="text-lg font-semibold">Benutzer Anlegen</h5>
                    <div class="card-border border-b-2"></div>
                </div>
                <div class="mt-4">
                    <form @submit.prevent="submitUser" class="w-full max-w-lg">
                        <div class="flex flex-wrap -mx-3 mb-6">
                            <div class="w-full md:w-1/2 px-3 mb-6 md:mb-0">
                            <label class="block uppercase tracking-wide text-gray-700 text-xs font-bold mb-2" for="grid-first-name">
                                Vorname
                            </label>
                            <input class="appearance-none block w-full bg-gray-200 text-gray-700 border border-red-500 rounded py-3 px-4 mb-3 leading-tight focus:outline-none focus:bg-white" id="grid-first-name" type="text" placeholder="Jane">
                            <p class="text-red-500 text-xs italic">Please fill out this field.</p>
                            </div>
                            <div class="w-full md:w-1/2 px-3">
                            <label class="block uppercase tracking-wide text-gray-700 text-xs font-bold mb-2" for="grid-last-name">
                                Nachname Name
                            </label>
                            <input class="appearance-none block w-full bg-gray-200 text-gray-700 border border-gray-200 rounded py-3 px-4 leading-tight focus:outline-none focus:bg-white focus:border-gray-500" id="grid-last-name" type="text" placeholder="Doe">
                            </div>
                        </div>
                        <div class="flex flex-wrap -mx-3 mb-6">
                            <div class="w-full px-3">
                            <label class="block uppercase tracking-wide text-gray-700 text-xs font-bold mb-2" for="grid-password">
                                Kennword
                            </label>
                            <input class="appearance-none block w-full bg-gray-200 text-gray-700 border border-gray-200 rounded py-3 px-4 mb-3 leading-tight focus:outline-none focus:bg-white focus:border-gray-500" id="grid-password" type="password" placeholder="******************">
                            <p class="text-gray-600 text-xs italic">Make it as long and as crazy as you'd like</p>
                            </div>
                        </div>

                    </form>
                </div>
            </div>
        </div>
    </app-layout>
</template>

