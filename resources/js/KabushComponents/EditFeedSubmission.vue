<script setup>
import { Head, Link } from '@inertiajs/vue3';
import { useForm } from '@inertiajs/vue3';
import { ref } from 'vue';
import { toast } from 'vue3-toastify';
import 'vue3-toastify/dist/index.css';
import { defineEmits } from 'vue';


const props = defineProps({
    submission: Object,
    index: Number,
    user: Object,
    todaysVoteCount: Object,
});

const submission = props.submission;
const votes = ref(props.submission.votes);
const index = props.index;
const user = props.user;
const isUsersSubmission = user && submission.user_id === user.id;
const showEdit = ref(false);

const form = {
    title: props.submission.title,
    description: props.submission.description,
};

const emit = defineEmits(['closeEdit']);

const submit = async (submissionId) => {
    try {
        const response = await axios.put(`/api/submission/${submissionId}/update`, form);

        if (response.data.flashStatus === 'success'){
            toast.success(response.data.message, {
                autoClose: 1000,
            });

            emit('closeEdit', response.data.submission);
        }
    } catch (error) {
        console.error('Error voting:', error);
        if(error.response.data.errors){
            // emit('closeEdit');

            toast.error(error.response.data.message, {
                autoClose: 1000,
            });
        }
    }
};

</script>

<template>
    <div class="w-full max-w-2xl" v-if="!showEdit">
        <p class="mb-1">Editing submission</p>        
        <input class="w-full rounded-lg border border-gray-300 p-2 mb-2 text-gray-800 shadow focus:border-blue-500 focus:ring-2 focus:ring-blue-200 transition mr-2" v-model="form.title" placeholder="Edit title..." />
        <textarea class="w-full rounded-lg border border-gray-300 p-2 text-gray-800 shadow focus:border-blue-500 focus:ring-2 focus:ring-blue-200 transition resize-none mb-2" name="description" id="description" v-model="form.description" placeholder="Edit description..."></textarea>
        <div class="flex">
            <div class="flex ml-auto gap-2 mb-4">
                <button type="submit" class="flex items-center gap-2 bg-blue-600 text-white font-semibold px-4 py-2 rounded-lg shadow hover:bg-blue-700 transition" @click="submit(submission.id)"><i class="fa fa-save"></i></button>
                <button type="button" class="flex items-center gap-2 bg-gray-600 text-white font-semibold px-4 py-2 rounded-lg shadow hover:bg-blue-700 transition" @click="emit('closeEdit')"><i class="fa fa-times"></i></button>
            </div>
        </div>
    </div>
</template>
