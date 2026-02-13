<script setup>
import { Link } from '@inertiajs/vue3';


defineProps({
    day: Object,
});


</script>

<template>
    <Link :href="route('days.show', day.id)">
        <div class="mb-4 p-4 sm:p-6 border border-gray-300 rounded-xl bg-white/80 shadow hover:shadow-lg transition flex flex-col gap-4">
            <div class="flex flex-col sm:flex-row sm:items-center gap-4 sm:gap-6">
                <div class="flex">
                    <h2 class="text-lg sm:text-xl font-bold text-blue-700 min-w-[60px] sm:min-w-[80px]">Day {{ day.id }}</h2>
                    <div class="lg:hidden flex items-center ml-auto bg-green-100 text-green-700 px-3 py-1 rounded-full text-xs sm:text-sm font-semibold">
                        <i class="fa fa-trophy mr-1"></i>
                        ${{ day.prize_pool?.total ?? '0.00' }}
                    </div>
                </div>
                <div class="flex flex-col gap-1 flex-1">
                    <h2 class="text-base sm:text-lg font-semibold text-gray-900">{{ day.topic.topic }}</h2>
                    <p class="text-gray-600 w-full sm:w-3/4">{{ day.topic.description }}</p>
                </div>
                <div class="flex flex-col justify-between h-auto sm:h-24 items-end ml-0 sm:ml-auto mt-2 sm:mt-0">
                    <div class="hidden lg:flex items-center bg-green-100 text-green-700 px-3 py-1 rounded-full text-xs sm:text-sm font-semibold">
                        <i class="fa fa-trophy mr-1"></i>
                        ${{ day.prize_pool?.total ?? '0.00' }}
                    </div>
                    <div class="text-xs sm:text-sm text-gray-500 flex flex-wrap gap-2 sm:gap-3 items-center mt-1" v-if="day.submissions_count !== undefined">
                        <span><i class="fa-regular fa-file-alt mr-1"></i> {{ day.submissions_count }}</span>
                        <span><i class="fa-regular fa-heart mr-1"></i> {{ day.submissions_sum_votes }}</span>
                        <span><i class="fa-regular fa-message mr-1"></i> {{ day.comments_count }}</span>
                    </div>
                </div>
            </div>
        </div>
    </Link>    
</template>
