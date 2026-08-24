<script setup>
import { computed } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import InvoiceDiscountEditor from '@/Pages/Invoices/Partials/InvoiceDiscountEditor.vue';
import InvoiceSummaryCards from '@/Pages/Invoices/Partials/InvoiceSummaryCards.vue';
import InvoiceExpensesPanel from '@/Pages/Invoices/Partials/InvoiceExpensesPanel.vue';
import InvoiceMetaHeader from '@/Pages/Invoices/Partials/InvoiceMetaHeader.vue';
import InvoiceSessionEntryPanel from '@/Pages/Invoices/Partials/InvoiceSessionEntryPanel.vue';
import InvoiceSessionGroupsList from '@/Pages/Invoices/Partials/InvoiceSessionGroupsList.vue';
import { useInvoicePageController } from '@/Pages/Invoices/composables/useInvoicePageController';

const props = defineProps({
    invoice: {
        type: Object,
        required: true,
    },
    assignedSessions: {
        type: Array,
        default: () => [],
    },
    clientTasks: {
        type: Array,
        default: () => [],
    },
    availableSessions: {
        type: Array,
        default: () => [],
    },
    expenses: {
        type: Array,
        default: () => [],
    },
    summary: {
        type: Object,
        default: () => ({
            sessions_count: 0,
            total_duration_seconds: 0,
            total_expenses_amount: 0,
            subtotal_amount: 0,
            discount_type: null,
            discount_value: 0,
            discount_amount: 0,
            total_billable_amount: 0,
        }),
    },
});

function formatTaskOption(task) {
    if (!task) {
        return 'Unknown task';
    }

    if (task.project?.name) {
        return `${task.name} (${task.project.name})`;
    }

    return task.name;
}

function formatInvoiceId(invoiceId) {
    return `INV${invoiceId}`;
}

function formatDateTime(value) {
    if (!value) {
        return '-';
    }

    return new Date(value).toLocaleDateString();
}

function formatSessionHeaderDate(value) {
    if (!value) {
        return '-';
    }

    return new Date(value).toLocaleDateString(undefined, {
        weekday: 'long',
        year: 'numeric',
        month: 'short',
        day: 'numeric',
    });
}

function formatDuration(totalSeconds) {
    const safeSeconds = Math.max(0, Number(totalSeconds || 0));
    const hours = Math.floor(safeSeconds / 3600);
    const minutes = Math.floor((safeSeconds % 3600) / 60);
    const seconds = safeSeconds % 60;

    return `${hours.toString().padStart(2, '0')}:${minutes
        .toString()
        .padStart(2, '0')}:${seconds.toString().padStart(2, '0')}`;
}

const {
    invoice,
    assignedSessions,
    expenses,
    summary,
    statusMessage,
    isFinalizing,
    isMarkingPaid,
    isSendingInvoiceEmail,
    isDeletingInvoice,
    isSavingDiscount,
    isSubmittingExpense,
    isSubmittingManualSession,
    isInlineTimerLoading,
    discountType,
    discountValue,
    manualDurationMinutes,
    manualStartedAt,
    selectedInlineProjectId,
    selectedInlineTaskId,
    selectedManualProjectId,
    selectedManualTaskId,
    inlineElapsedSeconds,
    inlineActiveSessionId,
    isInlineTimerRunning,
    isInlineTimerPaused,
    expenseName,
    expenseDescription,
    expenseAmount,
    isFinalized,
    isPaid,
    hasActiveClientTasks,
    clientProjects,
    assignedSessionsByProject,
    tasksForProject,
    displaySessionDuration,
    visibleSessionsForProject,
    hasMoreSessionsForProject,
    hiddenSessionsCountForProject,
    totalDurationSecondsForProject,
    isProjectSectionExpanded,
    toggleProjectSection,
    isBusy,
    isExpenseBusy,
    isSavingSessionDuration,
    isSavingSessionDetails,
    isEditingSessionDetails,
    isEditingSessionDuration,
    isSessionStopped,
    getSessionDateDraft,
    getSessionTaskDraft,
    getSessionProjectDraft,
    getSessionDurationDraft,
    sessionEditTasksForProject,
    setSessionProjectDraft,
    setSessionTaskDraft,
    setSessionDateDraft,
    setSessionDurationDraft,
    syncSessionTaskDraftForProject,
    deleteInvoice,
    finalizeInvoice,
    markInvoicePaid,
    emailInvoiceToClient,
    saveInvoiceDiscount,
    addExpense,
    removeExpense,
    createManualSession,
    runInlinePrimaryAction,
    stopInlineTimer,
    resumeStoppedSession,
    submitResumedSession,
    removeSession,
    startEditingSessionDetails,
    cancelEditingSessionDetails,
    saveSessionDetails,
    startEditingSessionDuration,
    cancelEditingSessionDuration,
    updateSessionDuration,
} = useInvoicePageController({
    initialInvoice: props.invoice,
    initialAssignedSessions: props.assignedSessions,
    initialClientTasks: props.clientTasks,
    initialAvailableSessions: props.availableSessions,
    initialExpenses: props.expenses,
    initialSummary: props.summary,
    formatDuration,
    onInvoiceDeleted: () => router.visit(route('invoices.index')),
});

