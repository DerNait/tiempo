<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ApiTokenController extends Controller
{
    /**
     * Only ever lists metadata: the plain text token is shown once, at
     * creation, and never stored in a readable form.
     */
    public function index(Request $request): JsonResponse
    {
        return response()->json([
            'tokens' => $request->user()->tokens()
                ->orderByDesc('created_at')
                ->get(['id', 'name', 'abilities', 'last_used_at', 'created_at']),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:80'],
        ]);

        // Rainmeter tokens are read-only by construction.
        $token = $request->user()->createToken($validated['name'], ['time:read']);

        return response()->json([
            'token' => $token->plainTextToken,
            'id' => $token->accessToken->getKey(),
            'name' => $validated['name'],
            'abilities' => ['time:read'],
        ], 201);
    }

    public function destroy(Request $request, string $tokenId): JsonResponse
    {
        $deleted = $request->user()->tokens()->whereKey($tokenId)->delete();

        return response()->json(['revoked' => $deleted > 0]);
    }
}
