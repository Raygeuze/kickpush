<script setup>
import { Head, Link } from '@inertiajs/vue3';
import { useForm } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import Feed from '@/kickpushComponents/Feed.vue';
import DayBanner from '@/kickpushComponents/DayBanner.vue';

defineProps({
    day: Object,
    user: Object,
    todaysVoteCount: Object,
    submissions: Object,
});


</script>

<template>
    <AppLayout title="Day {{ day.id }}">
        <DayBanner 
            :day="day"
            :is_today="false" />
        <Feed 
            :day="day"
            :submissions="submissions.data" 
            :user="user" 
            :todays-vote-count="todaysVoteCount"/>
        
    </AppLayout>
</template>
