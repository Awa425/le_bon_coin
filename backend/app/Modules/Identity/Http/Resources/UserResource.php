<?php

namespace App\Modules\Identity\Http\Resources;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Profil privé de l'utilisateur connecté (contient des données personnelles : ne jamais l'exposer à un tiers).
 *
 * @mixin User
 */
class UserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'email_verified' => $this->email_verified_at !== null,
            'phone' => $this->phone,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
