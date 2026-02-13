<script setup>
import { Head, Link } from '@inertiajs/vue3';
import { useForm } from '@inertiajs/vue3';
import WelcomeFeedSubmission from '@/kickpushComponents/WelcomeFeedSubmission.vue';
import { ref, onMounted, onUnmounted } from 'vue';

    const props = defineProps({
        day: Object,
        submissions: Object
    });

    const submissions = ref(props.submissions);
    const loading = ref(false);
    const page = ref(1);
    const hasMore = ref(true); // To track if there's more data to load
    const scrollContainer = ref(null);

    const fetchData = async () => {
        if (!hasMore.value || loading.value) return;

        loading.value = true;
        try {
            const response = await axios.get(`/api/day/${props.day.id}/submissions`, {
                params: { page: page.value + 1 }
            });
            const newItems = response.data.data;

            submissions.value = submissions.value.concat(newItems);
            page.value++;

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
        if( loading.value ) return;
        const container = scrollContainer.value;
        if (!container) return;

        const { scrollTop, clientHeight, scrollHeight } = container;
        // Load more when scrolled within a certain distance from the bottom (e.g., 100px)
        if (scrollTop + clientHeight >= scrollHeight) {
            fetchData();
        }
    };

    onMounted(() => {
        window.addEventListener('scroll', handleScroll);
    });

    onUnmounted(() => {
        window.removeEventListener('scroll', handleScroll);
    });
</script>

<template>

    <div class="flex min-h-[100vh]">
        <div class="w-1/5 min-h-[100vh]">
            
        </div>
        <div ref="scrollContainer" class="mx-auto w-full max-w-2xl p-6 lg:max-w-7xl min-h-[100vh] bg-gray-200">
            <div v-for="(submission, index) in submissions" :key="submission.id" class="flex">
                <WelcomeFeedSubmission :submission="submission" :index="index" />
            </div>
            <div v-if="loading" class="w-fit mx-auto text-black">Loading more...</div>
        </div>
        <div class="flex w-1/5 min-h-[100vh]">
            <Link
                v-if="$page.props.auth.user"
                :href="route('submissions.create')"
                class="w-fit ml-4 fixed bottom-12 bg-blue-500 text-white px-4 py-2 rounded-full shadow-lg cursor-pointer"
            >
                Create Submission
            </Link>
            <Link
                v-else
                :href="route('login')"
                class="w-fit ml-4 fixed bottom-12 bg-blue-500 text-white px-4 py-2 rounded-full shadow-lg cursor-pointer"
            >
                Create Submission
            </Link>
        </div>
    </div>

</template>