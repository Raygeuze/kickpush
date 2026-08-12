<script setup>
import { Head } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import TimerToggle from '@/KickpushComponents/TimerToggle.vue';
</script>

<template>
    <AppLayout title="Timer">
        <Head title="Timer" />
        <div class="min-h-screen flex flex-col items-center justify-center px-4 py-10 bg-gray-100 dark:bg-black">
            <div class="w-full max-w-2xl text-center mb-8">
                <h1 class="text-3xl sm:text-4xl font-bold text-gray-900 dark:text-white">Timer Tracker</h1>
                <p class="mt-3 text-gray-600 dark:text-gray-300">Start and stop the timer. Each session is saved in the database.</p>
            </div>

            <TimerToggle />
        </div>
    </AppLayout>
</template>
