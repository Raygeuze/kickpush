<script setup>
import { Head, Link } from '@inertiajs/vue3';
import { useForm } from '@inertiajs/vue3';
import { ref } from 'vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import BehaviourReport from '@/Pages/BehaviourReports/BehaviourReport.vue';

const props = defineProps({
    submission_reports: Array,
    comment_reports: Array,
});

const submission_reports = ref(props.submission_reports);
const comment_reports = ref(props.comment_reports);

const form = useForm({
    reason: ''
});

const reportResolved = (updatedReport) => {
    if(updatedReport.reportable_type === 'submission'){
        const reportIndex = submission_reports.value.findIndex(r => r.id === updatedReport.id);
        submission_reports.value.splice(reportIndex, 1);
    }
    else if(updatedReport.reportable_type === 'comment'){
        const reportIndex = comment_reports.value.findIndex(r => r.id === updatedReport.id);
        comment_reports.value.splice(reportIndex, 1);
    }
};

</script>

<template>
    <AppLayout>
        <div class="mx-auto w-full max-w-2xl lg:max-w-7xl p-4 sm:p-6 mt-6 mb-8">
            <h1 class="text-2xl sm:text-3xl font-extrabold text-blue-700 mb-4">Submission Reports</h1>
            <div v-if="submission_reports.length > 0" class="flex flex-col gap-4">
                <div v-for="report in submission_reports" :key="report.id">
                    <BehaviourReport :report="report" @reportResolved="reportResolved"/>
                </div>
            </div>
            <div v-else class="text-gray-500 mb-6">No submission reports found.</div>
            <h1 class="text-2xl sm:text-3xl font-extrabold text-blue-700 mb-4 mt-8">Comment Reports</h1>
            <div v-if="comment_reports.length > 0" class="flex flex-col gap-4">
                <div v-for="report in comment_reports" :key="report.id">
                    <BehaviourReport :report="report" @reportResolved="reportResolved"/>
                </div>
            </div>
            <div v-else class="text-gray-500">No comment reports found.</div>
        </div>
    </AppLayout>
</template>
