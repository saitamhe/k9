<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SearchSession;
use App\Models\SessionNote;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SessionSyncController extends Controller
{
    /**
     * Idempotente por uuid. Crea o actualiza la sesion en el remoto.
     * Body:
     *   uuid (req), name, started_at, ended_at (nullable), base_lat, base_lon,
     *   base_name, description, status (active|closed), creator_name (opcional).
     *
     * `created_by` en el remoto: si llega `creator_name` y existe un usuario con
     * ese email/nombre, lo asociamos; si no, queda NULL. Los users no se sincronizan
     * (cada server tiene los suyos).
     */
    public function upsert(Request $request): JsonResponse
    {
        $data = $request->validate([
            'uuid'         => 'required|uuid',
            'name'         => 'required|string|max:160',
            'started_at'   => 'required|string',
            'ended_at'     => 'nullable|string',
            'base_lat'     => 'nullable|numeric|between:-90,90',
            'base_lon'     => 'nullable|numeric|between:-180,180',
            'base_name'    => 'nullable|string|max:160',
            'description'  => 'nullable|string',
            'status'       => 'required|in:active,closed',
            'creator_name' => 'nullable|string|max:120',
        ]);

        // Si hay otra sesion activa local y la entrante tambien es active, cerramos
        // las otras: solo una activa a la vez. (Si esta misma sesion ya esta active,
        // la query la excluye via where uuid !=.)
        if ($data['status'] === SearchSession::STATUS_ACTIVE) {
            SearchSession::active()
                ->where('uuid', '!=', $data['uuid'])
                ->update([
                    'status'   => SearchSession::STATUS_CLOSED,
                    'ended_at' => now(),
                ]);
        }

        $creatorId = null;
        if (!empty($data['creator_name'])) {
            $creatorId = User::where('name', $data['creator_name'])->value('id');
        }

        $session = SearchSession::updateOrCreate(
            ['uuid' => $data['uuid']],
            [
                'name'        => $data['name'],
                'started_at'  => $data['started_at'],
                'ended_at'    => $data['ended_at'] ?? null,
                'base_lat'    => $data['base_lat'] ?? null,
                'base_lon'    => $data['base_lon'] ?? null,
                'base_name'   => $data['base_name'] ?? null,
                'description' => $data['description'] ?? null,
                'status'      => $data['status'],
                'created_by'  => $creatorId,
            ]
        );

        // Invalida cache de sesion activa para que los ingest siguientes la vean.
        \Illuminate\Support\Facades\Cache::forget('rastreo.active_session_id');

        return response()->json([
            'ok'      => true,
            'uuid'    => $session->uuid,
            'id'      => $session->id,
            'status'  => $session->status,
        ]);
    }

    /**
     * Agrega una nota a la sesion identificada por uuid.
     * Idempotente por (search_session_id, body, created_at_iso) — evita
     * duplicados si el job se reintenta.
     */
    public function addNote(Request $request, string $uuid): JsonResponse
    {
        $data = $request->validate([
            'body'        => 'required|string|max:2000',
            'lat'         => 'nullable|numeric|between:-90,90',
            'lon'         => 'nullable|numeric|between:-180,180',
            'author_name' => 'nullable|string|max:120',
            'created_at'  => 'required|string',
        ]);

        $session = SearchSession::where('uuid', $uuid)->firstOrFail();

        $authorId = null;
        if (!empty($data['author_name'])) {
            $authorId = User::where('name', $data['author_name'])->value('id');
        }

        // Normalizamos created_at a un Carbon: el ISO8601 que viaja en el payload
        // no matchea el formato 'Y-m-d H:i:s' que SQLite/MySQL usan en BD, asi
        // los retries del job no duplican la nota.
        $createdAt = Carbon::parse($data['created_at']);

        $note = SessionNote::firstOrCreate(
            [
                'search_session_id' => $session->id,
                'body'              => $data['body'],
                'created_at'        => $createdAt,
            ],
            [
                'user_id'    => $authorId,
                'lat'        => $data['lat'] ?? null,
                'lon'        => $data['lon'] ?? null,
                'updated_at' => $createdAt,
            ]
        );

        return response()->json([
            'ok'      => true,
            'note_id' => $note->id,
        ]);
    }
}
