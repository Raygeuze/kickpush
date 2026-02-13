<script setup>

defineProps({
    user: Object,
});


</script>

<template>
    <div class="z-10 sticky top-0 lg:mt-12 mx-auto flex-col lg:flex w-full max-w-2xl p-6 lg:max-w-7xl bg-blue-400 bg-gradient-to-r from-blue-400 to-blue-600 lg:rounded-xl">
        <div class="flex-col lg:flex-row lg:flex lg:items-center w-full">
            <div class="">
                <h1 v-if="user.name" class="text-2xl lg:text-4xl font-extrabold text-white">{{user.name}}</h1>

                <p v-if="user.created_at" class="text-white">{{ 'Joined ' + new Date(user.created_at).toLocaleDateString() }}</p>
            </div>
            <div class="text-xs sm:text-sm text-white flex flex-wrap gap-2 sm:gap-3 items-center mt-1 ml-auto">
                <span><i class="fa fa-trophy mr-1"></i> {{ user.winning_submissions_count }}</span>
                <span><i class="fa-regular fa-file-alt mr-1"></i> {{ user.submissions_count }}</span>
                <span><i class="fa-regular fa-heart mr-1"></i> {{ user.submissions_sum_votes }}</span>
                <span><i class="fa-regular fa-message mr-1"></i> {{ user.comments_count }}</span>
            </div>
        </div>
    </div>
    
</template>
