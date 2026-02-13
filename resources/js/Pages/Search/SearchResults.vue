<script setup>
import { Head, Link } from '@inertiajs/vue3';
import { useForm } from '@inertiajs/vue3';
import { ref, onMounted, onUnmounted } from 'vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import DayCard from '@/kickpushComponents/DayCard.vue';
import Banner from '@/kickpushComponents/Banner.vue';
import SubmissionCard from '@/kickpushComponents/SubmissionCard.vue';
import UserCard from '@/kickpushComponents/UserCard.vue';

const props = defineProps({
    days: Object,
    submissions: Object,
    users: Object,
});

const days = ref(props.days.data);
const submissions = ref(props.submissions.data);
const users = ref(props.users.data);


const loading = ref(false);

const loadMoreDays = async () => {
    if( loading.value ) return;
    loading.value = true;
    try {
        const nextPage = props.days.current_page + 1;
        if (nextPage <= props.days.last_page) {
            const response = await axios.get(route('search.loadMoreDays'), {
                params: {
                    query: route().params.query,
                    page: nextPage,
                },
            });
            const newItems = response.data.data;
            days.value = days.value.concat(newItems);
            props.days.current_page = response.data.current_page;
            props.days.last_page = response.data.last_page;
        }
    } catch (error) {
        console.error('Error loading more days:', error);
    } finally {
        loading.value = false;
    }
};

const hideDays = () => {
    days.value = props.days.data;
    props.days.current_page = 1;
};

const loadMoreSubmissions = async () => {
    if( loading.value ) return;
    loading.value = true;
    try {
        const nextPage = props.submissions.current_page + 1;
        if (nextPage <= props.submissions.last_page) {
            const response = await axios.get(route('search.loadMoreSubmissions'), {
                params: {
                    query: route().params.query,
                    page: nextPage,
                },
            });
            const newItems = response.data.data;
            submissions.value = submissions.value.concat(newItems);
            props.submissions.current_page = response.data.current_page;
            props.submissions.last_page = response.data.last_page;
        }
    } catch (error) {
        console.error('Error loading more submissions:', error);
    } finally {
        loading.value = false;
    }
};

const hideSubmissions = () => {
    submissions.value = props.submissions.data;
    props.submissions.current_page = 1;
};


const loadMoreUsers = async () => {
    if( loading.value ) return;
    loading.value = true;
    try {
        const nextPage = props.users.current_page + 1;
        if (nextPage <= props.users.last_page) {
            const response = await axios.get(route('search.loadMoreUsers'), {
                params: {
                    query: route().params.query,
                    page: nextPage,
                },
            });
            const newItems = response.data.data;
            users.value = users.value.concat(newItems);
            props.users.current_page = response.data.current_page;
            props.users.last_page = response.data.last_page;
        }
    } catch (error) {
        console.error('Error loading more users:', error);
    } finally {
        loading.value = false;
    }
};

const hideUsers = () => {
    users.value = props.users.data;
    props.users.current_page = 1;
};


</script>

<template>
    <AppLayout>
        <Banner :header="'Search Results'" :subheader="`Results for search: ${route().params.query}`" />

        <div class="lg:mx-auto lg:w-1/2 mt-10 mx-4">
            <div class="mb-6">

                <div class="">
                    <h1 class="mt-10 text-3xl font-extrabold tracking-tight text-blue-700 drop-shadow-sm">Topics</h1>
                    <p class="mb-4 text-gray-500 text-md">Were you searching for a particular topic?</p>

                    <div v-if="days.length === 0" class="mb-4 p-4 border border-gray-300 rounded-xl bg-white/80 shadow">No days found</div>
                    <div v-else>
                        <div v-for="day in days" :key="day.id" class="">
                            <DayCard :day="day" />   
                        </div>
                        <p v-if="props.days.current_page < props.days.last_page" class="text-center cursor-pointer" @click="loadMoreDays()">View more</p>
                        <p v-else-if="props.days.current_page != 0 && props.days.current_page != props.days.last_page" class="text-center cursor-pointer" @click="hideDays()">View less</p>
                    </div>
                </div>
            </div>

            <div class="mb-6">
                <h1 class="mt-10 text-3xl font-extrabold tracking-tight text-blue-700 drop-shadow-sm">Stories</h1>
                <p class="mb-4 text-gray-500 text-md">Or were you searching for a particular story?</p>

                <div v-if="submissions.length === 0" class="mb-4 p-4 border border-gray-300 rounded-xl bg-white/80 shadow">No submissions found</div>
                <div v-else>
                    <div v-for="submission in submissions" :key="submission.id" class="">
                        <SubmissionCard :submission="submission" />
                    </div>
                    <p v-if="props.submissions.current_page < props.submissions.last_page" class="text-center cursor-pointer" @click="loadMoreSubmissions()">View more</p>
                    <p v-else-if="props.submissions.current_page != 0 && props.submissions.current_page != props.submissions.last_page" class="text-center cursor-pointer" @click="hideSubmissions()">View less</p>
                </div>
            </div>

            <div class="mb-6">
                <h1 class="mt-10 text-3xl font-extrabold tracking-tight text-blue-700 drop-shadow-sm">Users</h1>
                <p class="mb-4 text-gray-500 text-md">Is there a particular user you are looking for?</p>
                <div v-if="users.length === 0" class="mb-4 p-4 border border-gray-300 rounded-xl bg-white/80 shadow">No users found</div>
                <div v-else>
                    <div v-for="user in users" :key="user.id" class="">
                        <UserCard :user="user" />
                    </div>
                    <p v-if="props.users.current_page < props.users.last_page" class="text-center cursor-pointer" @click="loadMoreUsers()">View more</p>
                    <p v-else-if="props.users.current_page != 0 && props.users.current_page != props.users.last_page" class="text-center cursor-pointer" @click="hideUsers()">View less</p>
                </div>
            </div>

        </div>
    </AppLayout>

</template>