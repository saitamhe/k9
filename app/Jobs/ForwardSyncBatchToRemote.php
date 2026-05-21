<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ForwardSyncBatchToRemote implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 5;
    public array $backoff = [10, 30, 60, 300, 900];

    public function __construct(public array $payload) {}

    public function handle(): void
    {
        $base = config('services.remote_ingest.base_url');
        if ($base === '') {
            return;
        }

        $url = $base . '/api/sync/batch';
        $token = config('services.remote_ingest.token');
        $timeout = config('services.remote_ingest.timeout');

        $req = Http::timeout($timeout)
            ->acceptJson()
            ->withHeaders(['X-Forwarded-From' => 'rastreo-local']);

        if (!empty($token)) {
            $req = $req->withToken($token);
        }

        $r = $req->post($url, $this->payload);

        if (!$r->successful()) {
            Log::warning('remote sync/batch failed', [
                'status'    => $r->status(),
                'body'      => substr($r->body(), 0, 300),
                'positions' => count($this->payload['positions'] ?? []),
                'waypoints' => count($this->payload['waypoints'] ?? []),
            ]);
            $r->throw();
        }
    }
}
