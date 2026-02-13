<script setup>
import { toast } from 'vue3-toastify';
import 'vue3-toastify/dist/index.css';
import { ref } from 'vue';
import { usePage } from '@inertiajs/vue3';
import { Link } from '@inertiajs/vue3';

const $page = usePage();

const emit = defineEmits(['toggleLoginModal']);

const props = defineProps({
    comment: Object,
    initialLikesCount: Number,
});

const form = {
    comment: props.comment,
    user_id: $page.props.auth.user ? $page.props.auth.user.id : null,
};

const commentRef = ref(props.comment);

const likeComment = async () => {
    try {
        const response = await axios.post(`/api/comments/${props.comment.id}/like`, form);

        if (response.data.flashStatus === 'success'){
            toast.success(response.data.message, {
                autoClose: 1000,
            });

            commentRef.value = response.data.comment;
        }
        else if (response.data.flashStatus === 'error'){
            toast.error(response.data.message, {
                autoClose: 1000,
            });

            commentRef.value = response.data.comment;
        }
    } catch (error) {
        console.error('Error like comment:', error);
        if(error.response.data.errors){

            toast.error(error.response.data.message, {
                autoClose: 1000,
            });
        }
    }
};

const dislikeComment = async () => {
    try {
        const response = await axios.post(`/api/comments/${props.comment.id}/dislike`, form);
        if (response.data.flashStatus === 'success'){
            toast.success(response.data.message, {
                autoClose: 1000,
            });

            commentRef.value = response.data.comment;
        }
        else if (response.data.flashStatus === 'error'){
            toast.error(response.data.message, {
                autoClose: 1000,
            });

            commentRef.value = response.data.comment;
        }
    } catch (error) {
        console.error('Error disliking comment:', error);
        if(error.response.data.errors){

            toast.error(error.response.data.message, {
                autoClose: 1000,
            });
        }
    }
};


</script>

<template>
    <div class="flex mt-2 text-gray-500">
        <div class="">
            <button v-if="$page.props.auth.user" @click="likeComment()">
                <i class="fa-regular fa-thumbs-up" :class="commentRef.likes.find(like => like.user_id === $page.props.auth.user.id) ? 'text-green-500' : ''"></i>
                {{ commentRef.likes_count }}
            </button>
            <button v-else @click="emit('toggleLoginModal')">    
                <i class="fa-regular fa-thumbs-up"></i>
                {{ commentRef.likes_count }}
            </button>
        </div>
        <div class="ml-4">
            <button v-if="$page.props.auth.user" @click="dislikeComment()">    
                <i class="fa-regular fa-thumbs-down" :class="commentRef.dislikes.find(dislike => dislike.user_id === $page.props.auth.user.id) ? 'text-red-500' : ''"></i>
                {{ commentRef.dislikes_count }}
            </button>
            <button v-else @click="emit('toggleLoginModal')">    
                <i class="fa-regular fa-thumbs-down"></i>
                {{ commentRef.dislikes_count }}
            </button>
        </div>
    </div>
</template>
