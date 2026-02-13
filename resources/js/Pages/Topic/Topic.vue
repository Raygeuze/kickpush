<script setup>
import { Head, Link } from '@inertiajs/vue3';
import { useForm } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import CreateTopicForm from '@/Pages/Topic/CreateTopicForm.vue';
import { toast } from 'vue3-toastify';
import 'vue3-toastify/dist/index.css';
import { ref } from 'vue';
import EditTopicForm from '@/Pages/Topic/EditTopicForm.vue';

const emit = defineEmits(['topicApproved']);

const props = defineProps({
    topic: Object,
});

const topic = ref(props.topic);
const editingTopic = ref(false);

const approveTopic = async (topicId) => {
    try {
        const response = await axios.post(`/admin/topics/${topicId}/approve`);

        if (response.data.flashStatus === 'success'){
            toast.success(response.data.message, {
                autoClose: 1000,
            });

            emit('topicApproved', response.data.topic);
        }
    } catch (error) {
        console.error('Error approving topic:', error);
        if(error.response.data.errors){

            toast.error(error.response.data.message, {
                autoClose: 1000,
            });
        }
    }
};

const handleTopicUpdated = (updatedTopic) => {
    topic.value = updatedTopic;
    editingTopic.value = false;
};


</script>

<template>
    <div class="mx-auto w-full max-w-2xl lg:max-w-7xl border-b py-4 px-2 sm:px-6 bg-white/80 rounded-xl shadow mb-2">
        <div v-if="!editingTopic" class="flex flex-col sm:flex-row items-start sm:items-center gap-2 sm:gap-4">
            <h2 class="text-lg sm:text-xl font-bold text-blue-700">{{ topic.topic }}</h2>
            <p class="text-gray-600 flex-1">{{ topic.description }}</p>
            <div class="text-sm text-gray-500">
                Submitted by <span class="font-semibold">{{ topic.created_by.name }}</span> | {{ new Date(topic.created_at).toLocaleDateString() }}
            </div>
            <div class="flex gap-2 ml-auto mt-2 sm:mt-0">
                <div v-if="!topic.approved" @click="approveTopic(topic.id)" class="text-green-600 underline cursor-pointer font-semibold">Approve</div>
                <div @click="editingTopic = true" class="text-blue-600 underline cursor-pointer font-semibold">Edit</div>
            </div>
        </div>
        <div v-if="editingTopic" class="mt-2">
            <div @click="editingTopic = false" class="text-red-600 underline mb-2 cursor-pointer font-semibold">Back</div>
            <EditTopicForm :topic="topic" @topicUpdated="handleTopicUpdated" />
        </div>
    </div>
</template>
