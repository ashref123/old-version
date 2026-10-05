<?php

namespace App\Services\Maps\DTO;

class RouteResult
{
    public function __construct(
        public readonly ?float $distanceMeters = null,
        public readonly ?int $durationSeconds = null,
        public readonly ?string $encodedPolyline = null,
        public readonly array $raw = [],
    ) {}

    public function distanceKm(): ?float
    {
        return $this->distanceMeters === null
            ? null
            : round($this->distanceMeters / 1000, 1);
    }

    public function durationMinutes(): ?int
    {
        return $this->durationSeconds === null
            ? null
            : (int) round($this->durationSeconds / 60);
    }
}
