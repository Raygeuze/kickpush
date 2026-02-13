<script setup>
import { Head, Link } from '@inertiajs/vue3';
import { useForm } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import { toast } from 'vue3-toastify';
import 'vue3-toastify/dist/index.css';

const props = defineProps({
    submission: Object,
});

const form = {
    reason: '',
    details: '',
    submission_id: props.submission.id,
    reported_user_id: props.submission.user_id,
};

const submit = async (submissionId) => {
    try {
        const response = await axios.post(`/submissions/${submissionId}/report/store`, form);

        if (response.data.flashStatus === 'success'){
            toast.success(response.data.message, {
                autoClose: 1000,
            });

            form.reset();
        }
        else if(response.data.flashStatus === 'error') {
            toast.error(response.data.message, {
                autoClose: 1000,
            });

        }
    } catch (error) {
        console.error('Error voting:', error);
        if(error.response.data.errors){

            toast.error(error.response.data.message, {
                autoClose: 1000,
            });
        }
    }
};

</script>

<template>
    <AppLayout>
        <Head title="Report" />
        <div class="container mx-auto p-4">
            <div class="max-w-lg mx-auto bg-white/90 rounded-xl shadow-lg p-8 mt-8">
                <h1 class="text-2xl font-bold text-blue-700 mb-4">Report @{{ submission.user.name }}</h1>
                <!-- <Link :href="`/user/${submission.user_id}`" class="text-blue-600 underline font-semibold mb-2 block">Submitted by: @{{ submission.user.name }}</Link> -->
                <div class="bg-gray-100 rounded-lg p-4 mb-6">
                    <p class="font-bold text-lg mb-1">{{ submission.title }}</p>
                    <p class="text-gray-600 mb-4">{{ submission.description }}</p>
                </div>
                <div>
                    <p class="mb-2 text-sm text-gray-600">Please provide your reason and details for reporting this story.</p>
                    <label for="reason" class="block text-sm font-medium text-gray-700">Reason</label>
                    <input type="text" id="reason" v-model="form.reason" class="mt-1 block w-full rounded-lg border border-gray-300 p-2 text-gray-800 shadow focus:border-blue-500 focus:ring-2 focus:ring-blue-200 transition mb-4" required />
                    <label for="details" class="block text-sm font-medium text-gray-700">Details</label>
                    <textarea id="details" v-model="form.details" class="mt-1 block w-full rounded-lg border border-gray-300 p-2 text-gray-800 shadow focus:border-blue-500 focus:ring-2 focus:ring-blue-200 transition resize-none mb-4" required></textarea>
                    <button @click="submit(submission.id)" type="button" class="w-full flex items-center justify-center gap-2 bg-blue-600 text-white font-semibold px-4 py-2 rounded-lg shadow hover:bg-blue-700 transition">
                        Submit
                    </button>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
