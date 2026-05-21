<?php

namespace App\Http\Controllers;

use App\Models\SearchSession;
use App\Models\SessionNote;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\StreamedResponse;

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

    /**
     * Export GPX de toda la sesion: 1 track por perro + waypoints.
     */
    public function exportGpx(SearchSession $session): StreamedResponse
    {
        $tracks = $session->positions()
            ->with('dog:id,name,node_id')
            ->orderBy('dog_id')->orderBy('received_at')
            ->get();

        $waypoints = $session->waypoints()->get();
        $byDog = $tracks->groupBy('dog_id');

        $filename = 'sesion-' . $session->id . '-' . preg_replace('/[^a-z0-9]+/i', '-', strtolower($session->name)) . '.gpx';

        return response()->streamDownload(function () use ($session, $byDog, $waypoints) {
            echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
            echo '<gpx version="1.1" creator="Rastreo K9 SAR" xmlns="http://www.topografix.com/GPX/1/1">' . "\n";
            echo '  <metadata>' . "\n";
            echo '    <name>' . e($session->name) . '</name>' . "\n";
            echo '    <time>' . $session->started_at->toIso8601String() . '</time>' . "\n";
            if ($session->description) {
                echo '    <desc>' . e($session->description) . '</desc>' . "\n";
            }
            echo '  </metadata>' . "\n";

            foreach ($waypoints as $wp) {
                echo '  <wpt lat="' . $wp->lat . '" lon="' . $wp->lon . '">' . "\n";
                echo '    <time>' . $wp->recorded_at->toIso8601String() . '</time>' . "\n";
                echo '    <name>' . e($wp->type) . '</name>' . "\n";
                if ($wp->note) {
                    echo '    <desc>' . e($wp->note) . '</desc>' . "\n";
                }
                echo '  </wpt>' . "\n";
            }

            foreach ($byDog as $dogId => $points) {
                $dog = $points->first()->dog;
                $dogName = $dog ? $dog->name : "Perro #{$dogId}";
                echo '  <trk>' . "\n";
                echo '    <name>' . e($dogName) . '</name>' . "\n";
                echo '    <trkseg>' . "\n";
                foreach ($points as $p) {
                    echo '      <trkpt lat="' . $p->lat . '" lon="' . $p->lon . '">' . "\n";
                    echo '        <ele>' . (int) $p->alt_m . '</ele>' . "\n";
                    echo '        <time>' . $p->received_at->toIso8601String() . '</time>' . "\n";
                    echo '      </trkpt>' . "\n";
                }
                echo '    </trkseg>' . "\n";
                echo '  </trk>' . "\n";
            }

            echo '</gpx>' . "\n";
        }, $filename, [
            'Content-Type' => 'application/gpx+xml',
        ]);
    }

    private function authorizeAdmin(): void
    {
        if (!request()->user() || !request()->user()->isAdmin()) {
            abort(403, 'Requiere rol admin.');
        }
    }
}
