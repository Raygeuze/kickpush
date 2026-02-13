<script setup>
import { Head, Link } from '@inertiajs/vue3';
import { useForm } from '@inertiajs/vue3';
import { usePage } from '@inertiajs/vue3';
import { toast } from 'vue3-toastify';
import 'vue3-toastify/dist/index.css';
import { ref } from 'vue';

const $page = usePage();

const emit = defineEmits(['closeEdit', 'toggleLoginModal', 'commentSubmitted', 'hideReplyForm']);

const props = defineProps({
    submission: Object,
    comment: Object,
    isReply: {
        type: Boolean,
        default: false
    }
});

const form = ref({
    content: '',
    submission_id: props.submission.id,
    user_id: $page.props.auth.user ? $page.props.auth.user.id : null,
    parent_id: props.comment ? props.comment.id : null,
    replying_to_id: props.comment ? props.comment.user.id : null,
});

const submit = async (submissionId) => {
    try {
        const response = await axios.post(`/submissions/${submissionId}/comments/store`, form.value);

        if (response.data.flashStatus === 'success'){
            toast.success(response.data.message, {
                autoClose: 1000,
            });

            emit('commentSubmitted', response.data.comment);
            form.value.content = '';
            if(props.comment){
                emit('hideReplyForm');
            }
        }
    } catch (error) {
        if(error.response.data.errors){

            toast.error(error.response.data.message, {
                autoClose: 1000,
            });
        }
    }
};


</script>

<template>
    <div class="w-full mx-auto mb-8 flex">
        <div v-if="$page.props.auth.user && $page.props.jetstream.managesProfilePhotos" class="flex text-sm border-2 items-center mr-2 border-transparent rounded-full focus:outline-none focus:border-gray-300 transition">
            <img class="size-8 rounded-full object-cover" :src="$page.props.auth.user.profile_photo_url" :alt="$page.props.auth.user.name">
        </div>
        <div class="w-full mx-auto flex items-center">
            <textarea 
                id="content" 
                :placeholder="isReply ? 'Replying to @' + comment.user.name : 'Add a comment...'" 
                v-model="form.content" 
                class="flex-1 rounded-lg border border-gray-300 text-gray-800 bg-transparent shadow focus:border-blue-500 focus:ring-2 focus:ring-blue-200 transition resize-none h-11 mr-2" 
                required
                oninput="this.style.height = 'auto'; this.style.height = (this.scrollHeight) + 'px';"
            ></textarea>
            <button v-if="$page.props.auth.user" @click="submit(submission.id)" type="submit" class="flex items-center gap-2 bg-blue-600 text-white font-semibold px-4 py-2 rounded-lg shadow hover:bg-blue-700 transition">
                <i class="fa fa-paper-plane"></i>
            </button>
            <button v-else @click="emit('toggleLoginModal')" class="flex items-center gap-2 bg-blue-600 text-white font-semibold px-4 py-2 rounded-lg shadow hover:bg-blue-700 transition">
                <i class="fa fa-paper-plane"></i>
            </button>
        </div>
    </div>
</template>
