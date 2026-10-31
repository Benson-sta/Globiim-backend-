<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table): void {
            $table->id();
            $table->string('name', 100);
            $table->string('username', 30)->unique();
            $table->string('email')->nullable()->unique();
            $table->string('phone', 25)->nullable()->unique();
            $table->string('password');
            $table->string('account_type', 30);
            $table->string('country_code', 5)->nullable();
            $table->string('avatar_url', 2048)->nullable();
            $table->string('bio', 500)->nullable();
            $table->timestamp('email_verified_at')->nullable();
            $table->timestamp('phone_verified_at')->nullable();
            $table->string('status', 20)->default('active');
            $table->rememberToken();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('users');
    }
};
