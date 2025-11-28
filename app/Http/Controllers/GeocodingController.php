<?php

namespace App\Http\Controllers;

use App\Services\GeocodingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class GeocodingController extends Controller
{
    public function __construct(
        private GeocodingService $geocodingService
    ) {}

    /**
     * Geocode an address
     */
    public function geocode(Request $request): JsonResponse
    {
        $request->validate([
            'address' => 'required|string|max:255',
        ]);

        $result = $this->geocodingService->geocode($request->input('address'));

        if (!$result) {
            return response()->json([
                'success' => false,
                'message' => 'Address not found',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $result,
        ]);
    }

    /**
     * Reverse geocode coordinates
     */
    public function reverseGeocode(Request $request): JsonResponse
    {
        $request->validate([
            'lat' => 'required|numeric|between:-90,90',
            'lng' => 'required|numeric|between:-180,180',
        ]);

        $result = $this->geocodingService->reverseGeocode(
            (float) $request->input('lat'),
            (float) $request->input('lng')
        );

        if (!$result) {
            return response()->json([
                'success' => false,
                'message' => 'Location not found',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $result,
        ]);
    }

    /**
     * Search for places
     */
    public function searchPlaces(Request $request): JsonResponse
    {
        $request->validate([
            'query' => 'required|string|max:255',
        ]);

        $results = $this->geocodingService->searchPlaces($request->input('query'));

        return response()->json([
            'success' => true,
            'data' => $results,
        ]);
    }
}