function formatCurrency(amount) {
    const value = Number(amount || 0);
    const currencyCode = String(invoice.value?.client?.currency || 'USD').toUpperCase();

    try {
        return new Intl.NumberFormat('en-US', {
            style: 'currency',
            currency: currencyCode,
            minimumFractionDigits: 2,
            maximumFractionDigits: 2,
        }).format(value);
    } catch (error) {
        return `${currencyCode} ${value.toFixed(2)}`;
    }
}

const sessionEntryState = computed(() => ({
    isFinalized: isFinalized.value,
    isInlineTimerLoading: isInlineTimerLoading.value,
    isInlineTimerRunning: isInlineTimerRunning.value,
    isInlineTimerPaused: isInlineTimerPaused.value,
    inlineElapsedSeconds: inlineElapsedSeconds.value,
    inlineActiveSessionId: inlineActiveSessionId.value,
    hasActiveClientTasks: hasActiveClientTasks.value,
    clientProjects: clientProjects.value,
    selectedInlineProjectId: selectedInlineProjectId.value,
    selectedInlineTaskId: selectedInlineTaskId.value,
    selectedManualProjectId: selectedManualProjectId.value,
    selectedManualTaskId: selectedManualTaskId.value,
    manualDurationMinutes: manualDurationMinutes.value,
    manualStartedAt: manualStartedAt.value,
    isSubmittingManualSession: isSubmittingManualSession.value,
}));

const sessionGroupsController = computed(() => ({
    isFinalized: isFinalized.value,
    isInlineTimerLoading: isInlineTimerLoading.value,
    inlineActiveSessionId: inlineActiveSessionId.value,
    clientProjects: clientProjects.value,
    displaySessionDuration,
    visibleSessionsForProject,
    hasMoreSessionsForProject,
    hiddenSessionsCountForProject,
    totalDurationSecondsForProject,
    isProjectSectionExpanded,
    toggleProjectSection,
    isEditingSessionDetails,
    isSavingSessionDetails,
    isSessionStopped,
    getSessionProjectDraft,
    getSessionTaskDraft,
    getSessionDateDraft,
    getSessionDurationDraft,
    sessionEditTasksForProject,
    isEditingSessionDuration,
    isSavingSessionDuration,
    isBusy,
    syncSessionTaskDraftForProject,
    startEditingSessionDetails,
    saveSessionDetails,
    cancelEditingSessionDetails,
    setSessionProjectDraft,
    setSessionTaskDraft,
    setSessionDateDraft,
    startEditingSessionDuration,
    updateSessionDuration,
    cancelEditingSessionDuration,
    setSessionDurationDraft,
    resumeStoppedSession,
    submitResumedSession,
    removeSession,
}));

const sessionFormatters = {
    formatDuration,
    formatSessionHeaderDate,
    formatTaskOption,
};
</script>

