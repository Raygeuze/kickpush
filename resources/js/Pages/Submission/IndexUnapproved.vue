<script setup>
import { Head, Link } from '@inertiajs/vue3';
import { useForm } from '@inertiajs/vue3';
import { ref } from 'vue';
import AppLayout from '@/Layouts/AppLayout.vue';


const props = defineProps({
    submissions: Array
});

const submissions = ref(props.submissions);
const showReason = ref(false);
const reason = ref('');

const approve = async (submissionId) => {
    try {
        const response = await axios.put(`/api/admin/submissions/${submissionId}/approve`);
        console.log('Submission approved successfully:', response.data);

        submissions.value = submissions.value.filter(sub => sub.id !== submissionId);
        // Optionally, update local state or display a success message
    } catch (error) {
        console.error('Error approving submission:', error);
        // Handle error, e.g., display an error message
    }
};

const disapprove = async (submissionId) => {
    try {
        const response = await axios.put(`/api/admin/submissions/${submissionId}/disapprove`, { reason: reason.value });
        console.log('Submission disapproved successfully:', response.data);

        submissions.value = submissions.value.filter(sub => sub.id !== submissionId);
        // Optionally, update local state or display a success message
    } catch (error) {
        console.error('Error approving submission:', error);
        // Handle error, e.g., display an error message
    }
};


</script>

<template>
    <AppLayout>
        <Head title="ALL DAY!" />
        <h1 class="text-2xl font-bold">All Unapproved Submissions</h1>

        <div v-for="submission in submissions" :key="submission.id" class="mb-4 p-4 border border-gray-300 rounded-md shadow-sm">
            <h2 class="text-xl font-semibold">{{ submission.title }}</h2>
            <p class="mt-2 text-gray-600">{{ submission.description }}</p>

            <div class="border-l-2 border-grey-500 p-4 ml-auto cursor-pointer" @click="approve(submission.id)">
                APPROVE
            </div>
            <div class="border-l-2 border-grey-500 p-4 ml-auto cursor-pointer" @click="showReason = !showReason">
                DISAPPROVE
            </div>


            <div v-if="showReason" class="mt-4">
                <textarea v-model="reason" placeholder="Enter reason for disapproval" class="w-full p-2 border border-gray-300 rounded-md"></textarea>
                <button @click="disapprove(submission.id)" class="mt-2 px-4 py-2 bg-red-500 text-white rounded-md">Submit Disapproval</button>
            </div>
        </div>
    </AppLayout>
</template>
