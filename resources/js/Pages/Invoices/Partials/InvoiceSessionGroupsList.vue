<script setup>
import InvoiceSessionRowItem from '@/Pages/Invoices/Partials/InvoiceSessionRowItem.vue';

defineProps({
    assignedSessionsByProject: {
        type: Array,
        default: () => [],
    },
    controller: {
        type: Object,
        required: true,
    },
    formatters: {
        type: Object,
        required: true,
    },
});
</script>

<template>
    <div class="mt-4 space-y-5">
        <section
            v-for="section in assignedSessionsByProject"
            :key="section.key"
            class="rounded-xl border border-gray-200 p-3 dark:border-gray-700"
        >
            <div class="flex items-center justify-between gap-3">
                <h3 class="text-sm font-semibold text-gray-900 dark:text-white">{{ section.projectName }}</h3>
                <p class="text-xs font-medium text-gray-600 dark:text-gray-300">
                    Total: {{ formatters.formatDuration(controller.totalDurationSecondsForProject(section)) }}
                </p>
            </div>

            <div class="mt-3 space-y-3">
                <InvoiceSessionRowItem
                    v-for="session in controller.visibleSessionsForProject(section)"
                    :key="session.id"
                    :session="session"
                    :controller="controller"
                    :formatters="formatters"
                />

                <div v-if="controller.hasMoreSessionsForProject(section)" class="flex justify-end">
                    <button
                        type="button"
                        class="rounded-lg border border-gray-300 px-3 py-1.5 text-xs font-semibold text-gray-700 transition hover:bg-gray-100 dark:border-gray-600 dark:text-gray-200 dark:hover:bg-gray-800"
                        @click="controller.toggleProjectSection(section.key)"
                    >
                        {{ controller.isProjectSectionExpanded(section.key) ? 'Show Latest 2' : `View All (${controller.hiddenSessionsCountForProject(section)} more)` }}
                    </button>
                </div>
            </div>
        </section>
    </div>
</template>
