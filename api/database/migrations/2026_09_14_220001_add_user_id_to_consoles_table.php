<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('consoles', function (Blueprint $table) {
            $table->foreignId('user_id')->nullable()->after('id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('sort_order')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('consoles', function (Blueprint $table) {
            $table->dropConstrainedForeignId('user_id');
            $table->unsignedSmallInteger('sort_order')->nullable(false)->change();
        });
    }
};
