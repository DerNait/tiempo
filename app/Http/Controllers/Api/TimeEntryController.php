<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreTimeEntryRequest;
use App\Http\Requests\UpdateTimeEntryRequest;
use App\Http\Resources\TimeEntryResource;
use App\Models\TimeEntry;
use App\Services\TimeTrackingService;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Symfony\Component\HttpFoundation\StreamedResponse;

class TimeEntryController extends Controller
{
    public function __construct(private readonly TimeTrackingService $tracking)
    {
    }

    public function index(Request $request): AnonymousResourceCollection
    {
        return TimeEntryResource::collection(
            $this->filtered($request)->paginate($request->integer('per_page', 50))
        );
    }

    public function store(StoreTimeEntryRequest $request): JsonResponse
    {
        $entry = $this->tracking->createManual($request->user(), $request->validated());

        return (new TimeEntryResource($entry->load('category')))->response()->setStatusCode(201);
    }

    public function update(UpdateTimeEntryRequest $request, TimeEntry $timeEntry): TimeEntryResource
    {
        $this->authorize('update', $timeEntry);

        $entry = $this->tracking->updateEntry($timeEntry, $request->validated());

        return new TimeEntryResource($entry->load('category'));
    }

    public function destroy(TimeEntry $timeEntry): JsonResponse
    {
        $this->authorize('delete', $timeEntry);

        $timeEntry->delete();

        return response()->json(['deleted' => true]);
    }

    /**
     * Streamed so a long history never needs to fit in memory.
     */
    public function export(Request $request): StreamedResponse
    {
        $user = $request->user();
        $timezone = $user->effectiveTimezone();
        $query = $this->filtered($request);

        return response()->streamDownload(function () use ($query, $timezone) {
            $handle = fopen('php://output', 'wb');
            fputcsv($handle, [
                'id', 'categoria', 'grupo', 'descripcion',
                'inicio_local', 'fin_local', 'duracion_minutos', 'origen',
            ]);

            $query->chunk(500, function ($entries) use ($handle, $timezone) {
                foreach ($entries as $entry) {
                    $started = CarbonImmutable::instance($entry->started_at)->setTimezone($timezone);
                    $ended = $entry->ended_at !== null
                        ? CarbonImmutable::instance($entry->ended_at)->setTimezone($timezone)
                        : null;

                    fputcsv($handle, [
                        $entry->id,
                        $entry->category?->name,
                        $entry->category?->group_name,
                        $entry->description,
                        $started->format('Y-m-d H:i:s'),
                        $ended?->format('Y-m-d H:i:s'),
                        $ended !== null
                            ? round(($ended->getTimestamp() - $started->getTimestamp()) / 60, 2)
                            : '',
                        $entry->source,
                    ]);
                }
            });

            fclose($handle);
        }, 'tiempo-'.now()->format('Ymd-His').'.csv', [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    private function filtered(Request $request)
    {
        $user = $request->user();
        $timezone = $user->effectiveTimezone();

        return $user->timeEntries()
            ->with('category')
            ->when($request->filled('from'), function ($query) use ($request, $timezone) {
                $from = CarbonImmutable::parse($request->string('from')->toString(), $timezone)->startOfDay();
                $query->where(function ($inner) use ($from) {
                    $inner->whereNull('ended_at')->orWhere('ended_at', '>', $from);
                });
            })
            ->when($request->filled('to'), function ($query) use ($request, $timezone) {
                $to = CarbonImmutable::parse($request->string('to')->toString(), $timezone)->endOfDay();
                $query->where('started_at', '<', $to);
            })
            ->when($request->filled('category_id'), fn ($query) => $query->where('category_id', $request->integer('category_id')))
            ->when($request->filled('search'), function ($query) use ($request) {
                $term = '%'.$request->string('search')->toString().'%';
                $query->where(function ($inner) use ($term) {
                    $inner->where('description', 'like', $term)
                        ->orWhereHas('category', fn ($c) => $c->where('name', 'like', $term));
                });
            })
            ->orderByDesc('started_at');
    }
}
