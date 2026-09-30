<?php

namespace App\Services\Privacy;

use App\Exceptions\PrivacyException;
use App\Models\IdempotencyRecord;
use App\Models\User;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class Idempotency
{
    public function run(Request $request, array $payload, Closure $operation): JsonResponse
    {
        $key = $request->header('Idempotency-Key', '');
        if (! preg_match('/^[A-Za-z0-9._:-]{16,128}$/D', $key)) {
            throw ValidationException::withMessages(['Idempotency-Key' => ['Provide a unique 16 to 128 character request key.']]);
        }
        /** @var User $user */
        $user = $request->user();
        $scope = $request->route()->getName().'|'.implode(':', $request->route()->parameters());
        $hash = hash('sha256', json_encode($this->canonical($payload), JSON_THROW_ON_ERROR));

        return DB::transaction(function () use ($user, $key, $scope, $hash, $operation): JsonResponse {
            User::query()->whereKey($user->id)->lockForUpdate()->firstOrFail();
            $row = IdempotencyRecord::query()->where([
                'actor_type' => 'user', 'actor_id' => $user->id, 'operation' => $scope, 'key_hash' => hash('sha256', $key),
            ])->lockForUpdate()->first();
            if ($row && $row->expires_at->isPast()) {
                $row->delete();
                $row = null;
            }
            if ($row) {
                if (! hash_equals($row->request_hash, $hash)) {
                    throw new PrivacyException('IDEMPOTENCY_CONFLICT', 'This request key has already been used for different input.');
                }

                return response()->json($row->response_body, $row->response_status)->header('Idempotency-Replayed', 'true');
            }
            $response = $operation();
            $row = new IdempotencyRecord;
            $row->forceFill([
                'actor_type' => 'user', 'actor_id' => $user->id, 'operation' => $scope,
                'key_hash' => hash('sha256', $key), 'request_hash' => $hash,
                'response_body' => $response->getData(true), 'response_status' => $response->getStatusCode(),
                'expires_at' => now()->addHours(config('privacy.idempotency_hours')),
            ])->save();

            return $response;
        });
    }

    private function canonical(array $data): array
    {
        if (! array_is_list($data)) {
            ksort($data);
        }
        foreach ($data as &$value) {
            if (is_array($value)) {
                $value = $this->canonical($value);
            }
        }

        return $data;
    }
}
