<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dogs', function (Blueprint $table) {
            $table->id();
            $table->unsignedTinyInteger('node_id')->unique();   // ID que viaja en el paquete LoRa
            $table->string('name');                              // nombre del perro
            $table->string('handler')->nullable();               // guia (persona)
            $table->string('team')->nullable();                  // equipo / brigada
            $table->string('color', 9)->default('#ef4444');      // color del marker en mapa
            $table->boolean('is_active')->default(true);         // visible en mapa
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dogs');
    }
};
