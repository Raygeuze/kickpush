<script setup>
import { Head, Link } from '@inertiajs/vue3';
import { useForm } from '@inertiajs/vue3';
import DialogModal from '@/Components/DialogModal.vue';
import { ref } from 'vue';


const props = defineProps({
    day: Object,
});

const form = useForm({
    title: '',
    description: '',
    day_id: props.day.id,
});

const showModal = ref(false);

const toggleModal = () => {
    showModal.value = !showModal.value;
};

</script>

<template>
    <Head title="Create Submission" />
    <div class="">
        <h1 class="text-2xl font-bold">Create a Submission</h1>
        <form @submit.prevent="form.post('store')" class="mt-4 max-w-lg">
            <div class="mb-4">
                <label for="title" class="block text-sm font-medium">Title</label>
                <input type="text" id="title" v-model="form.title" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm" required />
            </div>
            <div class="mb-4">
                <label for="description" class="block text-sm font-medium">Description</label>
                <textarea id="description" v-model="form.description" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm" required></textarea>
            </div>
            <button type="submit" class="bg-blue-500 text-black px-4 py-2 rounded">Create</button>
        </form>
    </div>

    <div @click="toggleModal" class="mt-4 max-w-lg cursor-pointer text-blue-500 underline">
        Open Modal
    </div>

    <DialogModal :show="showModal" @close="showModal = null">
        <template #title>
            Create Submission
        </template>

        <template #content>
            <form @submit.prevent="form.post('store')" class="mt-4 max-w-lg">
                <div class="mb-4">
                    <label for="title" class="block text-sm font-medium">Title</label>
                    <input type="text" id="title" v-model="form.title" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm" required />
                </div>
                <div class="mb-4">
                    <label for="description" class="block text-sm font-medium">Description</label>
                    <textarea id="description" v-model="form.description" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm" required></textarea>
                </div>
                <button type="submit" class="bg-blue-500 text-black px-4 py-2 rounded">Create</button>

            </form>
        </template>

        <template #footer>
        </template>
    </DialogModal>
</template>
