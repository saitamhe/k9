<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Dog;
use App\Models\Position;
use App\Models\Waypoint;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class SyncController extends Controller
{
    /**
     * Sync batch desde la PWA del guia.
     * Upsert idempotente por (dog_id, seq) para posiciones y por uuid para waypoints.
     * Las fotos NO van aqui — se suben por separado a /api/waypoints/{uuid}/photo.
     *
     * Body esperado:
     *   {
     *     "positions": [
     *       { "node_id":1, "seq":42, "lat":..., "lon":..., "alt":..., "spd":...,
     *         "hdg":..., "ts":..., "flags":..., "rssi":..., "snr":...,
     *         "received_at":"2026-05-19T12:00:00Z" }
     *     ],
     *     "waypoints": [
     *       { "uuid":"...", "session_id":"...", "type":"k9_alert",
     *         "lat":..., "lon":..., "note":"...", "recorded_at":"..." }
     *     ]
     *   }
     */
    public function batch(Request $request): JsonResponse
    {
        $data = $request->validate([
            'positions'                  => 'array',
            'positions.*.node_id'        => 'required|integer|min:0|max:255',
            'positions.*.seq'            => 'required|integer|min:0',
            'positions.*.lat'            => 'required|numeric|between:-90,90',
            'positions.*.lon'            => 'required|numeric|between:-180,180',
            'positions.*.alt'            => 'nullable|integer',
            'positions.*.spd'            => 'nullable|numeric',
            'positions.*.hdg'            => 'nullable|numeric',
            'positions.*.ts'             => 'nullable|integer',
            'positions.*.flags'          => 'nullable|integer',
            'positions.*.rssi'           => 'nullable|integer',
            'positions.*.snr'            => 'nullable|numeric',
            'positions.*.received_at'    => 'nullable|string',

            'waypoints'                  => 'array',
            'waypoints.*.uuid'           => 'required|uuid',
            'waypoints.*.session_id'     => 'nullable|string|max:64',
            'waypoints.*.type'           => 'required|string|in:' . implode(',', Waypoint::TYPES),
            'waypoints.*.lat'            => 'required|numeric|between:-90,90',
            'waypoints.*.lon'            => 'required|numeric|between:-180,180',
            'waypoints.*.note'           => 'nullable|string',
            'waypoints.*.recorded_at'    => 'required|string',
        ]);

        $posSynced = [];
        $wpSynced  = [];

        DB::transaction(function () use ($data, &$posSynced, &$wpSynced) {
            // ---- Posiciones ----
            // Agrupamos por node_id para resolver/crear el Dog una sola vez por nodo.
            $byNode = collect($data['positions'] ?? [])->groupBy('node_id');
            foreach ($byNode as $nodeId => $rows) {
                $dog = Dog::firstOrCreate(
                    ['node_id' => $nodeId],
                    ['name' => "Perro #{$nodeId}", 'is_active' => true]
                );

                $now = now();
                $upserts = $rows->map(fn ($r) => [
                    'dog_id'      => $dog->id,
                    'seq'         => (int) $r['seq'],
                    'lat'         => $r['lat'],
                    'lon'         => $r['lon'],
                    'alt_m'       => $r['alt']   ?? 0,
                    'speed_mps'   => $r['spd']   ?? 0,
                    'heading_deg' => (int) round($r['hdg'] ?? 0),
                    'epoch_s'     => $r['ts']    ?? 0,
                    'flags'       => $r['flags'] ?? 0,
                    'rssi'        => $r['rssi']  ?? 0,
                    'snr'         => $r['snr']   ?? 0,
                    'received_at' => $r['received_at'] ?? $now,
                    'created_at'  => $now,
                    'updated_at'  => $now,
                ])->all();

                Position::upsert(
                    $upserts,
                    ['dog_id', 'seq'],
                    ['lat','lon','alt_m','speed_mps','heading_deg','epoch_s','flags','rssi','snr','received_at','updated_at']
                );

                foreach ($rows as $r) {
                    $posSynced[] = ['node_id' => (int) $nodeId, 'seq' => (int) $r['seq']];
                }
            }

            // ---- Waypoints ----
            foreach ($data['waypoints'] ?? [] as $w) {
                Waypoint::updateOrCreate(
                    ['uuid' => $w['uuid']],
                    [
                        'session_id'  => $w['session_id'] ?? null,
                        'type'        => $w['type'],
                        'lat'         => $w['lat'],
                        'lon'         => $w['lon'],
                        'note'        => $w['note'] ?? null,
                        'recorded_at' => $w['recorded_at'],
                    ]
                );
                $wpSynced[] = $w['uuid'];
            }
        });

        return response()->json([
            'ok'               => true,
            'positions_synced' => $posSynced,
            'waypoints_synced' => $wpSynced,
            'server_ts'        => now()->toIso8601String(),
        ]);
    }

    /**
     * Sube la foto de un waypoint. Multipart: field "photo".
     * El waypoint debe existir (creado por sync batch previo).
     * Re-upload reemplaza la foto anterior.
     */
    public function waypointPhoto(Request $request, string $uuid): JsonResponse
    {
        $request->validate([
            'photo' => 'required|file|image|max:10240', // 10 MB max
        ]);

        $wp = Waypoint::where('uuid', $uuid)->firstOrFail();

        // Borra la foto previa si existe (caso re-upload)
        if ($wp->photo_path && Storage::disk('public')->exists($wp->photo_path)) {
            Storage::disk('public')->delete($wp->photo_path);
        }

        $path = $request->file('photo')->store('waypoints', 'public');
        $wp->update(['photo_path' => $path]);

        return response()->json([
            'ok'         => true,
            'uuid'       => $uuid,
            'photo_path' => $path,
            'url'        => Storage::disk('public')->url($path),
        ]);
    }
}
