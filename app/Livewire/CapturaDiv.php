<?php

namespace App\Http\Livewire;

use Livewire\Component;

class CapturaDiv extends Component
{
    public function descargarImagen()
    {
        $this->dispatchBrowserEvent('descargar-imagen'); // Dispara el evento para capturar la imagen
    }

    public function render()
    {
        return view('livewire.captura-div');
    }
}
