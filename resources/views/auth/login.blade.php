<x-guest-layout>
    <x-authentication-card>

        <x-slot name="logo">
            <x-authentication-card-logo />
        </x-slot>

        <x-validation-errors class="mb-4" />

        @if (session('status'))
            <div class="mb-4 font-medium text-sm text-green-600">
                {{ session('status') }}
            </div>
        @endif
        <form method="POST" action="{{ route('login') }}">
            @csrf

            <div>
                <x-label for="email" value="{{ __('Email') }}" />
                <x-input id="email" class="block mt-1 w-full" type="email" name="email" :value="old('email')" required autofocus autocomplete="username" />
            </div>

            <div class="mt-4">
                <x-label for="password" value="{{ __('Password') }}" />
                <x-input id="password" class="block mt-1 w-full" type="password" name="password" required autocomplete="current-password" />
            </div>

            <div class="block mt-4">
                <label for="remember_me" class="flex items-center">
                    <x-checkbox id="remember_me" name="remember" />
                    <span class="ms-2 text-sm text-white">{{ __('Remember me') }}</span>
                </label>
            </div>

            <div class="flex items-center justify-end mt-4">
                @if (Route::has('password.request'))
                    <a class="underline text-sm text-white hover:text-gray-300 rounded-md focus:outline-none" href="{{ route('password.request') }}">
                        {{ __('Forgot your password?') }}
                    </a>
                @endif

                <x-button class="ms-4">
                    {{ __('Log in') }}
                </x-button>
            </div>
        </form>
    </x-authentication-card>

    @push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const emailInput = document.getElementById('email');
            const loginLogo = document.getElementById('login-logo');
            const clientNameContainer = document.getElementById('client-name-container');
            const clientName = document.getElementById('client-name');
            let debounceTimer;

            // Función para verificar si el email pertenece a un cliente
            function checkClientLogo(email) {
                if (!email || !email.includes('@')) {
                    resetLogo();
                    return;
                }

                fetch(`/api/check-client/${encodeURIComponent(email)}`)
                    .then(response => response.json())
                    .then(data => {
                        if (data.is_client && data.has_photo) {
                            // Mostrar logo del cliente
                            loginLogo.src = `/storage/${data.photo_path}`;
                            loginLogo.alt = data.name;
                            loginLogo.className = 'w-40 h-40 rounded-xl object-cover border-4 border-yellow-300 shadow-lg transition-all duration-300 ease-in-out';
                            
                            // Mostrar nombre de fantasía
                            showClientName(data.name);
                            
                        } else if (data.is_client && !data.has_photo) {
                            // Cliente sin logo - mostrar logo con borde especial
                            loginLogo.src = '{{ asset("assets/images/logo.png") }}';
                            loginLogo.alt = `Cliente: ${data.name}`;
                            loginLogo.className = 'w-40 rounded-xl border-4 border-blue-300 shadow-lg transition-all duration-300 ease-in-out';
                            
                            // Mostrar nombre de fantasía
                            showClientName(data.name);
                            
                        } else {
                            // No es cliente - logo normal
                            resetLogo();
                        }
                    })
                    .catch(error => {
                        console.error('Error checking client:', error);
                        resetLogo();
                    });
            }

            // Función para mostrar el nombre del cliente
            function showClientName(name) {
                clientName.textContent = name;
                clientNameContainer.classList.remove('hidden');
                clientNameContainer.classList.add('block');
                
                // Animación de entrada
                setTimeout(() => {
                    clientNameContainer.style.opacity = '0';
                    clientNameContainer.style.transform = 'translateY(-10px)';
                    clientNameContainer.style.transition = 'all 0.3s ease-in-out';
                    
                    setTimeout(() => {
                        clientNameContainer.style.opacity = '1';
                        clientNameContainer.style.transform = 'translateY(0)';
                    }, 50);
                }, 10);
            }

            // Función para ocultar el nombre del cliente
            function hideClientName() {
                clientNameContainer.style.opacity = '0';
                clientNameContainer.style.transform = 'translateY(-10px)';
                
                setTimeout(() => {
                    clientNameContainer.classList.add('hidden');
                    clientNameContainer.classList.remove('block');
                    clientName.textContent = '';
                }, 300);
            }

            // Función para resetear el logo a su estado original
            function resetLogo() {
                loginLogo.src = '{{ asset("assets/images/logo.png") }}';
                loginLogo.alt = 'Logo';
                loginLogo.className = 'w-40 rounded-xl transition-all duration-300 ease-in-out';
                
                // Ocultar nombre del cliente
                hideClientName();
            }

            // Event listener con debounce para evitar muchas consultas
            emailInput.addEventListener('input', function() {
                clearTimeout(debounceTimer);
                debounceTimer = setTimeout(() => {
                    checkClientLogo(this.value);
                }, 500); // Esperar 500ms después del último input
            });

            // Resetear logo cuando se borra el email
            emailInput.addEventListener('blur', function() {
                if (!this.value) {
                    resetLogo();
                }
            });
        });
    </script>
    @endpush
</x-guest-layout>
