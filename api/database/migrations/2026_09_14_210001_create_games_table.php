<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('games', function (Blueprint $table) {
            $table->id();
            $table->foreignId('console_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('igdb_id');
            $table->string('name');
            $table->string('cover_url')->nullable();
            $table->unsignedSmallInteger('first_release_year')->nullable();
            $table->timestamps();

            $table->unique(['console_id', 'igdb_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('games');
    }
};
