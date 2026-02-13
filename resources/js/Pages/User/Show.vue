<script setup>
import { Head, Link } from '@inertiajs/vue3';
import { useForm } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import Feed from '@/kickpushComponents/Feed.vue';
import BehaviourReport from '@/Pages/BehaviourReports/BehaviourReport.vue';
import SubmissionCard from '@/kickpushComponents/SubmissionCard.vue';
import UserBanner from '@/kickpushComponents/UserBanner.vue';
import { ref } from 'vue';

const props = defineProps({
    user: Object
});

const showReports = ref(false);

</script>

<template>
    <AppLayout :title="user.name + ' - Profile'">

        <UserBanner :user="user" />

        <div v-if="$page.props.auth.user && $page.props.auth.user.is_admin && user.behaviour_reports && user.behaviour_reports.length > 0" class="mx-auto flex flex-col w-full max-w-2xl p-6 lg:max-w-6xl">
            <h1 @click="showReports = !showReports" class="bg-blue-600 text-white px-4 py-2 rounded-lg shadow hover:bg-blue-700 transition mt-4 mb-4">{{!showReports ? 'Show' : 'Hide'}} Behaviour Reports</h1>
            <div v-if="showReports">
                <h1 class="text-2xl sm:text-3xl font-extrabold text-blue-700 mb-4">Behaviour Reports</h1>
                <div v-for="report in user.behaviour_reports" :key="report.id" class="mb-4">
                    <BehaviourReport :report="report"/>
                </div>
            </div>
        </div>

        <div class="mx-auto w-full max-w-2xl p-6 lg:max-w-6xl min-h-[100vh mt-8 mb-8">
            <div class="text-lg leading-7">
                <h2 class="text-2xl font-bold  dark:text-white sm:text-3xl lg:text-4xl mb-4">
                    Entries
                </h2>
                <div v-if="user.submissions && user.submissions.length > 0">
                    <div v-for="submission in user.submissions" :key="submission.id" >
                        <SubmissionCard :submission="submission" />
                        
                        <div v-if="$page.props.auth.user">
                            <div v-if="submission.is_disapproved && submission.user_id === $page.props.auth.user.id" class="mb-4 p-4 border border-gray-300 rounded-md shadow-sm bg-white">
                                <div class="flex">
                                    <h3 class="text-xl font-semibold">{{ submission.title }} - Day {{ submission.day_id }}</h3>
                                    <p class="border border-red-400 rounded ml-auto text-red-400 p-1" v-if="submission.is_disapproved">FLAGGED</p>
                                </div>
                                <p class="mt-2 text-gray-600">{{ submission.description }}</p>
                                <Link :href="route('submissions.show', submission.id)" class="text-blue-500 hover:underline mt-2 inline-block">View Details</Link>
                            </div>
                        </div>

                    </div>
                </div>
                <div v-else>
                    <p class="text-gray-600 text-center mt-8">No submissions found.</p>
                </div>
            </div>
        </div>
        
    </AppLayout>
</template>
