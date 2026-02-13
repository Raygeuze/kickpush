<script setup>
import { Head, Link } from '@inertiajs/vue3';
import { useForm } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import Feed from '@/kickpushComponents/Feed.vue';

defineProps({
    day: Object,
    is_today: Boolean,
});


</script>

<template>
    <div class="z-10 sticky top-0 lg:mt-12 mx-auto flex-col lg:flex lg:flex-row-reverse w-full max-w-2xl p-6 lg:max-w-7xl bg-blue-400 bg-gradient-to-r from-blue-400 to-blue-600 lg:rounded-xl">
        <div class="lg:border rounded h-fit lg:px-4 lg:py-2 -mb-4 lg:mb-0 w-fit lg:mt-0 ml-auto self-center">
            <p class="text-white">
                <i class="fa fa-trophy"></i>
                ${{ day.prize_pool?.total ?? '0.00' }}</p>
        </div>
        <div class="">
            <h1 v-if="is_today" class="text-2xl lg:text-4xl font-extrabold text-white">Today's topic: {{day.topic.topic}}</h1>
            <h1 v-else class="text-2xl lg:text-4xl font-extrabold text-white">Day {{day.id}} topic: {{day.topic.topic}}</h1>

            <p class="text-white">{{ day.topic.description }}</p>
        </div>
    </div>
    
</template>
