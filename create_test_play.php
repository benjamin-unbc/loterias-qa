<?php

require_once 'vendor/autoload.php';

$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\Number;
use App\Models\City;
use App\Models\Extract;
use App\Models\ApusModel;
use App\Models\Result;
use Carbon\Carbon;

echo "=== CREANDO JUGADA GANADORA PARA HAROLD (user_id 24) ===" . PHP_EOL;

// 1. Crear números ganadores simulados para Ciudad NAC1015
$city = City::where('code', 'NAC1015')->first();
if (!$city) {
    echo "ERROR: No se encontró la ciudad NAC1015" . PHP_EOL;
    exit;
}

$extract = Extract::where('id', $city->extract_id)->first();
if (!$extract) {
    echo "ERROR: No se encontró el extracto" . PHP_EOL;
    exit;
}

$today = Carbon::today()->format('Y-m-d');
echo "Fecha: " . $today . PHP_EOL;
echo "Ciudad: " . $city->name . " (" . $city->code . ")" . PHP_EOL;
echo "Extracto: " . $extract->name . PHP_EOL;

// Números ganadores simulados (20 números)
$winningNumbers = [
    '1234', '5678', '9012', '3456', '7890',
    '1357', '2468', '3691', '4702', '5813',
    '6924', '7035', '8146', '9257', '0368',
    '1479', '2580', '3691', '4702', '5813'
];

echo PHP_EOL . "=== NÚMEROS GANADORES SIMULADOS ===" . PHP_EOL;
foreach ($winningNumbers as $index => $number) {
    $position = $index + 1;
    echo "Posición " . $position . ": " . $number . PHP_EOL;
}

// 2. Limpiar números existentes para hoy
Number::where('city_id', $city->id)->where('date', $today)->delete();

// 3. Insertar números ganadores
foreach ($winningNumbers as $index => $number) {
    Number::create([
        'city_id' => $city->id,
        'extract_id' => $extract->id,
        'index' => $index + 1,
        'value' => $number,
        'date' => $today
    ]);
}

echo PHP_EOL . "✅ Números ganadores insertados en la base de datos" . PHP_EOL;

// 4. Crear jugada ganadora para Harold
// Vamos a apostar al número de la cabeza (posición 1) que es '1234'
$winningNumber = $winningNumbers[0]; // '1234'
$betAmount = 100;

// Crear apuesta de 4 dígitos en posición 1
$play = ApusModel::create([
    'user_id' => 24, // Harold
    'ticket' => 'TEST-' . time(),
    'lottery' => 'AB', // Mapea a NAC1015
    'number' => $winningNumber, // '1234'
    'position' => 1, // Posición 1 (a la cabeza)
    'import' => $betAmount,
    'numberR' => null,
    'positionR' => null,
    'created_at' => now(),
    'updated_at' => now()
]);

echo PHP_EOL . "=== JUGADA CREADA PARA HAROLD ===" . PHP_EOL;
echo "Ticket: " . $play->ticket . PHP_EOL;
echo "Usuario: Harold (ID: 24)" . PHP_EOL;
echo "Lotería: AB (NAC1015)" . PHP_EOL;
echo "Número: " . $play->number . PHP_EOL;
echo "Posición: " . $play->position . PHP_EOL;
echo "Importe: $" . $play->import . PHP_EOL;

// 5. Calcular el premio esperado
// Como es posición 1 (a la cabeza) y 4 dígitos, debe usar tabla Quiniela
// 4 cifras cobra $3500.00
$expectedPrize = $betAmount * 3500;
echo PHP_EOL . "=== PREMIO ESPERADO ===" . PHP_EOL;
echo "Apuesta: $" . $betAmount . PHP_EOL;
echo "Multiplicador: 3500 (Tabla Quiniela, 4 cifras, posición 1)" . PHP_EOL;
echo "Premio esperado: $" . number_format($expectedPrize) . PHP_EOL;

// 6. Limpiar resultados existentes para hoy
Result::where('date', $today)->delete();

echo PHP_EOL . "✅ Jugada creada y datos preparados para el cálculo automático" . PHP_EOL;
echo "Ahora ejecuta el job CalculateLotteryResults para procesar los resultados" . PHP_EOL;
