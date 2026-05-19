<?php

namespace App\Http\Controllers;

class FieldController extends Controller
{
    public function index()
    {
        return view('field', [
            'base' => [
                'name' => env('BASE_NAME', 'Base'),
                'lat'  => (float) env('BASE_LAT', -33.45),
                'lon'  => (float) env('BASE_LON', -70.65),
            ],
            't3' => [
                'host' => env('T3_HOST', '192.168.4.1'),
                'ssid' => env('T3_SSID', 'K9-Base'),
                'pass' => env('T3_PASS', 'rastreok9'),
            ],
        ]);
    }
}
