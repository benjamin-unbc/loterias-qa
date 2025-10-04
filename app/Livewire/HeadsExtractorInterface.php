<?php

namespace App\Livewire;

use App\Services\HeadsExtractorService;
use Livewire\Component;

class HeadsExtractorInterface extends Component
{
    public $url = '';
    public $headsData = null;
    public $loading = false;
    public $error = '';
    public $lastUpdate = '';
    public $autoRefresh = true;
    public $refreshInterval = 300; // 5 minutos

    public function mount()
    {
        // URL por defecto para mostrar datos automáticamente
        $this->url = 'https://dejugadas.com/cabezas';
        
        // Extraer datos automáticamente al cargar
        $this->extractData();
    }

    public function extractData()
    {
        $this->loading = true;
        $this->error = '';
        $this->headsData = null;

        try {
            $extractorService = new HeadsExtractorService();
            $this->headsData = $extractorService->extractHeads($this->url);

            if ($this->headsData) {
                $this->lastUpdate = now()->format('d/m/Y H:i:s');
            } else {
                $this->error = 'No se pudieron extraer los datos de cabezas';
            }

        } catch (\Exception $e) {
            $this->error = 'Error al extraer los datos: ' . $e->getMessage();
        }

        $this->loading = false;
    }

    public function refreshData()
    {
        $this->extractData();
    }

    public function getHeadsByCity()
    {
        if (!$this->headsData || !isset($this->headsData['heads_data'])) {
            return [];
        }

        $headsByCity = [];
        foreach ($this->headsData['heads_data'] as $head) {
            $city = $head['city'];
            $headsByCity[$city] = [
                'numbers' => $head['numbers'] ?? [],
                'count' => $head['count'] ?? 0,
                'status' => $head['status'] ?? 'active'
            ];
        }

        return $headsByCity;
    }
    
    public function getActiveCities()
    {
        if (!$this->headsData || !isset($this->headsData['heads_data'])) {
            return [];
        }

        return array_filter($this->headsData['heads_data'], function($head) {
            return ($head['count'] ?? 0) > 0;
        });
    }
    
    public function getCitiesWithNoData()
    {
        if (!$this->headsData || !isset($this->headsData['heads_data'])) {
            return [];
        }

        return array_filter($this->headsData['heads_data'], function($head) {
            return ($head['count'] ?? 0) === 0;
        });
    }

    public function getTotalNumbers()
    {
        if (!$this->headsData || !isset($this->headsData['heads_data'])) {
            return 0;
        }

        $total = 0;
        foreach ($this->headsData['heads_data'] as $head) {
            $total += $head['count'];
        }

        return $total;
    }

    public function getCitiesCount()
    {
        if (!$this->headsData || !isset($this->headsData['heads_data'])) {
            return 0;
        }

        return count($this->headsData['heads_data']);
    }
    
    public function getTurnsInfo()
    {
        if (!$this->headsData || !isset($this->headsData['turns_info'])) {
            return [];
        }

        return $this->headsData['turns_info'];
    }
    
    public function getCurrentTurn()
    {
        if (!$this->headsData || !isset($this->headsData['current_turn'])) {
            return null;
        }

        return $this->headsData['current_turn'];
    }
    
    public function getDayInfo()
    {
        if (!$this->headsData || !isset($this->headsData['day_info'])) {
            return null;
        }

        return $this->headsData['day_info'];
    }
    
    public function getNextTurnTime()
    {
        if (!$this->headsData || !isset($this->headsData['next_turn_time'])) {
            return null;
        }

        return $this->headsData['next_turn_time'];
    }
    
    public function toggleAutoRefresh()
    {
        $this->autoRefresh = !$this->autoRefresh;
        $this->emit('autoRefreshToggled');
    }

    public function render()
    {
        return view('livewire.heads-extractor-interface')
            ->layout('layouts.app');
    }
}
