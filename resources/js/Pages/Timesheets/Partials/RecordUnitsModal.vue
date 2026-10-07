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
    dayLabel: {
        type: String,
        default: '',
    },
});

const form = computed(() => props.state.unitsForm);
const task = computed(() => props.state.selectedUnitTask);
const previewTotal = computed(() => props.state.unitPreviewTotal);
const isStartMode = computed(() => form.value.mode === 'start');
const activeUnitSession = computed(() => props.state.activeUnitSession);

const unitNoun = computed(() => {
    if (!task.value) {
        return 'units';
    }

    const quantity = Number(form.value.quantity);

    if (Number.isFinite(quantity) && Math.abs(quantity - 1) < 0.0001) {
        return task.value.unit_label || 'unit';
    }

    return task.value.unit_label_plural || `${task.value.unit_label || 'unit'}s`;
});

function close() {
    props.state.cancelRecordUnits();
}

function submit() {
    if (isStartMode.value) {
        props.state.startUnitSession();

        return;
    }

    props.state.recordUnits();
}
</script>

<template>
    <Modal :show="show" max-width="lg" @close="close">
        <form class="rounded-lg bg-white p-5 shadow-xl dark:bg-gray-950" @submit.prevent="submit">
            <div class="flex items-start justify-between gap-4">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-wide text-indigo-700 dark:text-indigo-400">Unit billing</p>
                    <h2 class="mt-1 text-lg font-bold text-gray-950 dark:text-white">Record unit work on {{ dayLabel }}</h2>
                </div>
                <button type="button" class="text-sm font-semibold text-gray-500 hover:text-gray-900 dark:hover:text-white" @click="close">Close</button>
            </div>

            <p v-if="state.unitsFormError" class="mt-4 rounded-lg bg-red-50 px-3 py-2 text-sm text-red-700 dark:bg-red-950/40 dark:text-red-300">
                {{ state.unitsFormError }}
            </p>

            <div
                v-else-if="isStartMode && activeUnitSession"
                class="mt-4 flex flex-col gap-2 rounded-lg border border-emerald-300 bg-emerald-50 px-3 py-3 text-sm text-emerald-900 dark:border-emerald-800 dark:bg-emerald-950/40 dark:text-emerald-200"
            >
                <p class="font-semibold">Unit session already running</p>
                <p>
                    {{ activeUnitSession.task_name }} · {{ state.formatPreciseDuration(state.sessionDuration(activeUnitSession)) }}
                    <span v-if="activeUnitSession.quantity_display"> · {{ activeUnitSession.quantity_display }}</span>
                </p>
            </div>

            <p
                v-else-if="state.unitProjects.length === 0"
                class="mt-4 rounded-lg bg-amber-50 px-3 py-2 text-sm text-amber-800 dark:bg-amber-950/40 dark:text-amber-300"
            >
                No tasks are set to per-unit billing yet. Set a task's billing mode to "Per unit" on the Tasks page first.
            </p>

            <div class="mt-4 grid gap-3 sm:grid-cols-2">
                <label class="text-xs font-semibold uppercase text-gray-500 dark:text-gray-400">
                    Project
                    <select v-model="form.project_id" class="mt-1 w-full rounded-lg border-gray-300 text-sm dark:border-gray-700 dark:bg-gray-900 dark:text-white">
                        <option value="">Select project</option>
                        <option v-for="project in state.unitProjects" :key="project.id" :value="String(project.id)">{{ project.name }}</option>
                    </select>
                </label>

                <label class="text-xs font-semibold uppercase text-gray-500 dark:text-gray-400">
                    Task
                    <select
                        v-model="form.task_id"
                        class="mt-1 w-full rounded-lg border-gray-300 text-sm dark:border-gray-700 dark:bg-gray-900 dark:text-white"
                        :disabled="!form.project_id"
                    >
                        <option value="">Select task</option>
                        <option v-for="option in state.unitProjectTasks" :key="option.id" :value="String(option.id)">{{ option.name }}</option>
                    </select>
                </label>
            </div>

            <div class="mt-3 grid gap-3 sm:grid-cols-2">
                <div class="text-xs font-semibold uppercase text-gray-500 dark:text-gray-400">
                    How many {{ unitNoun }}?
                    <div class="mt-1 flex items-stretch gap-2">
                        <button
                            type="button"
                            class="inline-flex h-16 w-14 items-center justify-center rounded-lg border border-gray-300 text-2xl text-gray-700 transition hover:bg-gray-50 disabled:opacity-40 dark:border-gray-700 dark:text-gray-200 dark:hover:bg-gray-900"
                            :disabled="Number(form.quantity) <= 0"
                            aria-label="Decrease quantity"
                            @click="form.quantity = String(Math.max(0, (Number(form.quantity) || 0) - 1))"
                        >
                            &minus;
                        </button>
                        <input
                            v-model="form.quantity"
                            type="number"
                            step="0.5"
                            min="0"
                            placeholder="0"
                            class="h-16 w-full rounded-lg border-gray-300 px-2 py-0 text-center text-2xl dark:border-gray-700 dark:bg-gray-900 dark:text-white"
                        />
                        <button
                            type="button"
                            class="inline-flex h-16 w-14 items-center justify-center rounded-lg border border-gray-300 text-2xl text-gray-700 transition hover:bg-gray-50 dark:border-gray-700 dark:text-gray-200 dark:hover:bg-gray-900"
                            aria-label="Increase quantity"
                            @click="form.quantity = String((Number(form.quantity) || 0) + 1)"
                        >
                            +
                        </button>
                    </div>
                </div>

                <div>
                    <div class="rounded-lg border border-gray-200 bg-gray-50 px-3 py-3 dark:border-gray-800 dark:bg-gray-900">
                        <p class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Time tracking</p>
                        <div class="mt-2 flex items-center justify-between gap-4">
                            <p class="text-sm font-semibold text-gray-800 dark:text-gray-200" :class="isStartMode ? 'text-emerald-700 dark:text-emerald-300' : ''">
                                Track with live timer
                            </p>
                            <button
                                type="button"
                                class="inline-flex h-6 w-11 shrink-0 items-center rounded-full p-0.5 transition"
                                :class="isStartMode ? 'bg-emerald-600' : 'bg-gray-300 dark:bg-gray-700'"
                                role="switch"
                                aria-label="Start timer"
                                :aria-checked="isStartMode"
                                @click="form.mode = isStartMode ? 'record' : 'start'"
                            >
                                <span class="h-5 w-5 rounded-full bg-white shadow-sm transition-transform" :class="isStartMode ? 'translate-x-5' : 'translate-x-0'"></span>
                            </button>
                        </div>
                    </div>

                    <input
                        v-if="!isStartMode"
                        v-model="form.duration"
                        type="text"
                        placeholder="0:00"
                        class="mt-2 h-16 w-full rounded-lg border-gray-300 px-2 py-0 text-right text-2xl dark:border-gray-700 dark:bg-gray-900 dark:text-white"
                    />

                    <div v-else class="mt-2 rounded-lg border border-emerald-200 bg-emerald-50/70 px-3 py-2 text-xs text-emerald-900 dark:border-emerald-800 dark:bg-emerald-950/30 dark:text-emerald-200">
                        Timer starts when you save. Stop later to capture duration.
                    </div>
                </div>
            </div>

            <label class="mt-3 block text-xs font-semibold uppercase text-gray-500 dark:text-gray-400">
                Notes (optional)
                <textarea
                    v-model="form.notes"
                    rows="2"
                    placeholder="Add a note about this work..."
                    class="mt-1 h-16 w-full resize-none rounded-lg border-gray-300 text-sm dark:border-gray-700 dark:bg-gray-900 dark:text-white"
                ></textarea>
            </label>

            <div class="mt-6 flex flex-col gap-3 border-t border-gray-100 pt-4 dark:border-gray-800 sm:flex-row sm:items-center sm:justify-between">
                <p class="text-xs text-gray-500 dark:text-gray-400">
                    <template v-if="isStartMode">
                        Quantity locks billing now, and a timer starts so you can stop later to capture duration.
                    </template>
                    <template v-else>
                        This creates a unit record only, without starting a running timer.
                    </template>
                    <template v-if="previewTotal !== null">
                        {{ form.quantity }} × {{ Number(task.unit_rate).toFixed(2) }} =
                        <span class="font-semibold text-gray-900 dark:text-white">{{ previewTotal.toFixed(2) }}</span>
                    </template>
                    <template v-else-if="task && task.unit_rate === null">
                        This task has no rate per unit set.
                    </template>
                    <template v-else>
                        Recorded on {{ dayLabel }}.
                    </template>
                </p>
                <div class="flex items-center justify-end gap-3">
                    <button type="button" class="rounded-lg border border-gray-300 px-3 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50 dark:border-gray-700 dark:text-gray-200 dark:hover:bg-gray-900" @click="close">Cancel</button>
                    <button
                        type="submit"
                        class="inline-flex items-center gap-2 rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-indigo-700 disabled:opacity-60"
                        :disabled="state.recordingUnits || state.startingUnitSession || !form.task_id || !(Number(form.quantity) > 0) || (isStartMode && state.hasActiveSession)"
                    >
                        <span>
                            {{
                                isStartMode
                                    ? (state.startingUnitSession ? 'Starting...' : 'Start session')
                                    : (state.recordingUnits ? 'Recording...' : 'Record units')
                            }}
                        </span>
                    </button>
                </div>
            </div>
        </form>
    </Modal>
</template>
