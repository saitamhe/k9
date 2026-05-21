<?php

namespace App\Jobs;

use App\Models\SessionNote;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ForwardSessionNoteToRemote implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 5;
    public array $backoff = [10, 30, 60, 300, 900];

    public function __construct(public int $noteId) {}

    public function handle(): void
    {
        $base = config('services.remote_ingest.base_url');
        if ($base === '') {
            return;
        }

        $note = SessionNote::with(['session:id,uuid', 'author:id,name'])->find($this->noteId);
        if (!$note || !$note->session) {
            Log::warning('forward note: note or session missing', ['id' => $this->noteId]);
            return;
        }

        $payload = [
            'body'        => $note->body,
            'lat'         => $note->lat,
            'lon'         => $note->lon,
            'author_name' => $note->author?->name,
            'created_at'  => $note->created_at->toIso8601String(),
        ];

        $token = config('services.remote_ingest.token');
        $timeout = config('services.remote_ingest.timeout');

        $req = Http::timeout($timeout)
            ->acceptJson()
            ->withHeaders(['X-Forwarded-From' => 'rastreo-local']);

        if (!empty($token)) {
            $req = $req->withToken($token);
        }

        $r = $req->post($base . '/api/sessions/' . $note->session->uuid . '/notes', $payload);

        if (!$r->successful()) {
            Log::warning('remote session note failed', [
                'status'       => $r->status(),
                'body'         => substr($r->body(), 0, 300),
                'session_uuid' => $note->session->uuid,
                'note_id'      => $note->id,
            ]);
            $r->throw();
        }
    }
}
