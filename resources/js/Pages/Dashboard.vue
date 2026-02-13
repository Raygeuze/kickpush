<script setup>
import { Head, Link } from '@inertiajs/vue3';
import Feed from '@/kickpushComponents/Feed.vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import DayBanner from '@/kickpushComponents/DayBanner.vue';


defineProps({
    day: Object,
    user: Object,
    todaysVoteCount: Object,
    submissions: Object
});

function handleImageError() {
    document.getElementById('screenshot-container')?.classList.add('!hidden');
    document.getElementById('docs-card')?.classList.add('!row-span-1');
    document.getElementById('docs-card-content')?.classList.add('!flex-row');
    document.getElementById('background')?.classList.add('!hidden');
}
</script>

<template>
    <AppLayout title="kickpush!">
        <div class="text-black/50 dark:bg-black dark:text-white/50">
            <!-- <img id="background" class="absolute -left-20 top-0 max-w-[877px]" src="https://laravel.com/assets/img/welcome/background.svg" /> -->
            <div class="relative min-h-screen flex flex-col items-center justify-center">
                <div class="relative w-full">


                    <main class="">
                        <DayBanner :day="day" :is_today="true" />

                        <Feed 
                            :day="day" 
                            :user="user" 
                            :todays-vote-count="todaysVoteCount"
                            :submissions="submissions.data" />
                    </main>

                </div>
            </div>
        </div>
    </AppLayout>
</template>
