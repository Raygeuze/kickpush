<script setup>
import { ref, watch } from 'vue';
import { router, useForm, usePage } from '@inertiajs/vue3';
import ActionMessage from '@/Components/ActionMessage.vue';
import ActionSection from '@/Components/ActionSection.vue';
import ConfirmationModal from '@/Components/ConfirmationModal.vue';
import DangerButton from '@/Components/DangerButton.vue';
import DialogModal from '@/Components/DialogModal.vue';
import FormSection from '@/Components/FormSection.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import SectionBorder from '@/Components/SectionBorder.vue';
import TextInput from '@/Components/TextInput.vue';

const props = defineProps({
    team: Object,
    availableRoles: Array,
    userPermissions: Object,
});

const page = usePage();

const addTeamMemberForm = useForm({
    email: '',
    role: null,
});

const updateRoleForm = useForm({
    role: null,
});

const leaveTeamForm = useForm({});
const removeTeamMemberForm = useForm({});
const transferOwnershipForm = useForm({});

const currentlyManagingRole = ref(false);
const managingRoleFor = ref(null);
const confirmingLeavingTeam = ref(false);
const teamMemberBeingRemoved = ref(null);
const teamMemberForOwnershipTransfer = ref(null);
const chargeOutRatesByUserId = ref({});
const chargeOutRateErrorByUserId = ref({});
const savingChargeOutRateForUserId = ref(null);

watch(
    () => props.team?.users,
    (users) => {
        const nextRates = {};

        (users || []).forEach((user) => {
            const rate = Number(user?.hourly_rate || 0);
            nextRates[user.id] = rate > 0 ? rate.toFixed(2) : '';
        });

        chargeOutRatesByUserId.value = nextRates;
    },
    { immediate: true }
);

const addTeamMember = () => {
    addTeamMemberForm.post(route('team-members.store', props.team), {
        errorBag: 'addTeamMember',
        preserveScroll: true,
        onSuccess: () => addTeamMemberForm.reset(),
    });
};

const cancelTeamInvitation = (invitation) => {
    router.delete(route('team-invitations.destroy', invitation), {
        preserveScroll: true,
    });
};

const manageRole = (teamMember) => {
    managingRoleFor.value = teamMember;
    updateRoleForm.role = teamMember.membership.role;
    currentlyManagingRole.value = true;
};

const updateRole = () => {
    updateRoleForm.put(route('team-members.update', [props.team, managingRoleFor.value]), {
        preserveScroll: true,
        onSuccess: () => currentlyManagingRole.value = false,
    });
};

const confirmLeavingTeam = () => {
    confirmingLeavingTeam.value = true;
};

const leaveTeam = () => {
    leaveTeamForm.delete(route('team-members.destroy', [props.team, page.props.auth.user]));
};

const confirmTeamMemberRemoval = (teamMember) => {
    teamMemberBeingRemoved.value = teamMember;
};

const removeTeamMember = () => {
    removeTeamMemberForm.delete(route('team-members.destroy', [props.team, teamMemberBeingRemoved.value]), {
        errorBag: 'removeTeamMember',
        preserveScroll: true,
        preserveState: true,
        onSuccess: () => teamMemberBeingRemoved.value = null,
    });
};

const confirmOwnershipTransfer = (teamMember) => {
    teamMemberForOwnershipTransfer.value = teamMember;
};

const transferOwnership = () => {
    if (!teamMemberForOwnershipTransfer.value) {
        return;
    }

    transferOwnershipForm.post(route('teams.transferOwnership', [props.team, teamMemberForOwnershipTransfer.value]), {
        preserveScroll: true,
        preserveState: true,
        onSuccess: () => {
            teamMemberForOwnershipTransfer.value = null;
        },
    });
};

const displayableRole = (role) => {
    return props.availableRoles.find(r => r.key === role).name;
};

const isTeamOwner = (teamMember) => {
    const ownerId = props.team?.owner?.id ?? props.team?.user_id;
    return Number(ownerId) === Number(teamMember?.id);
};

const currentTeamRole = () => {
    const currentUserId = Number(page.props.auth?.user?.id || 0);
    const currentTeamMember = (props.team?.users || []).find((member) => Number(member.id) === currentUserId);

    return String(currentTeamMember?.membership?.role || '');
};

const canEditChargeOutRate = (teamMember) => {
    if (!teamMember) {
        return false;
    }

    const currentUserId = Number(page.props.auth?.user?.id || 0);

    if (isTeamOwner(page.props.auth?.user)) {
        return true;
    }

    const role = currentTeamRole();

    if (role === 'admin' || role === 'editor') {
        return true;
    }

    if (role === 'employee') {
        return Number(teamMember.id) === currentUserId;
    }

    return false;
};

