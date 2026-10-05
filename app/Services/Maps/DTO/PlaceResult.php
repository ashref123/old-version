<?php

namespace App\Services\Maps\DTO;

class PlaceResult
{
    public function __construct(
        public readonly ?float $lat = null,
        public readonly ?float $lng = null,
        public readonly ?string $address = null,
        public readonly ?string $placeId = null,
        public readonly ?string $name = null,
        public readonly array $raw = [],
    ) {}

    public function toArray(): array
    {
        return [
            'lat' => $this->lat,
            'lng' => $this->lng,
            'address' => $this->address,
            'place_id' => $this->placeId,
            'name' => $this->name,
        ];
    }
}
