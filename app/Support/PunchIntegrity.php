<?php

namespace App\Support;

use Illuminate\Http\Request;

/**
 * What the phone reported about itself alongside the coordinates.
 * Everything here is client-supplied, so it can only ever catch the honest
 * majority — the gates it feeds raise the cost of cheating, they do not close it.
 */
readonly class PunchIntegrity
{
    public function __construct(
        public ?float $gpsAccuracy = null,
        public bool $isMocked = false,
        public bool $isRooted = false,
        public bool $isEmulator = false,
    ) {}

    public static function fromRequest(Request $request): self
    {
        return new self(
            gpsAccuracy: $request->filled('gps_accuracy') ? (float) $request->input('gps_accuracy') : null,
            isMocked: $request->boolean('is_mocked'),
            isRooted: $request->boolean('is_rooted'),
            isEmulator: $request->boolean('is_emulator'),
        );
    }

    /** @return array<string, mixed> */
    public function toAttributes(): array
    {
        return [
            'gps_accuracy' => $this->gpsAccuracy,
            'is_mocked' => $this->isMocked,
            'is_rooted' => $this->isRooted,
            'is_emulator' => $this->isEmulator,
        ];
    }
}