<template>
    <AppLayout title="Invoice Details">
        <Head :title="`Invoice ${formatInvoiceId(invoice.id)}`" />

        <div class="min-h-screen bg-gray-100 dark:bg-black px-4 py-10">
            <div class="mx-auto w-full max-w-5xl space-y-6">
                <div class="rounded-2xl bg-white dark:bg-gray-900 shadow-lg p-6 sm:p-8">
                    <InvoiceMetaHeader
                        :invoice="invoice"
                        :is-paid="isPaid"
                        :is-finalized="isFinalized"
                        :is-deleting-invoice="isDeletingInvoice"
                        :is-finalizing="isFinalizing"
                        :is-marking-paid="isMarkingPaid"
                        :is-sending-invoice-email="isSendingInvoiceEmail"
                        :status-message="statusMessage"
                        :format-invoice-id="formatInvoiceId"
                        :format-date-time="formatDateTime"
                        @delete-invoice="deleteInvoice"
                        @finalize-invoice="finalizeInvoice"
                        @mark-invoice-paid="markInvoicePaid"
                        @email-invoice-to-client="emailInvoiceToClient"
                    />

                    <InvoiceDiscountEditor
                        :discount-type="discountType"
                        :discount-value="discountValue"
                        :is-finalized="isFinalized"
                        :is-saving-discount="isSavingDiscount"
                        :discount-amount="summary.discount_amount || 0"
                        :format-currency="formatCurrency"
                        @update:discount-type="discountType = $event"
                        @update:discount-value="discountValue = $event"
                        @save="saveInvoiceDiscount"
                    />

                    <InvoiceSummaryCards
                        :summary="summary"
                        :format-duration="formatDuration"
                        :format-currency="formatCurrency"
                    />
                </div>

                <div class="rounded-2xl bg-white dark:bg-gray-900 shadow-lg p-6 sm:p-8">
                    <h2 class="text-xl font-semibold text-gray-900 dark:text-white">Sessions Assigned To Invoice</h2>

                    <p v-if="isFinalized" class="mt-3 text-sm text-gray-600 dark:text-gray-300">
                        This invoice is finalized and cannot be changed.
                    </p>

                    <InvoiceSessionEntryPanel
                        :state="sessionEntryState"
                        :format-duration="formatDuration"
                        :tasks-for-project="tasksForProject"
                        :format-task-option="formatTaskOption"
                        @update:selected-inline-project-id="selectedInlineProjectId = $event"
                        @update:selected-inline-task-id="selectedInlineTaskId = $event"
                        @update:selected-manual-project-id="selectedManualProjectId = $event"
                        @update:selected-manual-task-id="selectedManualTaskId = $event"
                        @update:manual-duration-minutes="manualDurationMinutes = $event"
                        @update:manual-started-at="manualStartedAt = $event"
                        @run-inline-primary-action="runInlinePrimaryAction"
                        @stop-inline-timer="stopInlineTimer"
                        @create-manual-session="createManualSession"
                    />

                    <p v-if="assignedSessions.length === 0" class="mt-3 text-sm text-gray-600 dark:text-gray-300">
                        No sessions assigned yet.
                    </p>

                    <InvoiceSessionGroupsList
                        v-else
                        :assigned-sessions-by-project="assignedSessionsByProject"
                        :controller="sessionGroupsController"
                        :formatters="sessionFormatters"
                    />
                </div>

                <InvoiceExpensesPanel
                    :is-finalized="isFinalized"
                    :is-submitting-expense="isSubmittingExpense"
                    :expenses="expenses"
                    :format-currency="formatCurrency"
                    :is-expense-busy="isExpenseBusy"
                    :expense-name="expenseName"
                    :expense-amount="expenseAmount"
                    :expense-description="expenseDescription"
                    @update:expense-name="expenseName = $event"
                    @update:expense-amount="expenseAmount = $event"
                    @update:expense-description="expenseDescription = $event"
                    @add-expense="addExpense"
                    @remove-expense="removeExpense"
                />

            </div>
        </div>
    </AppLayout>
</template>
