@if(Auth::check())
<div class="fixed top-4 left-4 z-50 max-w-sm">
    @if($unreadCount > 0)
        <!-- Botón de notificaciones -->
        <button onclick="toggleNotifications()" 
                class="bg-blue-600 hover:bg-blue-700 text-white px-3 py-2 rounded-lg shadow-lg flex items-center gap-2 transition-all duration-200">
            <i class="fas fa-bell"></i>
            <span class="text-sm font-medium">Sistema</span>
            @if($unreadCount > 0)
                <span class="bg-red-500 text-white text-xs rounded-full px-2 py-1 min-w-[20px] text-center">
                    {{ $unreadCount }}
                </span>
            @endif
        </button>
    @endif

    <!-- Panel de notificaciones -->
    <div id="notifications-panel" class="hidden mt-2 bg-white rounded-lg shadow-xl border max-h-96 overflow-y-auto">
        <div class="p-3 border-b bg-gray-50 rounded-t-lg">
            <div class="flex justify-between items-center">
                <h3 class="font-semibold text-gray-800">Notificaciones del Sistema</h3>
                @if($unreadCount > 0)
                    <button wire:click="markAllAsRead" 
                            class="text-xs bg-blue-500 text-white px-2 py-1 rounded hover:bg-blue-600">
                        Marcar todas como leídas
                    </button>
                @endif
            </div>
        </div>
        
        <div class="max-h-80 overflow-y-auto">
            @forelse($notifications as $notification)
                <div class="p-3 border-b hover:bg-gray-50 transition-colors {{ !$notification->is_read ? 'bg-blue-50' : '' }}">
                    <div class="flex items-start gap-3">
                        <!-- Icono según tipo -->
                        <div class="flex-shrink-0 mt-1">
                            @if($notification->type === 'success')
                                <i class="fas fa-check-circle text-green-500"></i>
                            @elseif($notification->type === 'warning')
                                <i class="fas fa-exclamation-triangle text-yellow-500"></i>
                            @elseif($notification->type === 'error')
                                <i class="fas fa-times-circle text-red-500"></i>
                            @else
                                <i class="fas fa-info-circle text-blue-500"></i>
                            @endif
                        </div>
                        
                        <div class="flex-1 min-w-0">
                            <div class="flex justify-between items-start">
                                <h4 class="text-sm font-medium text-gray-900">
                                    {{ $notification->title }}
                                </h4>
                                @if(!$notification->is_read)
                                    <button wire:click="markAsRead({{ $notification->id }})"
                                            class="text-xs text-blue-600 hover:text-blue-800">
                                        Marcar como leída
                                    </button>
                                @endif
                            </div>
                            
                            <p class="text-sm text-gray-600 mt-1">
                                {{ $notification->message }}
                            </p>
                            
                            <div class="flex justify-between items-center mt-2">
                                <span class="text-xs text-gray-500">
                                    {{ $notification->created_at->diffForHumans() }}
                                </span>
                                
                                @if($notification->data && isset($notification->data['inserted']) && $notification->data['inserted'] > 0)
                                    <span class="text-xs bg-green-100 text-green-800 px-2 py-1 rounded">
                                        {{ $notification->data['inserted'] }} números insertados
                                    </span>
                                @endif
                            </div>
                        </div>
                    </div>
                </div>
            @empty
                <div class="p-4 text-center text-gray-500">
                    <i class="fas fa-bell-slash text-2xl mb-2"></i>
                    <p class="text-sm">No hay notificaciones del sistema</p>
                </div>
            @endforelse
        </div>
    </div>
</div>

<script>
function toggleNotifications() {
    const panel = document.getElementById('notifications-panel');
    panel.classList.toggle('hidden');
}

// Cerrar panel al hacer clic fuera
document.addEventListener('click', function(event) {
    const panel = document.getElementById('notifications-panel');
    const button = event.target.closest('button');
    
    if (panel && !panel.contains(event.target) && !button?.onclick) {
        panel.classList.add('hidden');
    }
});

// Auto-refresh cada 30 segundos
setInterval(() => {
    @this.call('loadNotifications');
}, 30000);
</script>
@endif