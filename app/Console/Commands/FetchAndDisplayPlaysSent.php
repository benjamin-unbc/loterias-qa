<?php

namespace App\Console\Commands;

use App\Services\LotteryResultProcessor;
use Illuminate\Console\Command;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use App\Models\QuinielaModel; // Keep for error message, though not used for calculation
use App\Models\PrizesModel; // Keep for error message, though not used for calculation
use App\Models\FigureOneModel; // Keep for error message, though not used for calculation
use App\Models\FigureTwoModel; // Keep for error message, though not used for calculation
use App\Models\BetCollectionRedoblonaModel; // Keep for error message, though not used for calculation
use App\Models\BetCollection5To20Model; // Keep for error message, though not used for calculation
use App\Models\BetCollection10To20Model; // Keep for error message, though not used for calculation
use App\Models\Number; // Keep for error message, though not used for calculation
use App\Models\PlaysSentModel; // Keep for error message, though not used for calculation
use App\Models\City; // Keep for error message, though not used for calculation
 
class FetchAndDisplayPlaysSent extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'fetch:plays-sent';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Fetches and processes plays sent for lottery results.';

    public function handle()
    {
        Log::info("Ejecutando comando fetch:plays-sent a las " . now());
        
        $this->info("🔹 Iniciando el proceso de detección de ganadores...");
        $dateToProcess = Carbon::today()->toDateString();
        $processor = new LotteryResultProcessor();
        $processor->process($dateToProcess);

        $this->info("🔹 Procesamiento de ganadores completado para la fecha: {$dateToProcess}");
        return 0;
    }
}
