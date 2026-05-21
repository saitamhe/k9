<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Jobs\ForwardPositionToRemote;
use App\Models\Dog;
use App\Models\SearchSession;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class PositionController extends Controller
{
    /**
     * Ultima posicion de cada perro activo. Lo que polea el mapa cada 1s.
     */
    public function latest(): JsonResponse
    {
        $dogs = Dog::query()
            ->where('is_active', true)
            ->with('latestPosition')
            ->get()
            ->map(function (Dog $dog) {
                $p = $dog->latestPosition;
                return [
                    'id'       => $dog->id,
                    'node_id'  => $dog->node_id,
                    'name'     => $dog->name,
                    'handler'  => $dog->handler,
                    'color'    => $dog->color,
                    'position' => $p ? [
                        'lat'         => (float) $p->lat,
                        'lon'         => (float) $p->lon,
                        'alt_m'       => $p->alt_m,
                        'speed_mps'   => (float) $p->speed_mps,
                        'heading_deg' => $p->heading_deg,
                        'flags'       => $p->flags,
                        'has_fix'     => $p->hasFix(),
                        'is_moving'   => $p->isMoving(),
                        'rssi'        => $p->rssi,
                        'snr'         => (float) $p->snr,
                        'received_at' => $p->received_at->toIso8601String(),
                        'age_s'       => $p->received_at->diffInSeconds(now()),
                    ] : null,
                ];
            });

        return response()->json([
            'dogs'      => $dogs,
            'server_ts' => now()->toIso8601String(),
        ]);
    }

    /**
     * Ingest: recibe un paquete JSON del helper Python (que lee del serial).
     * Body: { "v":1, "id":1, "seq":42, "lat":-33.45, "lon":-70.65, "alt":543,
     *         "spd":1.2, "hdg":90, "ts":1716000000, "flags":1, "rssi":-78, "snr":10.5 }
     */
    public function ingest(Request $request): JsonResponse
    {
        $data = $request->json()->all();

        if (!isset($data['id'], $data['lat'], $data['lon'])) {
            return response()->json(['error' => 'missing required fields: id, lat, lon'], 422);
        }

        $dog = Dog::firstOrCreate(
            ['node_id' => $data['id']],
            ['name' => "Perro #{$data['id']}", 'is_active' => true]
        );

        // session_uuid puede venir cuando el paquete fue reenviado desde el local
        // (asi el remoto preserva la asociacion); si no, usa la sesion activa local.
        $sessionId = isset($data['session_uuid'])
            ? $this->resolveOrPlaceholderSessionId($data['session_uuid'])
            : $this->currentSessionId();

        $pos = $dog->positions()->create([
            'search_session_id' => $sessionId,
            'seq'         => $data['seq']   ?? 0,
            'lat'         => $data['lat'],
            'lon'         => $data['lon'],
            'alt_m'       => $data['alt']   ?? 0,
            'speed_mps'   => $data['spd']   ?? 0,
            'heading_deg' => (int) round($data['hdg'] ?? 0),
            'epoch_s'     => $data['ts']    ?? 0,
            'flags'       => $data['flags'] ?? 0,
            'rssi'        => $data['rssi']  ?? 0,
            'snr'         => $data['snr']   ?? 0,
            'received_at' => now(),
        ]);

        // Reenvio asincrono al servidor remoto (k9.heforge.cl). Si el request
        // ya viene reenviado desde otro nodo, NO re-reenviamos para evitar loops.
        if (!$request->hasHeader('X-Forwarded-From') && config('services.remote_ingest.base_url') !== '') {
            // Inyectamos el session_uuid asi el remoto puede asociar la posicion
            // a la misma sesion (los users/ids locales no existen alla, pero el
            // uuid si — el job ForwardSessionToRemote lo sincroniza aparte).
            if ($sessionId && !isset($data['session_uuid'])) {
                $data['session_uuid'] = SearchSession::whereKey($sessionId)->value('uuid');
            }
            ForwardPositionToRemote::dispatch($data);
        }

        return response()->json([
            'ok'          => true,
            'dog_id'      => $dog->id,
            'position_id' => $pos->id,
        ]);
    }

    /**
     * Devuelve el id de la sesion activa o null. Cacheado 5s para no pegarle a la
     * BD cada paquete LoRa. El cache lo invalida SearchSessionController al
     * iniciar/cerrar operativo via Cache::forget('rastreo.active_session_id').
     */
    private function currentSessionId(): ?int
    {
        return Cache::remember('rastreo.active_session_id', 5, function () {
            return SearchSession::active()->latest('started_at')->value('id');
        });
    }

    /**
     * Devuelve el id local de la sesion identificada por uuid. Si no existe
     * todavia (el job de upsert puede llegar despues que el primer paquete),
     * la crea como placeholder en estado 'active' con datos minimos — el
     * ForwardSessionToRemote::handle() del local la completa al primer upsert.
     */
    private function resolveOrPlaceholderSessionId(string $uuid): int
    {
        $existing = SearchSession::where('uuid', $uuid)->value('id');
        if ($existing) {
            return $existing;
        }

        $s = SearchSession::create([
            'uuid'       => $uuid,
            'name'       => 'Operativo (sync pendiente)',
            'started_at' => now(),
            'status'     => SearchSession::STATUS_ACTIVE,
        ]);
        Cache::forget('rastreo.active_session_id');
        return $s->id;
    }

    /**
     * Traza de un perro (ultimas N posiciones, default 500).
     */
    public function track(Request $request, Dog $dog): JsonResponse
    {
        $limit = min((int) $request->query('limit', 500), 5000);

        $points = $dog->positions()
            ->orderBy('received_at', 'desc')
            ->limit($limit)
            ->get(['lat', 'lon', 'received_at', 'speed_mps', 'flags'])
            ->reverse()
            ->values()
            ->map(fn ($p) => [
                'lat' => (float) $p->lat,
                'lon' => (float) $p->lon,
                't'   => $p->received_at->toIso8601String(),
                'spd' => (float) $p->speed_mps,
                'f'   => $p->flags,
            ]);

        return response()->json([
            'dog_id' => $dog->id,
            'name'   => $dog->name,
            'color'  => $dog->color,
            'points' => $points,
        ]);
    }
}
