<?php

namespace App\Http\Resources;

use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\TimeEntry */
class TimeEntryResource extends JsonResource
{
    /**
     * Timestamps go out as UTC ISO-8601 instants; the client renders them in
     * the user's timezone.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $now = CarbonImmutable::now('UTC');
        $started = CarbonImmutable::instance($this->started_at);
        $ended = $this->ended_at !== null ? CarbonImmutable::instance($this->ended_at) : null;

        return [
            'id' => $this->id,
            'category_id' => $this->category_id,
            'category' => new CategoryResource($this->whenLoaded('category')),
            'description' => $this->description,
            'started_at' => $started->setTimezone('UTC')->toIso8601String(),
            'ended_at' => $ended?->setTimezone('UTC')->toIso8601String(),
            'is_open' => $ended === null,
            'duration_seconds' => $ended !== null
                ? $ended->getTimestamp() - $started->getTimestamp()
                : max(0, $now->getTimestamp() - $started->getTimestamp()),
            'source' => $this->source,
        ];
    }
}
