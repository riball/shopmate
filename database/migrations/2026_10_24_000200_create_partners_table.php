<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Partners: retailers that push their own prices to ShopMate through the
 * partner API. Each one is tied to one store and may only change that
 * store's offers.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('partners', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100);
            $table->foreignId('store_id')->constrained()->cascadeOnDelete();
            $table->string('key_id', 40)->unique();      // public: sent with every request
            $table->text('secret');                       // encrypted with APP_KEY; signs requests, never sent
            $table->json('allowed_ips');                  // IPs or CIDR ranges; empty = nobody (fail closed)
            $table->string('status', 20)->default('active'); // active | read_only | suspended
            $table->unsignedSmallInteger('rate_limit_per_minute')->default(60);
            $table->timestamp('last_used_at')->nullable();
            $table->timestamps();

            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('partners');
    }
};
