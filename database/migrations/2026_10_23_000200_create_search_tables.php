<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * One search = one search_session. Every pipeline step is a trace_step row,
 * the final picks are recommendations, and every sentence shown is a claim
 * with its verification result.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('search_sessions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->text('query');
            $table->text('clarification_answer')->nullable();
            $table->boolean('clarified')->default(false);       // never ask twice
            $table->string('status', 30)->default('queued');      // queued|running|needs_clarification|done|no_results|failed
            $table->string('clarify_question')->nullable();
            $table->json('plan')->nullable();                     // the "work order"
            $table->decimal('original_budget', 12, 2)->nullable(); // verification always uses this
            $table->decimal('effective_budget', 12, 2)->nullable();
            $table->json('replans')->nullable();                  // what was given up, shown in the yellow note
            $table->string('llm_driver', 20)->nullable();
            $table->unsignedInteger('claims_checked')->default(0);
            $table->unsignedInteger('claims_removed')->default(0);
            $table->text('error')->nullable();
            $table->unsignedInteger('duration_ms')->nullable();
            $table->string('ip', 45)->nullable();
            $table->timestamps();

            $table->index(['status', 'created_at']);
        });

        Schema::create('trace_steps', function (Blueprint $table) {
            $table->id();
            $table->uuid('search_session_id');
            $table->unsignedSmallInteger('seq');
            $table->string('step_id', 10)->nullable();   // T1, T2 ... for tool steps that facts can cite
            $table->string('node', 30);                  // planner, search, rank, verify, note ...
            $table->string('level', 10)->default('info'); // info | warning
            $table->text('thought')->nullable();
            $table->text('action')->nullable();
            $table->text('observation')->nullable();
            $table->json('product_ids')->nullable();     // products this step produced facts about
            $table->json('data')->nullable();
            $table->timestamp('created_at')->nullable();

            $table->foreign('search_session_id')->references('id')->on('search_sessions')->cascadeOnDelete();
            $table->unique(['search_session_id', 'seq']);
        });

        Schema::create('recommendations', function (Blueprint $table) {
            $table->id();
            $table->uuid('search_session_id');
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_offer_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedTinyInteger('rank');
            $table->decimal('score', 6, 4);
            $table->json('checklist');                   // requirement => met | unknown | conflict | unsupported
            $table->timestamps();

            $table->foreign('search_session_id')->references('id')->on('search_sessions')->cascadeOnDelete();
            $table->unique(['search_session_id', 'rank']);
        });

        Schema::create('claims', function (Blueprint $table) {
            $table->id();
            $table->uuid('search_session_id');
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->text('text');
            $table->string('cites', 10)->nullable();
            $table->json('facts')->nullable();
            $table->string('status', 12);                // verified | rejected
            $table->unsignedTinyInteger('attempt')->default(1);
            $table->text('reason')->nullable();
            $table->timestamps();

            $table->foreign('search_session_id')->references('id')->on('search_sessions')->cascadeOnDelete();
            $table->index(['search_session_id', 'product_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('claims');
        Schema::dropIfExists('recommendations');
        Schema::dropIfExists('trace_steps');
        Schema::dropIfExists('search_sessions');
    }
};
