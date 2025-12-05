<?php

namespace App\Jobs;

use App\Models\ProductTagAudit;
use App\Services\ShopifyService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ApplyProductTagJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $timeout = 300; // 5 minutes
    public $tries = 3;

    /**
     * Create a new job instance.
     */
    public function __construct(
        public string $productId,
        public string $tag
    ) {}

    /**
     * Execute the job.
     */
    public function handle(ShopifyService $shopify): void
    {
        $result = $shopify->addTagToProduct($this->productId, $this->tag);

        // Log to audit table
        ProductTagAudit::create([
            'product_id' => $this->productId,
            'action' => $result['action'],
            'tag' => $this->tag,
            'status' => $result['success'] ? 'success' : 'error',
            'error_message' => $result['success'] ? null : $result['message'],
        ]);
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        ProductTagAudit::create([
            'product_id' => $this->productId,
            'action' => 'failed',
            'tag' => $this->tag,
            'status' => 'error',
            'error_message' => $exception->getMessage(),
        ]);
    }
}
