<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class ForwardWaypointPhotoToRemote implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 5;
    public array $backoff = [10, 30, 60, 300, 900];

    public function __construct(public string $uuid, public string $photoPath) {}

    public function handle(): void
    {
        $base = config('services.remote_ingest.base_url');
        if ($base === '') {
            return;
        }

        $disk = Storage::disk('public');
        if (!$disk->exists($this->photoPath)) {
            Log::warning('remote waypoint photo: file missing', ['uuid' => $this->uuid, 'path' => $this->photoPath]);
            return;
        }

        $url = $base . '/api/waypoints/' . $this->uuid . '/photo';
        $token = config('services.remote_ingest.token');
        $timeout = (int) config('services.remote_ingest.timeout') * 4; // fotos son más pesadas

        $req = Http::timeout($timeout)
            ->acceptJson()
            ->withHeaders(['X-Forwarded-From' => 'rastreo-local'])
            ->attach('photo', $disk->get($this->photoPath), basename($this->photoPath));

        if (!empty($token)) {
            $req = $req->withToken($token);
        }

        $r = $req->post($url);

        if (!$r->successful()) {
            Log::warning('remote waypoint photo failed', [
                'status' => $r->status(),
                'body'   => substr($r->body(), 0, 300),
                'uuid'   => $this->uuid,
            ]);
            $r->throw();
        }
    }
}
