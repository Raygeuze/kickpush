<script setup>
import { Head, Link } from '@inertiajs/vue3';
import { useForm } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import CreateTopicForm from '@/Pages/Topic/CreateTopicForm.vue';
import Topic from '@/Pages/Topic/Topic.vue';
import { toast } from 'vue3-toastify';
import 'vue3-toastify/dist/index.css';
import { ref } from 'vue';

const props = defineProps({
    approvedTopics: Array,
    unapprovedTopics: Array,
});

const approvedTopics = props.approvedTopics ? ref(props.approvedTopics) : ref([]);
const unapprovedTopics = props.unapprovedTopics ? ref(props.unapprovedTopics) : ref([]);

const handleNewTopicCreated = (newTopic) => {
    unapprovedTopics.value.push(newTopic);
};

const handleTopicApproved = (approvedTopic) => {
    unapprovedTopics.value = unapprovedTopics.value.filter(topic => topic.id !== approvedTopic.id);
    approvedTopics.value.push(approvedTopic);
};


</script>

<template>
    <AppLayout>
        <Head title="Topics" />

        <CreateTopicForm @newTopicCreated="handleNewTopicCreated" />

        <div class="mx-auto w-full max-w-2xl lg:max-w-7xl p-6">
            <h1 class="text-3xl font-extrabold text-blue-700 mb-6">Approved Topics</h1>
            <div class="flex flex-col gap-4">
                <div v-for="topic in approvedTopics" :key="topic.id">
                    <Topic 
                        :topic="topic" 
                        @topicApproved="handleTopicApproved"
                    />
                </div>
            </div>
        </div>
        <div class="mx-auto w-full max-w-2xl lg:max-w-7xl p-6 mt-8">
            <h1 class="text-3xl font-extrabold text-blue-700 mb-6">Unapproved Topics</h1>
            <div class="flex flex-col gap-4">
                <div v-for="topic in unapprovedTopics" :key="topic.id">
                    <Topic 
                        :topic="topic" 
                        @topicApproved="handleTopicApproved"
                    />
                </div>
            </div>
        </div>
    </AppLayout>

</template>
