<script setup>
import { computed } from 'vue';
import Modal from '@/Components/Modal.vue';

const props = defineProps({
    state: {
        type: Object,
        required: true,
    },
    show: {
        type: Boolean,
        default: false,
    },
    source: {
        type: String,
        required: true,
    },
    dayLabel: {
        type: String,
        default: '',
    },
    showProject: {
        type: Boolean,
        default: false,
    },
});

const isDaySource = computed(() => props.source === 'day');
const form = computed(() => isDaySource.value ? props.state.dayStartForm : props.state.startForm);
const taskOptions = computed(() => isDaySource.value ? props.state.dayProjectTasks : props.state.projectTasks);
const isSubmitting = computed(() => isDaySource.value ? props.state.startingDayTimer : props.state.startingTimer);

function close() {
    if (isDaySource.value) {
        props.state.cancelDayStartTimer();
        return;
    }

    props.state.cancelStartTimer();
}

function submit() {
    if (isDaySource.value) {
        props.state.startDayTimer();
        return;
    }

    props.state.startTimer();
}
</script>

<template>
    <Modal :show="show" max-width="lg" @close="close">
        <form class="rounded-lg bg-white p-5 shadow-xl dark:bg-gray-950" @submit.prevent="submit">
            <div class="flex items-start justify-between gap-4">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-wide text-emerald-700 dark:text-emerald-400">Start timer</p>
                    <h2 class="mt-1 text-lg font-bold text-gray-950 dark:text-white">Record time on {{ dayLabel }}</h2>
                </div>
                <button type="button" class="text-sm font-semibold text-gray-500 hover:text-gray-900 dark:hover:text-white" @click="close">Close</button>
            </div>

            <p v-if="state.formErrors" class="mt-4 rounded-lg bg-red-50 px-3 py-2 text-sm text-red-700 dark:bg-red-950/40 dark:text-red-300">{{ state.formErrors }}</p>

            <div class="mt-4 grid gap-3" :class="showProject ? 'sm:grid-cols-2' : ''">
                <label v-if="showProject" class="text-xs font-semibold uppercase text-gray-500 dark:text-gray-400">
                    Project
                    <select v-model="form.project_id" class="mt-1 w-full rounded-lg border-gray-300 text-sm dark:border-gray-700 dark:bg-gray-900 dark:text-white">
                        <option value="">Select project</option>
                        <optgroup v-for="group in state.projectsByClient" :key="group.clientName" :label="group.clientName">
                            <option v-for="project in group.projects" :key="project.id" :value="String(project.id)">{{ project.name }}</option>
                        </optgroup>
                    </select>
                </label>

                <label class="text-xs font-semibold uppercase text-gray-500 dark:text-gray-400">
                    Task
                    <select v-model="form.task_id" class="mt-1 w-full rounded-lg border-gray-300 text-sm dark:border-gray-700 dark:bg-gray-900 dark:text-white" :disabled="showProject && !form.project_id">
                        <option value="">Select task</option>
                        <option v-for="task in taskOptions" :key="task.id" :value="String(task.id)">{{ task.name }}</option>
                    </select>
                </label>
            </div>

            <div class="mt-5 flex flex-col-reverse gap-2 sm:flex-row sm:items-center sm:justify-between">
                <p class="text-xs text-gray-500 dark:text-gray-400">Timer runs from now and is recorded on {{ dayLabel }}.</p>
                <div class="flex items-center justify-end gap-3">
                    <button type="button" class="rounded-lg border border-gray-300 px-3 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50 dark:border-gray-700 dark:text-gray-200 dark:hover:bg-gray-900" @click="close">Cancel</button>
                    <button
                        type="submit"
                        class="inline-flex items-center gap-2 rounded-lg bg-emerald-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-emerald-700 disabled:opacity-60"
                        :disabled="isSubmitting || state.hasActiveSession || !form.task_id"
                        :title="state.hasActiveSession ? 'Stop your active timer before starting another' : 'Start recording time'"
                    >
                        <svg viewBox="0 0 24 24" class="h-4 w-4" fill="currentColor" aria-hidden="true"><path d="M8 5v14l11-7z" /></svg>
                        <span>{{ isSubmitting ? 'Starting...' : 'Start timer' }}</span>
                    </button>
                </div>
            </div>
        </form>
    </Modal>
</template>
