<script setup>
import { Head, Link } from '@inertiajs/vue3';
import { useForm } from '@inertiajs/vue3';
import { toast } from 'vue3-toastify';
import 'vue3-toastify/dist/index.css';

const emit = defineEmits(['newTopicCreated']);

defineProps({

});

const form = {
    topic: '',
    description: ''
};

const submit = async (submissionId) => {
    try {
        const response = await axios.post(`/admin/topics/store`, form);

        if (response.data.flashStatus === 'success'){
            toast.success(response.data.message, {
                autoClose: 1000,
            });

            emit('newTopicCreated', response.data.topic);
            form.topic = '';
            form.description = '';
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
    <Head title="Create Topic" />
    <div class="mx-auto w-full max-w-2xl p-6 lg:max-w-7xl">
        <h1 class="text-2xl font-bold">Create a New Topic</h1>
        <div class="mt-4 max-w-lg">
            <div class="mb-4">
                <label for="topic" class="block text-sm font-medium">Topic</label>
                <input type="text" id="topic" v-model="form.topic" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm" required />
            </div>
            <div class="mb-4">
                <label for="description" class="block text-sm font-medium">Description</label>
                <textarea id="description" v-model="form.description" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm" required></textarea>
            </div>
            <button @click="submit()" type="submit" class="bg-blue-500 text-black px-4 py-2 rounded">Create</button>
        </div>
    </div>
</template>
