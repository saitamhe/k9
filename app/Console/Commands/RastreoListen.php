<?php

namespace App\Console\Commands;

use App\Models\Dog;
use Illuminate\Console\Command;

class RastreoListen extends Command
{
    protected $signature = 'rastreo:listen
        {--port= : Sobrescribe el COM del .env (ej: COM5)}
        {--baud= : Sobrescribe el baudrate del .env}';

    protected $description = 'Escucha el puerto serie del host LoRa y persiste las posiciones recibidas';

    public function handle(): int
    {
        // Forzar flush inmediato — PHP CLI en Windows a veces buferea STDOUT.
        ob_implicit_flush(true);

        $port = $this->option('port') ?: env('RASTREO_SERIAL_PORT', 'COM4');
        $baud = (int) ($this->option('baud') ?: env('RASTREO_SERIAL_BAUD', 115200));

        // Configurar el puerto via "mode COMx:..." (solo Windows).
        // CRITICO: to=off para que fread no bloquee. Windows persiste settings entre opens,
        // asi que hay que ponerlo explicito.
        $this->info("Configurando $port a $baud baud...");
        $modeCmd = sprintf(
            'mode %s: BAUD=%d PARITY=N DATA=8 STOP=1 to=off xon=off odsr=off octs=off dtr=on rts=on idsr=off',
            $port, $baud
        );
        exec($modeCmd . ' 2>&1', $output, $code);
        $this->line("  > " . implode(' | ', $output));
        if ($code !== 0) {
            $this->error("Fallo configurar el puerto:");
            $this->error(implode("\n", $output));
            return self::FAILURE;
        }

        // Abrir el dispositivo. El prefijo \\.\ funciona para COM1..COM999.
        $devicePath = '\\\\.\\' . $port;
        $handle = @fopen($devicePath, 'r+b');
        if (!$handle) {
            $this->error("No se pudo abrir $port. Verifica que no este abierto por PlatformIO Monitor u otro proceso.");
            return self::FAILURE;
        }
        stream_set_blocking($handle, false);

        $this->info("Escuchando en $port. Ctrl+C para detener.");
        $this->line("(loop tick cada 1 s; heartbeat cada 5 s)");
        $this->newLine();

        // STDOUT directo para bypasear cualquier buffering de Laravel/Symfony en Windows.
        $stdout = fopen('php://stdout', 'w');
        $log = function (string $msg) use ($stdout) {
            fwrite($stdout, $msg . "\n");
            fflush($stdout);
        };

        $buffer = '';
        $totalBytes = 0;
        $iter = 0;
        $startedAt = time();
        $lastTick = microtime(true);
        $lastHeartbeat = time();
        $lastByteAt = 0;

        while (true) {
            $iter++;
            $chunk = fread($handle, 4096);
            $chunkLen = ($chunk !== false) ? strlen($chunk) : 0;
            if ($chunkLen > 0) {
                $totalBytes += $chunkLen;
                $lastByteAt = time();
                $buffer .= $chunk;
                while (($nl = strpos($buffer, "\n")) !== false) {
                    $line = rtrim(substr($buffer, 0, $nl), "\r");
                    $buffer = substr($buffer, $nl + 1);
                    if ($line !== '') {
                        $this->processLine($line);
                    }
                }
            }

            // Tick cada 1 segundo (asegura que veamos algo aunque el puerto este mudo)
            if (microtime(true) - $lastTick >= 1.0) {
                $log(sprintf(
                    "[tick] iter=%d uptime=%ds total_bytes=%d buf_len=%d",
                    $iter, time() - $startedAt, $totalBytes, strlen($buffer)
                ));
                $lastTick = microtime(true);
            }

            // Heartbeat extendido cada 5 s con preview del buffer si hay basura sin newline
            if (time() - $lastHeartbeat >= 5) {
                if (strlen($buffer) > 0) {
                    $log("[buffer-preview] " . substr(
                        addcslashes($buffer, "\r\n\t\0..\37\177..\377"), 0, 120
                    ));
                }
                $lastHeartbeat = time();
            }

            usleep(50000);
        }

        // Inalcanzable salvo Ctrl+C
        // @phpstan-ignore-next-line
        fclose($handle);
        return self::SUCCESS;
    }

    private function processLine(string $line): void
    {
        $data = json_decode($line, true);

        if (!is_array($data)) {
            $this->warn("NO-JSON: $line");
            return;
        }

        // Mensajes de estado del host (boot, ready, etc.) no son paquetes de posicion
        if (isset($data['status']) || !isset($data['id'], $data['lat'], $data['lon'])) {
            $this->line("<fg=cyan>STATUS:</> " . $line);
            return;
        }

        // Find or create del perro por node_id
        $dog = Dog::firstOrCreate(
            ['node_id' => $data['id']],
            ['name' => "Perro #{$data['id']}", 'is_active' => true]
        );

        $pos = $dog->positions()->create([
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

        $fixState = $pos->hasFix() ? '<fg=green>FIX</>' : '<fg=yellow>NO-FIX</>';
        $movState = $pos->isMoving() ? '<fg=magenta>MOV</>' : '   ';

        $this->line(sprintf(
            "[%s] %-12s %s %s  lat=%.6f lon=%.6f  spd=%.1f m/s  rssi=%d snr=%.1f",
            $pos->received_at->format('H:i:s'),
            $dog->name,
            $fixState,
            $movState,
            $pos->lat,
            $pos->lon,
            $pos->speed_mps,
            $pos->rssi,
            $pos->snr
        ));
    }
}
