<?php

namespace App\Suppliers;

use Illuminate\Support\Arr;

class SupplierB implements SupplierInterface
{
  protected string $endpoint = 'https://serpapi.com/search';

  public function createPoolRequest($pool, array $params, string $asKey)
  {
    $query = [
      'engine' => 'google_hotels',
      'q' => $params['location'] ?? '',
      'check_in_date' => $params['check_in'] ?? null,
      'check_out_date' => $params['check_out'] ?? null,
      'adults' => $params['guests'] ?? 1,
      'currency' => $params['currency'] ?? 'USD',
      'api_key' => config('services.serpapi.api_key') ?? env('SERPAPI_API_KEY'),
    ];

    return $pool->as($asKey)->get($this->endpoint, array_filter($query, fn($v) => $v !== null && $v !== ''));
  }

  public function normalizeResponse(array $payload): array
  {
    // serpapi returns properties (or similar). be defensive.
    $items = $payload['properties'] ?? $payload['hotels'] ?? $payload['results'] ?? $payload;

    $out = [];
    foreach ($items as $h) {
      $gps = Arr::get($h, 'gps_coordinates', Arr::get($h, 'coords', []));
      $lat = Arr::get($gps, 'latitude', $gps[0] ?? null);
      $lng = Arr::get($gps, 'longitude', $gps[1] ?? null);

      // price can be under different keys
      $price = Arr::get($h, 'rate_per_night.amount', Arr::get($h, 'min_price', Arr::get($h, 'price', 0)));

      $out[] = [
        'name' => Arr::get($h, 'name', Arr::get($h, 'title')),
        'location' => Arr::get($h, 'address', Arr::get($h, 'location')),
        'city' => Arr::get($h, 'city'),
        'country' => Arr::get($h, 'country'),
        'lat' => $lat !== null ? floatval($lat) : null,
        'lng' => $lng !== null ? floatval($lng) : null,
        'price' => floatval($price),
        'currency' => Arr::get($h, 'rate_per_night.currency', 'USD'),
        'rating' => floatval(Arr::get($h, 'overall_rating', Arr::get($h, 'rating', 0))),
        'max_guests' => intval(Arr::get($h, 'rooms[0].max_people', Arr::get($h, 'max_people', 1))),
        'supplier' => 'serpapi_google_hotels',
      ];
    }

    return $out;
  }
}
