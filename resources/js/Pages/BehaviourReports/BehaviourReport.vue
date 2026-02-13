<script setup>
import { toast } from 'vue3-toastify';
import 'vue3-toastify/dist/index.css';
import { ref } from 'vue';
import { usePage } from '@inertiajs/vue3';
import { Link } from '@inertiajs/vue3';

const $page = usePage();

const emit = defineEmits(['reportResolved']);

const props = defineProps({
    report: Object,
});

const form = {
    resolution_details: props.report.resolution_details || '',
    is_resolved: props.report.is_resolved,
    user_id: $page.props.auth.user ? $page.props.auth.user.id : null,
};

const showResolutionForm = ref(false);
const showResolutionComments = ref(false);

const submit = async (reportId) => {
    try {
        const response = await axios.post(`/admin/behaviour-reports/${reportId}/resolve`, form);

        if (response.data.flashStatus === 'success'){
            toast.success(response.data.message, {
                autoClose: 1000,
            });

            if(response.data.report.is_resolved){
                // update report
                emit('reportResolved', response.data.report);
            }
            showResolutionForm.value = false;}
        else {
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
    <div>
        <div class="p-4 sm:p-6 border border-gray-300 rounded-xl shadow bg-white flex flex-col">
            <div class="flex flex-col sm:flex-row sm:justify-between sm:items-start gap-2">
                <div class="flex-1">
                    <h3 class="text-lg sm:text-xl font-bold text-blue-700 mb-2">Report ID: {{ report.id }}</h3>
                    <p class="text-gray-700 mb-1">{{ report.reason }}</p>
                    <p class="text-gray-600 mb-2">{{ report.details }}</p>
                    <div class="flex flex-col gap-1">
                        <Link :href="route('user.show', report.reported_by.id)" class="text-sm text-gray-500">Reported by: @{{ report.reported_by.name }}</Link>
                        <Link :href="route('user.show', report.reported_user.id)" class="text-sm text-gray-500">Reported User: @{{ report.reported_user.name }}</Link>
                        <p class="text-xs text-gray-400">Date created: {{ new Date(report.created_at).toLocaleString() }}</p>
                    </div>
                </div>
                <div class="flex flex-col gap-2 sm:items-end sm:text-right min-w-[180px]">
                    <Link v-if="report.submission" :href="route('submissions.history', report.submission_id)" class="font-bold text-sm text-blue-600 underline">History/Edits</Link>
                    <Link v-if="report.comment" :href="route('comments.history', report.comment_id)" class="font-bold text-sm text-blue-600 underline">History/Edits</Link>
                    <div class="flex gap-2 mt-2 sm:mt-4">
                        <button v-if="!report.is_resolved" @click="showResolutionForm = !showResolutionForm" class="bg-blue-600 text-white px-4 py-2 rounded-lg shadow hover:bg-blue-700 transition">{{!showResolutionForm ? 'Resolve' : 'X'}}</button>
                        <button v-else @click="showResolutionComments = !showResolutionComments" class="bg-green-600 text-white px-4 py-2 rounded-lg shadow hover:bg-green-700 transition">Show Resolution Comments</button>
                    </div>
                </div>
            </div>
            <div v-if="showResolutionComments" class="mt-4 p-4 border border-gray-300 rounded-md shadow-sm bg-gray-100 text-gray-700">
                {{ report.resolution_details }}
            </div>
        </div>
        <!-- handle report form -->
        <div v-if="showResolutionForm" class="max-w-lg mx-auto py-6 px-4 sm:px-6 lg:px-8 bg-white rounded-xl shadow mt-4">
            <label class="flex items-center gap-2 mb-4">
                <input type="checkbox" v-model="form.is_resolved" :checked="form.is_resolved" />
                <span class="text-sm text-gray-700">Mark as resolved</span>
            </label>
            <div class="mb-4">
                <textarea id="resolution_details" placeholder="Add a comment" v-model="form.resolution_details" class="w-full rounded-lg border border-gray-300 p-2 text-gray-800 shadow focus:border-blue-500 focus:ring-2 focus:ring-blue-200 transition resize-none" required></textarea>
            </div>
            <button @click="submit(report.id)" type="submit" class="w-full flex items-center justify-center gap-2 bg-blue-600 text-white font-semibold px-4 py-2 rounded-lg shadow hover:bg-blue-700 transition">
                Submit
            </button>
        </div>
    </div>
</template>
