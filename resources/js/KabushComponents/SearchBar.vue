<script setup>
import { Head, Link } from '@inertiajs/vue3';
import { useForm } from '@inertiajs/vue3';
import { ref, onMounted, onUnmounted } from 'vue';

    const props = defineProps({
        days: Object,
        submissions: Object,
        users: Object,
    });

    const days = ref(props.days);
    const submissions = ref(props.submissions);
    const users = ref(props.users);

    const form = useForm({
        query: '',
    });

    const loading = ref(false);


    const submit = async () => {
        form.get(route('search'), { query: form.query });
    };
</script>

<template>
    <div class="lg:flex hidden">
        <form @submit.prevent="submit" class="flex items-center w-full max-w-xl border rounded-lg">
            <input
                v-model="form.query"
                type="text"
                name="query"
                placeholder="Topics, stories, users..."
                class="flex-1 rounded-full border-none bg-transparent px-2 py-2 text-gray-800 focus:outline-none focus:ring-0"
                :disabled="form.processing"
            />
            <button type="submit" class="ml-4 flex items-center justify-center rounded-lg bg-blue-400 px-4 py-2 text-white font-semibold shadow hover:bg-blue-600 transition">
                <i class="fa fa-search mr-1"></i> Search
            </button>
        </form>
    </div>
</template>