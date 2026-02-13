<script setup>
import { toast } from 'vue3-toastify';
import 'vue3-toastify/dist/index.css';
import { ref } from 'vue';
import { usePage } from '@inertiajs/vue3';
import { Link } from '@inertiajs/vue3';
import LikeComment from '@/Pages/Submission/Comments/LikeComment.vue';
import CommentForm from '@/Pages/Submission/Comments/CommentForm.vue';
import MoreActions from '@/kickpushComponents/MoreActions.vue';

const $page = usePage();

const emit = defineEmits(['toggleLoginModal', 'commentSubmitted']);

const props = defineProps({
    comment: Object,
    submission: Object,
    parent: Object // the parent comment if this is a reply
});

const form = {
    comment_id: props.comment.id,
    content: props.comment.content,
    user_id: $page.props.auth.user ? $page.props.auth.user.id : null,
};

const submission = ref(props.submission);
const commentRef = ref(props.comment);
const activeReplyCommentId = ref(null);
const hiddenReplies = submission.value.parent_comments ? ref(new Set(submission.value.parent_comments.map(c => c.id))) : ref(new Set());
const showEdit = ref(false);
const isUsersComment = $page.props.auth.user && $page.props.auth.user.id === props.comment.user.id;


const toggleReplies = (commentId) => {
    if (hiddenReplies.value.has(commentId)) {
        hiddenReplies.value.delete(commentId);
    } else {
        hiddenReplies.value.add(commentId);
    }
}

const submit = async (submissionId) => {
    try {
        const response = await axios.post(`/submissions/${submissionId}/comments/update`, form);

        if (response.data.flashStatus === 'success'){
            toast.success(response.data.message, {
                autoClose: 1000,
            });

            // emit('commentSubmitted', response.data.comment);
            showEdit.value = false;
            commentRef.value = response.data.comment;
        }
        else {
            toast.error(response.data.message, {
                autoClose: 1000,
            });
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
    <div class="flex lg:mr-8 mt-4">
        <div class="flex-col w-full rounded-lg p-2 border-l-2">
            <div class="flex-col w-full">
                <div class="flex">
                    <div v-if="$page.props.jetstream.managesProfilePhotos" class="flex min-w-fit text-sm mr-2 border-2 border-transparent rounded-full focus:outline-none focus:border-gray-300 transition">
                        <img class="size-8 rounded-full object-cover" :src="comment.user.profile_photo_url" :alt="comment.user.name">
                    </div>
                    <div class="flex items-center mb-2">
                        <Link :href="route('user.show', {id: comment.user.id})" class="font-bold">@{{ comment.user.name }}</Link>
                        <div class="flex ml-2">
                            <span class=" text-gray-500 text-xs">{{ new Date(comment.created_at).toLocaleDateString() }}</span>
                        </div>
                    </div>
                </div>
                <div class="text-gray-800 w-full flex-col">
                    <div class="ml-2 w-full">
                        <span v-if="showEdit" class="text-blue-500">
                            <span class="text-gray-500">Replying to </span>
                            @{{ comment.is_replying_to.name }}
                        </span>
                        <p v-if="!showEdit" class="ml-2 text-gray-700">@{{ comment.is_replying_to.name }} {{ commentRef.content }}</p>
                        <div v-else class="w-full mx-auto flex items-center mt-2">
                            <textarea 
                                v-model="form.content" 
                                class="flex-1 rounded-lg border border-gray-300 text-gray-800 bg-transparent shadow focus:border-blue-500 focus:ring-2 focus:ring-blue-200 transition resize-none h-auto mr-2"
                                oninput="this.style.height = 'auto'; this.style.height = (this.scrollHeight) + 'px';"
                                ></textarea>
                            <button @click="submit(comment.id)" class="flex items-center gap-2 bg-blue-600 text-white font-semibold px-4 py-2 rounded-lg shadow hover:bg-blue-700 transition mr-2"><i class="fa fa-save"></i></button>
                            <button @click="showEdit = false" class="flex items-center gap-2 bg-gray-600 text-white font-semibold px-4 py-2 rounded-lg shadow hover:bg-blue-700 transition"><i class="fa fa-times"></i></button>
                        </div>
                        <div class="flex w-full">
                            <LikeComment :comment="comment" @toggleLoginModal="emit('toggleLoginModal')"/>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <MoreActions 
            :parent="comment"
            :parent_type="'comment'"
            :direction="index % 2 === 0 ? 'left' : 'right'"
            @toggleLoginModal="emit('toggleLoginModal')"
            :isUsersParent="isUsersComment"
            @setShowEdit="showEdit = !showEdit"
        />
    </div>
    <div v-if="activeReplyCommentId === comment.id" class="max-w-4xl mx-auto py-10 sm:px-6 lg:px-8">
        <CommentForm 
            :submission="submission" 
            :comment="parent" 
            @commentSubmitted="emit('commentSubmitted', $event)" 
            @hideReplyForm="activeReplyCommentId = null"
            @toggleLoginModal="emit('toggleLoginModal')"
        />
    </div>
</template>
