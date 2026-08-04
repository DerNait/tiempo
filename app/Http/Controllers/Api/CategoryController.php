<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreCategoryRequest;
use App\Http\Requests\UpdateCategoryRequest;
use App\Http\Resources\CategoryResource;
use App\Models\Category;
use App\Services\CategoryProvisioner;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class CategoryController extends Controller
{
    public function __construct(private readonly CategoryProvisioner $provisioner)
    {
    }

    public function index(Request $request): AnonymousResourceCollection
    {
        $query = $request->user()->categories()->orderBy('sort_order')->orderBy('name');

        if (! $request->boolean('include_archived')) {
            $query->where('is_active', true);
        }

        return CategoryResource::collection($query->get());
    }

    public function store(StoreCategoryRequest $request): JsonResponse
    {
        $user = $request->user();
        $data = $request->validated();

        $category = $user->categories()->create([
            'name' => $data['name'],
            'slug' => $this->provisioner->uniqueSlug($user, $data['name']),
            'group_name' => $data['group_name'] ?? null,
            'icon' => $data['icon'] ?? '⏱️',
            'color' => $data['color'] ?? '#8b8b9e',
            'sort_order' => $data['sort_order'] ?? ((int) $user->categories()->max('sort_order') + 1),
            'is_active' => $data['is_active'] ?? true,
            'is_favorite' => $data['is_favorite'] ?? false,
        ]);

        return (new CategoryResource($category))->response()->setStatusCode(201);
    }

    public function update(UpdateCategoryRequest $request, Category $category): CategoryResource
    {
        $this->authorize('update', $category);

        $data = $request->validated();

        if (isset($data['name']) && $data['name'] !== $category->name) {
            $data['slug'] = $this->provisioner->uniqueSlug($request->user(), $data['name'], $category->id);
        }

        $category->update($data);

        return new CategoryResource($category);
    }

    /**
     * Categories with history are archived instead of deleted so past reports
     * keep making sense.
     */
    public function destroy(Request $request, Category $category): JsonResponse
    {
        $this->authorize('delete', $category);

        if ($category->timeEntries()->exists()) {
            $category->update(['is_active' => false, 'is_favorite' => false]);

            return response()->json([
                'message' => 'La categoría tiene registros, así que fue archivada en vez de eliminada.',
                'archived' => true,
            ]);
        }

        $user = $request->user();

        if ($user->rainmeter_priority_category_id === $category->id) {
            $user->update(['rainmeter_priority_category_id' => null]);
        }

        if ($user->rainmeter_leak_category_id === $category->id) {
            $user->update(['rainmeter_leak_category_id' => null]);
        }

        $category->delete();

        return response()->json(['archived' => false]);
    }

    /**
     * Persist a drag-and-drop ordering in one round trip.
     */
    public function reorder(Request $request): AnonymousResourceCollection
    {
        $validated = $request->validate([
            'order' => ['required', 'array'],
            'order.*' => ['integer'],
        ]);

        $user = $request->user();
        $owned = $user->categories()->pluck('id')->all();

        foreach ($validated['order'] as $position => $id) {
            if (in_array((int) $id, $owned, true)) {
                Category::query()->whereKey($id)->update(['sort_order' => $position + 1]);
            }
        }

        return CategoryResource::collection(
            $user->categories()->orderBy('sort_order')->orderBy('name')->get()
        );
    }
}
