<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('search_sessions', function (Blueprint $table) {
            // Nullable inicial para backfill. La unicidad va aparte (index parcial unique).
            $table->uuid('uuid')->nullable()->after('id');
        });

        // Backfill cualquier sesion previa con un UUID v4.
        DB::table('search_sessions')->whereNull('uuid')->orderBy('id')->each(function ($row) {
            DB::table('search_sessions')->where('id', $row->id)->update(['uuid' => (string) Str::uuid()]);
        });

        Schema::table('search_sessions', function (Blueprint $table) {
            $table->unique('uuid');
        });
    }

    public function down(): void
    {
        Schema::table('search_sessions', function (Blueprint $table) {
            $table->dropUnique(['uuid']);
            $table->dropColumn('uuid');
        });
    }
};
