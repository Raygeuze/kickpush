<script setup>
import { computed } from 'vue';
import { Head, router, usePage } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import InvoiceDiscountEditor from '@/Pages/Invoices/Partials/InvoiceDiscountEditor.vue';
import InvoiceSummaryCards from '@/Pages/Invoices/Partials/InvoiceSummaryCards.vue';
import InvoiceExpensesPanel from '@/Pages/Invoices/Partials/InvoiceExpensesPanel.vue';
import InvoiceMetaHeader from '@/Pages/Invoices/Partials/InvoiceMetaHeader.vue';
import InvoiceSessionGroupsList from '@/Pages/Invoices/Partials/InvoiceSessionGroupsList.vue';
import StartTimerModal from '@/Pages/Timesheets/Partials/StartTimerModal.vue';
import { useInvoicePageController } from '@/Pages/Invoices/composables/useInvoicePageController';

const page = usePage();
const canManageNonTimerRecords = computed(() => page.props.auth?.user?.current_team?.can_manage_non_timer_records !== false);
const canDeleteAnyTimerSession = computed(() => page.props.auth?.user?.current_team?.can_manage_team_sessions === true);

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
    lineItems: {
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
    lineItems,
    summary,
    statusMessage,
    isFinalizing,
    isMarkingPaid,
    isSendingInvoiceEmail,
    isDeletingInvoice,
    isSavingDiscount,
    isSubmittingLineItem,
    isInlineTimerLoading,
    discountType,
    discountValue,
    invoiceStartModalOpen,
    invoiceStartForm,
    invoiceProjectTasks,
    startingInvoiceTimer,
    invoiceStartFormError,
    openInvoiceStartTimer,
    cancelInvoiceStartTimer,
    startInvoiceTimer,
    inlineActiveSessionId,
    isInlineTimerActive,
    lineItemName,
    lineItemDescription,
    lineItemAmount,
    isFinalized,
    isPaid,
    hasActiveClientTasks,
    clientProjects,
    assignedSessionsByProject,
    displaySessionDuration,
    visibleSessionsForProject,
    hasMoreSessionsForProject,
    hiddenSessionsCountForProject,
    totalDurationSecondsForProject,
    isProjectSectionExpanded,
    toggleProjectSection,
    isBusy,
    isLineItemBusy,
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
    addLineItem,
    removeLineItem,
    resumeStoppedSession,
    submitResumedSession,
    canManageSession,
    canOperateSession,
    canDeleteSession,
    deleteSession,
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
    initialLineItems: props.lineItems,
    initialSummary: props.summary,
    currentUserId: page.props.auth?.user?.id,
    canDeleteAnyTimerSession: canDeleteAnyTimerSession.value,
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

const invoiceStartModalState = computed(() => ({
    invoiceStartForm,
    invoiceProjectTasks: invoiceProjectTasks.value,
    startingInvoiceTimer: startingInvoiceTimer.value,
    invoiceStartFormError: invoiceStartFormError.value,
    clientProjects: clientProjects.value,
    isInlineTimerActive: isInlineTimerActive.value,
    cancelInvoiceStartTimer,
    startInvoiceTimer,
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
    canManageSession,
    canOperateSession,
    canDeleteSession,
    deleteSession,
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
                        :can-manage-non-timer-records="canManageNonTimerRecords"
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
                        :can-manage-non-timer-records="canManageNonTimerRecords"
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

                    <div class="mt-4 flex flex-col gap-3 rounded-xl border border-gray-200 p-3 dark:border-gray-700 sm:flex-row sm:items-center sm:justify-between">
                        <p class="text-sm text-gray-600 dark:text-gray-300">
                            {{ hasActiveClientTasks ? 'Start a timer or record time directly on this invoice.' : 'Create an active task for this client before adding sessions.' }}
                        </p>
                        <button
                            type="button"
                            class="inline-flex items-center gap-2 rounded-lg bg-emerald-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-emerald-700 disabled:opacity-60"
                            :disabled="isFinalized || !hasActiveClientTasks"
                            @click="openInvoiceStartTimer"
                        >
                            <svg viewBox="0 0 24 24" class="h-4 w-4" fill="currentColor" aria-hidden="true"><path d="M8 5v14l11-7z" /></svg>
                            Start timer
                        </button>
                    </div>

                    <StartTimerModal
                        :show="invoiceStartModalOpen"
                        :state="invoiceStartModalState"
                        source="invoice"
                        show-project
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
                    :can-manage-non-timer-records="canManageNonTimerRecords"
                    :is-finalized="isFinalized"
                    :is-submitting-line-item="isSubmittingLineItem"
                    :status-message="statusMessage"
                    :line-items="lineItems"
                    :format-currency="formatCurrency"
                    :is-line-item-busy="isLineItemBusy"
                    :line-item-name="lineItemName"
                    :line-item-amount="lineItemAmount"
                    :line-item-description="lineItemDescription"
                    :line-item-currency="invoice?.client?.currency || 'USD'"
                    @update:line-item-name="lineItemName = $event"
                    @update:line-item-amount="lineItemAmount = $event"
                    @update:line-item-description="lineItemDescription = $event"
                    @add-line-item="addLineItem"
                    @remove-line-item="removeLineItem"
                />

            </div>
        </div>
    </AppLayout>
</template>
