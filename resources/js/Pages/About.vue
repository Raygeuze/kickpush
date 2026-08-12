<script setup>
import { Head } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import Banner from '@/KickpushComponents/Banner.vue';
</script>

<template>
    <AppLayout title="About kickpush">
        <Head title="About kickpush" />
        <Banner
            :header="'Hello World!'"
            :subheader="'What is heck is this crap!'"
        />
        <div class="flex flex-col px-2 sm:px-4 bg-transparent my-8">
            <div class="w-full max-w-4xl bg-white dark:bg-gray-900 rounded-xl shadow-lg p-8 mx-auto">
                <h1 class="text-3xl font-bold mb-4 text-blue-700 dark:text-blue-300">Oh Kabuuuush!</h1>
                <p class="text-gray-700 dark:text-gray-300 mb-6">
                    For a start, the word "kickpush" is something I believe my mother made up, and I always thought was hilarious. She would use
                    it as somewhat of a substitute for "Bullshit!", as if to say "Shut up! No way that is true!" or just generally "kickpush! (I don't believe you!)".
                    Anyway, I always get a good chuckle when she says it.
                </p>
                <p class="text-gray-700 dark:text-gray-300 mb-6">
                    kickpush the platform, is a project I thought could be fun and hope will bring a few laughs, and a few dollars to the winners!
                    <br><br>
                    Basically, the intention is a lighthearted daily competition, where a topic is posted each day and users tell stories/jokes/anything really, about that topic.
                    It costs $2NZD to enter a story, and the winner (selected by votes) takes the prize pool each day (minus a platform fee of 1% and payment processor fees of 2%).
                    <br><br>
                    I hope you enjoy using kickpush as much as I have enjoyed building it!
                </p>
            </div>
        </div>
    </AppLayout>
</template>