<?php

namespace App\Livewire;

use Livewire\Component;

class ToggleCheckbox extends Component
{
    public $checked = false;

    public function toggle()
    {
        $this->checked = !$this->checked;
    }

    public function render()
    {
        return view('livewire.toggle-checkbox');
    }
}
