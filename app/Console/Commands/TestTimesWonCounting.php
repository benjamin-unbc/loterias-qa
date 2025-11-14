<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Result;
use App\Models\ApusModel;
use App\Services\LotteryCompletenessService;
use Illuminate\Support\Facades\Log;

class TestTimesWonCounting extends Command
{
    protected $signature = 'test:times-won-counting {ticket?} {--lottery=} {--number=} {--position=}';
    protected $description = 'Verifica si el conteo de times_won está funcionando correctamente';

    public function handle()
    {
        $this->info("=== VERIFICACIÓN DE CONTEO DE times_won ===\n");

        // Obtener parámetros
        $ticket = $this->argument('ticket');
        $lottery = $this->option('lottery');
        $number = $this->option('number');
        $position = $this->option('position');

        if ($ticket) {
            // Verificar un ticket específico
            $this->checkTicket($ticket);
        } elseif ($lottery && $number && $position) {
            // Verificar una jugada específica
            $this->checkPlay($lottery, $number, $position);
        } else {
            // Verificar los últimos resultados insertados
            $this->checkRecentResults();
        }

        return 0;
    }

    private function checkTicket($ticket)
    {
        $this->info("Verificando ticket: {$ticket}\n");

        $results = Result::where('ticket', $ticket)
            ->orderBy('created_at', 'desc')
            ->get();

        if ($results->isEmpty()) {
            $this->error("No se encontraron resultados para el ticket {$ticket}");
            return;
        }

        $this->table(
            ['Lotería', 'Número', 'Posición', 'times_won', 'Premio', 'posicion_g'],
            $results->map(function ($r) {
                return [
                    $r->lottery,
                    $r->number,
                    $r->position,
                    $r->times_won ?? 'NULL',
                    '$' . number_format($r->aciert ?? 0, 2),
                    $r->posicion_g ?? 'NULL'
                ];
            })->toArray()
        );

        // Verificar si hay resultados con times_won > 1
        $multipleWins = $results->where('times_won', '>', 1);
        if ($multipleWins->count() > 0) {
            $this->info("\n✅ Se encontraron " . $multipleWins->count() . " resultados con times_won > 1:");
            foreach ($multipleWins as $r) {
                $this->line("  - {$r->number} posición {$r->position} en {$r->lottery}: Veces {$r->times_won}");
            }
        } else {
            $this->warn("\n⚠️  No se encontraron resultados con times_won > 1");
            $this->line("   Todos los resultados tienen times_won = 1 o NULL");
        }
    }

    private function checkPlay($lottery, $number, $position)
    {
        $this->info("Verificando jugada: {$number} posición {$position} en {$lottery}\n");

        // Buscar la jugada
        $play = ApusModel::where('lottery', 'LIKE', "%{$lottery}%")
            ->where('number', $number)
            ->where('position', $position)
            ->first();

        if (!$play) {
            $this->error("No se encontró la jugada");
            return;
        }

        // Buscar resultados
        $results = Result::where('ticket', $play->ticket)
            ->where('lottery', $lottery)
            ->where('number', $number)
            ->where('position', $position)
            ->get();

        if ($results->isEmpty()) {
            $this->warn("No se encontraron resultados para esta jugada");
            $this->line("Intentando verificar manualmente...\n");

            // Intentar verificar manualmente
            $date = $play->created_at->format('Y-m-d');
            $isComplete = LotteryCompletenessService::isLotteryComplete($lottery, $date);

            if ($isComplete) {
                $completeNumbers = LotteryCompletenessService::getCompleteLotteryNumbersCollection($lottery, $date);
                $this->info("Lotería completa: SÍ");
                $this->info("Números completos: " . $completeNumbers->count());

                // Contar manualmente
                $playNumber = trim(str_replace('*', '', $number));
                $playLength = strlen($playNumber);
                $playedPosition = (int)$position;

                // Determinar rango
                $allowedIndexes = [];
                switch ($playedPosition) {
                    case 1:
                        $allowedIndexes = [1];
                        break;
                    case 5:
                        $allowedIndexes = range(2, 5);
                        break;
                    case 10:
                        $allowedIndexes = range(2, 10);
                        break;
                    case 20:
                        $allowedIndexes = range(2, 20);
                        break;
                    default:
                        $allowedIndexes = [$playedPosition];
                }

                $count = 0;
                $positions = [];

                foreach ($completeNumbers as $num) {
                    $numIndex = (int)$num->index;
                    if (!in_array($numIndex, $allowedIndexes)) {
                        continue;
                    }

                    $winningValue = trim((string)$num->value);
                    $winningNumberStr = str_pad($winningValue, 4, '0', STR_PAD_LEFT);
                    $winningSuffix = substr($winningNumberStr, -$playLength);

                    if ($playNumber === $winningSuffix) {
                        $count++;
                        $positions[] = $numIndex;
                    }
                }

                $this->info("\nConteo manual:");
                $this->line("  - Debería tener times_won: {$count}");
                $this->line("  - Aparece en posiciones: " . implode(', ', $positions));
            } else {
                $this->warn("Lotería no está completa aún");
            }
        } else {
            $this->table(
                ['times_won', 'Premio', 'posicion_g', 'Fecha'],
                $results->map(function ($r) {
                    return [
                        $r->times_won ?? 'NULL',
                        '$' . number_format($r->aciert ?? 0, 2),
                        $r->posicion_g ?? 'NULL',
                        $r->date
                    ];
                })->toArray()
            );
        }
    }

    private function checkRecentResults()
    {
        $this->info("Verificando los últimos 20 resultados insertados:\n");

        $results = Result::orderBy('created_at', 'desc')
            ->limit(20)
            ->get();

        if ($results->isEmpty()) {
            $this->error("No se encontraron resultados");
            return;
        }

        $this->table(
            ['Ticket', 'Lotería', 'Número', 'Pos', 'times_won', 'Premio'],
            $results->map(function ($r) {
                return [
                    $r->ticket,
                    $r->lottery,
                    $r->number,
                    $r->position,
                    $r->times_won ?? 'NULL',
                    '$' . number_format($r->aciert ?? 0, 2)
                ];
            })->toArray()
        );

        // Estadísticas
        $total = $results->count();
        $withMultipleWins = $results->where('times_won', '>', 1)->count();
        $withOneWin = $results->where('times_won', '=', 1)->count();
        $withNull = $results->whereNull('times_won')->count();

        $this->info("\nEstadísticas:");
        $this->line("  - Total: {$total}");
        $this->line("  - Con times_won > 1: {$withMultipleWins}");
        $this->line("  - Con times_won = 1: {$withOneWin}");
        $this->line("  - Con times_won NULL: {$withNull}");

        if ($withMultipleWins > 0) {
            $this->info("\n✅ El sistema SÍ está detectando múltiples apariciones!");
        } else {
            $this->warn("\n⚠️  No se encontraron resultados con times_won > 1 en los últimos 20");
            $this->line("   Esto podría indicar que:");
            $this->line("   1. Los números solo aparecen 1 vez (normal)");
            $this->line("   2. El conteo no está funcionando (revisar logs)");
        }
    }
}

