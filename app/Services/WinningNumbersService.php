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
            
            // Delegar Tucumán al servicio especializado (maneja tanto con tilde como sin tilde)
            if ($city === 'Tucumán' || $city === 'Tucuman') {
                $this->log('Usando servicio especializado para Tucumán desde laquinieladetucuman.com.ar');
                $tucumanService = new \App\Services\TucumanWinningNumbersService();
                return $tucumanService->extractWinningNumbers();
            }
            
            // Mapear nombres de ciudades a URLs (vivitusuerte.com)
            $cityUrl = $this->getCityUrl($city);
            
            if (!$cityUrl) {
                $this->log("No se encontró URL para la ciudad: $city", 'warning');
                return null;
            }
            
            // Usar tomboleros.com para Tucumán, vivitusuerte.com para el resto
            if ($city === 'Tucumán') {
                $url = 'https://tomboleros.com' . $cityUrl;
            } else {
                $url = 'https://vivitusuerte.com' . $cityUrl;
            }
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
            if (!$this->isPageDateCurrent($html, $city)) {
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
        // Normalizar el nombre de la ciudad (convertir a formato correcto)
        $normalizedCity = $this->normalizeCityName($city);
        
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
            'Tucumán' => '/loteria-tucuman/quiniela', // Cambiado a tomboleros.com
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
        
        return $cityUrls[$normalizedCity] ?? null;
    }
    
    /**
     * Normaliza el nombre de la ciudad desde formato de BD a formato correcto
     */
    private function normalizeCityName(string $city): string
    {
        $normalizationMap = [
            'CIUDAD' => 'Ciudad',
            'SANTA FE' => 'Santa Fé',
            'PROVINCIA' => 'Provincia',
            'ENTRE RIOS' => 'Entre Ríos',
            'CORDOBA' => 'Córdoba',
            'CORRIENTES' => 'Corrientes',
            'CHACO' => 'Chaco',
            'NEUQUEN' => 'Neuquén',
            'MISIONES' => 'Misiones',
            'MENDOZA' => 'Mendoza',
            'RÍO NEGRO' => 'Río Negro',
            'TUCUMAN' => 'Tucumán',
            'SANTIAGO' => 'Santiago',
            'JUJUY' => 'Jujuy',
            'SALTA' => 'Salta',
            'MONTEVIDEO' => 'Montevideo',
            'SAN LUIS' => 'San Luis',
            'CHUBUT' => 'Chubut',
            'FORMOSA' => 'Formosa',
            'CATAMARCA' => 'Catamarca',
            'SAN JUAN' => 'San Juan'
        ];
        
        return $normalizationMap[$city] ?? $city;
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
            
            // Manejo especial para Tucumán con tomboleros.com
            if ($city === 'Tucumán') {
                return $this->parseTombolerosNumbers($xpath, $city);
            }
            
            // Definir los turnos a extraer según la ciudad
            if (in_array($city, ['Jujuy', 'Salta'])) {
                // Jujuy y Salta tienen Primera, Matutina, Vespertina y Nocturna
                $turns = ['Primera', 'Matutina', 'Vespertina', 'Nocturna'];
            } elseif (strtoupper($city) === 'MONTEVIDEO') {
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
                'url' => $city === 'Tucumán' ? 'https://tomboleros.com' . $this->getCityUrl($city) : 'https://vivitusuerte.com' . $this->getCityUrl($city)
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
            'CIUDAD' => [
                'La Previa' => 0,
                'Primera' => 2,  // Los números de Primera están en la tabla #2
                'Matutina' => 4, // Los números de Matutina están en la tabla #4
                'Vespertina' => 6,
                'Nocturna' => 8
            ],
            'Ciudad' => [
                'La Previa' => 0,
                'Primera' => 2,  // Los números de Primera están en la tabla #2
                'Matutina' => 4, // Los números de Matutina están en la tabla #4
                'Vespertina' => 6,
                'Nocturna' => 8
            ],
            'Montevideo' => [
                'La Previa' => null,  // No existe en Montevideo
                'Primera' => null,    // No existe en Montevideo
                'Matutina' => 4,      // Datos de la tabla "Matutina" de la web van a Vespertina (tabla #4)
                'Vespertina' => null, // No existe en Montevideo
                'Nocturna' => 8       // Datos de la tabla "Nocturna" de la web van a Nocturna (tabla #8)
            ],
            'MONTEVIDEO' => [
                'La Previa' => null,  // No existe en Montevideo
                'Primera' => null,    // No existe en Montevideo
                'Matutina' => 4,      // Datos de la tabla "Matutina" de la web van a Vespertina (tabla #4)
                'Vespertina' => null, // No existe en Montevideo
                'Nocturna' => 8       // Datos de la tabla "Nocturna" de la web van a Nocturna (tabla #8)
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
        // Verificar si hay una configuración personalizada en cache
        if (cache()->has('available_cities_override')) {
            return cache()->get('available_cities_override');
        }

        // Configuración por defecto (ciudades no ocultas)
        $hiddenCities = ['SAN LUIS', 'CHUBUT', 'FORMOSA', 'CATAMARCA', 'SAN JUAN'];
        
        return \App\Models\City::whereNotIn('name', $hiddenCities)
            ->select('name')
            ->distinct()
            ->pluck('name')
            ->toArray();
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
    private function isPageDateCurrent(string $html, string $city): bool
    {
        try {
            $currentDate = date('Y-m-d');
            $this->log("Fecha actual del sistema: $currentDate");
            
            // Extraer la fecha de la página según la fuente
            if ($city === 'Tucumán') {
                // Para tomboleros.com, extraer fechas de los H2
                $pageDate = $this->extractTombolerosDate($html);
            } else {
                // Para vivitusuerte.com, extraer fecha del atributo data-fecha-default
                $pageDate = $this->extractPageDate($html);
            }
            
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
            
            // Si no se puede extraer la fecha de la página, verificar si hay números válidos
            $this->log("❌ No se pudo extraer fecha de la página. Verificando si hay números válidos...");
            
            // Verificar si hay números de 4 dígitos en la página (indicador de que hay datos)
            if (preg_match_all('/\b\d{4}\b/', $html, $matches)) {
                $validNumbers = array_filter($matches[0], function($number) {
                    return $number !== '0000' && $number !== '----' && !$this->isYear($number);
                });
                
                if (count($validNumbers) >= 20) {
                    $this->log("✅ Se encontraron " . count($validNumbers) . " números válidos en la página. Procediendo con extracción (sin verificación de fecha).");
                    return true;
                }
            }
            
            $this->log("❌ No se encontraron suficientes números válidos. NO se extraerán números por seguridad.");
            return false;
            
        } catch (\Exception $e) {
            $this->log('Error verificando fecha de página: ' . $e->getMessage(), 'error');
            // En caso de error, NO proceder por seguridad
            $this->log("❌ Error en verificación de fecha. NO se extraerán números por seguridad.");
            return false;
        }
    }
    
    /**
     * Extrae la fecha de la página desde múltiples fuentes
     */
    private function extractPageDate(string $html): ?string
    {
        try {
            $dom = new \DOMDocument();
            libxml_use_internal_errors(true);
            $dom->loadHTML($html);
            $xpath = new \DOMXPath($dom);
            
            // Método 1: Buscar el elemento con data-fecha-default
            $dateElements = $xpath->query('//*[@data-fecha-default]');
            foreach ($dateElements as $element) {
                $dateValue = $element->getAttribute('data-fecha-default');
                if ($dateValue && preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateValue)) {
                    $this->log("Fecha encontrada en data-fecha-default: $dateValue");
                    return $dateValue;
                }
            }
            
            // Método 2: Buscar fechas en formato YYYY-MM-DD en el HTML
            if (preg_match('/\b(\d{4}-\d{2}-\d{2})\b/', $html, $matches)) {
                $this->log("Fecha encontrada en HTML (formato YYYY-MM-DD): " . $matches[1]);
                return $matches[1];
            }
            
            // Método 3: Buscar fechas en formato DD/MM/YYYY y convertir
            if (preg_match('/\b(\d{1,2}\/\d{1,2}\/\d{4})\b/', $html, $matches)) {
                $convertedDate = $this->convertDateFormat($matches[1]);
                $this->log("Fecha encontrada en HTML (formato DD/MM/YYYY): " . $matches[1] . " -> " . $convertedDate);
                return $convertedDate;
            }
            
            // Método 4: Buscar en elementos de fecha comunes
            $dateSelectors = [
                '//input[@type="date"]',
                '//input[@name*="fecha"]',
                '//input[@id*="fecha"]',
                '//span[contains(@class, "fecha")]',
                '//div[contains(@class, "fecha")]',
                '//*[contains(text(), "' . date('d/m/Y') . '")]',
                '//*[contains(text(), "' . date('Y-m-d') . '")]'
            ];
            
            foreach ($dateSelectors as $selector) {
                $elements = $xpath->query($selector);
                foreach ($elements as $element) {
                    $text = trim($element->textContent ?? $element->getAttribute('value') ?? '');
                    if ($text && preg_match('/\b(\d{4}-\d{2}-\d{2})\b/', $text, $matches)) {
                        $this->log("Fecha encontrada en selector '$selector': " . $matches[1]);
                        return $matches[1];
                    }
                    if ($text && preg_match('/\b(\d{1,2}\/\d{1,2}\/\d{4})\b/', $text, $matches)) {
                        $convertedDate = $this->convertDateFormat($matches[1]);
                        $this->log("Fecha encontrada en selector '$selector': " . $matches[1] . " -> " . $convertedDate);
                        return $convertedDate;
                    }
                }
            }
            
            $this->log("No se pudo extraer fecha de la página usando ningún método");
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
     * Parsea números específicamente de tomboleros.com para Tucumán
     */
    private function parseTombolerosNumbers(\DOMXPath $xpath, string $city): ?array
    {
        try {
            $this->log("Parseando números de tomboleros.com para: $city");
            
            $turnsData = [];
            
            // Mapeo de turnos de tomboleros.com a nuestro sistema
            $turnMapping = [
                'Matutina' => 'La Previa',      // 11:30h -> Previa (Tucu1130)
                'Vespertina' => 'Primera',      // 14:30h -> Primera (Tucu1430)
                'De la Siesta' => 'Matutina',   // 17:30h -> Matutina (Tucu1730)
                'De la Tarde' => 'Vespertina',  // 19:30h -> Vespertina (Tucu1930)
                'Nocturno' => 'Nocturna'        // 22:00h -> Nocturna (Tucu2200)
            ];
            
            // Buscar todas las tablas en la página
            $tables = $xpath->query('//table');
            $this->log("Encontradas " . $tables->length . " tablas en total");
            
            // Buscar TODOS los turnos disponibles en la página
            $this->log("Buscando todos los turnos disponibles en tomboleros.com...");
            
            // Buscar todos los H2 que contengan nombres de turnos
            $turnH2s = $xpath->query('//h2[contains(text(), "MATUTINA") or contains(text(), "VESPERTINA") or contains(text(), "SIESTA") or contains(text(), "TARDE") or contains(text(), "NOCTURNO")]');
            
            $this->log("Encontrados " . $turnH2s->length . " turnos en la página");
            
            // Buscar todas las tablas en la página
            $allTables = $xpath->query('//table');
            $this->log("Encontradas " . $allTables->length . " tablas en total");
            
            // Asociar cada H2 con su tabla correspondiente
            foreach ($turnH2s as $h2Index => $h2Element) {
                $h2Text = trim($h2Element->textContent);
                $this->log("Procesando turno: $h2Text");
                
                // Determinar qué turno es según el texto
                $tombolerosTurn = null;
                $ourTurn = null;
                
                if (strpos($h2Text, 'MATUTINA') !== false) {
                    $tombolerosTurn = 'Matutina';
                    $ourTurn = 'La Previa';      // Matutina de tomboleros = Previa en nuestro sistema
                } elseif (strpos($h2Text, 'VESPERTINA') !== false) {
                    $tombolerosTurn = 'Vespertina';
                    $ourTurn = 'Primera';        // Vespertina de tomboleros = Primera en nuestro sistema
                } elseif (strpos($h2Text, 'SIESTA') !== false) {
                    $tombolerosTurn = 'De la Siesta';
                    $ourTurn = 'Matutina';       // De la Siesta de tomboleros = Matutina en nuestro sistema
                } elseif (strpos($h2Text, 'TARDE') !== false) {
                    $tombolerosTurn = 'De la Tarde';
                    $ourTurn = 'Vespertina';     // De la Tarde de tomboleros = Vespertina en nuestro sistema
                } elseif (strpos($h2Text, 'NOCTURNO') !== false) {
                    $tombolerosTurn = 'Nocturno';
                    $ourTurn = 'Nocturna';       // Nocturno de tomboleros = Nocturna en nuestro sistema
                }
                
                if ($ourTurn) {
                    // Buscar la tabla que corresponde a este H2
                    // Usar el índice del H2 para encontrar la tabla correspondiente
                    if ($h2Index < $allTables->length) {
                        $table = $allTables->item($h2Index);
                        $this->log("Asociando tabla $h2Index con turno $tombolerosTurn");
                        
                        $numbers = $this->extractNumbersFromTombolerosTable($table, $xpath);
                        if (!empty($numbers)) {
                            $turnsData[$ourTurn] = $numbers;
                            $this->log("Extraídos " . count($numbers) . " números para turno: $ourTurn (desde $tombolerosTurn)");
                        }
                    } else {
                        $this->log("No hay tabla disponible para el turno $tombolerosTurn");
                    }
                }
            }
            
            // Si no encontramos la tabla específica, buscar en todas las tablas
            if (empty($turnsData)) {
                $this->log("No se encontró tabla específica, buscando en todas las tablas...");
                
                foreach ($tables as $index => $table) {
                    $numbers = $this->extractNumbersFromTombolerosTable($table, $xpath);
                    if (!empty($numbers)) {
                        $turnsData['La Previa'] = $numbers;
                        $this->log("Extraídos " . count($numbers) . " números de tabla $index para turno: La Previa");
                        break;
                    }
                }
            }
            
            return [
                'city' => $city,
                'turns' => $turnsData,
                'extracted_at' => $this->getCurrentTime(),
                'url' => 'https://tomboleros.com' . $this->getCityUrl($city)
            ];
            
        } catch (\Exception $e) {
            $this->log('Error parseando números de tomboleros.com: ' . $e->getMessage(), 'error');
            return null;
        }
    }
    
    /**
     * Extrae números de una tabla específica de tomboleros.com respetando el orden correcto de columnas
     */
    private function extractNumbersFromTombolerosTable(\DOMElement $table, \DOMXPath $xpath): array
    {
        $numbers = [];
        
        try {
            // Buscar todas las filas de la tabla
            $rows = $xpath->query('.//tr', $table);
            
            $leftColumnNumbers = [];  // Columna 1 (posiciones 1-10)
            $rightColumnNumbers = []; // Columna 3 (posiciones 11-20)
            
            foreach ($rows as $row) {
                // Buscar todas las celdas de la fila
                $cells = $xpath->query('.//td', $row);
                $cellIndex = 0;
                
                foreach ($cells as $cell) {
                    $text = trim($cell->textContent);
                    
                    // Verificar si es un número de lotería (3 o 4 dígitos)
                    if (preg_match('/^\d{3,4}$/', $text)) {
                        // Si tiene 3 dígitos, agregar 0 al inicio (números que comienzan con 0)
                        if (strlen($text) === 3) {
                            $text = '0' . $text;
                        }
                        
                        // Columna 1 (índice 1): números 1-10
                        if ($cellIndex == 1) {
                            $leftColumnNumbers[] = $text;
                        }
                        // Columna 3 (índice 3): números 11-20
                        elseif ($cellIndex == 3) {
                            $rightColumnNumbers[] = $text;
                        }
                    }
                    $cellIndex++;
                }
            }
            
            // Combinar: primero toda la columna izquierda, luego toda la columna derecha
            $numbers = array_merge($leftColumnNumbers, $rightColumnNumbers);
            
            // Si no encontramos números con el método de columnas, usar fallback
            if (empty($numbers)) {
                $elements = $xpath->query('.//td | .//span', $table);
                
                foreach ($elements as $element) {
                    $text = trim($element->textContent);
                    
                    if (preg_match('/^\d{3,4}$/', $text)) {
                        // Si tiene 3 dígitos, agregar 0 al inicio (números que comienzan con 0)
                        if (strlen($text) === 3) {
                            $text = '0' . $text;
                        }
                        $numbers[] = $text;
                    }
                }
            }
            
            // Filtrar números que no sean de lotería (como años 2025, etc.)
            $numbers = array_filter($numbers, function($num) {
                return $num !== '2025' && $num !== '7030'; // Filtrar años y otros números no relevantes
            });
            
            // Eliminar duplicados manteniendo el orden
            $numbers = array_unique($numbers);
            
            $this->log("Extraídos números de tabla (orden correcto por columnas): " . implode(', ', $numbers));
            $this->log("Total números extraídos: " . count($numbers));
            
        } catch (\Exception $e) {
            $this->log('Error extrayendo números de tabla tomboleros: ' . $e->getMessage(), 'error');
        }
        
        return array_values($numbers); // Reindexar array
    }

    /**
     * Extrae la fecha de los H2 en tomboleros.com
     */
    private function extractTombolerosDate(string $html): ?string
    {
        try {
            $dom = new \DOMDocument();
            libxml_use_internal_errors(true);
            $dom->loadHTML($html);
            $xpath = new \DOMXPath($dom);
            
            // Buscar H2 que contengan fechas
            $turnH2s = $xpath->query('//h2[contains(text(), "MATUTINA") or contains(text(), "VESPERTINA") or contains(text(), "SIESTA") or contains(text(), "TARDE") or contains(text(), "NOCTURNO")]');
            
            foreach ($turnH2s as $h2Element) {
                $h2Text = trim($h2Element->textContent);
                
                // Extraer fecha del H2 (formato: DD/MM/YYYY)
                if (preg_match('/(\d{2}\/\d{2}\/\d{4})/', $h2Text, $matches)) {
                    $dateArg = $matches[1]; // Formato DD/MM/YYYY
                    
                    // Convertir a formato YYYY-MM-DD
                    $dateParts = explode('/', $dateArg);
                    if (count($dateParts) === 3) {
                        $convertedDate = $dateParts[2] . '-' . $dateParts[1] . '-' . $dateParts[0];
                        $this->log("Fecha extraída de tomboleros.com: $dateArg → $convertedDate");
                        return $convertedDate;
                    }
                }
            }
            
            $this->log("No se encontró fecha en los H2 de tomboleros.com", 'warning');
            return null;
            
        } catch (\Exception $e) {
            $this->log('Error extrayendo fecha de tomboleros.com: ' . $e->getMessage(), 'error');
            return null;
        }
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
    
    /**
     * Verifica si un número es un año (1900-2100)
     */
    private function isYear(string $number): bool
    {
        $year = intval($number);
        return $year >= 1900 && $year <= 2100;
    }
}
