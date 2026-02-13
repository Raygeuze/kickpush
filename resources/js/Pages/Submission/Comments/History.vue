<script setup>
import { Head, Link } from '@inertiajs/vue3';
import { useForm } from '@inertiajs/vue3';
import { ref } from 'vue';
import AppLayout from '@/Layouts/AppLayout.vue';


const props = defineProps({
    comment: Object,
    audits: Array,
});


</script>

<template>
    <AppLayout>
        <Head title="ALL DAY!" />
        <h1 class="text-2xl font-bold">Comment History</h1>

        <div v-for="audit in audits" :key="audit.id" class="mb-4 p-4 border border-gray-300 rounded-md shadow-sm">
            <p class="text-red-500">{{ audit.old_values }}</p>
            <p class="mt-2 text-green-400">{{ audit.new_values }}</p>
        </div>
    </AppLayout>
</template>
