<script setup>
import { toast } from 'vue3-toastify';
import 'vue3-toastify/dist/index.css';
import { ref } from 'vue';
import { usePage } from '@inertiajs/vue3';
import { Head, Link } from '@inertiajs/vue3';
import LikeComment from '@/Pages/Submission/Comments/LikeComment.vue';
import CommentForm from '@/Pages/Submission/Comments/CommentForm.vue';
import Comment from '@/Pages/Submission/Comments/Comment.vue';

const emit = defineEmits(['toggleLoginModal']);

const $page = usePage();

const props = defineProps({
    submission: Object
});

const submission = ref(props.submission);



const commentSubmitted = (comment) => {
    if(!comment.parent_id)
        submission.value.parent_comments.push(comment);
    else {
        const parentComment = submission.value.parent_comments.find(c => c.id === comment.parent_id);
        if(parentComment){
            if(!parentComment.children) parentComment.children = [];
            parentComment.children.push(comment);
        }
    }
};

const toggleReplies = (commentId) => {
    if (hiddenReplies.value.has(commentId)) {
        hiddenReplies.value.delete(commentId);
    } else {
        hiddenReplies.value.add(commentId);
    }
}


</script>

<template>
    <div class="mt-8 max-w-4xl">
        <CommentForm
            :submission="submission" 
            @commentSubmitted="commentSubmitted"
            @hideReplyForm="activeReplyCommentId = null"
            @toggleLoginModal="emit('toggleLoginModal')"
            :isReply="false"
            class=""
        />

        <div v-if="submission.parent_comments && submission.parent_comments.length > 0">
            <div v-for="comment in submission.parent_comments" :key="comment.id" class="mx-auto mb-4">
                <Comment 
                    :comment="comment" 
                    :submission="submission" 
                    @commentSubmitted="commentSubmitted"
                    @toggleLoginModal="emit('toggleLoginModal')" 
                />
            </div>
        </div>
        <div v-else class="border-l-2">
            <p class="ml-12 text-gray-800">No comments yet.</p>
        </div>
    </div>
</template>
