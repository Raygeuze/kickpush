<script setup>
import { Head, Link } from '@inertiajs/vue3';
import { useForm } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import DayCard from '@/KickpushComponents/DayCard.vue';
import Banner from '@/KickpushComponents/Banner.vue';

defineProps({
    days: Array,
    topThreeDays: Array,
});


</script>

<template>
    <AppLayout>
        <Head title="The Archive" />

        <div class="mx-auto w-full lg:p-6 lg:max-w-7xl">
            <Banner :header="'The archive of BS'" :subheader="'Explore past days and their topics, submissions, and winners.'" />
            <div class="mx-auto w-full max-w-2xl p-6 lg:max-w-6xl">
                <h1 class="mt-10 text-3xl font-extrabold tracking-tight text-blue-700 drop-shadow-sm">A Few Favourites</h1>
                <p class="mb-4 text-gray-500 text-md">A few of our favourite days so far</p>

                <div v-for="day in topThreeDays" :key="day.id" class="mt-4">
                    <DayCard :day="day" />
                </div>

                <h1 class="text-3xl font-extrabold tracking-tight text-blue-700 mt-12 drop-shadow-sm">All the Archive has to offer</h1>
                <p class="mb-4 text-gray-500 text-md">View all the things</p>
                <div v-for="day in days" :key="day.id" class="mt-4">
                    <DayCard :day="day" />
                </div>
            </div>
        </div>
    </AppLayout>

</template>
