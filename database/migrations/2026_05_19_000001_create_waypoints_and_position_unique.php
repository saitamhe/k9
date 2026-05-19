<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Idempotente: si la migracion fallo a mitad de camino antes (typical en
        // SQLite cuyo DDL no es transaccional), no morir al crear de nuevo.
        if (!Schema::hasTable('waypoints')) {
            Schema::create('waypoints', function (Blueprint $table) {
                $table->id();
                $table->uuid('uuid')->unique();              // generado en la PWA, idempotencia del upsert
                $table->string('session_id', 64)->nullable(); // mapeo futuro al `deployments` formal
                $table->string('type', 32);                  // article|k9_alert|k9_interest|contamination|rest|other
                $table->decimal('lat', 10, 7);
                $table->decimal('lon', 10, 7);
                $table->text('note')->nullable();
                $table->string('photo_path')->nullable();    // path en disk 'public' (sube por endpoint separado)
                $table->timestamp('recorded_at');
                $table->timestamps();

                $table->index('recorded_at');
                $table->index('session_id');
            });
        }

        // Deduplica positions ANTES de aplicar el unique. Si hay (dog_id, seq)
        // repetidos por ingestas previas sin idempotencia, nos quedamos con la
        // fila mas reciente (MAX id) y borramos las demas.
        DB::statement('
            DELETE FROM positions
            WHERE id NOT IN (
                SELECT MAX(id) FROM positions GROUP BY dog_id, seq
            )
        ');

        // Unique compuesto en positions(dog_id, seq) para que el sync desde la
        // PWA sea idempotente via upsert. Si el T3 reinicia y el seq se resetea
        // dentro de la misma sesion, esto colapsaria registros — aceptable para
        // SAR de 1 hora (el reset de seq mid-sesion es extremadamente raro).
        Schema::table('positions', function (Blueprint $table) {
            $table->unique(['dog_id', 'seq'], 'positions_dog_seq_unique');
        });
    }

    public function down(): void
    {
        Schema::table('positions', function (Blueprint $table) {
            $table->dropUnique('positions_dog_seq_unique');
        });
        Schema::dropIfExists('waypoints');
    }
};
