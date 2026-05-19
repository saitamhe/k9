"""
serial_to_api.py
================
Lee el puerto serie del host LoRa (T3) y POSTea cada paquete JSON
al endpoint Laravel /api/positions/ingest.

Necesario porque PHP fread sobre COM ports en Windows bloquea sin importar
los flags de mode/stream_set_blocking. Python + pyserial funciona perfecto.

Dependencias: pyserial, requests
Ambas vienen preinstaladas en el venv de PlatformIO, asi que no hay que
instalar nada extra.

Uso (PowerShell):
  C:\\Users\\Saitam\\.platformio\\penv\\Scripts\\python.exe tools\\serial_to_api.py

Override opcional:
  python serial_to_api.py --port COM5 --baud 115200 --api http://localhost:8000/api/positions/ingest

Ctrl+C para detener.
"""
import argparse
import json
import sys
import time
from datetime import datetime

try:
    import serial
except ImportError:
    print("ERROR: falta pyserial. Si estas usando el Python del sistema, instala:")
    print("    pip install pyserial requests")
    print("O usa el Python del venv de PlatformIO (ya tiene pyserial):")
    print("    C:\\Users\\Saitam\\.platformio\\penv\\Scripts\\python.exe tools\\serial_to_api.py")
    sys.exit(1)

try:
    import requests
except ImportError:
    print("ERROR: falta requests. Instalar con:")
    print("    pip install requests")
    sys.exit(1)


def ts():
    return datetime.now().strftime('%H:%M:%S')


def main():
    ap = argparse.ArgumentParser(description="Serial -> HTTP gateway para Rastreo K9 SAR")
    ap.add_argument('--port', default='COM4', help='Puerto COM del T3 (default COM4)')
    ap.add_argument('--baud', type=int, default=115200, help='Baud rate (default 115200)')
    ap.add_argument('--api',
                    default='http://localhost:8000/api/positions/ingest',
                    help='URL del endpoint de ingesta')
    ap.add_argument('--quiet', action='store_true', help='No imprimir paquetes individuales')
    args = ap.parse_args()

    print(f"[{ts()}] Abriendo {args.port} @ {args.baud} baud...")
    try:
        ser = serial.Serial(args.port, args.baud, timeout=1)
    except serial.SerialException as e:
        print(f"[{ts()}] No se pudo abrir {args.port}: {e}")
        print("  Verifica que no este abierto por PlatformIO Monitor u otro proceso.")
        sys.exit(1)

    print(f"[{ts()}] Posteando paquetes a {args.api}")
    print(f"[{ts()}] Esperando datos... (Ctrl+C para detener)")
    print()

    ok_count = 0
    err_count = 0
    status_count = 0

    try:
        while True:
            try:
                raw = ser.readline()
            except serial.SerialException as e:
                print(f"[{ts()}] Serial error: {e}")
                time.sleep(1)
                continue

            if not raw:
                continue

            line = raw.decode('utf-8', errors='replace').strip()
            if not line:
                continue

            # Intentar parsear como JSON
            try:
                data = json.loads(line)
            except json.JSONDecodeError:
                if not args.quiet:
                    print(f"[{ts()}] NO-JSON: {line[:120]}")
                continue

            # Mensajes de status del host (boot, ready, oled, etc.) — solo mostrar
            if 'status' in data or 'lat' not in data:
                status_count += 1
                if not args.quiet:
                    print(f"[{ts()}] STATUS: {line}")
                continue

            # Paquete de posicion -> POST al backend
            try:
                r = requests.post(args.api, json=data, timeout=3)
                if 200 <= r.status_code < 300:
                    ok_count += 1
                    if not args.quiet:
                        body = r.json()
                        print(f"[{ts()}] OK seq={data.get('seq')} id={data['id']} "
                              f"lat={data['lat']:.6f} lon={data['lon']:.6f} "
                              f"flags={data.get('flags', 0)} -> position_id={body.get('position_id')}")
                else:
                    err_count += 1
                    print(f"[{ts()}] API {r.status_code}: {r.text[:200]}")
            except requests.exceptions.ConnectionError:
                err_count += 1
                print(f"[{ts()}] Sin conexion al backend ({args.api}). "
                      f"Esta corriendo 'php artisan serve'?")
                time.sleep(2)
            except requests.exceptions.RequestException as e:
                err_count += 1
                print(f"[{ts()}] POST fail: {e}")

    except KeyboardInterrupt:
        print()
        print(f"[{ts()}] Detenido. ok={ok_count} status={status_count} err={err_count}")
    finally:
        ser.close()


if __name__ == '__main__':
    main()
