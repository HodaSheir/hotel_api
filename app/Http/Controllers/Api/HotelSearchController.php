<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\HotelSearchRequest;
use App\Services\HotelSearchService;

class HotelSearchController extends Controller
{
    private $service;
    public function __construct(HotelSearchService $hotelSearchService){
        $this->service = $hotelSearchService;
    }
    public function search(HotelSearchRequest $request){
        $validated = $request->validated();
        $results = $this->service->search($validated);
        return response()->json($results);
    }
}