const chargeOutRateLabel = (teamMember) => {
    const rate = Number(teamMember?.hourly_rate || 0);

    if (rate <= 0) {
        return 'Client default';
    }

    return `$${rate.toFixed(2)}/hr`;
};

const updateChargeOutRate = async (teamMember) => {
    if (!canEditChargeOutRate(teamMember) || savingChargeOutRateForUserId.value !== null) {
        return;
    }

    const userId = Number(teamMember?.id || 0);

    if (userId <= 0) {
        return;
    }

    const rawValue = String(chargeOutRatesByUserId.value[userId] ?? '').trim();
    const numericValue = rawValue === '' ? 0 : Number(rawValue);

    if (!Number.isFinite(numericValue) || numericValue < 0) {
        chargeOutRateErrorByUserId.value = {
            ...chargeOutRateErrorByUserId.value,
            [userId]: 'Enter a valid rate of 0 or more.',
        };
        return;
    }

    chargeOutRateErrorByUserId.value = {
        ...chargeOutRateErrorByUserId.value,
        [userId]: '',
    };
    savingChargeOutRateForUserId.value = userId;

    try {
        const response = await axios.put(route('teams.members.chargeOutRate.update', [props.team, teamMember]), {
            hourly_rate: numericValue,
        });

        const updatedRate = Number(response?.data?.user?.hourly_rate || 0);
        teamMember.hourly_rate = updatedRate;
        chargeOutRatesByUserId.value[userId] = updatedRate > 0 ? updatedRate.toFixed(2) : '';
    } catch (error) {
        chargeOutRateErrorByUserId.value = {
            ...chargeOutRateErrorByUserId.value,
            [userId]: error?.response?.data?.message || 'Could not update rate.',
        };
    } finally {
        savingChargeOutRateForUserId.value = null;
    }
};
</script>

