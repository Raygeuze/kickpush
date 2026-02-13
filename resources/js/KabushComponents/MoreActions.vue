<script setup>
import { Head, Link } from '@inertiajs/vue3';
import { useForm } from '@inertiajs/vue3';
import { capitalize, ref } from 'vue';
import Dropdown from '@/Components/Dropdown.vue';
import { toast } from 'vue3-toastify';
import { usePage } from '@inertiajs/vue3';

const $page = usePage();

const emit = defineEmits(['toggleLoginModal', 'setShowEdit']);

const props = defineProps({
    parent: Object,
    parent_type: String, // 'submission' or 'comment'
    direction: String,
    isUsersParent: Boolean,
});

const parent = ref(props.parent);

const copyLink = () => {
    const link = `${window.location.origin}/${parent.value.type}s/${parent.value.id}/show`;
    navigator.clipboard.writeText(link).then(() => {
        toast.success('Link copied to clipboard!', {
            autoClose: 1000,
        });
    }).catch(err => {
        console.error('Failed to copy link: ', err);
        toast.error('Failed to copy link.', {
            autoClose: 1000,
        });
    });
};


</script>

<template>
    <Dropdown :align="direction" width="48">
        <template #trigger>
            <button class="mt-2 cursor-pointer">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 5v.01M12 12v.01M12 19v.01M12 6a1 1 0 110-2 1 1 0 010 2zm0 7a1 1 0 110-2 1 1 0 010 2zm0 7a1 1 0 110-2 1 1 0 010 2z" />
                </svg>
            </button>
        </template>

        <template #content>
            <button v-if="parent_type === 'submission'" @click="copyLink()" class="w-full text-left px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">
                <i class="fas fa-link mr-2 w-[15%]"></i>
                Copy Link
            </button>

            <div>
                <Link v-if="$page.props.auth.user" :href="`/${parent_type}s/${parent.id}/report/create`" class="block text-left px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">
                    <i class="fas fa-flag mr-2 w-[15%]"></i>
                    Report
                </Link>
                <div v-else>
                    <button @click="$emit('toggleLoginModal')" class="w-full text-left px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">
                        <i class="fas fa-flag mr-2 w-[15%]"></i>
                        Report
                    </button>
                </div>
            </div>
            <div v-if="$page.props.auth.user && isUsersParent" @click="emit('setShowEdit')" class="block text-left px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">
                <i class="fas fa-edit mr-2 w-[15%]"></i>
                Edit
            </div>
        </template>
    </Dropdown>
</template>
