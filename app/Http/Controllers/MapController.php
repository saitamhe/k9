<?php

namespace App\Http\Controllers;

class MapController extends Controller
{
    public function index()
    {
        return view('map', [
            'base' => [
                'name' => env('BASE_NAME', 'Base'),
                'lat'  => (float) env('BASE_LAT', -33.45),
                'lon'  => (float) env('BASE_LON', -70.65),
            ],
        ]);
    }
}
