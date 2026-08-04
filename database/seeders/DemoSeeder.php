<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\User;
use App\Services\CategoryProvisioner;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;

/**
 * Development fixture: a demo account with a week of varied, non-overlapping
 * entries plus gaps, so every report has something real to show.
 *
 * Refuses to run in production because it wipes the demo user's entries.
 */
class DemoSeeder extends Seeder
{
    private const TIMEZONE = 'America/Guatemala';

    public function run(CategoryProvisioner $provisioner): void
    {
        if (app()->environment('production')) {
            $this->command?->error('DemoSeeder no se ejecuta en producción.');

            return;
        }

        $user = User::query()->firstOrCreate(
            ['email' => 'demo@tiempo.test'],
            [
                'name' => 'Demo',
                'password' => 'password',
                'timezone' => self::TIMEZONE,
                'week_starts_on' => 1,
                'onboarded_at' => now(),
            ],
        );

        $provisioner->seedDefaults($user);
        $user->timeEntries()->delete();

        $categories = $user->categories()->get()->keyBy('name');
        $weekStart = CarbonImmutable::now(self::TIMEZONE)->startOfWeek();

        // Minutes after local midnight → [category, duration]. Deliberately
        // leaves untracked gaps so coverage is below 100%.
        $template = [
            ['Sueño', 0, 6 * 60 + 30],
            ['Higiene', 6 * 60 + 30, 25],
            ['Comidas', 7 * 60, 30],
            ['Transporte', 7 * 60 + 40, 45],
            ['Trabajo DC', 8 * 60 + 30, 3 * 60 + 30],
            ['Comidas', 12 * 60 + 30, 45],
            ['Doom Scrolling', 13 * 60 + 15, 40],
            ['Trabajo DC', 14 * 60, 3 * 60],
            ['Transporte', 17 * 60 + 30, 40],
            ['Entrenamiento', 18 * 60 + 30, 60],
            ['Comidas', 19 * 60 + 45, 40],
            ['Proyecto de Unity', 20 * 60 + 45, 2 * 60],
            ['Doom Scrolling', 22 * 60 + 50, 35],
        ];

        $weekendTemplate = [
            ['Sueño', 0, 8 * 60],
            ['Higiene', 8 * 60, 30],
            ['Comidas', 8 * 60 + 40, 40],
            ['Tareas del Hogar', 9 * 60 + 30, 90],
            ['Proyecto de Unity', 11 * 60 + 30, 3 * 60],
            ['Comidas', 14 * 60 + 45, 45],
            ['Social / Familia', 15 * 60 + 45, 3 * 60],
            ['Ocio', 19 * 60, 2 * 60],
            ['Doom Scrolling', 21 * 60 + 30, 55],
        ];

        $now = CarbonImmutable::now('UTC');
        $created = 0;

        for ($dayOffset = 0; $dayOffset < 7; $dayOffset++) {
            $day = $weekStart->addDays($dayOffset);
            $rows = $dayOffset >= 5 ? $weekendTemplate : $template;

            foreach ($rows as [$categoryName, $offsetMinutes, $durationMinutes]) {
                /** @var Category|null $category */
                $category = $categories->get($categoryName);

                if ($category === null) {
                    continue;
                }

                $start = $day->addMinutes($offsetMinutes)->setTimezone('UTC');
                $end = $start->addMinutes($durationMinutes);

                // Never fabricate time that has not happened yet.
                if ($start->greaterThanOrEqualTo($now)) {
                    continue 2;
                }

                if ($end->greaterThan($now)) {
                    $end = $now;
                }

                if ($end->lessThanOrEqualTo($start)) {
                    continue;
                }

                $user->timeEntries()->create([
                    'category_id' => $category->id,
                    'started_at' => $start,
                    'ended_at' => $end,
                    'duration_seconds' => $end->getTimestamp() - $start->getTimestamp(),
                    'source' => 'system',
                ]);
                $created++;
            }
        }

        $unity = $categories->get('Proyecto de Unity');
        $doom = $categories->get('Doom Scrolling');
        $training = $categories->get('Entrenamiento');

        $budgets = array_filter([
            $unity ? [$unity->id, 'minimum', 600] : null,
            $training ? [$training->id, 'minimum', 240] : null,
            $doom ? [$doom->id, 'maximum', 120] : null,
        ]);

        foreach ($budgets as [$categoryId, $type, $minutes]) {
            $user->weeklyBudgets()->updateOrCreate(
                ['category_id' => $categoryId, 'week_start' => $weekStart->format('Y-m-d')],
                ['budget_type' => $type, 'target_minutes' => $minutes],
            );
        }

        $user->update([
            'rainmeter_priority_category_id' => $unity?->id,
            'rainmeter_leak_category_id' => $doom?->id,
        ]);

        $this->command?->info("Demo listo: {$user->email} / password, {$created} registros.");
    }
}
