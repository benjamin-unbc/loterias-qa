<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;

class WinningNumbersService
{
    /**
     * Extrae los 20 números ganadores de una ciudad específica
     *
     * @param string $city Nombre de la ciudad
     * @return array|null Los números ganadores o null si hay error
     */
    public function extractWinningNumbers(string $city): ?array
    {
        try {
            $this->log('Iniciando extracción de números ganadores para: ' . $city);
            
            
            // Mapear nombres de ciudades a URLs
            $cityUrl = $this->getCityUrl($city);
            
            if (!$cityUrl) {
                $this->log("No se encontró URL para la ciudad: $city", 'warning');
                return null;
            }
            
            $url = 'https://vivitusuerte.com' . $cityUrl;
            $this->log("Accediendo a: $url");
            
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
            curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36');
            curl_setopt($ch, CURLOPT_TIMEOUT, 30);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
            
            $html = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $error = curl_error($ch);
            curl_close($ch);
            
            if ($error) {
                $this->log('Error cURL: ' . $error, 'error');
                return null;
            }
            
            if ($httpCode !== 200) {
                $this->log('Error HTTP: ' . $httpCode . ' para URL: ' . $url, 'error');
                return null;
            }
            
            if (!$html) {
                $this->log('No se obtuvo contenido HTML de la página', 'error');
                return null;
            }
            
            $this->log('HTML obtenido exitosamente, tamaño: ' . strlen($html) . ' bytes');
            
            // Verificar si la fecha de la página coincide con la fecha actual
            if (!$this->isPageDateCurrent($html)) {
                $this->log("La página muestra fecha anterior. No se insertarán números para evitar duplicados del día anterior.", 'warning');
                return [
                    'city' => $city,
                    'turns' => [],
                    'extracted_at' => $this->getCurrentTime(),
                    'url' => $url,
                    'skipped' => true,
                    'reason' => 'Fecha de página anterior detectada'
                ];
            }
            
            // Parsear los números ganadores
            return $this->parseWinningNumbers($html, $city);
            
        } catch (\Exception $e) {
            $this->log('Error en extractWinningNumbers: ' . $e->getMessage(), 'error');
            return null;
        }
    }
    
    /**
     * Obtiene la URL específica para cada ciudad
     */
    private function getCityUrl(string $city): ?string
    {
        $cityUrls = [
            'Ciudad' => '/pizarra/ciudad',
            'Santa Fé' => '/pizarra/santa+fe',
            'Provincia' => '/pizarra/provincia',
            'Entre Ríos' => '/pizarra/entre+rios',
            'Córdoba' => '/pizarra/cordoba',
            'Corrientes' => '/pizarra/corrientes',
            'Chaco' => '/pizarra/chaco',
            'Neuquén' => '/pizarra/neuquen',
            'Misiones' => '/pizarra/misiones',
            'Mendoza' => '/pizarra/mendoza',
            'Río Negro' => '/pizarra/rio+negro',
            'Tucumán' => '/pizarra/tucuman',
            'Santiago' => '/pizarra/santiago',
            'Jujuy' => '/pizarra/jujuy',
            'Salta' => '/pizarra/salta',
            'Montevideo' => '/pizarra/montevideo',
            'San Luis' => '/pizarra/san+luis',
            'Chubut' => '/pizarra/chubut',
            'Formosa' => '/pizarra/formosa',
            'Catamarca' => '/pizarra/catamarca',
            'San Juan' => '/pizarra/san+juan'
        ];
        
        return $cityUrls[$city] ?? null;
    }
    