<template>
    <div>
        <div v-if="userPermissions.canAddTeamMembers">
            <SectionBorder />

            <!-- Add Team Member -->
            <FormSection @submitted="addTeamMember">
                <template #title>
                    Add Team Member
                </template>

                <template #description>
                    Add a new team member to your team, allowing them to collaborate with you.
                </template>

                <template #form>
                    <div class="col-span-6">
                        <div class="max-w-xl text-sm text-gray-600">
                            Please provide the email address of the person you would like to add to this team.
                        </div>
                    </div>

                    <!-- Member Email -->
                    <div class="col-span-6 sm:col-span-4">
                        <InputLabel for="email" value="Email" />
                        <TextInput
                            id="email"
                            v-model="addTeamMemberForm.email"
                            type="email"
                            class="mt-1 block w-full"
                        />
                        <InputError :message="addTeamMemberForm.errors.email" class="mt-2" />
                    </div>

                    <!-- Role -->
                    <div v-if="availableRoles.length > 0" class="col-span-6 lg:col-span-4">
                        <InputLabel for="roles" value="Role" />
                        <InputError :message="addTeamMemberForm.errors.role" class="mt-2" />

                        <div class="relative z-0 mt-1 border border-gray-200 rounded-lg cursor-pointer">
                            <button
                                v-for="(role, i) in availableRoles"
                                :key="role.key"
                                type="button"
                                class="relative px-4 py-3 inline-flex w-full rounded-lg focus:z-10 focus:outline-none focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500"
                                :class="{'border-t border-gray-200 focus:border-none rounded-t-none': i > 0, 'rounded-b-none': i != Object.keys(availableRoles).length - 1}"
                                @click="addTeamMemberForm.role = role.key"
                            >
                                <div :class="{'opacity-50': addTeamMemberForm.role && addTeamMemberForm.role != role.key}">
                                    <!-- Role Name -->
                                    <div class="flex items-center">
                                        <div class="text-sm text-gray-600" :class="{'font-semibold': addTeamMemberForm.role == role.key}">
                                            {{ role.name }}
                                        </div>

                                        <svg v-if="addTeamMemberForm.role == role.key" class="ms-2 size-5 text-green-400" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                        </svg>
                                    </div>

                                    <!-- Role Description -->
                                    <div class="mt-2 text-xs text-gray-600 text-start">
                                        {{ role.description }}
                                    </div>
                                </div>
                            </button>
                        </div>
                    </div>
                </template>

                <template #actions>
                    <ActionMessage :on="addTeamMemberForm.recentlySuccessful" class="me-3">
                        Added.
                    </ActionMessage>

                    <PrimaryButton :class="{ 'opacity-25': addTeamMemberForm.processing }" :disabled="addTeamMemberForm.processing">
                        Add
                    </PrimaryButton>
                </template>
            </FormSection>
        </div>

        <div v-if="team.team_invitations.length > 0 && userPermissions.canAddTeamMembers">
            <SectionBorder />

            <!-- Team Member Invitations -->
            <ActionSection class="mt-10 sm:mt-0">
                <template #title>
                    Pending Team Invitations
                </template>

                <template #description>
                    These people have been invited to your team and have been sent an invitation email. They may join the team by accepting the email invitation.
                </template>

                <!-- Pending Team Member Invitation List -->
                <template #content>
                    <div class="space-y-6">
                        <div v-for="invitation in team.team_invitations" :key="invitation.id" class="flex items-center justify-between">
                            <div class="text-gray-600">
                                {{ invitation.email }}
                            </div>

                            <div class="flex items-center">
                                <!-- Cancel Team Invitation -->
                                <button
                                    v-if="userPermissions.canRemoveTeamMembers"
                                    class="cursor-pointer ms-6 text-sm text-red-500 focus:outline-none"
                                    @click="cancelTeamInvitation(invitation)"
                                >
                                    Cancel
                                </button>
                            </div>
                        </div>
                    </div>
                </template>
            </ActionSection>
        </div>

        <div v-if="team.users.length > 0">
            <SectionBorder />

            <!-- Manage Team Members -->
            <ActionSection class="mt-10 sm:mt-0">
                <template #title>
                    Team Members
                </template>

                <template #description>
                    All of the people that are part of this team.
                    If a user's hourly rate is not set, invoice calculations default to the hourly rate configured on the client.
                </template>

                <!-- Team Member List -->
                <template #content>
                    <div class="space-y-4">
                        <div
                            v-for="user in team.users"
                            :key="user.id"
                            class="rounded-xl border border-gray-200 bg-white p-4 dark:border-gray-700 dark:bg-gray-900/40"
                        >
                            <div class="flex flex-col gap-3 lg:flex-row lg:items-start lg:justify-between">
                                <div class="flex items-center">
                                    <img class="size-9 rounded-full object-cover" :src="user.profile_photo_url" :alt="user.name">
                                    <div class="ms-3">
                                        <p class="text-sm font-semibold text-gray-900 dark:text-white">{{ user.name }}</p>

                                        <div class="mt-1 flex items-center gap-2">
                                            <button
                                                v-if="userPermissions.canUpdateTeamMembers && availableRoles.length"
                                                class="text-xs text-gray-500 underline dark:text-gray-300"
                                                @click="manageRole(user)"
                                            >
                                                {{ displayableRole(user.membership.role) }}
                                            </button>

                                            <div v-else-if="availableRoles.length" class="text-xs text-gray-500 dark:text-gray-300">
                                                {{ displayableRole(user.membership.role) }}
                                            </div>

                                            <div v-if="isTeamOwner(user)" class="rounded-full bg-emerald-100 px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wide text-emerald-700 dark:bg-emerald-900/40 dark:text-emerald-300">
                                                Owner
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div class="flex flex-col gap-3 lg:items-end">
                                    <div class="rounded-lg border border-gray-200 bg-gray-50 px-3 py-2 dark:border-gray-700 dark:bg-gray-800/70">
                                        <p class="text-[11px] font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-300">Charge-out rate</p>
                                        <p class="text-sm font-semibold text-gray-900 dark:text-white">{{ chargeOutRateLabel(user) }}</p>

                                        <div v-if="canEditChargeOutRate(user)" class="mt-2 flex flex-wrap items-center gap-2">
                                            <TextInput
                                                v-model="chargeOutRatesByUserId[user.id]"
                                                type="number"
                                                step="0.01"
                                                min="0"
                                                class="w-32"
                                                placeholder="Client default"
                                            />
                                            <button
                                                type="button"
                                                class="rounded-md bg-indigo-600 px-2.5 py-1.5 text-xs font-semibold text-white hover:bg-indigo-700 disabled:opacity-60"
                                                :disabled="savingChargeOutRateForUserId === user.id"
                                                @click="updateChargeOutRate(user)"
                                            >
                                                {{ savingChargeOutRateForUserId === user.id ? 'Saving...' : 'Save rate' }}
                                            </button>
                                        </div>

                                        <p v-if="chargeOutRateErrorByUserId[user.id]" class="mt-1 text-xs text-red-600 dark:text-red-300">
                                            {{ chargeOutRateErrorByUserId[user.id] }}
                                        </p>
                                    </div>

                                    <div class="flex flex-wrap items-center gap-3 text-sm">
                                        <button
                                            v-if="userPermissions.canUpdateTeamMembers && $page.props.auth.user.current_team?.can_transfer_ownership && !isTeamOwner(user)"
                                            class="cursor-pointer text-indigo-600"
                                            @click="confirmOwnershipTransfer(user)"
                                        >
                                            Make owner
                                        </button>

                                        <button
                                            v-if="$page.props.auth.user.id === user.id"
                                            class="cursor-pointer text-red-500"
                                            @click="confirmLeavingTeam"
                                        >
                                            Leave
                                        </button>

                                        <button
                                            v-else-if="userPermissions.canRemoveTeamMembers && !isTeamOwner(user)"
                                            class="cursor-pointer text-red-500"
                                            @click="confirmTeamMemberRemoval(user)"
                                        >
                                            Remove
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </template>
            </ActionSection>
        </div>

        <!-- Role Management Modal -->
        <DialogModal :show="currentlyManagingRole" @close="currentlyManagingRole = false">
            <template #title>
                Manage Role
            </template>

            <template #content>
                <div v-if="managingRoleFor">
                    <div class="relative z-0 mt-1 border border-gray-200 rounded-lg cursor-pointer">
                        <button
                            v-for="(role, i) in availableRoles"
                            :key="role.key"
                            type="button"
                            class="relative px-4 py-3 inline-flex w-full rounded-lg focus:z-10 focus:outline-none focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500"
                            :class="{'border-t border-gray-200 focus:border-none rounded-t-none': i > 0, 'rounded-b-none': i !== Object.keys(availableRoles).length - 1}"
                            @click="updateRoleForm.role = role.key"
                        >
                            <div :class="{'opacity-50': updateRoleForm.role && updateRoleForm.role !== role.key}">
                                <!-- Role Name -->
                                <div class="flex items-center">
                                    <div class="text-sm text-gray-600" :class="{'font-semibold': updateRoleForm.role === role.key}">
                                        {{ role.name }}
                                    </div>

                                    <svg v-if="updateRoleForm.role == role.key" class="ms-2 size-5 text-green-400" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                    </svg>
                                </div>

                                <!-- Role Description -->
                                <div class="mt-2 text-xs text-gray-600">
                                    {{ role.description }}
                                </div>
                            </div>
                        </button>
                    </div>
                </div>
            </template>

            <template #footer>
                <SecondaryButton @click="currentlyManagingRole = false">
                    Cancel
                </SecondaryButton>

                <PrimaryButton
                    class="ms-3"
                    :class="{ 'opacity-25': updateRoleForm.processing }"
                    :disabled="updateRoleForm.processing"
                    @click="updateRole"
                >
                    Save
                </PrimaryButton>
            </template>
        </DialogModal>

        <!-- Leave Team Confirmation Modal -->
        <ConfirmationModal :show="confirmingLeavingTeam" @close="confirmingLeavingTeam = false">
            <template #title>
                Leave Team
            </template>

            <template #content>
                Are you sure you would like to leave this team?
            </template>

            <template #footer>
                <SecondaryButton @click="confirmingLeavingTeam = false">
                    Cancel
                </SecondaryButton>

                <DangerButton
                    class="ms-3"
                    :class="{ 'opacity-25': leaveTeamForm.processing }"
                    :disabled="leaveTeamForm.processing"
                    @click="leaveTeam"
                >
                    Leave
                </DangerButton>
            </template>
        </ConfirmationModal>

        <ConfirmationModal :show="teamMemberForOwnershipTransfer" @close="teamMemberForOwnershipTransfer = null">
            <template #title>
                Transfer Team Ownership
            </template>

            <template #content>
                The selected member will become the only person who can invite and add new users to this team. Continue?
            </template>

            <template #footer>
                <SecondaryButton @click="teamMemberForOwnershipTransfer = null">
                    Cancel
                </SecondaryButton>

                <DangerButton
                    class="ms-3"
                    :class="{ 'opacity-25': transferOwnershipForm.processing }"
                    :disabled="transferOwnershipForm.processing"
                    @click="transferOwnership"
                >
                    Transfer Ownership
                </DangerButton>
            </template>
        </ConfirmationModal>

        <!-- Remove Team Member Confirmation Modal -->
        <ConfirmationModal :show="teamMemberBeingRemoved" @close="teamMemberBeingRemoved = null">
            <template #title>
                Remove Team Member
            </template>

            <template #content>
                Are you sure you would like to remove this person from the team?
            </template>

            <template #footer>
                <SecondaryButton @click="teamMemberBeingRemoved = null">
                    Cancel
                </SecondaryButton>

                <DangerButton
                    class="ms-3"
                    :class="{ 'opacity-25': removeTeamMemberForm.processing }"
                    :disabled="removeTeamMemberForm.processing"
                    @click="removeTeamMember"
                >
                    Remove
                </DangerButton>
            </template>
        </ConfirmationModal>
    </div>
</template>
