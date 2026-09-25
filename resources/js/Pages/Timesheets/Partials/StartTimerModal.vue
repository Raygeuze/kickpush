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
const isInvoiceSource = computed(() => props.source === 'invoice');
const form = computed(() => {
    if (isInvoiceSource.value) return props.state.invoiceStartForm;

    return isDaySource.value ? props.state.dayStartForm : props.state.startForm;
});
const taskOptions = computed(() => {
    if (isInvoiceSource.value) return props.state.invoiceProjectTasks;

    return isDaySource.value ? props.state.dayProjectTasks : props.state.projectTasks;
});
const isSubmitting = computed(() => {
    if (isInvoiceSource.value) return props.state.startingInvoiceTimer;

    return isDaySource.value ? props.state.startingDayTimer : props.state.startingTimer;
});
const formError = computed(() => isInvoiceSource.value ? props.state.invoiceStartFormError : props.state.formErrors);
const blockedByActiveSession = computed(() => isInvoiceSource.value ? props.state.isInlineTimerActive : props.state.hasActiveSession);
const contextLabel = computed(() => isInvoiceSource.value ? 'this invoice' : props.dayLabel);
const hasManualDuration = computed(() => form.value.duration.trim() !== '');
const submitLabel = computed(() => {
    if (isSubmitting.value) {
        return hasManualDuration.value ? 'Recording...' : 'Starting...';
    }

    return hasManualDuration.value ? 'Record' : 'Start';
});

function close() {
    if (isInvoiceSource.value) {
        props.state.cancelInvoiceStartTimer();
        return;
    }

    if (isDaySource.value) {
        props.state.cancelDayStartTimer();
        return;
    }

    props.state.cancelStartTimer();
}

function submit() {
    if (isInvoiceSource.value) {
        props.state.startInvoiceTimer();
        return;
    }

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
                    <h2 class="mt-1 text-lg font-bold text-gray-950 dark:text-white">Record time on {{ contextLabel }}</h2>
                </div>
                <button type="button" class="text-sm font-semibold text-gray-500 hover:text-gray-900 dark:hover:text-white" @click="close">Close</button>
            </div>

            <p v-if="formError" class="mt-4 rounded-lg bg-red-50 px-3 py-2 text-sm text-red-700 dark:bg-red-950/40 dark:text-red-300">{{ formError }}</p>

            <div class="mt-4 grid gap-3" :class="showProject ? 'sm:grid-cols-2' : ''">
                <label v-if="showProject" class="text-xs font-semibold uppercase text-gray-500 dark:text-gray-400">
                    Project
                    <select v-model="form.project_id" class="mt-1 w-full rounded-lg border-gray-300 text-sm dark:border-gray-700 dark:bg-gray-900 dark:text-white">
                        <option value="">Select project</option>
                        <template v-if="isInvoiceSource">
                            <option v-for="project in state.clientProjects" :key="project.id" :value="String(project.id)">{{ project.name }}</option>
                        </template>
                        <template v-else>
                            <optgroup v-for="group in state.projectsByClient" :key="group.clientName" :label="group.clientName">
                                <option v-for="project in group.projects" :key="project.id" :value="String(project.id)">{{ project.name }}</option>
                            </optgroup>
                        </template>
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

            <div class="mt-3 grid gap-3 sm:grid-cols-3">
                <label class="text-xs font-semibold uppercase text-gray-500 dark:text-gray-400 sm:col-span-2">
                    Notes (optional)
                    <textarea
                        v-model="form.notes"
                        rows="2"
                        placeholder="Add a note about this session..."
                        class="mt-1 h-16 w-full resize-none rounded-lg border-gray-300 text-sm dark:border-gray-700 dark:bg-gray-900 dark:text-white"
                    ></textarea>
                </label>

                <label class="text-xs font-semibold uppercase text-gray-500 dark:text-gray-400">
                    Time
                    <input
                        v-model="form.duration"
                        type="text"
                        placeholder="0:00"
                        class="mt-1 h-16 w-full rounded-lg border-gray-300 px-2 py-0 text-right text-2xl dark:border-gray-700 dark:bg-gray-900 dark:text-white"
                    />
                </label>
            </div>

            <div class="mt-6 flex flex-col gap-3 border-t border-gray-100 pt-4 dark:border-gray-800 sm:flex-row sm:items-center sm:justify-between">
                <p class="text-xs text-gray-500 dark:text-gray-400">
                    {{ hasManualDuration ? `Recorded on ${contextLabel} without starting a running timer.` : `Timer runs from now and is recorded on ${contextLabel}.` }}
                </p>
                <div class="flex items-center justify-end gap-3">
                    <button type="button" class="rounded-lg border border-gray-300 px-3 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50 dark:border-gray-700 dark:text-gray-200 dark:hover:bg-gray-900" @click="close">Cancel</button>
                    <button
                        type="submit"
                        class="inline-flex items-center gap-2 rounded-lg bg-emerald-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-emerald-700 disabled:opacity-60"
                        :disabled="isSubmitting || !form.task_id || (!hasManualDuration && blockedByActiveSession)"
                        :title="(!hasManualDuration && blockedByActiveSession) ? 'Stop your active timer before starting another' : 'Start recording time'"
                    >
                        <span>{{ submitLabel }}</span>
                    </button>
                </div>
            </div>
        </form>
    </Modal>
</template>
