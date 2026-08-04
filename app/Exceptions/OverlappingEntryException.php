<?php

namespace App\Exceptions;

use App\Models\TimeEntry;
use Illuminate\Http\JsonResponse;
use RuntimeException;

class OverlappingEntryException extends RuntimeException
{
    public function __construct(public readonly TimeEntry $conflict)
    {
        parent::__construct('El rango se solapa con otra actividad registrada.');
    }

    public function render(): JsonResponse
    {
        return response()->json([
            'message' => $this->getMessage(),
            'code' => 'overlapping_entry',
            'conflict' => [
                'id' => $this->conflict->id,
                'category' => $this->conflict->category?->name,
                'started_at' => $this->conflict->started_at?->toIso8601String(),
                'ended_at' => $this->conflict->ended_at?->toIso8601String(),
            ],
        ], 422);
    }
}
