<?php

namespace App\Console\Commands;

use App\Services\ArticleExtractorService;
use Illuminate\Console\Command;

class TestExtractor extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'test:extractor';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Test the article extractor service';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Probando el extractor de artículos...');
        
        $extractor = new ArticleExtractorService();
        $result = $extractor->extractArticle('https://www.notitimba.com/lots/');
        
        if ($result) {
            $this->info('✅ Extracción exitosa!');
            $this->line('Título: ' . ($result['title'] ?? 'N/A'));
            $this->line('Descripción: ' . substr($result['description'] ?? 'N/A', 0, 100) . '...');
            $this->line('Contenido tiene ' . strlen($result['content'] ?? '') . ' caracteres');
            
            // Verificar si hay tablas en el contenido
            if (isset($result['content']) && strpos($result['content'], '<table') !== false) {
                $this->info('✅ Se encontraron tablas en el contenido');
            } else {
                $this->warn('⚠️ No se encontraron tablas en el contenido');
            }
        } else {
            $this->error('❌ Error en la extracción');
        }
    }
}
