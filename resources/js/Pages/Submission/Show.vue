<script setup>
import { Head, Link, usePage } from '@inertiajs/vue3';
import { useForm } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import { capitalize, ref } from 'vue';
import CommentFeed from '@/Pages/Submission/Comments/CommentFeed.vue';
import LoginModal from '@/kickpushComponents/LoginModal.vue';
import RegisterModal from '@/kickpushComponents/RegisterModal.vue';
import DayBanner from '@/kickpushComponents/DayBanner.vue';
import { inject } from 'vue';
import MoreActions from '@/kickpushComponents/MoreActions.vue';
import { toast } from 'vue3-toastify';
import EditFeedSubmission from '@/kickpushComponents/EditFeedSubmission.vue';

const $page = usePage();

const utilities = inject('utilities');

const props = defineProps({
    submission: Object
});

const submission = ref(props.submission);
const showLoginModal = ref(false);
const showRegisterModal = ref(false);
const votes = ref(props.submission.votes);
const todaysVoteCount = ref(props.submission.todaysVoteCount ? props.submission.todaysVoteCount : { count: 0 });
const isUsersSubmission = $page.props.auth.user && submission.value.user_id === $page.props.auth.user.id;
const index = 0;
const showEdit = ref(false);

const toggleLoginModal = () => {
    showLoginModal.value = !showLoginModal.value;
    showRegisterModal.value = false;
};

const toggleRegisterModal = () => {
    showRegisterModal.value = !showRegisterModal.value; 
    showLoginModal.value = false;
};

const setShowEdit = () => {
    showEdit.value = true;
};

const vote = async (submissionId) => {
    console.log('voting', submissionId);
    try {
        const response = await axios.put(`/api/submission/${submissionId}/vote`);
        votes.value = response.data.submission.votes;
        todaysVoteCount.count = response.data.todaysVoteCount.count;
        if(response.data.flashStatus === 'error'){
            toast.error(response.data.message, {
                autoClose: 1000,
            });
        } else if (response.data.flashStatus === 'success'){
            toast.success(response.data.message, {
                autoClose: 1000,
            });
        }
    } catch (error) {
        // Handle error, e.g., display an error message
        console.log('error voting', error);
    }
};

const closeEdit = (updatedSubmission) => {
    showEdit.value = false;
    if(updatedSubmission){
        submission.value.title = updatedSubmission.title;
        submission.value.description = updatedSubmission.description;
        description.value = updatedSubmission.description.substring(0, 350) + (updatedSubmission.description.length > 350 ? '...' : '');
        // Reset showingMore and showShowMore based on updated description
        showingMore.value = false;
        showShowMore.value = updatedSubmission.description.length > 350;
    }
};

</script>

<template>
    <AppLayout title="Submission {{ submission.id }}">
        
            <DayBanner :day="submission.day" :is_today="submission.day.is_today"/>

            <div v-if="!showEdit" class="lg:mx-auto lg:max-w-6xl mt-8 mx-4">
                <div class="flex-col">
                    <Link :href="route('user.show', {id: submission.user.id})" class="flex">
                        <div v-if="$page.props.jetstream.managesProfilePhotos" class="text-sm mr-2 border-2 border-transparent rounded-full focus:outline-none focus:border-gray-300 transition">
                            <img class="size-8 rounded-full object-cover" :src="submission.user.profile_photo_url" :alt="submission.user.name">
                        </div>
                        <div class="flex items-center mb-3 mt-1">
                            <Link :href="route('user.show', {id: submission.user.id})" class="font-semibold hover:text-black    ">@{{ submission.user.name }}</Link>
                            <span v-if="submission.is_winner" class="flex items-center gap-1 bg-green-500 text-white text-xs font-bold rounded px-2 py-1 ml-2">
                                <i class="fa fa-trophy"></i> Winner
                            </span>
                        </div>
                    </Link>

                    <div class="w-full">
                        <div class="">
                            <div class="flex w-full">
                                <div 
                                    class="flex w-full mb-2 p-4 rounded-b-lg rounded-tr-lg shadow-sm" 
                                    :class="index % 2 === 0 ? ' bg-white' : 'bg-blue-100'"
                                >
                                    <div class="flex-col">   
                                        <p class="text-md font-semibold">{{ submission.title }}</p>

                                        <p style="white-space: pre-wrap" class="">{{ submission.description }}</p>
                                    </div>
                                </div>
                                <MoreActions 
                                    :parent="submission"
                                    :parent_type="'submission'"
                                    :direction="'right'"
                                    :isUsersParent="isUsersSubmission"
                                    @toggleLoginModal="toggleLoginModal"
                                    @setShowEdit="setShowEdit"
                                />
                            </div>
                            <div class="w-full flex">
                                <span v-if="$page.props.auth.user" class="flex items-center gap-1 ml-2 text-gray-500 text-sm cursor-pointer" @click="vote(submission.id)">
                                    <i class="fa-regular fa-heart mx-1"></i> {{ votes }}
                                </span>
                                <span v-else class="flex items-center gap-1 ml-2 text-gray-500 text-sm cursor-pointer" @click="toggleLoginModal">
                                    <i class="fa-regular fa-heart mx-1"></i> {{ votes }}
                                </span>

                                <span class="ml-auto text-gray-500 text-xs lg:mr-8">{{ utilities.timeAgo(submission.created_at) }}</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <EditFeedSubmission class="mx-auto mt-8" v-if="showEdit" :submission="submission" :user="user" @closeEdit="closeEdit" />

            <CommentFeed 
                :submission="submission" 
                @toggleLoginModal="toggleLoginModal"
                class="lg:mx-auto mx-4"/>

            <LoginModal 
                :showModal="showLoginModal" 
                @close="toggleLoginModal"
                @showRegisterModal="toggleRegisterModal" />

            <RegisterModal 
                :showModal="showRegisterModal" 
                @close="toggleRegisterModal"
                @showLoginModal="toggleLoginModal" />
        
    </AppLayout>
</template>
