<?php

namespace App\Http\Resources\Admin\Kyc;

use App\Http\Resources\Expert\Kyc\KycApplicationResource;
use Illuminate\Http\Request;

class KycApplicationDetailResource extends KycApplicationResource
{
    public function toArray(Request $request): array
    {
        return [
            ...parent::toArray($request),
            'expert' => [
                'id' => $this->expert->id,
                'name' => $this->expert->name,
                'email' => $this->expert->email,
                'country' => $this->expert->country,
                'language' => $this->expert->language,
                'domain' => $this->expert->domain,
                'isActive' => $this->expert->is_active,
                'kycStatus' => $this->expert->kyc_status->value,
            ],
            'reviewedBy' => $this->reviewedBy ? [
                'id' => $this->reviewedBy->id,
                'name' => $this->reviewedBy->name,
            ] : null,
            'statusHistory' => $this->whenLoaded('statusHistories', fn () => $this->statusHistories->map(fn ($history): array => [
                'from' => $history->from_status?->value,
                'to' => $history->to_status->value,
                'actorType' => $history->actor_type->value,
                'actorId' => $history->actor_id,
                'reason' => $history->reason,
                'createdAt' => $history->created_at?->toISOString(),
            ])),
        ];
    }
}
