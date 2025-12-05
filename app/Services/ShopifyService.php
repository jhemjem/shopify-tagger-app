<?php

namespace App\Services;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use Illuminate\Support\Facades\Log;

class ShopifyService
{
    private Client $client;
    private string $shopDomain;
    private string $accessToken;
    private string $apiVersion;

    public function __construct()
    {
        $this->shopDomain = config('shopify.shop_domain');
        $this->accessToken = config('shopify.access_token');
        $this->apiVersion = config('shopify.api_version');

        $this->client = new Client([
            'base_uri' => "https://{$this->shopDomain}/admin/api/{$this->apiVersion}/",
            'headers' => [
                'X-Shopify-Access-Token' => $this->accessToken,
                'Content-Type' => 'application/json',
            ],
            'timeout' => 30,
        ]);
    }

    /**
     * Search products with filters
     */
    public function searchProducts(array $filters, ?string $pageInfo = null): array
    {
        $query = $this->buildGraphQLQuery($filters);
        
        $graphql = <<<GQL
        query(\$query: String!, \$first: Int!, \$after: String) {
            products(query: \$query, first: \$first, after: \$after) {
                edges {
                    node {
                        id
                        title
                        tags
                    }
                    cursor
                }
                pageInfo {
                    hasNextPage
                    endCursor
                }
            }
        }
        GQL;

        $variables = [
            'query' => $query,
            'first' => 50,
            'after' => $pageInfo,
        ];

        return $this->graphqlRequest($graphql, $variables);
    }

    /**
     * Build GraphQL query string from filters
     */
    private function buildGraphQLQuery(array $filters): string
    {
        $conditions = [];

        if (!empty($filters['keyword'])) {
            $conditions[] = "title:*{$filters['keyword']}*";
        }

        if (!empty($filters['product_type'])) {
            $conditions[] = "product_type:'{$filters['product_type']}'";
        }

        if (!empty($filters['collection_id'])) {
            $conditions[] = "collection_id:{$filters['collection_id']}";
        }

        return implode(' AND ', $conditions) ?: '*';
    }

    /**
     * Get collections for dropdown
     */
    public function getCollections(): array
    {
        $graphql = <<<GQL
        query {
            collections(first: 250) {
                edges {
                    node {
                        id
                        title
                        handle
                    }
                }
            }
        }
        GQL;

        $response = $this->graphqlRequest($graphql);
        
        return array_map(function ($edge) {
            return [
                'id' => $edge['node']['id'],
                'title' => $edge['node']['title'],
                'handle' => $edge['node']['handle'],
            ];
        }, $response['data']['collections']['edges'] ?? []);
    }

    /**
     * Add tag to a product (idempotent)
     */
    public function addTagToProduct(string $productId, string $tag): array
    {
        // Get current tags
        $graphql = <<<GQL
        query(\$id: ID!) {
            product(id: \$id) {
                id
                tags
            }
        }
        GQL;

        try {
            $response = $this->graphqlRequest($graphql, ['id' => $productId]);
            $currentTags = $response['data']['product']['tags'] ?? [];

            // Check if tag already exists (case-insensitive)
            $tagLower = strtolower($tag);
            $hasTag = collect($currentTags)->contains(function ($existingTag) use ($tagLower) {
                return strtolower($existingTag) === $tagLower;
            });

            if ($hasTag) {
                return [
                    'success' => true,
                    'action' => 'skipped',
                    'message' => 'Tag already exists',
                ];
            }

            // Add the new tag
            $newTags = array_merge($currentTags, [$tag]);

            $mutation = <<<GQL
            mutation(\$input: ProductInput!) {
                productUpdate(input: \$input) {
                    product {
                        id
                        tags
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
                    'id' => $productId,
                    'tags' => $newTags,
                ],
            ];

            $response = $this->graphqlRequest($mutation, $variables);

            if (!empty($response['data']['productUpdate']['userErrors'])) {
                $errors = $response['data']['productUpdate']['userErrors'];
                throw new \Exception($errors[0]['message'] ?? 'Unknown error');
            }

            return [
                'success' => true,
                'action' => 'added',
                'message' => 'Tag added successfully',
            ];

        } catch (\Exception $e) {
            Log::error('Failed to add tag to product', [
                'product_id' => $productId,
                'tag' => $tag,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'action' => 'failed',
                'message' => $e->getMessage(),
            ];
        }
    }

    /**
     * Execute GraphQL request with retry logic
     */
    private function graphqlRequest(string $query, array $variables = [], int $retries = 3): array
    {
        $attempt = 0;

        while ($attempt < $retries) {
            try {
                $response = $this->client->post('graphql.json', [
                    'json' => [
                        'query' => $query,
                        'variables' => $variables,
                    ],
                ]);

                $data = json_decode($response->getBody()->getContents(), true);

                // Check for rate limit
                if (isset($data['extensions']['cost']['throttleStatus'])) {
                    $throttle = $data['extensions']['cost']['throttleStatus'];
                    if ($throttle['currentlyAvailable'] < 100) {
                        $restoreRate = $throttle['restoreRate'] ?? 50;
                        $sleepTime = max(1, (100 - $throttle['currentlyAvailable']) / $restoreRate);
                        Log::info("Rate limit approaching, sleeping for {$sleepTime}s");
                        sleep((int) $sleepTime);
                    }
                }

                return $data;

            } catch (RequestException $e) {
                $attempt++;
                
                if ($e->hasResponse()) {
                    $statusCode = $e->getResponse()->getStatusCode();
                    
                    // Handle rate limiting (429)
                    if ($statusCode === 429) {
                        $retryAfter = $e->getResponse()->getHeader('Retry-After')[0] ?? 2;
                        Log::warning("Rate limited, retrying after {$retryAfter}s");
                        sleep((int) $retryAfter);
                        continue;
                    }
                }

                if ($attempt >= $retries) {
                    throw $e;
                }

                // Exponential backoff
                sleep(pow(2, $attempt));
            }
        }

        throw new \Exception('Max retries exceeded');
    }
}
