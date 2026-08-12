<script setup>
import { toast } from 'vue3-toastify';
import 'vue3-toastify/dist/index.css';
import { ref } from 'vue';
import { usePage } from '@inertiajs/vue3';
import { Link } from '@inertiajs/vue3';
import LikeComment from '@/Pages/Submission/Comments/LikeComment.vue';
import CommentForm from '@/Pages/Submission/Comments/CommentForm.vue';
import MoreActions from '@/KickpushComponents/MoreActions.vue';
import Reply from '@/Pages/Submission/Comments/Reply.vue';
import { inject } from 'vue';

const utilities = inject('utilities');

const $page = usePage();

const emit = defineEmits(['toggleLoginModal', 'commentSubmitted']);

const props = defineProps({
    comment: Object,
    submission: Object
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
    <div class="border-l-2 pl-2 mr-4 lg:ml-4" @click.self="toggleReplies(comment.id)">
        <div class="flex">
            <div class="flex-col w-full rounded-lg cursor-pointer" @click.stop="toggleReplies(comment.id)">
                <div class="flex-col w-full">
                    <div class="flex">
                        <div v-if="$page.props.jetstream.managesProfilePhotos" class="flex min-w-fit text-sm mr-2 border-2 border-transparent rounded-full focus:outline-none focus:border-gray-300 transition">
                            <img class="size-8 rounded-full object-cover" :src="comment.user.profile_photo_url" :alt="comment.user.name">
                        </div>
                        <div class="flex items-center mb-2">
                            <Link :href="route('user.show', {id: comment.user.id})" class="font-bold">@{{ comment.user.name }}</Link>
                            <div class="flex ml-2">
                                <span class=" text-gray-500 text-xs">{{ utilities.timeAgo(comment.created_at) }}</span>
                            </div>
                        </div>
                    </div>
                    <div class="text-gray-800 w-full flex-col">

                        <div class="ml-2 w-full">
                            <p class="" v-if="!showEdit">{{ commentRef.content }}</p>
                            <div v-else class="w-full mx-auto flex items-center">
                                <textarea 
                                    v-model="form.content" 
                                    class="flex-1 h-auto rounded-lg border border-gray-300 text-gray-800 bg-transparent shadow focus:border-blue-500 focus:ring-2 focus:ring-blue-200 transition resize-none mr-2"
                                    oninput="this.style.height = 'auto'; this.style.height = (this.scrollHeight) + 'px';"
                                    ></textarea>
                                <button @click="submit(comment.id)" class="flex items-center gap-2 bg-blue-600 text-white font-semibold px-4 py-2 rounded-lg shadow hover:bg-blue-700 transition mr-2"><i class="fa fa-save"></i></button>
                                <button @click="showEdit = false" class="flex items-center gap-2 bg-gray-600 text-white font-semibold px-4 py-2 rounded-lg shadow hover:bg-blue-700 transition"><i class="fa fa-times"></i></button>
                            </div>

                            <div class="flex w-full">
                                <LikeComment :comment="comment" @toggleLoginModal="emit('toggleLoginModal')"/>

                                <button v-if="$page.props.auth.user" class="ml-4 mt-2 underline" @click.stop="activeReplyCommentId ? activeReplyCommentId = null : activeReplyCommentId = comment.id">Reply</button>
                                <button v-else @click="emit('toggleLoginModal')" class="ml-4 mt-2 underline">Reply</button>
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

        <div v-if="activeReplyCommentId === comment.id" class="mt-4 ml-4 mr-4">
            <CommentForm 
                :submission="submission" 
                :comment="comment" 
                @commentSubmitted="emit('commentSubmitted', $event)" 
                @hideReplyForm="activeReplyCommentId = null"
                @toggleLoginModal="emit('toggleLoginModal')"
                :isReply="true"
            />
        </div>

        <div v-if="comment.children.length > 0 && hiddenReplies.has(comment.id)" class="text-sm text-gray-500 cursor-pointer text-center mt-4" @click.stop="toggleReplies(comment.id)">
            Show Replies ({{ comment.children ? comment.children.length : 0 }})
        </div>

        <div v-if="!hiddenReplies.has(comment.id)" class="">
            <div v-if="comment.children && comment.children.length > 0" class="lg:mt-4 lg:ml-4">
                <div v-for="child in comment.children" :key="child.id" class="mb-2">
                    <Reply 
                        :comment="child" 
                        :submission="submission"
                        :parent="comment"
                        @commentSubmitted="emit('commentSubmitted', $event)" 
                        @toggleLoginModal="emit('toggleLoginModal')"
                    />
                </div>
            </div>
        </div>

        <div v-if="comment.children.length > 0 && !hiddenReplies.has(comment.id)" class="text-sm text-gray-500 cursor-pointer text-center" @click.stop="toggleReplies(comment.id)">
            Hide Replies ({{ comment.children ? comment.children.length : 0 }})
        </div>
    </div>
</template>
