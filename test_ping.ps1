# Script de PowerShell para hacer ping al servidor de base de datos
# 
# USO: .\test_ping.ps1
# 
# Este script hace ping al servidor de MySQL para medir latencia de red

Write-Host "========================================" -ForegroundColor Cyan
Write-Host "TEST DE PING AL SERVIDOR DE BASE DE DATOS" -ForegroundColor Cyan
Write-Host "========================================" -ForegroundColor Cyan
Write-Host ""

# Leer la configuración del archivo .env
$envFile = ".\.env"
$dbHost = ""

if (Test-Path $envFile) {
    $envContent = Get-Content $envFile
    foreach ($line in $envContent) {
        if ($line -match "^DB_HOST=(.+)") {
            $dbHost = $matches[1].Trim()
            break
        }
    }
}

if ([string]::IsNullOrEmpty($dbHost)) {
    Write-Host "No se pudo encontrar DB_HOST en .env" -ForegroundColor Yellow
    Write-Host "Por favor, ingresa manualmente el host de la base de datos:" -ForegroundColor Yellow
    $dbHost = Read-Host "DB_HOST"
}

if ([string]::IsNullOrEmpty($dbHost)) {
    Write-Host "ERROR: No se especificó el host de la base de datos" -ForegroundColor Red
    exit 1
}

Write-Host "Servidor de BD: $dbHost" -ForegroundColor Green
Write-Host ""

# Hacer ping 10 veces
Write-Host "Haciendo ping al servidor..." -ForegroundColor Yellow
Write-Host ""

$pingResults = @()
for ($i = 1; $i -le 10; $i++) {
    $ping = Test-Connection -ComputerName $dbHost -Count 1 -ErrorAction SilentlyContinue
    if ($ping) {
        $time = $ping.ResponseTime
        $pingResults += $time
        Write-Host "  Ping $i : $time ms" -ForegroundColor White
    } else {
        Write-Host "  Ping $i : FALLIDO" -ForegroundColor Red
    }
    Start-Sleep -Milliseconds 500
}

if ($pingResults.Count -gt 0) {
    $avg = ($pingResults | Measure-Object -Average).Average
    $min = ($pingResults | Measure-Object -Minimum).Minimum
    $max = ($pingResults | Measure-Object -Maximum).Maximum
    
    Write-Host ""
    Write-Host "----------------------------------------" -ForegroundColor Cyan
    Write-Host "RESUMEN" -ForegroundColor Cyan
    Write-Host "----------------------------------------" -ForegroundColor Cyan
    Write-Host "  Promedio: $([math]::Round($avg, 2)) ms" -ForegroundColor Green
    Write-Host "  Mínimo:   $min ms" -ForegroundColor Green
    Write-Host "  Máximo:   $max ms" -ForegroundColor Green
    Write-Host ""
    
    if ($avg -lt 10) {
        Write-Host "  Estado: EXCELENTE - Latencia muy baja (servidor local o misma red)" -ForegroundColor Green
    } elseif ($avg -lt 50) {
        Write-Host "  Estado: BUENO - Latencia aceptable (misma región)" -ForegroundColor Yellow
    } elseif ($avg -lt 100) {
        Write-Host "  Estado: REGULAR - Latencia moderada (diferente región)" -ForegroundColor Yellow
    } else {
        Write-Host "  Estado: ALTA - Latencia significativa (puede afectar rendimiento)" -ForegroundColor Red
    }
} else {
    Write-Host ""
    Write-Host "ERROR: No se pudo hacer ping al servidor" -ForegroundColor Red
    Write-Host "Verifica que el servidor esté accesible y que el firewall permita ICMP" -ForegroundColor Yellow
}

Write-Host ""

