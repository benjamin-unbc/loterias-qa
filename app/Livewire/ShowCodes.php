<?php

namespace App\Livewire;

use Livewire\Component;

class ShowCodes extends Component
{

    public $codes = [
        // 10:15 y horarios tempranos - Loterías principales
        'AB' => 'NAC 1015',
        'CH1' => 'CHA 1015',
        'QW' => 'PRO 1015',
        'M10' => 'MZA 1015',
        '!' => 'CTE 1015',
        'ER' => 'SFE 1015',
        'SD' => 'COR 1015',
        'RT' => 'RIO 1015',
        
        // 10:15 y horarios tempranos - Loterías adicionales
        'NQ1' => 'NQN 1015',
        'MI1' => 'MIS 1030',
        'RN1' => 'Rio 1015',
        'TU1' => 'Tucu 1130',
        'SG1' => 'San 1015',
        
        // 12:00 - Loterías principales
        'Q' => 'NAC 1200',
        'CH2' => 'CHA 1200',
        'W' => 'PRO 1200',
        'M1' => 'MZA 1200',
        'M' => 'CTE 1200',
        'R' => 'SFE 1200',
        'T' => 'COR 1200',
        'K' => 'RIO 1200',
        
        // 12:00 - Loterías adicionales
        'NQ2' => 'NQN 1200',
        'MI2' => 'MIS 1215',
        'JU1' => 'JUJ 1200',
        'SA1' => 'Salt 1130',
        'RN2' => 'Rio 1200',
        'TU2' => 'Tucu 1430',
        'SG2' => 'San 1200',
        
        // 15:00 - Loterías principales
        'A' => 'NAC 1500',
        'CH3' => 'CHA 1500',
        'E' => 'PRO 1500',
        'M2' => 'MZA 1500',
        'Ct3' => 'CTE 1500',
        'D' => 'SFE 1500',
        'L' => 'COR 1500',
        'J' => 'RIO 1500',
        'S' => 'ORO 1800',
        
        // 15:00 - Loterías adicionales
        'NQ3' => 'NQN 1500',
        'MI3' => 'MIS 1500',
        'JU2' => 'JUJ 1500',
        'SA2' => 'Salt 1400',
        'RN3' => 'Rio 1500',
        'TU3' => 'Tucu 1730',
        'SG3' => 'San 1500',
        
        // 18:00 - Loterías principales
        'F' => 'NAC 1800',
        'CH4' => 'CHA 1800',
        'B' => 'PRO 1800',
        'M3' => 'MZA 1800',
        'Z' => 'CTE 1800',
        'V' => 'SFE 1800',
        'H' => 'COR 1800',
        'U' => 'RIO 1800',
        
        // 18:00 - Loterías adicionales
        'NQ4' => 'NQN 1800',
        'MI4' => 'MIS 1800',
        'JU3' => 'JUJ 1800',
        'SA3' => 'Salt 1730',
        'RN4' => 'Rio 1800',
        'TU4' => 'Tucu 1930',
        'SG4' => 'San 1945',
        
        // 21:00 - Loterías principales
        'N' => 'NAC 2100',
        'CH5' => 'CHA 2100',
        'P' => 'PRO 2100',
        'M4' => 'MZA 2100',
        'G' => 'CTE 2100',
        'I' => 'SFE 2100',
        'C' => 'COR 2100',
        'Y' => 'RIO 2100',
        'O' => 'ORO 2100',
        
        // 21:00 - Loterías adicionales
        'NQ5' => 'NQN 2100',
        'JU4' => 'JUJ 2100',
        'RN5' => 'Rio 2100',
        'SA4' => 'Salt 2100',
        'TU5' => 'Tucu 2200',
        'MI5' => 'MIS 2115',
        'SG5' => 'San 2200',
    ];

    public function render()
    {
        return view('livewire.show-codes');
    }
}
