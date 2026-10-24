<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Catalog: categories, stores, products, offers (one row per store price),
 * typed spec facts and price history.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('categories', function (Blueprint $table) {
            $table->id();
            $table->string('slug')->unique();
            $table->string('name');
            $table->timestamps();
        });

        Schema::create('stores', function (Blueprint $table) {
            $table->id();
            $table->string('slug')->unique();
            $table->string('name');
            $table->string('website')->nullable();
            $table->boolean('is_demo')->default(false);   // fictional store from the demo catalog
            $table->timestamps();
        });

        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('category_id')->constrained()->cascadeOnDelete();
            $table->string('source', 20);                // demo | local | dummyjson
            $table->string('external_id');               // id inside the source
            $table->string('slug')->unique();
            $table->string('name');
            $table->string('brand')->nullable();
            $table->string('model_group');               // name without colour/storage, used to collapse variants
            $table->string('image_url')->nullable();
            $table->decimal('rating', 3, 2)->nullable();
            $table->unsignedInteger('review_count')->default(0);
            // Denormalised (copied) values so browse pages need no sub-queries.
            $table->decimal('best_price', 12, 2)->nullable();
            $table->foreignId('best_offer_id')->nullable();
            $table->unsignedSmallInteger('offers_count')->default(0);
            $table->decimal('weighted_rating', 5, 3)->nullable();
            $table->date('captured_on')->nullable();     // when the source data was captured
            $table->timestamps();

            $table->unique(['source', 'external_id']);
            $table->index(['category_id', 'best_price']);
            $table->index(['category_id', 'weighted_rating']);
            $table->index(['category_id', 'brand']);
            $table->index('model_group');
        });

        Schema::create('product_offers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('store_id')->constrained()->cascadeOnDelete();
            $table->decimal('price', 12, 2);              // always rupees
            $table->decimal('mrp', 12, 2)->nullable();
            $table->string('original_currency', 3)->default('INR');
            $table->decimal('original_price', 12, 2)->nullable();
            $table->decimal('fx_rate', 12, 6)->nullable(); // set when converted, shown as "approx."
            $table->string('url')->nullable();
            $table->boolean('in_stock')->default(true);
            $table->timestamp('last_checked_at')->nullable();
            $table->timestamps();

            $table->unique(['product_id', 'store_id']);
            $table->index(['product_id', 'in_stock', 'price']);
        });

        Schema::create('spec_facts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->string('key', 60);
            $table->decimal('num_value', 14, 3)->nullable();
            $table->string('text_value')->nullable();
            $table->boolean('bool_value')->nullable();
            $table->string('raw_label')->nullable();
            $table->string('raw_value')->nullable();
            // The listing contradicted itself, so no value is recorded (never guessed).
            $table->boolean('is_conflict')->default(false);
            $table->timestamps();

            $table->unique(['product_id', 'key']);
            $table->index(['key', 'num_value']);
        });

        Schema::create('price_history', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_offer_id')->constrained()->cascadeOnDelete();
            $table->decimal('price', 12, 2);
            $table->timestamp('recorded_at');

            $table->index(['product_offer_id', 'recorded_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('price_history');
        Schema::dropIfExists('spec_facts');
        Schema::dropIfExists('product_offers');
        Schema::dropIfExists('products');
        Schema::dropIfExists('stores');
        Schema::dropIfExists('categories');
    }
};
