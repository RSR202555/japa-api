<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    /**
     * Nunca expõe: password, tokens, campos internos sensíveis.
     */
    public function toArray(Request $request): array
    {
        return [
            'id'             => $this->id,
            'name'           => $this->name,
            'email'          => $this->email,
            'phone'          => $this->phone,
            'date_of_birth'  => $this->date_of_birth?->toDateString(),
            'avatar_url'     => $this->avatar_url,
            'is_active'      => $this->is_active,
            'roles'          => $this->getRoleNames(),
            'email_verified' => ! is_null($this->email_verified_at),
            'created_at'     => $this->created_at->toIso8601String(),
            'subscription'   => $this->whenLoaded('subscription', fn () => [
                'id'         => $this->subscription?->id,
                'status'     => $this->subscription?->status,
                'expires_at' => $this->subscription?->expires_at?->toIso8601String(),
                'is_active'  => $this->subscription?->isActive(),
                'plan'       => $this->subscription?->plan ? [
                    'id'    => $this->subscription->plan->id,
                    'name'  => $this->subscription->plan->name,
                    'price' => $this->subscription->plan->price,
                ] : null,
            ]),
        ];
    }
}
