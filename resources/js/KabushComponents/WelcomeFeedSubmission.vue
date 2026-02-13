<script setup>
import { Head, Link } from '@inertiajs/vue3';
import { useForm } from '@inertiajs/vue3';
import { ref } from 'vue';

const props = defineProps({
    submission: Object,
    index: Number,
});

const submission = props.submission;
const votes = ref(props.submission.votes);
const index = props.index;
const description = ref(submission.description.substring(0, 350) + (submission.description.length > 350 ? '...' : ''));
const showingMore = ref(false);
const showShowMore = submission.description.length > 350;

const showFullDescription = () => {
    showingMore.value = !showingMore.value
    description.value = showingMore.value ? submission.description : submission.description.substring(0, 100) + (submission.description.length > 100 ? '...' : '');
};

</script>

<template>
    <div class="w-1/2" :class="index % 2 === 0 ? 'ml-auto' : ''">
        <div class="flex items-center mb-2">
            <Link :href="route('user.show', {id: submission.user.id})" class="flex items-center">
                <div v-if="$page.props.jetstream.managesProfilePhotos" class="flex text-sm mr-2 border-2 border-transparent rounded-full focus:outline-none focus:border-gray-300 transition">
                    <img class="size-8 rounded-full object-cover" :src="submission.user.profile_photo_url" :alt="submission.user.name">
                </div>
                @{{ submission.user.name }}
            </Link>
        </div>
        <div class="flex mb-4 p-4 border border-gray-300 rounded-md shadow-sm " :class="index % 2 === 0 ? ' bg-white' : 'bg-blue-300'">
            <div class="flex-col">
                <Link :href="route('submissions.show', submission.id)" class="text-xl font-semibold">{{ submission.title }}</Link>

                <div>   
                    <p style="white-space: pre-wrap" class="mt-2 text-gray-600">{{ description }}</p>
                    <p v-if="showShowMore" @click="showFullDescription()">{{ showingMore ? 'Show less' : 'Show more' }}</p>
                </div>
            </div>
            <div class="flex-col ml-auto">
                <Link :href="route('login')" class="border-l-2 border-grey-500 p-4 ml-auto cursor-pointer">
                    TICK
                </Link>
                <Link :href="route('login')" class="border-l-2 border-grey-500 p-4 ml-auto cursor-pointer" @click="removeVote(submission.id)">
                    UN-TICK
                </Link>
            </div>
        </div>

        <div class="mt-2 ml-2 text-sm text-gray-500 cursor-pointer">
            <Link :href="route('submissions.show', submission.id)" class="">Votes ({{ votes }}) | Comments ({{ submission.comments_count ? submission.comments_count : 0 }})</Link>
        </div>
    </div>
</template>