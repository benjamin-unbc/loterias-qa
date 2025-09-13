<?php

namespace App\Livewire;

use Livewire\Component;

class Notification extends Component
{
    public $message = '';
    public $type = 'success'; // Default type
    public $show = false;

    protected $listeners = [
        'notify' => 'showNotification',
        'hideNotification' => 'hideNotification',
    ];

    public function showNotification($message, $type = 'success')
    {
        $this->message = $message;
        $this->type = $type;
        $this->show = true;

        // Ocultar automáticamente después de 2 segundos
        $this->dispatch('show-notification');
    }

    public function hideNotification()
    {
        $this->show = false;
    }

    public function render()
    {
        return view('livewire.notification');
    }
}
