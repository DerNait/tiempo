<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StartActivityRequest;
use App\Models\Category;
use App\Services\StatusPresenter;
use App\Services\TimeTrackingService;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TrackingController extends Controller
{
    public function __construct(
        private readonly TimeTrackingService $tracking,
        private readonly StatusPresenter $status,
    ) {
    }

    public function show(Request $request): JsonResponse
    {
        return response()->json($this->status->forUser($request->user()));
    }

    public function start(StartActivityRequest $request): JsonResponse
    {
        $user = $request->user();

        /** @var Category $category */
        $category = $user->categories()->findOrFail($request->integer('category_id'));

        $this->tracking->start(
            $user,
            $category,
            $request->input('description'),
            $request->input('source', 'web'),
            $request->filled('started_at')
                ? CarbonImmutable::parse($request->string('started_at')->toString())
                : null,
        );

        return response()->json($this->status->forUser($user->fresh()));
    }

    public function stop(Request $request): JsonResponse
    {
        $user = $request->user();
        $this->tracking->stop($user);

        return response()->json($this->status->forUser($user->fresh()));
    }
}
