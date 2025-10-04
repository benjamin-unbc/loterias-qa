<?php

namespace App\Livewire;

use App\Models\SystemNotification;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class SystemNotifications extends Component
{
    public $notifications = [];
    public $unreadCount = 0;

    public function mount()
    {
        $this->loadNotifications();
    }

    public function loadNotifications()
    {
        // Temporalmente mostrar a todos los usuarios para probar
        if (Auth::check()) {
            $this->notifications = SystemNotification::getRecent();
            $this->unreadCount = SystemNotification::getUnread()->count();
        }
    }

    public function markAsRead($notificationId)
    {
        $notification = SystemNotification::find($notificationId);
        if ($notification) {
            $notification->markAsRead();
            $this->loadNotifications();
        }
    }

    public function markAllAsRead()
    {
        SystemNotification::where('is_read', false)->update([
            'is_read' => true,
            'read_at' => now()
        ]);
        $this->loadNotifications();
    }

    public function render()
    {
        return view('livewire.system-notifications');
    }
}