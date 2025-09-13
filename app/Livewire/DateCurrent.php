<?php

namespace App\Livewire;

use Livewire\Component;

class DateCurrent extends Component
{
    public $date;

    public function mount()
    {
        $this->date = date('d-m-Y');
    }

    public function render()
    {
        return view('livewire.date-current');
    }
}
