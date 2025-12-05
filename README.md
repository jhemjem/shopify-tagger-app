
# shopify-tagger-app
# Shopify Bulk Product Tagger - Setup Guide

## Overview
This feature allows you to filter Shopify products and bulk-add tags through an embedded UI built with Laravel, Inertia, React, and Polaris components.

## Features
✅ Filter by keyword, product type, and/or collection
✅ Preview matching products (count + first 10 titles)
✅ Bulk tag application with idempotent operations
✅ Rate limit handling with automatic retry/backoff
✅ Progress tracking and detailed summary
✅ Audit logging for all operations
✅ Optional queue/job processing for large batches

## Prerequisites
- PHP 8.2+
- Node.js 22+
- Composer
- A Shopify development store
- Shopify Admin API access token with scopes: `read_products`, `write_products`

## Installation Steps

### 1. Install Dependencies
Already completed during setup:
```bash
composer require shopify/shopify-api guzzlehttp/guzzle
npm install @shopify/polaris @shopify/app-bridge @shopify/app-bridge-react --legacy-peer-deps
```

### 2. Configure Environment Variables
Add these to your `.env` file:

```env
SHOPIFY_API_KEY=your_api_key
SHOPIFY_API_SECRET=your_api_secret
SHOPIFY_SHOP_DOMAIN=your-store.myshopify.com
SHOPIFY_ACCESS_TOKEN=your_access_token
SHOPIFY_API_VERSION=2024-10
```

### 3. Get Shopify Credentials

#### Option A: Create a Custom App (Recommended for Development)
1. Go to your Shopify Admin: `https://your-store.myshopify.com/admin`
2. Navigate to **Settings** → **Apps and sales channels** → **Develop apps**
3. Click **Create an app**
4. Name it (e.g., "Product Tagger")
5. Go to **Configuration** tab
6. Under **Admin API access scopes**, select:
   - `read_products`
   - `write_products`
7. Click **Save**
8. Go to **API credentials** tab
9. Click **Install app**
10. Copy the **Admin API access token** (this is your `SHOPIFY_ACCESS_TOKEN`)
11. Copy the **API key** (this is your `SHOPIFY_API_KEY`)
12. Copy the **API secret key** (this is your `SHOPIFY_API_SECRET`)

#### Option B: Use Existing App
If you already have a Shopify app, ensure it has the required scopes and use its credentials.

### 4. Run Database Migration
```bash
php artisan migrate
```

This creates the `product_tag_audits` table for logging.

### 5. Build Frontend Assets
```bash
npm run build
# or for development
npm run dev
```

### 6. Start the Application
```bash
# Development (with queue worker)
composer dev

# Or manually
php artisan serve
php artisan queue:work
npm run dev
```

## Usage

### Access the Feature
Navigate to: `http://localhost:8000/product-tagger`

### Workflow
1. **Set Filters** (optional, combine as needed):
   - **Keyword**: Search product titles (e.g., "Shirt")
   - **Product Type**: Filter by exact product type (e.g., "Apparel")
   - **Collection**: Select from dropdown

2. **Enter Tag**: Type the tag you want to add (e.g., "Free Ship")

3. **Preview**: Click "Preview Matches" to see:
   - Total count of matching products
   - First 10 product titles with existing tags

4. **Apply**: Click "Apply Tag" to bulk-add the tag
   - Shows progress and final summary
   - Safe to re-run (idempotent)

### Results Summary
After applying, you'll see:
- **Total products processed**
- **Updated**: Products that received the new tag
- **Skipped**: Products that already had the tag
- **Failed**: Products that encountered errors

## API Endpoints

### Preview Products
```
POST /product-tagger/preview
Content-Type: application/json

{
  "filters": {
    "keyword": "Shirt",
    "product_type": "Apparel",
    "collection_id": "gid://shopify/Collection/123"
  }
}
```

### Apply Tag
```
POST /product-tagger/apply-tag
Content-Type: application/json

{
  "filters": {
    "keyword": "Shirt"
  },
  "tag": "Free Ship",
  "use_queue": false
}
```

### Get Audit Logs
```
GET /product-tagger/audit-logs
```

## Queue Processing (Optional)

For large batches (1000+ products), use queue processing:

1. Ensure queue is configured in `.env`:
```env
QUEUE_CONNECTION=database
```

2. Run the queue worker:
```bash
php artisan queue:work
```

3. In the UI, the system will automatically queue jobs for processing

## Rate Limiting

The service automatically handles Shopify rate limits:
- Monitors GraphQL cost and throttle status
- Implements exponential backoff on 429 errors
- Respects `Retry-After` headers
- Logs rate limit events

## Audit Logging

All operations are logged to the `product_tag_audits` table:
- `product_id`: Shopify product GID
- `action`: `added`, `skipped`, or `failed`
- `tag`: The tag that was applied
- `status`: `success` or `error`
- `error_message`: Details if failed
- `created_at`: Timestamp

View logs via the API endpoint or directly in the database.

## Troubleshooting

### "Failed to fetch preview"
- Check your `.env` Shopify credentials
- Verify your access token has the required scopes
- Check Laravel logs: `storage/logs/laravel.log`

### Rate Limit Errors
- The system should handle these automatically
- If persistent, reduce batch size or increase delays

### No Products Found
- Verify filters match actual products in your store
- Check product type spelling (case-sensitive)
- Ensure collection ID is correct (use GraphQL ID format)

### Queue Jobs Not Processing
- Ensure queue worker is running: `php artisan queue:work`
- Check queue configuration in `.env`
- View failed jobs: `php artisan queue:failed`

## Testing

Test with a development store:
1. Create test products with various types and collections
2. Try different filter combinations
3. Verify tags are added correctly
4. Test with products that already have the tag
5. Monitor audit logs

## Support

For issues or questions:
1. Check Laravel logs: `storage/logs/laravel.log`
2. Check browser console for frontend errors
3. Review Shopify API documentation: https://shopify.dev/docs/api/admin-graphql
4. Check queue status: `php artisan queue:failed`
