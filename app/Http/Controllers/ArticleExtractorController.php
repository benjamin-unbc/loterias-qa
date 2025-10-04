<?php

namespace App\Http\Controllers;

use App\Services\ArticleExtractorService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class ArticleExtractorController extends Controller
{
    protected ArticleExtractorService $extractorService;

    public function __construct(ArticleExtractorService $extractorService)
    {
        $this->extractorService = $extractorService;
    }

    /**
     * Extrae un artículo desde una URL
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function extractArticle(Request $request): JsonResponse
    {
        $request->validate([
            'url' => 'required|url'
        ]);

        $url = $request->input('url');
        $articleData = $this->extractorService->extractArticle($url);

        if (!$articleData) {
            return response()->json([
                'success' => false,
                'message' => 'Error al extraer el artículo'
            ], 500);
        }

        return response()->json([
            'success' => true,
            'data' => $articleData
        ]);
    }

    /**
     * Extrae solo el texto de un artículo
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function extractText(Request $request): JsonResponse
    {
        $request->validate([
            'url' => 'required|url'
        ]);

        $url = $request->input('url');
        $text = $this->extractorService->extractArticleText($url);

        if (!$text) {
            return response()->json([
                'success' => false,
                'message' => 'Error al extraer el texto del artículo'
            ], 500);
        }

        return response()->json([
            'success' => true,
            'text' => $text
        ]);
    }

    /**
     * Extrae metadatos de un artículo
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function extractMetadata(Request $request): JsonResponse
    {
        $request->validate([
            'url' => 'required|url'
        ]);

        $url = $request->input('url');
        $metadata = $this->extractorService->extractArticleMetadata($url);

        if (!$metadata) {
            return response()->json([
                'success' => false,
                'message' => 'Error al extraer los metadatos del artículo'
            ], 500);
        }

        return response()->json([
            'success' => true,
            'metadata' => $metadata
        ]);
    }

    /**
     * Extrae múltiples artículos
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function extractMultiple(Request $request): JsonResponse
    {
        $request->validate([
            'urls' => 'required|array|min:1',
            'urls.*' => 'url'
        ]);

        $urls = $request->input('urls');
        $results = $this->extractorService->extractMultipleArticles($urls);

        return response()->json([
            'success' => true,
            'results' => $results
        ]);
    }
}
