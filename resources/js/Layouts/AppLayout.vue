<script setup>
import { ref } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import ApplicationMark from '@/Components/ApplicationMark.vue';
import Banner from '@/Components/Banner.vue';
import Dropdown from '@/Components/Dropdown.vue';
import DropdownLink from '@/Components/DropdownLink.vue';
import NavLink from '@/Components/NavLink.vue';
import ResponsiveNavLink from '@/Components/ResponsiveNavLink.vue';
import SearchBar from '@/kickpushComponents/SearchBar.vue';
import LoginModal from '@/kickpushComponents/LoginModal.vue';
import RegisterModal from '@/kickpushComponents/RegisterModal.vue';
import CountdownTimer from '@/kickpushComponents/CountdownTimer.vue';


defineProps({
    title: String,
});

const showingNavigationDropdown = ref(false);
const showLoginModal = ref(false);
const showRegisterModal = ref(false);

const logout = () => {
    router.post(route('logout'));
};

const toggleLoginModal = () => {
    showLoginModal.value = !showLoginModal.value;
    showRegisterModal.value = false;
};

const toggleRegisterModal = () => {
    showRegisterModal.value = !showRegisterModal.value; 
    showLoginModal.value = false;
};
</script>

<template>
    <div>
        <Head :title="title" />

        <Banner />

        <div class="min-h-screen bg-gray-100">
            <nav class="bg-white border-b border-gray-100">
                <!-- Primary Navigation Menu -->
                <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 ">
                    <div class="flex h-16 items-center justify-between">
                        <!-- Logo -->
                        <div class="shrink-0 flex items-center mr-4">
                            <Link :href="route('dashboard')">
                                <ApplicationMark class="block h-9 w-auto" />
                            </Link>
                        </div>
                        <!-- SearchBar Centered -->
                        <div class="flex-1 flex justify-center">
                            <SearchBar />
                        </div>
                        <!-- Nav Links and Dropdown Right -->
                        <div class="flex items-center gap-6">
                            <div class="hidden justify-between sm:-my-px sm:ms-10 sm:flex gap-6">
                                <NavLink :href="route('about')" :active="route().current('about')">
                                    The Heck?
                                </NavLink>

                                <NavLink :href="route('days.index')" :active="route().current('days.index')"
                                    class="">
                                    The Archive
                                </NavLink>

                                <button v-if="!$page.props.auth.user" @click="toggleLoginModal" class="text-gray-700">Log in</button>
                                <button v-if="!$page.props.auth.user" @click="toggleRegisterModal" class="text-gray-700">Register</button>

                                <CountdownTimer class="h-fit my-auto"/>
                            </div>
                            <div class="hidden sm:flex sm:items-center sm:ms-6">
                                <div v-if="$page.props.auth.user" class="ms-3 relative">
                                    <Dropdown align="right" width="48">
                                        <template #trigger>
                                            <div class="flex items-center">
                                                <button v-if="$page.props.jetstream.managesProfilePhotos" class="flex text-sm border-2 border-transparent rounded-full focus:outline-none focus:border-gray-300 transition" :class="$page.props.auth.user.disabled ? 'border-1 border-red-500' : ''">
                                                    <img class="size-8 rounded-full object-cover" :src="$page.props.auth.user.profile_photo_url" :alt="$page.props.auth.user.name">
                                                </button>

                                                <span class="inline-flex rounded-md">
                                                    <button type="button" class="inline-flex items-center px-3 py-2 border border-transparent text-sm leading-4 font-medium rounded-md text-gray-500 bg-white hover:text-gray-700 focus:outline-none focus:bg-gray-50 active:bg-gray-50 transition ease-in-out duration-150">
                                                        <span v-if="$page.props.auth.user.disabled" class="text-red-500">{{ $page.props.auth.user.name }}</span>
                                                        <span v-else>{{ $page.props.auth.user.name }}</span>

                                                        <svg class="ms-2 -me-0.5 size-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                                            <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 8.25l-7.5 7.5-7.5-7.5" />
                                                        </svg>
                                                    </button>
                                                </span>
                                            </div>
                                        </template>

                                        <template #content>
                                            <div v-if="$page.props.auth.user.is_admin" class="">
                                                <!-- kickpush Management -->
                                                <div class="block px-4 py-2 text-xs text-gray-400">
                                                    Admin tings
                                                </div>

                                                <DropdownLink :href="route('submissions.indexUnapproved')" >
                                                    <span>Manage Submissions</span>
                                                </DropdownLink>

                                                <DropdownLink :href="route('topics.index')" >
                                                    <span>Manage Topics</span>
                                                </DropdownLink>

                                                <DropdownLink :href="route('behaviourReports.index')" >
                                                    <span>Manage Behaviour Reports</span>
                                                </DropdownLink>
                                            </div>

                                            <!-- Account Management -->
                                            <div class="block px-4 py-2 text-xs text-gray-400">
                                                Manage Account
                                            </div>

                                            <DropdownLink :href="route('profile.show')" >
                                                <span v-if="$page.props.auth.user.disabled" class="text-red-500">Profile</span>
                                                <span v-else>Profile</span>
                                            </DropdownLink>

                                            <DropdownLink :href="route('profile.payments')" >
                                                <span v-if="$page.props.auth.user.disabled" class="text-red-500">Payments</span>
                                                <span v-else>
                                                    <span v-if="$page.props.auth.user.winningDaysUnpaid?.length > 0" class="inline-flex items-center rounded-full font-medium text-green-500">
                                                        Payments ({{ $page.props.auth.user.winningDaysUnpaid.length }})
                                                    </span>
                                                </span>
                                            </DropdownLink>

                                            <DropdownLink :href="route('profile.paymentsDetails')" >
                                                <span v-if="$page.props.auth.user.disabled" class="text-red-500">Payment Details</span>
                                                <span v-if="$page.props.auth.user.winningDaysUnpaid?.length > 0 && !$page.props.auth.user.can_accept_payouts" class="inline-flex items-center rounded-full text-xs font-medium text-red-500">
                                                    Payment Details (1)
                                                </span>
                                                <span v-else>
                                                    Payment Details
                                                </span>
                                            </DropdownLink>

                                            <div class="border-t border-gray-200" />

                                            <!-- Authentication -->
                                            <form @submit.prevent="logout">
                                                <DropdownLink as="button">
                                                    Log Out
                                                </DropdownLink>
                                            </form>
                                        </template>
                                    </Dropdown>
                                </div>
                            </div>
                        </div>

                        <!-- <div class="hidden sm:flex sm:items-center sm:ms-6">

                            <div v-if="$page.props.auth.user" class="ms-3 relative">
                                <Dropdown align="right" width="48">
                                    <template #trigger>
                                        <div class="flex items-center">
                                            <button v-if="$page.props.jetstream.managesProfilePhotos" class="flex text-sm border-2 border-transparent rounded-full focus:outline-none focus:border-gray-300 transition" :class="$page.props.auth.user.disabled ? 'border-1 border-red-500' : ''">
                                                <img class="size-8 rounded-full object-cover" :src="$page.props.auth.user.profile_photo_url" :alt="$page.props.auth.user.name">
                                            </button>

                                            <span class="inline-flex rounded-md">
                                                <button type="button" class="inline-flex items-center px-3 py-2 border border-transparent text-sm leading-4 font-medium rounded-md text-gray-500 bg-white hover:text-gray-700 focus:outline-none focus:bg-gray-50 active:bg-gray-50 transition ease-in-out duration-150">
                                                    <span v-if="$page.props.auth.user.disabled" class="text-red-500">{{ $page.props.auth.user.name }}</span>
                                                    <span v-else>{{ $page.props.auth.user.name }}</span>

                                                    <svg class="ms-2 -me-0.5 size-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                                        <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 8.25l-7.5 7.5-7.5-7.5" />
                                                    </svg>
                                                </button>
                                            </span>
                                        </div>
                                    </template>

                                    <template #content>
                                        <div v-if="$page.props.auth.user.is_admin" class="">
                                            <div class="block px-4 py-2 text-xs text-gray-400">
                                                Admin tings
                                            </div>

                                            <DropdownLink :href="route('submissions.indexUnapproved')" >
                                                <span>Manage Submissions</span>
                                            </DropdownLink>

                                            <DropdownLink :href="route('topics.index')" >
                                                <span>Manage Topics</span>
                                            </DropdownLink>

                                            <DropdownLink :href="route('behaviourReports.index')" >
                                                <span>Manage Behaviour Reports</span>
                                            </DropdownLink>
                                        </div>

                                        <div class="block px-4 py-2 text-xs text-gray-400">
                                            Manage Account
                                        </div>

                                        <DropdownLink :href="route('profile.show')" >
                                            <span v-if="$page.props.auth.user.disabled" class="text-red-500">Profile</span>
                                            <span v-else>Profile</span>
                                        </DropdownLink>

                                        <DropdownLink :href="route('profile.payments')" >
                                            <span v-if="$page.props.auth.user.disabled" class="text-red-500">Payments</span>
                                            <span v-else>
                                                Payments
                                                <span v-if="$page.props.auth.user.winningDaysUnpaid?.length > 0" class="ms-1 inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-red-100 text-green-500">
                                                    ( {{ $page.props.auth.user.winningDaysUnpaid.length }} )
                                                </span>
                                            </span>
                                        </DropdownLink>

                                        <DropdownLink :href="route('profile.paymentsDetails')" >
                                            <span v-if="$page.props.auth.user.disabled" class="text-red-500">Payment Details</span>
                                            <span v-if="$page.props.auth.user.winningDaysUnpaid?.length > 0 && !$page.props.auth.user.can_accept_payouts" class="ms-1 inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-500">
                                                Payment Details (1)
                                            </span>
                                            <span v-else>
                                                Payment Details
                                            </span>
                                        </DropdownLink>

                                        <div class="border-t border-gray-200" />

                                        <form @submit.prevent="logout">
                                            <DropdownLink as="button">
                                                Log Out
                                            </DropdownLink>
                                        </form>
                                    </template>
                                </Dropdown>
                            </div>
                        </div> -->

                        <div class="flex ml-auto lg:hidden ">
                            <CountdownTimer class="h-fit my-auto"/>
                        </div>

                        <!-- Hamburger -->
                        <div class="-me-2 flex items-center sm:hidden">
                            <button class="inline-flex items-center justify-center p-2 rounded-md text-gray-400 hover:text-gray-500 hover:bg-gray-100 focus:outline-none focus:bg-gray-100 focus:text-gray-500 transition duration-150 ease-in-out" @click="showingNavigationDropdown = ! showingNavigationDropdown">
                                <svg
                                    class="size-6"
                                    stroke="currentColor"
                                    fill="none"
                                    viewBox="0 0 24 24"
                                >
                                    <path
                                        :class="{'hidden': showingNavigationDropdown, 'inline-flex': ! showingNavigationDropdown }"
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                        stroke-width="2"
                                        d="M4 6h16M4 12h16M4 18h16"
                                    />
                                    <path
                                        :class="{'hidden': ! showingNavigationDropdown, 'inline-flex': showingNavigationDropdown }"
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                        stroke-width="2"
                                        d="M6 18L18 6M6 6l12 12"
                                    />
                                </svg>
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Responsive Navigation Menu -->
                <div :class="{'block': showingNavigationDropdown, 'hidden': ! showingNavigationDropdown}" class="sm:hidden">
                    <div class="pt-2 pb-3 space-y-1">
                        <ResponsiveNavLink :href="route('dashboard')" :active="route().current('dashboard')">
                            Dashboard
                        </ResponsiveNavLink>

                        <div class="flex lg:justify-left">
                            <SearchBar />
                        </div>

                        <ResponsiveNavLink :href="route('days.index')" :active="route().current('days.index')">
                            The Archive
                        </ResponsiveNavLink>

                        <button v-if="!$page.props.auth.user" @click="toggleLoginModal" class="w-full text-left font-medium py-2 block px-4 text-gray-700">Log in</button>
                        <button v-if="!$page.props.auth.user" @click="toggleRegisterModal" class="w-full text-left font-medium py-2 block px-4 text-gray-700">Register</button>
                    </div>

                    <!-- Responsive Settings Options -->
                    <div v-if="$page.props.auth.user" class="pt-4 pb-1 border-t border-gray-200">
                        <div class="flex items-center px-4">
                            <div v-if="$page.props.jetstream.managesProfilePhotos" class="shrink-0 me-3">
                                <img class="size-10 rounded-full object-cover" :src="$page.props.auth.user.profile_photo_url" :alt="$page.props.auth.user.name">
                            </div>

                            <div>
                                <div class="font-medium text-base text-gray-800">
                                    {{ $page.props.auth.user.name }}
                                </div>
                                <div class="font-medium text-sm text-gray-500">
                                    {{ $page.props.auth.user.email }}
                                </div>
                            </div>
                        </div>

                        <div v-if="$page.props.auth.user" class="mt-3 space-y-1">
                            <ResponsiveNavLink :href="route('profile.show')" :active="route().current('profile.show')">
                                Profile
                            </ResponsiveNavLink>

                            <ResponsiveNavLink :href="route('profile.payments')" :active="route().current('profile.payments')">
                                <span v-if="$page.props.auth.user.disabled" class="text-red-500">Payments</span>
                                <span v-else>
                                    <span v-if="$page.props.auth.user.winningDaysUnpaid?.length > 0" class="inline-flex items-center rounded-full font-medium text-green-500">
                                        Payments ({{ $page.props.auth.user.winningDaysUnpaid.length }})
                                    </span>
                                </span>
                            </ResponsiveNavLink>

                            <ResponsiveNavLink :href="route('profile.paymentsDetails')" :active="route().current('profile.paymentsDetails')">
                                <span v-if="$page.props.auth.user.disabled" class="text-red-500">Payment Details</span>
                                <span v-if="$page.props.auth.user.winningDaysUnpaid?.length > 0 && !$page.props.auth.user.can_accept_payouts" class="ms-1 inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-500">
                                    Payment Details (1)
                                </span>
                                <span v-else>
                                    Payment Details
                                </span>
                            </ResponsiveNavLink>

                            <!-- Authentication -->
                            <form method="POST" @submit.prevent="logout">
                                <ResponsiveNavLink as="button">
                                    Log Out
                                </ResponsiveNavLink>
                            </form>
                        </div>
                    </div>
                </div>
            </nav>
            <!-- <header v-else class="z-10 lg:grid items-center gap-2 py-6 lg:grid-cols-3 lg:px-32 h-fit top-0 bg-white">
                
                <div class="lg:flex lg:justify-left hidden">
                    <Link :href="route('dashboard')" class="text-black text-2xl">kickpush!</Link>
                </div>

                <div class="flex lg:justify-center lg:col-start-2">
                    <SearchBar />
                </div>

                <nav class="flex justify-end lg:gap-6">
                    
                    <Link :href="route('days.index')" 
                        class="hidden lg:flex rounded-md px-3 py-2">
                        The Archive
                    </Link>

                    <button
                        @click="toggleLoginModal"
                        class="rounded-md px-3 py-2 text-black ring-1 ring-transparent transition hover:text-black/70 focus:outline-none focus-visible:ring-[#FF2D20] dark:text-white dark:hover:text-white/80 dark:focus-visible:ring-white"
                    >
                        Log in
                    </button>

                    <button
                        @click="toggleRegisterModal"
                        class="hidden lg:flex rounded-md px-3 py-2 text-black ring-1 ring-transparent transition hover:text-black/70 focus:outline-none focus-visible:ring-[#FF2D20] dark:text-white dark:hover:text-white/80 dark:focus-visible:ring-white"
                    >
                        Register
                    </button>

                    <CountdownTimer class=""/>
                </nav>
            </header> -->

            <!-- Page Heading -->
            <header v-if="$slots.header" class="bg-white shadow">
                <div class="max-w-7xl mx-auto py-6 px-4 sm:px-6 lg:px-8">
                    <slot name="header" />
                </div>
            </header>

            <!-- Page Content -->
            <main class="min-h-screen">
                <slot @toggleLoginModal="toggleLoginModal" @toggleRegisterModal="toggleRegisterModal" />
            </main>

            
            <footer class="py-16 text-center text-sm bg-white text-black dark:text-white/70">
                <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 space-x-4 space-y-4">
                    <Link :href="route('about')" class="underline hover:text-black dark:hover:text-white">The heck is kickpush?</Link>

                    <Link :href="route('terms')" class="underline hover:text-black dark:hover:text-white">Terms and Conditions</Link>

                    <Link :href="route('privacy')" class="underline hover:text-black dark:hover:text-white">Privacy Policy</Link>

                    <Link :href="route('contact')" class="underline hover:text-black dark:hover:text-white">Contact Us</Link>
                    
                    <p>
                        &copy; {{ new Date().getFullYear() }} kickpush. All rights reserved.
                    </p>
                </div>
            </footer>
        </div>
    </div>


    <!-- LOGIN AND REGISTRATION MODALS -->
    <LoginModal 
        :showModal="showLoginModal" 
        @close="toggleLoginModal"
        @showRegisterModal="toggleRegisterModal" />

    <RegisterModal 
        :showModal="showRegisterModal" 
        @close="toggleRegisterModal"
        @showLoginModal="toggleLoginModal" />
</template>
