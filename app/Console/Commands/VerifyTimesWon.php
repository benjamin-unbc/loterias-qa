<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class VerifyTimesWon extends Command
{
    protected $signature = 'verify:times-won';
    protected $description = 'Verifica si hay números que deberían tener Veces > 1';

    public function handle()
    {
        $this->info("=== VERIFICACIÓN DE VECES QUE SALIÓ ===\n");

        // Extractos completos
        $extracts = [
            'NAC1500' => [
                1 => '8012', 2 => '5756', 3 => '7398', 4 => '3731', 5 => '2414',
                6 => '0640', 7 => '7039', 8 => '6925', 9 => '1105', 10 => '2449',
                11 => '9683', 12 => '7671', 13 => '5510', 14 => '4203', 15 => '8433',
                16 => '1432', 17 => '6485', 18 => '6290', 19 => '2340', 20 => '6411'
            ],
            'SFE1500' => [
                1 => '3281', 2 => '5280', 3 => '0876', 4 => '2972', 5 => '8903',
                6 => '0760', 7 => '7245', 8 => '0218', 9 => '1329', 10 => '3072',
                11 => '8315', 12 => '3800', 13 => '4227', 14 => '2863', 15 => '1934',
                16 => '3662', 17 => '3046', 18 => '5128', 19 => '8678', 20 => '2791'
            ],
            'PRO1500' => [
                1 => '7996', 2 => '7990', 3 => '8150', 4 => '8345', 5 => '2292',
                6 => '0869', 7 => '0556', 8 => '6027', 9 => '8563', 10 => '7755',
                11 => '0252', 12 => '8446', 13 => '7014', 14 => '4768', 15 => '9156',
                16 => '5947', 17 => '7304', 18 => '0793', 19 => '9383', 20 => '9676'
            ],
            'RIO1500' => [
                1 => null, 2 => '6867', 3 => '7862', 4 => '9296', 5 => '2345',
                6 => '6924', 7 => '9742', 8 => '6262', 9 => '6341', 10 => '5598',
                11 => '1678', 12 => '7288', 13 => '4106', 14 => '7466', 15 => '0372',
                16 => '2104', 17 => '4534', 18 => '2967', 19 => '5073', 20 => '0359'
            ],
            'COR1500' => [
                1 => '9018', 2 => '3232', 3 => '9383', 4 => '2263', 5 => '1657',
                6 => '8000', 7 => '9796', 8 => '9542', 9 => '6425', 10 => '0475',
                11 => '9542', 12 => '5295', 13 => '3610', 14 => '5977', 15 => '4804',
                16 => '7761', 17 => '0098', 18 => '5318', 19 => '6171', 20 => '1249'
            ],
            'CTE1500' => [
                1 => null, 2 => '5135', 3 => '3618', 4 => '3611', 5 => '0329',
                6 => '7324', 7 => '4073', 8 => '4115', 9 => '9674', 10 => '8774',
                11 => '1515', 12 => '5902', 13 => '1404', 14 => '3008', 15 => '6127',
                16 => '6127', 17 => '1534', 18 => '6602', 19 => '2902', 20 => '9321'
            ],
            'MZA1500' => [
                1 => null, 2 => '7282', 3 => '2491', 4 => '4690', 5 => '0551',
                6 => '8890', 7 => '4695', 8 => '1272', 9 => '8262', 10 => '2232',
                11 => '0542', 12 => '4456', 13 => '3744', 14 => '9331', 15 => '9534',
                16 => '2481', 17 => '9838', 18 => '7396', 19 => '7766', 20 => '4166'
            ]
        ];

        // Jugadas ganadoras del ticket para verificar
        $plays = [
            ['number' => '**63', 'position' => 10, 'lottery' => 'COR1500'],
            ['number' => '**63', 'position' => 20, 'lottery' => 'COR1500'],
            ['number' => '*475', 'position' => 10, 'lottery' => 'COR1500'],
            ['number' => '*475', 'position' => 20, 'lottery' => 'COR1500'],
            ['number' => '**75', 'position' => 10, 'lottery' => 'COR1500'],
            ['number' => '**75', 'position' => 20, 'lottery' => 'COR1500'],
            ['number' => '**74', 'position' => 10, 'lottery' => 'CTE1500'],
            ['number' => '**74', 'position' => 20, 'lottery' => 'CTE1500'],
            ['number' => '*345', 'position' => 5, 'lottery' => 'PRO1500'],
            ['number' => '**45', 'position' => 5, 'lottery' => 'PRO1500'],
            ['number' => '**63', 'position' => 10, 'lottery' => 'PRO1500'],
            ['number' => '**45', 'position' => 10, 'lottery' => 'PRO1500'],
            ['number' => '**45', 'position' => 20, 'lottery' => 'PRO1500'],
            ['number' => '**45', 'position' => 10, 'lottery' => 'SFE1500'],
            ['number' => '**45', 'position' => 20, 'lottery' => 'SFE1500'],
        ];

        $foundIssues = false;

        foreach ($plays as $play) {
            $cleanNumber = str_replace('*', '', $play['number']);
            $digits = strlen($cleanNumber);
            $position = $play['position'];
            $lottery = $play['lottery'];

            // Determinar rango
            $range = [];
            if ($position == 1) {
                $range = [1];
            } elseif ($position == 5) {
                $range = range(2, 5);
            } elseif ($position == 10) {
                $range = range(2, 10);
            } elseif ($position == 20) {
                $range = range(2, 20);
            }

            // Contar apariciones
            $count = 0;
            $positions = [];
            
            if (isset($extracts[$lottery])) {
                foreach ($range as $pos) {
                    if (!isset($extracts[$lottery][$pos]) || $extracts[$lottery][$pos] === null) {
                        continue;
                    }
                    
                    $winningNumber = $extracts[$lottery][$pos];
                    $suffix = substr($winningNumber, -$digits);
                    
                    if ($suffix === $cleanNumber) {
                        $count++;
                        $positions[] = $pos;
                    }
                }
            }

            if ($count > 1) {
                $foundIssues = true;
                $this->error("⚠️  {$play['number']} posición {$position} en {$lottery}:");
                $this->line("   Debería tener Veces: {$count}");
                $this->line("   Aparece en posiciones: " . implode(', ', $positions));
                $this->newLine();
            } elseif ($count == 1) {
                $this->line("✅ {$play['number']} posición {$position} en {$lottery}: Veces 1 (correcto)");
            }
        }

        if (!$foundIssues) {
            $this->info("\n✅ Todos los resultados están correctos. Cada número solo aparece 1 vez en el rango válido.");
        } else {
            $this->error("\n❌ Se encontraron números que deberían tener Veces > 1");
        }

        return 0;
    }
}

