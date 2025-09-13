<?php

namespace App\Livewire\Admin;

use Livewire\Attributes\Layout;
use Livewire\Component;

class RemoteAssistance extends Component
{
    #[Layout('layouts.app')]
    public function render()
    {
        $whatsappMessage = urlencode("Cuentanos en que podemos asistirte");
        $whatsappLink = "https://api.whatsapp.com/send?phone=541125869410&text=" . $whatsappMessage;
        
        return view('livewire.admin.remote-assistance', compact('whatsappLink'));
    }
}
