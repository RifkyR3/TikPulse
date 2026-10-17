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
        if (Schema::hasTable('routers')) {
            return;
        }

        Schema::create('routers', function (Blueprint $table): void {
            $table->id();
            $table->string('name', 100);
            $table->string('host', 255);
            $table->unsignedInteger('api_port')->default(8728);
            $table->string('protocol', 20)->default('socket'); // socket, socket_ssl, rest_https
            $table->string('username', 100);
            $table->text('password_encrypted');
            $table->boolean('is_safe_mode_enabled')->default(true)->index();
            $table->boolean('use_tls')->default(false);
            $table->unsignedSmallInteger('timeout_seconds')->default(5);
            $table->text('description')->nullable();
            $table->string('status', 20)->default('unknown')->index(); // online, offline, error, unknown
            $table->timestampTz('last_connected_at')->nullable();
            $table->string('ros_version', 50)->nullable();
            $table->string('board_model', 100)->nullable();
            $table->unsignedSmallInteger('cpu_load')->nullable();
            $table->unsignedBigInteger('free_memory_bytes')->nullable();
            $table->string('uptime', 100)->nullable();
            $table->timestampsTz();

            $table->index(['host', 'api_port']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('routers');
    }
};
