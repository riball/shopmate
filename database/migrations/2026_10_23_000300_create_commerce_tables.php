<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Cart mandates (signed mock carts), the audit log, price-drop alerts,
 * SEO data (polymorphic: one table for any page type) and static pages.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cart_mandates', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('search_session_id')->nullable();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_offer_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('quantity');
            $table->decimal('unit_price', 12, 2);
            $table->decimal('total', 12, 2);
            $table->json('payload');                     // exactly what was signed
            $table->string('signature', 64);
            $table->string('status', 20)->default('pending'); // pending | completed | rejected | expired
            $table->timestamp('consented_at');
            $table->timestamp('expires_at');
            $table->timestamp('used_at')->nullable();
            $table->string('order_ref', 30)->nullable();
            $table->timestamps();

            $table->index(['status', 'expires_at']);
        });

        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->string('event', 60);
            $table->string('subject_type', 40)->nullable();
            $table->string('subject_id', 40)->nullable();
            $table->json('data')->nullable();
            $table->string('ip', 45)->nullable();
            $table->timestamp('created_at')->nullable();

            $table->index(['subject_type', 'subject_id']);
            $table->index('event');
        });

        Schema::create('price_alerts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->string('email');
            $table->decimal('target_price', 12, 2);
            $table->timestamp('notified_at')->nullable();
            $table->timestamps();

            $table->index(['notified_at', 'product_id']);
            $table->unique(['product_id', 'email']);
        });

        Schema::create('pages', function (Blueprint $table) {
            $table->id();
            $table->string('slug')->unique();
            $table->string('title');
            $table->text('body')->nullable();
            $table->timestamps();
        });

        Schema::create('seo_meta', function (Blueprint $table) {
            $table->id();
            $table->string('seoable_type', 40);         // category | product | page
            $table->unsignedBigInteger('seoable_id');
            $table->string('title', 70)->nullable();
            $table->string('description', 170)->nullable();
            $table->string('keywords')->nullable();
            $table->boolean('noindex')->default(false);
            $table->timestamps();

            $table->unique(['seoable_type', 'seoable_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('seo_meta');
        Schema::dropIfExists('pages');
        Schema::dropIfExists('price_alerts');
        Schema::dropIfExists('audit_logs');
        Schema::dropIfExists('cart_mandates');
    }
};
