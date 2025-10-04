<?php

namespace App\Http\Controllers;

use App\Services\HeadsExtractorService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class HeadsExtractorController extends Controller
{
    protected HeadsExtractorService $extractorService;

    public function __construct(HeadsExtractorService $extractorService)
    {
        $this->extractorService = $extractorService;
    }

    /**
     * Extrae datos de cabezas desde una URL
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function extractHeads(Request $request): JsonResponse
    {
        $request->validate([
            'url' => 'nullable|url'
        ]);

        $url = $request->input('url', 'https://vivitusuerte.com/cabezas');
        $headsData = $this->extractorService->extractHeads($url);

        if (!$headsData) {
            return response()->json([
                'success' => false,
                'message' => 'Error al extraer los datos de cabezas'
            ], 500);
        }

        return response()->json([
            'success' => true,
            'data' => $headsData
        ]);
    }

    /**
     * Extrae solo los números de cabezas
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function extractNumbers(Request $request): JsonResponse
    {
        $request->validate([
            'url' => 'nullable|url'
        ]);

        $url = $request->input('url', 'https://vivitusuerte.com/cabezas');
        $numbers = $this->extractorService->extractHeadsNumbers($url);

        if (!$numbers) {
            return response()->json([
                'success' => false,
                'message' => 'Error al extraer los números de cabezas'
            ], 500);
        }

        return response()->json([
            'success' => true,
            'numbers' => $numbers
        ]);
    }

    /**
     * Extrae múltiples URLs de cabezas
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function extractMultiple(Request $request): JsonResponse
    {
        $request->validate([
            'urls' => 'required|array|min:1',
            'urls.*' => 'url'
        ]);

        $urls = $request->input('urls');
        $results = $this->extractorService->extractMultipleHeads($urls);

        return response()->json([
            'success' => true,
            'results' => $results
        ]);
    }

    /**
     * Obtiene estadísticas de los datos de cabezas
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function getStats(Request $request): JsonResponse
    {
        $request->validate([
            'url' => 'nullable|url'
        ]);

        $url = $request->input('url', 'https://vivitusuerte.com/cabezas');
        $headsData = $this->extractorService->extractHeads($url);

        if (!$headsData || !isset($headsData['heads_data'])) {
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener los datos de cabezas'
            ], 500);
        }

        $stats = $this->calculateStats($headsData['heads_data']);

        return response()->json([
            'success' => true,
            'stats' => $stats
        ]);
    }

    /**
     * Calcula estadísticas de los datos de cabezas
     *
     * @param array $headsData
     * @return array
     */
    private function calculateStats(array $headsData): array
    {
        $totalNumbers = 0;
        $citiesCount = count($headsData);
        $allNumbers = [];
        $cityStats = [];

        foreach ($headsData as $head) {
            $city = $head['city'];
            $numbers = $head['numbers'];
            $count = $head['count'];

            $totalNumbers += $count;
            $allNumbers = array_merge($allNumbers, $numbers);

            $cityStats[] = [
                'city' => $city,
                'count' => $count,
                'numbers' => $numbers
            ];
        }

        // Calcular estadísticas adicionales
        $averagePerCity = $citiesCount > 0 ? round($totalNumbers / $citiesCount, 2) : 0;
        
        // Encontrar ciudad con más números
        $maxCity = null;
        $maxCount = 0;
        foreach ($cityStats as $city) {
            if ($city['count'] > $maxCount) {
                $maxCount = $city['count'];
                $maxCity = $city['city'];
            }
        }

        // Encontrar ciudad con menos números
        $minCity = null;
        $minCount = PHP_INT_MAX;
        foreach ($cityStats as $city) {
            if ($city['count'] < $minCount) {
                $minCount = $city['count'];
                $minCity = $city['city'];
            }
        }

        return [
            'total_numbers' => $totalNumbers,
            'cities_count' => $citiesCount,
            'average_per_city' => $averagePerCity,
            'max_city' => [
                'name' => $maxCity,
                'count' => $maxCount
            ],
            'min_city' => [
                'name' => $minCity,
                'count' => $minCount
            ],
            'all_numbers' => $allNumbers,
            'unique_numbers' => array_unique($allNumbers),
            'city_stats' => $cityStats
        ];
    }
}
