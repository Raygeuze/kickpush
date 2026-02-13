<script setup>
import { Head, Link } from '@inertiajs/vue3';
import { useForm } from '@inertiajs/vue3';

defineProps({

});

const form = useForm({
    topic: '',
    description: ''
});

</script>

<template>
    <Head title="Create Day" />
    <div class="">
        <h1 class="text-2xl font-bold">Create a New Day</h1>
        <form @submit.prevent="form.post('store')" class="mt-4 max-w-lg">
            <input type="hidden" name="_token" :value="$page.props.csrf_token" />
            <div class="mb-4">
                <label for="topic" class="block text-sm font-medium">Topic</label>
                <input type="text" id="topic" v-model="form.topic" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm" required />
            </div>
            <div class="mb-4">
                <label for="description" class="block text-sm font-medium">Description</label>
                <textarea id="description" v-model="form.description" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm" required></textarea>
            </div>
            <button type="submit" class="bg-blue-500 text-black px-4 py-2 rounded">Create</button>
        </form>
    </div>
</template>
