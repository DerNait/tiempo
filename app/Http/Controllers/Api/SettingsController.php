<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateSettingsRequest;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SettingsController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        return response()->json(['user' => $this->payload($request->user())]);
    }

    public function update(UpdateSettingsRequest $request): JsonResponse
    {
        $user = $request->user();
        $data = $request->validated();

        if (array_key_exists('onboarded', $data)) {
            $user->onboarded_at = $data['onboarded'] ? ($user->onboarded_at ?? now()) : null;
            unset($data['onboarded']);
        }

        // Turning the audit on stamps its start; turning it off keeps the
        // stamp so the dates of a finished audit stay readable.
        if (array_key_exists('audit_mode_enabled', $data) && $data['audit_mode_enabled'] && ! $user->audit_mode_enabled) {
            $user->audit_started_at = now();
        }

        $user->fill($data)->save();

        return response()->json(['user' => $this->payload($user->fresh())]);
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(User $user): array
    {
        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'timezone' => $user->effectiveTimezone(),
            'week_starts_on' => $user->week_starts_on,
            'accent_color' => $user->accent_color,
            'audit_mode_enabled' => $user->audit_mode_enabled,
            'audit_started_at' => $user->audit_started_at !== null
                ? CarbonImmutable::instance($user->audit_started_at)->toIso8601String()
                : null,
            'audit_days' => $user->audit_days,
            'onboarded' => $user->onboarded_at !== null,
            'rainmeter_priority_category_id' => $user->rainmeter_priority_category_id,
            'rainmeter_leak_category_id' => $user->rainmeter_leak_category_id,
        ];
    }
}
