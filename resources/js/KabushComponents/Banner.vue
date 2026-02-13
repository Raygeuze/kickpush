<script setup>

defineProps({
    header: String,
    subheader: String,
});


</script>

<template>
    <div class="z-10 sticky top-0 lg:mt-12 mx-auto flex-col lg:flex w-full max-w-2xl p-6 lg:max-w-7xl bg-blue-400 bg-gradient-to-r from-blue-400 to-blue-600 lg:rounded-xl">
        <div class="">
            <h1 v-if="header" class="text-2xl lg:text-4xl font-extrabold text-white">{{header}}</h1>

            <p v-if="subheader" class="text-white">{{ subheader }}</p>
        </div>
    </div>
    
</template>
