<?php

namespace App\Modules\Identity\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\Identity\Http\Requests\LoginRequest;
use App\Modules\Identity\Http\Requests\RegisterRequest;
use App\Modules\Identity\Http\Resources\UserResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

/**
 * Authentification par jeton Sanctum (clients API et SSR).
 * Le mode cookie SPA de Sanctum sera branché avec le frontend Angular.
 */
class AuthController extends Controller
{
    public function register(RegisterRequest $request): JsonResponse
    {
        $user = User::create($request->safe()->only(['name', 'email', 'password', 'phone']));

        return $this->tokenResponse($user, $request->input('device_name'), 201);
    }

    public function login(LoginRequest $request): JsonResponse
    {
        $user = User::query()->where('email', $request->validated('email'))->first();

        // Vérification du hachage même si le compte n'existe pas : temps de réponse constant (anti-énumération).
        $hash = $user?->password ?? Hash::make('compte-inexistant');

        if (! Hash::check($request->validated('password'), $hash) || $user === null) {
            throw ValidationException::withMessages([
                'email' => __('auth.failed'),
            ]);
        }

        return $this->tokenResponse($user, $request->input('device_name'));
    }

    public function logout(Request $request): Response
    {
        $request->user()->currentAccessToken()->delete();

        return response()->noContent();
    }

    public function me(Request $request): UserResource
    {
        return new UserResource($request->user());
    }

    private function tokenResponse(User $user, ?string $deviceName, int $status = 200): JsonResponse
    {
        $token = $user->createToken($deviceName ?: 'api')->plainTextToken;

        return (new UserResource($user))
            ->additional(['token' => $token, 'token_type' => 'Bearer'])
            ->response()
            ->setStatusCode($status);
    }
}
