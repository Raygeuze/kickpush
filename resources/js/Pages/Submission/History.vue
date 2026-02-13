<script setup>
import { Head, Link } from '@inertiajs/vue3';
import { useForm } from '@inertiajs/vue3';
import { ref } from 'vue';
import AppLayout from '@/Layouts/AppLayout.vue';


const props = defineProps({
    submission: Object,
    audits: Array,
});


</script>

<template>
    <AppLayout>
        <Head title="ALL DAY!" />
        <div class="mx-auto max-w-6xl px-4 sm:px-6 lg:px-8 mt-8">
            <h1 class="text-3xl font-extrabold text-blue-700 mb-6">Submission History</h1>
            <div v-for="audit in audits" :key="audit.id" class="mb-4 p-6 border border-gray-300 rounded-xl bg-white/80 shadow flex flex-col gap-2">
                <div class="flex flex-col gap-2">
                    <div>
                        <span class="font-semibold text-gray-700">Old Values:</span>
                        <span class="text-red-500 ml-2">{{ audit.old_values }}</span>
                    </div>
                    <div>
                        <span class="font-semibold text-gray-700">New Values:</span>
                        <span class="text-green-600 ml-2">{{ audit.new_values }}</span>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
