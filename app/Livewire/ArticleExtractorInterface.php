<?php

namespace App\Livewire;

use App\Services\ArticleExtractorService;
use Livewire\Component;
use Livewire\WithFileUploads;

class ArticleExtractorInterface extends Component
{
    use WithFileUploads;

    public $url = '';
    public $articleData = null;
    public $allTablesData = [];
    public $loading = false;
    public $error = '';
    public $extractionType = 'full'; // Solo extracción completa

    // Sin validaciones necesarias para visualización automática

    public function mount()
    {
        // URL por defecto para mostrar datos automáticamente
        $this->url = 'https://www.notitimba.com/lots/';
        
        // Extraer datos automáticamente al cargar
        $this->extractData();
    }

    private function extractData()
    {
        $this->loading = true;
        $this->error = '';
        $this->articleData = null;
        $this->allTablesData = [];

        try {
            $extractorService = new ArticleExtractorService();
            $this->articleData = $extractorService->extractArticle($this->url);

            if ($this->articleData) {
                $this->processAllTablesData();
            } else {
                $this->error = 'No se pudieron extraer los datos del artículo';
            }

        } catch (\Exception $e) {
            $this->error = 'Error al extraer los datos: ' . $e->getMessage();
        }

        $this->loading = false;
    }

    private function processAllTablesData()
    {
        if (!isset($this->articleData['content'])) {
            return;
        }

        $content = $this->articleData['content'];
        
        // Buscar todas las tablas en el contenido HTML
        preg_match_all('/<table[^>]*>.*?<\/table>/s', $content, $tables);
        
        foreach ($tables[0] as $index => $table) {
            $tableData = $this->parseTable($table);
            if (!empty($tableData)) {
                $this->allTablesData[] = [
                    'index' => $index + 1,
                    'headers' => $tableData['headers'],
                    'rows' => $tableData['rows']
                ];
            }
        }
    }

    private function parseTable($table)
    {
        $headers = [];
        $rows = [];
        
        // Extraer headers (th)
        preg_match_all('/<th[^>]*>(.*?)<\/th>/s', $table, $headerMatches);
        foreach ($headerMatches[1] as $header) {
            // Convertir <br> a espacios y limpiar
            $cleanHeader = str_replace(['<br>', '<br/>', '<br />'], ' ', $header);
            $cleanHeader = strip_tags($cleanHeader);
            $cleanHeader = trim(preg_replace('/\s+/', ' ', $cleanHeader));
            $headers[] = $cleanHeader;
        }
        
        // Extraer filas (tr)
        preg_match_all('/<tr[^>]*>(.*?)<\/tr>/s', $table, $rowMatches);
        
        foreach ($rowMatches[1] as $row) {
            // Extraer celdas (td y th)
            preg_match_all('/<(td|th)[^>]*>(.*?)<\/(td|th)>/s', $row, $cellMatches);
            $cells = [];
            foreach ($cellMatches[2] as $cell) {
                // Convertir <br> a espacios y limpiar
                $cleanCell = str_replace(['<br>', '<br/>', '<br />'], ' ', $cell);
                $cleanCell = strip_tags($cleanCell);
                $cleanCell = trim(preg_replace('/\s+/', ' ', $cleanCell));
                $cells[] = $cleanCell;
            }
            
            if (!empty($cells)) {
                $rows[] = $cells;
            }
        }
        
        return [
            'headers' => $headers,
            'rows' => $rows
        ];
    }


    public function render()
    {
        return view('livewire.article-extractor-interface')
            ->layout('layouts.app');
    }
}
