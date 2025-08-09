<?php

namespace App\Services;

use App\Suppliers\SupplierA;
use App\Suppliers\SupplierB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class HotelSearchService
{
  protected array $suppliers;

  public function __construct()
  {
    $this->suppliers = [
      'hotelapi' => new SupplierA(),
      'serpapi'  => new SupplierB(),
    ];
  }
  public function search(array $params): array
  {
    // 1) Fire requests in parallel using Http::pool()
    $responses = Http::pool(function ($pool) use ($params) {
      $requests = [];
      foreach ($this->suppliers as $key => $supplier) {
        $requests[$key] = $supplier->createPoolRequest($pool, $params, $key);
      }
      return $requests; // return array of scheduled requests
    });

    // 2) Normalize & merge responses
    $all = [];
    foreach ($responses as $key => $response) {
      // $response is an Illuminate\Http\Client\Response
      if (! $response->successful()) {
        Log::warning("Supplier [$key] failed", [
          'status' => $response->status(),
          'body' => substr($response->body(), 0, 200),
        ]);
        continue;
      }

      $payload = $response->json();
      try {
        $normalized = $this->suppliers[$key]->normalizeResponse($payload);
        if (is_array($normalized)) {
          $all = array_merge($all, $normalized);
        }
      } catch (\Throwable $e) {
        Log::error("Normalization error for supplier [$key]: " . $e->getMessage());
        continue;
      }
    }

    // 3) apply filters
    $filtered = $this->applyFilters($all, $params);

    // 4) dedupe (lowest price wins)
    $deduped = $this->deduplicate($filtered);

    // 5) sort
    if (!empty($params['sort_by'])) {
      $deduped = $this->sortResults($deduped, $params['sort_by']);
    }

    return array_values($deduped);
  }

  protected function applyFilters(array $hotels, array $params): array
  {
    return array_values(array_filter($hotels, function ($h) use ($params) {
      // Price range
      if (isset($params['min_price']) && $h['price'] < $params['min_price']) return false;
      if (isset($params['max_price']) && $h['price'] > $params['max_price']) return false;

      // Guests
      if (!empty($params['guests']) && !empty($h['max_guests']) && $h['max_guests'] < $params['guests']) {
        return false;
      }

      // Location match (simple substring)
      if (!empty($params['location'])) {
        $needle = mb_strtolower($params['location']);
        $hay = mb_strtolower($h['location'] ?? $h['city'] ?? '');
        if (mb_strpos($hay, $needle) === false) return false;
      }

      // Optional proximity filter (lat/lng + max_km)
      if (isset($params['lat'], $params['lng']) && isset($h['lat'], $h['lng'])) {
        $maxKm = $params['max_km'] ?? 50;
        if ($this->haversine($params['lat'], $params['lng'], $h['lat'], $h['lng']) > $maxKm) {
          return false;
        }
      }

      return true;
    }));
  }

  protected function deduplicate(array $hotels): array
  {
    $map = [];
    foreach ($hotels as $h) {
      $key = $this->dedupeKey($h);
      if (!isset($map[$key])) {
        $map[$key] = $h;
      } else {
        // choose lowest price (tiebreaker: higher rating)
        if ($h['price'] < $map[$key]['price']) {
          $map[$key] = $h;
        } elseif ($h['price'] === $map[$key]['price'] && ($h['rating'] ?? 0) > ($map[$key]['rating'] ?? 0)) {
          $map[$key] = $h;
        }
      }
    }
    return $map;
  }

  protected function dedupeKey(array $hotel): string
  {
    $name = preg_replace('/[^a-z0-9]/i', '', mb_strtolower($hotel['name'] ?? ''));
    $city = preg_replace('/[^a-z0-9]/i', '', mb_strtolower($hotel['city'] ?? $hotel['location'] ?? ''));
    return $name . '|' . $city;
  }

  protected function sortResults(array $hotels, string $by): array
  {
    usort($hotels, function ($a, $b) use ($by) {
      if ($by === 'price') return $a['price'] <=> $b['price'];
      if ($by === 'rating') return ($b['rating'] ?? 0) <=> ($a['rating'] ?? 0);
      return 0;
    });
    return $hotels;
  }

  protected function haversine($lat1, $lon1, $lat2, $lon2)
  {
    $earthRadius = 6371;
    $dLat = deg2rad($lat2 - $lat1);
    $dLon = deg2rad($lon2 - $lon1);
    $a = sin($dLat / 2) ** 2 + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLon / 2) ** 2;
    $c = 2 * atan2(sqrt($a), sqrt(1 - $a));
    return $earthRadius * $c;
  }

}
