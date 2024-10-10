<template>
    <div class=" min-h-svh flex flex-col justify-center items-center px-5 bg-gray-50">
        <div class="w-full sm:max-w-md">
            <slot name="logo" />
        </div>

        <div class="w-full sm:max-w-md mt-6 px-6 py-4 bg-white shadow-2xl overflow-hidden rounded-lg border border-gray-300">
            <slot />
        </div>
    </div>
</template>
