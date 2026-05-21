<?php

namespace App\Http\Controllers;

use App\Models\SearchSession;
use App\Models\SessionNote;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class SearchSessionController extends Controller
{
    /**
     * Listado: activa (si hay) arriba, luego cerradas en orden descendente.
     */
    public function index()
    {
        $sessions = SearchSession::with('creator')
            ->withCount(['positions', 'waypoints', 'notes'])
            ->orderByRaw("CASE status WHEN 'active' THEN 0 ELSE 1 END")
            ->orderByDesc('started_at')
            ->paginate(30);

        return view('sessions.index', compact('sessions'));
    }

    /**
     * Form para iniciar un operativo nuevo. Si ya hay uno activo, redirige al detalle.
     */
    public function create()
    {
        $this->authorizeAdmin();
        $active = SearchSession::current();
        if ($active) {
            return redirect()->route('sessions.show', $active)
                ->with('flash', 'Ya hay un operativo activo: ' . $active->name);
        }
        return view('sessions.create');
    }

    public function store(Request $request)
    {
        $this->authorizeAdmin();
        $data = $request->validate([
            'name'        => 'required|string|max:160',
            'description' => 'nullable|string',
            'base_name'   => 'nullable|string|max:160',
            'base_lat'    => 'nullable|numeric|between:-90,90',
            'base_lon'    => 'nullable|numeric|between:-180,180',
        ]);

        // Cierra cualquier operativo activo previo: solo permitimos uno a la vez.
        SearchSession::active()->update([
            'status'   => SearchSession::STATUS_CLOSED,
            'ended_at' => now(),
        ]);
        Cache::forget('rastreo.active_session_id');

        $session = SearchSession::create([
            'name'        => $data['name'],
            'description' => $data['description'] ?? null,
            'base_name'   => $data['base_name'] ?? null,
            'base_lat'    => $data['base_lat'] ?? null,
            'base_lon'    => $data['base_lon'] ?? null,
            'started_at'  => now(),
            'status'      => SearchSession::STATUS_ACTIVE,
            'created_by'  => $request->user()->id,
        ]);
        Cache::forget('rastreo.active_session_id');

        return redirect()->route('map')->with('flash', "Operativo iniciado: {$session->name}");
    }

    /**
     * Detalle: mapa con todos los tracks por perro, waypoints y notas.
     */
    public function show(SearchSession $session)
    {
        $session->load(['creator', 'notes.author']);

        // Tracks agrupados por perro
        $tracks = $session->positions()
            ->with('dog:id,name,color,node_id')
            ->orderBy('received_at')
            ->get(['dog_id', 'lat', 'lon', 'received_at', 'speed_mps', 'flags'])
            ->groupBy('dog_id')
            ->map(fn ($pts, $dogId) => [
                'dog'    => $pts->first()->dog,
                'points' => $pts->map(fn ($p) => [
                    'lat' => (float) $p->lat,
                    'lon' => (float) $p->lon,
                    't'   => $p->received_at->toIso8601String(),
                    'spd' => (float) $p->speed_mps,
                ])->values(),
            ])->values();

        $waypoints = $session->waypoints()
            ->orderBy('recorded_at')
            ->get(['id','uuid','type','lat','lon','note','photo_path','recorded_at']);

        return view('sessions.show', compact('session', 'tracks', 'waypoints'));
    }

    /**
     * Cierra el operativo activo.
     */
    public function close(SearchSession $session, Request $request)
    {
        $this->authorizeAdmin();
        if (!$session->isActive()) {
            return back()->with('flash', 'Este operativo ya estaba cerrado.');
        }
        $session->update([
            'status'   => SearchSession::STATUS_CLOSED,
            'ended_at' => now(),
        ]);
        Cache::forget('rastreo.active_session_id');

        return redirect()->route('sessions.show', $session)
            ->with('flash', 'Operativo cerrado.');
    }

    /**
     * Agrega una nota a la sesion (puede tener pin en el mapa opcional).
     */
    public function addNote(SearchSession $session, Request $request)
    {
        $this->authorizeAdmin();
        $data = $request->validate([
            'body' => 'required|string|max:2000',
            'lat'  => 'nullable|numeric|between:-90,90',
            'lon'  => 'nullable|numeric|between:-180,180',
        ]);

        SessionNote::create([
            'search_session_id' => $session->id,
            'user_id'           => $request->user()->id,
            'body'              => $data['body'],
            'lat'               => $data['lat'] ?? null,
            'lon'               => $data['lon'] ?? null,
        ]);

        if ($request->wantsJson()) {
            return response()->json(['ok' => true]);
        }
        return back()->with('flash', 'Nota guardada.');
    }

    private function authorizeAdmin(): void
    {
        if (!request()->user() || !request()->user()->isAdmin()) {
            abort(403, 'Requiere rol admin.');
        }
    }
}
