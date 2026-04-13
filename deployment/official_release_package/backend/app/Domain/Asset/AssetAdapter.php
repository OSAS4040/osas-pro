<?php

namespace App\Domain\Asset;

use App\Models\Vehicle;

/**
 * Thin wrapper around {@see Vehicle} for generalized "asset" domain language.
 * Does not replace the model or table; use {@see underlying()} for full Eloquent behavior.
 */
final class AssetAdapter
{
    public function __construct(private readonly Vehicle $vehicle) {}

    public static function fromVehicle(Vehicle $vehicle): self
    {
        return new self($vehicle);
    }

    public function underlying(): Vehicle
    {
        return $this->vehicle;
    }

    public function id(): int
    {
        return (int) $this->vehicle->getKey();
    }
}
