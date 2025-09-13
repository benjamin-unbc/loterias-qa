<div
    x-data="{ shown: @entangle('show'), timeout: null }"
    x-init="$wire.on('show-notification', () => {
        clearTimeout(timeout);
        shown = true;
        timeout = setTimeout(() => { shown = false; $wire.hideNotification(); }, 3000);
    })"
    x-show="shown"
    x-transition:enter.opacity.duration.400ms
    x-transition:leave.opacity.duration.300ms
>
    <div class="fixed bottom-4 right-4 z-50">
        <div class="flex items-center px-5 py-3 rounded-lg shadow-lg text-sm text-white animate-fade-in" :class="{
            'bg-green-500': '{{ $type }}' === 'success',
            'bg-red-500': '{{ $type }}' === 'error',
            'bg-orange-500': '{{ $type }}' === 'warning',
            'bg-blue-500': '{{ $type }}' === 'info'
        }">
            <span class="mr-2">
                @if($type === 'success')
                    <i class="fas fa-check-circle"></i>
                @elseif($type === 'error')
                    <i class="fas fa-times-circle"></i>
                @elseif($type === 'warning')
                    <i class="fas fa-exclamation-triangle"></i>
                @elseif($type === 'info')
                    <i class="fas fa-info-circle"></i>
                @endif
            </span>
            <span>{{ $message }}</span>
            <button wire:click="hideNotification" class="ml-4">
                <i class="fas fa-times"></i>
            </button>
        </div>
    </div>
</div>
