<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('commerce_product_videos', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('product_id')->constrained('commerce_products')->cascadeOnDelete();
            $table->foreignId('creator_user_id')->constrained('users')->cascadeOnDelete();
            $table->string('video_url', 2048);
            $table->string('thumbnail_url', 2048)->nullable();
            $table->string('caption', 500)->nullable();
            $table->string('status', 20)->default('pending')->index();
            $table->unsignedBigInteger('views_count')->default(0);
            $table->timestamps();
            $table->index(['status', 'created_at']);
        });

        Schema::create('commerce_marketplace_listings', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('seller_id')->constrained('commerce_sellers')->cascadeOnDelete();
            $table->string('listing_type', 32)->index(); // agriculture, equipment, property, stay, service, opportunity
            $table->string('title', 180);
            $table->string('slug', 220)->unique();
            $table->text('description');
            $table->decimal('price', 14, 2)->nullable();
            $table->char('currency', 3)->default('KES');
            $table->string('price_period', 24)->nullable(); // once, night, month, acre, day
            $table->string('city', 100)->nullable()->index();
            $table->string('locality', 120)->nullable();
            $table->string('country_code', 2)->default('KE');
            $table->json('attributes')->nullable();
            $table->json('media')->nullable();
            $table->string('status', 20)->default('draft')->index();
            $table->timestamp('published_at')->nullable();
            $table->timestamps();
            $table->index(['listing_type', 'status', 'created_at']);
        });

        Schema::create('commerce_listing_bookings', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('listing_id')->constrained('commerce_marketplace_listings')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('booking_type', 24); // viewing, stay_enquiry, rental_enquiry, service_enquiry
            $table->dateTime('requested_start')->nullable();
            $table->dateTime('requested_end')->nullable();
            $table->unsignedSmallInteger('guests')->nullable();
            $table->text('message')->nullable();
            $table->string('status', 24)->default('requested')->index();
            $table->timestamps();
            $table->index(['user_id', 'created_at']);
        });

        Schema::create('commerce_partner_profiles', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('seller_id')->unique()->constrained('commerce_sellers')->cascadeOnDelete();
            $table->string('partner_type', 40)->index(); // distributor, agent, manufacturer, service_provider, investor
            $table->string('headline', 180);
            $table->text('description')->nullable();
            $table->json('sectors')->nullable();
            $table->json('service_areas')->nullable();
            $table->string('status', 20)->default('pending')->index();
            $table->timestamps();
        });

        Schema::create('commerce_partner_applications', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('partner_profile_id')->constrained('commerce_partner_profiles')->cascadeOnDelete();
            $table->foreignId('applicant_user_id')->constrained('users')->cascadeOnDelete();
            $table->text('message');
            $table->json('proposal')->nullable();
            $table->string('status', 24)->default('submitted')->index();
            $table->timestamps();
            $table->unique(['partner_profile_id', 'applicant_user_id']);
        });

        Schema::create('commerce_shipments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('order_id')->constrained('commerce_orders')->cascadeOnDelete();
            $table->foreignId('seller_id')->constrained('commerce_sellers')->cascadeOnDelete();
            $table->string('tracking_code', 100)->nullable()->unique();
            $table->string('courier_name', 120)->nullable();
            $table->string('status', 24)->default('awaiting_dispatch')->index();
            $table->json('tracking_events')->nullable();
            $table->dateTime('dispatched_at')->nullable();
            $table->dateTime('delivered_at')->nullable();
            $table->timestamps();
            $table->index(['order_id', 'status']);
        });

        Schema::create('commerce_creator_attributions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('creator_user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained('commerce_products')->cascadeOnDelete();
            $table->foreignId('video_id')->nullable()->constrained('commerce_product_videos')->nullOnDelete();
            $table->string('attribution_code', 40)->unique();
            $table->decimal('commission_rate', 6, 3)->default(0);
            $table->string('status', 20)->default('active')->index();
            $table->timestamps();
            $table->unique(['creator_user_id', 'product_id', 'video_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('commerce_creator_attributions');
        Schema::dropIfExists('commerce_shipments');
        Schema::dropIfExists('commerce_partner_applications');
        Schema::dropIfExists('commerce_partner_profiles');
        Schema::dropIfExists('commerce_listing_bookings');
        Schema::dropIfExists('commerce_marketplace_listings');
        Schema::dropIfExists('commerce_product_videos');
    }
};
