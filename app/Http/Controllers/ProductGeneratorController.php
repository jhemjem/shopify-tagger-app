<?php

namespace App\Http\Controllers;

use App\Services\ShopifyService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class ProductGeneratorController extends Controller
{
    public function __construct(
        private ShopifyService $shopify
    ) {}

    /**
     * Show the product generator page
     */
    public function index()
    {
        return Inertia::render('ProductGenerator/Index');
    }

    /**
     * Generate bulk products
     */
    public function generate(Request $request)
    {
        $request->validate([
            'count' => 'required|integer|min:1|max:250',
            'product_type' => 'nullable|string',
            'vendor' => 'nullable|string',
        ]);

        $count = $request->input('count');
        $productType = $request->input('product_type', 'Test Product');
        $vendor = $request->input('vendor', 'Test Vendor');

        $productTypes = ['T-Shirt', 'Hoodie', 'Jacket', 'Pants', 'Shoes', 'Hat', 'Bag', 'Accessory'];
        $colors = ['Red', 'Blue', 'Green', 'Black', 'White', 'Yellow', 'Purple', 'Orange', 'Pink', 'Gray'];
        $sizes = ['XS', 'S', 'M', 'L', 'XL', 'XXL'];
        $materials = ['Cotton', 'Polyester', 'Wool', 'Leather', 'Denim', 'Silk'];

        $created = 0;
        $failed = 0;
        $errors = [];

        for ($i = 1; $i <= $count; $i++) {
            try {
                $type = $productTypes[array_rand($productTypes)];
                $color = $colors[array_rand($colors)];
                $material = $materials[array_rand($materials)];
                
                $title = "{$color} {$material} {$type} #{$i}";
                $description = "High-quality {$material} {$type} in {$color}. Perfect for everyday wear.";
                $price = rand(1999, 9999) / 100; // Random price between $19.99 and $99.99

                $result = $this->createProduct([
                    'title' => $title,
                    'description' => $description,
                    'product_type' => $productType,
                    'vendor' => $vendor,
                    'price' => $price,
                    'tags' => [$type, $color, $material, 'Test Data'],
                    'color' => strtolower($color),
                ]);

                if ($result['success']) {
                    $created++;
                } else {
                    $failed++;
                    $errors[] = "Product #{$i}: {$result['message']}";
                }

                // Rate limiting: sleep briefly between requests
                if ($i % 10 === 0) {
                    sleep(1);
                }

            } catch (\Exception $e) {
                $failed++;
                $errors[] = "Product #{$i}: {$e->getMessage()}";
            }
        }

        return response()->json([
            'success' => true,
            'stats' => [
                'requested' => $count,
                'created' => $created,
                'failed' => $failed,
            ],
            'errors' => array_slice($errors, 0, 10), // Return first 10 errors
        ]);
    }

    /**
     * Create a single product via GraphQL (simplified)
     */
    private function createProduct(array $data): array
    {
        $mutation = <<<GQL
        mutation(\$input: ProductInput!) {
            productCreate(input: \$input) {
                product {
                    id
                    title
                }
                userErrors {
                    field
                    message
                }
            }
        }
        GQL;

        $variables = [
            'input' => [
                'title' => $data['title'],
                'descriptionHtml' => $data['description'],
                'productType' => $data['product_type'],
                'vendor' => $data['vendor'],
                'tags' => $data['tags'],
            ],
        ];

        try {
            $response = $this->executeGraphQL($mutation, $variables);

            if (!empty($response['data']['productCreate']['userErrors'])) {
                $errors = $response['data']['productCreate']['userErrors'];
                return [
                    'success' => false,
                    'message' => $errors[0]['message'] ?? 'Unknown error',
                ];
            }

            if (isset($response['errors'])) {
                return [
                    'success' => false,
                    'message' => $response['errors'][0]['message'] ?? 'GraphQL error',
                ];
            }

            $productId = $response['data']['productCreate']['product']['id'] ?? null;

            if (!$productId) {
                return [
                    'success' => false,
                    'message' => 'Product created but no ID returned',
                ];
            }

            return [
                'success' => true,
                'product_id' => $productId,
            ];

        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage(),
            ];
        }
    }

    /**
     * Execute GraphQL request
     */
    private function executeGraphQL(string $query, array $variables = []): array
    {
        $client = new \GuzzleHttp\Client([
            'base_uri' => 'https://' . config('shopify.shop_domain') . '/admin/api/' . config('shopify.api_version') . '/',
            'headers' => [
                'X-Shopify-Access-Token' => config('shopify.access_token'),
                'Content-Type' => 'application/json',
            ],
            'timeout' => 30,
        ]);

        $response = $client->post('graphql.json', [
            'json' => [
                'query' => $query,
                'variables' => $variables,
            ],
        ]);

        return json_decode($response->getBody()->getContents(), true);
    }

    /**
     * Delete all test products
     */
    public function deleteTestProducts(Request $request)
    {
        // Search for products with "Test Data" tag
        $products = [];
        $pageInfo = null;
        $hasMore = true;

        while ($hasMore) {
            $response = $this->shopify->searchProducts(['vendor' => 'Test Store'], $pageInfo);
            
            $edges = $response['data']['products']['edges'] ?? [];
            $products = array_merge($products, $edges);
            
            $pageInfo = $response['data']['products']['pageInfo']['endCursor'] ?? null;
            $hasMore = $response['data']['products']['pageInfo']['hasNextPage'] ?? false;

            if (count($products) >= 250) {
                break;
            }
        }

        $deleted = 0;
        $failed = 0;

        foreach ($products as $edge) {
            $productId = $edge['node']['id'];
            
            try {
                $result = $this->deleteProduct($productId);
                if ($result['success']) {
                    $deleted++;
                } else {
                    $failed++;
                }

                // Rate limiting
                if ($deleted % 10 === 0) {
                    sleep(1);
                }

            } catch (\Exception $e) {
                $failed++;
            }
        }

        return response()->json([
            'success' => true,
            'stats' => [
                'deleted' => $deleted,
                'failed' => $failed,
            ],
        ]);
    }

    /**
     * Delete a single product
     */
    private function deleteProduct(string $productId): array
    {
        $mutation = <<<GQL
        mutation(\$input: ProductDeleteInput!) {
            productDelete(input: \$input) {
                deletedProductId
                userErrors {
                    field
                    message
                }
            }
        }
        GQL;

        $variables = [
            'input' => [
                'id' => $productId,
            ],
        ];

        try {
            $response = $this->executeGraphQL($mutation, $variables);

            if (!empty($response['data']['productDelete']['userErrors'])) {
                return ['success' => false];
            }

            return ['success' => true];

        } catch (\Exception $e) {
            return ['success' => false];
        }
    }
}
