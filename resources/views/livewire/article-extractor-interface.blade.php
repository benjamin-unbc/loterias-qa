<div>
    <div class="max-w-7xl mx-auto py-6 sm:px-6 lg:px-8">
        <div class="px-4 py-6 sm:px-0">
            <!-- Header -->
            <div class="mb-8">
                <h1 class="text-3xl font-bold text-gray-900">Resultados de Quinielas</h1>
                <p class="mt-2 text-gray-600">Datos completos extraídos de notitimba.com</p>
            </div>

            <!-- Indicador de carga -->
            @if($loading)
                <div class="bg-white shadow rounded-lg p-8 mb-8">
                    <div class="flex items-center justify-center">
                        <div class="text-center">
                            <i class="fas fa-spinner fa-spin text-4xl text-blue-500 mb-4"></i>
                            <h3 class="text-xl font-medium text-gray-900">Extrayendo datos...</h3>
                            <p class="text-sm text-gray-600 mt-2">Procesando: {{ $url }}</p>
                        </div>
                    </div>
                </div>
            @endif

            <!-- Error -->
            @if($error)
                <div class="bg-white shadow rounded-lg p-6 mb-8">
                    <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded">
                        <div class="flex items-center">
                            <i class="fas fa-exclamation-triangle mr-2"></i>
                            <span class="font-medium">Error:</span>
                            <span class="ml-2">{{ $error }}</span>
                        </div>
                    </div>
                </div>
            @endif

            <!-- Todas las Tablas -->
            @if(!empty($allTablesData) && !$loading)
                <div class="space-y-8">
                    @foreach($allTablesData as $table)
                        <div class="bg-white shadow rounded-lg p-6">
                            <div class="flex items-center justify-between mb-6">
                                <h2 class="text-xl font-semibold text-gray-900">
                                    Tabla {{ $table['index'] }}
                                </h2>
                                <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium bg-blue-100 text-blue-800">
                                    <i class="fas fa-table mr-1"></i>
                                    {{ count($table['rows']) }} filas
                                </span>
                            </div>
                            
                            <div class="overflow-x-auto">
                                <table class="min-w-full divide-y divide-gray-200 border border-gray-300">
                                    @if(!empty($table['headers']))
                                        <thead class="bg-gray-50">
                                            <tr>
                                                @foreach($table['headers'] as $header)
                                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider border-r border-gray-200">
                                                        {{ $header }}
                                                    </th>
                                                @endforeach
                                            </tr>
                                        </thead>
                                    @endif
                                    <tbody class="bg-white divide-y divide-gray-200">
                                        @foreach($table['rows'] as $rowIndex => $row)
                                            <tr class="{{ $rowIndex % 2 === 0 ? 'bg-white' : 'bg-gray-50' }} hover:bg-blue-50">
                                                @foreach($row as $cellIndex => $cell)
                                                    <td class="px-4 py-3 text-sm text-gray-900 border-r border-gray-200 {{ $cellIndex === 0 ? 'font-medium bg-gray-100' : '' }}">
                                                        @if(is_numeric($cell) && strlen($cell) === 4)
                                                            <!-- Números de 4 dígitos con estilo especial -->
                                                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">
                                                                {{ $cell }}
                                                            </span>
                                                        @elseif(empty($cell))
                                                            <!-- Celdas vacías -->
                                                            <span class="text-gray-400">-</span>
                                                        @else
                                                            <!-- Texto normal -->
                                                            {{ $cell }}
                                                        @endif
                                                    </td>
                                                @endforeach
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                            
                            <!-- Información adicional de la tabla -->
                            <div class="mt-4 text-sm text-gray-600">
                                <div class="flex items-center space-x-4">
                                    <span><strong>Filas:</strong> {{ count($table['rows']) }}</span>
                                    @if(!empty($table['rows']))
                                        <span><strong>Columnas:</strong> {{ count($table['rows'][0]) }}</span>
                                    @endif
                                    <span><strong>Total celdas:</strong> {{ count($table['rows']) * (count($table['rows'][0] ?? [])) }}</span>
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>

                <!-- Resumen General -->
                <div class="bg-white shadow rounded-lg p-6 mt-8">
                    <h3 class="text-xl font-semibold text-gray-900 mb-4">Resumen General</h3>
                    
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                        <div class="bg-blue-50 rounded-lg p-4">
                            <div class="flex items-center">
                                <i class="fas fa-table text-blue-500 text-2xl mr-3"></i>
                                <div>
                                    <p class="text-sm font-medium text-blue-700">Total de Tablas</p>
                                    <p class="text-2xl font-bold text-blue-900">{{ count($allTablesData) }}</p>
                                </div>
                            </div>
                        </div>
                        
                        <div class="bg-green-50 rounded-lg p-4">
                            <div class="flex items-center">
                                <i class="fas fa-list text-green-500 text-2xl mr-3"></i>
                                <div>
                                    <p class="text-sm font-medium text-green-700">Total de Filas</p>
                                    <p class="text-2xl font-bold text-green-900">{{ array_sum(array_map(fn($table) => count($table['rows']), $allTablesData)) }}</p>
                                </div>
                            </div>
                        </div>
                        
                        <div class="bg-purple-50 rounded-lg p-4">
                            <div class="flex items-center">
                                <i class="fas fa-calculator text-purple-500 text-2xl mr-3"></i>
                                <div>
                                    <p class="text-sm font-medium text-purple-700">Total de Números</p>
                                    <p class="text-2xl font-bold text-purple-900">
                                        @php
                                            $totalNumbers = 0;
                                            foreach($allTablesData as $table) {
                                                foreach($table['rows'] as $row) {
                                                    foreach($row as $cell) {
                                                        if(is_numeric($cell) && strlen($cell) === 4) {
                                                            $totalNumbers++;
                                                        }
                                                    }
                                                }
                                            }
                                            echo $totalNumbers;
                                        @endphp
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            @endif

            <!-- Mensaje si no hay datos -->
            @if(empty($allTablesData) && !$loading && !$error)
                <div class="bg-white shadow rounded-lg p-8">
                    <div class="text-center">
                        <i class="fas fa-info-circle text-4xl text-gray-400 mb-4"></i>
                        <h3 class="text-lg font-medium text-gray-900 mb-2">No se encontraron datos</h3>
                        <p class="text-gray-600">Los datos de las quinielas no están disponibles en este momento.</p>
                    </div>
                </div>
            @endif
        </div>
    </div>
</div>