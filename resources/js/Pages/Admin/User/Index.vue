<script setup>
import { Head, Link } from '@inertiajs/vue3';
import { useForm } from '@inertiajs/vue3';

defineProps({
    users: Array
});


</script>

<template>
    <Head title="ALL USERS!" />
    <div class="mx-auto w-full max-w-2xl p-6 lg:max-w-7xl">
        <h1 class="text-2xl font-bold">All Users</h1>
        <div v-for="user in users" :key="user.id" class="lg:w-1/2 mb-4 p-4 border border-gray-300 rounded-md shadow-sm">
            <div class="flex">
                <h2 class="text-xl font-semibold">{{ user.name }}</h2>
                <p class="border border-red-400 rounded ml-auto text-red-400 p-1" v-if="user.disabled">DISABLED</p>
            </div>
            <p class="mt-2 text-gray-600">{{ user.email }}</p>
            <Link :href="route('user.adminShow', user.id)" class="text-blue-500 hover:underline mt-2 inline-block">View Details</Link>
        </div>
    </div>

</template>
