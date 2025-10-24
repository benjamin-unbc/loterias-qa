<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;

class TucumanWinningNumbersService
{
    /**
     * Extrae los 20 números ganadores de Tucumán desde laquinieladetucuman.com.ar
     *
     * @return array|null Los números ganadores o null si hay error
     */
    public function extractWinningNumbers(): ?array
    {
        try {
            $this->log('Iniciando extracción de números ganadores para Tucumán desde laquinieladetucuman.com.ar');
            
            $url = 'https://www.laquinieladetucuman.com.ar/';
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
                    'city' => 'Tucumán',
                    'turns' => [],
                    'extracted_at' => $this->getCurrentTime(),
                    'url' => $url,
                    'skipped' => true,
                    'reason' => 'Fecha de página anterior detectada'
                ];
            }
            
            // Parsear los números ganadores
            return $this->parseWinningNumbers($html);
            
        } catch (\Exception $e) {
            $this->log('Error en extractWinningNumbers: ' . $e->getMessage(), 'error');
            return null;
        }
    }
    
    /**
     * Parsea los números ganadores del HTML
     */
    private function parseWinningNumbers(string $html): ?array
    {
        try {
            $dom = new \DOMDocument();
            libxml_use_internal_errors(true);
            $dom->loadHTML($html);
            $xpath = new \DOMXPath($dom);
            
            $turnsData = [];
            
            // Mapeo de turnos de la web a nuestro sistema
            $turnMapping = [
                'QUINIELA MATUTINA' => 'La Previa',      // 11:30 hs
                'QUINIELA VESPERTINA' => 'Primera',      // 14:30 hs
                'QUINIELA DE LA SIESTA' => 'Matutina',   // 17:30 hs
                'QUINIELA DE LA TARDE' => 'Vespertina',  // 19:30 hs
                'QUINIELA NOCTURNA' => 'Nocturna'        // 22:00 hs
            ];
            
            // Buscar todas las tablas de resultados con diferentes selectores
            $allTables = $xpath->query("//table[contains(@class, 'table-resultado')] | //table[contains(@class, 'resultado')] | //table[contains(@class, 'tabla')] | //table");
            $this->log("Encontradas " . $allTables->length . " tablas de resultados");
            
            // Si no encontramos tablas con las clases específicas, buscar por contenido
            if ($allTables->length === 0) {
                $this->log("No se encontraron tablas con clases específicas, buscando por contenido...");
                $allTables = $xpath->query("//table");
                $this->log("Encontradas " . $allTables->length . " tablas en total");
            }
            
            // Procesar cada tabla en orden
            $tableIndex = 0;
            foreach ($turnMapping as $webTurnName => $ourTurnName) {
                if ($tableIndex < $allTables->length) {
                    $table = $allTables->item($tableIndex);
                    $numbers = $this->extractNumbersFromResultTable($xpath, $table);
                    $turnsData[$ourTurnName] = $numbers;
                    $this->log("Turno {$ourTurnName} ({$webTurnName}): " . count($numbers) . " números extraídos");
                    $tableIndex++;
                } else {
                    $turnsData[$ourTurnName] = [];
                    $this->log("Turno {$ourTurnName} ({$webTurnName}): No hay tabla disponible");
                }
            }
            
            // Si no se extrajeron números de ningún turno, intentar método alternativo
            $totalNumbers = array_sum(array_map('count', $turnsData));
            if ($totalNumbers === 0) {
                $this->log("No se extrajeron números con el método principal, intentando método alternativo...");
                $turnsData = $this->extractNumbersAlternativeMethod($xpath);
            }
            
            return [
                'city' => 'Tucumán',
                'turns' => $turnsData,
                'extracted_at' => $this->getCurrentTime(),
                'url' => 'https://www.laquinieladetucuman.com.ar/'
            ];
            
        } catch (\Exception $e) {
            $this->log('Error parseando números ganadores: ' . $e->getMessage(), 'error');
            return null;
        }
    }
    
    /**
     * Método alternativo para extraer números cuando el método principal falla
     */
    private function extractNumbersAlternativeMethod(\DOMXPath $xpath): array
    {
        $turnsData = [];
        
        try {
            $this->log("Intentando método alternativo de extracción...");
            
            // Buscar todos los elementos que contengan números de 4 dígitos
            $numberElements = $xpath->query("//*[contains(text(), '7') or contains(text(), '8') or contains(text(), '9') or contains(text(), '0') or contains(text(), '1') or contains(text(), '2') or contains(text(), '3') or contains(text(), '4') or contains(text(), '5') or contains(text(), '6')]");
            
            $allNumbers = [];
            foreach ($numberElements as $element) {
                $text = trim($element->textContent);
                // Buscar números de 4 dígitos
                if (preg_match_all('/\b\d{4}\b/', $text, $matches)) {
                    foreach ($matches[0] as $number) {
                        if (strlen($number) === 4 && is_numeric($number)) {
                            $allNumbers[] = $number;
                        }
                    }
                }
            }
            
            // Eliminar duplicados y tomar los primeros 20
            $uniqueNumbers = array_unique($allNumbers);
            $numbers = array_slice($uniqueNumbers, 0, 20);
            
            if (!empty($numbers)) {
                $this->log("Método alternativo encontró " . count($numbers) . " números");
                // Asignar todos los números a "La Previa" como fallback
                $turnsData['La Previa'] = $numbers;
                $turnsData['Primera'] = [];
                $turnsData['Matutina'] = [];
                $turnsData['Vespertina'] = [];
                $turnsData['Nocturna'] = [];
            } else {
                $this->log("Método alternativo no encontró números");
                $turnsData = [
                    'La Previa' => [],
                    'Primera' => [],
                    'Matutina' => [],
                    'Vespertina' => [],
                    'Nocturna' => []
                ];
            }
            
        } catch (\Exception $e) {
            $this->log('Error en método alternativo: ' . $e->getMessage(), 'error');
            $turnsData = [
                'La Previa' => [],
                'Primera' => [],
                'Matutina' => [],
                'Vespertina' => [],
                'Nocturna' => []
            ];
        }
        
        return $turnsData;
    }
    
    /**
     * Extrae números de un turno específico
     */
    private function extractNumbersFromTurn(\DOMXPath $xpath, string $turnName): array
    {
        $numbers = [];
        
        try {
            $this->log("Buscando turno: {$turnName}");
            
            // Buscar todas las tablas con clase table-resultado
            $allTables = $xpath->query("//table[contains(@class, 'table-resultado')]");
            $this->log("Encontradas " . $allTables->length . " tablas con clase table-resultado en total");
            
            // Buscar la sección que contiene el turno específico
            $turnSections = $xpath->query("//*[contains(text(), '{$turnName}')]");
            
            if ($turnSections->length === 0) {
                $this->log("No se encontró sección para turno: {$turnName}");
                return [];
            }
            
            $this->log("Encontradas " . $turnSections->length . " secciones para {$turnName}");
            
            // Buscar la tabla de resultados específica para este turno
            $targetTable = null;
            foreach ($turnSections as $section) {
                $this->log("Procesando sección para {$turnName}");
                
                // Buscar la tabla de resultados más cercana
                $table = $this->findResultTable($xpath, $section);
                
                if ($table) {
                    $this->log("Tabla de resultados encontrada para {$turnName}");
                    $targetTable = $table;
                    break; // Usar la primera tabla encontrada
                }
            }
            
            // Si no encontramos tabla específica, usar la primera tabla disponible
            if (!$targetTable && $allTables->length > 0) {
                $this->log("Usando primera tabla disponible para {$turnName}");
                $targetTable = $allTables->item(0);
            }
            
            if ($targetTable) {
                $numbers = $this->extractNumbersFromResultTable($xpath, $targetTable);
            }
            
            // Limitar a 20 números máximo
            $numbers = array_slice($numbers, 0, 20);
            
            $this->log("Números extraídos para {$turnName}: " . count($numbers) . " números");
            if (!empty($numbers)) {
                $this->log("Primeros 5 números: " . implode(', ', array_slice($numbers, 0, 5)));
            }
            
        } catch (\Exception $e) {
            $this->log("Error extrayendo números para turno {$turnName}: " . $e->getMessage(), 'error');
        }
        
        return $numbers;
    }
    
    /**
     * Encuentra la tabla de resultados más cercana a una sección
     */
    private function findResultTable(\DOMXPath $xpath, $section): ?\DOMElement
    {
        try {
            $this->log("Buscando tabla de resultados...");
            
            // Buscar en el elemento actual y sus hermanos
            $current = $section;
            $attempts = 0;
            $maxAttempts = 15;
            
            while ($current && $attempts < $maxAttempts) {
                $this->log("Intento {$attempts}: Buscando tabla en elemento " . $current->nodeName);
                
                // Buscar tabla con clase table-resultado
                $tables = $xpath->query('.//table[contains(@class, "table-resultado")]', $current);
                $this->log("Encontradas " . $tables->length . " tablas con clase table-resultado");
                
                if ($tables->length > 0) {
                    $this->log("✅ Tabla de resultados encontrada!");
                    return $tables->item(0);
                }
                
                // Buscar en el siguiente hermano
                $current = $current->nextSibling;
                while ($current && $current->nodeType !== XML_ELEMENT_NODE) {
                    $current = $current->nextSibling;
                }
                
                $attempts++;
            }
            
            $this->log("❌ No se encontró tabla de resultados después de {$maxAttempts} intentos");
            return null;
        } catch (\Exception $e) {
            $this->log("Error buscando tabla de resultados: " . $e->getMessage(), 'error');
            return null;
        }
    }
    
    /**
     * Extrae números de una tabla de resultados específica
     */
    private function extractNumbersFromResultTable(\DOMXPath $xpath, $table): array
    {
        $numbers = [];
        
        try {
            // Verificar si la tabla tiene el mensaje "Aún no hay resultados"
            $noResults = $xpath->query('.//*[contains(text(), "Aún no hay resultados")]', $table);
            if ($noResults->length > 0) {
                $this->log("Tabla contiene mensaje 'Aún no hay resultados'");
                return [];
            }
            
            // Extraer números en el orden correcto leyendo fila por fila
            $rows = $xpath->query('.//tr', $table);
            $this->log("Encontradas " . $rows->length . " filas en la tabla");
            
            foreach ($rows as $row) {
                $cells = $xpath->query('.//td', $row);
                
                // Cada fila tiene 4 celdas: posición1, número1, posición2, número2
                if ($cells->length >= 4) {
                    $position1 = trim($cells->item(0)->textContent);
                    $number1 = trim($cells->item(1)->textContent);
                    $position2 = trim($cells->item(2)->textContent);
                    $number2 = trim($cells->item(3)->textContent);
                    
                    // Verificar que sean números de 4 dígitos
                    if (preg_match('/^\d{4}$/', $number1) && preg_match('/^\d+$/', $position1)) {
                        $pos1 = intval($position1);
                        if ($pos1 >= 1 && $pos1 <= 20 && !isset($numbers[$pos1 - 1])) {
                            $numbers[$pos1 - 1] = $number1;
                            $this->log("Número en posición {$pos1}: {$number1}");
                        }
                    }
                    
                    if (preg_match('/^\d{4}$/', $number2) && preg_match('/^\d+$/', $position2)) {
                        $pos2 = intval($position2);
                        if ($pos2 >= 1 && $pos2 <= 20 && !isset($numbers[$pos2 - 1])) {
                            $numbers[$pos2 - 1] = $number2;
                            $this->log("Número en posición {$pos2}: {$number2}");
                        }
                    }
                }
            }
            
            // Si no encontramos números con el método de filas, usar método alternativo
            if (empty($numbers)) {
                $this->log("No se encontraron números con método de filas, usando método alternativo");
                $cells = $xpath->query('.//td', $table);
                $this->log("Encontradas " . $cells->length . " celdas en la tabla");
                
                foreach ($cells as $cell) {
                    $cellText = trim($cell->textContent);
                    if (preg_match('/^\d{4}$/', $cellText) && !in_array($cellText, $numbers)) {
                        $numbers[] = $cellText;
                        $this->log("Número extraído de celda: {$cellText}");
                    }
                }
            } else {
                // Reorganizar array para que tenga índices secuenciales manteniendo el orden correcto
                ksort($numbers);
                $numbers = array_values($numbers);
                
                $this->log("Números reorganizados en orden correcto: " . count($numbers) . " números");
                if (!empty($numbers)) {
                    $this->log("Primeros 5 números en orden: " . implode(', ', array_slice($numbers, 0, 5)));
                }
            }
            
        } catch (\Exception $e) {
            $this->log("Error extrayendo números de tabla de resultados: " . $e->getMessage(), 'error');
        }
        
        return $numbers;
    }
    
    /**
     * Extrae números de una sección específica
     */
    private function extractNumbersFromSection(\DOMXPath $xpath, $section, string $turnName): array
    {
        $numbers = [];
        
        try {
            // Buscar en el elemento actual y sus hermanos
            $current = $section;
            $attempts = 0;
            $maxAttempts = 20;
            
            while ($current && $attempts < $maxAttempts) {
                // Buscar números de 4 dígitos en el texto del elemento, excluyendo años
                $text = $current->textContent ?? '';
                $this->log("Texto en elemento: " . substr($text, 0, 500) . "...");
                
                // Buscar patrones de números de 4 dígitos, excluyendo años (1900-2100)
                if (preg_match_all('/\b(\d{4})\b/', $text, $matches)) {
                    foreach ($matches[1] as $number) {
                        // Excluir años y números que no parecen de quiniela
                        if ($number !== '----' && 
                            !in_array($number, $numbers) && 
                            !$this->isYear($number) &&
                            !$this->isTime($number) &&
                            !$this->isDate($number)) {
                            $numbers[] = $number;
                            $this->log("Número encontrado: {$number}");
                        }
                    }
                }
                
                // Buscar en elementos hijos específicos (td, span, div con números)
                $childNumbers = $xpath->query('.//td | .//span | .//div | .//p | .//strong | .//b', $current);
                foreach ($childNumbers as $child) {
                    $childText = trim($child->textContent);
                    if (preg_match('/^\d{4}$/', $childText) && 
                        $childText !== '----' && 
                        !in_array($childText, $numbers) &&
                        !$this->isYear($childText) &&
                        !$this->isTime($childText) &&
                        !$this->isDate($childText)) {
                        $numbers[] = $childText;
                        $this->log("Número encontrado en hijo: {$childText}");
                    }
                }
                
                // Buscar específicamente en tablas
                $tables = $xpath->query('.//table', $current);
                foreach ($tables as $table) {
                    $tableNumbers = $this->extractNumbersFromTable($xpath, $table);
                    $numbers = array_merge($numbers, $tableNumbers);
                }
                
                // Buscar en el siguiente hermano
                $current = $current->nextSibling;
                while ($current && $current->nodeType !== XML_ELEMENT_NODE) {
                    $current = $current->nextSibling;
                }
                
                $attempts++;
                
                // Si ya tenemos suficientes números, salir
                if (count($numbers) >= 20) {
                    break;
                }
            }
            
        } catch (\Exception $e) {
            $this->log("Error extrayendo números de sección para {$turnName}: " . $e->getMessage(), 'error');
        }
        
        return $numbers;
    }
    
    /**
     * Extrae números de una tabla específica
     */
    private function extractNumbersFromTable(\DOMXPath $xpath, $table): array
    {
        $numbers = [];
        
        try {
            // Buscar todas las celdas de la tabla
            $cells = $xpath->query('.//td | .//th', $table);
            
            foreach ($cells as $cell) {
                $cellText = trim($cell->textContent);
                
                // Buscar números de 4 dígitos en la celda
                if (preg_match_all('/\b(\d{4})\b/', $cellText, $matches)) {
                    foreach ($matches[1] as $number) {
                        if ($number !== '----' && 
                            !in_array($number, $numbers) &&
                            !$this->isYear($number) &&
                            !$this->isTime($number) &&
                            !$this->isDate($number)) {
                            $numbers[] = $number;
                            $this->log("Número encontrado en tabla: {$number}");
                        }
                    }
                }
            }
            
        } catch (\Exception $e) {
            $this->log("Error extrayendo números de tabla: " . $e->getMessage(), 'error');
        }
        
        return $numbers;
    }
    
    /**
     * Verifica si un número de 4 dígitos es un año
     */
    private function isYear(string $number): bool
    {
        $year = intval($number);
        return $year >= 1900 && $year <= 2100;
    }
    
    /**
     * Verifica si un número de 4 dígitos es una hora
     */
    private function isTime(string $number): bool
    {
        $hour = intval(substr($number, 0, 2));
        $minute = intval(substr($number, 2, 2));
        return $hour >= 0 && $hour <= 23 && $minute >= 0 && $minute <= 59;
    }
    
    /**
     * Verifica si un número de 4 dígitos es una fecha (DDMM)
     */
    private function isDate(string $number): bool
    {
        $day = intval(substr($number, 0, 2));
        $month = intval(substr($number, 2, 2));
        return $day >= 1 && $day <= 31 && $month >= 1 && $month <= 12;
    }
    
    /**
     * Verifica si la página muestra números del día actual
     */
    private function isPageDateCurrent(string $html): bool
    {
        try {
            $currentDate = date('Y-m-d');
            $this->log("Fecha actual del sistema: $currentDate");
            
            // Extraer la fecha de la página
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
            
            // Si no se puede extraer la fecha de la página, proceder con precaución
            $this->log("❌ No se pudo extraer fecha de la página. Procediendo con extracción.");
            return true;
            
        } catch (\Exception $e) {
            $this->log('Error verificando fecha de página: ' . $e->getMessage(), 'error');
            // En caso de error, proceder con precaución
            $this->log("❌ Error en verificación de fecha. Procediendo con extracción.");
            return true;
        }
    }
    
    /**
     * Extrae la fecha de la página
     */
    private function extractPageDate(string $html): ?string
    {
        try {
            $dom = new \DOMDocument();
            libxml_use_internal_errors(true);
            $dom->loadHTML($html);
            $xpath = new \DOMXPath($dom);
            
            // Buscar texto que contenga "HOY" seguido de fecha
            $dateElements = $xpath->query("//*[contains(text(), 'HOY')]");
            
            foreach ($dateElements as $element) {
                $text = $element->textContent;
                $this->log("Texto encontrado: " . $text);
                
                // Buscar patrón de fecha en formato "HOY Sábado, 11 de Octubre de 2025"
                if (preg_match('/HOY\s+\w+,\s+(\d{1,2})\s+de\s+(\w+)\s+de\s+(\d{4})/', $text, $matches)) {
                    $day = str_pad($matches[1], 2, '0', STR_PAD_LEFT);
                    $month = $this->convertMonthName($matches[2]);
                    $year = $matches[3];
                    
                    if ($month) {
                        $date = "$year-$month-$day";
                        $this->log("Fecha extraída: $date");
                        return $date;
                    }
                }
            }
            
            return null;
        } catch (\Exception $e) {
            $this->log('Error extrayendo fecha de página: ' . $e->getMessage(), 'error');
            return null;
        }
    }
    
    /**
     * Convierte nombre de mes en español a número
     */
    private function convertMonthName(string $monthName): ?string
    {
        $months = [
            'enero' => '01', 'febrero' => '02', 'marzo' => '03', 'abril' => '04',
            'mayo' => '05', 'junio' => '06', 'julio' => '07', 'agosto' => '08',
            'septiembre' => '09', 'octubre' => '10', 'noviembre' => '11', 'diciembre' => '12'
        ];
        
        $monthName = strtolower(trim($monthName));
        return $months[$monthName] ?? null;
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
