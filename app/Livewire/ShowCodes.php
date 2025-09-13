<?php

namespace App\Livewire;

use Livewire\Component;

class ShowCodes extends Component
{

    public $codes = [
        'AB' => 'NAC 1015',
        'CH1' => 'CHA 1015',
        'QW' => 'PRO 1015',
        'M10' => 'MZA 1015',
        '!' => 'CTE 1015',
        'ER' => 'SFE 1015',
        'SD' => 'COR 1015',
        'RT' => 'RIO 1015',
        'Q' => 'NAC 1200',
        'CH2' => 'CHA 1200',
        'W' => 'PRO 1200',
        'M1' => 'MZA 1200',
        'M' => 'CTE 1200',
        'R' => 'SFE 1200',
        'T' => 'COR 1200',
        'K' => 'RIO 1200',
        'A' => 'NAC 1500',
        'CH3' => 'CHA 1500',
        'E' => 'PRO 1500',
        'M2' => 'MZA 1500',
        'CT3' => 'CTE 1500',
        'D' => 'SFE 1500',
        'L' => 'COR 1500',
        'J' => 'RIO 1500',
        'S' => 'ORO 1500',
        'F' => 'NAC 1800',
        'CH4' => 'CHA 1800',
        'B' => 'PRO 1800',
        'M3' => 'MZA 1800',
        'Z' => 'CTE 1800',
        'V' => 'SFE 1800',
        'H' => 'COR 1800',
        'U' => 'RIO 1800',
        'N' => 'NAC 2100',
        'CH5' => 'CHA 2100',
        'P' => 'PRO 2100',
        'M4' => 'MZA 2100',
        'G' => 'CTE 2100',
        'I' => 'SFE 2100',
        'C' => 'COR 2100',
        'Y' => 'RIO 2100',
        'O' => 'ORO 2100',
    ];

    public function render()
    {
        return view('livewire.show-codes');
    }
}
