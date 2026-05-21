<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('search_sessions', function (Blueprint $table) {
            $table->id();
            $table->string('name');                                  // p.ej. "Búsqueda Lago Llanquihue 21-05"
            $table->timestamp('started_at')->useCurrent();
            $table->timestamp('ended_at')->nullable();
            $table->decimal('base_lat', 10, 7)->nullable();
            $table->decimal('base_lon', 10, 7)->nullable();
            $table->string('base_name')->nullable();
            $table->text('description')->nullable();                 // descripcion general del operativo
            $table->string('status', 16)->default('active');         // 'active' | 'closed'
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index('status');
            $table->index('started_at');
        });

        // FK nullable en positions: posiciones que llegan durante una sesion activa
        // se asocian a ella; el resto queda con NULL (telemetria fuera de operativo).
        Schema::table('positions', function (Blueprint $table) {
            $table->foreignId('search_session_id')->nullable()
                  ->after('dog_id')->constrained()->nullOnDelete();
            $table->index('search_session_id');
        });

        // Idem waypoints. Mantenemos `session_id` (string legacy) por si hay datos
        // previos, pero el nuevo flujo usa search_session_id.
        Schema::table('waypoints', function (Blueprint $table) {
            $table->foreignId('search_session_id')->nullable()
                  ->after('uuid')->constrained()->nullOnDelete();
            $table->index('search_session_id');
        });
    }

    public function down(): void
    {
        Schema::table('waypoints', function (Blueprint $table) {
            $table->dropConstrainedForeignId('search_session_id');
        });
        Schema::table('positions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('search_session_id');
        });
        Schema::dropIfExists('search_sessions');
    }
};
