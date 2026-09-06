<script setup>
import { useForm } from '@inertiajs/vue3';
import ActionMessage from '@/Components/ActionMessage.vue';
import FormSection from '@/Components/FormSection.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';

const props = defineProps({
    team: Object,
    permissions: Object,
});

const form = useForm({
    name: props.team.name,
    timezone: props.team.timezone || 'UTC',
});

const timezoneOptions = [
    'UTC',
    props.team.timezone,
    ...(typeof Intl.supportedValuesOf === 'function' ? Intl.supportedValuesOf('timeZone') : []),
].filter((timezone, index, options) => timezone && options.indexOf(timezone) === index);

const updateTeamName = () => {
    form.put(route('teams.update', props.team), {
        errorBag: 'updateTeamName',
        preserveScroll: true,
    });
};
</script>

<template>
    <FormSection @submitted="updateTeamName">
        <template #title>
            Team Details
        </template>

        <template #description>
            The team's identity and reporting timezone.
        </template>

        <template #form>
            <!-- Team Owner Information -->
            <div class="col-span-6">
                <InputLabel value="Team Owner" />

                <div class="flex items-center mt-2">
                    <img class="size-12 rounded-full object-cover" :src="team.owner.profile_photo_url" :alt="team.owner.name">

                    <div class="ms-4 leading-tight">
                        <div class="text-gray-900">{{ team.owner.name }}</div>
                        <div class="text-gray-700 text-sm">
                            {{ team.owner.email }}
                        </div>
                    </div>
                </div>
            </div>

            <!-- Team Name -->
            <div class="col-span-6 sm:col-span-4">
                <InputLabel for="name" value="Team Name" />

                <TextInput
                    id="name"
                    v-model="form.name"
                    type="text"
                    class="mt-1 block w-full"
                    :disabled="! permissions.canUpdateTeam"
                />

                <InputError :message="form.errors.name" class="mt-2" />
            </div>

            <div class="col-span-6 sm:col-span-4">
                <InputLabel for="timezone" value="Timezone" />

                <select
                    id="timezone"
                    v-model="form.timezone"
                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 disabled:opacity-60"
                    :disabled="! permissions.canUpdateTeam"
                >
                    <option v-for="timezone in timezoneOptions" :key="timezone" :value="timezone">
                        {{ timezone }}
                    </option>
                </select>

                <InputError :message="form.errors.timezone" class="mt-2" />
            </div>
        </template>

        <template v-if="permissions.canUpdateTeam" #actions>
            <ActionMessage :on="form.recentlySuccessful" class="me-3">
                Saved.
            </ActionMessage>

            <PrimaryButton :class="{ 'opacity-25': form.processing }" :disabled="form.processing">
                Save
            </PrimaryButton>
        </template>
    </FormSection>
</template>
