<script setup>
import { Head, Link } from '@inertiajs/vue3';
import { useForm } from '@inertiajs/vue3';
import { ref } from 'vue';
import { toast } from 'vue3-toastify';
import 'vue3-toastify/dist/index.css';
import { defineEmits } from 'vue';


const props = defineProps({
    topic: Object,
});

const topic = ref(props.topic);
const user = props.user;
const showEdit = ref(false);

const form = {
    topic: topic.value.topic,
    description: topic.value.description,
};

const emit = defineEmits(['topicUpdated']);

const submit = async (topicId) => {
    try {
        const response = await axios.put(`/admin/topics/${topicId}/update`, form);

        if (response.data.flashStatus === 'success'){
            toast.success(response.data.message, {
                autoClose: 1000,
            });

            emit('topicUpdated', response.data.topic);
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
    <div class="mt-4 max-w-lg">
        <div class="mb-4">
            <label for="topic" class="block text-sm font-medium">Topic</label>
            <input type="text" id="topic" v-model="form.topic" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm" required />
        </div>
        <div class="mb-4">
            <label for="description" class="block text-sm font-medium">Description</label>
            <textarea id="description" v-model="form.description" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm" required></textarea>
        </div>
        <button @click="submit(topic.id)" type="submit" class="bg-blue-500 text-black px-4 py-2 rounded">Update</button>
    </div>
</template>
