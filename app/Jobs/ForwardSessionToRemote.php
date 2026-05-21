<?php

namespace App\Jobs;

use App\Models\SearchSession;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ForwardSessionToRemote implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 5;
    public array $backoff = [10, 30, 60, 300, 900];

    public function __construct(public int $sessionId) {}

    public function handle(): void
    {
        $base = config('services.remote_ingest.base_url');
        if ($base === '') {
            return;
        }

        $session = SearchSession::with('creator')->find($this->sessionId);
        if (!$session) {
            Log::warning('forward session: session missing', ['id' => $this->sessionId]);
            return;
        }

        $payload = [
            'uuid'         => $session->uuid,
            'name'         => $session->name,
            'started_at'   => $session->started_at?->toIso8601String(),
            'ended_at'     => $session->ended_at?->toIso8601String(),
            'base_lat'     => $session->base_lat,
            'base_lon'     => $session->base_lon,
            'base_name'    => $session->base_name,
            'description'  => $session->description,
            'status'       => $session->status,
            'creator_name' => $session->creator?->name,
        ];

        $token = config('services.remote_ingest.token');
        $timeout = config('services.remote_ingest.timeout');

        $req = Http::timeout($timeout)
            ->acceptJson()
            ->withHeaders(['X-Forwarded-From' => 'rastreo-local']);

        if (!empty($token)) {
            $req = $req->withToken($token);
        }

        $r = $req->post($base . '/api/sessions/upsert', $payload);

        if (!$r->successful()) {
            Log::warning('remote session upsert failed', [
                'status' => $r->status(),
                'body'   => substr($r->body(), 0, 300),
                'uuid'   => $session->uuid,
            ]);
            $r->throw();
        }
    }
}
