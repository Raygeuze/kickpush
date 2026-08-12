<script setup>
import { Head, Link } from '@inertiajs/vue3';
import { useForm } from '@inertiajs/vue3';
import FeedSubmission from '@/KickpushComponents/FeedSubmission.vue';
import { ref, onMounted, onUnmounted } from 'vue';
import DialogModal from '@/Components/DialogModal.vue';
import CreateSubmissionModal from '@/KickpushComponents/CreateSubmissionModal.vue';
import LoginModal from '@/KickpushComponents/LoginModal.vue';
import RegisterModal from '@/KickpushComponents/RegisterModal.vue';

const props = defineProps({
    day: Object,
    user: Object,
    todaysVoteCount: Object,
    submissions: Object,
});

const form = useForm({
    title: '',
    description: '',
    day_id: props.day.id,
});

const submissions = ref(props.submissions);
const loading = ref(false);
const page = ref(1);
const hasMore = ref(true); // To track if there's more data to load
const scrollContainer = ref(null);
const showSubmissionModal = ref(false);
const showLoginModal = ref(false);
const showRegisterModal = ref(false);

const fetchData = async (usePage = true) => {
    if (!hasMore.value || loading.value) return;

    loading.value = true;
    try {
        const response = await axios.get(`/api/day/${props.day.id}/submissions`, {
            params: { page: usePage ? page.value + 1 : null }
        });
        const newItems = response.data.data;

        if( !usePage ) {
            submissions.value = newItems;
            hasMore.value = response.data.next_page_url !== null;
            loading.value = false;
            return;
        }
        else {
            submissions.value = submissions.value.concat(newItems);
            page.value++;
        }

        if (newItems.length == 0) {
            hasMore.value = false;
        }
    } catch (error) {
        console.error("Error fetching data:", error);
    } finally {
        loading.value = false;
    }
};

const handleScroll = () => {
    if(submissions.value.length > 0){
        const container = scrollContainer.value;
        if (!container) return;

        const { scrollTop, clientHeight, scrollHeight } = container;
        // Load more when scrolled within a certain distance from the bottom (e.g., 100px)
        const scrollYPosition = window.scrollY || window.pageYOffset;
        if (scrollYPosition >= scrollHeight - window.innerHeight - 100) {
            fetchData();
        }
    }
};

onMounted(() => {
    window.addEventListener('scroll', handleScroll);
});

onUnmounted(() => {
    window.removeEventListener('scroll', handleScroll);
});

const toggleSubmissionModal = () => {
    showSubmissionModal.value = !showSubmissionModal.value;
};

const submissionCreated = (submission) => {
    showSubmissionModal.value = false;
    if(submission){
        submissions.value.push(submission);
    }
    else {
        // If no submission returned, just refresh the feed
        // since we can't append the new submission since we don't have it
        fetchData(false);
    }
};

const toggleLoginModal = () => {
    showLoginModal.value = !showLoginModal.value;
    showRegisterModal.value = false;
};

const toggleRegisterModal = () => {
    showRegisterModal.value = !showRegisterModal.value; 
    showLoginModal.value = false;
};

</script>

<template>

    <div class="flex min-h-[100vh]">

        <div ref="scrollContainer" class="flex flex-row w-full lg:p-6 p-2  bg-gray-100">
            <FeedSubmission 
                v-for="(submission, index) in submissions" :key="submission.id"
                :submission="submission" 
                :index="index" 
                :user="props.user" 
                :todays-vote-count="props.todaysVoteCount" 
                @toggleLoginModal="toggleLoginModal" />
        </div>

        <!-- <div v-if="loading" class="w-fit mx-auto text-black">Loading more...</div> -->
        <div v-if="submissions.length === 0 && !loading" class="w-fit mx-auto text-black mt-10">No submissions yet. Be the first to submit!</div>
        <!-- <div v-else-if="!hasMore && !loading" class="w-fit mx-auto text-black mt-10">The end.</div> -->

        <div class="flex lg:w-1/5 min-h-[100vh] absolute w-0 lg:relative">
            <button
                v-if="$page.props.auth.user"
                @click="toggleSubmissionModal"
                class="w-fit lg:ml-4 right-2 lg:right-auto fixed bottom-2 lg:bottom-12 bg-blue-500 text-white px-4 py-2 rounded-full shadow-lg cursor-pointer"
            >
                <i class="fas fa-plus mr-1"></i>
                Create
            </button>
            <button
                v-else
                @click="toggleLoginModal"
                class="w-fit lg:ml-4 right-2 lg:right-auto fixed bottom-2 lg:bottom-12 bg-blue-500 text-white px-4 py-2 rounded-full shadow-lg cursor-pointer"
            >
                <i class="fas fa-plus mr-1"></i>
                Create
            </button>
        </div>
    </div>

    <CreateSubmissionModal 
        :day="props.day" 
        :showModal="showSubmissionModal" 
        @close="submissionCreated" />

    <LoginModal 
        :showModal="showLoginModal" 
        @close="toggleLoginModal"
        @showRegisterModal="toggleRegisterModal" />

    <RegisterModal 
        :showModal="showRegisterModal" 
        @close="toggleRegisterModal"
        @showLoginModal="toggleLoginModal" />



</template>
