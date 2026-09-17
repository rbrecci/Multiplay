<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('game_user', function (Blueprint $table) {
            $table->string('status', 20)->nullable()->change();
            $table->text('notes')->nullable();
            $table->unsignedTinyInteger('rating')->nullable();
            $table->boolean('favorite')->default(false);
        });
    }

    public function down(): void
    {
        Schema::table('game_user', function (Blueprint $table) {
            $table->dropColumn(['notes', 'rating', 'favorite']);
            $table->string('status', 20)->nullable(false)->change();
        });
    }
};
