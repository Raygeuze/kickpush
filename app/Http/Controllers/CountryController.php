<?php

namespace App\Http\Controllers;

use Webpatser\Countries\Countries;

class CountryController extends Controller
{
    public function getCountries(): \Illuminate\Http\JsonResponse
    {
        $countries = new Countries();
        $allCountries = $countries->getList();
        $allCountries = collect($allCountries)->map(function ($country) {
            return [
                'name' => $country['name'],
                'code' => $country['iso_3166_2'],
            ];
        })->sortBy('name')->values()->toArray();
        return response()->json($allCountries);
    }
}
