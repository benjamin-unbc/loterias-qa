<nav class="z-40 w-full flex items-center">
    <div class="relative w-full">
        <!-- Settings Dropdown -->
        <div class="px-3 relative w-full">
            <x-dropdown align="left" width="48">
                <x-slot name="trigger">
                    @if (Laravel\Jetstream\Jetstream::managesProfilePhotos())
                    <button
                        class="flex text-sm border-2 border-transparent rounded-full focus:outline-none focus:border-gray-300 transition">
                        <img class="h-8 w-8 rounded-full object-cover"
                            src="{{ Auth::user()->profile_photo_url }}"
                            alt="{{ Auth::user()->first_name }}" />
                    </button>
                    @else
                    <span class="inline-flex rounded-md w-full">
                        <button type="button"
                            class="flex justify-between w-full items-center px-3 py-2 border border-transparent text-sm leading-4 font-medium rounded-md text-gray-700 bg-yellow-200 hover:text-gray-900 focus:outline-none active:bg-yellow-300 transition ease-in-out duration-150">
                            <span class="flex gap-2 items-center">
                                <i class="fa-solid fa-user-tie"></i>
                            {{ Auth::user()->first_name . " " . Auth::user()->last_name }}
                            </span>
                            <svg class="ms-2 -me-0.5 h-4 w-4" xmlns="http://www.w3.org/2000/svg"
                                fill="none" viewBox="0 0 24 24" stroke-width="1.5"
                                stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round"
                                    d="M19.5 8.25l-7.5 7.5-7.5-7.5" />
                            </svg>
                        </button>
                    </span>
                    @endif
                </x-slot>
                <x-slot name="content">
                    <!-- Account Management -->
                    <div class="block px-4 py-2 text-xs text-gray-400 bg-[#2b3136]">
                        {{ __('Manage Account') }}
                    </div>
                    <x-dropdown-link href="{{ route('profile.show') }}">
                        {{ __('Profile') }}
                    </x-dropdown-link>
                    @if (Laravel\Jetstream\Jetstream::hasApiFeatures())
                    <x-dropdown-link href="{{ route('api-tokens.index') }}">
                        {{ __('API Tokens') }}
                    </x-dropdown-link>
                    @endif
                    <div class="border-t border-gray-200"></div>
                    <!-- Authentication -->
                    <form method="POST" action="{{ route('logout') }}" x-data>
                        @csrf
                        <x-dropdown-link href="{{ route('logout') }}" @click.prevent="$root.submit();">
                            {{ __('Log Out') }}
                        </x-dropdown-link>
                    </form>
                </x-slot>
            </x-dropdown>
        </div>
    </div>
</nav>
