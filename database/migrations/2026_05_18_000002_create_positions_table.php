<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('positions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('dog_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('seq');                    // contador del firmware
            $table->decimal('lat', 10, 7);                     // -90..90
            $table->decimal('lon', 10, 7);                     // -180..180
            $table->smallInteger('alt_m')->default(0);
            $table->decimal('speed_mps', 5, 2)->default(0);
            $table->smallInteger('heading_deg')->default(0);   // 0..359
            $table->unsignedBigInteger('epoch_s')->default(0); // UTC GPS time
            $table->unsignedTinyInteger('flags')->default(0);  // bitmask del firmware
            $table->smallInteger('rssi')->default(0);          // dBm (recepcion en host)
            $table->decimal('snr', 4, 2)->default(0);          // dB
            $table->timestamp('received_at')->useCurrent();    // momento que llego al host
            $table->timestamps();

            $table->index(['dog_id', 'received_at']);
            $table->index('received_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('positions');
    }
};