    /**
     * Parsea los números ganadores del HTML
     */
    private function parseWinningNumbers(string $html, string $city): ?array
    {
        try {
            $dom = new \DOMDocument();
            libxml_use_internal_errors(true);
            $dom->loadHTML($html);
            $xpath = new \DOMXPath($dom);
            
            $turnsData = [];
            
            // Definir los turnos a extraer según la ciudad
            if (in_array($city, ['Jujuy', 'Salta'])) {
                // Jujuy y Salta tienen Primera, Matutina, Vespertina y Nocturna
                $turns = ['Primera', 'Matutina', 'Vespertina', 'Nocturna'];
            } elseif ($city === 'Montevideo') {
                // Montevideo solo tiene Matutina y Nocturna (pero se mapean a Vespertina y Nocturna)
                $turns = ['Matutina', 'Nocturna'];
            } else {
                // Las demás ciudades tienen todos los turnos
                $turns = ['La Previa', 'Primera', 'Matutina', 'Vespertina', 'Nocturna'];
            }
            
            // Buscar todas las tablas primero
            $tables = $xpath->query('//table');
            $this->log("Encontradas " . $tables->length . " tablas en total");
            
            // Buscar cada tabla de turno
            foreach ($turns as $turn) {
                $numbers = $this->extractNumbersFromTurn($xpath, $turn, $tables, $city);
                $turnsData[$turn] = $numbers;
            }
            
            return [
                'city' => $city,
                'turns' => $turnsData,
                'extracted_at' => $this->getCurrentTime(),
                'url' => 'https://vivitusuerte.com' . $this->getCityUrl($city)
            ];
            
        } catch (\Exception $e) {
            $this->log('Error parseando números ganadores: ' . $e->getMessage(), 'error');
            return null;
        }
    }
    
    /**
     * Extrae números de un turno específico
     */
    private function extractNumbersFromTurn(\DOMXPath $xpath, string $turn, $tables, string $city): array
    {
        $numbers = [];
        
        // Mapear turnos a índices de tabla - Ciudad y Montevideo tienen estructura diferente
        $turnTableMapping = [
            'Ciudad' => [
                'La Previa' => 0,
                'Primera' => 2,  // Ciudad tiene estructura diferente
                'Matutina' => 4,
                'Vespertina' => 6,
                'Nocturna' => 8
            ],
            'Montevideo' => [
                'La Previa' => null,  // No existe en Montevideo
                'Primera' => null,    // No existe en Montevideo
                'Matutina' => 4,      // Datos de la tabla "Matutina" de la web (tabla #4)
                'Vespertina' => null, // No existe en Montevideo
                'Nocturna' => 8       // Datos de la tabla "Nocturna" de la web (tabla #8)
            ],
            'Santa Fé' => [
                'La Previa' => 0,
                'Primera' => 1,
                'Matutina' => 2,
                'Vespertina' => 3,
                'Nocturna' => 4  // Santa Fe usa estructura estándar
            ],
            'default' => [
                'La Previa' => 0,
                'Primera' => 1,
                'Matutina' => 2,
                'Vespertina' => 3,
                'Nocturna' => 4
            ]
        ];
        
        $mapping = $turnTableMapping[$city] ?? $turnTableMapping['default'];
        $tableIndex = $mapping[$turn] ?? null;
        
        if ($tableIndex !== null && isset($tables[$tableIndex])) {
            $table = $tables[$tableIndex];
            $this->log("Usando tabla #$tableIndex para turno: $turn en ciudad: $city");
            
            // Extraer números de las celdas de la tabla en el orden correcto
            $cells = $xpath->query('.//td', $table);
            $this->log("Tabla #$tableIndex tiene " . $cells->length . " celdas");
            
            // Crear array para almacenar números por posición
            $positionedNumbers = [];
            
            for ($i = 0; $i < $cells->length; $i += 2) {
                $positionCell = $cells->item($i);
                $numberCell = $cells->item($i + 1);
                
                if ($positionCell && $numberCell) {
                    $position = trim($positionCell->textContent);
                    $number = trim($numberCell->textContent);
                    
                    // Verificar que la posición sea un número y el valor sea un número de 4 dígitos
                    if (preg_match('/^\d+\.?$/', $position) && preg_match('/^\d{4}$/', $number) && $number !== '----') {
                        $positionNumber = intval($position);
                        $positionedNumbers[$positionNumber] = $number;
                    }
                }
            }
            
            // Ordenar por posición y extraer solo los números
            ksort($positionedNumbers);
            $numbers = array_values($positionedNumbers);
            
            $this->log("Números extraídos para $turn: " . count($numbers) . " números");
            if (!empty($numbers)) {
                $this->log("Primeros 5 números: " . implode(', ', array_slice($numbers, 0, 5)));
            }
        } else {
            $this->log("No se encontró tabla para turno: $turn en ciudad: $city");
        }
        
        return $numbers;
    }
    
