<script setup>
import { Link } from '@inertiajs/vue3';


defineProps({
    user: Object,
});


</script>

<template>
    <Link :href="route('user.show', user.id)">
        <div class="mb-4 p-6 border border-gray-300 rounded-xl bg-white/80 shadow hover:shadow-lg transition flex items-center gap-4">
            <div v-if="$page.props.jetstream.managesProfilePhotos" class="flex text-sm border-2 border-transparent rounded-full focus:outline-none focus:border-gray-300 transition">
                <img class="size-12 rounded-full object-cover" :src="user.profile_photo_url" :alt="user.name">
            </div>
            <div class="flex flex-col gap-1 flex-1">
                <Link :href="route('user.show', user.id)" class="font-bold text-lg text-gray-900">@{{ user.name }}</Link>
                <span class="text-sm text-gray-500 flex items-center mt-1"><i class="fa-regular fa-file-alt mr-1"></i> {{ user.submissions_count }} entries</span>
            </div>
        </div>
    </Link>    
</template>
