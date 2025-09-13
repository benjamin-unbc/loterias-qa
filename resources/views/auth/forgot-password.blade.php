<x-guest-layout>
    <x-authentication-card>
        <x-slot name="logo">
            <x-authentication-card-logo />
        </x-slot>

        <div class="mb-4 text-sm text-white">
            {{ __('Forgot your password? No problem. Just let us know your email address and we will email you a password reset link that will allow you to choose a new one.') }}
        </div>

        @if (session('status'))
            <div class="mb-4 font-medium text-sm text-white">
                {{ session('status') }}
            </div>
        @endif

        <x-validation-errors class="mb-4" />

        <form method="POST" action="{{ route('password.email') }}">
            @csrf

            <div class="block">
                <x-label for="email" value="{{ __('Email') }}" />
                <x-input id="email" class="block mt-1 w-full" type="email" name="email" :value="old('email')" required autofocus autocomplete="username" />
            </div>

            <div class="flex items-center justify-between mt-4">
                <a href="{{ route('login') }}" class="w-9 h-9 flex items-center justify-center border border-gray-600 shadow-lg hover:bg-gray-600/20 duration-200 transition-all ease-in-out text-white hover:text-gray-300 rounded-lg px-2 py-1"><i class="fa-solid fa-arrow-left"></i></a>
                <x-button>
                    Restablecer contraseña
                </x-button>
            </div>
        </form>
    </x-authentication-card>
</x-guest-layout>
