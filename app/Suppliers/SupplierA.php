<?php

namespace App\Suppliers;

use Illuminate\Support\Arr;

class SupplierA implements SupplierInterface
{
  protected string $endpoint = 'https://api.hotelapi.co/v1/search';

  public function createPoolRequest($pool, array $params, string $asKey)
  {
    $query = [
      'location' => $params['location'] ?? null,
      'check_in' => $params['check_in'] ?? null,
      'check_out' => $params['check_out'] ?? null,
      'guests' => $params['guests'] ?? 1,
      'currency' => $params['currency'] ?? 'USD',
    ];

    // schedule the request in the pool and give it a name $asKey
    return $pool->as($asKey)->get($this->endpoint, array_filter($query, fn($v) => $v !== null));
  }

  public function normalizeResponse(array $payload): array
  {
    $items = $payload['data'] ?? $payload['hotels'] ?? $payload['results'] ?? $payload;

    $out = [];
    foreach ($items as $h) {
      // be defensive: fields may differ
      $out[] = [
        'name' => Arr::get($h, 'title', Arr::get($h, 'name', Arr::get($h, 'hotel_name'))),
        'location' => trim((Arr::get($h, 'city', '') . ', ' . Arr::get($h, 'country', ''))),
        'city' => Arr::get($h, 'city'),
        'country' => Arr::get($h, 'country'),
        'lat' => Arr::get($h, 'coords.lat', Arr::get($h, 'latitude')),
        'lng' => Arr::get($h, 'coords.lng', Arr::get($h, 'longitude')),
        'price' => floatval(Arr::get($h, 'price.amount', Arr::get($h, 'price', 0))),
        'currency' => Arr::get($h, 'price.currency', 'USD'),
        'rating' => floatval(Arr::get($h, 'stars', Arr::get($h, 'rating', 0))),
        'max_guests' => intval(Arr::get($h, 'capacity', Arr::get($h, 'max_people', 1))),
        'supplier' => 'hotelapi_co',
      ];
    }

    return $out;
  }
}
