<script setup>
import { Head, Link } from '@inertiajs/vue3';
import { useForm } from '@inertiajs/vue3';
import AdminAppLayout from '@/Layouts/AdminAppLayout.vue';
import Feed from '@/KickpushComponents/Feed.vue';
import { toast } from 'vue3-toastify';
import { ref } from 'vue';


const props = defineProps({
    user: Object,
});

const user = ref(props.user);

const disable_account = async (userId) => {
    try {
        const response = await axios.put(`/api/admin/user/${userId}/disable`);
        console.log('User account disabled successfully:', response.data);
            user.value = response.data.user;

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
        console.error('Error disabling user account:', error);
        // Handle error, e.g., display an error message
    }
};
const enable_account = async (userId) => {
    try {
        const response = await axios.put(`/api/admin/user/${userId}/enable`);
        console.log('User account enabled successfully:', response.data);
        user.value = response.data.user;

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
        console.error('Error enabling user account:', error);
        // Handle error, e.g., display an error message
    }
};

</script>

<template>
    <AdminAppLayout title="User {{ user.id }}">
        <div class="w-full bg-red-500 text-center">ADMIN PANEL</div>
        <div class="bg-blue-400 w-full p-12">
            <div class="text-center grid gap-4">
                <h1 class="text-4xl font-bold text-black dark:text-white sm:text-5xl lg:text-6xl">
                    <span class="text-[#FF2D20]">{{user.name}}</span>
                </h1>
                <p class="text-lg leading-7">
                    {{user.email}}
                </p>
                <p class="text-lg leading-7">
                    Joined {{new Date(user.created_at).toLocaleDateString()}}
                </p>

                <div v-if="user.disabled" class="text-red-600 font-bold ml-auto">
                    This account is currently disabled.

                    <div @click="enable_account(user.id)" class="border rounded cursor-pointer p-3">Enable Account</div>
                </div>
                <div v-else-if="$page.props.auth.user.is_admin" class="font-bold ml-auto">
                    This account is currently enabled.

                    <div @click="disable_account(user.id)" class="border rounded cursor-pointer p-3">Disable Account</div>
                </div>
            </div>
        </div>

        <div class="mx-auto w-full max-w-2xl p-6 lg:max-w-7xl min-h-[100vh] bg-gray-200 mt-8 mb-8">
            <div class="text-lg leading-7">
                <h2 class="text-2xl font-bold text-black dark:text-white sm:text-3xl lg:text-4xl mb-4">
                    Today's Submissions
                </h2>
                <div v-if="user.submissions && user.submissions.length > 0">
                    <div v-for="submission in user.submissions" :key="submission.id">
                        <div class="mb-4 p-4 border border-gray-300 rounded-md shadow-sm bg-white">
                            <div class="flex">
                                <h3 class="text-xl font-semibold">{{ submission.title }}</h3>
                                <p class="border border-red-400 rounded ml-auto text-red-400 p-1" v-if="submission.is_disapproved">FLAGGED</p>
                            </div>
                            <p class="mt-2 text-gray-600">{{ submission.description }}</p>
                            <Link :href="route('submissions.show', submission.id)" class="text-blue-500 hover:underline mt-2 inline-block">View Details</Link>
                        </div>
                        <div v-if="submission.is_disapproved" class="mb-4 p-4 ml-auto w-1/2 border border-gray-300 rounded-md shadow-sm bg-red-100">
                            Reason for disapproval: {{submission.disapproval_reason}}
                        </div>
                    </div>
                </div>
                <div v-else>
                    <p>No submissions found.</p>
                </div>
            </div>
        </div>

        <div class="mx-auto w-full max-w-2xl p-6 lg:max-w-7xl min-h-[100vh] bg-gray-200 mt-8 mb-8">
            <div class="text-lg leading-7">
                <h2 class="text-2xl font-bold text-black dark:text-white sm:text-3xl lg:text-4xl mb-4">
                    Select a day to see all submissions from that day
                </h2>

                <!-- select a day and then present the users submissions on that day -->

            </div>
        </div>
        
    </AdminAppLayout>
</template>
