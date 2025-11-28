<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class GeocodingService
{
    /**
     * Geocode an address to get coordinates
     * Uses OpenStreetMap Nominatim API (free, no API key required)
     * 
     * @param string $address
     * @return array|null ['lat' => float, 'lng' => float, 'display_name' => string]
     */
    public function geocode(string $address): ?array
    {
        try {
            $response = Http::timeout(5)->get('https://nominatim.openstreetmap.org/search', [
                'q' => $address,
                'format' => 'json',
                'limit' => 1,
                'countrycodes' => 'tn', // Tunisia only
                'addressdetails' => 1,
            ]);

            if ($response->successful()) {
                $data = $response->json();
                if (!empty($data) && isset($data[0]['lat']) && isset($data[0]['lon'])) {
                    return [
                        'lat' => (float) $data[0]['lat'],
                        'lng' => (float) $data[0]['lon'],
                        'display_name' => $data[0]['display_name'] ?? $address,
                        'address' => $data[0]['address'] ?? [],
                    ];
                }
            }
        } catch (\Throwable $e) {
            Log::warning('Geocoding failed', [
                'address' => $address,
                'error' => $e->getMessage(),
            ]);
        }

        return null;
    }

    /**
     * Reverse geocode coordinates to get address
     * 
     * @param float $lat
     * @param float $lng
     * @return array|null ['address' => string, 'display_name' => string]
     */
    public function reverseGeocode(float $lat, float $lng): ?array
    {
        try {
            $response = Http::timeout(5)->get('https://nominatim.openstreetmap.org/reverse', [
                'lat' => $lat,
                'lon' => $lng,
                'format' => 'json',
                'addressdetails' => 1,
            ]);

            if ($response->successful()) {
                $data = $response->json();
                if (isset($data['display_name'])) {
                    return [
                        'address' => $data['address'] ?? [],
                        'display_name' => $data['display_name'],
                    ];
                }
            }
        } catch (\Throwable $e) {
            Log::warning('Reverse geocoding failed', [
                'lat' => $lat,
                'lng' => $lng,
                'error' => $e->getMessage(),
            ]);
        }

        return null;
    }

    /**
     * Search for cities/places in Tunisia
     * 
     * @param string $query
     * @return array
     */
    public function searchPlaces(string $query): array
    {
        try {
            $response = Http::timeout(5)->get('https://nominatim.openstreetmap.org/search', [
                'q' => $query . ', Tunisia',
                'format' => 'json',
                'limit' => 5,
                'countrycodes' => 'tn',
                'addressdetails' => 1,
            ]);

            if ($response->successful()) {
                $results = $response->json();
                return array_map(function($item) {
                    return [
                        'lat' => (float) $item['lat'],
                        'lng' => (float) $item['lon'],
                        'name' => $item['display_name'],
                        'type' => $item['type'] ?? 'place',
                    ];
                }, $results);
            }
        } catch (\Throwable $e) {
            Log::warning('Place search failed', [
                'query' => $query,
                'error' => $e->getMessage(),
            ]);
        }

        return [];
    }
}

