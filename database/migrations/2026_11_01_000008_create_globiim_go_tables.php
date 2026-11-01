<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('go_partner_applications', function (Blueprint $t) {
            $t->id(); $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->string('partner_type', 40)->index(); $t->string('full_name', 160);
            $t->string('phone', 32); $t->string('territory', 120)->nullable();
            $t->json('details')->nullable(); $t->string('status', 24)->default('submitted')->index();
            $t->string('referral_code', 32)->nullable()->unique(); $t->timestamps();
        });
        Schema::create('go_agent_referrals', function (Blueprint $t) {
            $t->id(); $t->foreignId('agent_user_id')->constrained('users')->cascadeOnDelete();
            $t->foreignId('referred_user_id')->nullable()->constrained('users')->nullOnDelete();
            $t->string('partner_type', 40); $t->string('contact_name', 160);
            $t->string('contact_phone', 32); $t->string('status', 24)->default('lead')->index();
            $t->decimal('commission_amount', 12, 2)->default(0); $t->char('currency',3)->default('KES');
            $t->timestamp('activated_at')->nullable(); $t->timestamps();
            $t->index(['agent_user_id','status']);
        });
        Schema::create('go_driver_profiles', function (Blueprint $t) {
            $t->id(); $t->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $t->string('driver_type', 24)->default('independent'); $t->string('licence_number', 100);
            $t->date('licence_expiry')->nullable(); $t->json('service_categories')->nullable();
            $t->json('documents')->nullable(); $t->string('verification_status',24)->default('pending')->index();
            $t->string('availability',20)->default('offline')->index(); $t->decimal('rating',3,2)->default(0);
            $t->unsignedInteger('completed_jobs')->default(0); $t->timestamps();
        });
        Schema::create('go_vehicles', function (Blueprint $t) {
            $t->id(); $t->foreignId('owner_user_id')->constrained('users')->cascadeOnDelete();
            $t->foreignId('driver_user_id')->nullable()->constrained('users')->nullOnDelete();
            $t->string('registration_number',32)->unique(); $t->string('vehicle_type',40)->index();
            $t->string('make',80)->nullable(); $t->string('model',80)->nullable(); $t->unsignedSmallInteger('year')->nullable();
            $t->unsignedSmallInteger('seat_capacity')->default(1); $t->json('photos')->nullable();
            $t->json('documents')->nullable(); $t->date('insurance_expiry')->nullable();
            $t->date('inspection_expiry')->nullable(); $t->string('verification_status',24)->default('pending')->index();
            $t->string('operational_status',24)->default('inactive')->index(); $t->timestamps();
        });
        Schema::create('go_fleet_agreements', function (Blueprint $t) {
            $t->id(); $t->foreignId('owner_user_id')->constrained('users')->cascadeOnDelete();
            $t->string('management_type',32); $t->json('terms')->nullable(); $t->string('status',24)->default('requested')->index();
            $t->string('agreement_document_url',2048)->nullable(); $t->timestamp('approved_at')->nullable(); $t->timestamps();
        });
        Schema::create('go_fleet_vehicle_assignments', function (Blueprint $t) {
            $t->id(); $t->foreignId('fleet_agreement_id')->constrained('go_fleet_agreements')->cascadeOnDelete();
            $t->foreignId('vehicle_id')->constrained('go_vehicles')->cascadeOnDelete();
            $t->foreignId('driver_user_id')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamp('starts_at')->nullable(); $t->timestamp('ends_at')->nullable();
            $t->string('status',24)->default('pending')->index(); $t->timestamps();
        });
        Schema::create('go_rides', function (Blueprint $t) {
            $t->id(); $t->string('reference',40)->unique(); $t->foreignId('passenger_user_id')->constrained('users')->cascadeOnDelete();
            $t->foreignId('driver_user_id')->nullable()->constrained('users')->nullOnDelete();
            $t->foreignId('vehicle_id')->nullable()->constrained('go_vehicles')->nullOnDelete();
            $t->string('service_class',40); $t->json('pickup'); $t->json('destination');
            $t->decimal('estimated_fare',12,2)->nullable(); $t->char('currency',3)->default('KES');
            $t->string('status',24)->default('requested')->index(); $t->string('pickup_pin_hash',255)->nullable();
            $t->timestamp('scheduled_at')->nullable(); $t->timestamp('accepted_at')->nullable();
            $t->timestamp('started_at')->nullable(); $t->timestamp('completed_at')->nullable(); $t->timestamps();
            $t->index(['passenger_user_id','created_at']);
        });
        Schema::create('go_trips', function (Blueprint $t) {
            $t->id(); $t->foreignId('organiser_user_id')->constrained('users')->cascadeOnDelete();
            $t->string('title',180); $t->text('description'); $t->json('route');
            $t->timestamp('departure_at'); $t->timestamp('return_at')->nullable();
            $t->unsignedSmallInteger('seat_capacity'); $t->unsignedSmallInteger('seats_available');
            $t->decimal('price_per_seat',12,2); $t->char('currency',3)->default('KES');
            $t->string('trip_category',40); $t->json('media')->nullable(); $t->json('compliance')->nullable();
            $t->string('status',24)->default('draft')->index(); $t->timestamp('published_at')->nullable(); $t->timestamps();
            $t->index(['departure_at','status']);
        });
        Schema::create('go_trip_bookings', function (Blueprint $t) {
            $t->id(); $t->foreignId('trip_id')->constrained('go_trips')->cascadeOnDelete();
            $t->foreignId('passenger_user_id')->constrained('users')->cascadeOnDelete();
            $t->unsignedSmallInteger('seats'); $t->decimal('total_amount',12,2); $t->char('currency',3)->default('KES');
            $t->string('status',24)->default('payment_pending')->index(); $t->string('payment_reference',100)->nullable();
            $t->timestamps(); $t->unique(['trip_id','passenger_user_id']);
        });
        Schema::create('go_bus_operators', function (Blueprint $t) {
            $t->id(); $t->foreignId('owner_user_id')->constrained('users')->cascadeOnDelete();
            $t->string('company_name',180); $t->json('licences')->nullable(); $t->json('amenities')->nullable();
            $t->string('verification_status',24)->default('pending')->index(); $t->timestamps();
        });
        Schema::create('go_bus_schedules', function (Blueprint $t) {
            $t->id(); $t->foreignId('operator_id')->constrained('go_bus_operators')->cascadeOnDelete();
            $t->string('route_name',180); $t->string('origin',120); $t->string('destination',120);
            $t->timestamp('departure_at'); $t->timestamp('arrival_at')->nullable();
            $t->unsignedSmallInteger('seat_capacity'); $t->decimal('fare',12,2); $t->char('currency',3)->default('KES');
            $t->json('seat_map'); $t->string('status',24)->default('draft')->index(); $t->timestamps();
            $t->index(['origin','destination','departure_at']);
        });
        Schema::create('go_bus_bookings', function (Blueprint $t) {
            $t->id(); $t->foreignId('schedule_id')->constrained('go_bus_schedules')->cascadeOnDelete();
            $t->foreignId('passenger_user_id')->constrained('users')->cascadeOnDelete();
            $t->json('seat_numbers'); $t->json('passengers'); $t->decimal('total_amount',12,2);
            $t->char('currency',3)->default('KES'); $t->string('booking_reference',40)->unique();
            $t->string('status',24)->default('payment_pending')->index(); $t->string('qr_token',100)->nullable()->unique(); $t->timestamps();
        });
        Schema::create('go_delivery_jobs', function (Blueprint $t) {
            $t->id(); $t->foreignId('customer_user_id')->constrained('users')->cascadeOnDelete();
            $t->string('job_type',40); $t->json('pickup'); $t->json('dropoff'); $t->json('package_details')->nullable();
            $t->decimal('quoted_fare',12,2)->nullable(); $t->char('currency',3)->default('KES');
            $t->foreignId('provider_user_id')->nullable()->constrained('users')->nullOnDelete();
            $t->string('status',24)->default('requested')->index(); $t->json('proof_of_delivery')->nullable(); $t->timestamps();
        });
        Schema::create('go_vehicle_transports', function (Blueprint $t) {
            $t->id(); $t->foreignId('customer_user_id')->constrained('users')->cascadeOnDelete();
            $t->json('vehicle_details'); $t->json('pickup'); $t->json('dropoff'); $t->json('photos')->nullable();
            $t->date('preferred_date')->nullable(); $t->foreignId('transporter_user_id')->nullable()->constrained('users')->nullOnDelete();
            $t->json('condition_report')->nullable(); $t->json('delivery_report')->nullable();
            $t->string('status',24)->default('quote_requested')->index(); $t->decimal('quoted_amount',12,2)->nullable();
            $t->char('currency',3)->default('KES'); $t->timestamps();
        });
        Schema::create('go_rental_listings', function (Blueprint $t) {
            $t->id(); $t->foreignId('owner_user_id')->constrained('users')->cascadeOnDelete();
            $t->foreignId('vehicle_id')->constrained('go_vehicles')->cascadeOnDelete();
            $t->string('rental_mode',24); $t->decimal('rate',12,2); $t->char('currency',3)->default('KES');
            $t->string('rate_period',16); $t->json('availability_rules')->nullable();
            $t->string('status',24)->default('pending')->index(); $t->timestamps();
        });
        Schema::create('go_rental_bookings', function (Blueprint $t) {
            $t->id(); $t->foreignId('listing_id')->constrained('go_rental_listings')->cascadeOnDelete();
            $t->foreignId('customer_user_id')->constrained('users')->cascadeOnDelete();
            $t->timestamp('starts_at'); $t->timestamp('ends_at'); $t->string('status',24)->default('requested')->index(); $t->timestamps();
        });
        Schema::create('go_corporate_accounts', function (Blueprint $t) {
            $t->id(); $t->foreignId('owner_user_id')->constrained('users')->cascadeOnDelete();
            $t->string('company_name',180); $t->string('registration_number',100)->nullable();
            $t->json('offices')->nullable(); $t->json('settings')->nullable();
            $t->string('status',24)->default('pending')->index(); $t->timestamps();
        });
        Schema::create('go_corporate_members', function (Blueprint $t) {
            $t->id(); $t->foreignId('corporate_account_id')->constrained('go_corporate_accounts')->cascadeOnDelete();
            $t->foreignId('user_id')->constrained('users')->cascadeOnDelete(); $t->string('role',24)->default('employee');
            $t->decimal('monthly_budget',12,2)->nullable(); $t->timestamps(); $t->unique(['corporate_account_id','user_id']);
        });
        Schema::create('go_financial_entries', function (Blueprint $t) {
            $t->id(); $t->string('division',32)->index(); $t->string('entry_type',32);
            $t->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $t->string('reference',100)->index(); $t->decimal('amount',14,2); $t->char('currency',3)->default('KES');
            $t->string('status',24)->default('pending')->index(); $t->json('metadata')->nullable(); $t->timestamps();
            $t->unique(['division','entry_type','reference']);
        });
        Schema::create('go_safety_incidents', function (Blueprint $t) {
            $t->id(); $t->foreignId('reporter_user_id')->constrained('users')->cascadeOnDelete();
            $t->string('incident_type',40); $t->string('reference',100)->nullable();
            $t->text('description'); $t->json('location')->nullable(); $t->json('evidence')->nullable();
            $t->string('priority',16)->default('normal')->index(); $t->string('status',24)->default('open')->index(); $t->timestamps();
        });
        Schema::create('go_reviews', function (Blueprint $t) {
            $t->id(); $t->foreignId('reviewer_user_id')->constrained('users')->cascadeOnDelete();
            $t->foreignId('reviewed_user_id')->constrained('users')->cascadeOnDelete();
            $t->string('division',32); $t->string('reference',100); $t->unsignedTinyInteger('rating');
            $t->text('body')->nullable(); $t->boolean('verified_transaction')->default(false); $t->timestamps();
            $t->unique(['reviewer_user_id','division','reference']);
        });
    }

    public function down(): void
    {
        foreach (['go_reviews','go_safety_incidents','go_financial_entries','go_corporate_members','go_corporate_accounts','go_rental_bookings','go_rental_listings','go_vehicle_transports','go_delivery_jobs','go_bus_bookings','go_bus_schedules','go_bus_operators','go_trip_bookings','go_trips','go_rides','go_fleet_vehicle_assignments','go_fleet_agreements','go_vehicles','go_driver_profiles','go_agent_referrals','go_partner_applications'] as $table) Schema::dropIfExists($table);
    }
};
