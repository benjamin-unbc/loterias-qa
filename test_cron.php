<?php
// Script para probar que el cron funciona
$logFile = __DIR__ . '/storage/logs/cron_test.log';
$message = "[" . date('Y-m-d H:i:s') . "] Cron job ejecutado correctamente\n";
file_put_contents($logFile, $message, FILE_APPEND | LOCK_EX);

echo "Cron job ejecutado: " . date('Y-m-d H:i:s');
?>
