<?php

namespace App\Http\Controllers;

use App\Models\ProductTagAudit;
use App\Services\ShopifyService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class ProductTaggerController extends Controller
{
    public function __construct(
        private ShopifyService $shopify
    ) {}

    /**
     * Show the product tagger page
     */
    public function index()
    {
        return Inertia::render('ProductTagger/Index', [
            'collections' => $this->shopify->getCollections(),
        ]);
    }

    /**
     * Preview products matching filters
     */
    public function preview(Request $request)
    {
        $request->validate([
            'filters' => 'required|array',
            'filters.keyword' => 'nullable|string',
            'filters.product_type' => 'nullable|string',
            'filters.collection_id' => 'nullable|string',
        ]);

        $filters = $request->input('filters');
        $products = [];
        $totalCount = 0;
        $pageInfo = null;
        $hasMore = true;

        // Fetch all products to get accurate count
        while ($hasMore) {
            $response = $this->shopify->searchProducts($filters, $pageInfo);
            
            $edges = $response['data']['products']['edges'] ?? [];
            $products = array_merge($products, $edges);
            
            $pageInfo = $response['data']['products']['pageInfo']['endCursor'] ?? null;
            $hasMore = $response['data']['products']['pageInfo']['hasNextPage'] ?? false;

            // Limit preview to reasonable amount
            if (count($products) >= 1000) {
                break;
            }
        }

        $totalCount = count($products);
        
        // Return first 10 for preview
        $preview = array_slice(array_map(function ($edge) {
            return [
                'id' => $edge['node']['id'],
                'title' => $edge['node']['title'],
                'tags' => $edge['node']['tags'],
            ];
        }, $products), 0, 10);

        return response()->json([
            'count' => $totalCount,
            'products' => $preview,
        ]);
    }

    /**
     * Apply tag to all matching products
     */
    public function applyTag(Request $request)
    {
        $request->validate([
            'filters' => 'required|array',
            'filters.keyword' => 'nullable|string',
            'filters.product_type' => 'nullable|string',
            'filters.collection_id' => 'nullable|string',
            'tag' => 'required|string|max:255',
            'use_queue' => 'nullable|boolean',
        ]);

        $filters = $request->input('filters');
        $tag = trim($request->input('tag'));
        $useQueue = $request->input('use_queue', false);

        $products = [];
        $pageInfo = null;
        $hasMore = true;

        // Fetch all matching products
        while ($hasMore) {
            $response = $this->shopify->searchProducts($filters, $pageInfo);
            
            $edges = $response['data']['products']['edges'] ?? [];
            $products = array_merge($products, $edges);
            
            $pageInfo = $response['data']['products']['pageInfo']['endCursor'] ?? null;
            $hasMore = $response['data']['products']['pageInfo']['hasNextPage'] ?? false;
        }

        $stats = [
            'total' => count($products),
            'updated' => 0,
            'skipped' => 0,
            'failed' => 0,
        ];

        if ($useQueue) {
            // Dispatch jobs to queue
            foreach ($products as $edge) {
                $productId = $edge['node']['id'];
                \App\Jobs\ApplyProductTagJob::dispatch($productId, $tag);
            }

            return response()->json([
                'success' => true,
                'queued' => true,
                'stats' => [
                    'total' => count($products),
                    'message' => 'Jobs queued for processing',
                ],
            ]);
        }

        // Process synchronously
        foreach ($products as $edge) {
            $productId = $edge['node']['id'];
            $result = $this->shopify->addTagToProduct($productId, $tag);

            // Log to audit table
            ProductTagAudit::create([
                'product_id' => $productId,
                'action' => $result['action'],
                'tag' => $tag,
                'status' => $result['success'] ? 'success' : 'error',
                'error_message' => $result['success'] ? null : $result['message'],
            ]);

            // Update stats
            if ($result['action'] === 'added') {
                $stats['updated']++;
            } elseif ($result['action'] === 'skipped') {
                $stats['skipped']++;
            } else {
                $stats['failed']++;
            }
        }

        return response()->json([
            'success' => true,
            'stats' => $stats,
        ]);
    }

    /**
     * Get audit logs
     */
    public function auditLogs(Request $request)
    {
        $logs = ProductTagAudit::orderBy('created_at', 'desc')
            ->limit(100)
            ->get();

        return response()->json($logs);
    }

    /**
     * View all products
     */
    public function viewProducts(Request $request)
    {
        $products = [];
        $pageInfo = null;
        $hasMore = true;
        $limit = $request->input('limit', 50); // Default to 50 products

        // Fetch products
        $response = $this->shopify->searchProducts(['keyword' => ''], $pageInfo);
        $edges = $response['data']['products']['edges'] ?? [];
        
        $products = array_map(function ($edge) {
            return [
                'id' => $edge['node']['id'],
                'title' => $edge['node']['title'],
                'tags' => $edge['node']['tags'],
            ];
        }, $edges);

        return response()->json([
            'products' => $products,
            'count' => count($products),
        ]);
    }
}
