<script setup>
import ApplicationLogo from '@/Components/ApplicationLogo.vue';
import { Link } from '@inertiajs/vue3';
</script>

<template>
    <div class="lg:w-1/2 ml-auto mr-auto">
        <div class="p-6 lg:p-8 bg-white border-b border-gray-200">
            
            <h1 class="mt-8 text-2xl font-medium text-gray-900">
                Hello admin
            </h1>

            <p class="mt-6 text-gray-500 leading-relaxed">
                There are a few things to do to finish setting it up, if you would like to make submissions that is.
                If you would only like to view and vote for submissions, go ahead and get to it!

                If you want to make submissions you'll need to finish the steps here 
            </p>
        </div>

        <div class="bg-gray-200 bg-opacity-25">
            <div>
                <Link
                    :href="route('days.create')"
                    class="w-fit ml-4 bg-blue-500 text-white px-4 py-2 rounded-full shadow-lg cursor-pointer"
                >
                    Create Day
                </Link>
                <Link
                    :href="route('submissions.indexUnapproved')"
                    class="w-fit ml-4 bg-blue-500 text-white px-4 py-2 rounded-full shadow-lg cursor-pointer"
                >
                    View unapproved submissions
                </Link>
                <Link
                    :href="route('user.adminIndex')"
                    class="w-fit ml-4 bg-blue-500 text-white px-4 py-2 rounded-full shadow-lg cursor-pointer"
                >
                    View users
                </Link>
                <!-- <Link
                    :href="route('submissions.create')"
                    class="w-fit ml-4 bg-blue-500 text-white px-4 py-2 rounded-full shadow-lg cursor-pointer"
                >
                    Create Submission
                </Link> -->
            </div>
        </div>
    </div>
</template>
