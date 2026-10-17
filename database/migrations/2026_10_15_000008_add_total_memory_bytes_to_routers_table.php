<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (Schema::hasColumn('routers', 'total_memory_bytes')) {
            return;
        }

        Schema::table('routers', function (Blueprint $table): void {
            $table->unsignedBigInteger('total_memory_bytes')->nullable()->after('free_memory_bytes');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (! Schema::hasColumn('routers', 'total_memory_bytes')) {
            return;
        }

        Schema::table('routers', function (Blueprint $table): void {
            $table->dropColumn('total_memory_bytes');
        });
    }
};
