<script setup>
import { Link } from '@inertiajs/vue3';


defineProps({
    submission: Object,
});


</script>

<template>
    <Link :href="route('submissions.show', submission.id)">
        <div class="mb-4 p-6 border border-gray-300 rounded-xl bg-white/80 shadow hover:shadow-lg transition flex flex-col">
            <div class="flex-col lg:flex-row flex  mb-2">
                <div class="flex-col lg:w-4/5">
                    <div class="flex  items-center">
                        <div class="flex items-center">
                            <div v-if="$page.props.jetstream.managesProfilePhotos" class="flex text-sm border-2 border-transparent rounded-full focus:outline-none focus:border-gray-300 transition">
                                <img class="size-8 rounded-full object-cover" :src="submission.user.profile_photo_url" :alt="submission.user.name">
                            </div>
                            <Link :href="route('user.show', submission.user.id)" class="font-semibold text-gray-800">@{{ submission.user.name }}</Link>
                        </div>
                        <span v-if="submission.is_winner" class="flex ml-2 gap-1 bg-green-500 text-white text-xs font-bold rounded px-2 py-1">
                            <i class="fa fa-trophy"></i> Winner
                        </span>
                    </div>
                
                    <div class="mt-4">
                        <div class="flex items-center mb-2">
                            <Link :href="route('submissions.show', submission.id)" class="">{{ submission.title }}</Link>
                        </div>
                        <p class="text-gray-600">{{ submission.description }}</p>
                    </div>
                </div>

                <div class="flex items-center gap-2 ml-auto mt-4">
                    <span class="text-sm text-gray-500"><i class="fa-regular fa-heart mr-1"></i> {{ submission.votes }}</span>
                    <span class="text-sm text-gray-500"><i class="fa-regular fa-message mr-1"></i> {{ submission.comments_count ? submission.comments_count : 0 }}</span>
                </div>
            </div>
        </div>
    </Link>    
</template>
