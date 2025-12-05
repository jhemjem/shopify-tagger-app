<?php

namespace App\Console\Commands;

use App\Services\ShopifyService;
use Illuminate\Console\Command;

class TestShopifyConnection extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'shopify:test';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Test Shopify API connection and credentials';

    /**
     * Execute the console command.
     */
    public function handle(ShopifyService $shopify): int
    {
        $this->info('Testing Shopify API connection...');
        $this->newLine();

        // Check configuration
        $this->info('Configuration:');
        $this->line('  Shop Domain: ' . config('shopify.shop_domain'));
        $this->line('  API Version: ' . config('shopify.api_version'));
        $this->line('  Access Token: ' . (config('shopify.access_token') ? '✓ Set' : '✗ Not set'));
        $this->newLine();

        if (!config('shopify.access_token')) {
            $this->error('Access token not configured. Please set SHOPIFY_ACCESS_TOKEN in .env');
            return self::FAILURE;
        }

        // Test collections endpoint
        try {
            $this->info('Fetching collections...');
            $collections = $shopify->getCollections();
            
            $this->info('✓ Successfully connected to Shopify!');
            $this->line('  Found ' . count($collections) . ' collections');
            
            if (count($collections) > 0) {
                $this->newLine();
                $this->info('Sample collections:');
                foreach (array_slice($collections, 0, 5) as $collection) {
                    $this->line('  - ' . $collection['title'] . ' (' . $collection['handle'] . ')');
                }
            }

            $this->newLine();

            // Test product search
            $this->info('Testing product search...');
            $response = $shopify->searchProducts(['keyword' => '']);
            $productCount = count($response['data']['products']['edges'] ?? []);
            
            $this->info('✓ Product search working!');
            $this->line('  Found ' . $productCount . ' products in first page');

            $this->newLine();
            $this->info('🎉 All tests passed! Your Shopify integration is ready.');

            return self::SUCCESS;

        } catch (\Exception $e) {
            $this->error('✗ Connection failed!');
            $this->error('Error: ' . $e->getMessage());
            $this->newLine();
            $this->warn('Troubleshooting tips:');
            $this->line('  1. Verify your SHOPIFY_SHOP_DOMAIN is correct (e.g., your-store.myshopify.com)');
            $this->line('  2. Check that SHOPIFY_ACCESS_TOKEN is valid');
            $this->line('  3. Ensure your app has read_products and write_products scopes');
            $this->line('  4. Check storage/logs/laravel.log for detailed errors');

            return self::FAILURE;
        }
    }
}
