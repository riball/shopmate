<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The AI name shown to shoppers ("Offline AI (rule-based)", or
 * "Groq · <model name>") is longer than the original 20-character column.
 * SQLite ignores string lengths, so this only failed on MySQL.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('search_sessions', function (Blueprint $table) {
            $table->string('llm_driver', 120)->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('search_sessions', function (Blueprint $table) {
            $table->string('llm_driver', 20)->nullable()->change();
        });
    }
};