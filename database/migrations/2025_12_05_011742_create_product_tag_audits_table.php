<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('product_tag_audits', function (Blueprint $table) {
            $table->id();
            $table->string('product_id');
            $table->string('action'); // 'added', 'skipped', 'failed'
            $table->string('tag');
            $table->string('status'); // 'success', 'error'
            $table->text('error_message')->nullable();
            $table->timestamps();
            
            $table->index(['product_id', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('product_tag_audits');
    }
};