    /**
     * Obtiene la lista de ciudades disponibles
     */
    public function getAvailableCities(): array
    {
        return [
            'Ciudad',
            'Santa Fé',
            'Provincia',
            'Entre Ríos',
            'Córdoba',
            'Corrientes',
            'Chaco',
            'Neuquén',
            'Misiones',
            'Mendoza',
            'Río Negro',
            'Tucumán',
            'Santiago',
            'Jujuy',
            'Salta',
            'Montevideo',
            'San Luis',
            'Chubut',
            'Formosa',
            'Catamarca',
            'San Juan'
        ];
    }
    
    /**
     * Método de logging que funciona con y sin Laravel
     */
    private function log(string $message, string $level = 'info'): void
    {
        try {
            if (class_exists('Illuminate\Support\Facades\Log')) {
                Log::$level($message);
            } else {
                echo "[" . strtoupper($level) . "] " . $message . "\n";
            }
        } catch (\Exception $e) {
            echo "[" . strtoupper($level) . "] " . $message . "\n";
        }
    }
    
    /**
     * Verifica si la página muestra números del día actual
     * SOLO extrae números cuando la página muestra la fecha actual del sistema
     */
    private function isPageDateCurrent(string $html): bool
    {
        try {
            $currentDate = date('Y-m-d');
            $this->log("Fecha actual del sistema: $currentDate");
            
            // Extraer la fecha de la página desde el atributo data-fecha-default
            $pageDate = $this->extractPageDate($html);
            
            if ($pageDate) {
                $this->log("Fecha encontrada en la página: $pageDate");
                
                // SOLO proceder si la fecha de la página coincide EXACTAMENTE con la fecha actual
                if ($pageDate === $currentDate) {
                    $this->log("✅ La página muestra la fecha actual ($currentDate). Procediendo con extracción.");
                    return true;
                } else {
                    $this->log("⚠️ La página muestra fecha diferente ($pageDate vs $currentDate). NO se extraerán números hasta que la página actualice su fecha.");
                    return false;
                }
            }
            
            // Si no se puede extraer la fecha de la página, NO proceder
            $this->log("❌ No se pudo extraer fecha de la página. NO se extraerán números por seguridad.");
            return false;
            
        } catch (\Exception $e) {
            $this->log('Error verificando fecha de página: ' . $e->getMessage(), 'error');
            // En caso de error, NO proceder por seguridad
            $this->log("❌ Error en verificación de fecha. NO se extraerán números por seguridad.");
            return false;
        }
    }
    
    /**
     * Extrae la fecha de la página desde el atributo data-fecha-default
     */
    private function extractPageDate(string $html): ?string
    {
        try {
            $dom = new \DOMDocument();
            libxml_use_internal_errors(true);
            $dom->loadHTML($html);
            $xpath = new \DOMXPath($dom);
            
            // Buscar el elemento con data-fecha-default
            $dateElements = $xpath->query('//*[@data-fecha-default]');
            
            foreach ($dateElements as $element) {
                $dateValue = $element->getAttribute('data-fecha-default');
                if ($dateValue && preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateValue)) {
                    return $dateValue;
                }
            }
            
            return null;
        } catch (\Exception $e) {
            $this->log('Error extrayendo fecha de página: ' . $e->getMessage(), 'error');
            return null;
        }
    }
    
    
    /**
     * Convierte fecha de formato DD/MM/YYYY a YYYY-MM-DD
     */
    private function convertDateFormat(string $date): string
    {
        try {
            $parts = explode('/', $date);
            if (count($parts) === 3) {
                return $parts[2] . '-' . $parts[1] . '-' . $parts[0];
            }
        } catch (\Exception $e) {
            $this->log('Error convirtiendo formato de fecha: ' . $e->getMessage(), 'error');
        }
        
        return date('Y-m-d');
    }
    
    /**
     * Obtiene el tiempo actual
     */
    private function getCurrentTime(): string
    {
        try {
            if (function_exists('now')) {
                return now()->format('Y-m-d H:i:s');
            } else {
                return date('Y-m-d H:i:s');
            }
        } catch (\Exception $e) {
            return date('Y-m-d H:i:s');
        }
    }
}
