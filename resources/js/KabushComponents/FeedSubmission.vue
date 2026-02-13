<script setup>
import { Head, Link } from '@inertiajs/vue3';
import { useForm } from '@inertiajs/vue3';
import { ref } from 'vue';
import { toast } from 'vue3-toastify';
import EditFeedSubmission from '@/kickpushComponents/EditFeedSubmission.vue';
import CommentFeed from '@/Pages/Submission/Comments/CommentFeed.vue';
import MoreActions from '@/kickpushComponents/MoreActions.vue';
import { inject } from 'vue';

const utilities = inject('utilities');

const emit = defineEmits(['toggleLoginModal']);

const props = defineProps({
    submission: Object,
    index: Number,
    user: Object,
    todaysVoteCount: Object,
});

const submission = ref(props.submission);
const votes = ref(props.submission.votes);
const index = props.index;
const user = props.user;
const todaysVoteCount = props.todaysVoteCount ? props.todaysVoteCount : { count: 0 };
const isUsersSubmission = user && submission.value.user_id === user.id;
const showEdit = ref(false);
const submissionsVotedOn = ref(props.todaysVoteCount ? [...props.todaysVoteCount.submissions.map(s => s.id)] : []);

//show more/less logic
const description = ref(submission.value.description.substring(0, 350) + (submission.value.description.length > 350 ? '...' : ''));
const showingMore = ref(false);
const showShowMore = ref(submission.value.description.length > 350);

const showFullDescription = (e) => {
    showingMore.value = !showingMore.value
    description.value = showingMore.value ? submission.value.description : submission.value.description.substring(0, 350) + (submission.value.description.length > 100 ? '...' : '');
};

const hiddenComments = ref(new Set());



const vote = async (submissionId) => {
    try {
        const response = await axios.put(`/api/submission/${submissionId}/vote`);
        votes.value = response.data.submission.votes;
        todaysVoteCount.count = response.data.todaysVoteCount.count;

        if(response.data.flashStatus === 'error'){
            toast.error(response.data.message, {
                autoClose: 1000,
            });
        } else if (response.data.flashStatus === 'success'){
            submissionsVotedOn.value = [...response.data.todaysVoteCount.submissions.map(s => s.id)];

            toast.success(response.data.message, {
                autoClose: 1000,
            });
        }
    } catch (error) {
        // Handle error, e.g., display an error message
    }
};

const removeVote = async (submissionId) => {
    try {
        const response = await axios.put(`/api/submission/${submissionId}/remove-vote`);
        votes.value = response.data.submission.votes;
        todaysVoteCount.count = response.data.todaysVoteCount.count;
        submissionsVotedOn.value = [...response.data.todaysVoteCount.submissions.map(s => s.id)];

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

const setShowEdit = () => {
    showEdit.value = true;
};

//comment related
const toggleComments = (submissionId) => {
    hiddenComments.value = hiddenComments.value || ref(new Set(submission.value.parent_comments.map(c => c.id)))
    if (hiddenComments.value.has(submissionId)) {
        hiddenComments.value.delete(submissionId);
    } else {
        hiddenComments.value.add(submissionId);
    }
}

</script>

<template>
    <div class="lg:w-2/3 w-full my-4 lg:pl-2 pl-2" 
        :class="{'lg:ml-auto': index % 2 === 0, 'border-l-2 border-l-gray-200': hiddenComments.has(submission.id), 'border-l-2 border-l-transparent': !hiddenComments.has(submission.id)}" 
        v-if="!showEdit"
        @click.self="toggleComments(submission.id)"
        >
        <div class="flex-col">
            <Link :href="route('user.show', {id: submission.user.id})" class="h-fit flex">
                <div v-if="$page.props.jetstream.managesProfilePhotos" class="text-sm mr-2 border-2 border-transparent rounded-full focus:outline-none focus:border-gray-300 transition">
                    <img class="size-8 rounded-full object-cover:" :src="submission.user.profile_photo_url" :alt="submission.user.name">
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
                            class="flex w-full mb-2 p-4 rounded-b-lg rounded-tr-lg cursor-pointer shadow hover:shadow-lg transition" 
                            :class="index % 2 === 0 ? ' bg-white' : 'bg-blue-100'"
                            @click.stop="toggleComments(submission.id)"
                        >
                            <div class="flex-col ">   
                                <Link :href="route('submissions.show', submission.id)" class="text-md font-semibold">{{ submission.title }}</Link>

                                <p style="white-space: pre-wrap" class="">{{ description }}</p>
                                <p v-if="showShowMore" @click.stop="showFullDescription()">{{ showingMore ? 'Show less' : 'Show more' }}</p>
                            </div>
                        </div>
                        <MoreActions 
                            :parent="submission"
                            :parent_type="'submission'"
                            :direction="'right'"
                            :isUsersParent="isUsersSubmission"
                            @toggleLoginModal="emit('toggleLoginModal')"
                            @setShowEdit="setShowEdit"
                        />
                    </div>
                    <div class="w-full flex">
                        <span v-if="$page.props.auth.user" class="flex items-center gap-1 ml-2 text-gray-500 text-sm cursor-pointer" @click="vote(submission.id)">
                            <i class="fa-regular fa-heart mx-1" :class="submissionsVotedOn.includes(submission.id) ? 'text-green-500' : 'text-gray-500'"></i> {{ votes }}
                        </span>
                        <span v-else class="flex items-center gap-1 ml-2 text-gray-500 text-sm cursor-pointer" @click="emit('toggleLoginModal')">
                            <i class="fa-regular fa-heart mx-1"></i> {{ votes }}
                        </span>
                        <div class="ml-2 text-sm text-gray-500 cursor-pointer">
                            <div @click="toggleComments(submission.id)" class="">
                                <i class="fa-regular fa-message mx-1"></i> 
                                {{ submission.comments_count ? submission.comments_count : 0 }} Comments
                            </div>
                        </div>
                        <span class="ml-auto mr-8 text-gray-500 text-xs">{{ utilities.timeAgo(submission.created_at) }}</span>
                    </div>
                </div>
                <div v-if="hiddenComments.has(submission.id)" :class="index % 2 === 0 ? 'ml-auto' : ''">
                    <CommentFeed :submission="submission" @toggleLoginModal="emit('toggleLoginModal')"></CommentFeed>
                </div>
            </div>
        </div>
    </div>

    <EditFeedSubmission :class="index % 2 === 0 ? 'ml-auto' : ''" v-if="showEdit" :submission="submission" :user="user" @closeEdit="closeEdit" />

</template>
