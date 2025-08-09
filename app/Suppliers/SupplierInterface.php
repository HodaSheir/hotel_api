<?php
namespace App\Suppliers;

interface SupplierInterface
{
    /**
     * Create and return a pool request (e.g. $pool->as($as)->get(...))
     * Must return whatever $pool->get(...) returns so it is scheduled by Http::pool()
     *
     * @param  \Illuminate\Http\Client\Pool  $pool
     * @param  array  $params
     * @param  string $asKey   // name used for response key in the pool results
     * @return mixed
     */
    public function createPoolRequest($pool, array $params, string $asKey);

    /**
     * Normalize a supplier HTTP JSON payload into array of hotels in our canonical schema.
     *
     * @param array $payload
     * @return array<int, array> normalized hotels
     */
    public function normalizeResponse(array $payload): array;
}
