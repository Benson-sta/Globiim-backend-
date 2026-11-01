<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('commerce_sellers', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('store_name', 120);
            $table->string('slug', 150)->unique();
            $table->string('seller_type', 30)->default('creator');
            $table->text('description')->nullable();
            $table->string('country_code', 2)->default('KE');
            $table->string('city', 100)->nullable();
            $table->string('locality', 120)->nullable();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->string('status', 20)->default('pending')->index();
            $table->boolean('verified')->default(false);
            $table->json('metadata')->nullable();
            $table->timestamps();
        });

        Schema::create('commerce_products', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('seller_id')->constrained('commerce_sellers')->cascadeOnDelete();
            $table->string('name', 180);
            $table->string('slug', 200)->unique();
            $table->string('sku', 80)->nullable()->index();
            $table->text('description')->nullable();
            $table->string('category', 100)->index();
            $table->string('product_type', 30)->default('physical');
            $table->decimal('price', 14, 2);
            $table->char('currency', 3)->default('KES');
            $table->unsignedInteger('stock_quantity')->default(0);
            $table->unsignedInteger('minimum_order_quantity')->default(1);
            $table->string('wholesale_terms', 160)->nullable();
            $table->string('status', 20)->default('draft')->index();
            $table->string('country_code', 2)->default('KE');
            $table->string('city', 100)->nullable();
            $table->boolean('featured')->default(false);
            $table->timestamp('published_at')->nullable();
            $table->timestamps();
            $table->index(['status', 'category', 'created_at']);
            $table->index(['city', 'status']);
        });

        Schema::create('commerce_product_media', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('product_id')->constrained('commerce_products')->cascadeOnDelete();
            $table->string('media_type', 20)->default('image');
            $table->string('url', 2048);
            $table->string('thumbnail_url', 2048)->nullable();
            $table->unsignedSmallInteger('position')->default(0);
            $table->timestamps();
        });

        Schema::create('commerce_carts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('status', 20)->default('active')->index();
            $table->timestamps();
            $table->index(['user_id', 'status']);
        });

        Schema::create('commerce_cart_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('cart_id')->constrained('commerce_carts')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained('commerce_products')->cascadeOnDelete();
            $table->unsignedInteger('quantity');
            $table->timestamps();
            $table->unique(['cart_id', 'product_id']);
        });

        Schema::create('commerce_orders', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('order_number', 40)->unique();
            $table->string('status', 24)->default('pending')->index();
            $table->string('payment_status', 24)->default('unpaid')->index();
            $table->char('currency', 3)->default('KES');
            $table->decimal('subtotal', 14, 2);
            $table->decimal('delivery_fee', 14, 2)->default(0);
            $table->decimal('total', 14, 2);
            $table->json('shipping_address')->nullable();
            $table->string('customer_note', 500)->nullable();
            $table->timestamps();
            $table->index(['user_id', 'created_at']);
        });

        Schema::create('commerce_order_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('order_id')->constrained('commerce_orders')->cascadeOnDelete();
            $table->foreignId('seller_id')->constrained('commerce_sellers')->cascadeOnDelete();
            $table->foreignId('product_id')->nullable()->constrained('commerce_products')->nullOnDelete();
            $table->string('product_name', 180);
            $table->unsignedInteger('quantity');
            $table->decimal('unit_price', 14, 2);
            $table->decimal('line_total', 14, 2);
            $table->timestamps();
        });

        Schema::create('commerce_quote_requests', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('seller_id')->constrained('commerce_sellers')->cascadeOnDelete();
            $table->foreignId('product_id')->nullable()->constrained('commerce_products')->nullOnDelete();
            $table->text('message');
            $table->string('status', 24)->default('submitted')->index();
            $table->json('response')->nullable();
            $table->timestamps();
            $table->index(['seller_id', 'created_at']);
        });

        Schema::create('commerce_business_requests', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('request_type', 40)->index();
            $table->string('title', 180);
            $table->text('description');
            $table->string('category', 100)->nullable();
            $table->string('country_code', 2)->default('KE');
            $table->string('city', 100)->nullable();
            $table->decimal('budget_min', 14, 2)->nullable();
            $table->decimal('budget_max', 14, 2)->nullable();
            $table->string('currency', 3)->default('KES');
            $table->string('status', 24)->default('open')->index();
            $table->timestamps();
        });

        Schema::create('commerce_saved_products', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained('commerce_products')->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['user_id', 'product_id']);
        });

        Schema::create('commerce_reviews', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('seller_id')->constrained('commerce_sellers')->cascadeOnDelete();
            $table->foreignId('product_id')->nullable()->constrained('commerce_products')->nullOnDelete();
            $table->unsignedTinyInteger('rating');
            $table->text('body')->nullable();
            $table->boolean('verified_purchase')->default(false);
            $table->string('status', 20)->default('published')->index();
            $table->timestamps();
            $table->index(['seller_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('commerce_reviews');
        Schema::dropIfExists('commerce_saved_products');
        Schema::dropIfExists('commerce_business_requests');
        Schema::dropIfExists('commerce_quote_requests');
        Schema::dropIfExists('commerce_order_items');
        Schema::dropIfExists('commerce_orders');
        Schema::dropIfExists('commerce_cart_items');
        Schema::dropIfExists('commerce_carts');
        Schema::dropIfExists('commerce_product_media');
        Schema::dropIfExists('commerce_products');
        Schema::dropIfExists('commerce_sellers');
    }
};
