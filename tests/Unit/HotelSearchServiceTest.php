<?php

namespace Tests\Unit;

use App\Services\HotelSearchService;
use Tests\TestCase;
use Illuminate\Support\Facades\Http;


class HotelSearchServiceTest extends TestCase
{
    public function test_handles_failed_supplier_requests()
    {
        Http::fake([
            'api.hotelapi.co/*' => Http::response(null, 500),
            'serpapi.com/*' => Http::response([
                'properties' => [
                    [
                        'name' => 'Hotel Gamma',
                        'location' => 'London, UK',
                        'price' => 180,
                        'rating' => 4.3,
                    ]
                ]
            ], 200),
        ]);

        $service = new HotelSearchService();

        $results = $service->search([
            'query' => 'London',
            'check_in' => '2025-09-01',
            'check_out' => '2025-09-03'
        ]);

        $this->assertCount(1, $results);
        $this->assertEquals('Hotel Gamma', $results[0]['name']);
    }
}
