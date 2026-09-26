<?php

namespace App\Http\Controllers\Api\PublicApi;

use App\Enums\ExpertKycStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\Expert\Profile\PublicExpertProfileResource;
use App\Models\ExpertProfile;
use Illuminate\Http\JsonResponse;

class ExpertProfileController extends Controller
{
    public function show(ExpertProfile $profile): JsonResponse
    {
        $profile->load([
            'expert:id,name,country,is_active,kyc_status',
            'expert.availability',
            'expert.verifiedScopes',
        ]);

        $isVisible = $profile->is_published
            && $profile->expert->is_active
            && $profile->expert->kyc_status === ExpertKycStatus::Approved
            && $profile->expert->verifiedScopes->contains(fn ($scope): bool => $scope->isEffective());

        abort_unless($isVisible, 404);

        return response()->json([
            'status' => true,
            'message' => 'Public expert profile retrieved successfully.',
            'data' => ['expert' => new PublicExpertProfileResource($profile)],
        ]);
    }
}
