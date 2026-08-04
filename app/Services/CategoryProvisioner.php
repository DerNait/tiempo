<?php

namespace App\Services;

use App\Models\Category;
use App\Models\User;
use App\Support\DefaultCategories;
use Illuminate\Support\Str;

class CategoryProvisioner
{
    /**
     * Give a user the default category set. Existing slugs are left untouched
     * so this is safe to run again on an account that already has data.
     */
    public function seedDefaults(User $user): void
    {
        $existing = $user->categories()->pluck('slug')->all();
        $sortOrder = (int) $user->categories()->max('sort_order');

        foreach (DefaultCategories::all() as $definition) {
            $slug = Str::slug($definition['name']);

            if (in_array($slug, $existing, true)) {
                continue;
            }

            $user->categories()->create([
                'name' => $definition['name'],
                'slug' => $slug,
                'group_name' => $definition['group_name'],
                'icon' => $definition['icon'],
                'color' => $definition['color'],
                'sort_order' => ++$sortOrder,
                'is_active' => true,
                'is_favorite' => $definition['is_favorite'],
            ]);
        }
    }

    /**
     * Slugs are unique per user, so a rename may need a numeric suffix.
     */
    public function uniqueSlug(User $user, string $name, ?int $ignoreId = null): string
    {
        $base = Str::slug($name) ?: 'categoria';
        $slug = $base;
        $suffix = 1;

        while ($this->slugTaken($user, $slug, $ignoreId)) {
            $slug = $base.'-'.(++$suffix);
        }

        return $slug;
    }

    private function slugTaken(User $user, string $slug, ?int $ignoreId): bool
    {
        return Category::query()
            ->where('user_id', $user->getKey())
            ->where('slug', $slug)
            ->when($ignoreId !== null, fn ($query) => $query->whereKeyNot($ignoreId))
            ->exists();
    }
}
