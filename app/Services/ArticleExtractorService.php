<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;

class ArticleExtractorService
{
    /**
     * Extrae el contenido de un artículo desde una URL usando @extractus/article-extractor
     *
     * @param string $url La URL del artículo a extraer
     * @return array|null Los datos del artículo extraído o null si hay error
     */
    public function extractArticle(string $url): ?array
    {
        try {
            // Usar cURL como alternativa a Node.js
            return $this->extractWithCurl($url);
            
        } catch (\Exception $e) {
            Log::error('Error en ArticleExtractorService: ' . $e->getMessage());
            return null;
        }
    }

    
    /**
     * Extrae el artículo usando cURL como alternativa a Node.js
     */
    private function extractWithCurl(string $url): ?array
    {
        try {
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
            curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36');
            curl_setopt($ch, CURLOPT_TIMEOUT, 30);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            
            $html = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            
            if ($httpCode !== 200 || !$html) {
                Log::error('Error al obtener la página: HTTP ' . $httpCode);
                return null;
            }
            
            // Parsear el HTML para extraer la información
            return $this->parseHtmlContent($html, $url);
            
        } catch (\Exception $e) {
            Log::error('Error en extractWithCurl: ' . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Parsea el contenido HTML para extraer la información del artículo
     */
    private function parseHtmlContent(string $html, string $url): ?array
    {
        try {
            // Crear un objeto DOMDocument
            $dom = new \DOMDocument();
            libxml_use_internal_errors(true);
            $dom->loadHTML($html);
            libxml_clear_errors();
            
            $xpath = new \DOMXPath($dom);
            
            // Extraer título
            $titleNodes = $xpath->query('//title');
            $title = $titleNodes->length > 0 ? trim($titleNodes->item(0)->textContent) : 'Resultados de la quiniela en Argentina';
            
            // Extraer descripción
            $descNodes = $xpath->query('//meta[@name="description"]/@content');
            $description = $descNodes->length > 0 ? trim($descNodes->item(0)->textContent) : '';
            
            // Extraer contenido (buscar solo las tablas principales de loterías)
            $content = $this->extractLotteryTables($xpath);
            
            // Si no hay tablas, usar todo el contenido
            if (empty($content)) {
                $bodyNodes = $xpath->query('//body');
                if ($bodyNodes->length > 0) {
                    $content = $dom->saveHTML($bodyNodes->item(0));
                }
            }
            
            return [
                'url' => $url,
                'title' => $title,
                'description' => $description,
                'content' => $content,
                'author' => 'notitimba.com',
                'source' => 'notitimba.com',
                'published' => date('Y-m-d'),
                'ttr' => 0,
                'type' => 'article'
            ];
            
        } catch (\Exception $e) {
            Log::error('Error parseando HTML: ' . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Extrae exactamente las 5 tablas principales de loterías (5 turnos)
     */
    private function extractLotteryTables(\DOMXPath $xpath): string
    {
        $content = '';
        
        // Buscar las tablas que contienen los resultados de loterías
        $tableNodes = $xpath->query('//table');
        
        $lotteryTables = [];
        $turnNames = ['La Previa', 'El Primero', 'Matutina', 'Vespertina', 'Nocturna'];
        
        foreach ($tableNodes as $table) {
            $tableHtml = $table->ownerDocument->saveHTML($table);
            
            // Verificar si esta tabla contiene datos de loterías
            if ($this->isLotteryTable($tableHtml)) {
                $lotteryTables[] = $tableHtml;
            }
        }
        
        // Si encontramos exactamente 5 tablas, usarlas
        if (count($lotteryTables) === 5) {
            return implode('', $lotteryTables);
        }
        
        // Si encontramos más de 5, tomar las primeras 5
        if (count($lotteryTables) > 5) {
            $lotteryTables = array_slice($lotteryTables, 0, 5);
            return implode('', $lotteryTables);
        }
        
        // Si encontramos menos de 5, usar todas las que tengamos
        return implode('', $lotteryTables);
    }



    /**
     * Parsea una tabla de quinielas específica
     */
    private function parseQuinielaTable(string $tableHtml, int $index): array
    {
        $dom = new \DOMDocument();
        libxml_use_internal_errors(true);
        $dom->loadHTML($tableHtml);
        libxml_clear_errors();
        
        $xpath = new \DOMXPath($dom);
        $headers = [];
        $rows = [];
        
        // Extraer headers (th)
        $headerNodes = $xpath->query('//th');
        foreach ($headerNodes as $header) {
            $headerText = trim(strip_tags($header->textContent));
            if (!empty($headerText)) {
                $headers[] = $headerText;
            }
        }
        
        // Extraer filas (tr)
        $rowNodes = $xpath->query('//tr');
        foreach ($rowNodes as $row) {
            $cells = [];
            $cellNodes = $xpath->query('.//td | .//th', $row);
            
            foreach ($cellNodes as $cell) {
                $cellText = trim(strip_tags($cell->textContent));
                $cells[] = $cellText;
            }
            
            if (!empty($cells)) {
                $rows[] = $cells;
            }
        }
        
        return [
            'index' => $index + 1,
            'headers' => $headers,
            'rows' => $rows,
            'total_rows' => count($rows),
            'total_columns' => !empty($rows) ? count($rows[0]) : 0
        ];
    }

    
    /**
     * Verifica si una tabla contiene datos de loterías principales
     */
    private function isLotteryTable(string $tableHtml): bool
    {
        // Buscar indicadores principales de que es una tabla de loterías
        $mainLotteryIndicators = [
            'La Ciudad',
            'La Provincia', 
            'Santa Fe',
            'Córdoba',
            'Entre Ríos',
            'Montevideo',
            'Mendoza',
            'Santiago',
            'Salta',
            'Corrientes',
            'Misiones',
            'Jujuy',
            'Chubut',
            'Catamarca',
            'Río Negro',
            'Santa Cruz',
            'Formosa',
            'San Juan',
            'Tucumán',
            'La Rioja',
            'Chaco',
            'San Luis',
            'La Pampa',
            'Neuquén',
            'T. del fuego'
        ];
        
        // Verificar si la tabla contiene al menos 5 de estos indicadores principales
        $foundIndicators = 0;
        foreach ($mainLotteryIndicators as $indicator) {
            if (stripos($tableHtml, $indicator) !== false) {
                $foundIndicators++;
            }
        }
        
        // Verificar que tenga números de 4 dígitos (resultados de quinielas)
        $hasNumbers = preg_match_all('/\b\d{4}\b/', $tableHtml) >= 15;
        
        // Verificar que no sea una tabla de navegación o menú
        $isNotNavigation = !preg_match('/menu|nav|header|footer|sidebar/i', $tableHtml);
        
        // Verificar que tenga un tamaño mínimo (tablas principales son grandes)
        $hasMinimumSize = strlen($tableHtml) > 2000;
        
        return $foundIndicators >= 5 && $hasNumbers && $isNotNavigation && $hasMinimumSize;
    }
    
    /**
     * Crea un script JavaScript temporal para extraer el artículo
     *
     * @param string $scriptContent El contenido del script
     * @return string La ruta del script temporal
     */
    private function createTempScript(string $scriptContent): string
    {
        $tempPath = storage_path('app/temp_extract_' . uniqid() . '.js');
        file_put_contents($tempPath, $scriptContent);
        
        return $tempPath;
    }
    
    /**
     * Encuentra la ruta de Node.js
     *
     * @return string La ruta completa de Node.js
     */
    private function findNodePath(): string
    {
        // Ruta específica donde está instalado Node.js en este sistema
        $nodePath = 'C:\\Program Files\\nodejs\\node.exe';
        
        if (file_exists($nodePath)) {
            Log::info('Node.js encontrado en: ' . $nodePath);
            return $nodePath;
        }
        
        // Rutas alternativas como fallback
        $fallbackPaths = [
            'C:\\Program Files (x86)\\nodejs\\node.exe',
            'C:\\Users\\' . get_current_user() . '\\AppData\\Roaming\\npm\\node.exe',
            'C:\\Users\\' . get_current_user() . '\\AppData\\Local\\Programs\\nodejs\\node.exe',
        ];
        
        foreach ($fallbackPaths as $path) {
            if (file_exists($path)) {
                Log::info('Node.js encontrado en fallback: ' . $path);
                return $path;
            }
        }
        
        // Último recurso
        Log::warning('Node.js no encontrado, usando comando directo');
        return 'node';
    }
    
    /**
     * Limpia el archivo temporal creado
     *
     * @param string $scriptPath La ruta del script a eliminar
     */
    private function cleanupTempScript(string $scriptPath): void
    {
        if (file_exists($scriptPath)) {
            unlink($scriptPath);
        }
    }
    
    /**
     * Extrae múltiples artículos de una lista de URLs
     *
     * @param array $urls Array de URLs a procesar
     * @return array Array de resultados con URL y datos del artículo
     */
    public function extractMultipleArticles(array $urls): array
    {
        $results = [];
        
        foreach ($urls as $url) {
            $articleData = $this->extractArticle($url);
            $results[] = [
                'url' => $url,
                'article' => $articleData,
                'success' => $articleData !== null
            ];
        }
        
        return $results;
    }
    
    /**
     * Extrae solo el texto del artículo sin metadatos
     *
     * @param string $url La URL del artículo
     * @return string|null El texto del artículo o null si hay error
     */
    public function extractArticleText(string $url): ?string
    {
        $articleData = $this->extractArticle($url);
        
        if ($articleData && isset($articleData['content'])) {
            return $articleData['content'];
        }
        
        return null;
    }
    
    /**
     * Extrae metadatos del artículo (título, descripción, imagen, etc.)
     *
     * @param string $url La URL del artículo
     * @return array|null Los metadatos del artículo o null si hay error
     */
    public function extractArticleMetadata(string $url): ?array
    {
        $articleData = $this->extractArticle($url);
        
        if (!$articleData) {
            return null;
        }
        
        return [
            'title' => $articleData['title'] ?? null,
            'description' => $articleData['description'] ?? null,
            'image' => $articleData['image'] ?? null,
            'author' => $articleData['author'] ?? null,
            'published' => $articleData['published'] ?? null,
            'url' => $articleData['url'] ?? $url,
            'site_name' => $articleData['site_name'] ?? null,
            'language' => $articleData['language'] ?? null
        ];
    }
}
