<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\CmsPage;
use App\Models\Faq;
use Illuminate\Http\JsonResponse;

class ContentController extends Controller
{
    public function page(string $slug): JsonResponse
    {
        $page = CmsPage::query()->where('slug', $slug)->where('is_published', true)->firstOrFail();

        return response()->json(['data' => [
            'slug' => $page->slug,
            'title' => $page->title,
            'body' => $page->body,
            'updated_at' => $page->updated_at?->toIso8601String(),
        ]]);
    }

    public function faqs(): JsonResponse
    {
        $faqs = Faq::query()->where('is_published', true)->orderBy('category')->orderBy('sort')
            ->get(['id', 'category', 'question', 'answer']);

        return response()->json(['data' => $faqs]);
    }
}
