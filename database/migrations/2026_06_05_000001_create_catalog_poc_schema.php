<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('supplier_sources', function (Blueprint $table): void {
            $table->id();
            $table->string('code')->unique();
            $table->string('name');
            $table->string('connector')->nullable();
            $table->json('sync_rules')->nullable();
            $table->timestamps();
        });

        Schema::create('categories', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('parent_id')->nullable()->constrained('categories')->nullOnDelete();
            $table->string('slug')->unique();
            $table->string('name');
            $table->string('short_name');
            $table->string('path')->index();
            $table->unsignedSmallInteger('depth')->default(0);
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
        });

        Schema::create('products', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('category_id')->constrained();
            $table->foreignId('supplier_source_id')->nullable()->constrained()->nullOnDelete();
            $table->string('sku')->unique();
            $table->string('slug')->unique();
            $table->string('brand')->index();
            $table->string('name');
            $table->string('family')->index();
            $table->text('description')->nullable();
            $table->decimal('price', 10, 2);
            $table->decimal('compare_price', 10, 2)->nullable();
            $table->string('currency', 3)->default('EUR');
            $table->string('availability')->index();
            $table->string('delivery_text');
            $table->unsignedSmallInteger('delivery_days')->default(2);
            $table->unsignedInteger('stock_quantity')->default(0);
            $table->decimal('rating', 3, 2)->default(4.6);
            $table->unsignedInteger('review_count')->default(0);
            $table->string('image_path')->nullable();
            $table->text('search_keywords')->nullable();
            $table->json('specs')->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->timestamp('indexed_at')->nullable();
            $table->timestamps();
        });

        Schema::create('product_identifiers', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->string('type')->index();
            $table->string('value')->index();
            $table->string('normalized_value')->index();
            $table->timestamps();
            $table->unique(['product_id', 'type', 'normalized_value']);
        });

        Schema::create('product_compatible_models', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->string('model_number')->index();
            $table->string('model_family')->nullable()->index();
            $table->string('normalized_model_number')->index();
            $table->timestamps();
            $table->unique(['product_id', 'normalized_model_number']);
        });

        Schema::create('search_synonyms', function (Blueprint $table): void {
            $table->id();
            $table->string('term')->unique();
            $table->string('replacement');
            $table->unsignedSmallInteger('weight')->default(10);
            $table->timestamps();
        });

        Schema::create('search_events', function (Blueprint $table): void {
            $table->id();
            $table->string('query')->nullable();
            $table->string('normalized_query')->nullable()->index();
            $table->json('filters')->nullable();
            $table->unsignedInteger('result_count')->default(0);
            $table->boolean('cache_hit')->default(false);
            $table->timestamp('searched_at')->index();
        });

        Schema::create('search_index_runs', function (Blueprint $table): void {
            $table->id();
            $table->string('index_name');
            $table->string('alias_name')->default('products_current');
            $table->string('status')->index();
            $table->unsignedInteger('document_count')->default(0);
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('search_index_runs');
        Schema::dropIfExists('search_events');
        Schema::dropIfExists('search_synonyms');
        Schema::dropIfExists('product_compatible_models');
        Schema::dropIfExists('product_identifiers');
        Schema::dropIfExists('products');
        Schema::dropIfExists('categories');
        Schema::dropIfExists('supplier_sources');
    }
};
