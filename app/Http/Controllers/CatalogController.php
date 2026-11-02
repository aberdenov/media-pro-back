<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Services\Catalog\Taxonomy;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class CatalogController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $data = $request->validate([
            'q' => 'nullable|string|max:150', 'category' => 'nullable|string|in:'.implode(',', array_keys(Taxonomy::CATEGORIES)),
            'provider' => 'nullable|in:oasis,arte', 'in_stock' => 'nullable|boolean',
            'min_price' => 'nullable|numeric|min:0', 'max_price' => 'nullable|numeric|min:0',
            'sort' => 'nullable|in:recommended,price_asc,price_desc,newest',
            'page' => 'nullable|integer|min:1|max:100000', 'per_page' => 'nullable|integer|min:1|max:48',
        ]);
        $query = Product::where('active', true);
        if (! empty($data['q'])) {
            foreach (preg_split('/\s+/u', trim(mb_strtolower($data['q']))) as $term) {
                $term = str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $term);
                $query->whereRaw("search_text LIKE ? ESCAPE '\\'", ['%'.$term.'%']);
            }
        }
        if (! empty($data['category'])) {
            $query->where('category', $data['category']);
        }
        if (! empty($data['provider'])) {
            $query->where('provider', $data['provider']);
        }
        if (! empty($data['in_stock'])) {
            $query->where('stock', '>', 0);
        }
        if (isset($data['min_price'])) {
            $query->where('price', '>=', $data['min_price']);
        }
        if (isset($data['max_price'])) {
            $query->where('price', '<=', $data['max_price']);
        }
        match ($data['sort'] ?? 'recommended') {
            'price_asc' => $query->orderByRaw('price IS NULL')->orderBy('price'),
            'price_desc' => $query->orderByRaw('price IS NULL')->orderByDesc('price'),
            'newest' => $query->orderByDesc('id'),
            default => $query->orderByRaw('CASE WHEN stock > 0 THEN 0 ELSE 1 END')->orderByRaw('CASE WHEN price >= 1000 THEN 0 ELSE 1 END'),
        };
        $page = $query->orderBy('id')->paginate($data['per_page'] ?? 24)->withQueryString();

        return response()->json(['data' => $page->items(), 'meta' => [
            'total' => $page->total(), 'current_page' => $page->currentPage(),
            'last_page' => $page->lastPage(), 'per_page' => $page->perPage(),
        ]]);
    }

    public function show(int $product): JsonResponse
    {
        return response()->json(['data' => Product::where('active', true)->findOrFail($product)]);
    }

    public function meta(): JsonResponse
    {
        return response()->json(Cache::remember('catalog-meta', 60, function () {
            $counts = Product::where('active', true)->selectRaw('category, count(*) as total')->groupBy('category')->pluck('total', 'category');

            return [
                'total' => $counts->sum(),
                'categories' => collect(Taxonomy::CATEGORIES)->map(fn ($name, $slug) => ['slug' => $slug, 'name' => $name, 'count' => (int) ($counts[$slug] ?? 0)])->values()->all(),
                'updated_at' => Product::max('synced_at'),
            ];
        }));
    }

    public function featured(): JsonResponse
    {
        return response()->json(['data' => Cache::remember('catalog-featured', 60, function () {
            return collect(['drinkware', 'bags', 'office', 'apparel', 'tech', 'sets', 'leisure', 'accessories'])
                ->map(fn ($category) => Product::where('active', true)->where('category', $category)
                    ->where('stock', '>', 10)->whereBetween('price', [1500, 25000])
                    ->where('images', '!=', '[]')->orderByDesc('id')->first())
                ->filter()->values()->toArray();
        })]);
    }
}
